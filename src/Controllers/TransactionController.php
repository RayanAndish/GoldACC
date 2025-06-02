<?php

namespace App\Controllers;

use PDO;
use Monolog\Logger;
use Throwable;
use Exception;
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;
use App\Repositories\TransactionRepository;
use App\Repositories\ContactRepository;
use App\Repositories\AssayOfficeRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionItemRepository;
use App\Repositories\InventoryLedgerRepository;
use App\Services\DeliveryService;
use App\Services\FormulaService;
use App\Models\TransactionItem;
use App\Utils\Helper;
use App\Repositories\SettingsRepository;
use Morilog\Jalali\Jalalian; // اضافه شده برای استفاده از Jalalian

class TransactionController extends AbstractController
{
    private TransactionRepository $transactionRepository;
    private ContactRepository $contactRepository;
    private AssayOfficeRepository $assayOfficeRepository;
    private ProductRepository $productRepository;
    private TransactionItemRepository $transactionItemRepository;
    private InventoryLedgerRepository $inventoryLedgerRepository;
    private DeliveryService $deliveryService;
    private FormulaService $formulaService;
    private SettingsRepository $settingsRepository;

    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        $required = [
            'transactionRepository' => TransactionRepository::class,
            'contactRepository' => ContactRepository::class,
            'assayOfficeRepository' => AssayOfficeRepository::class,
            'productRepository' => ProductRepository::class,
            'transactionItemRepository' => TransactionItemRepository::class,
            'inventoryLedgerRepository' => InventoryLedgerRepository::class,
            'deliveryService' => DeliveryService::class,
            'settingsRepository' => SettingsRepository::class,
        ];
        foreach ($required as $prop => $class) {
            if (!isset($services[$prop]) || !$services[$prop] instanceof $class) {
                throw new Exception("{$class} not found or invalid for TransactionController.");
            }
            $this->$prop = $services[$prop];
        }
        // FIX: FormulaService باید از services تزریق شود یا به درستی مقداردهی شود
        // فرض می‌کنیم FormulaService در index.php به services اضافه شده است.
        if (!isset($services['formulaService']) || !$services['formulaService'] instanceof FormulaService) {
            // اگر FormulaService به عنوان یک سرویس تزریق نشده، آن را اینجا مقداردهی اولیه می‌کنیم.
            // این حالت ایده‌آل نیست و بهتر است از کانتینر DI استفاده شود.
            $fieldsJson = $this->config['app']['global_json_strings']['fields'] ?? '[]';
            $formulasJson = $this->config['app']['global_json_strings']['formulas'] ?? '[]';
            $fieldsData = json_decode($fieldsJson, true)['fields'] ?? [];
            $formulasData = json_decode($formulasJson, true)['formulas'] ?? [];
            $this->formulaService = new FormulaService($this->logger, $formulasData, $fieldsData);
        } else {
            $this->formulaService = $services['formulaService'];
        }
    }
    
    public function index(): void
    {
        $this->requireLogin();
        // Optional: Permission check

        $pageTitle = "مدیریت معاملات طلا";
        $transactions = [];
        $paginationData = [];
        $errorMessage = $this->getFlashMessage('transaction_error');
        $successMessage = $this->getFlashMessage('transaction_success');

        // --- Filtering, Searching, Pagination ---
        $itemsPerPage = (int)($this->config['app']['items_per_page'] ?? 15);
        $currentPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $searchTerm = trim(filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? '');
        $filters = [ // Read filters from GET
            'type' => filter_input(INPUT_GET, 'type', FILTER_DEFAULT),
            'contact_id' => filter_input(INPUT_GET, 'contact', FILTER_VALIDATE_INT),
            'status' => filter_input(INPUT_GET, 'status', FILTER_DEFAULT),
            'start_date_jalali' => trim(filter_input(INPUT_GET, 'start_date', FILTER_DEFAULT) ?? ''), // Keep Jalali for form repopulation
            'end_date_jalali' => trim(filter_input(INPUT_GET, 'end_date', FILTER_DEFAULT) ?? ''),
        ];

        // استفاده از Helper برای تبدیل تاریخ شمسی به SQL
        $filters['start_date_sql'] = Helper::parseJalaliDateToSql($filters['start_date_jalali']);
        $filters['end_date_sql'] = Helper::parseJalaliDateToSql($filters['end_date_jalali'], true); // true برای انتهای روز

        // Clean up null/empty filters before passing to repository
        $activeFilters = array_filter($filters, function ($value) {
            return $value !== false && $value !== '' && $value !== null;
        });


        try {
            // Fetch data using Repository
            $totalRecords = $this->transactionRepository->countFiltered($activeFilters, $searchTerm);
            $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $itemsPerPage) : 1;
            $currentPage = max(1, min($currentPage, $totalPages));
            $offset = ($currentPage - 1) * $itemsPerPage;

            $transactions = $this->transactionRepository->getFilteredAndPaginated($activeFilters, $searchTerm, $itemsPerPage, $offset);

            // Prepare data for display
            foreach ($transactions as &$tx) {
                $this->formatTransactionForListView($tx);
            } unset($tx);

            // استفاده از Helper::generatePaginationData
            $paginationData = Helper::generatePaginationData($currentPage, $totalPages, $totalRecords, $itemsPerPage);
            $this->logger->debug("Transactions list fetched.", ['count' => count($transactions), 'total' => $totalRecords]);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching transactions list.", ['exception' => $e]);
            $errorMessage = $errorMessage ?: ['text' => "خطا در بارگذاری لیست معاملات."];
            if ($this->config['app']['debug']) {
                $errorMessage['text'] .= " جزئیات: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
            $transactions = [];
            // استفاده از Helper::generatePaginationData برای حالت خطا
            $paginationData = Helper::generatePaginationData(1, 1, 0, $itemsPerPage);
        }

        // Fetch contacts for filter dropdown
        $contactsForFilter = [];
        try {
            $contactsForFilter = $this->contactRepository->getAll();
        } catch (Throwable $e) {
            $this->logger->error("Failed getting contacts for filter dropdown.", ['exception' => $e]);
        }


        $this->render('transactions/list', [
            'page_title'     => $pageTitle,
            'transactions'   => $transactions,
            'error_msg'      => $errorMessage ? $errorMessage['text'] : null,
            'success_msg'    => $successMessage ? $successMessage['text'] : null,
            'search_term'    => htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'),
            'filters'        => $filters, // Pass original filters (with Jalali dates) back to view
            'contacts_for_filter' => $contactsForFilter, // Contacts for dropdown
            // استفاده از Helper::getDeliveryStatusOptions()
            'delivery_statuses' => Helper::getDeliveryStatusOptions(), // Get statuses for dropdown
            'pagination'     => $paginationData,
            'csrf_token'     => Helper::generateCsrfToken() // تولید CSRF Token برای فرم‌ها
        ]);
    }

    public function showAddForm(): void
    {
        $this->requireLogin();
        $pageTitle = "ثبت معامله جدید";
        $loadingError = null;
        try {
            $contacts = $this->contactRepository->getAll();
            $assayOffices = $this->assayOfficeRepository->getAll();
            $products = $this->productRepository->getAllActiveWithCategory();
        } catch (Throwable $e) {
            $this->logger->error("Error fetching dropdown data for add transaction form.", ['exception' => $e]);
            $loadingError = "خطا در بارگذاری لیست‌های پیش‌نیاز.";
            $contacts = $assayOffices = $products = [];
        }

        // استفاده از global_json_strings_for_footer که در AbstractController تزریق می‌شود
        $fieldsData = json_decode($this->config['app']['global_json_strings']['fields'] ?? '[]', true)['fields'] ?? [];
        $formulasData = json_decode($this->config['app']['global_json_strings']['formulas'] ?? '[]', true)['formulas'] ?? [];
        
        $this->render('transactions/form', [
            'page_title' => $pageTitle,
            'is_edit_mode' => false,
            'form_action' => $this->config['app']['base_url'] . '/app/transactions/save',
            'loading_error' => $loadingError,
            'csrf_token' => Helper::generateCsrfToken(),
            'contactsData' => $contacts,
            'assayOfficesData' => $assayOffices,
            'productsData' => $products,
            'fieldsData' => $fieldsData,
            'formulasData' => $formulasData,
            'transactionData' => $_SESSION['transaction_form_data'] ?? null,
            'transactionItemsData' => $_SESSION['transaction_form_data']['items'] ?? [],
            'default_settings' => $this->getDefaultSettings(), // اضافه شده
            'config' => $this->config // برای دسترسی به app.debug در View
        ]);
        unset($_SESSION['transaction_form_data']);
    }
    
    public function showEditForm(int $transactionId): void
    {
        $this->requireLogin();
        $transactionId = (int)$transactionId;
        if ($transactionId <= 0) {
            $this->setSessionMessage('شناسه معامله نامعتبر است.');
            $this->redirect('/app/transactions');
            return;
        }

        $pageTitle = "ویرایش معامله";
        $errorMessage = $this->getFlashMessage('form_error');
        $successMessage = $this->getFlashMessage('transaction_success');
        $sessionKey = 'transaction_edit_data_' . $transactionId;
        $formData = $_SESSION[$sessionKey] ?? null;
        if ($formData) {
            unset($_SESSION[$sessionKey]);
        }

        $loadingError = null;
        
        try {
            // دریافت اطلاعات معامله و آیتم‌ها
            $transaction = $this->transactionRepository->findByIdWithItems($transactionId);
            if (!$transaction) {
                $this->setSessionMessage('معامله مورد نظر یافت نشد.');
                $this->redirect('/app/transactions');
                return;
            }
            
            $items = $transaction['items'] ?? [];
            
            // استفاده از داده‌های فرم اگر وجود داشته باشد (برای repopulation)
            if ($formData) {
                // merge کردن داده‌های transaction اصلی با formData
                $transaction = array_merge($transaction, $formData);
                $items = $formData['items'] ?? $items;
            }
            
            // دریافت لیست‌های مورد نیاز
            $assayOffices = $this->assayOfficeRepository->getAll();
            $contacts = $this->contactRepository->getAll();
            // از getAllActiveWithCategory استفاده شود که Product Model برمی‌گرداند
            $products = $this->productRepository->getAllActiveWithCategory();
            
            // بارگذاری فیلدها و فرمول‌ها از global_json_strings
            $fieldsFromView = json_decode($this->config['app']['global_json_strings']['fields'] ?? '[]', true)['fields'] ?? [];
            $formulas = json_decode($this->config['app']['global_json_strings']['formulas'] ?? '[]', true)['formulas'] ?? [];
            $defaults = $this->getDefaultSettings();
            
            // لاگ کردن اطلاعات برای دیباگ
            $this->logger->debug("Transaction data for edit form:", [
                'transaction_id' => $transactionId,
                'has_delivery_status' => isset($transaction['delivery_status']),
                'delivery_status' => $transaction['delivery_status'] ?? 'not set',
                'items_count' => count($items)
            ]);

            // نمایش فرم ویرایش
            $this->render('transactions/form', [ // FIX: تغییر به 'transactions/form'
                'page_title'        => $pageTitle,
                'is_edit_mode'      => true, // FIX: تنظیم حالت ویرایش
                'form_action'       => $this->config['app']['base_url'] . '/app/transactions/save/' . $transactionId, // FIX: تنظیم اکشن فرم برای ویرایش
                'transactionData'   => $transaction, // FIX: استفاده از transactionData به جای transaction
                'transactionItemsData' => $items, // FIX: استفاده از transactionItemsData به جای items
                'assayOfficesData'  => $assayOffices, // FIX: استفاده از assayOfficesData
                'productsData'      => $products, // FIX: استفاده از productsData
                'contactsData'      => $contacts, // FIX: استفاده از contactsData
                'fieldsData'        => $fieldsFromView, // FIX: استفاده از fieldsData
                'formulasData'      => $formulas, // FIX: استفاده از formulasData
                'error_message'     => $errorMessage,
                'success_message'   => $successMessage,
                'loading_error'     => $loadingError,
                'default_settings'  => $defaults,
                'csrf_token'        => Helper::generateCsrfToken(),
                'baseUrl'           => $this->config['app']['base_url'],
                'config'            => $this->config // برای دسترسی به app.debug در View
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->error("Error loading transaction for edit form.", ['exception' => $e]);
            $loadingError = "خطا در بارگذاری اطلاعات معامله. " . ($this->config['app']['debug'] ? $e->getMessage() : '');
            
            $this->render('transactions/form', [ // FIX: تغییر به 'transactions/form'
                'page_title'    => $pageTitle,
                'is_edit_mode'  => true, // FIX: تنظیم حالت ویرایش
                'form_action'   => $this->config['app']['base_url'] . '/app/transactions/save/' . $transactionId, // FIX: تنظیم اکشن فرم
                'transactionData' => [], // FIX: داده خالی
                'transactionItemsData' => [], // FIX: داده خالی
                'assayOfficesData' => [], // FIX: داده خالی
                'productsData' => [], // FIX: داده خالی
                'contactsData' => [], // FIX: داده خالی
                'fieldsData' => [], // FIX: داده خالی
                'formulasData' => [], // FIX: داده خالی
                'error_message' => $errorMessage ?: $loadingError,
                'csrf_token'    => Helper::generateCsrfToken(),
                'baseUrl'       => $this->config['app']['base_url'],
                'config'        => $this->config
            ]);
        }
    }

    public function save(?int $transactionId = null): void
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => 'متد درخواست نامعتبر است.'], 405);
        }
        
        $isEditMode = ($transactionId !== null && $transactionId > 0);
        $postData = $_POST;
        
        try {
            if (!Helper::verifyCsrfToken($postData['csrf_token'] ?? null)) {
                throw new Exception('خطای امنیتی: توکن نامعتبر است.');
            }

            $this->db->beginTransaction();
            $mainTxData = $this->validateAndPrepareMainTxData($postData);
            if ($isEditMode) $mainTxData['id'] = $transactionId;

            $submittedItems = $postData['items'] ?? [];
            if (empty($submittedItems)) throw new Exception('حداقل یک قلم معامله باید وارد شود.');
            
            $validatedItems = [];
            $allProducts = $this->productRepository->getAllActiveWithCategory();
            $productsById = [];
            foreach ($allProducts as $p) { $productsById[$p->id] = $p; }

            foreach ($submittedItems as $index => $itemData) {
                if(empty($itemData['product_id'])) continue; 
                $product = $productsById[$itemData['product_id']] ?? null;
                if (!$product) throw new Exception("محصول انتخاب شده در ردیف " . ($index + 1) . " معتبر نیست.");
                
                $normalizedItem = $this->normalizeItemData($itemData, $product);
                $recalculatedItem = $this->recalculateItemWithFormulas($normalizedItem, $product, (float)($mainTxData['mazaneh_price'] ?? 0));
                
                $itemModel = new TransactionItem($recalculatedItem);
                $itemModel->id = isset($itemData['id']) && (int)$itemData['id'] > 0 ? (int)$itemData['id'] : null;
                $validatedItems[] = $itemModel;
            }
            
            if(empty($validatedItems)) throw new Exception('هیچ قلم معامله معتبری برای ذخیره وجود ندارد.');

            // FIX: تبدیل اشیاء TransactionItem به آرایه قبل از ارسال به FormulaService
            $itemsAsArrays = array_map(fn($itemModel) => $itemModel->toArray(), $validatedItems);

            // FIX: ارسال آرایه آیتم‌ها به FormulaService
            $summary = $this->formulaService->calculateTransactionSummary(
                $itemsAsArrays, // استفاده از آرایه آیتم‌ها
                $mainTxData,
                $productsById,
                $this->getDefaultSettings(), // Pass default settings
                [
                    'tax_rate' => (float)($this->settingsRepository->getSetting('tax_rate') ?? 0), // FIX: استفاده از getSetting
                    'vat_rate' => (float)($this->settingsRepository->getSetting('vat_rate') ?? 0) // FIX: استفاده از getSetting
                ]
            );
            $mainTxData = array_merge($mainTxData, $summary);
            
            $finalTransactionId = $this->transactionRepository->save($mainTxData);

            if ($isEditMode) {
                $this->inventoryLedgerRepository->deleteByTransactionId($transactionId);
                $this->transactionItemRepository->deleteByTransactionId($transactionId);
            }

            foreach ($validatedItems as $item) { // $item در اینجا یک شیء TransactionItem است
                $item->transaction_id = $finalTransactionId;
                $savedItemId = $this->transactionItemRepository->save($item);

                // FIX: اطمینان از ارسال آرایه به recordChange
                $this->inventoryLedgerRepository->recordChange([
                    'product_id' => $item->product_id,
                    'transaction_id' => $finalTransactionId,
                    'transaction_item_id' => $savedItemId,
                    'event_date' => $mainTxData['transaction_date'],
                    'change_quantity' => ($mainTxData['transaction_type'] === 'buy') ? ($item->quantity ?? 0) : -($item->quantity ?? 0),
                    'change_weight_grams' => ($mainTxData['transaction_type'] === 'buy') ? ($item->weight_grams ?? 0.0) : -($item->weight_grams ?? 0.0),
                    'event_type' => ($mainTxData['transaction_type'] === 'buy') ? 'PURCHASE' : 'SALE',
                    'notes' => "Item from Transaction #" . $finalTransactionId,
                ]);
            }

            $this->db->commit();
            $actionWord = $isEditMode ? 'ویرایش' : 'ثبت';
            $this->setSessionMessage("معامله با موفقیت {$actionWord} شد.", 'success');
            
            $this->jsonResponse([
                'success' => true,
                'message' => "معامله با موفقیت {$actionWord} شد.",
                'redirect_url' => $this->config['app']['base_url'] . '/app/transactions'
            ]);

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->logger->error("Critical error saving transaction.", ['exception' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'message' => 'خطا در ذخیره معامله: ' . $e->getMessage()], 500);
        }
    }

    private function validateAndPrepareMainTxData(array $postData): array {
        $data = [];
        $data['transaction_type'] = $postData['transaction_type'] ?? null;
        if (!in_array($data['transaction_type'], ['buy', 'sell'])) throw new Exception('نوع معامله نامعتبر است.');
        $data['counterparty_contact_id'] = filter_var($postData['counterparty_contact_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if (empty($data['counterparty_contact_id'])) throw new Exception('انتخاب طرف حساب الزامی است.');
        // استفاده از Helper::parseJalaliDatetimeToSql برای تبدیل تاریخ
        $data['transaction_date'] = Helper::parseJalaliDatetimeToSql($postData['transaction_date'] ?? '');
        if (empty($data['transaction_date'])) throw new Exception('تاریخ معامله نامعتبر است.');
        $data['delivery_status'] = $postData['delivery_status'] ?? 'completed';
        if ($data['transaction_type'] === 'buy' && $data['delivery_status'] === 'pending_delivery') throw new Exception('وضعیت تحویل برای معامله خرید نامعتبر است.');
        if ($data['transaction_type'] === 'sell' && $data['delivery_status'] === 'pending_receipt') throw new Exception('وضعیت تحویل برای معامله فروش نامعتبر است.');
        // استفاده از Helper::sanitizeFormattedNumber برای مظنه
        $data['mazaneh_price'] = (float)Helper::sanitizeFormattedNumber($postData['mazaneh_price'] ?? '0');
        $data['notes'] = trim($postData['notes'] ?? '');
        $data['created_by_user_id'] = $_SESSION['user_id'] ?? null;
        return $data;
    }
    
    private function normalizeItemData(array $itemData, object $product): array {
        // اطمینان حاصل کنید که product->category->base_category همیشه موجود است
        $group = strtolower($product->category->base_category ?? '');
        $normalized = ['product_id' => (int)$itemData['product_id']];
        $normalized['description'] = $itemData["description"] ?? ($itemData["item_description_{$group}"] ?? null);
        
        // نگاشت فیلدهای فرم به فیلدهای دیتابیس
        // این نگاشت باید جامع باشد و تمام فیلدهای ممکن را پوشش دهد.
        // از TransactionItem::mapFormFieldsToDbFields برای این نگاشت استفاده کنید.
        $fieldMapping = \App\Models\TransactionItem::mapFormFieldsToDbFields(strtoupper($group));

        foreach ($fieldMapping as $formField => $dbField) {
            if (isset($itemData[$formField])) {
                // از sanitizeFormattedNumber برای فیلدهای عددی استفاده کنید
                $value = in_array($dbField, ['quantity', 'weight_grams', 'carat', 'unit_price_rials', 'ajrat_rials', 'profit_percent', 'fee_percent', 'manufacturing_fee_rate', 'total_value_rials']) // FIX: total_value_rials اضافه شد
                    ? Helper::sanitizeFormattedNumber($itemData[$formField])
                    : $itemData[$formField];
                
                if ($value !== '' && $value !== null) {
                    // تبدیل به نوع صحیح
                    if (in_array($dbField, ['quantity', 'carat', 'assay_office_id', 'coin_year'])) {
                        $normalized[$dbField] = (int)$value;
                    } elseif (in_array($dbField, ['weight_grams', 'unit_price_rials', 'ajrat_rials', 'profit_percent', 'fee_percent', 'manufacturing_fee_rate', 'total_value_rials'])) { // FIX: total_value_rials اضافه شد
                        $normalized[$dbField] = (float)$value;
                    } else {
                        $normalized[$dbField] = $value;
                    }
                } else {
                    $normalized[$dbField] = null; // اگر خالی بود، null ذخیره شود
                }
            }
        }
        
        // فیلدهای خاصی که ممکن است در mapFormFieldsToDbFields نباشند اما در TransactionItem Model هستند
        // و باید از itemData مستقیماً کپی شوند (مثلاً اگر نام فیلد در فرم با نام ستون در DB یکی است)
        $directCopyFields = ['tag_number', 'seal_name', 'is_bank_coin', 'workshop_name', 'stone_weight_grams'];
        foreach ($directCopyFields as $field) {
            // FIX: بررسی کنید که آیا فیلد در itemData وجود دارد و خالی نیست
            if (isset($itemData[$field]) && ($itemData[$field] !== '' || $itemData[$field] !== null)) {
                $normalized[$field] = $itemData[$field];
            } else {
                $normalized[$field] = null; // اگر خالی بود، null ذخیره شود
            }
        }

        return $normalized;
    }
    
    private function recalculateItemWithFormulas(array $item, object $product, float $mazanehPrice): array {
        $group = strtolower($product->category->base_category ?? '');
        $inputValues = $item;
        $inputValues['mazaneh_price'] = $mazanehPrice;
        
        // دریافت نرخ مالیات و ارزش افزوده از تنظیمات کلی (SettingsRepository)
        $taxRate = (float)($this->settingsRepository->getSetting('tax_rate') ?? 0);
        $vatRate = (float)($this->settingsRepository->getSetting('vat_rate') ?? 0);

        // اضافه کردن اطلاعات مالیات و ارزش افزوده محصول به inputValues
        $inputValues['product_tax_enabled'] = (int)($product->tax_enabled ?? 0);
        $inputValues['product_tax_rate'] = (float)($product->tax_rate ?? $taxRate); // نرخ مالیات محصول یا نرخ پیش‌فرض
        $inputValues['product_vat_enabled'] = (int)($product->vat_enabled ?? 0);
        $inputValues['product_vat_rate'] = (float)($product->vat_rate ?? $vatRate); // نرخ ارزش افزوده محصول یا نرخ پیش‌فرض

        // FIX: استفاده از متد جدید getFormulasByGroup() در FormulaService
        $itemFormulas = $this->formulaService->getFormulasByGroup($group);
        foreach ($itemFormulas as $formula) {
            if (isset($formula['output_field'])) {
                $calculatedValue = $this->formulaService->calculate($formula['name'], $inputValues);
                // اطمینان از اینکه مقدار محاسبه شده عددی است
                $item[$formula['output_field']] = is_numeric($calculatedValue) ? (float)$calculatedValue : 0.0;
                // به‌روزرسانی inputValues برای استفاده در فرمول‌های بعدی
                $inputValues[$formula['output_field']] = $item[$formula['output_field']];
            }
        }

        return $item;
    }
    
    public function delete(int $transactionId): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Helper::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
             $this->setSessionMessage('درخواست نامعتبر است.', 'danger');
             $this->redirect('/app/transactions');
             return; // اضافه شده
        }
        try {
            $this->db->beginTransaction();
            // FIX: ابتدا آیتم‌ها و سپس دفتر موجودی حذف شوند (ترتیب مهم است)
            $this->inventoryLedgerRepository->deleteByTransactionId($transactionId);
            $this->transactionItemRepository->deleteByTransactionId($transactionId);
            $this->transactionRepository->delete($transactionId);
            $this->db->commit();
            $this->setSessionMessage('معامله با موفقیت حذف شد.', 'success');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->logger->error("Error deleting transaction.", ['tx_id' => $transactionId, 'exception' => $e]);
            $this->setSessionMessage('خطا در حذف معامله: ' . $e->getMessage(), 'danger'); // اضافه شده
        }
        $this->redirect('/app/transactions');
    }

    public function completeDeliveryAction(int $transactionId, string $actionType): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setSessionMessage(Helper::getMessageText('invalid_request_method'), 'danger', 'transaction_error');
            $this->redirect('/app/transactions');
            return;
        }

        // --- CSRF Token Validation ---
        $submittedToken = $_POST['csrf_token'] ?? null; // Assuming token is sent in POST
        if (!Helper::verifyCsrfToken($submittedToken)) {
            $this->logger->error("CSRF token validation failed for complete delivery action.", ['tx_id' => $transactionId, 'action' => $actionType]);
            $this->setSessionMessage(Helper::getMessageText('csrf_token_invalid'), 'danger', 'transaction_error');
            $this->redirect('/app/transactions');
            return;
        }
        // Helper::regenerateCsrfToken(); // Optional

        $this->logger->info("Attempting complete delivery/receipt action.", ['tx_id' => $transactionId, 'action' => $actionType]);

        try {
            $success = $this->deliveryService->completeDelivery($transactionId, $actionType);

            if ($success) {
                $actionFarsi = ($actionType === 'receipt') ? Helper::getMessageText('delivery_receipt', 'دریافت') : Helper::getMessageText('delivery_send', 'تحویل'); // Added specific keys or fallback
                Helper::logActivity($this->db, "Transaction {$actionType} completed for ID {$transactionId}", ($actionType === 'receipt' ? 'DELIVERY_RECEIPT_COMPLETED' : 'DELIVERY_SEND_COMPLETED'), 'INFO', ['transaction_id' => $transactionId]);
                $this->setSessionMessage(Helper::getMessageText('delivery_completion_success', "عملیات {$actionFarsi} برای معامله با موفقیت تکمیل شد."), 'success', 'transaction_success');
                $this->logger->info("Delivery/Receipt completed successfully.", ['tx_id' => $transactionId, 'action' => $actionType]);
            } else {
                $this->setSessionMessage(Helper::getMessageText('delivery_completion_error'), 'danger', 'transaction_error');
                $this->logger->warning("DeliveryService->completeDelivery returned false unexpectedly.", ['tx_id' => $transactionId, 'action' => $actionType]);
            }

        } catch (Throwable $e) {
            $this->logger->error("Error completing delivery/receipt action.", ['tx_id' => $transactionId, 'action' => $actionType, 'exception' => $e]);
            $errorMessage = Helper::getMessageText('delivery_completion_error_details', 'خطا در تکمیل عملیات') . ": " . Helper::escapeHtml($e->getMessage()); // Added specific key or fallback
            $this->setSessionMessage($errorMessage, 'danger', 'transaction_error');
        }

        $this->redirect('/app/transactions');
    }

    // FIX: متد generatePaginationDataLocal به Helper منتقل شده است.
    // این متد از اینجا حذف می‌شود.
    // private function generatePaginationDataLocal(...) { ... }

    // FIX: متد getDeliveryStatusOptionsLocal به Helper منتقل شده است.
    // این متد از اینجا حذف می‌شود.
    // private function getDeliveryStatusOptionsLocal(...) { ... }

    private function getDefaultSettings(): array
    {
        // دریافت مقادیر پیش‌فرض از SettingsRepository
        return [
            'melted_profit_percentage' => (float)($this->settingsRepository->getSetting('melted_profit_percentage') ?? 0),
            'melted_fee_percentage' => (float)($this->settingsRepository->getSetting('melted_fee_percentage') ?? 0),
            'manufactured_profit_percentage' => (float)($this->settingsRepository->getSetting('manufactured_profit_percentage') ?? 7),
            'bullion_profit_percentage' => (float)($this->settingsRepository->getSetting('bullion_profit_percentage') ?? 0),
            'bullion_fee_percentage' => (float)($this->settingsRepository->getSetting('bullion_fee_percentage') ?? 0),
            // نرخ مالیات و ارزش افزوده باید از تنظیمات عمومی (settingsRepository) خوانده شوند
            'tax_rate' => (float)($this->settingsRepository->getSetting('tax_rate') ?? 0),
            'vat_rate' => (float)($this->settingsRepository->getSetting('vat_rate') ?? 0),
        ];
    }
    
    // FIX: متد getFieldsAndFormulas از اینجا حذف می‌شود.
    // این اطلاعات از global_json_strings در کنترلر اصلی (index.php) لود و تزریق می‌شوند.
    // private function getFieldsAndFormulas(): array { ... }

    // FIX: متد getGroupedProducts از اینجا حذف می‌شود.
    // این منطق باید در ProductRepository یا یک ProductService باشد.
    // private function getGroupedProducts(): array { ... }

    private function formatTransactionForListView(array &$tx): void
    {
        // استفاده از Helper::getMessageText برای ترجمه
        $tx['transaction_type_farsi'] = match ($tx['transaction_type'] ?? '') {
            'buy' => Helper::getMessageText('buy', 'خرید'),
            'sell' => Helper::getMessageText('sell', 'فروش'),
            default => '؟'
        };
        // FIX: استفاده از total_items_value_rials برای نمایش مبلغ کل در لیست
        $tx['total_value_rials_formatted'] = Helper::formatRial($tx['total_items_value_rials'] ?? 0);
        // تبدیل تاریخ میلادی به شمسی با Jalalian
        if (!empty($tx['transaction_date'])) {
            try {
                $dt = new \DateTime($tx['transaction_date']);
                $jalali = Jalalian::fromDateTime($dt);
                $tx['transaction_date_persian'] = $jalali->format('Y/m/d H:i');
            } catch (\Exception $e) {
                // لاگ خطا در صورت عدم توانایی در تبدیل تاریخ
                $this->logger->warning("Failed to format transaction date to Persian.", ['date' => $tx['transaction_date'], 'exception' => $e->getMessage()]);
                $tx['transaction_date_persian'] = '-';
            }
        } else {
            $tx['transaction_date_persian'] = '-';
        }
        $tx['counterparty_name'] = Helper::escapeHtml($tx['counterparty_name'] ?? '[نامشخص]');
        // استفاده از Helper برای ترجمه و کلاس وضعیت
        $tx['delivery_status_farsi'] = Helper::translateDeliveryStatus($tx['delivery_status'] ?? '');
        $tx['delivery_status_class'] = Helper::getDeliveryStatusClass($tx['delivery_status'] ?? '');
        $tx['can_complete_delivery'] = ($tx['delivery_status'] ?? '') === 'pending_delivery';
        $tx['can_complete_receipt'] = ($tx['delivery_status'] ?? '') === 'pending_receipt';
        $tx['notes_tooltip'] = Helper::escapeHtml($tx['notes'] ?? '');
        $tx['notes_short'] = mb_substr($tx['notes'] ?? '', 0, 30, 'UTF-8') . (mb_strlen($tx['notes'] ?? '') > 30 ? '...' : '');
        if (isset($tx['gold_product_type']) && str_starts_with($tx['gold_product_type'], 'coin_')) {
            $tx['base_price_formatted'] = Helper::formatRial($tx['unit_price'] ?? 0);
        } else {
            $mazanehPrice = $tx['mazaneh_price'] ?? 0;
            $tx['base_price_formatted'] = Helper::formatRial($mazanehPrice);
            $tx['mazaneh_price_formatted'] = Helper::formatRial($mazanehPrice);
        }
    }
}
