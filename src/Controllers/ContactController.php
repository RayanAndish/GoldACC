<?php
namespace App\Controllers;

use PDO;
use Monolog\Logger;
use Throwable;
use Morilog\Jalali\Jalalian;
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;
use App\Repositories\ContactRepository;
use App\Repositories\ContactWeightLedgerRepository;
use App\Utils\Helper;
use App\Core\CSRFProtector;

class ContactController extends AbstractController {

    private ContactRepository $contactRepository;
    private ContactWeightLedgerRepository $contactWeightLedgerRepository; // <-- این خط اضافه شود
    private const array VALID_CONTACT_TYPES = ['debtor', 'creditor_account', 'counterparty', 'mixed', 'other'];

    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        $this->contactRepository = $services['contactRepository'];
        $this->contactWeightLedgerRepository = $services['contactWeightLedgerRepository']; // <-- این خط اضافه شود
        $this->logger->debug("ContactController initialized.");
    }


    public function index(): void {
        $this->requireLogin();
        $pageTitle = "مدیریت مشتریان و مخاطبین";
        $errorMessage = $this->getFlashMessage('contact_error');
        $successMessage = $this->getFlashMessage('contact_success');

        $itemsPerPage = (int)($this->config['app']['items_per_page'] ?? 15);
        $currentPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $searchTerm = trim(filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? '');
        
        try {
            // (اصلاح شده) ابتدا تعداد کل را می‌گیریم
            $totalRecords = $this->contactRepository->countFiltered($searchTerm);
            // سپس اطلاعات صفحه‌بندی را تولید می‌کنیم
            $paginationData = Helper::generatePaginationData($currentPage, $itemsPerPage, $totalRecords);
            
            // و در نهایت لیست مخاطبین صفحه فعلی را واکشی می‌کنیم
            $contacts = $this->contactRepository->searchAndPaginate($searchTerm, $paginationData['itemsPerPage'], $paginationData['offset']);

            // محاسبه مانده حساب برای هر مخاطب در لیست
            foreach ($contacts as &$contact) {
                $contact['balance'] = $this->contactRepository->calculateBalance((int)$contact['id']);
                $contact['type_farsi'] = Helper::getContactTypeFarsi($contact['type'] ?? '');
                $contact['credit_limit_formatted'] = Helper::formatRial($contact['credit_limit'] ?? 0);
                $contact['created_at_persian'] = !empty($contact['created_at']) ? Jalalian::fromDateTime($contact['created_at'])->format('Y/m/d') : '-';
            }
            unset($contact);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching contacts list.", ['exception' => $e]);
            $this->setFlashMessage("خطا در بارگذاری لیست مخاطبین.", 'danger', 'contact_error');
            $contacts = [];
            $paginationData = Helper::generatePaginationData(1, $itemsPerPage, 0);
        }

        $this->render('contacts/list', [
            'page_title' => $pageTitle,
            'contacts'   => $contacts,
            'error_msg'  => $errorMessage['text'] ?? null,
            'success_msg'=> $successMessage['text'] ?? null,
            'search_term'=> Helper::escapeHtml($searchTerm),
            'pagination' => $paginationData,
            'baseUrl' => $this->config['app']['base_url'],
        ]);
    }

    public function showAddForm(): void {
        $this->requireLogin();
        $pageTitle = "افزودن مخاطب جدید";
        $formError = $this->getFlashMessage('form_error');
        $formData = $_SESSION['form_data']['contact_add'] ?? [];
        if ($formData) { unset($_SESSION['form_data']['contact_add']); }

        $this->render('contacts/form', [
            'page_title'         => $pageTitle,
            'form_action'        => $this->config['app']['base_url'] . '/app/contacts/save',
            'contact'            => $formData,
            'is_edit_mode'       => false,
            'submit_button_text' => 'ذخیره مخاطب جدید',
            'error_message'      => $formError['text'] ?? null,
            'loading_error'      => null,
            'valid_contact_types'=> self::VALID_CONTACT_TYPES,
            'baseUrl' => $this->config['app']['base_url'],
        ]);
    }

    public function showEditForm(int $contactId): void {
        $this->requireLogin();
        $pageTitle = "ویرایش مخاطب";
        $loadingError = null;
        $formError = $this->getFlashMessage('form_error');
        
        if ($contactId <= 0) {
            $this->setSessionMessage('شناسه مخاطب نامعتبر است.', 'danger', 'contact_error');
            $this->redirect('/app/contacts');
            return;
        }

        try {
            $contactData = $this->contactRepository->getById($contactId);
            if (!$contactData) {
                $this->setSessionMessage('مخاطب یافت نشد.', 'warning', 'contact_error');
                $this->redirect('/app/contacts');
                return;
            }
        } catch (Throwable $e) {
            $this->logger->error("Error loading contact for editing.", ['contact_id' => $contactId, 'exception' => $e]);
            $loadingError = 'خطا در بارگذاری اطلاعات مخاطب.';
            $contactData = [];
        }

        $this->render('contacts/form', [
            'page_title'         => $pageTitle,
            'form_action'        => $this->config['app']['base_url'] . '/app/contacts/save',
            'contact'            => $contactData,
            'is_edit_mode'       => true,
            'submit_button_text' => 'به‌روزرسانی اطلاعات',
            'error_message'      => $formError['text'] ?? null,
            'loading_error'      => $loadingError,
            'valid_contact_types'=> self::VALID_CONTACT_TYPES,
            'baseUrl' => $this->config['app']['base_url'],
        ]);
    }

    public function save(): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/contacts');
            return;
        }

        if (!CSRFProtector::validateToken($_POST['csrf_token'] ?? null)) {
            $this->setSessionMessage('خطای امنیتی: توکن نامعتبر است.', 'danger', 'contact_error');
            $this->redirect('/app/contacts');
            return;
        }

        $contactId = filter_input(INPUT_POST, 'contact_id', FILTER_VALIDATE_INT);
        $isEditMode = ($contactId !== null && $contactId > 0);
        
        $redirectUrlOnError = $this->config['app']['base_url'] . '/app/contacts/' . ($isEditMode ? 'edit/' . $contactId : 'add');
        $sessionFormDataKey = $isEditMode ? 'contact_edit_' . $contactId : 'contact_add';

        try {
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? '';
            if (empty($name)) throw new \Exception('نام / عنوان الزامی است.');
            if (empty($type) || !in_array($type, self::VALID_CONTACT_TYPES)) throw new \Exception('نوع مخاطب انتخاب شده نامعتبر است.');

            $creditLimit = Helper::sanitizeFormattedNumber($_POST['credit_limit'] ?? '');

            $contactData = [
                'id' => $isEditMode ? $contactId : null,
                'name' => $name,
                'type' => $type,
                'details' => trim($_POST['details'] ?? '') ?: null,
                'credit_limit' => $creditLimit,
            ];

            $savedContactId = $this->contactRepository->save($contactData);
            $actionWord = $isEditMode ? 'به‌روزرسانی' : 'اضافه';
            $this->setSessionMessage("مخاطب با موفقیت {$actionWord} شد.", 'success', 'contact_success');
            $this->redirect('/app/contacts');

        } catch (Throwable $e) {
            $this->logger->warning("Contact form submission failed.", ['errors' => $e->getMessage(), 'contact_id' => $contactId]);
            $this->setSessionMessage($e->getMessage(), 'danger', 'form_error');
            $_SESSION['form_data'][$sessionFormDataKey] = $_POST;
            $this->redirect($redirectUrlOnError);
        }
    }

    public function delete(int $contactId): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/contacts');
            return;
        }

        if (!CSRFProtector::validateToken($_POST['csrf_token'] ?? null)) {
            $this->setSessionMessage('خطای امنیتی: توکن نامعتبر است.', 'danger', 'contact_error');
            $this->redirect('/app/contacts');
            return;
        }

        try {
            if ($this->contactRepository->isUsedInTransactionsOrPayments($contactId)) {
                $this->setSessionMessage('این مخاطب در معاملات یا پرداخت‌ها استفاده شده و قابل حذف نیست.', 'warning', 'contact_error');
            } else {
                if ($this->contactRepository->delete($contactId)) {
                    $this->setSessionMessage('مخاطب با موفقیت حذف شد.', 'success', 'contact_success');
                } else {
                    $this->setSessionMessage('مخاطب یافت نشد.', 'warning', 'contact_error');
                }
            }
        } catch (Throwable $e) {
            $this->logger->error("Error deleting contact.", ['contact_id' => $contactId, 'exception' => $e]);
            $this->setSessionMessage("خطا در حذف مخاطب.", 'danger', 'contact_error');
        }
        $this->redirect('/app/contacts');
    }

    
    public function showLedger(int $contactId): void {
        $this->requireLogin();
        
        $errorMessage = $this->getFlashMessage('ledger_error');
        $startDateJalali = trim(filter_input(INPUT_GET, 'start_date', FILTER_DEFAULT) ?? '');
        $endDateJalali = trim(filter_input(INPUT_GET, 'end_date', FILTER_DEFAULT) ?? '');
        
        // تبدیل تاریخ‌ها به فرمت SQL
        $startDateSql = !empty($startDateJalali) ? Helper::parseJalaliDateToSql($startDateJalali) . ' 00:00:00' : null;
        $endDateSql = !empty($endDateJalali) ? Helper::parseJalaliDateToSql($endDateJalali, true) . ' 23:59:59' : null;

        try {
            $contactInfo = $this->contactRepository->getById($contactId);
            if (!$contactInfo) {
                $this->setSessionMessage('مخاطب یافت نشد.', 'warning', 'contact_error');
                $this->redirect('/app/contacts');
                return;
            }
            
            $pageTitle = "ته حساب - " . Helper::escapeHtml($contactInfo['name']);
 
              // 1. کل تاریخچه را برای محاسبه مانده کل واکشی کن
            $fullLedger = $this->contactRepository->getUnifiedLedgerEntries($contactId, null, null);
            
            $totalRialBalance = 0.0;
            $totalWeightBalance = 0.0;
            $totalCountableBalance = 0.0; // <<-- متغیر جدید

            foreach($fullLedger as $entry) {
                $totalRialBalance += ((float)($entry['debit_rial'] ?? 0) - (float)($entry['credit_rial'] ?? 0));
                $totalWeightBalance += ((float)($entry['credit_weight_for_balance'] ?? 0) - (float)($entry['debit_weight_for_balance'] ?? 0));
                $totalCountableBalance += ((float)($entry['credit_count_for_balance'] ?? 0) - (float)($entry['debit_count_for_balance'] ?? 0)); // <<-- محاسبه مانده تعدادی
            }

            // 2. تاریخچه فیلتر شده را برای نمایش در جدول واکشی کن
            $ledgerEntries = $this->contactRepository->getUnifiedLedgerEntries($contactId, $startDateSql, $endDateSql);

            // 3. مانده اول دوره را محاسبه کن
            $startRialBalance = 0.0;
            $startWeightBalance = 0.0;
            if ($startDateSql) {
                foreach($fullLedger as $entry) {
                    if (strtotime($entry['entry_date']) < strtotime($startDateSql)) {
                        $startRialBalance += ((float)($entry['debit_rial'] ?? 0) - (float)($entry['credit_rial'] ?? 0));
                        $startWeightBalance += ((float)($entry['credit_weight_750'] ?? 0) - (float)($entry['debit_weight_750'] ?? 0));
                    }
                }
            }

        } catch (Throwable $e) {
            $this->logger->error("Error fetching contact ledger.", ['contact_id' => $contactId, 'exception' => $e]);
            $this->setFlashMessage("خطا در بارگذاری کارت حساب: " . $e->getMessage(), 'danger', 'ledger_error');
            $this->redirect('/app/contacts');
            return;
        }

           $this->render('contacts/ledger', [
            'page_title'           => $pageTitle,
            'contact_info'         => $contactInfo,
            'ledger_entries'       => $ledgerEntries,
            'start_rial_balance'   => $startRialBalance,
            'start_weight_balance' => $startWeightBalance,
            'total_rial_balance'   => $totalRialBalance,
            'total_weight_balance' => $totalWeightBalance,
            'filters'              => ['start_date_jalali' => $startDateJalali, 'end_date_jalali' => $endDateJalali],
            'error_msg'            => $errorMessage['text'] ?? null,
            'baseUrl'              => $this->config['app']['base_url'],
            'total_countable_balance' => $totalCountableBalance, // <<-- پاس دادن متغیر جدید به ویو
            'start_countable_balance' => $startCountableBalance, // <<-- پاس دادن متغیر جدید به ویو
        ]);
    }
    
    /**
     * (جدید) کارت حساب وزنی (طلا) یک مخاطب را نمایش می‌دهد.
     */
    public function showWeightLedger(int $contactId): void {
        $this->requireLogin();
        $pageTitle = "کاردکس وزنی طلا";
        
        try {
            $contactInfo = $this->contactRepository->getById($contactId);
            if (!$contactInfo) {
                $this->setFlashMessage('مخاطب یافت نشد.', 'warning');
                $this->redirect('/app/contacts');
                return;
            }
            
            // به این وابستگی نیاز داریم، باید آن را به __construct اضافه کنیم
            $weightLedgerEntries = $this->contactWeightLedgerRepository->getLedgerForContact($contactId);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching contact weight ledger.", ['contact_id' => $contactId, 'exception' => $e]);
            $this->setFlashMessage("خطا در بارگذاری کاردکس وزنی.", 'danger');
            $this->redirect('/app/contacts');
            return;
        }

        $this->render('contacts/weight_ledger', [
            'page_title'     => $pageTitle . " - " . Helper::escapeHtml($contactInfo['name']),
            'contact_info'   => $contactInfo,
            'ledger_entries' => $weightLedgerEntries,
            'baseUrl'        => $this->config['app']['base_url'],
        ]);
    }
}