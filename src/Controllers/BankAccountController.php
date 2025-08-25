<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions
use Exception;
use Morilog\Jalali\Jalalian; // Add Jalalian namespace

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Dependencies
use App\Repositories\BankAccountRepository;
use App\Utils\Helper; // Utility functions
use App\Core\CSRFProtector; // Added for CSRF protection

/**
 * BankAccountController handles HTTP requests related to Bank Accounts.
 * Manages listing, add/edit forms, ledger view, transaction list (placeholder),
 * and save/delete processing.
 * Inherits from AbstractController.
 */
class BankAccountController extends AbstractController {

    private BankAccountRepository $bankAccountRepository;

    /**
     * Constructor. Injects dependencies.
     *
     * @param PDO $db Database connection.
     * @param Logger $logger Logger instance.
     * @param array $config Application configuration.
     * @param ViewRenderer $viewRenderer View renderer instance.
     * @param array $services Array of application services.
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services // Receive the $services array
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services); // Pass all to parent

        // Retrieve specific repository
        if (!isset($this->services['bankAccountRepository']) || !$this->services['bankAccountRepository'] instanceof BankAccountRepository) {
            throw new \Exception('BankAccountRepository not found in services array for BankAccountController.');
        }
        $this->bankAccountRepository = $this->services['bankAccountRepository'];
        $this->logger->debug("BankAccountController initialized.");
    }

    /**
     * Displays the list of bank accounts.
     * Route: /app/bank-accounts (GET)
     */
    public function index(): void {
        $this->requireLogin();
        // Optional: Add permission check like:
        // if (!$this->authService->hasPermission('view_bank_accounts')) { $this->redirectWithError(...); }

        $pageTitle = "مدیریت حساب‌های بانکی";
        $accounts = [];
        $errorMessage = $this->getFlashMessage('bank_account_error'); // Get potential flash error
        $successMessage = $this->getFlashMessage('bank_account_success'); // Get potential flash success

        try {
            // Use Repository to fetch all accounts and total balance separately
            $accountsData = $this->bankAccountRepository->getAll(); // Fetch all accounts
            $totalBalance = $this->bankAccountRepository->getTotalCurrentBalance(); // Fetch total balance

            // Check for errors during fetching
            if ($accountsData === null || $totalBalance === null) {
                throw new Exception("Error fetching bank account data from repository.");
            }

            // Prepare data for display
            $accounts = $accountsData; // Assuming getAll() returns the array directly
            foreach ($accounts as &$acc) { // Use reference
                $acc['account_name'] = Helper::escapeHtml($acc['account_name']);
                $acc['bank_name'] = Helper::escapeHtml($acc['bank_name'] ?? '');
                $acc['account_number'] = Helper::escapeHtml($acc['account_number'] ?? '');
                // Format balances using Helper
                $acc['current_balance_formatted'] = Helper::formatRial($acc['current_balance'] ?? 0);
                $acc['created_at_persian'] = $acc['created_at'] ? Jalalian::fromFormat('Y-m-d H:i:s', $acc['created_at'])->format('Y/m/d H:i') : '-';
            }
            unset($acc); // Unset reference

            $this->logger->debug("Bank accounts list and total balance fetched successfully.", [
                'count' => count($accounts),
                'total_balance' => $totalBalance
            ]);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching bank accounts list or total balance.", ['exception' => $e]);
            $errorMessage = "خطا در بارگذاری اطلاعات حساب‌ها.";
            if ($this->config['app']['debug']) {
                 $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
            }
            $accounts = [];
            $totalBalance = 0; // Default total balance on error
        }

        // Render the list view
        $this->render('bank_accounts/list', [
            'page_title' => $pageTitle,
            'accounts'   => $accounts,
            'total_balance_formatted' => Helper::formatRial($totalBalance), // Add formatted total balance
            'error_msg'  => $errorMessage, // Pass the error message directly
            'success_msg'=> $successMessage ? $successMessage['text'] : null
            // Add pagination data here if implemented
        ]);
    }

