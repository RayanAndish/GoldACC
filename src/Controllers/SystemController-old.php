<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use JetBrains\PhpStorm\NoReturn;
use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Required Services for system operations
use App\Services\BackupService;
use App\Services\UpdateService;
use App\Services\MonitoringService; // Optional, depending on features
use App\Services\DatabaseService; // For Optimize DB

// Utilities
use App\Utils\Helper;

/**
 * SystemController manages system-level operations like backups, updates, and optimization.
 * Access restricted to Admins. Inherits from AbstractController.
 */
class SystemController extends AbstractController {

    // Injected Services
    private BackupService $backupService;
    private UpdateService $updateService;
    private MonitoringService $monitoringService; // Make optional?
    private DatabaseService $databaseService;


    /**
     * Constructor. Injects dependencies.
     *
     * @param PDO $db
     * @param Logger $logger
     * @param array $config
     * @param ViewRenderer $viewRenderer
     * @param array $services Array of application services.
     * @throws \Exception If required services are missing.
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services // Receive the $services array
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services); // Pass all to parent

        // Retrieve specific services
        $required = [
            'backupService' => BackupService::class,
            'updateService' => UpdateService::class,
            'databaseService' => DatabaseService::class,
             // MonitoringService might be optional depending on use case
            'monitoringService' => MonitoringService::class,

        ];
        foreach ($required as $prop => $class) {
            if (!isset($services[$prop]) || !$services[$prop] instanceof $class) {
                 throw new \Exception("{$class} not found or invalid for SystemController.");
            }
            $this->$prop = $services[$prop];
        }
        $this->logger->debug("SystemController initialized.");
    }

    /**
     * Displays the main system management page or a specific section.
     * Route: /app/system/overview (GET)
     * Route: /app/system/{section} (GET) - Using optional parameter or separate routes
     *
     * @param string $section The section to display ('overview', 'backup', 'update', 'maintenance'). Defaults to 'overview'.
     */
    public function index(string $section = 'overview'): void {
        $this->requireLogin();
        $this->requireAdmin();

        $pageTitle = "مدیریت سیستم";
        $viewFile = 'system/index'; // Default view file
        $viewData = ['page_title' => $pageTitle, 'active_section' => $section];
        $loadingError = null;
        $successMessage = $this->getFlashMessage('system_success'); // General success
        $errorMessage = $this->getFlashMessage('system_error');     // General error
        $resetCodeMessage = $this->getFlashMessage('reset_code_generated'); // Specific message for reset code

        $this->logger->debug("Loading system section.", ['section' => $section]);

        try {
            switch ($section) {
                case 'overview':
                    $pageTitle .= " - نمای کلی";
                    $viewData['current_version'] = $this->updateService->getCurrentVersion();
                    $viewData['backups'] = $this->backupService->listBackups();
                    $viewData['update_info'] = $this->updateService->checkForUpdate();
                    $viewData['update_history'] = $this->updateService->getUpdateHistoryPaginated(5, 0); // فقط ۵ مورد آخر
                    break;

                case 'backup':
                    $pageTitle .= " - پشتیبان‌گیری";
                    $viewFile = 'system/backups'; // Specific view for backup section
                    // Fetch list of existing backups
                    $viewData['backup_files'] = $this->backupService->listBackups();
                    break;

                case 'update':
                    $pageTitle .= " - به‌روزرسانی";
                    $viewFile = 'system/update'; // Specific view for update section
                    $viewData['current_version'] = $this->updateService->getCurrentVersion();
                    // صفحه‌بندی تاریخچه به‌روزرسانی
                    $itemsPerPage = (int)($this->config['app']['items_per_page'] ?? 10);
                    $currentPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
                    $offset = ($currentPage - 1) * $itemsPerPage;
                    $totalRecords = $this->updateService->countUpdateHistory();
                    $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $itemsPerPage) : 1;
                    $currentPage = max(1, min($currentPage, $totalPages));
                    $viewData['update_history'] = $this->updateService->getUpdateHistoryPaginated($itemsPerPage, $offset);
                    $viewData['update_pagination'] = [
                        'totalRecords' => $totalRecords,
                        'totalPages' => $totalPages,
                        'currentPage' => $currentPage,
                        'limit' => $itemsPerPage
                    ];
                    break;

                case 'maintenance':
                     $pageTitle .= " - نگهداری";
                     $viewFile = 'system/maintenance'; // Specific view for maintenance
                     // Could show DB size, logs size, clear cache options etc.
                     // Check if a reset code hash already exists to inform the view
                     $viewData['reset_code_hash_exists'] = !empty($this->settingsRepository->get('system_reset_code_hash'));
                     break;

                default:
                    $this->logger->warning("Invalid system section requested.", ['section' => $section]);
                    $this->setSessionMessage('بخش مدیریت سیستم نامعتبر است.', 'warning', 'system_error');
                    $this->redirect('/app/system/overview');
                    // exit; // Redirect includes exit
                    break;
            }
        } catch (Throwable $e) {
            $this->logger->error("Error loading system section data.", ['section' => $section, 'exception' => $e]);
            $loadingError = "خطا در بارگذاری اطلاعات بخش '" . htmlspecialchars($section) . "'.";
            if ($this->config['app']['debug']) { $loadingError .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
            $viewData['loading_error'] = $loadingError;
        }

        // اضافه کردن پیام‌ها به viewData
        $viewData['error_msg'] = $errorMessage ? $errorMessage['text'] : ($loadingError ?: null);
        $viewData['success_msg'] = $successMessage ? $successMessage['text'] : null;
        $viewData['reset_code_message'] = $resetCodeMessage ? $resetCodeMessage['text'] : null;

        $this->render($viewFile, $viewData);
    }

