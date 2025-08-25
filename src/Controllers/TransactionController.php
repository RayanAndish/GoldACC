<?php

namespace App\Controllers;

use App\Core\ViewRenderer;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Repositories\AssayOfficeRepository;
use App\Repositories\ContactRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\TransactionItemRepository;
use App\Repositories\TransactionRepository;
use App\Utils\Helper;
use Exception;
use Monolog\Logger;
use PDO;
use Throwable;

class TransactionController extends AbstractController
{
    private TransactionRepository $transactionRepository;
    private TransactionItemRepository $transactionItemRepository;
    private ProductRepository $productRepository;
    private ContactRepository $contactRepository;
    private AssayOfficeRepository $assayOfficeRepository;
    private SettingsRepository $settingsRepository;

    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        $this->transactionRepository = $services['transactionRepository'];
        $this->transactionItemRepository = $services['transactionItemRepository'];
        $this->productRepository = $services['productRepository'];
        $this->contactRepository = $services['contactRepository'];
        $this->assayOfficeRepository = $services['assayOfficeRepository'];
        $this->settingsRepository = $services['settingsRepository'];
    }

    /**
     * Displays the list of transactions.
     */
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

    /**
     * Displays the form for adding a new transaction.
     * Route: /app/transactions/add (GET)
     */
    public function showAddForm(): void
    {
        $this->showForm(null);
    }

    /**
     * Displays the form for editing an existing transaction.
     * Route: /app/transactions/edit/{id} (GET)
     */
    public function showEditForm(int $id): void
    {
        $this->showForm($id);
    }

    /**
     * Unified private method to show the Add/Edit form.
     * This is called by showAddForm and showEditForm.
     */
    private function showForm(?int $id = null): void
    {
        $this->requireLogin();
        $isEditMode = $id !== null;
        $pageTitle = $isEditMode ? "ویرایش معامله #{$id}" : "ثبت معامله جدید";
        $loadingError = null;
        $transactionData = null;
        $transactionItemsData = [];

        if ($isEditMode) {
            try {
                $transactionData = $this->transactionRepository->findByIdWithItems($id);
                if (!$transactionData) {
                    $this->setFlashMessage("معامله مورد نظر یافت نشد.", 'warning');
                    $this->redirect('/app/transactions');
                    return;
                }
                $transactionItemsData = $transactionData['items'] ?? [];
            } catch (Throwable $e) {
                $this->logger->error("Error loading transaction for edit form.", ['exception' => $e]);
                $loadingError = "خطا در بارگذاری اطلاعات معامله.";
            }
        }
        
        list($fields, $formulas) = $this->loadFieldsAndFormulas();

        $productsData = $this->getProductsWithDetails();

        $this->render('transactions/form', [
            'page_title' => $pageTitle,
            'form_action' => $this->config['app']['base_url'] . '/app/transactions/save' . ($isEditMode ? '/' . $id : ''),
            'is_edit_mode' => $isEditMode,
            'transactionData' => $transactionData,
            'transactionItemsData' => $transactionItemsData,
            'contactsData' => $this->contactRepository->getAll(),
            'assayOfficesData' => $this->assayOfficeRepository->getAll(),
            'productsData' => $productsData,
            'fieldsData' => $fields,
            'formulasData' => $formulas,
            'default_settings' => [
                'tax_rate' => $this->settingsRepository->get('tax_rate', 0),
                'vat_rate' => $this->settingsRepository->get('vat_rate', 0),
            ],
            'loading_error' => $loadingError,
            'csrf_token' => Helper::generateCsrfToken(),
            'baseUrl' => $this->config['app']['base_url'],
            'config' => $this->config,
        ]);
    }

    /**
     * Unified method to save a transaction (Add or Edit).
     */
    public function save(?int $id = null): void
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/transactions');
            return;
        }

        if (!Helper::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->sendJsonResponse(false, "خطای امنیتی (CSRF). لطفاً صفحه را رفرش کنید.");
            return;
        }

        $isEditMode = $id !== null;
        $postData = $_POST;
        $this->logger->debug("Raw POST data received for save.", ['post_data' => $postData, 'is_edit' => $isEditMode]);

        $errors = $this->validateTransactionData($postData);
        if (!empty($errors)) {
            $this->sendJsonResponse(false, "خطا در اعتبارسنجی فرم.", ['errors' => $errors]);
            return;
        }

        $this->db->beginTransaction();
        try {
            $transaction = new Transaction();
            $transaction->id = $isEditMode ? $id : null;
            $transaction->transaction_type = $postData['transaction_type'];
            $transaction->transaction_date = Helper::parseJalaliDatetimeToSql($postData['transaction_date']);
            $transaction->counterparty_contact_id = (int)$postData['counterparty_contact_id'];
            $transaction->mazaneh_price = Helper::sanitizeFormattedNumber($postData['mazaneh_price'] ?? '0');
            $transaction->delivery_status = $postData['delivery_status'];
            $transaction->notes = trim($postData['notes'] ?? '');

            // IMPROVEMENT: Fetch all products at once using the new efficient method.
            $productIds = array_unique(array_filter(array_column($postData['items'] ?? [], 'product_id')));
            $productsById = !empty($productIds) ? $this->productRepository->findByIds($productIds) : [];
            
            $itemsToSave = [];
            foreach ($postData['items'] as $itemData) {
                if (empty($itemData['product_id'])) continue;
                
                $product = $productsById[$itemData['product_id']] ?? null;
                if (!$product) throw new Exception("محصول با شناسه {$itemData['product_id']} یافت نشد.");

                $item = new TransactionItem();
                $item->id = !empty($itemData['id']) ? (int)$itemData['id'] : null;
                $item->product_id = (int)$itemData['product_id'];

                // FIX: Map form data robustly, converting empty strings to null.
                $this->mapItemData($item, $itemData, $product->category->base_category);
                
                // Recalculate all financial values on the server for security.
                $this->recalculateItemFormulas($item, $transaction, $product);
                
                $itemsToSave[] = $item;
            }

            if (empty($itemsToSave)) {
                throw new Exception("هیچ قلم کالای معتبری برای ذخیره وجود ندارد.");
            }

            $this->recalculateTransactionSummary($transaction, $itemsToSave);
            
            $savedTransactionId = $this->transactionRepository->save($transaction);
            
            if ($isEditMode) {
                $itemIdsToKeep = array_filter(array_column($itemsToSave, 'id'));
                $this->transactionItemRepository->deleteRemovedItems($savedTransactionId, $itemIdsToKeep);
            }

            foreach ($itemsToSave as $item) {
                $item->transaction_id = $savedTransactionId;
                $this->transactionItemRepository->save($item);
            }

            $this->db->commit();
            $this->logger->info("Transaction saved successfully.", ['id' => $savedTransactionId, 'is_edit' => $isEditMode]);
            
            $message = $isEditMode ? "معامله با موفقیت به‌روزرسانی شد." : "معامله با موفقیت ثبت شد.";
            $this->setFlashMessage($message, 'success');
            $this->sendJsonResponse(true, $message, ['redirect_url' => $this->config['app']['base_url'] . '/app/transactions']);

        } catch (Throwable $e) {
            $this->db->rollBack();
            $this->logger->error("Critical error saving transaction.", ['exception' => $e, 'post_data' => $postData]);
            $this->sendJsonResponse(false, "خطای سیستمی در ذخیره معامله: " . $e->getMessage());
        }
    }

    /**
     * Deletes a transaction.
     */
    public function delete(int $id): void
    {
        $this->requireLogin();
        
        try {
            $this->db->beginTransaction();
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

    // ===================================================================
    // PRIVATE HELPER METHODS
    // ===================================================================

    private function getProductsWithDetails(): array
    {
        $sql = "SELECT p.*, pc.name as category_name, pc.base_category 
                FROM products p 
                JOIN product_categories pc ON p.category_id = pc.id 
                WHERE p.is_active = 1 
                ORDER BY pc.name, p.name";
        $stmt = $this->db->query($sql);
        $productsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $productsData = [];
        foreach ($productsRaw as $p) {
            $productsData[] = [
                'id' => (int)$p['id'],
                'name' => $p['name'],
                'tax_enabled' => isset($p['tax_enabled']) ? (bool)$p['tax_enabled'] : false,
                'tax_rate' => isset($p['tax_rate']) ? (float)$p['tax_rate'] : 0.0,
                'vat_enabled' => isset($p['vat_enabled']) ? (bool)$p['vat_enabled'] : false,
                'vat_rate' => isset($p['vat_rate']) ? (float)$p['vat_rate'] : 0.0,
                'category' => [
                    'name' => $p['category_name'],
                    'base_category' => $p['base_category']
                ]
            ];
        }
        return $productsData;
    }

    private function mapItemData(TransactionItem &$item, array $itemData, string $baseCategory): void
    {
        $group = strtolower($baseCategory);

        // Helper function to sanitize and nullify if empty or not numeric.
        $sanitize = function($value) {
            if ($value === null || $value === '') {
                return null; // Convert empty string to NULL for DB
            }
            // Sanitize to get a clean number string
            $cleaned = Helper::sanitizeFormattedNumber($value);
            // Return as float if numeric, otherwise null
            return is_numeric($cleaned) ? (float)$cleaned : null;
        };
        
        // Helper for string values that can be empty
        $stringOrNull = fn($value) => ($value === null || $value === '') ? null : trim($value);

        $item->quantity = $sanitize($itemData["item_quantity_{$group}"] ?? null);
        $item->weight_grams = $sanitize($itemData["item_weight_scale_{$group}"] ?? null);
        $item->carat = $sanitize($itemData["item_carat_{$group}"] ?? null);
        $item->unit_price_rials = $sanitize($itemData["item_unit_price_{$group}"] ?? null);
        $item->tag_number = $stringOrNull($itemData["item_tag_number_{$group}"] ?? null);
        $item->assay_office_id = $sanitize($itemData["item_assay_office_melted"] ?? null);
        $item->coin_year = $sanitize($itemData["item_coin_year_{$group}"] ?? null);
        $item->seal_name = $stringOrNull($itemData["item_vacuum_name_{$group}"] ?? null);
        $item->is_bank_coin = isset($itemData['item_type_coin']) && $itemData['item_type_coin'] === 'bank';
        $item->ajrat_rials = $sanitize($itemData["item_manufacturing_fee_rate_{$group}"] ?? null); // This is a rate from form
        $item->workshop_name = $stringOrNull($itemData["item_workshop_{$group}"] ?? null);
        $item->stone_weight_grams = $sanitize($itemData["item_attachment_weight_{$group}"] ?? null);
        $item->description = trim($itemData['item_description'] ?? '');
        $item->profit_percent = $sanitize($itemData["item_profit_percent_{$group}"] ?? '0');
        $item->fee_percent = $sanitize($itemData["item_fee_percent_{$group}"] ?? '0');
    }

    private function recalculateItemFormulas(TransactionItem &$item, Transaction $transaction, object $product): void
    {
        $group = strtolower($product->category->base_category);
        $item->weight_750 = ($item->weight_grams && $item->carat) ? round(($item->weight_grams * $item->carat) / 750, 3) : 0;

        // قیمت کل فقط عدد صحیح (بدون اعشار)
        if ($group === 'coin' || $group === 'jewelry') {
            $item->total_value_rials = round(($item->quantity ?: 1) * ($item->unit_price_rials ?: 0));
        } else {
            $unitPrice750 = $transaction->mazaneh_price > 0 ? $transaction->mazaneh_price / 4.3318 : 0;
            $item->total_value_rials = round($item->weight_750 * $unitPrice750);
        }

        $baseForProfit = $item->total_value_rials;
        $item->profit_amount = round($baseForProfit * (($item->profit_percent ?? 0) / 100));
        $item->fee_amount = round($baseForProfit * (($item->fee_percent ?? 0) / 100));

        // مقداردهی پیش‌فرض برای مالیات‌ها
        $item->general_tax = 0;
        $item->vat = 0;

        if (!empty($product->tax_enabled)) {
            $item->general_tax = round($baseForProfit * (($product->tax_rate ?? 0) / 100));
        }

        if (!empty($product->vat_enabled)) {
            $baseForVat = ($item->profit_amount ?? 0) + ($item->fee_amount ?? 0);
            if ($group === 'manufactured') {
                $baseForVat += ($item->ajrat_rials ?? 0);
            }
            $item->vat = round($baseForVat * (($product->vat_rate ?? 0) / 100));
        }
    }

    private function recalculateTransactionSummary(Transaction &$transaction, array $items): void
    {
        $transaction->total_items_value_rials = array_sum(array_column($items, 'total_value_rials'));
        $transaction->total_profit_wage_commission_rials = array_sum(array_column($items, 'profit_amount')) + array_sum(array_column($items, 'fee_amount')) + array_sum(array_column($items, 'ajrat_rials'));
        $transaction->total_general_tax_rials = array_sum(array_column($items, 'general_tax'));
        $transaction->total_vat_rials = array_sum(array_column($items, 'vat'));
        $transaction->total_before_vat_rials = $transaction->total_items_value_rials + $transaction->total_profit_wage_commission_rials + $transaction->total_general_tax_rials;
        $transaction->final_payable_amount_rials = $transaction->total_before_vat_rials + $transaction->total_vat_rials;
    }

    private function validateTransactionData(array $postData): array
    {
        $errors = [];
        if (empty($postData['transaction_type'])) $errors[] = "نوع معامله الزامی است.";
        if (empty($postData['counterparty_contact_id'])) $errors[] = "انتخاب طرف حساب الزامی است.";
        if (empty($postData['transaction_date'])) $errors[] = "تاریخ معامله الزامی است.";
        if (!isset($postData['items']) || !is_array($postData['items']) || empty($postData['items'])) {
            $errors[] = "حداقل یک قلم کالا باید در معامله وجود داشته باشد.";
        }
        return $errors;
    }

    private function sendJsonResponse(bool $success, string $message, array $data = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message] + $data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function loadFieldsAndFormulas(): array
    {
        $fieldsPath = $this->config['paths']['config'] . '/fields.json';
        $formulasPath = $this->config['paths']['config'] . '/formulas.json';
        $fields = json_decode(file_get_contents($fieldsPath), true)['fields'] ?? [];
        $formulas = json_decode(file_get_contents($formulasPath), true)['formulas'] ?? [];
        return [$fields, $formulas];
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
