<?php

namespace App\Services;

use Monolog\Logger;
use Exception;
use Throwable;
use App\Repositories\LicenseRepository;
use App\Services\ApiClient; // ApiClient با متدهای جدید (requestHandshake, sendSecurePostRequest)
use App\Services\SecurityService;
use App\Repositories\SettingsRepository; // برای دسترسی به اطلاعات handshake از settings
use PDO;

class LicenseService {

    private LicenseRepository $licenseRepository;
    private ApiClient $apiClient;
    private SecurityService $securityService;
    private SettingsRepository $settingsRepository; // اضافه شد
    private Logger $logger;
    private array $config;
    private int $onlineCheckIntervalDays;
    private PDO $db;

    private ?array $activeLicenseInfoCache = null;
    private bool $licenseCheckedThisRequest = false;

    public function __construct(
        LicenseRepository $licenseRepository,
        ApiClient $apiClient,
        SecurityService $securityService,
        SettingsRepository $settingsRepository,
        Logger $logger,
        array $config
    ) {
        $this->licenseRepository = $licenseRepository;
        $this->apiClient = $apiClient;
        $this->securityService = $securityService;
        $this->settingsRepository = $settingsRepository;
        $this->logger = $logger;
        $this->config = $config;

        $licenseConfig = $config['license'] ?? [];
        $this->onlineCheckIntervalDays = (int)($licenseConfig['online_check_interval_days'] ?? 7);
        $this->logger->debug("LicenseService initialized with new structure.");
    }

    private function cacheResult(array $result): array {
        $this->activeLicenseInfoCache = $result;
        $this->licenseCheckedThisRequest = true;
        return $result;
    }

    private function generateHardwareId(): string {
        try {
            return $this->securityService->generateHardwareId();
        } catch (Throwable $e) {
            $this->logger->error("Failed to generate Hardware ID via SecurityService.", ['exception' => $e->getMessage()]);
            throw new Exception("System error: Could not generate hardware identifier.", 0, $e);
        }
    }

    /**
     * بررسی می‌کند آیا اطلاعات Handshake معتبر در سیستم وجود دارد یا خیر.
     * @return bool
     */
    /**
     * بررسی می‌کند آیا اطلاعات Handshake معتبر و کامل (شامل system_id) در سیستم وجود دارد یا خیر.
     * @return bool
     */
    private function hasValidHandshake(): bool
    {
        $handshakeString = $this->settingsRepository->get('api_handshake_string');
        $handshakeMap = $this->settingsRepository->get('api_handshake_map');
        $systemId = $this->settingsRepository->get('api_system_id'); // بررسی وجود system_id
        $expires = (int) $this->settingsRepository->get('api_handshake_expires');
        $currentTime = time();

        $isValid = true;
        $missingReason = [];

        if (empty($handshakeString)) {
            $isValid = false;
            $missingReason[] = 'Handshake string missing';
        }
        if (empty($handshakeMap)) {
            $isValid = false;
            $missingReason[] = 'Handshake map missing';
        }
        if (empty($systemId)) { // شرط جدید برای system_id
            $isValid = false;
            $missingReason[] = 'System ID missing';
        }
        if ($expires <= $currentTime) {
            $isValid = false;
            $missingReason[] = 'Handshake expired';
        }

        if ($isValid) {
            $this->logger->debug("Valid handshake data found in settings (including system_id).");
            return true;
        } else {
            $this->logger->info("No valid/complete handshake data found or expired. Handshake needed.", [
                'reason' => implode(', ', $missingReason),
                'has_string' => !empty($handshakeString),
                'has_map' => !empty($handshakeMap),
                'has_system_id' => !empty($systemId), // لاگ کردن وجود system_id
                'expires_at_unix' => $expires,
                'current_time_unix' => $currentTime
            ]);
            return false;
        }
    }