    // --- Action Methods ---

    /**
     * Action to run a manual database backup.
     * Route: /app/system/backup/run (POST)
     */
    public function runBackupAction(): void {
        $this->requireLogin();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/system/overview'); }
        $this->logger->info("Manual backup action initiated.");
        try {
            $file = $this->backupService->createFullBackup();
            $this->setSessionMessage('گرفتن نسخه پشتیبان با موفقیت انجام شد.', 'success', 'system_success');
            $this->logger->info("Backup created successfully.", ['file' => $file]);
        } catch (\Throwable $e) {
            $this->setSessionMessage('خطا در ایجاد نسخه پشتیبان: ' . $e->getMessage(), 'danger', 'system_error');
            $this->logger->error("Backup failed.", ['exception' => $e]);
        }
        $this->redirect('/app/system/overview');
    }

     /**
     * Action to delete a specific backup file.
     * Route: /app/system/backup/delete (POST) - Expects 'filename' in POST
     */
    public function deleteBackupAction(): void {
        $this->requireLogin(); $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/system/backup'); }
        // TODO: CSRF validation

        $fileName = trim($_POST['filename'] ?? '');
        if (empty($fileName)) {
            $this->setSessionMessage('نام فایل پشتیبان برای حذف مشخص نشده است.', 'warning', 'system_error');
            $this->redirect('/app/system/backup');
        }
        $this->logger->info("Backup delete action initiated.", ['filename' => $fileName]);

        try {
            $isDeleted = $this->backupService->deleteBackup($fileName);
            if ($isDeleted) {
                 Helper::logActivity($this->db, "Backup file deleted: " . $fileName, 'BACKUP_DELETE', 'INFO');
                 $this->setSessionMessage("فایل پشتیبان '{$fileName}' حذف شد.", 'success', 'system_success');
            } else {
                 // Backup service logs warning if file not found
                 $this->setSessionMessage("فایل پشتیبان '{$fileName}' یافت نشد یا حذف نشد.", 'warning', 'system_error');
            }
        } catch (Throwable $e) {
             $this->logger->error("Error deleting backup file.", ['filename' => $fileName, 'exception' => $e]);
             $errorMessage = "خطا در حذف فایل پشتیبان '{$fileName}'.";
             if ($this->config['app']['debug']) { $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
             $this->setSessionMessage($errorMessage, 'danger', 'system_error');
        }
         $this->redirect('/app/system/backup');
    }

    /**
     * Action to optimize database tables.
     * Route: /app/system/optimize-db (POST)
     */
    public function optimizeDatabaseAction(): void {
        $this->requireLogin();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/system/maintenance'); }
        // TODO: CSRF validation

        $this->logger->info("Database optimization action initiated.");
        try {
            // Use DatabaseService
            $results = $this->databaseService->optimizeTables();
            // Process results to create a user-friendly message (optional)
            $success = true; $messages = [];
            foreach ($results as $table => $resultArray) {
                 foreach ($resultArray as $result) {
                     if (($result['Msg_type'] ?? '') === 'Error') $success = false;
                     $messages[] = Helper::escapeHtml($table . ": " . ($result['Msg_text'] ?? 'Unknown status'));
                 }
            }
            $finalMessage = "بهینه‌سازی انجام شد.<br>" . implode("<br>", $messages);
            Helper::logActivity($this->db, "Database optimization finished.", $success ? 'SUCCESS' : 'WARNING');
            $this->setSessionMessage($finalMessage, $success ? 'success' : 'warning', 'system_success'); // Use success key even for partial success

        } catch (Throwable $e) {
            $this->logger->error("Database optimization action failed.", ['exception' => $e]);
            $errorMessage = 'خطا در اجرای بهینه‌سازی دیتابیس.';
            if ($this->config['app']['debug']) { $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
            $this->setSessionMessage($errorMessage, 'danger', 'system_error');
        }
        $this->redirect('/app/system/maintenance');
    }


    /**
     * Action to check for updates via API (likely called via AJAX).
     * Route: /app/system/update/check (POST or GET?)
     */
     public function checkUpdateAction(): void {
          $this->requireLogin(); $this->requireAdmin();
          // Consider using GET if no parameters needed, POST if sending data (like current version explicitly)
          $this->logger->info("Update check action initiated.");
          $response = ['success' => false, 'message' => 'Check failed', 'update_info' => null];
          try {
               $updateInfo = $this->updateService->checkForUpdate();
               $response['success'] = true;
               $response['message'] = $updateInfo ? 'آپدیت جدید یافت شد.' : 'سیستم شما به‌روز است.';
               $response['update_info'] = $updateInfo; // Contains details if update available
          } catch (Throwable $e) {
               $this->logger->error("Update check action failed.", ['exception' => $e]);
               $response['message'] = 'خطا در ارتباط با سرور به‌روزرسانی.';
                if ($this->config['app']['debug']) { $response['message'] .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
                http_response_code(500); // Indicate server error
          }
          $this->jsonResponse($response); // Send JSON response
     }

    /**
     * Action to apply an update (Placeholder).
     * Route: /app/system/update/apply (POST) - Expects update details (e.g., version) in POST
     */
    public function applyUpdateAction(): void {
        $this->requireLogin(); $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/system/update'); }
        // TODO: CSRF validation

        $this->logger->critical("Apply update action initiated (PLACEHOLDER - DANGEROUS).");

        // Retrieve update details from POST
        $latestVersion = trim($_POST['latest_version'] ?? '');
        $downloadUrl = trim($_POST['download_url'] ?? '');
        $checksum = trim($_POST['checksum'] ?? ''); // Optional

        // Basic validation
        if (empty($latestVersion) || empty($downloadUrl)) {
            $this->logger->error("Apply update action failed: Missing required parameters (version or download URL).");
            $this->setSessionMessage('اطلاعات لازم برای شروع به‌روزرسانی ناقص است.', 'danger', 'system_error');
            $this->redirect('/app/system/update');
            return; // Stop execution
        }

        // Construct the update info array for the service
        $updateInfo = [
            'latest_version' => $latestVersion,
            'download_url' => $downloadUrl,
        ];
        if (!empty($checksum)) {
            $updateInfo['checksum'] = $checksum;
        }
        // You might need to add other info here if your applyUpdate expects more

        try {
             // ** WARNING: Placeholder - Real implementation needed **
             $success = $this->updateService->applyUpdate($updateInfo);
             if ($success) {
                  Helper::logActivity($this->db, "System update applied: " . $latestVersion, 'UPDATE_APPLY', 'CRITICAL');
                  $this->setSessionMessage("سیستم با موفقیت به نسخه {$latestVersion} به‌روز شد.", 'success', 'system_success');
             } else {
                  // The service should throw an exception on failure, so this else might not be reached often.
                   $this->setSessionMessage("اعمال به‌روزرسانی ناموفق بود (اعلان Placeholder).", 'danger', 'system_error');
             }
        } catch (Throwable $e) {
             $this->logger->critical("Failed to apply update.", ['version' => $latestVersion, 'exception' => $e]);
             $errorMessage = "خطای بحرانی در هنگام اعمال به‌روزرسانی.";
             if ($this->config['app']['debug']) { $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
             $this->setSessionMessage($errorMessage, 'danger', 'system_error');
        }
        $this->redirect('/app/system/update');
    }


     /** Helper to send JSON response and exit */
     #[NoReturn] 
     protected function jsonResponse(array $data, int $statusCode = 200): void {
         if (!headers_sent()) {
             http_response_code($statusCode);
             header('Content-Type: application/json; charset=UTF-8');
         }
         echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
         exit;
     }

    /**
     * نمایش لیست بکاپ‌ها
     */
    public function backups()
    {
        $backups = $this->backupService->listBackups();
        $this->render('system/backups', ['backups' => $backups]);
    }

    /**
     * ایجاد بکاپ جدید
     */
    public function createBackup()
    {
        $file = $this->backupService->createFullBackup();
        $this->redirect('/app/system/backups');
    }

    /**
     * دانلود بکاپ
     */
    #[NoReturn] 
    public function downloadBackup($filename)
    {
        $backupDir = realpath(__DIR__ . '/../../backups');
        $file = $backupDir . '/' . basename($filename);
        if (!file_exists($file)) {
            http_response_code(404);
            echo 'Backup not found.';
            exit;
        }
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    /**
     * نمایش گزارش یک به‌روزرسانی خاص
     * Route: /app/system/update/report/{id}
     */
    public function updateReportAction(int $id): void {
        $this->requireLogin();
        $this->requireAdmin();
        $report = $this->updateService->getUpdateHistoryPaginated(1, $id-1); // یا متد مخصوص getById اگر در سرویس اضافه شود
        $row = $report[0] ?? null;
        if (!$row) {
            $this->render('system/update_report', [
                'page_title' => 'گزارش به‌روزرسانی',
                'error_msg' => 'گزارش مورد نظر یافت نشد.'
            ]);
            return;
        }
        $this->render('system/update_report', [
            'page_title' => 'گزارش به‌روزرسانی نسخه ' . htmlspecialchars($row['version']),
            'version' => $row['version'],
            'update_time' => $row['update_time'],
            'status' => $row['status'],
            'log' => $row['log'],
        ]);
    }

    /**
     * Handles backup actions (restore/delete).
     * Route: /app/system/backup/action (POST)
     */
    public function handleBackupAction(): void {
        $this->requireLogin();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/system/overview'); }
        // TODO: CSRF validation

        $action = $_POST['action'] ?? null;
        $fileName = $_POST['selected_backup'] ?? null;

        if (empty($fileName)) {
            $this->setSessionMessage('لطفاً یک فایل پشتیبان را انتخاب کنید.', 'warning', 'system_error');
            $this->redirect('/app/system/overview');
        }
        if (empty($action) || !in_array($action, ['restore', 'delete'])) {
            $this->setSessionMessage('عملیات نامعتبر انتخاب شده است.', 'warning', 'system_error');
            $this->redirect('/app/system/overview');
        }

        $this->logger->info("Backup action initiated.", ['action' => $action, 'filename' => $fileName]);

        try {
            if ($action === 'restore') {
                // فراخوانی سرویس بازگردانی (که اکنون پیاده‌سازی شده)
                $success = $this->backupService->restoreBackup($fileName);
                if ($success) {
                    // توجه: لاگ کردن با سطح CRITICAL مناسب است چون عملیات بازیابی انجام شده.
                    Helper::logActivity($this->db, "Backup restored: " . $fileName, 'BACKUP_RESTORE', 'CRITICAL');
                    $this->setSessionMessage("پشتیبان '{$fileName}' با موفقیت بازگردانی شد. (فقط دیتابیس)", 'success', 'system_success');
                } else {
                    // خطا باید توسط سرویس throw شده باشد و در catch پایین مدیریت شود،
                    // اما برای اطمینان یک پیام خطا اینجا هم قرار می‌دهیم.
                    $this->setSessionMessage("بازگردانی پشتیبان '{$fileName}' ناموفق بود.", 'danger', 'system_error');
                }
            } elseif ($action === 'delete') {
                $isDeleted = $this->backupService->deleteBackup($fileName);
                if ($isDeleted) {
                    Helper::logActivity($this->db, "Backup file deleted: " . $fileName, 'BACKUP_DELETE', 'INFO');
                    $this->setSessionMessage("فایل پشتیبان '{$fileName}' حذف شد.", 'success', 'system_success');
                } else {
                    $this->setSessionMessage("فایل پشتیبان '{$fileName}' یافت نشد یا حذف نشد.", 'warning', 'system_error');
                }
            }
        } catch (Throwable $e) {
            $this->logger->error("Error during backup action.", ['action' => $action, 'filename' => $fileName, 'exception' => $e]);
            $errorMessage = "خطا در انجام عملیات '".($action == 'restore' ? 'بازگردانی' : 'حذف')."' برای فایل '{$fileName}'.";
            if ($this->config['app']['debug']) { $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
            $this->setSessionMessage($errorMessage, 'danger', 'system_error');
        }

        $this->redirect('/app/system/overview');
    }

    /**
     * Generates a new system reset code, stores its hash, and displays the code once.
     * Route: /app/system/maintenance/generate-reset-code (POST)
     */
    public function generateResetCodeAction(): void {
        $this->requireLogin();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/system/maintenance'); }
        // TODO: Add CSRF validation here

        $this->logger->warning("Admin requested generation of a new system reset code.");

        try {
            // Generate a strong random code (e.g., 32 chars, alphanumeric)
            $newCode = bin2hex(random_bytes(16)); // 32 hex characters

            // Hash the code securely
            $hashedCode = password_hash($newCode, PASSWORD_DEFAULT);

            if ($hashedCode === false) {
                throw new Exception("Failed to hash the new reset code.");
            }

            // Save the HASHED code to settings
            $saved = $this->settingsRepository->set('system_reset_code_hash', $hashedCode);

            if (!$saved) {
                throw new Exception("Failed to save the new reset code hash to settings.");
            }

            // IMPORTANT: Store the PLAINTEXT code in a flash message to display ONCE
            $message = "کد بازنشانی جدید سیستم ایجاد شد. این کد فقط یک بار نمایش داده می‌شود. لطفاً آن را در مکانی امن یادداشت کنید و هرگز با دیگران به اشتراک نگذارید.\n\nکد شما: " . $newCode;
            $this->setSessionMessage($message, 'warning', 'reset_code_generated'); // Use a specific key

            Helper::logActivity($this->db, "Admin generated a new system reset code.", 'SECURITY', 'WARNING');
            $this->logger->info("New system reset code generated and hash saved.");

        } catch (Throwable $e) {
            $this->logger->error("Failed to generate system reset code.", ['exception' => $e]);
            $this->setSessionMessage("خطا در ایجاد کد بازنشانی سیستم: " . $e->getMessage(), 'danger', 'system_error');
        }

        $this->redirect('/app/system/maintenance');
    }

    /**
     * Displays the final confirmation page for system reset.
     * Route: /app/system/reset/confirm (GET)
     */
    public function confirmResetAction(): void {
        $this->requireLogin();
        $this->requireAdmin();

        $token = $_GET['token'] ?? null;
        $sessionData = $_SESSION['reset_confirmation'] ?? null;
        $userId = $_SESSION['user']['id'] ?? 0;

        $isValid = false;
        $errorMessage = null;

        if (empty($token)) {
            $errorMessage = 'توکن تأیید بازنشانی یافت نشد.';
        } elseif (empty($sessionData) || !is_array($sessionData)) {
            $errorMessage = 'اطلاعات تأیید بازنشانی در سشن یافت نشد یا نامعتبر است.';
        } elseif (!hash_equals($sessionData['token'] ?? '', $token)) {
            $errorMessage = 'توکن تأیید نامعتبر است.';
        } elseif (($sessionData['user_id'] ?? -1) !== $userId) {
             $errorMessage = 'توکن تأیید متعلق به کاربر دیگری است.';
        } elseif (time() > ($sessionData['expires'] ?? 0)) {
            $errorMessage = 'توکن تأیید منقضی شده است. لطفاً فرآیند را دوباره آغاز کنید.';
            // Clear expired token from session
            unset($_SESSION['reset_confirmation']);
        } else {
            $isValid = true;
        }

        if (!$isValid) {
            $this->logger->warning("Invalid attempt to access reset confirmation page.", ['reason' => $errorMessage, 'token_provided' => $token]);
        }

        $this->render('system/reset_confirm', [
            'page_title' => 'تأیید نهایی بازنشانی سیستم',
            'is_valid' => $isValid,
            'error_message' => $errorMessage,
            'confirmation_token' => $token // Pass the token to the view for the form
        ]);
    }

    /**
     * Executes the final system data purge after confirmation.
     * Route: /app/system/reset/execute (POST)
     */
    public function executeResetAction(): void {
        $this->requireLogin();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/dashboard'); }
        // TODO: CSRF validation

        $token = $_POST['confirmation_token'] ?? null;
        $sessionData = $_SESSION['reset_confirmation'] ?? null;
        $userId = $_SESSION['user']['id'] ?? 0;

        $this->logger->critical("!!! SYSTEM RESET EXECUTION ATTEMPT !!!", ['user_id' => $userId]);

        // --- Validate Token Again ---
        $isValid = false;
        $errorMessage = null;

        if (empty($token)) {
            $errorMessage = 'توکن تأیید بازنشانی ارسال نشده است.';
        } elseif (empty($sessionData) || !is_array($sessionData)) {
            $errorMessage = 'اطلاعات تأیید بازنشانی در سشن یافت نشد یا نامعتبر است.';
        } elseif (!hash_equals($sessionData['token'] ?? '', $token)) {
            $errorMessage = 'توکن تأیید نامعتبر است.';
        } elseif (($sessionData['user_id'] ?? -1) !== $userId) {
             $errorMessage = 'توکن تأیید متعلق به کاربر دیگری است.';
        } elseif (time() > ($sessionData['expires'] ?? 0)) {
            $errorMessage = 'توکن تأیید منقضی شده است.';
        } else {
            $isValid = true;
        }

        if (!$isValid) {
            $this->logger->error("System reset execution failed: Invalid or expired token.", ['reason' => $errorMessage]);
             unset($_SESSION['reset_confirmation']); // Clear invalid/expired token
             $this->setSessionMessage("خطا در اجرای بازنشانی: " . $errorMessage, 'danger', 'system_error');
             $this->redirect('/app/dashboard'); // Redirect away from confirmation page
            return;
        }

        // --- Token is valid, proceed with deletion --- 

        // IMPORTANT: Invalidate the token immediately
        unset($_SESSION['reset_confirmation']);

        try {
            $this->logger->critical("Executing data purge...");

            // Call the service method to truncate tables
            $success = $this->databaseService->purgeApplicationData();

            if ($success) {
                $this->logger->critical("!!! SYSTEM DATA PURGED SUCCESSFULLY !!!");
                 Helper::logActivity($this->db, "System data purged successfully by admin.", 'SYSTEM_RESET', 'CRITICAL');
                 $this->setSessionMessage("تمام داده‌های سامانه با موفقیت حذف شدند.", 'success', 'system_success');
                 // Redirect to dashboard or login?
                 $this->redirect('/app/dashboard');
            } else {
                // This case might mean the service method returned false without an exception
                 $this->logger->error("Data purge method returned false, but no exception was thrown.");
                 $this->setSessionMessage("عملیات حذف داده‌ها به طور کامل انجام نشد. لطفاً لاگ‌ها را بررسی کنید.", 'warning', 'system_error');
                 $this->redirect('/app/system/maintenance');
            }

        } catch (Throwable $e) {
            $this->logger->emergency("!!! CRITICAL FAILURE DURING DATA PURGE !!!", ['exception' => $e]);
             Helper::logActivity($this->db, "Critical failure during system data purge.", 'SYSTEM_RESET', 'EMERGENCY');
             $this->setSessionMessage("خطای بسیار جدی در هنگام حذف داده‌ها رخ داد: " . $e->getMessage(), 'danger', 'system_error');
             $this->redirect('/app/system/maintenance');
        }
    }

} // End SystemController class
