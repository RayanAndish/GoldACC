<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Dependencies
use App\Repositories\AssayOfficeRepository;
use App\Utils\Helper; // Utility functions

/**
 * AssayOfficeController handles HTTP requests related to Assay Offices (Reygiri Centers).
 * Manages listing, add/edit forms, and save/delete processing.
 * Inherits from AbstractController.
 */
class AssayOfficeController extends AbstractController {

    private AssayOfficeRepository $assayOfficeRepository;

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
        if (!isset($this->services['assayOfficeRepository']) || !$this->services['assayOfficeRepository'] instanceof AssayOfficeRepository) {
            throw new \Exception('AssayOfficeRepository not found in services array for AssayOfficeController.');
        }
        $this->assayOfficeRepository = $this->services['assayOfficeRepository'];
        $this->logger->debug("AssayOfficeController initialized.");
    }

    /**
     * Displays the list of Assay Offices.
     * Includes search and pagination.
     * Route: /app/assay-offices (GET)
     */
    public function index(): void {
        // Access Control
        $this->requireLogin();
        $this->requireAdmin(); // Or specific permission check

        $pageTitle = "مدیریت مراکز ری‌گیری";
        $assayOffices = [];
        $paginationData = [];
        $errorMessage = $this->getFlashMessage('assay_office_error'); // Get potential flash error
        $successMessage = $this->getFlashMessage('assay_office_success'); // Get potential flash success

        // Search and Pagination Logic
        $itemsPerPage = (int)($this->config['app']['items_per_page'] ?? 15);
        $currentPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $searchTerm = trim(filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? '');
        $offset = ($currentPage - 1) * $itemsPerPage;

        try {
            $searchResult = $this->assayOfficeRepository->searchAndPaginate($searchTerm, $itemsPerPage, $offset);
            $assayOffices = $searchResult['offices'];
            $totalRecords = $searchResult['total'];
            $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $itemsPerPage) : 1;
            $currentPage = max(1, min($currentPage, $totalPages));
            $paginationData = [
                'totalRecords' => $totalRecords,
                'totalPages' => $totalPages,
                'currentPage' => $currentPage,
                'limit' => $itemsPerPage
            ];

            foreach ($assayOffices as &$office) {
                 $office['name'] = Helper::escapeHtml($office['name']);
                 $office['phone'] = Helper::escapeHtml($office['phone'] ?? '');
                 $office['address'] = Helper::escapeHtml($office['address'] ?? '');
            }
            unset($office);

            $this->logger->debug("Assay offices list fetched successfully.", ['count' => count($assayOffices), 'total' => $totalRecords, 'page' => $currentPage]);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching assay offices list.", ['exception' => $e]);
            $errorMessage = "خطا در بارگذاری لیست مراکز ری‌گیری.";
            if ($this->config['app']['debug']) {
                 $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
            }
            $assayOffices = [];
            $paginationData = [
                'totalRecords' => 0,
                'totalPages' => 1,
                'currentPage' => 1,
                'limit' => $itemsPerPage
            ];
        }

        // Render the list view
        $this->render('assays/list', [ // Assuming view path is 'assays/list.php' based on tree.txt
            'page_title' => $pageTitle,
            'assay_offices' => $assayOffices,
            'error_msg'  => $errorMessage,
            'success_msg' => $successMessage ? $successMessage['text'] : null, // Display success message if present
            'search_term'=> Helper::escapeHtml($searchTerm),
            'pagination' => $paginationData
        ]);
    }

    /**
     * Displays the form for adding a new Assay Office.
     * Handles repopulation from session data if a previous save attempt failed.
     * Route: /app/assay-offices/add (GET)
     */
    public function showAddForm(): void {
        $this->requireLogin();
        $this->requireAdmin();

        $pageTitle = "افزودن مرکز ری‌گیری جدید";

        // Get potential form error message and previous form data from session
        $formError = $this->getFlashMessage('form_error'); // Use a generic key
        $formData = $_SESSION['form_data']['assay_office_add'] ?? null; // Use a specific key for this form's data
        if ($formData) {
             unset($_SESSION['form_data']['assay_office_add']); // Clear after reading
             $this->logger->debug("Repopulating add assay office form from session data.");
        }

        // Prepare default data, potentially overridden by session data
        $defaultData = [
            'id' => null,
            'name' => Helper::escapeHtml($formData['name'] ?? ''),
            'phone' => Helper::escapeHtml($formData['phone'] ?? ''),
            'address' => Helper::escapeHtml($formData['address'] ?? '')
        ];

        // Render the form view (assuming 'assays/form.php')
        $this->render('assays/form', [
            'page_title' => $pageTitle,
            'form_action' => $this->config['app']['base_url'] . '/app/assay-offices/save',
            'assay_office' => $defaultData,
            'is_edit_mode' => false,
            'submit_button_text' => 'ذخیره مرکز جدید',
            'error_message' => $formError ? $formError['text'] : null,
            'success_message' => null // No success message on add form initially
        ]);
    }

    /**
     * Displays the form for editing an existing Assay Office.
     * Fetches data from DB or repopulates from session if a previous save attempt failed.
     * Route: /app/assay-offices/edit/{id} (GET)
     *
     * @param int $officeId The ID of the office to edit.
     */
    public function showEditForm(int $officeId): void {
        $this->requireLogin();
        $this->requireAdmin();

        $pageTitle = "ویرایش مرکز ری‌گیری";
        $loadingError = null; // Error during DB fetch
        $formError = $this->getFlashMessage('form_error'); // Validation error from previous POST
        $assayOfficeData = null; // Final data for the form

        // Validate ID
        if ($officeId <= 0) {
            $this->setSessionMessage('شناسه مرکز ری‌گیری نامعتبر است.', 'danger', 'assay_office_error');
            $this->redirect('/app/assay-offices');
        }

        // Check for repopulation data from session first
        $sessionFormData = $_SESSION['form_data']['assay_office_edit_' . $officeId] ?? null;
        if ($sessionFormData) {
            unset($_SESSION['form_data']['assay_office_edit_' . $officeId]); // Clear after reading
            $assayOfficeData = [ // Rebuild from potentially unsafe session data, ensure escaping
                'id' => $officeId,
                'name' => Helper::escapeHtml($sessionFormData['name'] ?? ''),
                'phone' => Helper::escapeHtml($sessionFormData['phone'] ?? ''),
                'address' => Helper::escapeHtml($sessionFormData['address'] ?? '')
            ];
            $pageTitle .= " (داده‌های اصلاح نشده)";
            $this->logger->debug("Repopulating edit assay office form from session data.", ['office_id' => $officeId]);
        } else {
            // Fetch data from the database
            try {
                $officeFromDb = $this->assayOfficeRepository->getById($officeId);
                if (!$officeFromDb) {
                    $this->setSessionMessage('مرکز ری‌گیری یافت نشد.', 'warning', 'assay_office_error');
                    $this->redirect('/app/assay-offices');
                }
                // Prepare data from DB for the form (escaping is good practice even here)
                $assayOfficeData = [
                     'id' => (int)$officeFromDb['id'],
                     'name' => Helper::escapeHtml($officeFromDb['name'] ?? ''),
                     'phone' => Helper::escapeHtml($officeFromDb['phone'] ?? ''),
                     'address' => Helper::escapeHtml($officeFromDb['address'] ?? '')
                 ];
                $this->logger->debug("Assay office data fetched from database.", ['office_id' => $officeId]);
            } catch (Throwable $e) {
                $this->logger->error("Error loading assay office for editing.", ['office_id' => $officeId, 'exception' => $e]);
                $loadingError = 'خطا در بارگذاری اطلاعات مرکز.';
                // Provide default structure on load error
                $assayOfficeData = ['id' => $officeId, 'name' => '[خطا]', 'phone' => '', 'address' => ''];
            }
        }

        // Render the form view
        $this->render('assays/form', [
            'page_title' => $pageTitle,
            'form_action' => $this->config['app']['base_url'] . '/app/assay-offices/save',
            'assay_office' => $assayOfficeData,
            'is_edit_mode' => true,
            'submit_button_text' => 'به‌روزرسانی اطلاعات',
            'error_message' => $formError ? $formError['text'] : null, // Validation error msg
            'loading_error' => $loadingError // DB loading error msg
        ]);
    }


    /**
     * Processes the save request for an Assay Office (handles both add and edit).
     * Route: /app/assay-offices/save (POST)
     */
    public function save(): void {
        $this->requireLogin();
        $this->requireAdmin();

        // Only accept POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/assay-offices');
        }

        // --- Input Extraction and Basic Sanitization ---
        $officeId = filter_input(INPUT_POST, 'office_id', FILTER_VALIDATE_INT); // null for add, ID for edit
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $isEditMode = ($officeId !== null && $officeId > 0);

        // Redirect URL in case of validation errors
        $redirectUrlOnError = '/app/assay-offices/' . ($isEditMode ? 'edit/' . $officeId : 'add');
        $sessionFormDataKey = $isEditMode ? 'assay_office_edit_' . $officeId : 'assay_office_add';

        // --- Validation ---
        $errors = [];
        if (empty($name)) {
            $errors[] = 'نام مرکز ری‌گیری الزامی است.';
        } elseif (mb_strlen($name) > 150) {
            $errors[] = 'نام مرکز نمی‌تواند بیشتر از ۱۵۰ کاراکتر باشد.';
        }
        // Add more validation as needed (e.g., phone format, address length)
        if (mb_strlen($phone) > 50) { $errors[] = 'تلفن نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.'; }


        // --- Handle Validation Failure ---
        if (!empty($errors)) {
            $this->logger->warning("Assay office form validation failed.", ['errors' => $errors, 'office_id' => $officeId]);
            $this->setSessionMessage(implode('<br>', $errors), 'danger', 'form_error');
            // Store submitted data in session for repopulation
            $_SESSION['form_data'][$sessionFormDataKey] = $_POST;
            $this->redirect($redirectUrlOnError);
        }

        // --- Prepare Data for Repository ---
        $officeData = [
            'id' => $isEditMode ? $officeId : null, // Pass ID only for edits
            'name' => $name,
            'phone' => $phone ?: null, // Store null if empty
            'address' => $address ?: null, // Store null if empty
        ];

        // --- Database Operation ---
        try {
            // Use Repository to save data (handles INSERT or UPDATE based on ID)
            // Assumes AssayOfficeRepository::save() returns the ID on success
            $savedOfficeId = $this->assayOfficeRepository->save($officeData);

            $actionWord = $isEditMode ? 'به‌روزرسانی' : 'اضافه';
            $this->logger->info("Assay office saved successfully.", ['id' => $savedOfficeId, 'action' => $actionWord]);
            $this->setSessionMessage("مرکز ری‌گیری با موفقیت {$actionWord} شد.", 'success', 'assay_office_success');

            // Redirect to the list page on success
            $this->redirect('/app/assay-offices');

        } catch (Throwable $e) {
            $this->logger->error("Error saving assay office.", ['office_id' => $officeId, 'exception' => $e]);

            // Prepare error message (check for unique constraint violation etc. if repo throws specific exceptions)
            $errorMessage = "خطا در ذخیره اطلاعات مرکز ری‌گیری.";
            // Example: Check if it's a unique constraint error (depends on DB/Repo exception)
            // if ($e instanceof UniqueConstraintViolationException || str_contains($e->getMessage(), 'Duplicate entry')) {
            //    $errorMessage = "مرکز ری‌گیری با این نام قبلاً ثبت شده است.";
            // } else
            if ($this->config['app']['debug']) {
                 $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
            }

            // Set error message and redirect back to form with data
            $this->setSessionMessage($errorMessage, 'danger', 'form_error');
            $_SESSION['form_data'][$sessionFormDataKey] = $_POST; // Repopulate
            $this->redirect($redirectUrlOnError);
        }
    }

    /**
     * Processes the delete request for an Assay Office.
     * Route: /app/assay-offices/delete/{id} (POST)
     *
     * @param int $officeId The ID of the office to delete.
     */
    public function delete(int $officeId): void {
        $this->requireLogin();
        $this->requireAdmin();

        // Only accept POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/assay-offices');
        }
        // TODO: Add CSRF token validation here for POST requests

        // Validate ID
        if ($officeId <= 0) {
            $this->setSessionMessage('شناسه مرکز ری‌گیری نامعتبر است.', 'danger', 'assay_office_error');
            $this->redirect('/app/assay-offices');
        }

        $this->logger->info("Attempting to delete assay office.", ['office_id' => $officeId]);

        try {
            // Check for dependencies before deleting
            // Assumes AssayOfficeRepository::countUsageInTransactions exists
            $usageCount = $this->assayOfficeRepository->countUsageInTransactions($officeId);

            if ($usageCount > 0) {
                $this->logger->warning("Delete aborted: Assay office is in use.", ['office_id' => $officeId, 'usage_count' => $usageCount]);
                $this->setSessionMessage("این مرکز ری‌گیری در {$usageCount} معامله استفاده شده و قابل حذف نیست.", 'warning', 'assay_office_error');
            } else {
                // Proceed with deletion
                // Assumes AssayOfficeRepository::delete returns true on success, false if not found
                $isDeleted = $this->assayOfficeRepository->delete($officeId);

                if ($isDeleted) {
                    $this->logger->info("Assay office deleted successfully.", ['office_id' => $officeId]);
                    $this->setSessionMessage('مرکز ری‌گیری با موفقیت حذف شد.', 'success', 'assay_office_success');
                } else {
                    // Row not found or not deleted for other reasons
                    $this->logger->warning("Assay office deletion failed (not found or no rows affected).", ['office_id' => $officeId]);
                    $this->setSessionMessage('مرکز ری‌گیری یافت نشد یا حذف آن با مشکل مواجه شد.', 'warning', 'assay_office_error');
                }
            }
        } catch (Throwable $e) {
            $this->logger->error("Error deleting assay office.", ['office_id' => $officeId, 'exception' => $e]);
            // Check for foreign key constraint errors if applicable
            $errorMessage = "خطا در حذف مرکز ری‌گیری.";
            if ($this->config['app']['debug']) {
                 $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
            }
             $this->setSessionMessage($errorMessage, 'danger', 'assay_office_error');
        }

        // Redirect back to the list page regardless of outcome
        $this->redirect('/app/assay-offices');
    }

    /**
     * Returns list of assay offices in JSON format.
     * Route: /app/assay-offices/list (GET)
     */
    public function getList(): void {
        $this->requireLogin();
        
        try {
            $offices = $this->assayOfficeRepository->getAll();
            
            // تبدیل به فرمت مورد نیاز برای select
            $response = [
                'success' => true,
                'data' => array_map(function($office) {
                    return [
                        'id' => (int)$office['id'],
                        'name' => Helper::escapeHtml($office['name'])
                    ];
                }, $offices)
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
            
        } catch (Throwable $e) {
            $this->logger->error("Error fetching assay offices list for JSON response.", ['exception' => $e]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'خطا در دریافت لیست مراکز ری‌گیری'
            ]);
        }
    }

} // End AssayOfficeController class