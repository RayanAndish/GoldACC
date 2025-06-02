<?php

namespace App\Controllers;

use PDO;
use Monolog\Logger;
use Throwable;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Dependencies
use App\Services\LicenseService;
use App\Services\SecurityService;
use App\Utils\Helper;
use App\Services\ApiClient;

class LicenseController extends AbstractController {

    // LicenseService از طریق سازنده AbstractController و آرایه $services در دسترس است: $this->licenseService

    private SecurityService $securityService;
    protected Logger $logger;
    protected LicenseService $licenseService;
    protected ApiClient $apiClient;

    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        
        // اطمینان از وجود سرویس‌های مورد نیاز
        if (!isset($this->services['securityService']) || !$this->services['securityService'] instanceof SecurityService) {
            throw new Exception('SecurityService not found in services array for LicenseController.');
        }
        if (!isset($this->services['licenseService']) || !$this->services['licenseService'] instanceof LicenseService) {
            throw new Exception('LicenseService not found in services array for LicenseController.');
        }
        if (!isset($this->services['apiClient']) || !$this->services['apiClient'] instanceof ApiClient) {
            throw new Exception('ApiClient not found in services array for LicenseController.');
        }

        $this->securityService = $this->services['securityService'];
        $this->licenseService = $this->services['licenseService'];
        $this->apiClient = $this->services['apiClient'];
        
