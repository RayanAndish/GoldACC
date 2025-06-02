<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions
use PDOException; // For specific DB exceptions
use Exception; // For general exceptions
use Morilog\Jalali\Jalalian;

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Dependencies
use App\Repositories\TransactionRepository;
use App\Repositories\ContactRepository; // For contact dropdown
use App\Repositories\AssayOfficeRepository; // For assay office dropdown
use App\Repositories\ProductRepository; // For product dropdown and details
use App\Repositories\TransactionItemRepository; // For saving/updating items
use App\Repositories\InventoryLedgerRepository; // For inventory tracking
use App\Services\DeliveryService; // To complete delivery/receipt actions
use App\Repositories\InventoryRepository; // Needed for temporary revert logic
use App\Repositories\CoinInventoryRepository; // Needed for temporary revert logic
use App\Utils\Helper; // Utility functions
use App\Repositories\SettingsRepository;
use App\core\CSRFProtector;

/**
 * TransactionController handles HTTP requests related to gold transactions.
 * Manages listing, add/edit forms, save/delete processing, and delivery completion.
 * Inherits from AbstractController.
 */
class TransactionController extends AbstractController
{

    // Dependencies
    private TransactionRepository $transactionRepository;
    private ContactRepository $contactRepository;
    private AssayOfficeRepository $assayOfficeRepository;
    private ProductRepository $productRepository;
    private TransactionItemRepository $transactionItemRepository;
    private InventoryLedgerRepository $inventoryLedgerRepository;
    private DeliveryService $deliveryService;
    // --- Temporary Dependencies for Revert Logic (Should be moved to a dedicated Service) ---
    private InventoryRepository $inventoryRepository;
    private CoinInventoryRepository $coinInventoryRepository;
    private SettingsRepository $settingsRepository;
    // --- End Temporary Dependencies ---

    /**
     * Constructor. Injects dependencies.
     *
     * @param PDO $db
     * @param Logger $logger
     * @param array $config
     * @param ViewRenderer $viewRenderer
     * @param array $services Array of application services.
     * @throws \Exception If required repositories/services are missing.
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services // Receive the master services array
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services); // Pass all to parent

        // Retrieve specific dependencies
        $required = [
            'transactionRepository' => TransactionRepository::class,
            'contactRepository' => ContactRepository::class,
            'assayOfficeRepository' => AssayOfficeRepository::class,
            'productRepository' => ProductRepository::class,
            'transactionItemRepository' => TransactionItemRepository::class,
            'inventoryLedgerRepository' => InventoryLedgerRepository::class,
            'deliveryService' => DeliveryService::class,
            // Temporary for revert logic
            'inventoryRepository' => InventoryRepository::class,
            'coinInventoryRepository' => CoinInventoryRepository::class,
            'settingsRepository' => SettingsRepository::class,
        ];
        foreach ($required as $prop => $class) {
            if (!isset($services[$prop]) || !$services[$prop] instanceof $class) {
                throw new \Exception("{$class} not found or invalid for TransactionController.");
            }
            $this->$prop = $services[$prop];
        }
        $this->settingsRepository = $services['settingsRepository'];
        $this->logger->debug("TransactionController initialized.");
    }

    /**
     * Displays the list of transactions with filters, search, and pagination.
     * Route: /app/transactions (GET)
     */
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

        $filters['start_date_sql'] = null;
        if (!empty($filters['start_date_jalali'])) {
            try {
                $jalaliDate = preg_replace('#\s.*#', '', $filters['start_date_jalali']);
                $parts = preg_split('/[-\/]/', $jalaliDate);
                if (count($parts) === 3 && ctype_digit($parts[0]) && ctype_digit($parts[1]) && ctype_digit($parts[2])) {
                    $filters['start_date_sql'] = (new Jalalian((int)$parts[0], (int)$parts[1], (int)$parts[2]))->toCarbon()->toDateString();
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to parse start_date_jalali to SQL.', ['date' => $filters['start_date_jalali'], 'error' => $e->getMessage()]);
            }
        }

        $filters['end_date_sql'] = null;
        if (!empty($filters['end_date_jalali'])) {
            try {
                $jalaliDate = preg_replace('#\s.*#', '', $filters['end_date_jalali']);
                $parts = preg_split('/[-\/]/', $jalaliDate);
                if (count($parts) === 3 && ctype_digit($parts[0]) && ctype_digit($parts[1]) && ctype_digit($parts[2])) {
                    $filters['end_date_sql'] = (new Jalalian((int)$parts[0], (int)$parts[1], (int)$parts[2]))->toCarbon()->toDateString();
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to parse end_date_jalali to SQL.', ['date' => $filters['end_date_jalali'], 'error' => $e->getMessage()]);
            }
        }

        if ($filters['end_date_sql']) {
            $filters['end_date_sql'] .= ' 23:59:59';
        }
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

            $paginationData = $this->generatePaginationDataLocal($currentPage, $totalPages, $totalRecords, $itemsPerPage);
            $this->logger->debug("Transactions list fetched.", ['count' => count($transactions), 'total' => $totalRecords]);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching transactions list.", ['exception' => $e]);
            $errorMessage = $errorMessage ?: ['text' => "خطا در بارگذاری لیست معاملات."];
            if ($this->config['app']['debug']) {
                $errorMessage['text'] .= " جزئیات: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
            $transactions = [];
            $paginationData = $this->generatePaginationDataLocal(1, 1, 0, $itemsPerPage);
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
             'delivery_statuses' => $this->getDeliveryStatusOptionsLocal(), // Get statuses for dropdown
            'pagination'     => $paginationData
        ]);
    }

