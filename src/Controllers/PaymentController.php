<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions
use PDOException; // For specific DB exceptions
use Exception;
use Morilog\Jalali\Jalalian;

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Dependencies
use App\Repositories\PaymentRepository;
use App\Repositories\ContactRepository; // For contact dropdowns
use App\Repositories\BankAccountRepository; // For bank account dropdowns
use App\Repositories\TransactionRepository; // For related transaction dropdown (optional)
use App\Utils\Helper; // Utility functions
use App\core\CSRFProtector;

/**
 * PaymentController handles HTTP requests related to Payments (Inflows/Outflows).
 * Manages listing, add/edit forms, and save/delete processing, including related bank transactions.
 * Inherits from AbstractController.
 */
class PaymentController extends AbstractController {

    // Dependencies
    private PaymentRepository $paymentRepository;
    private ContactRepository $contactRepository;
    private BankAccountRepository $bankAccountRepository;
    // Optional: Inject if linking payments to specific gold transactions
    private ?TransactionRepository $transactionRepository = null; // Made nullable

    // Define valid payment methods
    private const array VALID_PAYMENT_METHODS = [
        'cash' => 'نقدی',
        'barter' => 'تهاتر',
        'bank_slip' => 'فیش بانکی',
        'mobile_transfer' => 'همراه بانک',
        'internet_transfer' => 'اینترنت بانک',
        'atm' => 'کارتخوان ATM',
        'pos' => 'کارتخوان POS',
        'cheque' => 'چک بانکی',
        'clearing_account' => 'حساب واسط'
    ];

