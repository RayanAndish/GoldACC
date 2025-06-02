<?php

namespace App\Controllers;

use JetBrains\PhpStorm\NoReturn;
use PDO;
use Monolog\Logger;
use Throwable;
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;
use App\Services\BackupService;
use App\Services\UpdateService;
use App\Services\MonitoringService;
use App\Services\DatabaseService;
use App\Repositories\SettingsRepository;
use App\Utils\Helper;

class SystemController extends AbstractController {

    private BackupService $backupService;
    private UpdateService $updateService;
    private MonitoringService $monitoringService;
    private DatabaseService $databaseService;
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
            'backupService' => BackupService::class,
            'updateService' => UpdateService::class,
            'databaseService' => DatabaseService::class,
            'monitoringService' => MonitoringService::class,
            'settingsRepository' => SettingsRepository::class,
        ];
        foreach ($required as $prop => $class) {
            if (!isset($services[$prop]) || !$services[$prop] instanceof $class) {
                 throw new \Exception("{$class} not found or invalid for SystemController.");
            }
            $this->$prop = $services[$prop];
        }
        $this->logger->debug("SystemController initialized.");
    }

    public function index(string $section = 'overview'): void {
        $this->requireLogin();
        $this->requireAdmin();

        $pageTitle = "مدیریت سیستم";
        $viewFile = 'system/index';
        $viewData = ['page_title' => $pageTitle, 'active_section' => $section];
        $loadingError = null;
        $successMessage = $this->getFlashMessage('system_success');
        $errorMessage = $this->getFlashMessage('system_error');
        $resetCodeMessage = $this->getFlashMessage('reset_code_generated');

        $this->logger->debug("Loading system section.", ['section' => $section]);

        try {
            switch ($section) {
                case 'overview':
                    $pageTitle .= " - نمای کلی";
                    $viewData['current_version'] = $this->updateService->getCurrentVersion();
                    $viewData['backups'] = $this->backupService->listBackups();
                    $viewData['update_info'] = $this->updateService->checkForUpdate();
                    $viewData['update_history'] = $this->updateService->getUpdateHistoryPaginated(5, 0);
                    break;
                case 'backup':
                    $pageTitle .= " - پشتیبان‌گیری";
                    $viewFile = 'system/backups';
                    $viewData['backups'] = $this->backupService->listBackups();
                    break;
                case 'update':
                    $pageTitle .= " - به‌روزرسانی";
                    $viewFile = 'system/update';
                    $viewData['current_version'] = $this->updateService->getCurrentVersion();
                    $itemsPerPage = (int)($this->config['app']['items_per_page'] ?? 10);
                    $currentPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
                    $offset = ($currentPage - 1) * $itemsPerPage;
                    $totalRecords = $this->updateService->countUpdateHistory();
                    $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $itemsPerPage) : 1;
                    $currentPage = max(1, min($currentPage, $totalPages));
                    $viewData['update_history'] = $this->updateService->getUpdateHistoryPaginated($itemsPerPage, $offset);
                    $viewData['pagination'] = Helper::generatePaginationData($currentPage, $totalPages, $totalRecords, $itemsPerPage);
                    break;
                case 'maintenance':
                     $pageTitle .= " - نگهداری";
                     $viewFile = 'system/maintenance';
                     $viewData['reset_code_hash_exists'] = !empty($this->settingsRepository->get('system_reset_code_hash'));
                     break;
                default:
                    $this->logger->warning("Invalid system section requested.", ['section' => $section]);
                    $this->redirect('/app/system/overview');
                    break;
            }
        } catch (Throwable $e) {
            $this->logger->error("Error loading system section data.", ['section' => $section, 'exception' => $e]);
            $loadingError = "خطا در بارگذاری اطلاعات بخش '" . htmlspecialchars($section) . "'.";
            if ($this->config['app']['debug']) { $loadingError .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
            $viewData['loading_error'] = $loadingError;
        }

        $viewData['error_msg'] = $errorMessage['text'] ?? ($loadingError ?: null);
        $viewData['success_msg'] = $successMessage['text'] ?? null;
        $viewData['reset_code_message'] = $resetCodeMessage['text'] ?? null;

        $this->render($viewFile, $viewData);
    }
    
    // FIX: The private jsonResponse method is removed. The protected method from AbstractController will be used.

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
    
    public function handleBackupAction(): void {
        $this->requireLogin();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/system/overview'); }
        $action = $_POST['action'] ?? null;
        $fileName = $_POST['selected_backup'] ?? null;
        if (empty($fileName) || empty($action) || !in_array($action, ['restore', 'delete'])) {
            $this->setSessionMessage('عملیات یا فایل پشتیبان نامعتبر است.', 'warning', 'system_error');
            $this->redirect('/app/system/overview');
        }
        $this->logger->info("Backup action initiated.", ['action' => $action, 'filename' => $fileName]);
        try {
            if ($action === 'restore') {
                $this->backupService->restoreBackup($fileName);
                $this->setSessionMessage("پشتیبان '{$fileName}' با موفقیت بازگردانی شد.", 'success', 'system_success');
            } else { // delete
                $this->backupService->deleteBackup($fileName);
                $this->setSessionMessage("فایل پشتیبان '{$fileName}' حذف شد.", 'success', 'system_success');
            }
        } catch (Throwable $e) {
            $this->logger->error("Error during backup action.", ['action' => $action, 'filename' => $fileName, 'exception' => $e]);
            $this->setSessionMessage("خطا در انجام عملیات: " . $e->getMessage(), 'danger', 'system_error');
        }
        $this->redirect('/app/system/overview');
    }

    public function optimizeDatabaseAction(): void {
        $this->requireLogin();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/system/maintenance'); }
        $this->logger->info("Database optimization action initiated.");
        try {
            $results = $this->databaseService->optimizeTables();
            $success = true; $messages = [];
            foreach ($results as $table => $resultArray) {
                 foreach ($resultArray as $result) {
                     if (($result['Msg_type'] ?? '') === 'Error') $success = false;
                     $messages[] = Helper::escapeHtml($table . ": " . ($result['Msg_text'] ?? 'Unknown status'));
                 }
            }
            $this->setSessionMessage("بهینه‌سازی انجام شد.<br>" . implode("<br>", $messages), $success ? 'success' : 'warning', 'system_success');
        } catch (Throwable $e) {
            $this->logger->error("Database optimization action failed.", ['exception' => $e]);
            $this->setSessionMessage('خطا در اجرای بهینه‌سازی دیتابیس: ' . Helper::escapeHtml($e->getMessage()), 'danger', 'system_error');
        }
        $this->redirect('/app/system/maintenance');
    }

    public function checkUpdateAction(): void {
        $this->requireLogin(); $this->requireAdmin();
        $this->logger->info("Update check action initiated.");
        $response = ['success' => false, 'message' => 'Check failed', 'update_info' => null];
        try {
             $updateInfo = $this->updateService->checkForUpdate();
             $response['success'] = true;
             $response['message'] = $updateInfo ? 'آپدیت جدید یافت شد.' : 'سیستم شما به‌روز است.';
             $response['update_info'] = $updateInfo;
        } catch (Throwable $e) {
             $this->logger->error("Update check action failed.", ['exception' => $e]);
             $response['message'] = 'خطا در ارتباط با سرور به‌روزرسانی.';
             http_response_code(500);
        }
        $this->jsonResponse($response);
    }
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
 }