    /** Helper function to format transaction data for list view */
    private function formatTransactionForListView(array &$tx): void
    {
        $tx['transaction_type_farsi'] = match ($tx['transaction_type'] ?? '') {
            'buy' => 'خرید', 'sell' => 'فروش', default => '؟'
        };
        $tx['total_value_rials_formatted'] = Helper::formatRial($tx['total_items_value_rials'] ?? $tx['total_value_rials'] ?? 0);
        // تبدیل تاریخ میلادی به شمسی با Jalalian
        if (!empty($tx['transaction_date'])) {
            try {
                $dt = new \DateTime($tx['transaction_date']);
                $jalali = Jalalian::fromDateTime($dt);
                $tx['transaction_date_persian'] = $jalali->format('Y/m/d H:i');
            } catch (\Exception $e) {
                $tx['transaction_date_persian'] = '-';
            }
        } else {
            $tx['transaction_date_persian'] = '-';
        }
        $tx['counterparty_name'] = Helper::escapeHtml($tx['counterparty_name'] ?? '[نامشخص]');
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

    private function getDeliveryStatusOptionsLocal(bool $forFilter = false): array
    {
        $statuses = [
            'pending_receipt' => 'منتظر دریافت',
            'pending_delivery' => 'در صف تحویل',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
        ];

        if ($forFilter) {
            return ['all' => 'همه وضعیت‌ها'] + $statuses;
        }
        return $statuses;
    }

    private function generatePaginationDataLocal(int $currentPage, int $totalPages, int $totalRecords, int $itemsPerPage, int $linksToShow = 5): array
    {
        $pagination = [
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'itemsPerPage' => $itemsPerPage,
            'hasNextPage' => $currentPage < $totalPages,
            'hasPrevPage' => $currentPage > 1,
            'nextPage' => $currentPage + 1,
            'prevPage' => $currentPage - 1,
            'pages' => [],
            'firstItem' => ($totalRecords > 0) ? (($currentPage - 1) * $itemsPerPage) + 1 : 0,
            'lastItem' => ($totalRecords > 0) ? min($currentPage * $itemsPerPage, $totalRecords) : 0,
        ];

        if ($totalPages <= 1) {
            return $pagination;
        }

        $start = max(1, $currentPage - (int)floor(($linksToShow - 1) / 2));
        $end = min($totalPages, $currentPage + (int)ceil(($linksToShow - 1) / 2));

        if ($end - $start + 1 < $linksToShow) {
            if ($start === 1) {
                $end = min($totalPages, $start + $linksToShow - 1);
            } elseif ($end === $totalPages) {
                $start = max(1, $end - $linksToShow + 1);
            }
        }

        if ($start > 1) {
            $pagination['pages'][] = ['num' => 1, 'isCurrent' => false, 'isEllipsis' => false];
            if ($start > 2) {
                $pagination['pages'][] = ['num' => '...', 'isCurrent' => false, 'isEllipsis' => true];
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $pagination['pages'][] = ['num' => $i, 'isCurrent' => $i === $currentPage, 'isEllipsis' => false];
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $pagination['pages'][] = ['num' => '...', 'isCurrent' => false, 'isEllipsis' => true];
            }
            $pagination['pages'][] = ['num' => $totalPages, 'isCurrent' => false, 'isEllipsis' => false];
        }

        return $pagination;
    }

    private function getDefaultSettings(): array
    {
        return [
            'melted_profit_percentage' => $this->config['defaults']['melted_profit_percentage'] ?? 0,
            'melted_fee_percentage' => $this->config['defaults']['melted_fee_percentage'] ?? 0,
            'manufactured_profit_percentage' => $this->config['defaults']['manufactured_profit_percentage'] ?? 7,
            'bullion_profit_percentage' => $this->config['defaults']['bullion_profit_percentage'] ?? 0,
            'bullion_fee_percentage' => $this->config['defaults']['bullion_fee_percentage'] ?? 0,
            // مقادیر مالیات و ارزش افزوده حذف شد چون نرخ و فعال بودن آن‌ها از مدل محصول خوانده می‌شود
        ];
    }
    
    private function getFieldsAndFormulas(): array
    {
    $fieldsFromView = [];
    $formulas = [];
    $fieldsFilePath = realpath(__DIR__ . '/../../config/fields.json');
    $formulasFilePath = realpath(__DIR__ . '/../../config/formulas.json');
    if ($fieldsFilePath && file_exists($fieldsFilePath)) {
        $fieldsJsonContent = file_get_contents($fieldsFilePath);
        $fieldsData = json_decode($fieldsJsonContent, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($fieldsData['fields'])) {
            $fieldsFromView = $fieldsData['fields'];
        } else {
            $this->logger->error('Failed to parse fields.json or "fields" key missing.', ['path' => $fieldsFilePath, 'json_error' => json_last_error_msg()]);
            $loadingError = ($loadingError ? $loadingError . '<br>' : '') . 'خطا در بارگذاری ساختار فیلدهای فرم.';
        }
    } else {
        $this->logger->error('fields.json not found.', ['path' => $fieldsFilePath]);
        $loadingError = ($loadingError ? $loadingError . '<br>' : '') . 'فایل تعریف فیلدها (fields.json) یافت نشد.';
    }
    if ($formulasFilePath && file_exists($formulasFilePath)) {
        $formulasJsonContent = file_get_contents($formulasFilePath);
        $formulasData = json_decode($formulasJsonContent, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($formulasData['formulas'])) {
            // فقط فرمول‌های مربوط به فرم تراکنش را نگه دار
            $formulas = array_filter($formulasData['formulas'], function($formula) {
                return isset($formula['form']) && $formula['form'] === 'transactions/form.php';
            });
        } else {
            $this->logger->error('Failed to parse formulas.json or "formulas" key missing.', ['path' => $formulasFilePath, 'json_error' => json_last_error_msg()]);
            $loadingError = ($loadingError ? $loadingError . '<br>' : '') . 'خطا در بارگذاری ساختار فرمول‌های فرم.';
        }
    } else {
        $this->logger->error('formulas.json not found.', ['path' => $formulasFilePath]);
        $loadingError = ($loadingError ? $loadingError . '<br>' : '') . 'فایل تعریف فرمول‌ها (formulas.json) یافت نشد.';
    }
    return [$fieldsFromView, $formulas];
    }

    private function getGroupedProducts(): array
    {
        $productsArray = $this->productRepository->findAll(['is_active' => true], true);
        $products_list = $productsArray;
        $products_by_id = [];
        $groupedProducts = [];
        foreach ($productsArray as $p) {
            $productId = is_object($p) ? $p->id : $p['id'];
            if ($productId) {
                $products_by_id[$productId] = $p;
            }
        }
        $groupedProducts = [];
        foreach ($products_list as $product) {
            $categoryName = '';
            if (is_object($product) && isset($product->category) && isset($product->category->name)) {
                $categoryName = $product->category->name;
            } elseif (is_array($product) && isset($product['category']['name'])) {
                $categoryName = $product['category']['name'];
            } else {
                $categoryName = 'سایر';
            }
            $groupedProducts[$categoryName][] = $product;
        }
        return [$groupedProducts, $products_list];
    }

    /**
     * Displays the form for adding a new transaction.
     * Route: /app/transactions/add (GET)
     */
    public function showAddForm(): void
    {
        $this->requireLogin();
        // Optional: Permission check

        $pageTitle = "ثبت معامله جدید";
        $errorMessage = $this->getFlashMessage('form_error');
        
        // لاگ کردن پیام خطا برای بررسی
        $this->logger->debug("Form error message in showAddForm", [
            'form_error' => $errorMessage,
            'is_array' => is_array($errorMessage),
            'has_text' => is_array($errorMessage) && isset($errorMessage['text']),
            'text_value' => is_array($errorMessage) && isset($errorMessage['text']) ? $errorMessage['text'] : null
        ]);
        
        $sessionKey = 'transaction_add_data';
        $formData = $_SESSION[$sessionKey] ?? null;
        if ($formData) {
            unset($_SESSION[$sessionKey]);
        }

        $contacts = [];
        $assayOffices = [];
        $products_list = [];
        $products_by_id = [];
        $loadingError = null;
        try {
            $contacts = $this->contactRepository->getAll();
            $assayOffices = $this->assayOfficeRepository->getAll();
            // productsArray
            list($groupedProducts, $products_list) = $this->getGroupedProducts();
        } catch (Throwable $e) {
            $this->logger->error("Error fetching dropdown data for add transaction form.", ['exception' => $e]);
            $loadingError = "خطا در بارگذاری لیست‌های پیش‌نیاز.";
        }
        // --- بارگذاری فرمول‌ها و فیلدها برای FormulaService (حتماً قبل از استفاده) ---
        list($fieldsFromView, $formulas) = $this->getFieldsAndFormulas();
        if (!is_array($formulas)) $formulas = [];
        $formulaService = new \App\Services\FormulaService($this->logger, $formulas, $fieldsFromView);
        $defaults = $this->getDefaultSettings();

        $transaction = null;
        $transactionItems = [];
        if (
            isset($formData) && is_array($formData)
        ) {
            $transaction = $formData;
            $transactionItems = $formData['items'] ?? [];
        }
        $successMessage = $this->getFlashMessage('transaction_success');

        // لاگ کردن پیام خطا قبل از ارسال به view
        $this->logger->debug("Messages before rendering", [
            'error_message' => $errorMessage,
            'success_message' => $successMessage
        ]);

        $this->render('transactions/form', [
            'page_title'         => $pageTitle,
            'form_action'        => $this->config['app']['base_url'] . '/app/transactions/save',
            'transaction'        => $transaction,
            'transaction_items'  => $transactionItems,
            'contacts'           => $contacts,
            'assay_offices'      => $assayOffices,
            'groupedProducts'    => $groupedProducts ?? [],
            'products_list'      => $products_list,
            'fields'             => $fieldsFromView,
            'formulas'           => $formulas,
            'is_edit_mode'       => false,
            'submit_button_text' => 'ثبت معامله',
            'error_message'      => $errorMessage,
            'success_message'    => $successMessage, // Pass success message
            'loading_error'      => $loadingError,
            'delivery_statuses'  => $this->getDeliveryStatusOptionsLocal(true),
            'default_settings'   => $defaults,
            'csrf_token'         => \App\Utils\Helper::generateCsrfToken(),
            'baseUrl'            => $this->config['app']['base_url']
        ]);
    }

    /**
     * Displays the form for editing an existing transaction.
     * Route: /app/transactions/edit/{id} (GET)
     *
     * @param int $transactionId The ID of the transaction to edit.
     */
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
            
            // استفاده از داده‌های فرم اگر وجود داشته باشد
            if ($formData) {
                $transaction = $formData;
                $items = $formData['items'] ?? $items;
            }
            
            // دریافت لیست‌های مورد نیاز
            $assayOffices = $this->assayOfficeRepository->getAll();
            $contacts = $this->contactRepository->getAll();
            list($groupedProducts, $productsList) = $this->getGroupedProducts();
            $products = $productsList;
            
            // بارگذاری فیلدها و فرمول‌ها از فایل‌های JSON
            list($fieldsFromView, $formulas) = $this->getFieldsAndFormulas();
            $defaults = $this->getDefaultSettings();
            
            // لاگ کردن اطلاعات برای دیباگ
            $this->logger->debug("Transaction data for edit form:", [
                'transaction_id' => $transactionId,
                'has_delivery_status' => isset($transaction['delivery_status']),
                'delivery_status' => $transaction['delivery_status'] ?? 'not set',
                'items_count' => count($items)
            ]);

            // نمایش فرم ویرایش
            $this->render('transactions/edit', [
                'page_title'        => $pageTitle,
                'transaction'       => $transaction,
                'items'             => $items,
                'assay_offices'     => $assayOffices,
                'products'          => $products,
                'contacts'          => $contacts,
                'fields'            => $fieldsFromView,
                'formulas'          => $formulas,
                'error_message'     => $errorMessage,
                'success_message'   => $successMessage,
                'loading_error'     => $loadingError,
                'default_settings'  => $defaults,
                'csrf_token'        => \App\Utils\Helper::generateCsrfToken(),
                'baseUrl'           => $this->config['app']['base_url'],
                'config'            => $this->config
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->error("Error loading transaction for edit form.", ['exception' => $e]);
            $loadingError = "خطا در بارگذاری اطلاعات معامله. " . ($this->config['app']['debug'] ? $e->getMessage() : '');
            
            $this->render('transactions/edit', [
                'page_title'    => $pageTitle,
                'transaction'   => [],
                'items'         => [],
                'assay_offices' => [],
                'products'      => [],
                'error_message' => $errorMessage ?: $loadingError,
                'csrf_token'    => \App\Utils\Helper::generateCsrfToken(),
                'baseUrl'       => $this->config['app']['base_url'],
                'config'        => $this->config
            ]);
        }
    }


    /**
     * Processes the save request for a transaction (add or edit).
     * Route: /app/transactions/save (POST for add)
     * Route: /app/transactions/save/{id} (POST for edit)
     */
    public function save(?int $transactionId = null): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("Save action called with invalid method.", ['method' => $_SERVER['REQUEST_METHOD']]);
            $this->logger->debug("RAW POST DATA", ['post_data' => $_POST]);
            $this->redirect('/app/transactions');
            return;
        }