    /**
     * Constructor. Injects dependencies.
     *
     * @param PDO $db
     * @param Logger $logger
     * @param array $config
     * @param ViewRenderer $viewRenderer
     * @param array $services Array of application services.
     * @throws \Exception If required repositories are missing.
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services // Receive the $services array
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services); // Pass all to parent

        // Retrieve specific repositories
        if (!isset($this->services['paymentRepository']) || !$this->services['paymentRepository'] instanceof PaymentRepository) {
            throw new \Exception('PaymentRepository not found for PaymentController.');
        }
        $this->paymentRepository = $this->services['paymentRepository'];

        if (!isset($this->services['contactRepository']) || !$this->services['contactRepository'] instanceof ContactRepository) {
            throw new \Exception('ContactRepository not found for PaymentController.');
        }
        $this->contactRepository = $this->services['contactRepository'];

        if (!isset($this->services['bankAccountRepository']) || !$this->services['bankAccountRepository'] instanceof BankAccountRepository) {
            throw new \Exception('BankAccountRepository not found for PaymentController.');
        }
        $this->bankAccountRepository = $this->services['bankAccountRepository'];

        // Optional: Load TransactionRepository if needed
        if (isset($this->services['transactionRepository']) && $this->services['transactionRepository'] instanceof TransactionRepository) {
            $this->transactionRepository = $this->services['transactionRepository'];
        } else {
            $this->logger->warning("TransactionRepository not found or invalid, related transaction dropdown will be empty.");
            // Handle optional dependency if necessary - allow controller to work without it
        }


        $this->logger->debug("PaymentController initialized.");
    }

    /**
     * Displays the list of payments.
     * Includes search and pagination.
     * Route: /app/payments (GET)
     */
    public function index(): void {
        $this->requireLogin();
        // Optional: Permission check

        $pageTitle = "مدیریت پرداخت‌ها و دریافت‌ها";
        $payments = [];
        $paginationData = [];
        $errorMessage = $this->getFlashMessage('payment_error');
        $successMessage = $this->getFlashMessage('payment_success');

        // Search and Pagination
        $itemsPerPage = (int)($this->config['app']['items_per_page'] ?? 15);
        $currentPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        // Refine search - search in contacts, notes, amount?
        $searchTerm = trim(filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? '');
        // Add other filters (date range, direction, contact, bank account)
        // $filterDirection = filter_input(INPUT_GET, 'direction', FILTER_SANITIZE_STRING);
        // $filterContactId = filter_input(INPUT_GET, 'contact_id', FILTER_VALIDATE_INT);

        try {
            // Fetch data using Repository (assume method handles filters, search, joins, pagination)
            // $filters = ['direction' => $filterDirection, 'contact_id' => $filterContactId, ...];
            $totalRecords = $this->paymentRepository->countFiltered($searchTerm /*, $filters */);
            $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $itemsPerPage) : 1;
            $currentPage = max(1, min($currentPage, $totalPages));
            $offset = ($currentPage - 1) * $itemsPerPage;

            $payments = $this->paymentRepository->getFilteredAndPaginated($searchTerm, $itemsPerPage, $offset /*, $filters */);

            // Prepare data for display
            foreach ($payments as &$payment) { // Use reference
                 $payment['amount_rials_formatted'] = Helper::formatRial($payment['amount_rials'] ?? 0);
                 $payment['direction_farsi'] = match($payment['direction'] ?? '') { 'inflow' => 'دریافت', 'outflow' => 'پرداخت', default => 'نامشخص' };
                 // Display paying/receiving based on direction for clarity
                 $payment['payer_display'] = Helper::escapeHtml($payment['paying_contact_name'] ?? $payment['paying_details'] ?? 'نامشخص');
                 $payment['receiver_display'] = Helper::escapeHtml($payment['receiving_contact_name'] ?? $payment['receiving_details'] ?? 'نامشخص');
                 // Simplify display based on direction
                 $payment['related_party_name'] = ($payment['direction'] === 'inflow') ? $payment['payer_display'] : $payment['receiver_display'];
                 $payment['bank_account_display'] = Helper::escapeHtml($payment['bank_account_name'] ?? '-'); // Assuming join brings bank name
                 $payment['notes_short'] = Helper::escapeHtml(mb_substr($payment['notes'] ?? '', 0, 40, 'UTF-8')) . (mb_strlen($payment['notes'] ?? '') > 40 ? '…' : '');
                 $payment['payment_date_persian'] = $payment['payment_date'] ? Jalalian::fromFormat('Y-m-d H:i:s', $payment['payment_date'])->format('Y/m/d H:i') : '-';
                 // Link to related transaction if exists
                 $payment['related_transaction_link'] = isset($payment['related_transaction_id']) && $payment['related_transaction_id'] > 0
                     ? $this->config['app']['base_url'] . '/app/transactions/edit/' . $payment['related_transaction_id']
                     : null;
            }
            unset($payment);

            $paginationData = Helper::generatePaginationData($currentPage, $totalPages, $totalRecords, $itemsPerPage);
            $this->logger->debug("Payments list fetched.", ['count' => count($payments), 'total' => $totalRecords]);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching payments list.", ['exception' => $e]);
            $errorMessage = $errorMessage ?: ['text' => "خطا در بارگذاری لیست پرداخت‌ها."];
            if ($this->config['app']['debug']) { $errorMessage['text'] .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
            $payments = [];
            $paginationData = Helper::generatePaginationData(1, 1, 0, $itemsPerPage);
        }

        $this->render('payments/list', [
            'page_title'     => $pageTitle,
            'payments'       => $payments,
            'error_msg'      => $errorMessage ? $errorMessage['text'] : null,
            'success_msg'    => ($this->getFlashMessage('payment_success'))['text'] ?? null,
            'search_term'    => Helper::escapeHtml($searchTerm),
            'pagination'     => $paginationData
             // Pass other filters back to view if implemented
        ]);
    }

