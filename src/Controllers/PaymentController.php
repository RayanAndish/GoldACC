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
use App\core\CSRFProtector; // Added for CSRF protection

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
        'cheque' => 'چک',
        'pos' => 'کارتخوان',
        'online' => 'آنلاین',
        'other' => 'سایر'
    ];

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

        // Retrieve specific repositories/services
        if (!isset($this->services['paymentRepository']) || !$this->services['paymentRepository'] instanceof PaymentRepository) {
            throw new Exception('PaymentRepository not found for PaymentController.');
        }
        $this->paymentRepository = $this->services['paymentRepository'];

        if (!isset($this->services['contactRepository']) || !$this->services['contactRepository'] instanceof ContactRepository) {
            throw new Exception('ContactRepository not found for PaymentController.');
        }
        $this->contactRepository = $this->services['contactRepository'];

        if (!isset($this->services['bankAccountRepository']) || !$this->services['bankAccountRepository'] instanceof BankAccountRepository) {
            throw new Exception('BankAccountRepository not found for PaymentController.');
        }
        $this->bankAccountRepository = $this->services['bankAccountRepository'];

        // TransactionRepository is optional for payments
        if (isset($this->services['transactionRepository']) && $this->services['transactionRepository'] instanceof TransactionRepository) {
            $this->transactionRepository = $this->services['transactionRepository'];
        }

        $this->logger->debug("PaymentController initialized.");
    }

    /**
     * Displays a paginated list of payments.
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

        // --- Filtering, Searching, Pagination ---
        $itemsPerPage = (int)($this->config['app']['items_per_page'] ?? 15);
        $currentPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $searchTerm = trim(filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? '');
        $filters = [ // Read filters from GET
            'direction' => filter_input(INPUT_GET, 'direction', FILTER_DEFAULT), // 'inflow' or 'outflow'
            'contact_id' => filter_input(INPUT_GET, 'contact', FILTER_VALIDATE_INT),
            'method' => filter_input(INPUT_GET, 'method', FILTER_DEFAULT),
            'start_date_jalali' => trim(filter_input(INPUT_GET, 'start_date', FILTER_DEFAULT) ?? ''),
            'end_date_jalali' => trim(filter_input(INPUT_GET, 'end_date', FILTER_DEFAULT) ?? ''),
        ];

        // Convert Jalali dates to SQL format for repository
        $filters['start_date_sql'] = Helper::parseJalaliDateToSql($filters['start_date_jalali']);
        $filters['end_date_sql'] = Helper::parseJalaliDateToSql($filters['end_date_jalali'], true); // true for end of day

        // Clean up null/empty filters before passing to repository
        $activeFilters = array_filter($filters, function ($value) {
            return $value !== false && $value !== '' && $value !== null;
        });

        try {
            // FIX: تغییر ترتیب آرگومان‌ها در فراخوانی countFiltered
            $totalRecords = $this->paymentRepository->countFiltered($searchTerm, $activeFilters);
            $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $itemsPerPage) : 1;
            $currentPage = max(1, min($currentPage, $totalPages));
            $offset = ($currentPage - 1) * $itemsPerPage;

            // FIX: تغییر ترتیب آرگومان‌ها در فراخوانی getFilteredAndPaginated
            $payments = $this->paymentRepository->getFilteredAndPaginated($searchTerm, $activeFilters, $itemsPerPage, $offset);

            // Prepare data for display (e.g., format dates, amounts)
            foreach ($payments as &$payment) {
                $payment['amount_formatted'] = Helper::formatRial($payment['amount_rials']);
                $payment['payment_date_persian'] = Jalalian::fromDateTime(new \DateTime($payment['payment_date']))->format('Y/m/d H:i');
                $payment['method_farsi'] = self::VALID_PAYMENT_METHODS[$payment['payment_method']] ?? $payment['payment_method'];
                $payment['direction_farsi'] = ($payment['direction'] === 'inflow') ? 'دریافت' : 'پرداخت';
                $payment['direction_class'] = ($payment['direction'] === 'inflow') ? 'text-success' : 'text-danger';
                $payment['notes_short'] = mb_substr($payment['notes'] ?? '', 0, 50, 'UTF-8') . (mb_strlen($payment['notes'] ?? '') > 50 ? '...' : '');
                $payment['notes_tooltip'] = Helper::escapeHtml($payment['notes'] ?? '');
            }
            unset($payment); // Unset reference

            $paginationData = Helper::generatePaginationData($currentPage, $totalPages, $totalRecords, $itemsPerPage);
            $this->logger->debug("Payments list fetched.", ['count' => count($payments), 'total' => $totalRecords]);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching payments list.", ['exception' => $e]);
            $errorMessage = $errorMessage ?: ['text' => "خطا در بارگذاری لیست پرداخت‌ها/دریافت‌ها."];
            if ($this->config['app']['debug']) {
                 $errorMessage['text'] .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
            }
            $payments = []; // Ensure payments array is empty on error
            $paginationData = Helper::generatePaginationData(1, 1, 0, $itemsPerPage); // Default pagination
        }

        // Fetch contacts for filter dropdown
        $contactsForFilter = [];
        try {
            $contactsForFilter = $this->contactRepository->getAll();
        } catch (Throwable $e) {
            $this->logger->error("Failed getting contacts for filter dropdown.", ['exception' => $e]);
        }

        $this->render('payments/list', [
            'page_title'     => $pageTitle,
            'payments'       => $payments,
            'error_msg'      => $errorMessage ? $errorMessage['text'] : null,
            'success_msg'    => $successMessage ? $successMessage['text'] : null,
            'search_term'    => Helper::escapeHtml($searchTerm),
            'filters'        => $filters, // Pass original filters (with Jalali dates) back to view
            'contacts_for_filter' => $contactsForFilter,
            'payment_methods' => self::VALID_PAYMENT_METHODS,
            'pagination'     => $paginationData,
            'csrf_token'     => Helper::generateCsrfToken()
        ]);
    }

    /**
     * Displays the form for adding a new payment or editing an existing one.
     * Route: /app/payments/add (GET)
     * Route: /app/payments/edit/{id} (GET)
     */
    public function showForm(?int $paymentId = null): void {
        $this->requireLogin();
        // Optional: Permission check

        $isEditMode = ($paymentId !== null);
        $pageTitle = $isEditMode ? "ویرایش پرداخت/دریافت" : "ثبت پرداخت/دریافت جدید";
        $paymentData = [];
        $loadingError = null;
        $flashFormData = null;

        // Check for old form data from session (after validation error)
        $sessionKey = $isEditMode ? 'payment_edit_data_' . $paymentId : 'payment_add_data';
        if (isset($_SESSION[$sessionKey])) {
            $flashFormData = $_SESSION[$sessionKey];
            unset($_SESSION[$sessionKey]);
            $this->logger->debug("Loading old form data from session.", ['session_key' => $sessionKey]);
        }

        try {
            // Fetch payment data if in edit mode
            if ($isEditMode) {
                $paymentData = $this->paymentRepository->getById($paymentId);
                if (!$paymentData) {
                    $this->setSessionMessage('پرداخت/دریافت مورد نظر یافت نشد.', 'danger');
                    $this->redirect('/app/payments');
                    return;
                }
                $this->logger->debug("Loaded payment data for edit.", ['payment_id' => $paymentId]);
            }

            // Merge flash data over fetched data for repopulation
            if ($flashFormData) {
                $paymentData = array_merge($paymentData, $flashFormData);
                $this->logger->debug("Merged flash data for repopulation.", ['payment_data' => $paymentData]);
            }

            // Fetch dropdown data
            $contacts = $this->contactRepository->getAll();
            $bankAccounts = $this->bankAccountRepository->getAll();
            $transactions = []; // Optional: if payments can be linked to gold transactions
            if ($this->transactionRepository) {
                // Example: Fetch only pending transactions for linking
                // $transactions = $this->transactionRepository->getPendingTransactions();
            }

            // Format dates for display in form fields
            if (isset($paymentData['payment_date']) && !empty($paymentData['payment_date'])) {
                try {
                    $dt = new \DateTime($paymentData['payment_date']);
                    $paymentData['payment_date_jalali'] = Jalalian::fromDateTime($dt)->format('Y/m/d H:i:s');
                } catch (\Exception $e) {
                    $this->logger->warning("Failed to format payment_date to Jalali.", ['date' => $paymentData['payment_date'], 'exception' => $e->getMessage()]);
                    $paymentData['payment_date_jalali'] = '';
                }
            } else {
                $paymentData['payment_date_jalali'] = Jalalian::now()->format('Y/m/d H:i:s'); // Default for new payments
            }

            // Format amounts for display in form fields (AutoNumeric will handle client-side)
            $paymentData['amount_rials'] = Helper::formatNumber($paymentData['amount_rials'] ?? 0, 0, '.', ''); // Ensure raw number for autonumeric
            $paymentData['method_details_amount'] = Helper::formatNumber($paymentData['method_details_amount'] ?? 0, 0, '.', '');

        } catch (Throwable $e) {
            $this->logger->error("Error loading payment form data.", ['payment_id' => $paymentId, 'exception' => $e]);
            $loadingError = "خطا در بارگذاری اطلاعات فرم. " . ($this->config['app']['debug'] ? Helper::escapeHtml($e->getMessage()) : '');
            $paymentData = []; // Clear data on error
            $contacts = [];
            $bankAccounts = [];
            $transactions = [];
        }

        $this->render('payments/form', [
            'page_title'        => $pageTitle,
            'is_edit_mode'      => $isEditMode,
            'form_action'       => $this->config['app']['base_url'] . '/app/payments/save' . ($isEditMode ? '/' . $paymentId : ''),
            'payment_data'      => $paymentData,
            'contacts'          => $contacts,
            'bank_accounts'     => $bankAccounts,
            'transactions'      => $transactions, // Pass if used for linking
            'payment_methods'   => self::VALID_PAYMENT_METHODS,
            'error_msg'         => $this->getFlashMessage('form_error') ? $this->getFlashMessage('form_error')['text'] : $loadingError,
            'csrf_token'        => Helper::generateCsrfToken(),
            'baseUrl'           => $this->config['app']['base_url']
        ]);
    }

    /**
     * Handles saving (add/edit) a payment.
     * Route: /app/payments/save (POST)
     * Route: /app/payments/save/{id} (POST)
     */
    public function save(?int $paymentId = null): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setSessionMessage('متد درخواست نامعتبر است.', 'danger');
            $this->redirect('/app/payments');
            return;
        }

        // --- CSRF Token Validation ---
        if (!Helper::verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $this->setSessionMessage(Helper::getMessageText('csrf_token_invalid'), 'danger', 'form_error');
            $this->redirect('/app/payments/add'); // Or redirect to edit if $paymentId is set
            return;
        }

        $isEditMode = ($paymentId !== null);
        $postData = $_POST;
        $sessionKey = $isEditMode ? 'payment_edit_data_' . $paymentId : 'payment_add_data';

        try {
            $this->db->beginTransaction();

            // 1. Validate and prepare data
            $paymentData = $this->validateAndPreparePaymentData($postData);
            if ($isEditMode) $paymentData['id'] = $paymentId;
            $paymentData['created_by_user_id'] = $_SESSION['user_id'] ?? null; // Ensure user ID is set

            // If editing, reverse previous bank transaction effect if linked
            $oldPayment = null;
            $relatedBankTxId = null;
            if ($isEditMode) {
                $oldPayment = $this->paymentRepository->getById($paymentId);
                if ($oldPayment && !empty($oldPayment['bank_transaction_id'])) {
                    $relatedBankTxId = $oldPayment['bank_transaction_id'];
                    $this->bankAccountRepository->updateCurrentBalance(
                        $oldPayment['bank_account_id'],
                        -($oldPayment['amount_rials']), // Reverse the old amount
                        true // Indicate reversal
                    );
                    $this->logger->info("Reversed old bank transaction effect for payment ID {$paymentId}.", ['old_amount' => $oldPayment['amount_rials']]);
                }
            }

            // 2. Save payment record
            $finalPaymentId = $this->paymentRepository->save($paymentData);
            if (!$finalPaymentId) throw new Exception('خطا در ذخیره رکورد پرداخت/دریافت.');

            // 3. Handle related bank transaction
            $bankAccountId = filter_var($postData['bank_account_id'] ?? null, FILTER_VALIDATE_INT);
            if ($bankAccountId > 0) {
                $amountChange = ($paymentData['direction'] === 'inflow') ? $paymentData['amount_rials'] : -$paymentData['amount_rials'];

                $bankTxData = [
                    'bank_account_id' => $bankAccountId,
                    'payment_id' => $finalPaymentId,
                    'transaction_type' => $paymentData['direction'], // inflow/outflow
                    'amount' => $paymentData['amount_rials'],
                    'balance_change' => $amountChange,
                    'transaction_date' => $paymentData['payment_date'],
                    'description' => "پرداخت/دریافت شماره {$finalPaymentId} - " . ($paymentData['notes'] ?? ''),
                    'related_payment_id' => $finalPaymentId, // Link back to payment
                    'created_by_user_id' => $_SESSION['user_id'] ?? null
                ];

                // If updating an existing bank transaction
                if ($relatedBankTxId) {
                    $bankTxData['id'] = $relatedBankTxId;
                }

                $savedBankTxId = $this->bankAccountRepository->saveBankTransaction($bankTxData);
                if (!$savedBankTxId) throw new Exception('خطا در ذخیره تراکنش بانکی مرتبط.');

                // Update payment record with bank_transaction_id
                $this->paymentRepository->update($finalPaymentId, ['bank_transaction_id' => $savedBankTxId]);

                // Update bank account current balance
                $this->bankAccountRepository->updateCurrentBalance($bankAccountId, $amountChange);
                $this->logger->info("Bank account balance updated for payment ID {$finalPaymentId}.", ['account_id' => $bankAccountId, 'change' => $amountChange]);
            }

            $this->db->commit();
            $actionWord = $isEditMode ? 'ویرایش' : 'ثبت';
            $this->setSessionMessage("پرداخت/دریافت با موفقیت {$actionWord} شد.", 'success', 'payment_success');
            
            // FIX: Corrected logActivity call
            Helper::logActivity($this->db, "Payment {$actionWord}ed: ID {$finalPaymentId}", 'PAYMENT_SAVE', 'INFO', ['payment_id' => $finalPaymentId]);

            $this->redirect('/app/payments');

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logger->error("Error saving payment.", ['exception' => $e]);
            $this->setSessionMessage('خطا در ذخیره پرداخت/دریافت: ' . $e->getMessage(), 'danger', 'form_error');
            $_SESSION[$sessionKey] = $postData; // Repopulate form
            $this->redirect($isEditMode ? '/app/payments/edit/' . $paymentId : '/app/payments/add');
        }
    }

    /**
     * Validates and sanitizes payment data from POST request.
     * @param array $postData
     * @return array Cleaned and validated data.
     * @throws Exception On validation failure.
     */
    private function validateAndPreparePaymentData(array $postData): array {
        $data = [];

        $data['payment_date'] = Helper::parseJalaliDatetimeToSql($postData['payment_date'] ?? '');
        if (empty($data['payment_date'])) throw new Exception('تاریخ پرداخت/دریافت نامعتبر است.');

        $data['amount_rials'] = (float)Helper::sanitizeFormattedNumber($postData['amount_rials'] ?? '0');
        if ($data['amount_rials'] <= 0) throw new Exception('مبلغ پرداخت/دریافت باید عدد مثبت باشد.');

        $data['direction'] = $postData['direction'] ?? null;
        if (!in_array($data['direction'], ['inflow', 'outflow'])) throw new Exception('جهت پرداخت/دریافت نامعتبر است.');

        $data['paying_contact_id'] = filter_var($postData['paying_contact_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $data['receiving_contact_id'] = filter_var($postData['receiving_contact_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

        if (empty($data['paying_contact_id']) && empty($data['receiving_contact_id'])) {
            throw new Exception('طرف پرداخت‌کننده یا دریافت‌کننده باید مشخص شود.');
        }

        $data['payment_method'] = $postData['payment_method'] ?? null;
        if (!in_array($data['payment_method'], array_keys(self::VALID_PAYMENT_METHODS))) {
            throw new Exception('روش پرداخت/دریافت نامعتبر است.');
        }

        $data['bank_account_id'] = filter_var($postData['bank_account_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $data['related_transaction_id'] = filter_var($postData['related_transaction_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

        $data['notes'] = trim($postData['notes'] ?? '');

        // Handle method-specific details
        $data['method_details_slip_number'] = null;
        $data['method_details_cheque_number'] = null;
        $data['method_details_cheque_sayad_id'] = null;
        $data['method_details_card_number'] = null;
        $data['method_details_amount'] = null; // for Pos/Online

        switch ($data['payment_method']) {
            case 'bank_slip':
                $data['method_details_slip_number'] = trim($postData['method_details_slip_number'] ?? '');
                if (empty($data['method_details_slip_number'])) throw new Exception('شماره فیش بانکی الزامی است.');
                break;
            case 'cheque':
                $data['method_details_cheque_number'] = trim($postData['method_details_cheque_number'] ?? '');
                $data['method_details_cheque_sayad_id'] = trim($postData['method_details_cheque_sayad_id'] ?? '');
                if (empty($data['method_details_cheque_number']) || empty($data['method_details_cheque_sayad_id'])) {
                    throw new Exception('شماره چک و شناسه صیادی الزامی است.');
                }
                break;
            case 'pos':
            case 'online':
                $data['method_details_card_number'] = trim($postData['method_details_card_number'] ?? '');
                $data['method_details_amount'] = (float)Helper::sanitizeFormattedNumber($postData['method_details_amount'] ?? '0');
                if (empty($data['method_details_card_number']) || $data['method_details_amount'] <= 0) {
                    throw new Exception('شماره کارت و مبلغ جزئیات روش پرداخت الزامی است.');
                }
                break;
            // 'cash' and 'barter' have no specific details
        }

        return $data;
    }

    /**
     * Handles deleting a payment.
     * Route: /app/payments/delete/{id} (POST)
     */
    public function delete(int $paymentId): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Helper::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
             $this->setSessionMessage('درخواست نامعتبر است.', 'danger');
             $this->redirect('/app/payments');
             return;
        }
        try {
            $this->db->beginTransaction();

            // Get payment details to reverse bank transaction if exists
            $payment = $this->paymentRepository->getById($paymentId);
            $relatedBankTx = null;
            if ($payment && !empty($payment['bank_transaction_id'])) {
                $relatedBankTx = $this->bankAccountRepository->getBankTransactionById($payment['bank_transaction_id']);
                if ($relatedBankTx) {
                    // Reverse the effect on bank account balance
                    $this->bankAccountRepository->updateCurrentBalance(
                        $relatedBankTx['bank_account_id'],
                        -($relatedBankTx['balance_change']), // Reverse the change
                        true // Indicate reversal
                    );
                    // Delete the bank transaction record
                    $this->bankAccountRepository->deleteBankTransaction($relatedBankTx['id']);
                    $this->logger->info("Reversed and deleted related bank transaction for payment ID {$paymentId}.", ['bank_tx_id' => $relatedBankTx['id']]);
                }
            }

            $isDeleted = $this->paymentRepository->delete($paymentId);

            if ($isDeleted) {
                $this->db->commit();
                $this->logger->info("Payment record deleted.", ['payment_id' => $paymentId]);
                // FIX: Corrected logActivity call
                Helper::logActivity($this->db, "Payment deleted.", 'PAYMENT_DELETE', 'INFO', ['payment_id' => $paymentId]);
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
}