    /**
     * Displays the form for adding a new bank account.
     * Route: /app/bank-accounts/add (GET)
     */
    public function showAddForm(): void {
        $this->requireLogin();
        // Optional: Permission check for adding accounts

        $pageTitle = "افزودن حساب بانکی جدید";
        $formError = $this->getFlashMessage('form_error');
        $formData = $_SESSION['form_data']['bank_account_add'] ?? null;
        if ($formData) { unset($_SESSION['form_data']['bank_account_add']); }

        $defaultData = [
            'id'             => null,
            'account_name'   => Helper::escapeHtml($formData['account_name'] ?? ''),
            'bank_name'      => Helper::escapeHtml($formData['bank_name'] ?? ''),
            'account_number' => Helper::escapeHtml($formData['account_number'] ?? ''),
            'initial_balance'=> $formData['initial_balance'] ?? '0', // Keep raw for repopulation
            'current_balance'=> '' // Not applicable for add form display
        ];

        $this->render('bank_accounts/form', [
            'page_title'         => $pageTitle,
            'form_action'        => $this->config['app']['base_url'] . '/app/bank-accounts/save',
            'account'            => $defaultData,
            'is_edit_mode'       => false,
            'submit_button_text' => 'ذخیره حساب جدید',
            'error_message'      => $formError ? $formError['text'] : null,
            'loading_error'      => null
        ]);
    }

    /**
     * Displays the form for editing an existing bank account.
     * Route: /app/bank-accounts/edit/{id} (GET)
     *
     * @param int $accountId The ID of the account to edit.
     */
    public function showEditForm(int $accountId): void {
        $this->requireLogin();
        // Optional: Permission check for editing accounts

        $pageTitle = "ویرایش حساب بانکی";
        $loadingError = null;
        $formError = $this->getFlashMessage('form_error');
        $accountData = null;

        if ($accountId <= 0) {
            $this->setSessionMessage('شناسه حساب بانکی نامعتبر است.', 'danger', 'bank_account_error');
            $this->redirect('/app/bank-accounts');
        }

        // Check for repopulation data first
        $sessionFormDataKey = 'bank_account_edit_' . $accountId;
        $sessionFormData = $_SESSION['form_data'][$sessionFormDataKey] ?? null;
        if ($sessionFormData) {
            unset($_SESSION['form_data'][$sessionFormDataKey]);
            $accountData = [
                'id'             => $accountId,
                'account_name'   => Helper::escapeHtml($sessionFormData['account_name'] ?? ''),
                'bank_name'      => Helper::escapeHtml($sessionFormData['bank_name'] ?? ''),
                'account_number' => Helper::escapeHtml($sessionFormData['account_number'] ?? ''),
                // Keep initial balance as it was (non-editable), get current from session
                'initial_balance'=> $sessionFormData['initial_balance_display'] ?? '0', // Display value
                'current_balance'=> $sessionFormData['current_balance'] ?? '' // Raw input value
            ];
            $pageTitle .= " (داده‌های اصلاح نشده)";
            $this->logger->debug("Repopulating edit bank account form from session data.", ['account_id' => $accountId]);
        } else {
            // Fetch from database
            try {
                $accountFromDb = $this->bankAccountRepository->getById($accountId);
                if (!$accountFromDb) {
                    $this->setSessionMessage('حساب بانکی یافت نشد.', 'warning', 'bank_account_error');
                    $this->redirect('/app/bank-accounts');
                }
                // Prepare data for form display
                $accountData = [
                    'id'             => (int)$accountFromDb['id'],
                    'account_name'   => Helper::escapeHtml($accountFromDb['account_name'] ?? ''),
                    'bank_name'      => Helper::escapeHtml($accountFromDb['bank_name'] ?? ''),
                    'account_number' => Helper::escapeHtml($accountFromDb['account_number'] ?? ''),
                    // Format numbers without thousand separators for input fields
                    'initial_balance'=> Helper::formatNumber($accountFromDb['initial_balance'] ?? 0, 0, '.', ''),
                    'current_balance'=> Helper::formatNumber($accountFromDb['current_balance'] ?? 0, 0, '.', ''),
                    // Store original initial balance separately if needed for display only
                    'initial_balance_display' => Helper::formatNumber($accountFromDb['initial_balance'] ?? 0, 0, '.', '')
                ];
                 $this->logger->debug("Bank account data fetched from database.", ['account_id' => $accountId]);
            } catch (Throwable $e) {
                $this->logger->error("Error loading bank account for editing.", ['account_id' => $accountId, 'exception' => $e]);
                $loadingError = 'خطا در بارگذاری اطلاعات حساب.';
                $accountData = ['id' => $accountId, 'account_name' => '[خطا]', /* other fields empty/default */];
            }
        }

        $this->render('bank_accounts/form', [
            'page_title'         => $pageTitle,
            'form_action'        => $this->config['app']['base_url'] . '/app/bank-accounts/save',
            'account'            => $accountData,
            'is_edit_mode'       => true,
            'submit_button_text' => 'به‌روزرسانی اطلاعات',
            'error_message'      => $formError ? $formError['text'] : null,
            'loading_error'      => $loadingError
        ]);
    }

