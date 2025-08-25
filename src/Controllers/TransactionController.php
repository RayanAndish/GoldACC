<?php
// src/Controllers/TransactionController.php
namespace App\Controllers;

use App\Core\ViewRenderer;
use App\Models\Product;
use App\Repositories\AssayOfficeRepository;
use App\Repositories\ContactRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use App\Services\TransactionService;
use App\Utils\Helper;
use Monolog\Logger;
use PDO;
use Throwable;

class TransactionController extends AbstractController
{
    private TransactionRepository $transactionRepository;
    private ProductRepository $productRepository;
    private ContactRepository $contactRepository;
    private AssayOfficeRepository $assayOfficeRepository;
    private TransactionService $transactionService;
    private \App\Repositories\TransactionItemRepository $transactionItemRepository; 

    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        $this->transactionRepository = $services['transactionRepository'];
        $this->productRepository = $services['productRepository'];
        $this->contactRepository = $services['contactRepository'];
        $this->assayOfficeRepository = $services['assayOfficeRepository'];
        $this->transactionItemRepository = $services['transactionItemRepository'];
        
        if (!isset($services['transactionService']) || !$services['transactionService'] instanceof TransactionService) {
            throw new \Exception('TransactionService missing or invalid.');
        }
        $this->transactionService = $services['transactionService'];
    }

    public function index(): void
    {
        $this->requireLogin();
        $pageTitle = "مدیریت معاملات";

        $itemsPerPage = (int)($this->config['app']['items_per_page'] ?? 15);
        $currentPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $searchTerm = trim(filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? '');
        $filters = [
            'type' => filter_input(INPUT_GET, 'type', FILTER_DEFAULT),
            'contact_id' => filter_input(INPUT_GET, 'contact', FILTER_VALIDATE_INT),
            'status' => filter_input(INPUT_GET, 'status', FILTER_DEFAULT),
            'start_date_jalali' => trim(filter_input(INPUT_GET, 'start_date', FILTER_DEFAULT) ?? ''),
            'end_date_jalali' => trim(filter_input(INPUT_GET, 'end_date', FILTER_DEFAULT) ?? ''),
        ];

        $filters['start_date_sql'] = Helper::parseJalaliDateToSql($filters['start_date_jalali']);
        $filters['end_date_sql'] = Helper::parseJalaliDateToSql($filters['end_date_jalali'], true);

        $activeFilters = array_filter($filters, fn($value) => $value !== null && $value !== '' && $value !== false);

        try {
            $totalRecords = $this->transactionRepository->countFiltered($activeFilters, $searchTerm);
            $paginationData = Helper::generatePaginationData($currentPage, $itemsPerPage, $totalRecords);
            
            $transactions = $this->transactionRepository->getFilteredAndPaginated(
                $activeFilters,
                $searchTerm,
                $itemsPerPage,
                $paginationData['offset']
            );

            foreach ($transactions as &$tx) {
                $this->formatTransactionForListView($tx);
            }
            unset($tx);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching transactions list.", ['exception' => $e]);
            $this->setFlashMessage("خطا در بارگذاری لیست معاملات.", 'danger');
            $transactions = [];
            $paginationData = Helper::generatePaginationData(1, $itemsPerPage, 0);
        }

        $this->render('transactions/list', [
            'page_title' => $pageTitle,
            'transactions' => $transactions,
            'pagination' => $paginationData,
            'search_term' => htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'),
            'filters' => $filters,
            'contacts_for_filter' => $this->contactRepository->getAll(),
            'delivery_statuses' => Helper::getDeliveryStatusOptions(true),
            'csrf_token' => Helper::generateCsrfToken(),
            'baseUrl' => $this->config['app']['base_url'],
        ]);
    }


    public function showAddForm(): void
    {
        $this->showForm(null);
    }

    public function showEditForm(int $id): void
    {
        $this->showForm($id);
    }

    private function showForm(?int $id): void
    {
        $this->requireLogin();
        $isEditMode = $id !== null;
        $pageTitle = $isEditMode ? "ویرایش معامله #" . htmlspecialchars($id) : "ثبت معامله جدید";
        $loadingError = null;
        $transactionData = [];
        $transactionItemsData = [];
        
        if (!$isEditMode) {
            $transactionData['transaction_date_persian'] = Helper::formatPersianDate('now');
        }

        
        if ($isEditMode) {
            try {
                $dbTransaction = $this->transactionRepository->findByIdWithItems($id);
                if (!$dbTransaction) {
                    $this->setSessionMessage("معامله مورد نظر یافت نشد.", 'warning');
                    $this->redirect('/app/transactions');
                    return;
                }
                
                $transactionData = $dbTransaction;
                // **اصلاح کلیدی: اطمینان از وجود شناسه در داده‌های اصلی**
                $transactionData['id'] = $id;

                if (!empty($transactionData['transaction_date'])) {
                    $transactionData['transaction_date_persian'] = Helper::formatPersianDate($transactionData['transaction_date']);
                }
                
                if (!empty($transactionData['items'])) {
                    foreach ($transactionData['items'] as $item) {
                        $transactionItemsData[] = $this->mapItemKeysForForm($item);
                    }
                }

            } catch (Throwable $e) {
                $this->logger->error("Error loading transaction for edit form.", ['id' => $id, 'exception' => $e]);
                $loadingError = "خطا در بارگذاری اطلاعات معامله.";
            }
        }
       
        
        list($fields, $formulas) = $this->loadFieldsAndFormulas();
        
        $productsFromRepo = $this->productRepository->getAllActiveWithCategory();
        
        $productsForJs = array_map(function(\App\Models\Product $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'unit_of_measure' => $product->unit_of_measure,
                'general_tax_base_type' => $product->general_tax_base_type,
                'tax_rate' => $product->tax_rate,
                'vat_base_type' => $product->vat_base_type,
                'vat_rate' => $product->vat_rate,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'code' => $product->category->code ?? null,
                    'base_category' => $product->category->base_category,
                    'unit_of_measure' => $product->category->unit_of_measure ?? null,
                ] : null
            ];
        }, $productsFromRepo);

        $this->render('transactions/form', [
            'page_title' => $pageTitle,
            'is_edit_mode' => $isEditMode,
            'transactionData' => $transactionData,
            'transactionItemsData' => $transactionItemsData,
            'contactsData' => $this->contactRepository->getAll(),
            'assayOfficesData' => $this->assayOfficeRepository->getAll(),
            'productsData' => $productsForJs,
            'fieldsData' => $fields,
            'formulasData' => $formulas,
            'loading_error' => $loadingError,
            'baseUrl' => $this->config['app']['base_url'],
            'config' => $this->config, // برای دسترسی به تنظیمات در view
        ]);
    }


    public function save(?int $id = null): void
    {
        $this->requireLogin();
        
        $requestData = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $jsonInput = file_get_contents('php://input');
            $requestData = json_decode($jsonInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->jsonResponse(['success' => false, 'message' => 'درخواست نامعتبر (JSON).'], 400);
                return;
            }
        } else {
            $this->redirect('/app/transactions');
            return;
        }

        if (!Helper::verifyCsrfToken($requestData['csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'message' => "خطای امنیتی (CSRF)."], 403);
            return;
        }
        
        try {
            $savedId = $this->transactionService->saveTransaction($requestData, $id);
            
            $isEditMode = $id !== null;
            $message = $isEditMode ? "معامله با موفقیت به‌روزرسانی شد." : "معامله با موفقیت ثبت شد.";
            $this->setFlashMessage($message, 'success');
            
            $responseData = [
                'success' => true,
                'message' => $message,
                'redirect_url' => $this->config['app']['base_url'] . '/app/transactions'
            ];
            $this->jsonResponse($responseData, 200);

        } catch (Throwable $e) {
            $this->logger->error("Transaction save controller error.", ['exception' => $e]);
            $errorData = [ 'success' => false, 'message' => "خطا در ذخیره معامله: " . $e->getMessage() ];
            if ($this->config['app']['debug']) {
                $errorData['message'] .= " (Detail: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . ")";
            }
            $this->jsonResponse($errorData, 500);
        }
    }
    
    /**
     * Maps database column names (and computed values from backend processing) to the expected form field names for an item when loading for edit.
     * This ensures the frontend form displays existing data correctly matching the JS-rendered dynamic field names.
     * REVISED for unified 'bullion' group and better defaults.
     */

     /**
     * (نهایی و کامل) کلیدهای دیتابیس را به نام فیلدهای فرم جاوا اسکریپت ترجمه می‌کند.
     */
   /**
     * (نسخه نهایی بر اساس لیست دقیق شما) کلیدهای دیتابیس را به نام فیلدهای فرم ترجمه می‌کند.
     */
 /**
     * (نسخه نهایی) کلیدهای دیتابیس را به نام فیلدهای فرم ترجمه می‌کند.
     */
 
    /**
     * (نسخه نهایی) کلیدهای دیتابیس را به نام فیلدهای فرم ترجمه می‌کند.
     */

  private function mapItemKeysForForm(array $itemData): array
    {
        $mappedData = $itemData;
        $baseCategory = strtolower($itemData['base_category'] ?? '');
        
        $mappedData['id'] = $itemData['item_id'];
        $mappedData['item_product_id'] = $itemData['product_id'];
        $mappedData['item_description'] = $itemData['item_description'];
        $mappedData["item_weight_scale_{$baseCategory}"] = $itemData['weight_grams'];
        $mappedData["item_carat_{$baseCategory}"] = $itemData['carat'];
        $mappedData["item_unit_price_{$baseCategory}"] = $itemData['unit_price_rials'];
        $mappedData["item_profit_percent_{$baseCategory}"] = $itemData['profit_percent'];
        $mappedData["item_fee_percent_{$baseCategory}"] = $itemData['fee_percent'];

        switch ($baseCategory) {
            case 'melted':
                $mappedData['item_tag_number_melted'] = $itemData['tag_number'];
                $mappedData['item_assay_office_melted'] = $itemData['assay_office_id'];
                $mappedData['item_tag_type_melted'] = $itemData['tag_type'];
                break;
            case 'manufactured':
                $mappedData['item_quantity_manufactured'] = $itemData['quantity'];
                $mappedData['item_type_manufactured'] = $itemData['manufactured_item_type'];
                $mappedData['item_has_attachments_manufactured'] = ($itemData['has_attachments'] == 1) ? 'yes' : 'no';
                $mappedData['item_attachment_type_manufactured'] = $itemData['attachment_type'];
                $mappedData['item_attachment_weight_manufactured'] = $itemData['stone_weight_grams'];
                $mappedData['item_workshop_manufactured'] = $itemData['workshop_name'];
                if (isset($itemData['ajrat_percent']) && (float)$itemData['ajrat_percent'] > 0) {
                    $mappedData['item_manufacturing_fee_type_manufactured'] = 'percent';
                    $mappedData['item_manufacturing_fee_rate_manufactured'] = $itemData['ajrat_percent'];
                } elseif (isset($itemData['ajrat_rials']) && (float)$itemData['ajrat_rials'] > 0) {
                    $mappedData['item_manufacturing_fee_type_manufactured'] = 'amount';
                    $mappedData['item_manufacturing_fee_amount_manufactured'] = $itemData['ajrat_rials'];
                }
                break;
            case 'coin':
                $mappedData['item_quantity_coin'] = $itemData['quantity'];
                $mappedData['item_coin_year_coin'] = $itemData['coin_year'];
                $mappedData['item_type_coin'] = ($itemData['is_bank_coin'] == 1) ? 'bank' : 'misc';
                $mappedData['item_vacuum_name_coin'] = $itemData['seal_name'];
                break;
            case 'bullion':
                $mappedData['item_bullion_number_bullion'] = $itemData['tag_number'];
                $mappedData['item_manufacturer_bullion'] = $itemData['workshop_name'];
                break;
            case 'jewelry':
                $mappedData['item_quantity_jewelry'] = $itemData['quantity'];
                $mappedData['item_weight_carat_jewelry'] = $itemData['weight_grams'];
                $mappedData['item_unit_price_jewelry'] = $itemData['unit_price_rials'];
                $mappedData['item_type_jewelry'] = $itemData['jewelry_type'];
                $mappedData['item_color_jewelry'] = $itemData['jewelry_color'];
                $mappedData['item_quality_grade_jewelry'] = $itemData['jewelry_quality'];
                break;
        }
        return $mappedData;
    }


    public function delete(int $id): void
    {
        $this->requireLogin();
        
        try {
            $this->db->beginTransaction();
            if (isset($this->services['contactWeightLedgerRepository'])) {
                $this->services['contactWeightLedgerRepository']->deleteByTransactionId($id);
            } else {
                $this->logger->warning("contactWeightLedgerRepository service not found for delete operation.");
            }
            
            $this->transactionItemRepository->deleteByTransactionId($id);
            $this->transactionRepository->delete($id);
            $this->db->commit();
            $this->setFlashMessage("معامله با موفقیت حذف شد.", 'success');
        } catch (Throwable $e) {
            $this->db->rollBack();
            $this->logger->error("Error deleting transaction.", ['id' => $id, 'exception' => $e]);
            $this->setFlashMessage("خطا در حذف معامله.", 'danger');
        }
        $this->redirect('/app/transactions');
    }

    private function loadFieldsAndFormulas(): array
    {
        $fieldsPath = $this->config['paths']['config'] . '/fields.json';
        $formulasPath = $this->config['paths']['config'] . '/formulas.json';
        
        if (!file_exists($fieldsPath) || !is_readable($fieldsPath)) {
            $this->logger->error("fields.json not found or not readable at: " . $fieldsPath);
            throw new Exception("File not found or not readable: fields.json");
        }
        if (!file_exists($formulasPath) || !is_readable($formulasPath)) {
            $this->logger->error("formulas.json not found or not readable at: " . $formulasPath);
            throw new Exception("File not found or not readable: formulas.json");
        }

        $fields = json_decode(file_get_contents($fieldsPath), true)['fields'] ?? [];
        $formulas = json_decode(file_get_contents($formulasPath), true)['formulas'] ?? [];

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = "JSON Decode Error in config files: " . json_last_error_msg();
            $this->logger->error($errorMessage, ['fields_json_error' => (json_last_error() === JSON_ERROR_NONE ? null : json_last_error_msg()), 'formulas_json_error' => (json_last_error() === JSON_ERROR_NONE ? null : json_last_error_msg())]);
            throw new Exception($errorMessage);
        }

        return [$fields, $formulas];
    }

    /**
     * Handles the completion of a transaction's delivery status (receipt or delivery).
     * Route: /app/transactions/complete-delivery/{id}/{action} (POST)
     * @param int $id The transaction ID.
     * @param string $action 'receipt' for pending_receipt, 'delivery' for pending_delivery.
     */
    public function completeDeliveryAction(int $id, string $action): void
    {
        $this->requireLogin();

        // Validate action type
        if (!in_array($action, ['receipt', 'delivery'])) {
            $this->setFlashMessage('عمل نامعتبر.', 'danger');
            $this->redirect('/app/transactions');
            return;
        }

        // Basic CSRF protection. Assuming form submits csrf_token as POST parameter.
        // As it's an API-like route without a form in the original code, ensure you validate request or csrf helper does.
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrfToken($csrfToken)) {
            $this->setFlashMessage("خطای امنیتی (CSRF).", 'danger');
            $this->redirect('/app/transactions');
            return;
        }

        try {
            $result = $this->transactionService->completeDelivery($id, $action);

            if ($result) {
                $this->setFlashMessage("وضعیت تحویل معامله با موفقیت به‌روزرسانی شد و موجودی به روز شد.", 'success');
            } else {
                $this->setFlashMessage("عملیات ناموفق بود. معامله در وضعیت صحیح برای این عمل نیست یا مشکلی پیش آمد.", 'warning');
            }
        } catch (Throwable $e) {
            $this->logger->error("Error completing delivery action.", ['id' => $id, 'action' => $action, 'exception' => $e]);
            $this->setFlashMessage("خطا در به‌روزرسانی وضعیت تحویل: " . $e->getMessage(), 'danger');
        }

        $this->redirect('/app/transactions');
    }


    private function formatTransactionForListView(array &$tx): void
    {
        $tx['transaction_type_farsi'] = match ($tx['transaction_type'] ?? '') {
            'buy' => 'خرید', 'sell' => 'فروش', default => '؟'
        };
        $tx['final_payable_amount_rials_formatted'] = Helper::formatRial($tx['final_payable_amount_rials'] ?? 0);
        $tx['transaction_date_persian'] = Helper::formatPersianDateTime($tx['transaction_date']);
        $tx['delivery_status_farsi'] = Helper::translateDeliveryStatus($tx['delivery_status'] ?? '');
        $tx['delivery_status_class'] = Helper::getDeliveryStatusClass($tx['delivery_status'] ?? '');
        $tx['can_complete_delivery'] = ($tx['delivery_status'] ?? '') === 'pending_delivery';
        $tx['can_complete_receipt'] = ($tx['delivery_status'] ?? '') === 'pending_receipt';
    }
}