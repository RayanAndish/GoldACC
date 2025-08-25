<?php

namespace App\Controllers;

use PDO;
use Monolog\Logger;
use Throwable;
use Exception;
use Morilog\Jalali\Jalalian;
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;
use App\Repositories\PaymentRepository;
use App\Repositories\ContactRepository;
use App\Repositories\BankAccountRepository;
use App\Repositories\TransactionRepository;
use App\Utils\Helper;

class PaymentController extends AbstractController {

    private PaymentRepository $paymentRepository;
    private ContactRepository $contactRepository;
    private BankAccountRepository $bankAccountRepository;
    private ?TransactionRepository $transactionRepository;

    private const array VALID_PAYMENT_METHODS = [
        'cash' => 'نقدی', 'cheque' => 'چک', 'bank_slip' => 'فیش بانکی', 'pos' => 'کارتخوان (POS)',
        'atm' => 'کارتخوان (ATM)', 'mobile_transfer' => 'همراه بانک', 'internet_transfer' => 'اینترنت بانک',
        'barter' => 'تهاتر', 'clearing_account' => 'حساب واسط',
    ];

    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        $this->paymentRepository = $services['paymentRepository'];
        $this->contactRepository = $services['contactRepository'];
        $this->bankAccountRepository = $services['bankAccountRepository'];
        $this->transactionRepository = $services['transactionRepository'] ?? null;
        $this->logger->debug("PaymentController initialized.");
    }

    public function index(): void {
        $this->requireLogin();
        $pageTitle = "مدیریت پرداخت‌ها و دریافت‌ها";
        $errorMessage = $this->getFlashMessage('payment_error');
        $successMessage = $this->getFlashMessage('payment_success');

        try {
            $itemsPerPage = (int)($this->config['app']['items_per_page'] ?? 15);
            $currentPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
            $searchTerm = trim(filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? '');
            
            $totalRecords = $this->paymentRepository->countFiltered($searchTerm);
            
            // FIX: Calculate offset directly in the controller before passing it.
            $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $itemsPerPage) : 1;
            $currentPage = max(1, min($currentPage, $totalPages)); // Ensure current page is valid
            $offset = ($currentPage - 1) * $itemsPerPage;

            $paginationData = Helper::generatePaginationData($currentPage, $totalPages, $totalRecords, $itemsPerPage);
            
            // Pass the calculated offset to the repository method.
            $payments = $this->paymentRepository->getFilteredAndPaginated($searchTerm, $itemsPerPage, $offset);

            foreach ($payments as &$payment) {
                 $payment['amount_rials_formatted'] = Helper::formatRial($payment['amount_rials'] ?? 0, false);
                 $payment['direction_farsi'] = match($payment['direction'] ?? '') { 'inflow' => 'دریافت', 'outflow' => 'پرداخت', default => 'نامشخص' };
                 $payment['payment_date_persian'] = !empty($payment['payment_date']) ? Jalalian::fromDateTime(new \DateTime($payment['payment_date']))->format('Y/m/d H:i') : '-';
            }
            unset($payment);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching payments list.", ['exception' => $e]);
            $errorMessage = $errorMessage ?: ['text' => "خطا در بارگذاری لیست."];
            $payments = [];
            $paginationData = Helper::generatePaginationData(1, 1, 0, 15);
        }

        $this->render('payments/list', [
            'page_title' => $pageTitle, 'payments' => $payments,
            'error_msg' => $errorMessage['text'] ?? null, 'success_msg' => $successMessage['text'] ?? null,
            'search_term' => Helper::escapeHtml($searchTerm), 'pagination' => $paginationData,
        ]);
    }

    public function showAddForm(): void {
        $this->showForm(null);
    }

    public function showEditForm(int $paymentId): void {
        $this->showForm($paymentId);
    }

    private function showForm(?int $paymentId = null): void {
        $this->requireLogin();
        $isEditMode = ($paymentId !== null);
        $pageTitle = $isEditMode ? "ویرایش پرداخت/دریافت" : "ثبت پرداخت/دریافت جدید";
        $paymentData = [];
        $loadingError = null;

        $sessionKey = $isEditMode ? 'payment_edit_data_' . $paymentId : 'payment_add_data';
        $flashFormData = $_SESSION[$sessionKey] ?? null;
        if ($flashFormData) unset($_SESSION[$sessionKey]);

        try {
            if ($isEditMode) {
                $paymentData = $this->paymentRepository->getById($paymentId);
                if (!$paymentData) {
                    $this->setSessionMessage('رکورد مورد نظر یافت نشد.', 'danger', 'payment_error');
                    $this->redirect('/app/payments');
                    return;
                }
                $relatedBankTx = $this->paymentRepository->findRelatedBankTransaction($paymentId);
                if ($relatedBankTx) {
                    if ((float)$relatedBankTx['amount'] < 0) $paymentData['source_bank_account_id'] = $relatedBankTx['bank_account_id'];
                    else $paymentData['destination_bank_account_id'] = $relatedBankTx['bank_account_id'];
                }
            }

            if ($flashFormData) $paymentData = array_merge($paymentData, $flashFormData);

            if (empty($paymentData['payment_date_persian'])) {
                $paymentData['payment_date_persian'] = !empty($paymentData['payment_date'])
                    ? Jalalian::fromDateTime(new \DateTime($paymentData['payment_date']))->format('Y/m/d H:i:s')
                    : Jalalian::now()->format('Y/m/d H:i:s');
            }

            $contacts = $this->contactRepository->getAll();
            $bankAccounts = $this->bankAccountRepository->getAll();
            $transactions = $this->transactionRepository ? $this->transactionRepository->getLatestTransactions(null, 20) : [];

        } catch (Throwable $e) {
            $this->logger->error("Error loading payment form.", ['id' => $paymentId, 'exception' => $e]);
            $loadingError = "خطا در بارگذاری اطلاعات فرم.";
            $paymentData = $flashFormData ?? [];
            $contacts = []; $bankAccounts = []; $transactions = [];
        }

        $formError = $this->getFlashMessage('form_error');

        $this->render('payments/form', [
            'page_title' => $pageTitle, 'is_edit_mode' => $isEditMode,
            'form_action' => $this->config['app']['base_url'] . '/app/payments/save',
            'payment' => $paymentData, 'contacts' => $contacts, 'bank_accounts' => $bankAccounts,
            'transactions' => $transactions, 'payment_methods' => self::VALID_PAYMENT_METHODS,
            'error_message' => $formError['text'] ?? null, 'loading_error' => $loadingError,
            'submit_button_text' => $isEditMode ? 'به‌روزرسانی' : 'ثبت',
        ]);
    }

    public function save(): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/payments');
            return;
        }

        $paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
        $isEditMode = ($paymentId !== null && $paymentId > 0);
        $postData = $_POST;
        
        $redirectUrlOnError = $this->config['app']['base_url'] . '/app/payments/' . ($isEditMode ? 'edit/' . $paymentId : 'add');
        $sessionKey = $isEditMode ? 'payment_edit_data_' . $paymentId : 'payment_add_data';

        try {
            if (!Helper::verifyCsrfToken($postData['csrf_token'] ?? null)) {
                throw new Exception('درخواست نامعتبر است (CSRF).');
            }

            $this->db->beginTransaction();
            
            $paymentData = $this->validateAndPrepareData($postData);
            if ($isEditMode) $paymentData['id'] = $paymentId;
            
            $finalPaymentId = $this->paymentRepository->save($paymentData);

            $this->handleBankTransaction($postData, $finalPaymentId, $paymentData, $isEditMode);

            $this->db->commit();
            $actionWord = $isEditMode ? 'به‌روزرسانی' : 'ثبت';
            $this->setSessionMessage("پرداخت/دریافت با موفقیت {$actionWord} شد.", 'success', 'payment_success');
            $this->redirect('/app/payments');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->logger->warning("Payment save failed.", ['id' => $paymentId, 'error' => $e->getMessage()]);
            
            $this->setSessionMessage($e->getMessage(), 'danger', 'form_error');
            $_SESSION[$sessionKey] = $postData;
            
            $this->redirect($redirectUrlOnError);
        }
    }

    private function validateAndPrepareData(array $postData): array {
        $data = [];
        
        $data['direction'] = $postData['direction'] ?? '';
        if (!in_array($data['direction'], ['inflow', 'outflow'])) throw new Exception('جهت تراکنش نامعتبر است.');
        
        $data['payment_date'] = Helper::parseJalaliDatetimeToSql($postData['payment_date'] ?? '');
        if (!$data['payment_date']) throw new Exception('تاریخ و زمان الزامی و معتبر است.');

        $data['amount_rials'] = Helper::sanitizeFormattedNumber($postData['amount_rials'] ?? '0');
        if ($data['amount_rials'] <= 0) throw new Exception('مبلغ باید یک عدد مثبت باشد.');
        
        $data['payment_method'] = $postData['payment_method'] ?? '';
        if (!array_key_exists($data['payment_method'], self::VALID_PAYMENT_METHODS)) throw new Exception('روش پرداخت نامعتبر است.');

        $data['paying_contact_id'] = filter_var($postData['paying_contact_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $data['paying_details'] = trim($postData['paying_details'] ?? '');
        if (!$data['paying_contact_id'] && !$data['paying_details']) throw new Exception('اطلاعات پرداخت‌کننده الزامی است.');

        $data['receiving_contact_id'] = filter_var($postData['receiving_contact_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $data['receiving_details'] = trim($postData['receiving_details'] ?? '');
        if (!$data['receiving_contact_id'] && !$data['receiving_details']) throw new Exception('اطلاعات دریافت‌کننده الزامی است.');

        $data['related_transaction_id'] = filter_var($postData['related_transaction_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $data['notes'] = trim($postData['notes'] ?? '');
        
        foreach ($postData as $key => $value) {
            if (str_starts_with($key, 'method_details_')) {
                if (str_ends_with($key, '_date') && !empty($value)) {
                    $data[$key] = Helper::parseJalaliDateToSql(trim($value));
                } else {
                    $data[$key] = trim($value) ?: null;
                }
            }
        }

        return $data;
    }

    private function handleBankTransaction(array $postData, int $paymentId, array $paymentData, bool $isEditMode): void {
        $bankAccountId = filter_var($postData['source_bank_account_id'] ?? $postData['destination_bank_account_id'] ?? null, FILTER_VALIDATE_INT);
        
        if ($bankAccountId) {
            $amountChange = ($paymentData['direction'] === 'inflow') ? $paymentData['amount_rials'] : -$paymentData['amount_rials'];
            $bankTxData = [
                'bank_account_id' => $bankAccountId, 'related_payment_id' => $paymentId,
                'transaction_type' => $paymentData['direction'], 'amount' => $amountChange,
                'transaction_date' => $paymentData['payment_date'],
                'description' => "مربوط به پرداخت/دریافت شماره {$paymentId}",
            ];
            
            $savedBankTxId = $this->bankAccountRepository->saveBankTransaction($bankTxData);
            $this->paymentRepository->update($paymentId, ['bank_transaction_id' => $savedBankTxId]);
            $this->bankAccountRepository->updateCurrentBalance($bankAccountId, $amountChange);
        }
    }
    
    public function delete(int $paymentId): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('/app/payments');
        
        try {
            $this->db->beginTransaction();
            $relatedBankTx = $this->paymentRepository->findRelatedBankTransaction($paymentId);
            if ($relatedBankTx) {
                $amountToRevert = - (float) $relatedBankTx['amount'];
                $this->bankAccountRepository->updateCurrentBalance((int)$relatedBankTx['bank_account_id'], $amountToRevert);
                $this->paymentRepository->deleteBankTransaction((int)$relatedBankTx['id']);
            }
            if ($this->paymentRepository->delete($paymentId)) {
                $this->db->commit();
                $this->setSessionMessage('پرداخت/دریافت با موفقیت حذف شد.', 'success', 'payment_success');
            } else {
                throw new Exception("رکورد برای حذف یافت نشد.");
            }
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->logger->error("Error deleting payment.", ['id' => $paymentId, 'exception' => $e]);
            $this->setSessionMessage("خطا در حذف: " . $e->getMessage(), 'danger', 'payment_error');
        }
        $this->redirect('/app/payments');
    }
}