        $this->logger->debug("LicenseController initialized.");
    }

    public function showActivateForm(): void {
        // بررسی وضعیت لایسنس
        $licenseStatus = $this->licenseService->checkLicense();
        if ($licenseStatus['valid']) {
            // اگر لایسنس معتبر است، به صفحه اصلی هدایت می‌کنیم
            $this->redirect('app/dashboard');
            return;
        }

        try {
            // بررسی زمان ایجاد کد درخواست قبلی
            $lastRequestTime = $_SESSION['activation_request_time'] ?? 0;
            $currentTime = time();
            $timeDiff = $currentTime - $lastRequestTime;
            $timeRemaining = max(0, 300 - $timeDiff); // 5 دقیقه = 300 ثانیه

            // اگر کمتر از 5 دقیقه از آخرین درخواست گذشته باشد، از کد قبلی استفاده می‌کنیم
            if ($timeDiff < 300 && isset($_SESSION['activation_data'])) {
                $activationData = $_SESSION['activation_data'];
                $viewData = [
                    'page_title' => 'فعال‌سازی لایسنس',
                    'appName' => $this->config['app']['name'],
                    'baseUrl' => $this->config['app']['base_url'],
                    'form_action' => $this->config['app']['base_url'] . '/activate',
                    'license_status' => $licenseStatus,
                    'error' => null,
                    'success' => null,
                    'domain' => $activationData['domain'] ?? '',
                    'hardware_id' => $activationData['hardware_id'] ?? '',
                    'request_code' => $activationData['request_code'] ?? '',
                    'activation_data' => $activationData,
                    'time_remaining' => $timeRemaining,
                    'last_request_time' => $lastRequestTime
                ];
                require_once __DIR__ . '/../views/license/activate.php';
                return;
            }

            // تولید اطلاعات اولیه برای فعال‌سازی
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $hostName = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? php_uname('n');
            $domain = $protocol . $hostName;
            $ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? '127.0.0.1';
            $rayId = $_SESSION['ray_id'] ?? uniqid('ray_', true);
            $_SESSION['ray_id'] = $rayId;

            // مرحله 1: ارسال domain و ip به سرور برای دریافت salt و nonce
            $initPayload = [
                'domain' => $domain,
                'ip' => $ip,
                'ray_id' => $rayId
            ];

            $this->logger->debug('Sending initial activation request.', [
                'payload' => $initPayload
            ]);

            // استفاده از endpoint صحیح سرور
            $initResponse = $this->apiClient->sendSecurePostRequest('license/initiate-activation', $initPayload);
            
            if (!$initResponse || !isset($initResponse['status']) || $initResponse['status'] !== 'success') {
                throw new \Exception('Failed to initiate activation: ' . ($initResponse['message'] ?? 'Unknown error'));
            }

            // مرحله 2: دریافت salt و nonce از سرور
            $hardwareIdSalt = $initResponse['hardware_id_salt'] ?? $initResponse['salt'] ?? null;
            $activationSalt = $initResponse['activation_salt'] ?? $initResponse['salt'] ?? null;
            $serverNonce = $initResponse['server_nonce'] ?? null;

            if (!$hardwareIdSalt || !$serverNonce) {
                throw new \Exception('Invalid server response: missing salt or nonce');
            }

            // مرحله 3: تولید hardware_id با استفاده از salt و nonce
            $hardwareId = $this->securityService->generateHardwareId($hardwareIdSalt, $serverNonce);
            
            // ارسال hardware_id به سرور
            $hwPayload = [
                'hardware_id' => $hardwareId,
                'domain' => $domain,
                'ip' => $ip,
                'ray_id' => $rayId,
                'server_nonce' => $serverNonce,
                'activation_salt' => $activationSalt
            ];

            $this->logger->debug('Sending hardware ID to server.', [
                'payload' => array_merge($hwPayload, [
                    'hardware_id' => substr($hardwareId, 0, 10) . '...'
                ])
            ]);

            // استفاده از endpoint صحیح سرور
            $hwResponse = $this->apiClient->sendSecurePostRequest('license/complete-activation', $hwPayload);

            if (!$hwResponse || !isset($hwResponse['status']) || $hwResponse['status'] !== 'success') {
                throw new \Exception('Failed to verify hardware: ' . ($hwResponse['message'] ?? 'Unknown error'));
            }

            // مرحله 4: دریافت request_code از سرور
            $requestCode = $hwResponse['request_code'] ?? null;
            if (!$requestCode) {
                throw new \Exception('Invalid server response: missing request code');
            }

            // ذخیره اطلاعات در سشن برای استفاده در مراحل بعدی
            $_SESSION['activation_data'] = [
                'hardware_id' => $hardwareId,
                'domain' => $domain,
                'ip' => $ip,
                'server_nonce' => $serverNonce,
                'request_code' => $requestCode,
                'hardware_id_salt' => $hardwareIdSalt,
                'activation_salt' => $activationSalt
            ];

            // ذخیره زمان ایجاد کد درخواست
            $_SESSION['activation_request_time'] = time();

            // نمایش فرم فعال‌سازی به کاربر
            $viewData = [
                'page_title' => 'فعال‌سازی لایسنس',
                'appName' => $this->config['app']['name'],
                'baseUrl' => $this->config['app']['base_url'],
                'form_action' => $this->config['app']['base_url'] . '/activate',
                'license_status' => $licenseStatus,
                'error' => null,
                'success' => null,
                'domain' => $domain,
                'hardware_id' => $hardwareId,
                'request_code' => $requestCode,
                'activation_data' => $_SESSION['activation_data']
            ];

            require_once __DIR__ . '/../views/license/activate.php';
            return;

        } catch (Throwable $e) {
            $this->logger->error('Error in showActivateForm.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $viewData = [
                'page_title' => 'خطا در فعال‌سازی',
                'appName' => $this->config['app']['name'],
                'baseUrl' => $this->config['app']['base_url'],
                'error' => 'خطا در آماده‌سازی اطلاعات فعال‌سازی: ' . $e->getMessage(),
                'domain' => $domain ?? 'نامشخص',
                'hardware_id' => $hardwareId ?? 'نامشخص',
                'request_code' => '',
                'activation_data' => null
            ];
            
            require_once __DIR__ . '/../views/license/activate.php';
        }
    }

    /**
     * متد جدید برای دریافت request_code از سرور
     */
    public function getRequestCode(): void {
        try {
            if (!isset($_SESSION['activation_data'])) {
                throw new Exception('اطلاعات فعال‌سازی در دسترس نیست');
            }

            $activationData = $_SESSION['activation_data'];
            
            // ارسال درخواست به سرور برای دریافت request_code
            $response = $this->securityService->completeActivation();
            
            if (!isset($response['success']) || !$response['success']) {
                throw new Exception($response['message'] ?? 'خطا در دریافت کد درخواست از سرور');
            }

            // به‌روزرسانی اطلاعات در سشن
            $_SESSION['activation_data']['request_code'] = $response['request_code'] ?? null;

            // ارسال پاسخ به کلاینت
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'request_code' => $response['request_code'],
                'message' => 'کد درخواست با موفقیت دریافت شد'
            ]);
            
        } catch (Throwable $e) {
            $this->logger->error("Error getting request code", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'خطا در دریافت کد درخواست: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * متد کمکی برای دریافت شناسه سخت‌افزار، مشابه آنچه در LicenseService وجود دارد.
     * این می‌تواند به AbstractController یا یک Trait منتقل شود.
     */
    private function tryGetHardwareId(): string {
        if (isset($this->services['securityService']) && $this->services['securityService'] instanceof \App\Services\SecurityService) {
            try {
                return $this->services['securityService']->generateHardwareId();
            } catch (Exception $e) {
                $this->logger->error("Failed to get hardware ID in LicenseController.", ['exception' => $e->getMessage()]);
                return 'خطا در دریافت شناسه سخت‌افزار';
            }
        }
        return 'سرویس امنیتی یافت نشد';
    }


    public function processActivation(): void {
        $this->logger->info("Processing license activation request.");

        if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
            $this->redirect('activate');
            return;
        }

        $licenseKey = trim($_POST['license_key'] ?? '');

        if (empty($licenseKey)) {
            $this->setSessionMessage('لطفاً کلید فعال‌سازی را وارد کنید.', 'danger', 'license_error');
            $this->redirect('activate');
            return;
        }

        try {
            // ساخت دامنه کامل با پروتکل
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domain = $protocol . $_SERVER['HTTP_HOST'];

            // مرحله 1: ارسال درخواست initiateActivation
            $initiatePayload = [
                'domain' => $domain,
                'ip' => $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? '127.0.0.1',
                'ray_id' => $_SESSION['ray_id'] ?? uniqid('Ray-1-', true)
            ];

            $this->logger->debug('Sending initiate activation request.', [
                'payload' => array_merge($initiatePayload, [
                    'ip' => $initiatePayload['ip']
                ])
            ]);

            $initiateResponse = $this->apiClient->sendSecurePostRequest('license/initiate-activation', $initiatePayload);

            if (!$initiateResponse || !isset($initiateResponse['status']) || $initiateResponse['status'] !== 'success') {
                throw new Exception('Failed to initiate activation: ' . ($initiateResponse['message'] ?? 'Unknown error'));
            }

            // لاگ کردن پاسخ سرور برای دیباگ
            $this->logger->debug('Received initiate activation response.', [
                'response' => $initiateResponse
            ]);

            // تولید hardware_id با استفاده از salt و server_nonce
            $hardwareIdSalt = $initiateResponse['hardware_id_salt'] ?? $initiateResponse['salt'] ?? '';
            $activationSalt = $initiateResponse['activation_salt'] ?? $initiateResponse['salt'] ?? '';
            $serverNonce = $initiateResponse['server_nonce'] ?? '';

            $hardwareId = $this->securityService->generateHardwareId($hardwareIdSalt, $serverNonce);

            // ذخیره اطلاعات در سشن
            $_SESSION['activation_data'] = [
                'hardware_id' => $hardwareId,
                'domain' => $domain,
                'ip' => $initiatePayload['ip'],
                'server_nonce' => $serverNonce,
                'hardware_id_salt' => $hardwareIdSalt,
                'activation_salt' => $activationSalt,
                'ray_id' => $initiatePayload['ray_id']
            ];

            // ذخیره زمان ایجاد کد درخواست
            $_SESSION['activation_request_time'] = time();

            // مرحله 2: ارسال درخواست completeActivation
            $finalPayload = [
                'hardware_id' => $hardwareId,
                'domain' => $domain,
                'ip' => $initiatePayload['ip'],
                'server_nonce' => $serverNonce,
                'hardware_id_salt' => $hardwareIdSalt,
                'activation_salt' => $activationSalt,
                'ray_id' => $initiatePayload['ray_id'],
                'license_key' => $licenseKey
            ];

            $this->logger->debug('Sending final activation request.', ['payload' => $finalPayload]);

            $response = $this->apiClient->sendSecurePostRequest('license/complete-activation', $finalPayload);

            if (!$response || !isset($response['status']) || $response['status'] !== 'success') {
                throw new Exception('API request failed with status code: ' . 
                    ($response['http_code'] ?? 'unknown') . ' - ' . 
                    ($response['message'] ?? 'Unknown error'));
            }

            // لاگ کردن پاسخ نهایی سرور برای دیباگ
            $this->logger->debug('Received complete activation response.', [
                'response' => $response
            ]);

            // ذخیره اطلاعات لایسنس
            $licenseData = [
                'license_key' => $licenseKey,
                'hardware_id' => $hardwareId,
                'domain' => $domain,
                'ip' => $initiatePayload['ip'],
                'ray_id' => $initiatePayload['ray_id'],
                'status' => 'active',
                'activation_date' => date('Y-m-d H:i:s'),
                'request_code' => $response['request_code'] ?? null,
                'license_type' => 'standard',
                'is_active' => 1,
                'expires_at' => $response['expires_at'] ?? null,
                'features' => $response['features'] ?? []
            ];

            if ($this->licenseService->saveLicenseData($licenseData)) {
                $this->setSessionMessage('لایسنس با موفقیت فعال شد.', 'success', 'license_success');
                $this->redirect('dashboard');
            } else {
                throw new Exception('Failed to save license data locally.');
            }

        } catch (Throwable $e) {
            $this->logger->error("Exception during license activation processing.", [
                'key_prefix' => substr($licenseKey, 0, 5),
                'exception_type' => get_class($e),
                'message' => $e->getMessage()
            ]);
            
            $errorMessage = 'خطای سیستمی در هنگام فعال‌سازی رخ داد.';
            if ($this->config['app']['debug']) {
                $errorMessage .= ' جزئیات: ' . Helper::escapeHtml($e->getMessage());
            }
            
            $this->setSessionMessage($errorMessage, 'danger', 'license_error');
            $this->redirect('activate');
        }
    }

    public function activateLicense($licenseKey, $hardwareId, $domain) {
        $this->logger->info('Starting license activation process.', [
            'license_key_prefix' => substr($licenseKey, 0, 8) . '...',
            'domain' => $domain
        ]);

        try {
            // 1. شروع فرآیند فعال‌سازی
            $initiationResponse = $this->securityService->initiateActivation(
                $licenseKey,
                $hardwareId,
                $domain
            );

            // 2. تکمیل فرآیند فعال‌سازی
            $activationResponse = $this->securityService->completeActivation();

            // 3. بررسی پاسخ نهایی
            if (!isset($activationResponse['status']) || $activationResponse['status'] !== 'success') {
                throw new \Exception('License activation failed: ' . 
                    ($activationResponse['message'] ?? 'Unknown error'));
            }

            // 4. ذخیره اطلاعات لایسنس
            $this->licenseService->saveLicenseData([
                'license_key' => $licenseKey,
                'hardware_id' => $hardwareId,
                'domain' => $domain,
                'activation_date' => date('Y-m-d H:i:s'),
                'status' => 'active',
                'request_code' => $activationResponse['request_code'] ?? null
            ]);

            $this->logger->info('License activation completed successfully.');

            return [
                'status' => 'success',
                'message' => 'License activated successfully',
                'data' => [
                    'activation_date' => date('Y-m-d H:i:s'),
                    'expiry_date' => $activationResponse['expiry_date'] ?? null,
                    'features' => $activationResponse['features'] ?? []
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('License activation failed.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => 'License activation failed: ' . $e->getMessage()
            ];
        }
    }

    public function activate(Request $request) {
        try {
            $this->validate($request, [
                'hardware_id' => 'required|string|max:64',
                'domain' => 'required|string|max:255',
                'request_code' => 'required|string|max:64'
            ]);

            $hardwareId = $request->input('hardware_id');
            $domain = $request->input('domain');
            $requestCode = $request->input('request_code');

            // فعال‌سازی لایسنس
            $result = $this->licenseService->activateLicense(
                $hardwareId,
                $domain,
                $requestCode
            );

            return response()->json([
                'success' => true,
                'activation_token' => $result['activation_token'],
                'expires_at' => $result['expires_at']
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input data',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate license: ' . $e->getMessage()
            ], 500);
        }
    }

    public function initiateActivation(Request $request) {
        try {
            $this->validate($request, [
                'domain' => 'required|string|max:255',
                'client_nonce' => 'required|string|size:32'
            ]);

            $domain = $request->input('domain');
            $clientNonce = $request->input('client_nonce');
            $ip = $request->ip();

            // شروع فرآیند فعال‌سازی
            $result = $this->securityService->initiateActivation(
                $clientNonce,
                $domain,
                $ip
            );

            return response()->json([
                'success' => true,
                'hardware_id' => $result['hardware_id'],
                'salt' => $result['salt'],
                'server_nonce' => $result['server_nonce'],
                'ray_id' => $result['ray_id']
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input data',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate activation: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateRequestCode(Request $request) {
        try {
            $this->validate($request, [
                'hardware_id' => 'required|string|max:64',
                'domain' => 'required|string|max:255',
                'client_nonce' => 'required|string|size:32',
                'server_nonce' => 'required|string|size:32'
            ]);

            $hardwareId = $request->input('hardware_id');
            $domain = $request->input('domain');
            $clientNonce = $request->input('client_nonce');
            $serverNonce = $request->input('server_nonce');

            // تولید request code
            $requestCode = $this->licenseService->generateRequestCode(
                $hardwareId,
                $domain,
                $clientNonce,
                $serverNonce
            );

            return response()->json([
                'success' => true,
                'request_code' => $requestCode
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input data',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate request code: ' . $e->getMessage()
            ], 500);
        }
    }

    // ... (سایر متدها مانند validateLicenseApi و sendSupportEmail اگر هنوز لازم هستند) ...
    // متد validateLicenseApi اگر دیگر استفاده نمی‌شود، می‌تواند حذف شود.
}