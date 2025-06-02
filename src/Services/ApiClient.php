<?php

namespace App\Services;

use Exception;
use Monolog\Logger;
use Throwable;
use App\Repositories\SettingsRepository; // اضافه شود
use App\Services\SecurityService;      // اضافه شود

class ApiClient {
    private Logger $logger;
    private string $baseUrl;
    // private string $secretKey; // این دیگر مستقیماً استفاده نمی‌شود، چون از handshake می‌آید
    private int $timeout = 15;
    private int $connectTimeout = 10;
    private SettingsRepository $settingsRepository; // اضافه شود
    private ?SecurityService $securityService = null; // تغییر به nullable
    private array $config; // اضافه شود برای دسترسی به تنظیمات کلی
    private $apiKey;
    private $apiSecret;

    public function __construct(
        string $baseUrl,
        Logger $logger,
        SettingsRepository $settingsRepository,
        array $config,
        ?SecurityService $securityService = null,
        int $timeout = 15,
        int $connectTimeout = 10
    ) {
        $this->baseUrl = rtrim($baseUrl, '/\\');
        if (empty($this->baseUrl) || filter_var($this->baseUrl, FILTER_VALIDATE_URL) === false) {
            $logger->critical("Invalid Base URL provided to ApiClient.", ['url' => $baseUrl]);
            $this->baseUrl = ''; // Prevent
            // throw new Exception("Invalid API base URL configured.");
        }

        $this->logger = $logger;
        $this->settingsRepository = $settingsRepository;
        $this->securityService = $securityService;  // می‌تواند null باشد
        $this->config = $config;
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->logger->debug("ApiClient initialized.", ['baseUrl' => $this->baseUrl]);
        $this->apiKey = $config['api']['key'] ?? '';
        $this->apiSecret = $config['api']['secret'] ?? '';
    }