    /**
     * Processes the save request for a bank account (add or edit).
     * Route: /app/bank-accounts/save (POST)
     */
    public function save(): void {
        $this->requireLogin();
        // Optional: Permission check

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/bank-accounts');
        }

        // CSRF token validation
        if (!CSRFProtector::validateToken($_POST['csrf_token'] ?? null)) {
            $this->setSessionMessage('خطای امنیتی: توکن نامعتبر است.', 'danger', 'bank_account_error');
            $this->redirect('/app/bank-accounts');
        }

        // --- Input Extraction ---
        $accountId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);
        $isEditMode = ($accountId !== null && $accountId > 0);
        $accountName = trim($_POST['account_name'] ?? '');
        $bankName = trim($_POST['bank_name'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $initialBalanceStr = $_POST['initial_balance'] ?? '0'; // Only relevant for add
        $currentBalanceStr = $_POST['current_balance'] ?? null; // Only relevant for edit

        $redirectUrlOnError = '/app/bank-accounts/' . ($isEditMode ? 'edit/' . $accountId : 'add');
        $sessionFormDataKey = $isEditMode ? 'bank_account_edit_' . $accountId : 'bank_account_add';

        // --- Validation & Processing ---
        $errors = [];
        if (empty($accountName)) { $errors[] = 'نام حساب الزامی است.'; }
        elseif (mb_strlen($accountName) > 150) { $errors[] = 'نام حساب طولانی‌تر از حد مجاز (۱۵۰) است.'; }
        if (mb_strlen($bankName) > 100) { $errors[] = 'نام بانک طولانی‌تر از حد مجاز (۱۰۰) است.'; }
        if (mb_strlen($accountNumber) > 50) { $errors[] = 'شماره حساب طولانی‌تر از حد مجاز (۵۰) است.'; }

        $initialBalance = null;
        if (!$isEditMode) { // Process initial balance only on add
            $cleaned = Helper::sanitizeFormattedNumber($initialBalanceStr);
            if ($cleaned !== '' && $cleaned !== null && is_numeric($cleaned)) {
                $initialBalance = (float)$cleaned;
            } elseif (!empty($initialBalanceStr)) { // Error only if input was given but invalid
                $errors[] = 'فرمت موجودی اولیه نامعتبر است.';
            } else {
                $initialBalance = 0.0; // Default to 0 if empty
            }
        }

        $currentBalance = null;
        if ($isEditMode && $currentBalanceStr !== null) { // Process current balance only on edit if submitted
            $cleaned = Helper::sanitizeFormattedNumber($currentBalanceStr);
            if ($cleaned !== '' && $cleaned !== null && is_numeric($cleaned)) {
                $currentBalance = (float)$cleaned;
            } elseif (!empty($currentBalanceStr)) {
                $errors[] = 'فرمت موجودی فعلی نامعتبر است.';
            }
            // If $currentBalanceStr is null (field not submitted/disabled), $currentBalance remains null
        }

        // --- Handle Validation Failure ---
        if (!empty($errors)) {
            $this->logger->warning("Bank account form validation failed.", ['errors' => $errors, 'account_id' => $accountId]);
            $this->setSessionMessage(implode('<br>', $errors), 'danger', 'form_error');
            // Store submitted data (including potentially invalid numbers as strings)
            $_SESSION['form_data'][$sessionFormDataKey] = $_POST;
            // Also store the intended display value for initial balance if editing
             if ($isEditMode) { $_SESSION['form_data'][$sessionFormDataKey]['initial_balance_display'] = $_POST['initial_balance_display'] ?? $initialBalanceStr; }
            $this->redirect($redirectUrlOnError);
        }

        // --- Prepare Data for Repository ---
        $accountData = [
            'id'             => $isEditMode ? $accountId : null,
            'account_name'   => $accountName,
            'bank_name'      => $bankName ?: null,
            'account_number' => $accountNumber ?: null,
        ];
        if (!$isEditMode && $initialBalance !== null) {
             $accountData['initial_balance'] = $initialBalance;
             // Let repository set current_balance = initial_balance on insert
        }
        // Only include current_balance in data array if it was processed (i.e., submitted and valid during edit)
        if ($isEditMode && $currentBalance !== null) {
             $accountData['current_balance'] = $currentBalance;
        }

        // --- Database Operation ---
        try {
            $savedAccountId = $this->bankAccountRepository->save($accountData);
            $actionWord = $isEditMode ? 'به‌روزرسانی' : 'اضافه';
            // Log activity (assumes Helper::logActivity exists and $this->db is accessible)
            Helper::logActivity($this->db, "Bank account {$actionWord}d: {$accountName}", 'SUCCESS', 'INFO', ['account_id' => $savedAccountId]);
            $this->logger->info("Bank account saved successfully.", ['id' => $savedAccountId, 'action' => $actionWord]);

            $this->setSessionMessage("حساب بانکی با موفقیت {$actionWord} شد.", 'success', 'bank_account_success');
            $this->redirect('/app/bank-accounts');

        } catch (Throwable $e) {
            $this->logger->error("Error saving bank account.", ['account_id' => $accountId, 'exception' => $e]);
            $errorMessage = "خطا در ذخیره حساب بانکی.";
            // Check for unique constraints if repo throws specific exceptions
            if ($this->config['app']['debug']) {
                 $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
            }
            $this->setSessionMessage($errorMessage, 'danger', 'form_error');
            $_SESSION['form_data'][$sessionFormDataKey] = $_POST; // Repopulate
             if ($isEditMode) { $_SESSION['form_data'][$sessionFormDataKey]['initial_balance_display'] = $_POST['initial_balance_display'] ?? $initialBalanceStr; }
            $this->redirect($redirectUrlOnError);
        }
    }

    /**
     * Displays the ledger (transaction history) for a specific bank account.
     * Route: /app/bank-accounts/ledger/{id} (GET)
     *
     * @param int $accountId The ID of the bank account.
     */
    public function showLedger(int $accountId): void {
        $this->requireLogin();
        // Optional: Permission check

        $pageTitle = "گردش حساب بانکی";
        $accountInfo = null;
        $ledgerEntries = [];
        $startBalancePeriod = 0.0;
        $endBalancePeriod = 0.0; // Calculated based on start balance and entries
        $currentTotalBalance = 0.0; // Overall current balance
        $errorMessage = $this->getFlashMessage('ledger_error');

        if ($accountId <= 0) {
            $this->setSessionMessage('شناسه حساب بانکی نامعتبر است.', 'danger', 'bank_account_error');
            $this->redirect('/app/bank-accounts');
        }

        // Get date filters from GET request
        $startDateJalali = trim(filter_input(INPUT_GET, 'start_date', FILTER_DEFAULT) ?? '');
        $endDateJalali = trim(filter_input(INPUT_GET, 'end_date', FILTER_DEFAULT) ?? '');

        // Parse dates (Helper handles empty strings -> null)
        $startDateSql = Helper::parseJalaliDateToSql($startDateJalali);
        $endDateSql = Helper::parseJalaliDateToSql($endDateJalali);
         // For end date, often want to include the whole day
        if ($endDateSql) { $endDateSql .= ' 23:59:59'; }


        try {
            // Fetch account info
            $accountInfo = $this->bankAccountRepository->getById($accountId);
            if (!$accountInfo) {
                $this->setSessionMessage('حساب بانکی یافت نشد.', 'warning', 'bank_account_error');
                $this->redirect('/app/bank-accounts');
            }
            $pageTitle .= " - " . Helper::escapeHtml($accountInfo['account_name']);
            $currentTotalBalance = (float)($accountInfo['current_balance'] ?? 0);

            // Fetch ledger entries for the date range
            // Assumes getTransactionsByAccountIdAndDateRange exists
            $ledgerEntries = $this->bankAccountRepository->getTransactionsByAccountIdAndDateRange(
                $accountId,
                $startDateSql, // Can be null
                $endDateSql   // Can be null
            );

            // Calculate starting balance for the period
            // Assumes calculateBalanceBeforeDate exists
            $startBalancePeriod = $this->bankAccountRepository->calculateBalanceBeforeDate($accountId, $startDateSql); // Pass start date SQL (or null)

            // Calculate ending balance for the period (optional, can be done in view)
            $runningBalance = $startBalancePeriod;
            foreach($ledgerEntries as $entry) {
                 $amount = (float)($entry['amount_rials'] ?? 0);
                 $runningBalance += ($entry['direction'] === 'inflow') ? $amount : -$amount;
            }
            $endBalancePeriod = $runningBalance;


            // Prepare entries for display
            foreach ($ledgerEntries as &$entry) {
                 $entry['amount_rials_formatted'] = Helper::formatRial($entry['amount_rials']);
                 $entry['transaction_date_persian'] = $entry['transaction_date'] ? Jalalian::fromFormat('Y-m-d H:i:s', $entry['transaction_date'])->format('Y/m/d H:i') : '-';
                 $entry['description'] = Helper::escapeHtml($entry['description'] ?? '');
                 $entry['direction_farsi'] = ($entry['direction'] === 'inflow') ? 'واریز' : 'برداشت';
                 $entry['balance_after'] = 0; // Calculate running balance in the view
            }
            unset($entry);


            $this->logger->debug("Bank account ledger fetched.", ['account_id' => $accountId, 'count' => count($ledgerEntries)]);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching bank account ledger.", ['account_id' => $accountId, 'exception' => $e]);
            $errorMessage = "خطا در بارگذاری گردش حساب.";
            if ($this->config['app']['debug']) {
                 $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
            }
            $accountInfo = ['id' => $accountId, 'account_name' => '[خطا]', 'current_balance' => 0];
            $ledgerEntries = [];
            $startBalancePeriod = 0.0;
            $endBalancePeriod = 0.0;
            $currentTotalBalance = 0.0;
        }

        // Render the ledger view
        $this->render('bank_accounts/ledger', [
            'page_title' => $pageTitle,
            'account_info' => $accountInfo,
            'ledger_entries' => $ledgerEntries,
            'start_balance_period' => $startBalancePeriod,
            'end_balance_period' => $endBalancePeriod, // Calculated ending balance for the period
            'current_total_balance' => $currentTotalBalance, // Overall current balance
            'filters' => [ // Pass filters back to the view form
                'start_date_jalali' => $startDateJalali,
                'end_date_jalali' => $endDateJalali,
            ],
            'error_msg' => $errorMessage ? $errorMessage['text'] : null,
        ]);
    }

    /**
     * Processes the delete request for a bank account.
     * Route: /app/bank-accounts/delete/{id} (POST)
     *
     * @param int $accountId The ID of the account to delete.
     */
     public function delete(int $accountId): void {
         $this->requireLogin();
         // Optional: Permission check

         if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             $this->redirect('/app/bank-accounts');
         }

         // CSRF token validation
         if (!CSRFProtector::validateToken($_POST['csrf_token'] ?? null)) {
             $this->setSessionMessage('خطای امنیتی: توکن نامعتبر است.', 'danger', 'bank_account_error');
             $this->redirect('/app/bank-accounts');
         }

         if ($accountId <= 0) {
             $this->setSessionMessage('شناسه حساب بانکی نامعتبر است.', 'danger', 'bank_account_error');
             $this->redirect('/app/bank-accounts');
         }

         $this->logger->info("Attempting to delete bank account.", ['account_id' => $accountId]);

         try {
             // Check if the account has any associated transactions
             // Assumes BankAccountRepository::hasTransactions exists
             $hasTransactions = $this->bankAccountRepository->hasTransactions($accountId);

             if ($hasTransactions) {
                 $this->logger->warning("Delete aborted: Bank account has transactions.", ['account_id' => $accountId]);
                 $this->setSessionMessage('این حساب بانکی دارای تراکنش‌های ثبت شده است و قابل حذف نیست.', 'warning', 'bank_account_error');
             } else {
                 // Proceed with deletion
                 // Assumes BankAccountRepository::delete returns true on success, false if not found
                 $isDeleted = $this->bankAccountRepository->delete($accountId);

                 if ($isDeleted) {
                     $this->logger->info("Bank account deleted successfully.", ['account_id' => $accountId]);
                     $this->setSessionMessage('حساب بانکی با موفقیت حذف شد.', 'success', 'bank_account_success');
                 } else {
                     $this->logger->warning("Bank account deletion failed (not found or no rows affected).", ['account_id' => $accountId]);
                     $this->setSessionMessage('حساب بانکی یافت نشد یا حذف آن با مشکل مواجه شد.', 'warning', 'bank_account_error');
                 }
             }

         } catch (Throwable $e) { // Catch errors from repository (e.g., DB error)
             $this->logger->error("Error deleting bank account.", ['account_id' => $accountId, 'exception' => $e]);
             $errorMessage = "خطا در حذف حساب بانکی.";
             // Check for specific foreign key constraint errors if repo doesn't handle it gracefully
             if ($this->config['app']['debug']) {
                  $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
             }
             $this->setSessionMessage($errorMessage, 'danger', 'bank_account_error');
         }

         $this->redirect('/app/bank-accounts');
     }

    /**
     * Displays a list of all bank transactions (Placeholder).
     * Route: /app/bank-accounts/transactions (GET)
     */
     public function listTransactions(): void {
         $this->requireLogin();
         // Optional: Permission check

         $this->logger->info("Accessing listTransactions (currently placeholder).");

         // --- Placeholder Logic ---
         // 1. Get filters (date range, account, type?) from GET request.
         // 2. Use BankAccountRepository::getFilteredTransactions(...) to fetch paginated transactions.
         // 3. Prepare data for view (formatting, escaping).
         // 4. Render a view (e.g., 'bank_accounts/transactions.php').
         // --- End Placeholder ---

         $this->setSessionMessage('صفحه لیست تراکنش‌های بانکی هنوز پیاده‌سازی نشده است.', 'info');
         $this->redirect('/app/bank-accounts'); // Redirect back to accounts list for now
     }

} // End BankAccountController class