     /**
      * Displays the form for adding a new payment/receipt.
      * Route: /app/payments/add (GET)
      */
     public function showAddForm(): void {
         $this->requireLogin();
         // Optional: Permission check

         $pageTitle = "افزودن پرداخت/دریافت جدید";
         $formError = $this->getFlashMessage('form_error');
         $sessionKey = 'payment_add_data';
         $formData = $_SESSION[$sessionKey] ?? null;
         if ($formData) { unset($_SESSION[$sessionKey]); }

         $contacts = []; $bankAccounts = []; $transactions = []; $loadingError = null;
         // Fetch dropdown data
         try {
             $contacts = $this->contactRepository->getAll();
             $bankAccounts = $this->bankAccountRepository->getAll();
             if ($this->transactionRepository) { // Check if repository was loaded
                 $transactions = $this->transactionRepository->getLatestTransactions(null, 15); // Fetch latest 15 transactions
             } else {
                 $transactions = []; // Ensure it's an empty array if repo is not available
             }
         } catch (Throwable $e) {
             $this->logger->error("Error fetching dropdown data for add payment form.", ['exception' => $e]);
             $loadingError = "خطا در بارگذاری لیست‌های پیش‌نیاز.";
         }

         $defaultData = [ // Default values, potentially overridden by repopulation data
             'id' => null,
             'payment_date' => $formData['payment_date'] ?? '', // Set to empty string initially for add form
             'amount_rials' => $formData['amount_rials'] ?? '',
             'direction' => $formData['direction'] ?? 'outflow',
             'paying_contact_id' => $formData['paying_contact_id'] ?? null,
             'paying_details' => Helper::escapeHtml($formData['paying_details'] ?? ''),
             'receiving_contact_id' => $formData['receiving_contact_id'] ?? null,
             'receiving_details' => Helper::escapeHtml($formData['receiving_details'] ?? ''),
             'related_transaction_id' => $formData['related_transaction_id'] ?? null,
             'notes' => Helper::escapeHtml($formData['notes'] ?? ''),
             'source_bank_account_id' => $formData['source_bank_account_id'] ?? null,
             'destination_bank_account_id' => $formData['destination_bank_account_id'] ?? null,
         ];

         $this->render('payments/form', [
             'page_title'         => $pageTitle,
             'form_action'        => $this->config['app']['base_url'] . '/app/payments/save',
             'payment'            => $defaultData,
             'contacts'           => $contacts,
             'bank_accounts'      => $bankAccounts,
             'transactions'       => $transactions, // Pass transactions if fetched
             'is_edit_mode'       => false,
             'submit_button_text' => 'ذخیره',
             'error_message'      => $formError ? $formError['text'] : null,
             'loading_error'      => $loadingError,
             'payment_methods'    => self::VALID_PAYMENT_METHODS // Pass payment methods to the view
         ]);
     }