    /**
     * استخراج api_key، api_secret و hmac_salt از داده‌های handshake ذخیره‌شده.
     * این متد باید به SettingsRepository و احتمالاً SecurityService برای رمزگشایی دسترسی داشته باشد.
     */
    private function getApiCredentials(): array
    {
        $handshakeStringEnc = $this->settingsRepository->get('api_handshake_string');
        $mappingEnc = $this->settingsRepository->get('api_handshake_map');

        if (empty($handshakeStringEnc) || empty($mappingEnc)) {
            $this->logger->error("اطلاعات Handshake در تنظیمات یافت نشد یا ناقص است.");
            throw new Exception('اطلاعات Handshake برای ارتباط امن در دسترس نیست. لطفاً ابتدا Handshake را انجام دهید.');
        }

        $handshakeString = $handshakeStringEnc;
        $mappingJson = base64_decode($mappingEnc);
        $mapping = json_decode($mappingJson, true);

        if (!$handshakeString || !$mapping || json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("خطا در decode کردن اطلاعات Handshake.", ['mapping_json_error' => json_last_error_msg()]);
            throw new Exception('خطا در پردازش اطلاعات Handshake: ' . json_last_error_msg());
        }

        // بررسی وجود کلیدها در مپینگ قبل از استفاده
        if (!isset($mapping['api_key']) || !isset($mapping['api_secret']) || !isset($mapping['hmac_salt'])) {
            $this->logger->error("ساختار Mapping اطلاعات Handshake نامعتبر است. یک یا چند کلید اصلی (api_key, api_secret, hmac_salt) یافت نشد.", ['mapping_keys' => array_keys($mapping)]);
            throw new Exception('ساختار اطلاعات Handshake نامعتبر است.');
        }

        $api_key_map = $mapping['api_key'];
        $api_secret_map = $mapping['api_secret'];
        $hmac_salt_map = $mapping['hmac_salt'];

        // بررسی اینکه آیا اندیس‌ها عددی و معتبر هستند
        if (!is_array($api_key_map) || count($api_key_map) !== 2 || !is_numeric($api_key_map[0]) || !is_numeric($api_key_map[1]) ||
            !is_array($api_secret_map) || count($api_secret_map) !== 2 || !is_numeric($api_secret_map[0]) || !is_numeric($api_secret_map[1]) ||
            !is_array($hmac_salt_map) || count($hmac_salt_map) !== 2 || !is_numeric($hmac_salt_map[0]) || !is_numeric($hmac_salt_map[1])) {
            $this->logger->error("اندیس‌های ارائه شده در mapping برای کلیدهای API نامعتبر هستند.", ['mapping' => $mapping]);
            throw new Exception('اندیس‌های mapping برای کلیدهای API نامعتبر است.');
        }

        $api_key_len = $api_key_map[1] - $api_key_map[0] + 1;
        $api_secret_len = $api_secret_map[1] - $api_secret_map[0] + 1;
        $hmac_salt_len = $hmac_salt_map[1] - $hmac_salt_map[0] + 1;

        if ($api_key_len <= 0 || $api_secret_len <= 0 || $hmac_salt_len <= 0) {
            $this->logger->error("طول محاسبه شده برای یک یا چند کلید API بر اساس mapping صفر یا منفی است.", ['mapping' => $mapping]);
            throw new Exception('طول نامعتبر برای کلیدهای API بر اساس mapping.');
        }

        $api_key = substr($handshakeString, $api_key_map[0], $api_key_len);
        $api_secret = substr($handshakeString, $api_secret_map[0], $api_secret_len);
        $hmac_salt = substr($handshakeString, $hmac_salt_map[0], $hmac_salt_len);

        if (empty($api_key) || empty($api_secret) || empty($hmac_salt)) {
             $this->logger->error("یکی از مقادیر api_key, api_secret یا hmac_salt پس از استخراج از handshake خالی است.", [
                'api_key_extracted' => $api_key, 
                'api_secret_extracted' => $api_secret, 
                'hmac_salt_extracted' => $hmac_salt,
                'mapping' => $mapping
            ]);
             throw new Exception('استخراج کلیدهای API از Handshake ناموفق بود. مقادیر خالی استخراج شدند.');
        }

        return [
            'api_key'    => $api_key,
            'api_secret' => $api_secret,
            'hmac_salt'  => $hmac_salt,
        ];
    }