    /**
     * تلاش برای انجام یا تایید Handshake با سرور.
     * @param string|null $licenseKeyForHandshake (اختیاری) کلید لایسنس برای ارسال به سرور در زمان handshake اولیه.
     * @return bool True on success, false on failure.
     */
    private function ensureHandshake(?string $licenseKeyForHandshake = null): bool
    {
        if ($this->hasValidHandshake()) {
            return true;
        }

        $this->logger->info("Attempting to perform handshake with the server.");
        try {
            // اطلاعات لازم برای handshake
            // خواندن دامنه به همراه پروتکل
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $hostName = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? php_uname('n');
            $clientDomain = $protocol . $hostName;
            
            $clientIp = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? '127.0.0.1';
            $clientRayId = $_SESSION['ray_id'] ?? uniqid('ray_hs_', true);
            $_SESSION['ray_id'] = $clientRayId; // اطمینان از ذخیره در سشن
            $clientHardwareId = $this->generateHardwareId();
            $clientRequestCode = $this->securityService->generateRequestCode($clientIp, $clientDomain, $clientRayId);

            // آماده سازی license_key_display برای ارسال به سرور (۸ کاراکتر اول + "...")
            $fullLicenseKeyFromUser = $licenseKeyForHandshake; 
            $prefix = substr((string)$fullLicenseKeyFromUser, 0, 8); 
            $licenseKeyDisplayToSend = $prefix . '...';

            $this->logger->debug("Handshake Payload being sent to server", [
                'license_key_display' => $licenseKeyDisplayToSend,
                'domain' => $clientDomain,
                'ip' => $clientIp,
                'ray_id' => $clientRayId,
                'hardware_id' => $clientHardwareId,
                'request_code' => $clientRequestCode
            ]);

            $handshakePayload = [
                'license_key_display' => $licenseKeyDisplayToSend, 
                'domain'      => $clientDomain,
                'ip'          => $clientIp,
                'ray_id'      => $clientRayId,
                'hardware_id' => $clientHardwareId,
                'request_code'=> $clientRequestCode,
            ];
            
            // اگر $licenseKeyForHandshake از ابتدا null بود، نباید چیزی ارسال شود
            if ($fullLicenseKeyFromUser === null) {
                unset($handshakePayload['license_key_display']);
                $this->logger->debug("No license key provided for handshake, removing from payload.");
            }

            $handshakeResult = $this->apiClient->requestHandshake($handshakePayload); 

            // بررسی پاسخ و ذخیره اطلاعات در صورت موفقیت
            if (isset($handshakeResult['handshake_string']) && isset($handshakeResult['handshake_map']) && isset($handshakeResult['system_id'])) {
                // ذخیره‌سازی امن (اگر رمزنگاری لازم است، باید در ApiClient یا اینجا انجام شود)
                // ApiClient در حال حاضر به صورت مستقیم ذخیره می‌کند (باید در requestHandshake باشد)
                // ذخیره‌سازی system_id اضافه شده است:
                $this->settingsRepository->set('api_system_id', $handshakeResult['system_id']);
                $this->logger->info("Handshake successful and credentials (including system_id) stored.", ['system_id' => $handshakeResult['system_id']]);
                return true;
            } else {
                $this->logger->error("Handshake failed or server returned invalid/incomplete response.", ['response' => $handshakeResult]);
                // اگر system_id وجود نداشت
                if (!isset($handshakeResult['system_id'])) {
                    throw new Exception("Handshake response from server is missing 'system_id'.");
                }
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error("Exception during handshake attempt.", ['exception' => $e->getMessage()]);
            // خطا را دوباره throw کن تا processActivationWithHandshake آن را بگیرد
            throw $e;
        }
    } 

    public function checkLicense(bool $forceOnlineCheck = false): array {
        if ($this->licenseCheckedThisRequest && !$forceOnlineCheck && $this->activeLicenseInfoCache !== null) {
            return $this->activeLicenseInfoCache;
        }
        $this->logger->info("Performing license check (new structure)." . ($forceOnlineCheck ? " (Online forced)" : ""));

        try {
            $license = $this->licenseRepository->getActiveLicense();

            if (!$license) {
                $this->logger->warning("No active license found in database.");
                return $this->cacheResult(['valid' => false, 'message' => 'لایسنس یافت نشد یا فعال نیست.', 'license_info' => null, 'status_code' => 'NO_LICENSE']);
            }
            $licenseId = (int)$license['id'];

            // Log current license state
            $this->logger->info("Current license state:", [
                'id' => $licenseId,
                'status' => $license['status'],
                'is_active' => $license['is_active'],
                'expires_at' => $license['expires_at'],
                'last_validated' => $license['last_validated']
            ]);

            if (($license['is_active'] ?? 0) !== 1 || ($license['status'] ?? '') !== 'active') {
                $this->logger->warning("License marked as inactive in database.", [
                    'id' => $licenseId,
                    'is_active' => $license['is_active'],
                    'status' => $license['status']
                ]);
                return $this->cacheResult(['valid' => false, 'message' => 'وضعیت لایسنس محلی نامعتبر است.', 'license_info' => $license, 'status_code' => 'LOCAL_INACTIVE']);
            }

            $currentHardwareId = $this->generateHardwareId();
            $storedHardwareId = $license['hardware_id'] ?? null;

            if (empty($storedHardwareId)) {
                $this->logger->info("No hardware ID stored, updating with current ID.", ['license_id' => $licenseId]);
                $this->licenseRepository->updateLicenseHardwareId($licenseId, $currentHardwareId);
                $forceOnlineCheck = true;
            } elseif ($storedHardwareId !== $currentHardwareId) {
                $this->logger->warning("Hardware ID mismatch.", [
                    'license_id' => $licenseId,
                    'stored_hwid' => substr($storedHardwareId, 0, 10) . '...',
                    'current_hwid' => substr($currentHardwareId, 0, 10) . '...'
                ]);
                return $this->cacheResult(['valid' => false, 'message' => 'لایسنس برای این دستگاه معتبر نیست (عدم تطابق شناسه سخت‌افزار).', 'license_info' => $license, 'status_code' => 'HWID_MISMATCH']);
            }

            $localStatus = ['valid' => true, 'message' => 'لایسنس محلی معتبر است.', 'license_info' => $license, 'status_code' => 'LOCAL_VALID'];
            if (!empty($license['expires_at']) && strtotime($license['expires_at']) < time()) {
                $this->logger->warning("License has expired.", [
                    'license_id' => $licenseId,
                    'expires_at' => $license['expires_at'],
                    'current_time' => date('Y-m-d H:i:s')
                ]);
                $localStatus = ['valid' => false, 'message' => 'لایسنس شما (محلی) منقضی شده است.', 'license_info' => $license, 'status_code' => 'LOCAL_EXPIRED'];
            }

            $needsOnlineCheck = $forceOnlineCheck;
            if (!$needsOnlineCheck) {
                $lastCheckTime = $this->licenseRepository->getLastOnlineCheckTimestamp($licenseId);
                if ($lastCheckTime === 0 || (time() - $lastCheckTime) >= ($this->onlineCheckIntervalDays * 86400)) {
                    $needsOnlineCheck = true;
                    $this->logger->info("Online check needed due to interval.", [
                        'license_id' => $licenseId,
                        'last_check' => date('Y-m-d H:i:s', $lastCheckTime),
                        'interval_days' => $this->onlineCheckIntervalDays
                    ]);
                }
            }

            if ($needsOnlineCheck) {
                $this->logger->info("Attempting online license verification.", ['license_id' => $licenseId]);
                // قبل از بررسی آنلاین، از وجود handshake معتبر اطمینان حاصل کنید
                if (!$this->ensureHandshake($license['license_key'])) {
                    $this->logger->warning("Online check aborted: Handshake failed or not available.", [
                        'license_id' => $licenseId,
                        'handshake_status' => 'failed'
                    ]);
                    // اگر handshake ناموفق بود، به وضعیت محلی اتکا می‌کنیم.
                    $localStatus['message'] .= ' (خطا در برقراری ارتباط امن اولیه با سرور برای بررسی آنلاین).';
                    $localStatus['status_code'] .= '_HANDSHAKE_FAILED';
                    return $this->cacheResult($localStatus);
                }

                try {
                    $domain = $license['domain'] ?? $_SERVER['SERVER_NAME'] ?? php_uname('n');
                    $ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? 'unknown';
                    $rayId = $_SESSION['ray_id'] ?? null;

                    $this->logger->debug("Sending online verification request.", [
                        'license_id' => $licenseId,
                        'domain' => $domain,
                        'ip' => $ip,
                        'ray_id' => $rayId
                    ]);

                    $apiResponse = $this->apiClient->verifyLicenseWithNewServer(
                        $license['license_key'], $domain, $ip, $currentHardwareId, $rayId
                    );

                    $this->logger->debug("Received online verification response.", [
                        'license_id' => $licenseId,
                        'response_status' => $apiResponse['status'] ?? 'unknown',
                        'response_code' => $apiResponse['status_code'] ?? 'unknown'
                    ]);

                    if (isset($apiResponse['valid']) && $apiResponse['valid'] === true) {
                        $this->licenseRepository->updateLastOnlineCheck($licenseId);
                        if (isset($apiResponse['license_data']) && is_array($apiResponse['license_data'])) {
                            $this->syncLicenseInfo($licenseId, $apiResponse['license_data']);
                            $license = $this->licenseRepository->getLicenseById($licenseId);
                        }
                        return $this->cacheResult(['valid' => true, 'message' => $apiResponse['message'] ?? 'لایسنس از طریق سرور تایید شد.', 'license_info' => $license, 'status_code' => $apiResponse['status_code'] ?? 'SERVER_VALID']);
                    }
                    
                    $this->logger->warning("Online license verification failed by server.", [
                        'license_id' => $licenseId,
                        'response' => $apiResponse,
                        'server_message' => $apiResponse['message'] ?? 'No message'
                    ]);
                    
                    if(isset($apiResponse['status_code'])) {
                        $newStatus = match ($apiResponse['status_code']) {
                            'EXPIRED_ON_SERVER', 'LICENSE_EXPIRED' => 'expired',
                            'SUSPENDED_ON_SERVER', 'LICENSE_SUSPENDED' => 'suspended',
                            'REVOKED_ON_SERVER', 'LICENSE_REVOKED' => 'revoked',
                            default => $license['status']
                        };
                        if ($newStatus !== $license['status']) {
                            $this->logger->info("Updating license status based on server response.", [
                                'license_id' => $licenseId,
                                'old_status' => $license['status'],
                                'new_status' => $newStatus
                            ]);
                            $this->licenseRepository->updateLicenseStatus($licenseId, $newStatus);
                            $license['status'] = $newStatus;
                            $license['is_active'] = ($newStatus === 'active') ? 1 : 0;
                        }
                    }
                    return $this->cacheResult(['valid' => false, 'message' => $apiResponse['message'] ?? 'سرور لایسنس را نامعتبر اعلام کرد.', 'license_info' => $license, 'status_code' => $apiResponse['status_code'] ?? 'SERVER_INVALID']);
                } catch (Exception $e) {
                    $this->logger->error("Exception during online license verification.", [
                        'license_id' => $licenseId,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    if ($localStatus['valid']) {
                        $localStatus['message'] = 'لایسنس محلی معتبر است اما خطایی در اعتبارسنجی آنلاین رخ داد: ' . $e->getMessage();
                        $localStatus['status_code'] = 'ONLINE_CHECK_FAILED_BUT_LOCAL_VALID';
                        return $this->cacheResult($localStatus);
                    }
                    $localStatus['message'] .= ' همچنین خطایی در اعتبارسنجی آنلاین رخ داد: ' . $e->getMessage();
                    $localStatus['status_code'] .= '_AND_ONLINE_CHECK_FAILED';
                    return $this->cacheResult($localStatus);
                }
            }

            return $this->cacheResult($localStatus);

        } catch (Throwable $e) {
            $this->logger->error("Critical error during license check process.", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->cacheResult(['valid' => false, 'message' => 'خطای سیستمی بحرانی در بررسی لایسنس: ' . $e->getMessage(), 'license_info' => null, 'status_code' => 'CRITICAL_SYSTEM_ERROR']);
        }
    }

    /**
     * فرآیند کامل فعال‌سازی لایسنس با Handshake اولیه.
     */
    public function processActivationWithHandshake(string $licenseKey): array {
        $this->logger->info("Starting full activation process with handshake.", ['key_prefix' => substr($licenseKey, 0, 5)]);
    
        try {
            // مرحله 1: انجام Handshake با پاس دادن کلید لایسنس (مهم برای شناسایی سیستم در سرور)
            if (!$this->ensureHandshake($licenseKey)) {
                return ['success' => false, 'message' => 'خطا در برقراری ارتباط امن اولیه (Handshake) با سرور. فعال‌سازی متوقف شد.'];
            }
    
            // مرحله 2: ارسال درخواست فعال‌سازی امن به سرور لاراول
            $this->logger->info("Handshake successful, proceeding with secure activation request.");
            $activationEndpoint = 'license/activate';
            $currentHardwareId = $this->generateHardwareId();
            
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $hostName = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? php_uname('n');
            $domain = $protocol . $hostName;
            $ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? 'unknown';
            $rayId = $_SESSION['ray_id'] ?? null;
    
            // تهیه license_key_display برای ارسال به اندپوینت /license/activate
            $prefix = substr($licenseKey, 0, 8); 
            $licenseKeyDisplayForActivation = $prefix . '...'; 
    
            $activationPayload = [
                'license_key_display' => $licenseKeyDisplayForActivation, 
                'hardware_id' => $currentHardwareId,
                'domain'      => $domain,
                'ip'          => $ip,
                'ray_id'      => $rayId,
                'license_type' => 'standard', // اضافه کردن license_type با مقدار پیش‌فرض
                'features' => [], // اضافه کردن features با مقدار پیش‌فرض
                'expires_at' => null // اضافه کردن expires_at با مقدار پیش‌فرض
            ];
            
            $this->logger->debug("LicenseService: Payload for /license/activate", $activationPayload);
    
            $apiResponse = $this->apiClient->sendSecurePostRequest($activationEndpoint, $activationPayload);
            $this->logger->debug('LicenseService: Raw API response from /license/activate', ['response' => $apiResponse]);
            
            // مرحله 3: پردازش پاسخ سرور
            if (isset($apiResponse['message']) && str_contains(strtolower($apiResponse['message']), 'activated successfully') && isset($apiResponse['license_status']) && is_array($apiResponse['license_status'])) {
                
                $this->logger->info("License activated successfully by server (based on new check).", ['response_message' => $apiResponse['message'], 'license_status' => $apiResponse['license_status']]);
            
                $licenseDataToSave = [
                    'license_key' => $licenseKey, 
                    'license_key_display' => $apiResponse['license_status']['license_key_display'] ?? substr($licenseKey, 0, 8) . '...',
                    'hardware_id' => $currentHardwareId,
                    'domain' => $domain,
                    'status' => $apiResponse['license_status']['status'] ?? 'active',
                    'is_active' => ($apiResponse['license_status']['is_active'] ?? true) ? 1 : 0,
                    'expires_at' => $apiResponse['license_status']['expires_at'] ?? null,
                    'activated_at' => $apiResponse['license_status']['activated_at'] ?? gmdate('Y-m-d H:i:s'), 
                    'features' => $apiResponse['license_status']['features'] ?? [], 
                    'license_type' => $apiResponse['license_status']['license_type'] ?? 'standard', 
                    'ip_address' => $ip,
                    'ray_id' => $rayId,
                    'request_code' => $apiResponse['license_status']['request_code'] ?? null
                ];
            
                $savedLicenseId = $this->licenseRepository->saveActivatedLicense($licenseDataToSave);
                if ($savedLicenseId) {
                    $this->clearCache();
                    return ['success' => true, 'message' => $apiResponse['message']];
                } else {
                    $this->logger->error("License approved by server but failed to save locally.", ['license_key' => $licenseKey, 'server_data' => $apiResponse]);
                    return ['success' => false, 'message' => 'لایسنس توسط سرور تایید شد اما در ذخیره‌سازی محلی خطا رخ داد. لطفاً با پشتیبانی تماس بگیرید.', 'status_code' => 'LOCAL_SAVE_FAILED_AFTER_SERVER_APPROVAL'];
                } 
            } else {
                $this->logger->warning("Server denied license activation or returned unexpected response format.", ['response' => $apiResponse]);
                $errorMessage = 'سرور فعال‌سازی لایسنس را تایید نکرد یا پاسخ نامفهومی ارسال کرد.';
                if (isset($apiResponse['error'])) { 
                    $errorMessage = $apiResponse['error'];
                } elseif (isset($apiResponse['message'])) {
                    $errorMessage = $apiResponse['message'];
                }
                return ['success' => false, 'message' => $errorMessage, 'status_code' => $apiResponse['status_code'] ?? 'ACTIVATION_DENIED_OR_UNEXPECTED_RESPONSE'];
            }
        } catch (Exception $e) {
            $this->logger->error("Exception during full activation process.", ['key_prefix' => substr($licenseKey, 0, 5), 'exception' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطای سیستمی در فرآیند فعال‌سازی: ' . $e->getMessage()];
        }
    }

    private function syncLicenseInfo(int $licenseId, array $apiLicenseData): void
    {
        $this->logger->info("Synchronizing local license info with server data.", ['license_id' => $licenseId, 'server_data_keys' => array_keys($apiLicenseData)]);
        $dataToUpdate = [];

        if (isset($apiLicenseData['expires_at'])) {
            $dataToUpdate['expires_at'] = $apiLicenseData['expires_at'];
        }
        if (isset($apiLicenseData['status'])) {
            $dataToUpdate['status'] = $apiLicenseData['status'];
            $dataToUpdate['is_active'] = ($apiLicenseData['status'] === 'active') ? 1 : 0;
        }
        if (isset($apiLicenseData['features']) && is_array($apiLicenseData['features'])) {
            // LicenseRepository باید بتواند آرایه features را به JSON تبدیل کند یا مستقیم ذخیره کند.
            $dataToUpdate['features'] = $apiLicenseData['features'];
        }
        // ... سایر فیلدها

        if (!empty($dataToUpdate)) {
            if ($this->licenseRepository->updateLicenseDetails($licenseId, $dataToUpdate)) {
                 $this->logger->info("Local license details updated from server.", ['license_id' => $licenseId, 'updated_fields' => array_keys($dataToUpdate)]);
                 $this->clearCache();
            } else {
                 $this->logger->error("Failed to update local license details from server.", ['license_id' => $licenseId, 'data_to_update' => $dataToUpdate]);
            }
        }
    }

    public function getActiveLicenseInfo(): ?array {
        if ($this->activeLicenseInfoCache && isset($this->activeLicenseInfoCache['license_info'])) {
            return $this->activeLicenseInfoCache['license_info'];
        }
        $result = $this->checkLicense();
        return $result['license_info'] ?? null;
    }

    public function isLicenseValid(): bool {
        $result = $this->checkLicense();
        return $result['valid'] ?? false;
    }

    public function getLicenseStatusMessage(): string {
        $result = $this->checkLicense();
        return $result['message'] ?? 'وضعیت لایسنس نامشخص است.';
    }
    public function getLicenseStatusCode(): string {
        $result = $this->checkLicense();
        return $result['status_code'] ?? 'UNKNOWN';
    }

    public function clearCache(): void {
        $this->activeLicenseInfoCache = null;
        $this->licenseCheckedThisRequest = false;
        $this->logger->debug("LicenseService cache cleared.");
    }

    public function generateRequestCode($hardwareId, $domain, $clientNonce, $serverNonce): string {
        try {
            $this->logger->info('Generating request code', [
                'hardware_id' => substr($hardwareId, 0, 10) . '...',
                'domain' => $domain
            ]);

            // تولید request_code با استفاده از hardware_id و nonce‌ها
            $requestCode = hash_hmac('sha256', 
                $hardwareId . $domain . $clientNonce . $serverNonce,
                $this->securityService->generateMachineKey()
            );

            return $requestCode;
        } catch (Exception $e) {
            $this->logger->error('Error generating request code', [
                'error' => $e->getMessage(),
                'hardware_id' => substr($hardwareId, 0, 10) . '...',
                'domain' => $domain
            ]);
            throw $e;
        }
    }

    /**
     * پردازش پاسخ سرور در مرحله شروع فعال‌سازی
     * @param array $response پاسخ سرور شامل server_nonce و salt
     * @return string request_code تولید شده
     */
    public function handleActivationInitiation(array $response): string {
        try {
            if (!isset($response['server_nonce']) || !isset($response['salt'])) {
                throw new Exception('Invalid server response: missing required fields');
            }

            // دریافت اطلاعات از سشن
            if (!isset($_SESSION['client_nonce'])) {
                throw new Exception('Client nonce not found in session');
            }

            $clientNonce = $_SESSION['client_nonce'];
            $serverNonce = $response['server_nonce'];
            $hardwareId = $this->generateHardwareId();
            
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $hostName = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? php_uname('n');
            $domain = $protocol . $hostName;

            // تولید request_code
            $requestCode = $this->generateRequestCode($hardwareId, $domain, $clientNonce, $serverNonce);

            // ذخیره اطلاعات در سشن
            $_SESSION['activation_data'] = [
                'hardware_id' => $hardwareId,
                'domain' => $domain,
                'client_nonce' => $clientNonce,
                'server_nonce' => $serverNonce,
                'salt' => $response['salt'],
                'request_code' => $requestCode
            ];

            $this->logger->info('Activation initiation handled successfully', [
                'hardware_id' => substr($hardwareId, 0, 10) . '...',
                'domain' => $domain
            ]);

            return $requestCode;

        } catch (Exception $e) {
            $this->logger->error('Error handling activation initiation', [
                'error' => $e->getMessage(),
                'response_keys' => array_keys($response)
            ]);
            throw $e;
        }
    }

    /**
     * ذخیره اطلاعات لایسنس در دیتابیس
     * @param array $data اطلاعات لایسنس
     * @return bool نتیجه عملیات
     */
    public function saveLicenseData(array $data): bool {
        try {
            $this->logger->info("Attempting to save license data.", [
                'license_key_prefix' => substr($data['license_key'], 0, 8) . '...',
                'domain' => $data['domain']
            ]);

            // استفاده از saveActivatedLicense برای ذخیره لایسنس
            $licenseData = [
                'license_key' => $data['license_key'],
                'hardware_id' => $data['hardware_id'],
                'domain' => $data['domain'],
                'status' => $data['status'] ?? 'active',
                'is_active' => ($data['status'] ?? 'active') === 'active' ? 1 : 0,
                'ip_address' => $data['ip'] ?? null,
                'ray_id' => $data['ray_id'] ?? null,
                'request_code' => $data['request_code'] ?? null,
                'activated_at' => $data['activation_date'] ?? date('Y-m-d H:i:s'),
                'license_type' => $data['license_type'] ?? 'standard',
                'features' => $data['features'] ?? [],
                'expires_at' => $data['expires_at'] ?? null
            ];

            $savedId = $this->licenseRepository->saveActivatedLicense($licenseData);
            
            if ($savedId) {
                $this->logger->info("License data saved successfully.", ['license_id' => $savedId]);
                return true;
            } else {
                $this->logger->error("Failed to save license data.");
                return false;
            }

        } catch (Exception $e) {
            $this->logger->error("Error saving license data.", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}