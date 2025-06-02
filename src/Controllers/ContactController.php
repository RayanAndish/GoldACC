<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions
use Morilog\Jalali\Jalalian; // Add Jalalian namespace

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Dependencies
use App\Repositories\ContactRepository;
use App\Utils\Helper; // Utility functions
use App\core\CSRFProtector; // Added for CSRF protection

/**
 * ContactController handles HTTP requests related to Contacts (Customers/Suppliers).
 * Manages listing, add/edit forms, ledger view, and save/delete processing.
 * Inherits from AbstractController.
 */
class ContactController extends AbstractController {

    private ContactRepository $contactRepository;
    // Define valid contact types centrally (can be moved to config or a constants file/enum later)
    private const array VALID_CONTACT_TYPES = ['debtor', 'creditor_account', 'counterparty', 'mixed', 'other'];

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
        if (!isset($this->services['contactRepository']) || !$this->services['contactRepository'] instanceof ContactRepository) {
            throw new \Exception('ContactRepository not found in services array for ContactController.');
        }
        $this->contactRepository = $this->services['contactRepository'];
        $this->logger->debug("ContactController initialized.");
    }

    /**
     * Displays the list of contacts.
     * Includes search and pagination.
     * Route: /app/contacts (GET)
     */
    public function index(): void {
        $this->requireLogin();
        // Optional: Permission check

        $pageTitle = "مدیریت مشتریان و مخاطبین";
        $contacts = [];
        $paginationData = [];
        $errorMessage = $this->getFlashMessage('contact_error');
        $successMessage = $this->getFlashMessage('contact_success');

        // Search and Pagination
        $itemsPerPage = (int)($this->config['app']['items_per_page'] ?? 15);
        $currentPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $searchTerm = trim(filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? '');
        $offset = ($currentPage - 1) * $itemsPerPage;

        try {
            // Fetch paginated and filtered contacts WITH balance from Repository
            // ** IMPORTANT: Calculating balance per contact here (N+1) is inefficient.
            //    The Repository method should perform a JOIN or subquery to get the balance.
            //    Assume getFilteredAndPaginatedWithBalance exists in ContactRepository.
            $searchResult = $this->contactRepository->searchAndPaginate($searchTerm, $itemsPerPage, $offset);
            $contacts = $searchResult['contacts'];
            $totalRecords = $searchResult['total'];
            $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $itemsPerPage) : 1;
            $currentPage = max(1, min($currentPage, $totalPages));
            $paginationData = [
                'totalRecords' => $totalRecords,
                'totalPages' => $totalPages,
                'currentPage' => $currentPage,
                'limit' => $itemsPerPage
            ];

            // Prepare data for display
            foreach ($contacts as &$contact) { // Use reference
                 $contact['name'] = Helper::escapeHtml($contact['name']);
                 $contact['type_farsi'] = Helper::getContactTypeFarsi($contact['type'] ?? ''); // Use helper, handle potential null
                 $contact['details'] = Helper::escapeHtml($contact['details'] ?? '');
                 $contact['credit_limit_formatted'] = Helper::formatRial($contact['credit_limit'] ?? 0);
                 $contact['created_at_persian'] = $contact['created_at'] ? Jalalian::fromFormat('Y-m-d H:i:s', $contact['created_at'])->format('Y/m/d H:i') : '-';
                 // Balance is now assumed to be fetched by the repository query
                 $contact['balance_formatted'] = Helper::formatRial($contact['balance'] ?? 0); // Format balance from repo
            }
            unset($contact);

            $this->logger->debug("Contacts list fetched successfully.", ['count' => count($contacts), 'total' => $totalRecords, 'page' => $currentPage]);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching contacts list.", ['exception' => $e]);
            $errorMessage = "خطا در بارگذاری لیست مخاطبین.";
            if ($this->config['app']['debug']) {
                 $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
            }
            $contacts = [];
            $paginationData = Helper::generatePaginationData(1, 1, 0, $itemsPerPage);
        }

        // Render the list view
        $this->render('contacts/list', [
            'page_title' => $pageTitle,
            'contacts'   => $contacts,
            'error_msg'  => $errorMessage ? $errorMessage['text'] : null,
            'success_msg'=> $successMessage ? $successMessage['text'] : null,
            'search_term'=> Helper::escapeHtml($searchTerm),
            'pagination' => $paginationData,
            'totalRecords' => $totalRecords // اضافه شد
        ]);
    }

    /**
     * Displays the form for adding a new contact.
     * Route: /app/contacts/add (GET)
     */
    public function showAddForm(): void {
         $this->requireLogin();
         // Optional: Permission check

         $pageTitle = "افزودن مخاطب جدید";
         $formError = $this->getFlashMessage('form_error');
         $formData = $_SESSION['form_data']['contact_add'] ?? null;
         if ($formData) { unset($_SESSION['form_data']['contact_add']); }

         $defaultData = [
            'id' => null,
            'name' => Helper::escapeHtml($formData['name'] ?? ''),
            'type' => $formData['type'] ?? 'counterparty', // Default type
            'details' => Helper::escapeHtml($formData['details'] ?? ''),
            'credit_limit' => $formData['credit_limit'] ?? '0' // Keep raw string for repopulation
         ];

         $this->render('contacts/form', [
            'page_title'         => $pageTitle,
            'form_action'        => $this->config['app']['base_url'] . '/app/contacts/save',
            'contact'            => $defaultData,
            'is_edit_mode'       => false,
            'submit_button_text' => 'ذخیره مخاطب جدید',
            'error_message'      => $formError ? $formError['text'] : null,
            'loading_error'      => null,
            'valid_contact_types'=> self::VALID_CONTACT_TYPES // Pass valid types to the view
         ]);
    }

     /**
      * Displays the form for editing an existing contact.
      * Route: /app/contacts/edit/{id} (GET)
      *
      * @param int $contactId The ID of the contact to edit.
      */
     public function showEditForm(int $contactId): void {
         $contactId = (int)$contactId;
         $this->requireLogin();
         // Optional: Permission check

         $pageTitle = "ویرایش مخاطب";
         $loadingError = null;
         $formError = $this->getFlashMessage('form_error');
         $contactData = null;

         if ($contactId <= 0) {
             $this->setSessionMessage('شناسه مخاطب نامعتبر است.', 'danger', 'contact_error');
             $this->redirect('/app/contacts');
         }

         $sessionFormDataKey = 'contact_edit_' . $contactId;
         $sessionFormData = $_SESSION['form_data'][$sessionFormDataKey] ?? null;
         if ($sessionFormData) {
             unset($_SESSION['form_data'][$sessionFormDataKey]);
             $contactData = [
                 'id' => $contactId,
                 'name' => Helper::escapeHtml($sessionFormData['name'] ?? ''),
                 'type' => $sessionFormData['type'] ?? '',
                 'details' => Helper::escapeHtml($sessionFormData['details'] ?? ''),
                 'credit_limit' => $sessionFormData['credit_limit'] ?? '' // Raw string from previous input
             ];
             $pageTitle .= " (داده‌های اصلاح نشده)";
             $this->logger->debug("Repopulating edit contact form from session data.", ['contact_id' => $contactId]);
         } else {
             try {
                 $contactFromDb = $this->contactRepository->getById($contactId);
                 if (!$contactFromDb) {
                     $this->setSessionMessage('مخاطب یافت نشد.', 'warning', 'contact_error');
                     $this->redirect('/app/contacts');
                 }
                 $contactData = [
                     'id' => (int)$contactFromDb['id'],
                     'name' => Helper::escapeHtml($contactFromDb['name'] ?? ''),
                     'type' => $contactFromDb['type'] ?? '',
                     'details' => Helper::escapeHtml($contactFromDb['details'] ?? ''),
                     // Format number without separators for form input field
                     'credit_limit' => Helper::formatNumber($contactFromDb['credit_limit'] ?? 0, 0, '.', '')
                 ];
                 $this->logger->debug("Contact data fetched from database.", ['contact_id' => $contactId]);
             } catch (Throwable $e) {
                 $this->logger->error("Error loading contact for editing.", ['contact_id' => $contactId, 'exception' => $e]);
                 $loadingError = 'خطا در بارگذاری اطلاعات مخاطب.';
                 $contactData = ['id' => $contactId, 'name' => '[خطا]', /* defaults */ ];
             }
         }

         $this->render('contacts/form', [
             'page_title'         => $pageTitle,
             'form_action'        => $this->config['app']['base_url'] . '/app/contacts/save',
             'contact'            => $contactData,
             'is_edit_mode'       => true,
             'submit_button_text' => 'به‌روزرسانی اطلاعات',
             'error_message'      => $formError ? $formError['text'] : null,
             'loading_error'      => $loadingError,
             'valid_contact_types'=> self::VALID_CONTACT_TYPES // Pass valid types to the view
         ]);
     }


    /**
     * Processes the save request for a contact (add or edit).
     * Route: /app/contacts/save (POST)
     */
    public function save(): void {
        $this->requireLogin();
        // Optional: Permission check

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/contacts');
        }

        // CSRF token validation
        if (!CSRFProtector::validateToken($_POST['csrf_token'] ?? null)) {
            $this->setSessionMessage('خطای امنیتی: توکن نامعتبر است.', 'danger', 'contact_error');
            $this->redirect('/app/contacts');
        }

        // --- Input Extraction ---
        $contactId = filter_input(INPUT_POST, 'contact_id', FILTER_VALIDATE_INT);
        $isEditMode = ($contactId !== null && $contactId > 0);
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? '';
        $details = trim($_POST['details'] ?? '');
        $creditLimitStr = $_POST['credit_limit'] ?? ''; // Raw string from form

        $redirectUrlOnError = '/app/contacts/' . ($isEditMode ? 'edit/' . $contactId : 'add');
        $sessionFormDataKey = $isEditMode ? 'contact_edit_' . $contactId : 'contact_add';

        // --- Validation ---
        $errors = [];
        if (empty($name)) { $errors[] = 'نام / عنوان الزامی است.'; }
        elseif (mb_strlen($name) > 150) { $errors[] = 'نام طولانی‌تر از حد مجاز (۱۵۰) است.'; }
        if (empty($type) || !in_array($type, self::VALID_CONTACT_TYPES)) { $errors[] = 'نوع مخاطب انتخاب شده نامعتبر است.'; }
        // No length limit check for details (TEXT type) unless desired

        // Validate and process credit limit
        $creditLimit = null; // Default to null (no limit)
        $cleanedCredit = Helper::sanitizeFormattedNumber($creditLimitStr);
        if ($cleanedCredit !== '' && $cleanedCredit !== null) { // If input was given after sanitizing
             if (is_numeric($cleanedCredit)) {
                 $creditLimitFloat = (float)$cleanedCredit;
                 if ($creditLimitFloat >= 0) {
                     $creditLimit = $creditLimitFloat; // Store as float if valid and non-negative
                 } else {
                      $errors[] = 'سقف اعتبار نمی‌تواند منفی باشد.';
                 }
             } else {
                  $errors[] = 'فرمت سقف اعتبار نامعتبر است (پس از پاکسازی).';
             }
        } elseif (!empty($creditLimitStr)) { // Error only if input was given but was completely invalid
              $errors[] = 'فرمت سقف اعتبار نامعتبر است.';
        }
        // If $creditLimitStr was empty or '0', $creditLimit remains null or becomes 0.0 based on sanitization

        // --- Handle Validation Failure ---
        if (!empty($errors)) {
            $this->logger->warning("Contact form validation failed.", ['errors' => $errors, 'contact_id' => $contactId]);
            $this->setSessionMessage(implode('<br>', $errors), 'danger', 'form_error');
            $_SESSION['form_data'][$sessionFormDataKey] = $_POST; // Repopulate with raw POST data
            $this->redirect($redirectUrlOnError);
        }

        // --- Prepare Data for Repository ---
        $contactData = [
            'id' => $isEditMode ? $contactId : null,
            'name' => $name,
            'type' => $type,
            'details' => $details ?: null, // Store null if empty
            'credit_limit' => $creditLimit, // Store float or null
        ];

        // --- Database Operation ---
        try {
            $savedContactId = $this->contactRepository->save($contactData);
            $actionWord = $isEditMode ? 'به‌روزرسانی' : 'اضافه';
            // Log activity (assumes Helper exists and $db is accessible)
            $eventType = $isEditMode ? 'CONTACT_UPDATED' : 'CONTACT_CREATED';
            Helper::logActivity($this->db, "Contact {$actionWord}d: {$name}", $eventType, 'INFO', ['contact_id' => $savedContactId]);
            $this->logger->info("Contact saved successfully.", ['id' => $savedContactId, 'action' => $actionWord]);

            $this->setSessionMessage("مخاطب با موفقیت {$actionWord} شد.", 'success', 'contact_success');
            $this->redirect('/app/contacts');

        } catch (Throwable $e) {
            $this->logger->error("Error saving contact.", ['contact_id' => $contactId, 'exception' => $e]);
            $errorMessage = "خطا در ذخیره اطلاعات مخاطب.";
            // Check for unique constraint violation etc. if repo throws specific exceptions
             if ($this->config['app']['debug']) {
                 $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
             }
            $this->setSessionMessage($errorMessage, 'danger', 'form_error');
                        $_SESSION['form_data'][$sessionFormDataKey] = $_POST; // Repopulate            
                        $this->redirect($redirectUrlOnError);        }    }    
                        
    /**

      * @param int $contactId The ID of the contact to delete.
      */
     public function delete(int $contactId): void {
         $this->requireLogin();
         // Optional: Permission check

         if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             $this->redirect('/app/contacts');
         }

         // CSRF token validation
         if (!CSRFProtector::validateToken($_POST['csrf_token'] ?? null)) {
             $this->setSessionMessage('خطای امنیتی: توکن نامعتبر است.', 'danger', 'contact_error');
             $this->redirect('/app/contacts');
         }

         if ($contactId <= 0) {
             $this->setSessionMessage('شناسه مخاطب نامعتبر است.', 'danger', 'contact_error');
             $this->redirect('/app/contacts');
         }

         $this->logger->info("Attempting to delete contact.", ['contact_id' => $contactId]);

         try {
             // Check for dependencies (transactions, payments) before deleting
             // Assumes ContactRepository::isUsedInTransactionsOrPayments exists
             $isUsed = $this->contactRepository->isUsedInTransactionsOrPayments($contactId);

             if ($isUsed) {
                 $this->logger->warning("Delete aborted: Contact is used in transactions/payments.", ['contact_id' => $contactId]);
                 $this->setSessionMessage('این مخاطب در معاملات یا پرداخت‌ها استفاده شده و قابل حذف نیست.', 'warning', 'contact_error');
             } else {
                 // Proceed with deletion
                 $isDeleted = $this->contactRepository->delete($contactId);
                 if ($isDeleted) {
                     $this->logger->info("Contact deleted successfully.", ['contact_id' => $contactId]);
                     $this->setSessionMessage('مخاطب با موفقیت حذف شد.', 'success', 'contact_success');
                 } else {
                     $this->logger->warning("Contact deletion failed (not found?).", ['contact_id' => $contactId]);
                     $this->setSessionMessage('مخاطب یافت نشد یا حذف آن با مشکل مواجه شد.', 'warning', 'contact_error');
                 }
             }
         } catch (Throwable $e) {
             $this->logger->error("Error deleting contact.", ['contact_id' => $contactId, 'exception' => $e]);
             $errorMessage = "خطا در حذف مخاطب.";
              // Check for specific foreign key errors if repo doesn't prevent it fully
             if ($this->config['app']['debug']) { $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
             $this->setSessionMessage($errorMessage, 'danger', 'contact_error');
         }

         $this->redirect('/app/contacts');
     }

     /**
      * Displays the ledger (transaction and payment history) for a specific contact.
      * Route: /app/contacts/ledger/{id} (GET)
      *
      * @param int $contactId The ID of the contact.
      */
     public function showLedger(int $contactId): void {
         $this->requireLogin();
         // Optional: Permission check

         $pageTitle = "دفتر کل مخاطب";
         $contactInfo = null;
         $ledgerEntries = [];
         $startBalancePeriod = 0.0;
         $totalBalance = 0.0; // Overall balance
         $errorMessage = $this->getFlashMessage('ledger_error');

         if ($contactId <= 0) {
             $this->setSessionMessage('شناسه مخاطب نامعتبر است.', 'danger', 'contact_error');
             $this->redirect('/app/contacts');
         }

         // Get date filters
         $startDateJalali = trim(filter_input(INPUT_GET, 'start_date', FILTER_DEFAULT) ?? '');
         $endDateJalali = trim(filter_input(INPUT_GET, 'end_date', FILTER_DEFAULT) ?? '');
         $startDateSql = Helper::parseJalaliDateToSql($startDateJalali);
         $endDateSql = Helper::parseJalaliDateToSql($endDateJalali);
         if ($endDateSql) { $endDateSql .= ' 23:59:59'; } // Include full end day

         try {
             // Fetch contact info
             $contactInfo = $this->contactRepository->getById($contactId);
             if (!$contactInfo) {
                 $this->setSessionMessage('مخاطب یافت نشد.', 'warning', 'contact_error');
                 $this->redirect('/app/contacts');
             }
             $pageTitle .= " - " . Helper::escapeHtml($contactInfo['name']);

             // Fetch ledger entries (combined transactions/payments sorted by date)
             // Assumes ContactRepository::getLedgerEntries exists
             $ledgerEntries = $this->contactRepository->getLedgerEntries(
                 $contactId,
                 $startDateSql,
                 $endDateSql
             );

             // Calculate starting balance for the filtered period
             // Assumes ContactRepository::calculateBalanceBeforeDate exists
             $startBalancePeriod = $this->contactRepository->calculateBalanceBeforeDate($contactId, $startDateSql);

             // Calculate the overall current balance
             // Assumes ContactRepository::calculateBalance exists
             $totalBalance = $this->contactRepository->calculateBalance($contactId);

             // Prepare entries for display (formatting done in view or helper within view)

             $this->logger->debug("Contact ledger fetched successfully.", ['contact_id' => $contactId, 'count' => count($ledgerEntries)]);

         } catch (Throwable $e) {
             $this->logger->error("Error fetching contact ledger.", ['contact_id' => $contactId, 'exception' => $e]);
             $errorMessage = "خطا در بارگذاری دفتر کل.";
             if ($this->config['app']['debug']) { $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
             $contactInfo = ['id' => $contactId, 'name' => '[خطا]'];
             $ledgerEntries = [];
             $startBalancePeriod = 0.0;
             $totalBalance = 0.0;
         }

         // Render the ledger view
         $this->render('contacts/ledger', [
             'page_title'     => $pageTitle,
             'contact_info'   => $contactInfo,
             'ledger_entries' => $ledgerEntries,
             'start_balance_period' => $startBalancePeriod,
             'total_balance'  => $totalBalance,
             'filters'        => [
                 'start_date_jalali' => $startDateJalali,
                 'end_date_jalali' => $endDateJalali,
             ],
             'error_msg'      => $errorMessage ? $errorMessage['text'] : null,
         ]);
     }

} // End ContactController class