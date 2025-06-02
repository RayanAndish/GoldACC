<?php

namespace App\Services;

use Monolog\Logger;
use Exception;
use Throwable; // Catch errors and exceptions
use PDO;
use App\Services\ApiClient;

// Use the robust defuse/php-encryption library
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException; // Catch environment issues
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException; // Catch tampering/wrong key
use Defuse\Crypto\Exception\CryptoException; // General Defuse crypto exceptions

/**
 * SecurityService provides various security-related operations:
 * - Hardware ID generation (best effort).
 * - Secure password hashing and verification.
 * - Data encryption and decryption using defuse/php-encryption.
 * Requires a securely configured encryption key.
 */
class SecurityService {

    private Logger $logger;
    private array $config;
    private ?Key $encryptionKey = null; // defuse/php-encryption Key object (or null if not configured)
    private string $hardwareIdSalt;
    private string $requestCodeSalt; // Salt
    private ApiClient $apiClient;
    private PDO $db;

    /**
     * Constructor.
     * Loads configuration and attempts to load the encryption key.
     *
     * @param Logger $logger Logger instance.
     * @param ApiClient $apiClient API client instance.
     * @param PDO $db Database connection instance.
     * @param array $config Application configuration array (must contain security.encryption_key and security.hardware_id_salt).
     */
    public function __construct(
        Logger $logger, 
        ApiClient $apiClient, 
        PDO $db,
        array $config
    ) {
        $this->logger = $logger;
        $this->apiClient = $apiClient;
        $this->db = $db;
        $this->config = $config;
    
        // بارگذاری saltها فقط از فایل config
        if (empty($this->config['security']['hardware_id_salt']) || empty($this->config['security']['request_code_salt'])) {
            throw new \RuntimeException("Missing required security salt values in configuration (hardware_id_salt, request_code_salt).");
        }
    
        $this->hardwareIdSalt = $this->config['security']['hardware_id_salt'];
        $this->requestCodeSalt = $this->config['security']['request_code_salt'];
    
        // Load encryption key securely
        $this->loadEncryptionKey();
       
        $this->logger->debug("SecurityService initialized.");
        if ($this->encryptionKey === null) {
            $this->logger->warning("SecurityService initialized WITHOUT a valid encryption key. Encryption/Decryption disabled.");
        }
    }
    /**
     * Loads the encryption key from the configuration.
     * Handles potential errors during key loading.
     */
    private function loadEncryptionKey(): void {
        $encryptionKeyAscii = $this->config['security']['encryption_key'] ?? '';

        if (empty($encryptionKeyAscii)) {
            $this->logger->warning("SECURITY_ENCRYPTION_KEY is not configured in .env or config. Encryption/Decryption will be unavailable.");
            $this->encryptionKey = null;
            return;
        }

        try {
            // Load the key from the Ascii-Safe string format provided by defuse/php-encryption
            $this->encryptionKey = Key::loadFromAsciiSafeString($encryptionKeyAscii);
            $this->logger->info("Encryption key loaded successfully from configuration.");
        } catch (CryptoException | Throwable $e) { // Catch Defuse exceptions and general errors
            $this->logger->critical("FATAL ERROR: Failed to load encryption key from configured string. Check SECURITY_ENCRYPTION_KEY format and validity.", ['exception_type' => get_class($e), 'message' => $e->getMessage()]);
            $this->encryptionKey = null; // Ensure key is null on failure
            // In production, consider throwing an exception to halt startup if encryption is critical
            // if (($this->config['app']['env'] ?? 'development') === 'production') {
            //     throw new RuntimeException("System configuration error: Invalid or unloadable encryption key.", 0, $e);
            // }
        }
    }