     /**
      * Displays the form for editing an existing payment/receipt.
      * Route: /app/payments/edit/{id} (GET)
      *
      * @param int $paymentId The ID of the payment to edit.
      */
     public function showEditForm(int $paymentId): void {
         $paymentId = (int)$paymentId; // Cast to int here
         $this->requireLogin();
         // Optional: Permission check

         $pageTitle = "ویرایش پرداخت/دریافت";
         $loadingError = null;
         $formError = $this->getFlashMessage('form_error');
         $paymentData = null;
         $contacts = []; $bankAccounts = []; $transactions = [];
         $sessionKey = 'payment_edit_data_' . $paymentId;

         if ($paymentId <= 0) {
             $this->setSessionMessage('شناسه نامعتبر است.', 'danger', 'payment_error');
             $this->redirect('/app/payments');
         }

         $sessionFormData = $_SESSION[$sessionKey] ?? null;
         if ($sessionFormData) {
             unset($_SESSION[$sessionKey]);
             $paymentData = $sessionFormData; // Use raw session data for repopulation
             $paymentData['id'] = $paymentId; // Ensure ID
             $pageTitle .= " (داده‌های اصلاح نشده)";
             $this->logger->debug("Repopulating edit payment form from session.", ['payment_id' => $paymentId]);
         } else {
             try {
                 $paymentFromDb = $this->paymentRepository->getById($paymentId);
                 if (!$paymentFromDb) {
                     $this->setSessionMessage('پرداخت/دریافت یافت نشد.', 'warning', 'payment_error');
                     $this->redirect('/app/payments');
                 }
                 // Format data for form display
                 $paymentData = $paymentFromDb;
                 // مقدار خام میلادی برای فرم
                 $paymentData['payment_date'] = $paymentFromDb['payment_date'] ?? '';
                 $paymentData['payment_date_persian'] = $paymentFromDb['payment_date'] ? Jalalian::fromFormat('Y-m-d H:i:s', $paymentFromDb['payment_date'])->format('Y/m/d H:i:s') : '-';
                 $paymentData['amount_rials'] = Helper::formatNumber($paymentFromDb['amount_rials'], 0, '.', '');
                 // Find related bank transaction to pre-select source/destination account
                  $relatedBankTx = $this->paymentRepository->findRelatedBankTransaction($paymentId);
                  $paymentData['source_bank_account_id'] = null;
                  $paymentData['destination_bank_account_id'] = null;
                  if ($relatedBankTx) {
                       if ((float)$relatedBankTx['amount'] < 0) { // Withdrawal (source)
                           $paymentData['source_bank_account_id'] = $relatedBankTx['bank_account_id'];
                       } else { // Deposit (destination)
                            $paymentData['destination_bank_account_id'] = $relatedBankTx['bank_account_id'];
                       }
                  }
                 $this->logger->debug("Payment data fetched from database.", ['payment_id' => $paymentId]);
             } catch (Throwable $e) {
                 $this->logger->error("Error loading payment for editing.", ['payment_id' => $paymentId, 'exception' => $e]);
                 $loadingError = 'خطا در بارگذاری اطلاعات.';
                 $paymentData = ['id' => $paymentId]; // Minimal data
             }
         }

         // Fetch dropdown data
         try {
             $contacts = $this->contactRepository->getAll();
             $bankAccounts = $this->bankAccountRepository->getAll();
             if ($this->transactionRepository) { // Check if repository was loaded
                 $transactions = $this->transactionRepository->getLatestTransactions(null, 15); // Fetch latest 15 transactions
             } else {
                 $transactions = []; // Ensure it's an empty array if repo is not available
             }
         } catch (Throwable $e) {
             $this->logger->error("Error fetching dropdown data for edit payment form.", ['exception' => $e]);
             $loadingError = ($loadingError ? $loadingError . '<br>' : '') . "خطا در بارگذاری لیست‌های پیش‌نیاز.";
         }

         $this->render('payments/form', [
             'page_title'         => $pageTitle,
             'form_action'        => $this->config['app']['base_url'] . '/app/payments/save',
             'payment'            => $paymentData,
             'contacts'           => $contacts,
             'bank_accounts'      => $bankAccounts,
             'transactions'       => $transactions,
             'is_edit_mode'       => true,
             'submit_button_text' => 'به‌روزرسانی',
             'error_message'      => $formError ? $formError['text'] : null,
             'loading_error'      => $loadingError,
             'payment_methods'    => self::VALID_PAYMENT_METHODS // Pass payment methods to the view
         ]);
     }