    /**
     * ساخت Signature و Header های امنیتی برای درخواست به سرور.
     */
    private function buildSecureHeaders(string $requestBody): array
    {
        $credentials = $this->getApiCredentials();
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $systemId = $this->settingsRepository->get('api_system_id');

        if (empty($systemId)) {
            $this->logger->error("Cannot build secure headers: System ID not found in settings.");
            throw new Exception('System ID not available for secure request. Please perform handshake.');
        }

        $signature = hash_hmac(
            'sha3-512',
            $requestBody . $nonce . $timestamp,
            $credentials['api_secret'] . $credentials['hmac_salt']
        );

        return [
            'X-API-KEY: ' . $credentials['api_key'],
            'X-SYSTEM-ID: ' . $systemId,
            'X-NONCE: ' . $nonce,
            'X-TIMESTAMP: ' . $timestamp,
            'X-SIGNATURE: ' . $signature,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
    }

    /**
     * ارسال درخواست امن به یک endpoint مشخص با استفاده از POST.
     */
    public function sendSecurePostRequest(string $endpoint, array $payload): array
    {
        $this->logger->debug('Preparing secure API request.', [
            'endpoint' => $endpoint,
            'payload_keys' => array_keys($payload)
        ]);

        try {
            // 1. تولید timestamp و nonce
            $timestamp = time();
            $nonce = bin2hex(random_bytes(16));

            // 2. اطمینان از معتبر بودن payload
            $payload = array_filter($payload, function($value) {
                return $value !== null && $value !== '';
            });

            // 3. اضافه کردن timestamp به payload اگر وجود نداشته باشد
            if (!isset($payload['timestamp'])) {
                $payload['timestamp'] = $timestamp;
            }

            // 4. اضافه کردن هدرهای امنیتی
            $headers = [
                'X-API-Key: ' . $this->apiKey,
                'X-Timestamp: ' . $timestamp,
                'X-Nonce: ' . $nonce,
                'Content-Type: application/json',
                'Accept: application/json'
            ];

            // 5. تولید امضای درخواست
            $signature = $this->generateRequestSignature($endpoint, $payload, $timestamp, $nonce);
            $headers[] = 'X-Signature: ' . $signature;

            // 6. آماده‌سازی درخواست
            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
            $this->logger->debug('Sending request to API endpoint.', [
                'url' => $url,
                'headers' => array_map(function($header) {
                    return preg_replace('/^(X-API-Key|X-Signature): .*/', '$1: [REDACTED]', $header);
                }, $headers)
            ]);

            $ch = curl_init($url);
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if ($jsonPayload === false) {
                throw new Exception('Failed to encode payload: ' . json_last_error_msg());
            }

            // 7. تنظیمات CURL
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => false, // در محیط توسعه
                CURLOPT_SSL_VERIFYHOST => 0,     // در محیط توسعه
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_ENCODING => 'gzip, deflate',
                CURLOPT_VERBOSE => true
            ]);

            // 8. ارسال درخواست
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            
            // لاگ کردن جزئیات درخواست
            $this->logger->debug('API request details.', [
                'http_code' => $httpCode,
                'total_time' => $info['total_time'],
                'connect_time' => $info['connect_time'],
                'size_download' => $info['size_download'],
                'speed_download' => $info['speed_download'],
                'curl_error' => $error ?: 'none'
            ]);

            curl_close($ch);

            if ($error) {
                throw new Exception('CURL error: ' . $error);
            }

            // 9. بررسی پاسخ
            if ($httpCode >= 400) {
                $errorData = json_decode($response, true);
                $errorMessage = isset($errorData['message']) ? $errorData['message'] : 
                              (isset($errorData['error']) ? $errorData['error'] : 'Unknown API error');
                
                $this->logger->error('API request failed.', [
                    'endpoint' => $endpoint,
                    'http_code' => $httpCode,
                    'error_message' => $errorMessage,
                    'response' => $response
                ]);
                
                throw new Exception('API request failed with status code: ' . $httpCode . ' - ' . $errorMessage);
            }

            // 10. پردازش پاسخ
            $responseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Invalid JSON response from API.', [
                    'endpoint' => $endpoint,
                    'response' => $response,
                    'json_error' => json_last_error_msg()
                ]);
                throw new Exception('Invalid JSON response from API: ' . json_last_error_msg());
            }

            $this->logger->debug('API request successful.', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'response_time' => $info['total_time']
            ]);

            return $responseData;

        } catch (Exception $e) {
            $this->logger->error('API request failed.', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function generateRequestSignature($endpoint, array $payload, $timestamp, $nonce) {
        // 1. مرتب‌سازی پارامترها
        $params = [
            'endpoint' => $endpoint,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'payload' => json_encode($payload)
        ];
        ksort($params);

        // 2. ساخت رشته امضا
        $signatureString = implode('|', array_map(
            function($key, $value) {
                return $key . '=' . $value;
            },
            array_keys($params),
            array_values($params)
        ));

        // 3. تولید امضا با استفاده از HMAC-SHA3-512
        return hash_hmac('sha3-512', $signatureString, $this->apiSecret);
    }

    /**
     * ارسال درخواست برای اعتبارسنجی دوره‌ای لایسنس به سرور جدید لاراول.
     * از مکانیزم امنیتی جدید (API Key, Nonce, Timestamp, Signature) استفاده می‌کند.
     */
    public function verifyLicenseWithNewServer(string $licenseKey, string $domain, string $ip, string $hardwareId, ?string $rayId = null): array
    {
        $this->logger->info("ارسال درخواست اعتبارسنجی دوره‌ای لایسنس به سرور جدید.", [
            'license_key_prefix' => substr($licenseKey, 0, 5),
            'domain' => $domain
        ]);
        $endpoint = 'license/verify';
        $payload = [
            'license_key' => $licenseKey,
            'domain'      => $domain,
            'ip'          => $ip,
            'hardware_id' => $hardwareId,
            'ray_id'      => $rayId,
        ];

        try {
            $response = $this->sendSecurePostRequest($endpoint, $payload);
            if (!isset($response['valid']) && isset($response['error'])) {
                 $this->logger->warning("سرور اعتبارسنجی خطا برگرداند.", ['error' => $response['error'], 'message' => $response['message'] ?? '']);
                 return ['valid' => false, 'message' => $response['message'] ?? $response['error']];
            }
            if (!isset($response['valid'])) {
                 $this->logger->warning("پاسخ دریافتی از سرور اعتبارسنجی فاقد فیلد 'valid' است.", ['response' => $response]);
                 return ['valid' => false, 'message' => 'پاسخ نامعتبر از سرور اعتبارسنجی.'];
            }
            $this->logger->info("پاسخ اعتبارسنجی دوره‌ای لایسنس از سرور جدید دریافت شد.", [
                'valid' => $response['valid'],
                'message' => $response['message'] ?? null
            ]);
            return $response;
        } catch (Exception $e) {
            $this->logger->error("خطا در هنگام اعتبارسنجی لایسنس با سرور جدید.", [
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'license_key_prefix' => substr($licenseKey, 0, 5)
            ]);
            return [
                'valid' => false,
                'message' => 'خطا در ارتباط با سرور اعتبارسنجی: ' . $e->getMessage()
            ];
        }
    }

    /**
     * متد قدیمی validateLicenseOnServer (در صورت وجود) باید حذف یا بازنویسی شود
     * اگر دیگر از سیستم رمزنگاری قدیمی index.php استفاده نمی‌شود.
     * من آن را کامنت می‌کنم.
     */
    /*
    public function validateLicenseOnServer(string $domain, string $requestCode, string $licenseKey): array {
        // ... کد قدیمی که با index.php و رمزنگاری متقارن کار می‌کرد ...
    }
    */

    /**
     * متد قدیمی activateLicense (در صورت وجود) باید حذف یا بازنویسی شود
     * اگر دیگر از سیستم فعال‌سازی قدیمی استفاده نمی‌شود و فعال‌سازی از طریق پنل سرور انجام می‌شود.
     */
    /*
    public function activateLicense(string $licenseKey, string $domain, string $ip, string $hardwareId): array {
        // ... کد قدیمی ...
    }
    */

    // متد requestHandshake که قبلاً ایجاد کردیم، اینجا می‌تواند باقی بماند یا به اینجا منتقل شود.
    // اگر در فایل api.php است، اطمینان حاصل کنید که از همین ساختار getApiCredentials و ... استفاده می‌کند.
    public function requestHandshake(array $clientData): array
    {
        $endpoint = 'handshake';
        if (empty($this->baseUrl)) {
            $this->logger->error("Base URL برای ApiClient (جهت Handshake) تنظیم نشده است.");
            throw new Exception("API Base URL for Handshake not configured.");
        }
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $this->logger->info("ارسال درخواست Handshake.", ['url' => $url]);

        $ch = curl_init();
        if ($ch === false) {
            $this->logger->error("cURL init failed for handshake request.", ['url' => $url]);
            throw new Exception("cURL initialization failed for Handshake.");
        }
        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($clientData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_FAILONERROR => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $responseBody = curl_exec($ch);
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrorNum = curl_errno($ch);
            $curlErrorMsg = curl_error($ch);

            if ($curlErrorNum !== 0) {
                $this->logger->error("خطا در ارتباط API (cURL) برای Handshake.", ['url' => $url, 'errno' => $curlErrorNum, 'error' => $curlErrorMsg]);
                throw new Exception("Handshake API communication error: " . $curlErrorMsg, $curlErrorNum);
            }

            $this->logger->debug("پاسخ از Handshake دریافت شد.", ['url' => $url, 'status' => $httpStatusCode, 'response_length' => strlen($responseBody ?: '')]);
            $decodedResponse = json_decode($responseBody ?: '', true);

            if ($httpStatusCode !== 200 || json_last_error() !== JSON_ERROR_NONE || !isset($decodedResponse['handshake_string']) || !isset($decodedResponse['handshake_map']) || !isset($decodedResponse['system_id'])) {
                $serverMessage = $decodedResponse['message'] ?? $decodedResponse['error'] ?? 'Unknown server error during handshake';
                $this->logger->error("پاسخ نامعتبر از سرور Handshake.", [
                    'url' => $url, 'status' => $httpStatusCode, 'server_message' => $serverMessage,
                    'response_body_preview' => substr($responseBody ?: '', 0, 500), 'json_last_error_msg' => json_last_error_msg(),
                    'missing_keys' => array_diff(['handshake_string', 'handshake_map', 'system_id'], array_keys($decodedResponse ?? []))
                ]);
                throw new Exception("Invalid response from Handshake server: " . $serverMessage . " (Status: " . $httpStatusCode . ")");
            }

            $this->settingsRepository->set('api_handshake_string', $decodedResponse['handshake_string']);
            $this->settingsRepository->set('api_handshake_map', $decodedResponse['handshake_map']);
            $this->settingsRepository->set('api_handshake_expires', time() + ($decodedResponse['expires_in'] ?? 3600));
            $this->settingsRepository->set('api_system_id', $decodedResponse['system_id']);
            $this->logger->info("اطلاعات Handshake (شامل system_id) با موفقیت دریافت و در تنظیمات ذخیره شد.", ['system_id' => $decodedResponse['system_id']]);

            return $decodedResponse;

        } catch (Throwable $e) {
            if ($e instanceof Exception && $e->getCode() >= 100 && $e->getCode() < 600) throw $e;
            $this->logger->error("استثنای پیش‌بینی نشده در requestHandshake.", ['url' => $url, 'exception_message' => $e->getMessage()]);
            throw new Exception("Unexpected error during Handshake API request: " . $e->getMessage(), 0, $e);
        } finally {
            if (is_resource($ch) || (is_object($ch) && $ch instanceof \CurlHandle)) {
                curl_close($ch);
            }
        }
    }

    // متد getApplicationVersion (اگر نیاز باشد)
    private function getApplicationVersion(): string {
        return $this->config['app_config']['app_version'] ?? '1.0.0';
    }

    // متد obfuscateSensitiveData (اگر نیاز باشد)
    private function obfuscateSensitiveData(array $data): array {
        if (isset($data['license_key'])) {
            $data['license_key'] = substr($data['license_key'], 0, 5) . '...' . substr($data['license_key'], -5);
        }
        // ... سایر فیلدهای حساس
        return $data;
    }

    // متدهای encryptData و decryptResponse اگر دیگر استفاده نمی‌شوند، می‌توانند حذف شوند.
    // این متدها برای سیستم رمزنگاری قدیمی با index.php بودند.
    /*
    private function encryptData(array $data, string $secretKey): array {
        // ... کد قدیمی ...
    }
    private function decryptResponse($dataB64, $ivB64, $secretKey) {
        // ... کد قدیمی ...
    }
    */

    /**
     * تنظیم SecurityService بعد از ساخت اولیه
     * @param SecurityService $securityService
     */
    public function setSecurityService(SecurityService $securityService): void {
        $this->securityService = $securityService;
        $this->logger->debug("SecurityService updated in ApiClient.");
    }
}