    /**
     * Generates a unique hardware identifier for the server (best effort).
     * Attempts platform-specific commands first, then falls back to less reliable methods.
     *
     * @return string The generated hardware ID.
     * @throws Exception If unable to generate any form of ID (highly unlikely).
     */
    public function generateHardwareId(): string {
        $this->logger->debug("Generating hardware ID for shared hosting environment.");
        $hardwareId = '';
        $methodUsed = 'None';

        // ترکیب اطلاعات پایه که همیشه در دسترس هستند
        $baseData = [
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'server_name' => $_SERVER['SERVER_NAME'] ?? '',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? '',
            'server_port' => $_SERVER['SERVER_PORT'] ?? '',
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'php_os' => PHP_OS,
            'php_uname' => php_uname('s') . php_uname('r') . php_uname('m'),
            'hostname' => php_uname('n'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'session_save_path' => session_save_path(),
            'session_id' => session_id(),
            'salt' => $this->hardwareIdSalt
        ];

        // حذف مقادیر خالی و مرتب‌سازی برای اطمینان از یکسان بودن ترتیب
        $baseData = array_filter($baseData, function($value) {
            return !empty($value) && $value !== '0';
        });
        ksort($baseData);

        // ساخت رشته‌ای از تمام اطلاعات
        $combinedData = implode('|', array_map(function($key, $value) {
            return $key . ':' . $value;
        }, array_keys($baseData), $baseData));

        // تولید شناسه سخت‌افزار با استفاده از اطلاعات ترکیبی
        $hardwareId = hash('sha256', $combinedData);
        $methodUsed = 'Shared_Hosting_Hash';

        $this->logger->info("Hardware ID generated successfully for shared hosting.", [
            'method' => $methodUsed,
            'id' => $hardwareId,
            'data_points' => count($baseData)
        ]);

        return $hardwareId;
    }


    /**
     * Hashes a plain-text password using PHP's standard password_hash function.
     *
     * @param string $password The plain-text password.
     * @return string The resulting password hash.
     * @throws Exception If hashing fails.
     */
    public function hashPassword(string $password): string {
        try {
            // PASSWORD_DEFAULT is recommended as it will adapt to newer/stronger algorithms in future PHP versions
            // Consider increasing cost factor if needed, but default is generally good.
            // $options = ['cost' => 12];
            // $hash = password_hash($password, PASSWORD_DEFAULT, $options);
            $hash = password_hash($password, PASSWORD_DEFAULT);

            if ($hash === false) {
                // This indicates a fundamental problem with the password hashing setup.
                $this->logger->critical("password_hash() returned false. Check PHP configuration/version.");
                throw new Exception("Security error: Failed to hash password.");
            }
            $this->logger->debug("Password hashed successfully.");
            return $hash;
        } catch (Throwable $e) {
             $this->logger->critical("Error during password hashing.", ['exception' => $e]);
             throw new Exception("Security error: Could not process password.", 0, $e);
        }
    }

    /**
     * Verifies a plain-text password against a stored hash.
     * Uses PHP's standard password_verify function (timing-attack safe).
     *
     * @param string $password The plain-text password to verify.
     * @param string $hash The stored password hash.
     * @return bool True if the password matches the hash, false otherwise.
     */
    public function verifyPassword(string $password, string $hash): bool {
         // Basic check: Don't try to verify against an empty hash.
         if (empty($password) || empty($hash)) {
              return false;
         }
         try {
             // password_verify is designed to be safe against timing attacks.
             $result = password_verify($password, $hash);

             // Check if the hash needs rehashing (e.g., algorithm or cost changed)
             // You might want to rehash and update the user's password hash in the DB here if needed.
             if ($result && password_needs_rehash($hash, PASSWORD_DEFAULT /*, $options */)) {
                  $this->logger->info("Password needs rehashing.", ['username_or_userid' => 'unknown']); // Add user context if possible
                  // Trigger rehash logic (e.g., in UserRepository after successful login)
             }

             return $result;
         } catch (Throwable $e) {
            // Catch potential errors during verification (e.g., invalid hash format, though unlikely for valid hashes)
            $this->logger->error("Error during password_verify.", ['exception' => $e]);
            return false; // Treat errors as verification failure
        }
    }

    /**
     * Encrypts data using the configured encryption key (defuse/php-encryption).
     *
     * @param string $plaintext The data to encrypt.
     * @return string The encrypted data (ciphertext) in ASCII-safe format.
     * @throws Exception If encryption fails or the key is not configured/valid.
     * @throws EnvironmentIsBrokenException If the PHP environment is unsuitable for crypto.
     */
    public function encrypt(string $plaintext): string {
        if ($this->encryptionKey === null) {
             $this->logger->error("Attempted to encrypt data but encryption key is not available.");
             throw new Exception("System error: Encryption is not configured or key is invalid.");
        }
        try {
            // Encrypts and returns an ASCII-safe string
            $ciphertext = Crypto::encrypt($plaintext, $this->encryptionKey);
            $this->logger->debug("Data encrypted successfully.");
            return $ciphertext;
        } catch (EnvironmentIsBrokenException $e) {
             $this->logger->critical("PHP environment is unsuitable for cryptographic operations!", ['exception' => $e]);
             throw $e; // Rethrow critical environment error
        } catch (CryptoException | Throwable $e) { // Catch Defuse crypto errors and others
            $this->logger->error("Encryption failed.", ['exception_type' => get_class($e), 'message' => $e->getMessage()]);
            throw new Exception("Encryption operation failed.", 0, $e);
        }
    }

    /**
     * Decrypts data using the configured encryption key (defuse/php-encryption).
     *
     * @param string $ciphertext The ASCII-safe encrypted data.
     * @return string The original plaintext data.
     * @throws WrongKeyOrModifiedCiphertextException If decryption fails due to tampering or wrong key.
     * @throws Exception If decryption fails for other reasons or key is not configured/valid.
     * @throws EnvironmentIsBrokenException If the PHP environment is unsuitable for crypto.
     */
    public function decrypt(string $ciphertext): string {
        if ($this->encryptionKey === null) {
            $this->logger->error("Attempted to decrypt data but encryption key is not available.");
            throw new Exception("System error: Decryption is not configured or key is invalid.");
        }
        try {
            // Decrypts the ASCII-safe string
            $plaintext = Crypto::decrypt($ciphertext, $this->encryptionKey);
            $this->logger->debug("Data decrypted successfully.");
            return $plaintext;
        } catch (WrongKeyOrModifiedCiphertextException $e) {
            // This is a critical security event - data may be corrupt, tampered with, or wrong key used.
            $this->logger->error("Decryption failed: Wrong key or modified ciphertext.", ['exception_message' => $e->getMessage()]);
            // Rethrow the specific exception so the caller knows the nature of the failure.
            throw $e;
        } catch (EnvironmentIsBrokenException $e) {
            $this->logger->critical("PHP environment is unsuitable for cryptographic operations!", ['exception' => $e]);
            throw $e; // Rethrow critical environment error
        } catch (CryptoException | Throwable $e) { // Catch other Defuse crypto errors and general errors
            $this->logger->error("Decryption failed.", ['exception_type' => get_class($e), 'message' => $e->getMessage()]);
            throw new Exception("Decryption operation failed.", 0, $e);
        }
    }

    /**
     * Generates a new, random encryption key in ASCII-safe format.
     * Useful for initial setup or key rotation procedures.
     * **The generated key MUST be stored securely (e.g., in .env).**
     *
     * @return string The new encryption key as an ASCII-safe string.
     * @throws Exception If key generation fails.
     * @throws EnvironmentIsBrokenException If the PHP environment is unsuitable for crypto.
     */
    public function generateNewEncryptionKey(): string {
         $this->logger->info("Generating a new random encryption key.");
         try {
             $key = Key::createNewRandomKey();
             $asciiSafeKey = $key->saveToAsciiSafeString();
             $this->logger->info("New encryption key generated successfully. Store this securely!");
             return $asciiSafeKey;
         } catch (EnvironmentIsBrokenException $e) {
             $this->logger->critical("PHP environment is unsuitable for generating keys!", ['exception' => $e]);
             throw $e; // Rethrow critical environment error
         } catch (CryptoException | Throwable $e) {
             $this->logger->critical("Failed to generate new encryption key.", ['exception' => $e]);
             throw new Exception("Failed to generate a new encryption key.", 0, $e);
         }
    }
    /**
     * تولید کد درخواست لایسنس برای مکانیزم قدیمی (بر اساس ip، دامنه، ray_id و salt)
     * @param string $ip
     * @param string $domain
     * @param string $rayId
     * @return array اطلاعات مورد نیاز برای فعال‌سازی شامل کد درخواست و شناسه سخت‌افزاری
     */
    public function generateLegacyRequestCode(string $ip, string $domain, string $rayId): array {
        try {
            // بررسی وجود داده‌های قبلی در session
            if (isset($_SESSION['activation_data'])) {
                $this->logger->info('Using existing activation data from session', [
                    'hardware_id' => substr($_SESSION['activation_data']['hardware_id'], 0, 10) . '...',
                    'domain' => $_SESSION['activation_data']['domain']
                ]);
                return $_SESSION['activation_data'];
            }

            // 1. تولید client_nonce
            $clientNonce = bin2hex(random_bytes(16));
            
            // 2. ذخیره client_nonce در session
            $_SESSION['activation_client_nonce'] = $clientNonce;
            
            // 3. دریافت hardware_id و اطمینان از تمیز بودن آن
            $hardwareId = $this->generateHardwareId();
            
            // 4. اطمینان از معتبر بودن domain
            $domain = filter_var($domain, FILTER_SANITIZE_STRING);
            if (empty($domain)) {
                $domain = 'localhost';
            }
            
            // 5. اطمینان از معتبر بودن IP
            $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
            
            // 6. اطمینان از معتبر بودن ray_id
            if (empty($rayId) || strlen($rayId) !== 32) {
                $rayId = bin2hex(random_bytes(16));
            }
            
            // 7. ارسال درخواست اولیه به سرور برای دریافت server_nonce
            $initialPayload = [
                'hardware_id' => $hardwareId,
                'domain' => $domain,
                'client_nonce' => $clientNonce,
                'ip' => $ip,
                'ray_id' => $rayId,
                'timestamp' => time()
            ];
            
            $this->logger->debug('Sending activation initiation request.', [
                'payload' => array_merge($initialPayload, [
                    'hardware_id' => '***'
                ])
            ]);
            
            $response = $this->apiClient->sendSecurePostRequest(
                'license/initiate-activation',
                $initialPayload
            );
            
            if (!isset($response['server_nonce']) || !isset($response['challenge'])) {
                throw new Exception('Invalid server response: missing required fields');
            }
            
            // 8. ذخیره پاسخ سرور در session
            $_SESSION['activation_server_nonce'] = $response['server_nonce'];
            $_SESSION['activation_challenge'] = $response['challenge'];
            
            // 9. تولید کد درخواست با استفاده از nonce‌ها
            $requestCode = $this->generateChallengeRequestCode(
                $clientNonce,
                $response['server_nonce']
            );
            
            // 10. ذخیره تمام اطلاعات فعال‌سازی در session
            $activationData = [
                'request_code' => $requestCode,
                'hardware_id' => $hardwareId,
                'domain' => $domain,
                'ip' => $ip,
                'ray_id' => $rayId,
                'timestamp' => time()
            ];
            
            $_SESSION['activation_data'] = $activationData;
            
            $this->logger->info('Activation initiation successful.', [
                'server_nonce' => $response['server_nonce'],
                'challenge' => substr($response['challenge'], 0, 10) . '...',
                'hardware_id' => substr($hardwareId, 0, 10) . '...'
            ]);
            
            return $activationData;
            
        } catch (Exception $e) {
            $this->logger->error('Error in activation initiation.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception('Failed to generate request code: ' . $e->getMessage());
        }
    }

    /**
     * تولید کد درخواست برای مکانیزم Challenge-Response جدید
     * @param string $clientNonce
     * @param string $serverNonce
     * @return string
     */
    public function generateChallengeRequestCode(string $clientNonce, string $serverNonce): string {
        // تولید کد درخواست با استفاده از nonce‌ها و timestamp
        $timestamp = time();
        $data = $clientNonce . $serverNonce . $timestamp;
        return hash('sha256', $data);
    }

    /**
     * Hashes data with a specific salt and iterations, suitable for request codes.
     *
     * @param string $dataToHash Combined data string (e.g., domain|ip|ray_id|hardware_id|timestamp).
     * @param int $iterations Number of hashing rounds.
     * @return string The resulting hash.
     * @throws Exception If hashing fails.
     */
    public function hashRequestData(string $dataToHash, int $iterations = 5000): string {
        if (empty($this->requestCodeSalt)) {
             $this->logger->error("Request code salt is not configured in SecurityService.");
             throw new Exception("System configuration error: Request code salt missing.");
        }
         if ($iterations < 1000) { // Enforce minimum iterations
             $iterations = 1000;
             $this->logger->warning("Request code hash iterations was below minimum, set to 1000.");
         }

         $this->logger->debug("Hashing request data.", ['iterations' => $iterations]);
         $hash = hash('sha256', $dataToHash . $this->requestCodeSalt); // Initial hash

         for ($i = 0; $i < $iterations; $i++) {
             $hash = hash('sha256', $hash . $dataToHash . $this->requestCodeSalt . $i); // Re-hash iteratively
         }

         $this->logger->debug("Request data hashed successfully.", ['hash_prefix' => substr($hash, 0, 10)]);
         return $hash;
    }
 
    /**
     * Generates a cryptographically secure random token (e.g., for CSRF, password reset).
     *
     * @param int $lengthBytes The desired length of the raw token bytes (default 32).
     * @return string The random token encoded in hexadecimal.
     * @throws Exception If random bytes generation fails.
     */
    public function generateRandomToken(int $lengthBytes = 32): string {
        try {
            $bytes = random_bytes($lengthBytes);
            return bin2hex($bytes);
        } catch (Throwable $e) { // Catches Exception, Error
            $this->logger->error("Failed to generate random token using random_bytes.", ['exception' => $e]);
            // Fallback (less ideal, but better than nothing if random_bytes fails unexpectedly)
            try {
                 return bin2hex(openssl_random_pseudo_bytes($lengthBytes));
            } catch (Throwable $e2) {
                 $this->logger->critical("Failed to generate random token using openssl fallback.", ['exception' => $e2]);
                 throw new Exception("Could not generate secure random token.", 0, $e2);
            }
        }
    }

    // --- Rate Limiting & Blocking related methods ---

    /**
     * Checks if login attempts from an IP or for a specific username are currently blocked.
     * **Requires implementation in UserRepository or a dedicated RateLimitRepository.**
     *
     * @param string $ip IP address.
     * @param string|null $username Optional username to check for user-specific blocking.
     * @return bool True if blocked, false otherwise.
     */
    public function isLoginBlocked(string $ip, ?string $username = null): bool {
        $this->logger->warning("SecurityService::isLoginBlocked is a placeholder. Requires implementation.");
        // --- Implementation Logic ---
        // 1. Query `blocked_ips` table via UserRepository/RateLimitRepository for the IP.
        // 2. Check if block_until is in the future.
        // 3. Optionally, check for username-specific blocks if implemented.
        // --- End Implementation Logic ---
        return false; // Placeholder
    }

    /**
     * Clears failed login attempts count/history for a given IP/username.
     * Called after a successful login.
     * **Requires implementation in UserRepository or a dedicated RateLimitRepository.**
     *
     * @param string $ip IP address.
     * @param string|null $username Optional username.
     */
    public function clearLoginAttempts(string $ip, ?string $username = null): void {
        $this->logger->warning("SecurityService::clearLoginAttempts is a placeholder. Requires implementation.");
        // --- Implementation Logic ---
        // 1. Delete relevant entries from `login_attempts` table via UserRepository/RateLimitRepository.
        // 2. Optionally, remove any active block from `blocked_ips` table for this IP/user.
        // --- End Implementation Logic ---
    }

    /**
     * Checks recent failed login attempts and blocks the IP/user if the threshold is exceeded.
     * Called after a failed login attempt.
     * **Requires implementation in UserRepository or a dedicated RateLimitRepository.**
     *
     * @param string $ip IP address.
     * @param string|null $username Optional username.
     */
    public function checkAndBlockLoginAttempts(string $ip, ?string $username = null): void {
         $this->logger->warning("SecurityService::checkAndBlockLoginAttempts is a placeholder. Requires implementation.");
        // --- Implementation Logic ---
        // 1. Count recent failed attempts for the IP (and optionally username) from `login_attempts` table (e.g., within last 15 minutes).
        // 2. Compare count against `$this->config['app']['max_login_attempts']`.
        // 3. If threshold exceeded:
        //    a. Calculate `block_until` timestamp (now + `$this->config['app']['login_block_time']`).
        //    b. Insert or update a record in `blocked_ips` table for the IP (and optionally username).
        //    c. Log the blocking action.
        // --- End Implementation Logic ---
    }

    public function initiateActivation($clientNonce, $domain, $ip): array {
        try {
            $this->logger->info('Initiating license activation process', [
                'domain' => $domain,
                'ip' => $ip,
                'ray_id' => $this->generateRayId()
            ]);

            // ارسال درخواست به سرور
            $response = $this->apiClient->sendRequest('license/initiate-activation', [
                'client_nonce' => $clientNonce,
                'domain' => $domain,
                'ip' => $ip,
                'ray_id' => $this->generateRayId()
            ]);

            if (!isset($response['salt']) || !isset($response['server_nonce'])) {
                throw new Exception('Invalid server response: missing required fields');
            }

            // تولید hardware_id با استفاده از متد اصلی
            $hardwareId = $this->generateHardwareId();

            // ذخیره در دیتابیس
            $stmt = $this->db->prepare("
                INSERT INTO licenses (
                    hardware_id, domain, client_nonce, server_nonce, 
                    salt, activation_ip, status, created_at
                ) VALUES (
                    :hardware_id, :domain, :client_nonce, :server_nonce,
                    :salt, :activation_ip, 'pending', NOW()
                )
            ");

            $stmt->execute([
                'hardware_id' => $hardwareId,
                'domain' => $domain,
                'client_nonce' => $clientNonce,
                'server_nonce' => $response['server_nonce'],
                'salt' => $response['salt'],
                'activation_ip' => $ip
            ]);

            // لاگ کردن
            $stmt = $this->db->prepare("
                INSERT INTO license_activation_logs (
                    action_type, hardware_id, domain, ip_address,
                    ray_id, status, message, created_at
                ) VALUES (
                    'initiate', :hardware_id, :domain, :ip_address,
                    :ray_id, 'success', 'Activation initiation successful', NOW()
                )
            ");

            $stmt->execute([
                'hardware_id' => $hardwareId,
                'domain' => $domain,
                'ip_address' => $ip,
                'ray_id' => $response['ray_id']
            ]);

            return [
                'hardware_id' => $hardwareId,
                'salt' => $response['salt'],
                'server_nonce' => $response['server_nonce'],
                'ray_id' => $response['ray_id']
            ];

        } catch (Exception $e) {
            $this->logger->error('Activation initiation failed', [
                'error' => $e->getMessage(),
                'domain' => $domain,
                'ip' => $ip
            ]);

            // لاگ کردن خطا
            $stmt = $this->db->prepare("
                INSERT INTO license_activation_logs (
                    action_type, domain, ip_address, ray_id,
                    status, message, created_at
                ) VALUES (
                    'initiate', :domain, :ip_address, :ray_id,
                    'failed', :message, NOW()
                )
            ");

            $stmt->execute([
                'domain' => $domain,
                'ip_address' => $ip,
                'ray_id' => $this->generateRayId(),
                'message' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function getMachineSpecificData(): string {
        return $this->generateMachineSpecificData();
    }

    /**
     * تولید داده‌های ماشین برای استفاده در تولید کلید ماشین
     * @return string داده‌های ماشین به صورت رشته
     */
    private function generateMachineSpecificData(): string {
        try {
            // جمع‌آوری اطلاعات پایه ماشین
            $data = [
                'hostname' => gethostname(),
                'os' => PHP_OS,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
                'server_name' => $_SERVER['SERVER_NAME'] ?? '',
                'server_addr' => $_SERVER['SERVER_ADDR'] ?? '',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'php_uname' => php_uname('s') . php_uname('r') . php_uname('m'),
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'session_save_path' => session_save_path(),
                'session_id' => session_id(),
                'salt' => $this->hardwareIdSalt
            ];

            // حذف مقادیر خالی و مرتب‌سازی برای اطمینان از یکسان بودن ترتیب
            $data = array_filter($data, function($value) {
                return !empty($value) && $value !== '0';
            });
            ksort($data);

            // ساخت رشته‌ای از تمام اطلاعات
            $combinedData = implode('|', array_map(function($key, $value) {
                return $key . ':' . $value;
            }, array_keys($data), $data));

            $this->logger->debug('Machine specific data generated', [
                'data_points' => count($data)
            ]);

            return $combinedData;

        } catch (Exception $e) {
            $this->logger->error('Error generating machine specific data', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to generate machine specific data: ' . $e->getMessage());
        }
    }

    /**
     * تولید کلید ماشین برای استفاده در تولید request_code
     * @return string کلید ماشین
     */
    public function generateMachineKey(): string {
        try {
            $machineData = $this->getMachineSpecificData();
            
            // استفاده از app.security.encryption_key به جای security.encryption_key
            if (empty($this->config['app']['security']['encryption_key'])) {
                throw new Exception('Encryption key is not configured in security settings');
            }
            
            return hash_hmac('sha256', $machineData, $this->config['app']['security']['encryption_key']);
        } catch (Exception $e) {
            $this->logger->error('Error generating machine key', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to generate machine key: ' . $e->getMessage());
        }
    }

    public function completeActivation(): array {
        $maxRetries = 3;
        $retryDelay = 1; // seconds
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $this->apiClient->sendSecurePostRequest('license/complete-activation', [
                    'hardware_id' => $_SESSION['activation_data']['hardware_id'],
                    'domain' => $_SESSION['activation_data']['domain'],
                    'ip' => $_SESSION['activation_data']['ip'],
                    'ray_id' => $_SESSION['ray_id'],
                    'server_nonce' => $_SESSION['activation_data']['server_nonce']
                ]);

                if (!$response || !isset($response['status']) || $response['status'] !== 'success') {
                    $lastError = $response['message'] ?? 'Unknown error';
                    
                    // اگر خطای Challenge expired نباشد، تلاش مجدد نکنیم
                    if (strpos($lastError, 'Challenge expired') === false) {
                        break;
                    }
                    
                    $this->logger->warning("Activation attempt {$attempt} failed with Challenge expired error.", [
                        'error' => $lastError
                    ]);
                    
                    if ($attempt < $maxRetries) {
                        sleep($retryDelay);
                        continue;
                    }
                }

                return [
                    'success' => true,
                    'request_code' => $response['request_code'] ?? null
                ];

            } catch (Exception $e) {
                $lastError = $e->getMessage();
                $this->logger->error("Error in activation attempt {$attempt}", [
                    'error' => $lastError,
                    'attempt' => $attempt
                ]);
                
                if ($attempt < $maxRetries) {
                    sleep($retryDelay);
                    continue;
                }
            }
        }

        return [
            'success' => false,
            'message' => $lastError ?? 'Failed to complete activation after ' . $maxRetries . ' attempts'
        ];
    }

    public function completeActivationWithLicense(string $licenseKey): array {
        $maxRetries = 3;
        $retryDelay = 1; // seconds
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $this->apiClient->sendSecurePostRequest('license/complete-activation', [
                    'hardware_id' => $_SESSION['activation_data']['hardware_id'],
                    'domain' => $_SESSION['activation_data']['domain'],
                    'ip' => $_SESSION['activation_data']['ip'],
                    'ray_id' => $_SESSION['ray_id'],
                    'server_nonce' => $_SESSION['activation_data']['server_nonce'],
                    'license_key' => $licenseKey
                ]);

                if (!$response || !isset($response['status']) || $response['status'] !== 'success') {
                    $lastError = $response['message'] ?? 'Unknown error';
                    
                    // اگر خطای Challenge expired نباشد، تلاش مجدد نکنیم
                    if (strpos($lastError, 'Challenge expired') === false) {
                        break;
                    }
                    
                    $this->logger->warning("License activation attempt {$attempt} failed with Challenge expired error.", [
                        'error' => $lastError
                    ]);
                    
                    if ($attempt < $maxRetries) {
                        sleep($retryDelay);
                        continue;
                    }
                }

                return [
                    'success' => true,
                    'message' => 'License activated successfully'
                ];

            } catch (Exception $e) {
                $lastError = $e->getMessage();
                $this->logger->error("Error in license activation attempt {$attempt}", [
                    'error' => $lastError,
                    'attempt' => $attempt
                ]);
                
                if ($attempt < $maxRetries) {
                    sleep($retryDelay);
                    continue;
                }
            }
        }

        return [
            'success' => false,
            'message' => $lastError ?? 'Failed to activate license after ' . $maxRetries . ' attempts'
        ];
    }

} 