    /**
     * Processes the save request for a payment (add or edit).
     * Handles related bank transaction updates.
     * Route: /app/payments/save (POST)
     */
    public function save(): void {
        $this->requireLogin();
        // Optional: Permission check

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/payments'); }
        // TODO: Add CSRF token validation

        // --- Input Extraction ---
        $paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
        $isEditMode = ($paymentId !== null && $paymentId > 0);
        $paymentDateStr = $_POST['payment_date'] ?? '';
        $amountRialsStr = $_POST['amount_rials'] ?? '';
        $direction = '';
        $payingContactId = filter_input(INPUT_POST, 'paying_contact_id', FILTER_VALIDATE_INT) ?: null;
        $payingDetails = trim($_POST['paying_details'] ?? '');
        $receivingContactId = filter_input(INPUT_POST, 'receiving_contact_id', FILTER_VALIDATE_INT) ?: null;
        $receivingDetails = trim($_POST['receiving_details'] ?? '');
        $relatedTransactionId = filter_input(INPUT_POST, 'related_transaction_id', FILTER_VALIDATE_INT) ?: null;
        $notes = trim($_POST['notes'] ?? '');
        $sourceBankAccountId = filter_input(INPUT_POST, 'source_bank_account_id', FILTER_VALIDATE_INT) ?: null;
        $destinationBankAccountId = filter_input(INPUT_POST, 'destination_bank_account_id', FILTER_VALIDATE_INT) ?: null;

        // تعیین direction به صورت خودکار:
        if (!empty($sourceBankAccountId)) {
            $direction = 'outflow';
        } elseif (!empty($destinationBankAccountId)) {
            $direction = 'inflow';
        } else {
            // بر اساس مدل پرداخت یا سایر منطق‌ها مقداردهی شود
            $direction = 'outflow'; // مقدار پیش‌فرض
        }

        // --- New Payment Method Fields ---
        $paymentMethod = $_POST['payment_method'] ?? null;
        $methodDetails = [];
        $detailFields = [
            'payer_receiver', 'clearing_type', 'slip_number', 'slip_date', 'bank_agent',
            'tracking_code', 'transfer_date', 'source_dest_info', 'terminal_id', 'pos_holder',
            'cheque_holder_nid', 'cheque_account_number', 'cheque_holder_name', 'cheque_type',
            'cheque_serial', 'cheque_sayad_id', 'cheque_due_date'
        ];
        foreach ($detailFields as $fieldSuffix) {
            $postKey = 'method_details_' . $fieldSuffix;
            // Trim and set to null if empty string after trim
            $value = isset($_POST[$postKey]) ? trim($_POST[$postKey]) : null;
            $methodDetails[$postKey] = ($value === '') ? null : $value;
        }

        $redirectUrlOnError = '/app/payments/' . ($isEditMode ? 'edit/' . $paymentId : 'add');
        $sessionFormDataKey = $isEditMode ? 'payment_edit_data_' . $paymentId : 'payment_add_data';

        // --- Validation ---
        $errors = [];
        $paymentDateSql = Helper::parseJalaliDatetimeToSql($paymentDateStr);
        if (!$paymentDateSql && !empty($paymentDateStr)) { $errors[] = 'فرمت تاریخ و زمان نامعتبر است.'; }
        elseif (!$paymentDateSql) { $errors[] = 'تاریخ و زمان الزامی است.'; }

        $amountRials = null;
        $cleanedAmount = Helper::sanitizeFormattedNumber($amountRialsStr);
        if ($cleanedAmount !== '' && $cleanedAmount !== null && is_numeric($cleanedAmount) && ($amt = floatval($cleanedAmount)) > 0) {
            $amountRials = $amt;
        } elseif (!empty($amountRialsStr)) { $errors[] = 'مبلغ نامعتبر است.'; }
        else { $errors[] = 'مبلغ الزامی است.'; }

        if (!in_array($direction, ['inflow', 'outflow'])) { $errors[] = 'جهت (دریافت/پرداخت) نامعتبر است.'; }
        if ($payingContactId === null && empty($payingDetails)) { $errors[] = 'پرداخت کننده باید مشخص شود.'; }
        if ($receivingContactId === null && empty($receivingDetails)) { $errors[] = 'دریافت کننده باید مشخص شود.'; }
        if ($sourceBankAccountId !== null && $destinationBankAccountId !== null) { $errors[] = 'فقط یکی از حساب مبدا یا مقصد قابل انتخاب است.'; }
        // if ($sourceBankAccountId !== null && $direction !== 'outflow') { $errors[] = 'برای پرداخت از حساب، جهت باید "پرداخت" باشد.'; }
        // if ($destinationBankAccountId !== null && $direction !== 'inflow') { $errors[] = 'برای واریز به حساب، جهت باید "دریافت" باشد.'; }
        // Direction is now automatically set if bank account is chosen

        // Validate Payment Method
        if (empty($paymentMethod)) {
            $errors[] = 'روش پرداخت الزامی است.';
        } elseif (!array_key_exists($paymentMethod, self::VALID_PAYMENT_METHODS)) {
            $errors[] = 'روش پرداخت انتخاب شده نامعتبر است.';
        }
        // TODO: Add specific validation based on $paymentMethod (e.g., require cheque details if method is 'cheque')
        // Example:
        /*
        if ($paymentMethod === 'cheque') {
            if (empty($methodDetails['method_details_cheque_sayad_id'])) { $errors[] = 'شماره صیاد چک الزامی است.'; }
            if (empty($methodDetails['method_details_cheque_due_date'])) { $errors[] = 'تاریخ سررسید چک الزامی است.'; }
            // ... other cheque validations
        } elseif ($paymentMethod === 'bank_slip') {
            if (empty($methodDetails['method_details_slip_number'])) { $errors[] = 'شماره فیش بانکی الزامی است.'; }
            // ...
        }
        */

        // Validate specific date formats if provided
        $sqlSlipDate = null;
        if (!empty($methodDetails['method_details_slip_date'])) {
            $sqlSlipDate = Helper::parseJalaliDateToSql($methodDetails['method_details_slip_date']);
            if (!$sqlSlipDate) $errors[] = 'فرمت تاریخ فیش بانکی نامعتبر است (مثال: 1403/02/01).';
        }
        $sqlTransferDate = null;
        if (!empty($methodDetails['method_details_transfer_date'])) {
            $sqlTransferDate = Helper::parseJalaliDateToSql($methodDetails['method_details_transfer_date']);
            if (!$sqlTransferDate) $errors[] = 'فرمت تاریخ انتقال/واریز نامعتبر است (مثال: 1403/02/01).';
        }
        $sqlChequeDueDate = null;
        if (!empty($methodDetails['method_details_cheque_due_date'])) {
            $sqlChequeDueDate = Helper::parseJalaliDateToSql($methodDetails['method_details_cheque_due_date']);
            if (!$sqlChequeDueDate) $errors[] = 'فرمت تاریخ سررسید چک نامعتبر است (مثال: 1403/02/01).';
        }

        // --- Handle Validation Failure ---
        if (!empty($errors)) {
            $this->logger->warning("Payment validation failed.", ['errors' => $errors, 'payment_id' => $paymentId]);
            $this->setSessionMessage(implode('<br>', $errors), 'danger', 'form_error');
            $_SESSION[$sessionFormDataKey] = $_POST;
            $this->redirect($redirectUrlOnError);
        }

        // --- Database Operations (Transaction) ---
        try {
            $this->db->beginTransaction();
            $this->logger->debug("Starting payment save transaction.", ['payment_id' => $paymentId]);

            $bankAccountIdToUpdate = $sourceBankAccountId ?? $destinationBankAccountId;
            $oldBankTx = null;

            // 1. (Edit only) Revert old bank transaction effect if bank account changed or payment deleted/changed direction
            if ($isEditMode) {
                $oldBankTx = $this->paymentRepository->findRelatedBankTransaction($paymentId);
                if ($oldBankTx) {
                     $oldBankAccountId = (int)$oldBankTx['bank_account_id'];
                     $needsRevert = false;
                     // Check if bank account changed OR if it was linked and now isn't OR direction mismatch
                     if ($bankAccountIdToUpdate !== $oldBankAccountId || $bankAccountIdToUpdate === null) {
                          $needsRevert = true;
                     } elseif (($direction === 'inflow' && (float)$oldBankTx['amount'] < 0) || ($direction === 'outflow' && (float)$oldBankTx['amount'] > 0)) {
                          $needsRevert = true; // Direction changed relative to bank transaction type
                     }

                     if ($needsRevert) {
                           $this->logger->debug("Reverting old bank transaction effect.", ['old_bank_tx_id' => $oldBankTx['id'], 'old_account_id' => $oldBankAccountId]);
                           $revertAmount = - (float) $oldBankTx['amount'];
                           // Assume repo method exists and throws on error
                           $this->bankAccountRepository->updateCurrentBalance($oldBankAccountId, $revertAmount);
                           // Assume repo method exists and throws on error
                           $this->paymentRepository->deleteBankTransaction($oldBankTx['id']);
                           $oldBankTx = null; // Mark as handled/deleted
                     }
                }
            }

            // 2. Prepare and Save Payment Record
            $paymentData = [
                'id' => $isEditMode ? $paymentId : null,
                'payment_date' => $paymentDateSql, 'amount_rials' => $amountRials,
                'direction' => $direction,
                'payment_method' => $paymentMethod, // Add payment method
                'paying_contact_id' => $payingContactId, 'paying_details' => $payingDetails ?: null,
                'receiving_contact_id' => $receivingContactId, 'receiving_details' => $receivingDetails ?: null,
                'related_transaction_id' => $relatedTransactionId, 'notes' => $notes ?: null,
            ];
            // Add method details to the data array, converting dates to SQL format
            $methodDetails['method_details_slip_date'] = $sqlSlipDate;
            $methodDetails['method_details_transfer_date'] = $sqlTransferDate;
            $methodDetails['method_details_cheque_due_date'] = $sqlChequeDueDate;
            $paymentData = array_merge($paymentData, $methodDetails);

            $currentPaymentId = $this->paymentRepository->save($paymentData); // Assume repo handles INSERT/UPDATE & returns ID

            // 3. Create/Update new bank transaction if needed
            if ($bankAccountIdToUpdate !== null) {
                 $bankAmountChange = ($direction === 'inflow') ? +$amountRials : -$amountRials;
                 $bankTxType = ($direction === 'inflow') ? 'deposit' : 'withdrawal';
                 $bankTxDescription = "پیوست پرداخت/دریافت #" . $currentPaymentId . ($notes ? ' - ' . mb_substr($notes, 0, 100, 'UTF-8') : '');

                 // If editing and the old bank transaction still matches account/direction, update it. Otherwise, insert new.
                 if ($oldBankTx && (int)$oldBankTx['bank_account_id'] === $bankAccountIdToUpdate) {
                     // Update existing bank transaction amount/date/desc
                      $this->logger->debug("Updating existing bank transaction.", ['bank_tx_id' => $oldBankTx['id']]);
                      // Assume repo method exists
                       $bankTxData = [ 'id' => $oldBankTx['id'], 'amount' => $bankAmountChange, 'transaction_date' => $paymentDateSql, 'description' => $bankTxDescription ];
                       $this->paymentRepository->updateBankTransaction($bankTxData);
                       // Recalculate balance change (new amount - old amount)
                        $balanceAdjustment = $bankAmountChange - (float)$oldBankTx['amount'];
                 } else {
                     // Insert new bank transaction
                      $this->logger->debug("Inserting new bank transaction.", ['account_id' => $bankAccountIdToUpdate]);
                      // Assume repo method exists
                       $bankTxData = [
                           'bank_account_id' => $bankAccountIdToUpdate,
                           'transaction_date' => $paymentDateSql,
                           'amount' => $bankAmountChange,
                           'type' => $bankTxType,
                           'description' => $bankTxDescription,
                           'related_payment_id' => $currentPaymentId
                       ];
                       $this->paymentRepository->saveBankTransaction($bankTxData);
                       $balanceAdjustment = $bankAmountChange; // Change is the full amount
                 }

                 // Update bank account balance using the adjustment amount
                 // Assume repo method exists and throws on error
                 $this->bankAccountRepository->updateCurrentBalance($bankAccountIdToUpdate, $balanceAdjustment);
            }

            // 4. Commit
            $this->db->commit();
            $this->logger->info("Payment save transaction committed.", ['payment_id' => $currentPaymentId]);

            // 5. Redirect with success
            $actionWord = $isEditMode ? 'به‌روزرسانی' : 'ثبت';
            Helper::logActivity($this->db, "Payment {$actionWord}d.", 'SUCCESS', ['payment_id' => $currentPaymentId]);
            $this->setSessionMessage("پرداخت/دریافت با موفقیت {$actionWord} شد.", 'success', 'payment_success');
            $this->redirect('/app/payments');

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            $this->logger->error("Error saving payment.", ['payment_id' => $paymentId, 'exception' => $e]);
            $errorMessage = "خطا در ذخیره پرداخت/دریافت.";
            if ($this->config['app']['debug']) { $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
            $this->setSessionMessage($errorMessage, 'danger', 'form_error');
            $_SESSION[$sessionFormDataKey] = $_POST; // Repopulate
            $this->redirect($redirectUrlOnError);
        }
    }

    /**
     * Processes the delete request for a payment/receipt.
     * Reverts associated bank transaction effect.
     * Route: /app/payments/delete/{id} (POST)
     *
     * @param int $paymentId The ID of the payment to delete.
     */
    public function delete(int $paymentId): void {
        $this->requireLogin();
        // Optional: Permission check

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/payments'); }
        // TODO: Add CSRF token validation

        if ($paymentId <= 0) {
            $this->setSessionMessage('شناسه نامعتبر است.', 'danger', 'payment_error');
            $this->redirect('/app/payments');
        }
        $this->logger->info("Attempting delete.", ['payment_id' => $paymentId]);

        try {
            $this->db->beginTransaction();

            // 1. Find and revert associated bank transaction
            $relatedBankTx = $this->paymentRepository->findRelatedBankTransaction($paymentId);
            if ($relatedBankTx) {
                $bankAccountId = (int)$relatedBankTx['bank_account_id'];
                $amountToRevert = - (float) $relatedBankTx['amount'];
                 $this->logger->debug("Reverting bank transaction effect.", ['bank_tx_id' => $relatedBankTx['id'], 'account_id' => $bankAccountId, 'revert_amount' => $amountToRevert]);
                // Assume repo method throws on error
                $this->bankAccountRepository->updateCurrentBalance($bankAccountId, $amountToRevert);
                // Assume repo method throws on error
                $this->paymentRepository->deleteBankTransaction($relatedBankTx['id']);
            }

            // 2. Delete the main payment record
            // Assume repo method returns true/false or throws exception
            $isDeleted = $this->paymentRepository->delete($paymentId);

            if ($isDeleted) {
                $this->db->commit();
                $this->logger->info("Payment record deleted.", ['payment_id' => $paymentId]);
                Helper::logActivity($this->db, "Payment deleted.", 'SUCCESS', ['payment_id' => $paymentId]);
                $message = 'پرداخت/دریافت حذف شد.';
                if ($relatedBankTx) { $message .= ' اثر بانکی آن نیز خنثی شد.'; }
                $this->setSessionMessage($message, 'success', 'payment_success');
            } else {
                $this->db->rollBack(); // Rollback if payment delete failed (e.g., not found)
                $this->logger->warning("Payment delete failed (not found?).", ['payment_id' => $paymentId]);
                $this->setSessionMessage('پرداخت/دریافت یافت نشد یا حذف نشد.', 'warning', 'payment_error');
            }
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            $this->logger->error("Error deleting payment.", ['payment_id' => $paymentId, 'exception' => $e]);
            $errorMessage = "خطا در حذف پرداخت/دریافت.";
            if ($this->config['app']['debug']) { $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
            $this->setSessionMessage($errorMessage, 'danger', 'payment_error');
        }
        $this->redirect('/app/payments');
    }

} // End PaymentController class