        // --- بارگذاری فرمول‌ها و فیلدها برای FormulaService (فقط یک‌بار و ابتدای متد) ---
        list($fieldsFromView, $formulas) = $this->getFieldsAndFormulas();
        if (!is_array($formulas)) $formulas = [];
        $formulaService = new \App\Services\FormulaService($this->logger, $formulas, $fieldsFromView);
        $defaults = $this->getDefaultSettings();

        // لاگ کردن داده‌های خام دریافتی با نام‌های فیلد جدید
        $this->logger->debug("Raw POST data received:", [
            'post_data' => $_POST,
            'is_edit' => ($transactionId !== null && $transactionId > 0)
        ]);

        // --- CSRF Token Validation ---
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!Helper::verifyCsrfToken($submittedToken)) {
            $this->logger->error("CSRF token validation failed for transaction save.", ['tx_id' => $transactionId]);
            $this->setSessionMessage(Helper::getMessageText('csrf_token_invalid'), 'danger', 'form_error');
            $redirectUrlOnError = '/app/transactions/' . (($transactionId !== null && $transactionId > 0) ? 'edit/' . $transactionId : 'add');
            $this->redirect($redirectUrlOnError);
            return;
        }

        $isEditMode = ($transactionId !== null && $transactionId > 0);
        $postData = $_POST;
        
        // نگاشت نام‌های فیلد قدیمی به جدید
        if (isset($postData['items']) && is_array($postData['items'])) {
            foreach ($postData['items'] as &$item) {
                if (isset($item['weight_grams'])) {
                    $item['item_weight_scale_melted'] = $item['weight_grams'];
                    unset($item['weight_grams']);
                }
                if (isset($item['unit_price_rials'])) {
                    $item['item_unit_price_melted'] = $item['unit_price_rials'];
                    unset($item['unit_price_rials']);
                }
                // --- نگاشت فیلدهای آبشده (MELTED) ---
                if (isset($item['item_carat_melted'])) {
                    $item['carat'] = $item['item_carat_melted'];
                }
                if (isset($item['item_unit_price_melted'])) {
                    $item['unit_price_rials'] = $item['item_unit_price_melted'];
                }
                if (isset($item['item_profit_percent_melted'])) {
                    $item['profit_percent'] = $item['item_profit_percent_melted'];
                }
                if (isset($item['item_fee_percent_melted'])) {
                    $item['fee_percent'] = $item['item_fee_percent_melted'];
                }
                // سایر فیلدهای مرتبط با آبشده را نیز به همین صورت map کن (مطابق با ساختار fields و formulas)
            }
            unset($item);
        }

        $sanitizedData = Helper::sanitizeNumbersRecursive($postData);

        // --- نگاشت نام فیلدها بعد از sanitize و قبل از اعتبارسنجی (پویا و داینامیک) ---
        if (isset($sanitizedData['items']) && is_array($sanitizedData['items'])) {
            foreach ($sanitizedData['items'] as &$item) {
                $group = $this->detectItemGroup($item);
                $mapping = \App\Models\TransactionItem::mapFormFieldsToDbFields($group);
                foreach ($mapping as $formField => $dbField) {
                    if ($dbField && isset($item[$formField])) {
                        $item[$dbField] = $item[$formField];
                    }
                }
                // مقداردهی داینامیک: هر فیلدی که با item_ شروع می‌شود و در مدل وجود دارد، به همان نام در آیتم قرار بگیرد
                foreach ($item as $key => $value) {
                    if (strpos($key, 'item_') === 0 && !isset($item[$key])) {
                        $item[$key] = $value;
                    }
                }
            }
            unset($item);
        }

        // ذخیره داده‌های اصلی در سشن قبل از اعتبارسنجی
        $redirectUrlOnError = '/app/transactions/' . ($isEditMode ? 'edit/' . $transactionId : 'add');
        $sessionFormDataKey = $isEditMode ? 'transaction_edit_data_' . $transactionId : 'transaction_add_data';
        $_SESSION[$sessionFormDataKey] = $sanitizedData;

        $errors = [];
        $validatedTransactionData = [];
        $validatedItemsData = [];
        $calculatedGrandTotalRials = 0.0;
        
        // مقداردهی transaction_type قبل از استفاده در اعتبارسنجی آیتم‌ها
        $validatedTransactionData['transaction_type'] = $sanitizedData['transaction_type'] ?? '';
        $validatedTransactionData['delivery_status'] = $sanitizedData['delivery_status'] ?? '';
        
        // --- اعتبارسنجی همخوانی نوع معامله و وضعیت تحویل ---
        if (!empty($validatedTransactionData['transaction_type']) && !empty($validatedTransactionData['delivery_status'])) {
            $transactionType = $validatedTransactionData['transaction_type'];
            $deliveryStatus = $validatedTransactionData['delivery_status'];
            
            // برای معامله خرید، وضعیت تحویل نباید "منتظر تحویل" باشد
            if ($transactionType === 'buy' && $deliveryStatus === 'pending_delivery') {
                $errors[] = Helper::getMessageText('transaction_type_delivery_mismatch', 'نوع معامله (خرید) با وضعیت تحویل (منتظر تحویل) همخوانی ندارد. وضعیت صحیح برای خرید، "منتظر دریافت" است.');
            }
            
            // برای معامله فروش، وضعیت تحویل نباید "منتظر دریافت" باشد
            if ($transactionType === 'sell' && $deliveryStatus === 'pending_receipt') {
                $errors[] = Helper::getMessageText('transaction_type_delivery_mismatch', 'نوع معامله (فروش) با وضعیت تحویل (منتظر دریافت) همخوانی ندارد. وضعیت صحیح برای فروش، "منتظر تحویل" است.');
            }
        }
        
        $this->logger->debug("SANITIZED DATA", ['sanitized_data' => $sanitizedData]);
        // بارگذاری محصولات برای اعتبارسنجی
        $allProducts = $this->productRepository->findAll(['is_active' => true], true);
        $productsById = [];
        foreach ($allProducts as $p) {
            $productsById[$p->id] = $p;
        }
        
        if (empty($productsById)) {
            $this->logger->warning("No active products found for transaction validation.");
        }

        // لاگ کردن محصولات بارگذاری شده
        $this->logger->debug("Loaded products for validation:", [
            'product_count' => count($productsById),
            'product_ids' => array_keys($productsById)
        ]);

        // --- 3. اعتبارسنجی اقلام معامله ---
        $submittedItems = $sanitizedData['items'] ?? [];
        $this->logger->debug("Sanitized items for validation:", [
            'items' => $submittedItems
        ]);

        if (empty($errors) && (!is_array($submittedItems) || count($submittedItems) === 0)) {
            $errors[] = Helper::getMessageText('transaction_items_required');
        }

        if (empty($errors)) { 
            // اطمینان از مقداردهی صحیح formulas قبل از حلقه آیتم‌ها
            if (!isset($formulas) || !is_array($formulas)) {
                list($fieldsFromView, $formulas) = $this->getFieldsAndFormulas();
                if (!is_array($formulas)) $formulas = [];
            }
            foreach ($submittedItems as $index => $itemData) {
                $this->logger->debug("Processing item #{$index}:", [
                    'item_data' => $itemData
                ]);
                $itemErrors = [];
                $itemIndexLabel = "قلم #" . ($index + 1); 
                // تشخیص گروه کالا
                $group = $this->detectItemGroup($itemData);
                // اعتبارسنجی و محاسبات (در صورت نیاز)
                // ...
                // مپینگ مرکزی و دقیق
                $dbItem = \App\Models\TransactionItem::mapFormToDb($itemData, $group);
                // مقداردهی فیلدهای سیستمی (در صورت نیاز)
                $dbItem['id'] = isset($itemData['id']) ? (int)$itemData['id'] : null;
                $validatedItemsData[] = $dbItem;
            }
        }

        // --- محاسبه و مقداردهی همه فیلدهای محاسباتی آیتم‌ها بر اساس فرمول‌های هر گروه ---
        foreach ($validatedItemsData as $idx => &$item) {
            $group = $this->detectItemGroup($item);
            $product = $productsById[$item['product_id']] ?? null;
            if ($product) {
                $groupFormulas = array_filter($formulas, function($f) use ($group) {
                    return isset($f['group']) && strtoupper($f['group']) === strtoupper($group) && isset($f['output_field']);
                });
                foreach ($groupFormulas as $formula) {
                    $outputField = $formula['output_field'];
                    $fieldsNeeded = $formula['fields'] ?? [];
                    $inputValues = [];
                    foreach ($fieldsNeeded as $fieldName) {
                        $inputValues[$fieldName] = isset($item[$fieldName]) && is_numeric($item[$fieldName]) ? $item[$fieldName] : 0;
                    }
                    // مقداردهی داینامیک مالیات و ارزش افزوده از مدل محصول
                    $inputValues['product_tax_enabled'] = isset($product->tax_enabled) ? (int)$product->tax_enabled : 0;
                    $inputValues['product_tax_rate'] = isset($product->tax_rate) ? (float)$product->tax_rate : 0;
                    $inputValues['product_vat_enabled'] = isset($product->vat_enabled) ? (int)$product->vat_enabled : 0;
                    $inputValues['product_vat_rate'] = isset($product->vat_rate) ? (float)$product->vat_rate : 0;
                    if (in_array('mazaneh_price', $fieldsNeeded) && !isset($inputValues['mazaneh_price'])) {
                        $inputValues['mazaneh_price'] = isset($sanitizedData['mazaneh_price']) ? $sanitizedData['mazaneh_price'] : 0;
                    }
                    $result = $formulaService->calculate($formula['name'], $inputValues);
                    $item[$outputField] = $result ?? 0;
                }
            }
        }
        unset($item);
        // --- محاسبه مقادیر محاسباتی کلی تراکنش با FormulaService ---
        $taxRate = (float)($this->settingsRepository->get('tax_rate', 9));
        $vatRate = (float)($this->settingsRepository->get('vat_rate', 7));
        $transactionSummary = $formulaService->calculateTransactionSummary($validatedItemsData, $sanitizedData, $productsById, $defaults, [
            'tax_rate' => $taxRate,
            'vat_rate' => $vatRate
        ]);
        foreach ($transactionSummary as $field => $value) {
            $postData[$field] = $value;
        } 

        // --- 4. Handle Validation Errors ---
        if (!empty($errors)) {
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            // حالت عادی (غیر AJAX):
            $this->logger->warning("Transaction save validation failed.", ['errors' => $errors, 'tx_id' => $transactionId, 'is_edit' => $isEditMode]);
            $errorMessageText = Helper::getMessageText('validation_errors') . '<br>' . implode('<br>', array_unique($errors));
            $this->setSessionMessage($errorMessageText, 'danger', 'form_error');
            $_SESSION[$sessionFormDataKey] = $sanitizedData;
            $this->logger->debug("Saving form data to session for error recovery", [
                'session_key' => $sessionFormDataKey,
                'data_sample' => array_slice($sanitizedData, 0, 3)
            ]);
            $this->redirect($redirectUrlOnError);
            return;
        }

        // --- 5. Database Operations ---
        try {
            $this->db->beginTransaction();
            $this->logger->debug("Starting transaction save process.", ['tx_id' => $transactionId, 'is_edit' => $isEditMode, 'item_count' => count($validatedItemsData)]);

            // محاسبه جمع مبلغ آیتم‌ها
            $totalItemsValue = 0.0;
            $calculatedWeight = 0.0;
            foreach ($validatedItemsData as $item) {
                $totalItemsValue += floatval($item['total_value_rials'] ?? 0);
                $calculatedWeight += floatval($item['weight_grams'] ?? 0);
            }
            // تبدیل مظنه به عدد خالص
            $mazanehPrice = isset($postData['mazaneh_price']) ? Helper::sanitizeFormattedNumber($postData['mazaneh_price']) : 0;
            // مقداردهی فیلدهای محاسباتی دقیق جدول transactions
            $postData['total_items_value_rials'] = $totalItemsValue;
            $postData['total_value_rials'] = $totalItemsValue;
            $postData['mazaneh_price'] = $mazanehPrice;
            $postData['calculated_weight_grams'] = $calculatedWeight;
            $postData['price_per_reference_gram'] = $postData['price_per_reference_gram'] ?? 0;
            $postData['usd_rate_ref'] = $postData['usd_rate_ref'] ?? 0;
            $postData['total_profit_wage_commission_rials'] = $postData['total_profit_wage_commission_rials'] ?? 0;
            $postData['total_general_tax_rials'] = $postData['total_general_tax_rials'] ?? 0;
            $postData['total_before_vat_rials'] = $postData['total_before_vat_rials'] ?? 0;
            $postData['total_vat_rials'] = $postData['total_vat_rials'] ?? 0;
            $postData['final_payable_amount_rials'] = $postData['final_payable_amount_rials'] ?? 0;
            // مپینگ مرکزی و دقیق داده‌های فرم تراکنش
            $mainTransactionSaveData = \App\Models\Transaction::mapFormToDb($postData);
            $mainTransactionSaveData['total_items_value_rials'] = $totalItemsValue;
            // اضافه کردن فیلدهای audit
            $userId = $_SESSION['user_id'] ?? null;
            $now = date('Y-m-d H:i:s');
            if ($isEditMode) {
                $mainTransactionSaveData['id'] = $transactionId;
                $mainTransactionSaveData['updated_by_user_id'] = $userId;
                $mainTransactionSaveData['updated_at'] = $now;
            } else {
                $mainTransactionSaveData['created_by_user_id'] = $userId;
                $mainTransactionSaveData['created_at'] = $now;
                $mainTransactionSaveData['updated_by_user_id'] = $userId;
                $mainTransactionSaveData['updated_at'] = $now;
            }
            // بعد از ذخیره تراکنش، مقدار transaction_id را به validatedItemsData اضافه کن
            $finalTransactionId = $isEditMode ? $transactionId : $savedTransactionId;
            foreach ($validatedItemsData as &$item) {
                $item['transaction_id'] = $finalTransactionId;
            }
            unset($item);

            $existingDbItemIds = [];
            if ($isEditMode) {
                $this->inventoryLedgerRepository->deleteByTransactionId($transactionId);
                $existingDbItemIds = $this->transactionItemRepository->getItemIdsByTransactionId($transactionId);
            }

            $savedTransactionId = $this->transactionRepository->save($mainTransactionSaveData);
            if (!$savedTransactionId && !$isEditMode) { 
                throw new Exception('خطا در ذخیره اطلاعات پایه معامله (ID دریافت نشد).');
            }
            $finalTransactionId = $isEditMode ? $transactionId : $savedTransactionId;

            $submittedItemIdsWithData = [];
            foreach ($validatedItemsData as $itemData) {
                if (!empty($itemData['id'])) {
                    $submittedItemIdsWithData[$itemData['id']] = $itemData; 
                }
            }
            $submittedExistingItemIds = array_keys($submittedItemIdsWithData);

            if ($isEditMode) {
                $itemsToDelete = array_diff($existingDbItemIds, $submittedExistingItemIds);
                foreach ($itemsToDelete as $itemIdToDelete) {
                    $this->logger->debug('Deleting transaction item.', ['tx_id' => $finalTransactionId, 'item_id' => $itemIdToDelete]);
                    $this->transactionItemRepository->delete($itemIdToDelete);
                }
            }

            // لاگ نهایی داده تراکنش قبل از ذخیره
            $this->logger->debug('FINAL TRANSACTION DATA FOR DB', ['mainTransactionSaveData' => $mainTransactionSaveData]);
            foreach ($validatedItemsData as $itemToSave) {
                $allowedFields = \App\Models\TransactionItem::getAllowedFields();
                $itemSaveData = array_intersect_key($itemToSave, array_flip($allowedFields));
                $itemSaveData['transaction_id'] = $finalTransactionId;
                // لاگ نهایی داده آیتم قبل از ذخیره
                $this->logger->debug('FINAL ITEM DATA FOR DB', ['itemSaveData' => $itemSaveData]);
                $itemModel = new \App\Models\TransactionItem($itemSaveData);
                $savedItemId = $this->transactionItemRepository->save($itemModel);
                if (!$savedItemId) {
                    $this->logger->error("خطا در ذخیره آیتم تراکنش", ['item_data' => $itemSaveData]);
                    throw new Exception("خطا در ذخیره آیتم تراکنش برای محصول: " . ($itemSaveData['product_id'] ?? 'نامشخص'));
                }
                // مقداردهی مالیات و ارزش افزوده فقط اگر فعال باشد
                if ($product && !empty($product->tax_enabled)) {
                    $group = strtolower($product->category->base_category ?? '');
                    $itemModel->general_tax = $itemData['item_general_tax_' . $group] ?? 0;
                    $itemModel->vat = $itemData['item_vat_' . $group] ?? 0;
                } else {
                    $itemModel->general_tax = 0;
                    $itemModel->vat = 0;
                }
            }

            $this->db->commit();
            $this->logger->info("Transaction and items saved successfully, DB transaction committed.", ['transaction_id' => $finalTransactionId, 'is_edit' => $isEditMode]);

            $actionWord = $isEditMode ? Helper::getMessageText('edit') : Helper::getMessageText('add');
            $messageKey = $isEditMode ? 'transaction_update_success' : 'transaction_create_success';
            $successMsg = Helper::getMessageText($messageKey);
            $this->setSessionMessage($successMsg, 'success', 'transaction_success');

            Helper::logActivity(
                $this->db,
                "Transaction {$actionWord}: ID {$finalTransactionId}",
                $isEditMode ? 'TRANSACTION_UPDATED' : 'TRANSACTION_CREATED',
                'INFO',
                ['transaction_id' => $finalTransactionId, 'items_count' => count($validatedItemsData)]
            );

            // --- پاسخ‌دهی پویا بر اساس نوع درخواست ---
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $successMsg, 'transaction_id' => $finalTransactionId]);
                exit;
            }

            $this->redirect('/app/transactions');
            return; 

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->logger->error("Rolling back DB transaction due to error during save.", ['tx_id' => $transactionId]);
                $this->db->rollBack();
            }
            $this->logger->error("Critical error saving transaction.", [
                'tx_id' => $transactionId,
                'is_edit' => $isEditMode,
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                 'trace' => $e->getTraceAsString() 
            ]);

            $detailedError = $this->config['app']['debug'] ? (" جزئیات فنی: " . Helper::escapeHtml($e->getMessage())) : '';
            $userMessage = Helper::getMessageText('transaction_save_error') . $detailedError; // Use key for base message
            if ($e instanceof PDOException && $e->getCode() == '23000') {
                $userMessage = Helper::getMessageText('database_constraint_error'); // Use specific key for constraint error
            }

            // --- پاسخ‌دهی پویا بر اساس نوع درخواست ---
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $userMessage]);
                exit;
            }

            $this->setSessionMessage($userMessage, 'danger', 'form_error');
            $_SESSION[$sessionFormDataKey] = $sanitizedData; 
            $this->redirect($redirectUrlOnError);
            return; 
        }
    }


    /**
     * Processes the delete request for a transaction. Reverts inventory if completed.
     * Route: /app/transactions/delete/{id} (POST)
     *
     * @param int $transactionId The ID of the transaction to delete.
     */
    public function delete(int $transactionId): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("Delete action called with invalid method.", ['method' => $_SERVER['REQUEST_METHOD']]);
            $this->redirect('/app/transactions');
            return;
        }

        // --- CSRF Token Validation ---
        $submittedToken = $_POST['csrf_token'] ?? null; // Assuming token is sent in POST
        if (!Helper::verifyCsrfToken($submittedToken)) {
            $this->logger->error("CSRF token validation failed for transaction delete.", ['tx_id' => $transactionId]);
            $this->setSessionMessage(Helper::getMessageText('csrf_token_invalid'), 'danger', 'transaction_error');
            $this->redirect('/app/transactions');
            return;
        }
        // Helper::regenerateCsrfToken(); // Optional

        if ($transactionId <= 0) {
            $this->setSessionMessage(Helper::getMessageText('invalid_transaction_id', 'شناسه معامله نامعتبر است.'), 'danger', 'transaction_error'); // Added specific key or fallback
            $this->redirect('/app/transactions');
            return;
        }
        $this->logger->info("Attempting delete.", ['transaction_id' => $transactionId]);

        try {
            $this->db->beginTransaction();

            $txToDelete = $this->transactionRepository->findByIdWithItems($transactionId); // Fetch full transaction to revert inventory
            if (!$txToDelete) {
                $this->db->rollBack();
                $this->setSessionMessage(Helper::getMessageText('transaction_not_found'), 'warning', 'transaction_error'); // Using 'warning' as it's not a hard error
                $this->redirect('/app/transactions');
                return;
            }

            // --- Revert Inventory Changes ---
            // This requires knowing the original items and their quantities/weights.
            // The inventory ledger should ideally handle reversals based on transaction_id.
            // For simplicity, let's assume we delete ledger entries for this transaction.
            // A more robust system would create "reversal" ledger entries.
            $this->inventoryLedgerRepository->deleteByTransactionId($transactionId);
            $this->logger->info("Inventory ledger entries deleted for transaction before actual delete.", ['tx_id' => $transactionId]);
            

            // Delete the transaction items first due to FK constraints
            $this->transactionItemRepository->deleteByTransactionId($transactionId);
            $this->logger->info("Transaction items deleted.", ['tx_id' => $transactionId]);

            // Then delete the main transaction record
            $isDeleted = $this->transactionRepository->delete($transactionId);


            if ($isDeleted) {
                $this->db->commit();
                $message = Helper::getMessageText('transaction_delete_success');
                Helper::logActivity($this->db, "Transaction deleted: ID {$transactionId}", 'TRANSACTION_DELETED', 'INFO', ['transaction_id' => $transactionId]);
                $this->setSessionMessage($message, 'success', 'transaction_success');
                $this->logger->info("Transaction deleted successfully.", ['tx_id' => $transactionId]);
            } else {
                $this->db->rollBack();
                $this->setSessionMessage(Helper::getMessageText('transaction_delete_error'), 'danger', 'transaction_error');
                $this->logger->warning("Transaction delete failed (not found or not deleted by repo).", ['tx_id' => $transactionId]);
            }

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logger->error("Error deleting transaction.", ['tx_id' => $transactionId, 'exception' => $e]);
            $errorMessage = 'خطا در حذف معامله.';
            if ($e instanceof PDOException && $e->getCode() == '23000') {
                $errorMessage = "امکان حذف نیست: به پرداخت‌ها یا سایر رکوردها مرتبط است.";
            } elseif ($this->config['app']['debug']) {
                $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
            }
            $this->setSessionMessage($errorMessage, 'danger', 'transaction_error');
        }
        $this->redirect('/app/transactions');
    }

    /**
     * Completes the delivery or receipt process for a transaction using DeliveryService.
     * Route: /app/transactions/complete-delivery/{id}/{action:receipt|delivery} (POST)
     *
     * @param int $transactionId The ID of the transaction.
     * @param string $actionType 'receipt' or 'delivery'.
     */
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

    /**
     * تشخیص گروه کالا برای mapping پویا
     * @param array $item آیتم تراکنش
     * @return string گروه کالا (MELTED, MANUFACTURED, COIN, GOLDBULLION, JEWELRY)
     */
    private function detectItemGroup(array $item): string
    {
        // اگر category_code مستقیماً در آیتم باشد
        if (!empty($item['category_code'])) {
            return strtoupper($item['category_code']);
        }
        // اگر product_id وجود دارد، گروه را از محصول پیدا کن
        if (!empty($item['product_id']) && isset($this->productRepository)) {
            $product = $this->productRepository->findById((int)$item['product_id']);
            if ($product && !empty($product->category) && !empty($product->category->code)) {
                return strtoupper($product->category->code);
            }
        }
        // تشخیص بر اساس وجود فیلدهای خاص هر گروه
        if (isset($item['item_carat_melted']) || isset($item['item_weight_scale_melted'])) return 'MELTED';
        if (isset($item['item_carat_manufactured']) || isset($item['item_weight_scale_manufactured'])) return 'MANUFACTURED';
        if (isset($item['item_quantity_coin']) || isset($item['item_unit_price_coin'])) return 'COIN';
        if (isset($item['item_carat_goldbullion']) || isset($item['item_weight_scale_goldbullion'])) return 'GOLDBULLION';
        if (isset($item['item_weight_carat_jewelry']) || isset($item['item_unit_price_jewelry'])) return 'JEWELRY';
        // پیش‌فرض
        return 'MELTED';
    }

} // End TransactionController class