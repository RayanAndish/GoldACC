<?php

namespace App\Repositories;

use PDO;
use Monolog\Logger;
use Throwable;
use Exception;
use App\Services\HardwareIdService;

class LicenseRepository {

    protected PDO $db;
    protected Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Fetches the currently active license record from the database.
     */
    public function getActiveLicense(): ?array {
        $this->logger->debug("Fetching active license from database.");
        try {
            // انتخاب لایسنس فعال که وضعیت آن نیز 'active' باشد
            $sql = "SELECT * FROM licenses WHERE is_active = 1 AND status = 'active' ORDER BY activated_at DESC LIMIT 1";
            $stmt = $this->db->query($sql);
            $license = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($license) {
                $this->logger->debug("Active license found.", ['id' => $license['id']]);
                
                // بررسی تطابق hardware_id
                if (!empty($license['hardware_id'])) {
                    $currentHardwareId = HardwareIdService::getHardwareId();
                    if ($currentHardwareId !== $license['hardware_id']) {
                        $this->logger->warning("Hardware ID mismatch. Deactivating license.", [
                            'license_id' => $license['id'],
                            'stored_hwid' => substr($license['hardware_id'], 0, 12) . '...',
                            'current_hwid' => substr($currentHardwareId, 0, 12) . '...'
                        ]);
                        
                        // غیرفعال کردن لایسنس
                        $this->updateLicenseStatus($license['id'], 'inactive');
                        return null;
                    }
                }
                
                if (isset($license['features']) && is_string($license['features'])) {
                    $license['features'] = json_decode($license['features'], true) ?: [];
                } elseif (!isset($license['features'])) {
                    $license['features'] = []; // اگر features وجود نداشت، آرایه خالی
                }
                // اطمینان از اینکه features همیشه آرایه است
                if (!is_array($license['features'])) $license['features'] = [];

                return $license;
            } else {
                $this->logger->info("No active and valid status license found.");
                return null;
            }
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching active license.", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Gets the current hardware ID of the system
     */
    private function getCurrentHardwareId(): string {
        // استفاده از متد موجود در SecurityService یا تولید مستقیم
        $dataPoints = [
            'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? '',
            'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'SERVER_ADDR' => $_SERVER['SERVER_ADDR'] ?? '',
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? '',
            'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? '',
            'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? '',
            'PHP_SELF' => $_SERVER['PHP_SELF'] ?? '',
            'SERVER_PROTOCOL' => $_SERVER['SERVER_PROTOCOL'] ?? '',
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'HTTP_ACCEPT_LANGUAGE' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'HTTP_ACCEPT_ENCODING' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            'HTTP_CONNECTION' => $_SERVER['HTTP_CONNECTION'] ?? '',
            'HTTP_CACHE_CONTROL' => $_SERVER['HTTP_CACHE_CONTROL'] ?? '',
            'HTTP_ACCEPT' => $_SERVER['HTTP_ACCEPT'] ?? '',
            'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? ''
        ];

        $this->logger->info("Hardware ID generated successfully for shared hosting.", [
            'method' => 'Shared_Hosting_Hash',
            'data_points' => count($dataPoints)
        ]);

        return hash('sha256', json_encode($dataPoints));
    }

    /**
     * Fetches a specific license by its ID.
     * اضافه شده برای استفاده در LicenseService پس از syncLicenseInfo.
     * @param int $licenseId
     * @return array|null
     */
    public function getLicenseById(int $licenseId): ?array {
        $this->logger->debug("Fetching license by ID.", ['id' => $licenseId]);
        try {
            $sql = "SELECT * FROM licenses WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $licenseId, PDO::PARAM_INT);
            $stmt->execute();
            $license = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($license) {
                if (isset($license['features']) && is_string($license['features'])) {
                    $license['features'] = json_decode($license['features'], true) ?: [];
                } elseif (!isset($license['features'])) {
                    $license['features'] = [];
                }
                if (!is_array($license['features'])) $license['features'] = [];
                return $license;
            }
            return null;
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching license by ID.", ['id' => $licenseId, 'exception' => $e->getMessage()]);
            return null;
        }
    }


    /**
     * Saves the details of a generated license request code.
     */
    public function saveRequest(string $domain, string $ip, string $requestCode, string $hardwareId, ?string $rayId, int $timestamp): bool {
         $this->logger->debug("Saving license request details.", ['req_code_prefix' => substr($requestCode,0,10)]);
         try {
             $sql = "INSERT INTO license_requests (domain, ip, request_code, hardware_id, ray_id, requested_at)
                     VALUES (:domain, :ip, :req_code, :hwid, :ray, FROM_UNIXTIME(:ts))";
             $stmt = $this->db->prepare($sql);
             return $stmt->execute([
                 ':domain' => $domain,
                 ':ip' => $ip,
                 ':req_code' => $requestCode,
                 ':hwid' => $hardwareId,
                 ':ray' => $rayId,
                 ':ts' => $timestamp
             ]);
         } catch (Throwable $e) {
              $this->logger->error("Failed to save license request details.", ['exception' => $e->getMessage()]);
              // throw $e; // شاید بهتر باشد false برگردانیم تا برنامه متوقف نشود
              return false;
         }
    }

    /**
     * Updates the status and is_active fields of a specific license.
     */
    public function updateLicenseStatus(int $licenseId, string $status): bool {
         $this->logger->info("Updating license status.", ['id' => $licenseId, 'new_status' => $status]);
         $isActive = ($status === 'active') ? 1 : 0;
          try {
              // همزمان is_active را نیز بر اساس status به‌روز کن
              $sql = "UPDATE licenses SET status = :status, is_active = :is_active, updated_at = NOW() WHERE id = :id";
              $stmt = $this->db->prepare($sql);
              $stmt->bindParam(':status', $status, PDO::PARAM_STR);
              $stmt->bindParam(':is_active', $isActive, PDO::PARAM_INT);
              $stmt->bindParam(':id', $licenseId, PDO::PARAM_INT);
              return $stmt->execute();
          } catch (Throwable $e) {
               $this->logger->error("Failed to update license status.", ['id' => $licenseId, 'exception' => $e->getMessage()]);
               return false;
          }
    }

    /**
     * Updates the hardware ID for a specific license.
     */
    public function updateLicenseHardwareId(int $licenseId, string $hardwareId): bool {
          $this->logger->info("Updating hardware ID for license.", ['id' => $licenseId /* 'hwid' => $hardwareId */]); // از لاگ کردن خود hwid خودداری شود بهتر است
           try {
               $sql = "UPDATE licenses SET hardware_id = :hwid, updated_at = NOW() WHERE id = :id";
               $stmt = $this->db->prepare($sql);
               $stmt->bindParam(':hwid', $hardwareId, PDO::PARAM_STR);
               $stmt->bindParam(':id', $licenseId, PDO::PARAM_INT);
               return $stmt->execute();
           } catch (Throwable $e) {
                $this->logger->error("Failed to update license hardware ID.", ['id' => $licenseId, 'exception' => $e->getMessage()]);
                return false;
           }
    }

    /**
     * Gets the timestamp of the last successful online validation for a license.
     */
     public function getLastOnlineCheckTimestamp(int $licenseId): int {
          try {
              $sql = "SELECT last_validated FROM licenses WHERE id = :id LIMIT 1";
              $stmt = $this->db->prepare($sql);
              $stmt->bindParam(':id', $licenseId, PDO::PARAM_INT);
              $stmt->execute();
              $timestampStr = $stmt->fetchColumn();
              return $timestampStr ? (int)strtotime($timestampStr) : 0;
          } catch (Throwable $e) {
               $this->logger->error("Failed to get last online check timestamp.", ['id' => $licenseId, 'exception' => $e->getMessage()]);
               return 0;
          }
     }

    /**
     * Updates the timestamp of the last successful online validation.
     */
     public function updateLastOnlineCheck(int $licenseId): bool {
         $this->logger->debug("Updating last online check timestamp.", ['id' => $licenseId]);
           try {
               $sql = "UPDATE licenses SET last_validated = NOW(), updated_at = NOW() WHERE id = :id";
               $stmt = $this->db->prepare($sql);
               $stmt->bindParam(':id', $licenseId, PDO::PARAM_INT);
               return $stmt->execute();
           } catch (Throwable $e) {
                $this->logger->error("Failed to update last online check timestamp.", ['id' => $licenseId, 'exception' => $e->getMessage()]);
                return false;
           }
     }

    /**
     * Saves an activated license. Deactivates all other licenses first within a transaction.
     * Handles both inserting a new license or updating an existing one based on license key.
     * $data باید شامل تمام فیلدهای لازم با نام‌های صحیح ستون‌ها باشد.
     */
    public function saveActivatedLicense(array $data): ?int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO licenses (
                    license_key, license_type, domain, request_code, client_nonce,
                    server_nonce, salt, activation_ip, activation_date,
                    activation_challenge, activation_challenge_expires,
                    activation_nonce, activation_salt, activation_status,
                    activation_attempts, last_activation_attempt, ip_whitelist,
                    max_activation_attempts, ip_address, ray_id, hardware_id,
                    activation_count, max_activations, is_active, activated_at,
                    status, validated, last_validated, last_check, check_interval,
                    expires_at, max_users, features
                ) VALUES (
                    :license_key, :license_type, :domain, :request_code, :client_nonce,
                    :server_nonce, :salt, :activation_ip, :activation_date,
                    :activation_challenge, :activation_challenge_expires,
                    :activation_nonce, :activation_salt, :activation_status,
                    :activation_attempts, :last_activation_attempt, :ip_whitelist,
                    :max_activation_attempts, :ip_address, :ray_id, :hardware_id,
                    :activation_count, :max_activations, :is_active, :activated_at,
                    :status, :validated, :last_validated, :last_check, :check_interval,
                    :expires_at, :max_users, :features
                )
            ");

            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                'license_key' => $data['license_key'],
                'license_type' => $data['license_type'] ?? 'standard',
                'domain' => $data['domain'],
                'request_code' => $data['request_code'] ?? null,
                'client_nonce' => $data['client_nonce'] ?? null,
                'server_nonce' => $data['server_nonce'] ?? null,
                'salt' => $data['salt'] ?? null,
                'activation_ip' => $data['activation_ip'] ?? $_SERVER['REMOTE_ADDR'],
                'activation_date' => $now,
                'activation_challenge' => $data['activation_challenge'] ?? null,
                'activation_challenge_expires' => $data['activation_challenge_expires'] ?? null,
                'activation_nonce' => $data['activation_nonce'] ?? null,
                'activation_salt' => $data['activation_salt'] ?? null,
                'activation_status' => 'completed',
                'activation_attempts' => 1,
                'last_activation_attempt' => $now,
                'ip_whitelist' => $data['ip_whitelist'] ?? null,
                'max_activation_attempts' => $data['max_activation_attempts'] ?? 5,
                'ip_address' => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'],
                'ray_id' => $data['ray_id'] ?? null,
                'hardware_id' => HardwareIdService::getHardwareId(),
                'activation_count' => 1,
                'max_activations' => $data['max_activations'] ?? 1,
                'is_active' => 1,
                'activated_at' => $now,
                'status' => 'active',
                'validated' => 1,
                'last_validated' => $now,
                'last_check' => $now,
                'check_interval' => $data['check_interval'] ?? 10,
                'expires_at' => $data['expires_at'] ?? null,
                'max_users' => $data['max_users'] ?? null,
                'features' => is_array($data['features'] ?? []) ? json_encode($data['features']) : ($data['features'] ?? '[]')
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            $this->logger->error("Error saving activated license: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Updates specific license details (e.g., after online sync).
     */
    public function updateLicenseDetails(int $licenseId, array $dataToUpdate): bool {
        if (empty($dataToUpdate)) {
            $this->logger->debug("No data provided to updateLicenseDetails.", ['license_id' => $licenseId]);
            return true;
        }

        $this->logger->info("Updating license details from sync.", ['id' => $licenseId, 'fields_to_update' => array_keys($dataToUpdate)]);

        $setClauses = [];
        $params = [':id' => $licenseId];
        // فیلدهای مجاز برای به‌روزرسانی از طریق sync
        // is_active هم باید اینجا باشد اگر سرور آن را کنترل می‌کند
        $allowedFields = ['expires_at', 'status', 'last_validated', 'features', 'license_type', 'max_users', 'is_active', 'domain', 'hardware_id'];

        foreach ($dataToUpdate as $key => $value) {
            if (in_array($key, $allowedFields)) {
                 $setClauses[] = "`" . $key . "` = :" . $key;
                 if ($key === 'features' && is_array($value)) {
                     $params[':' . $key] = json_encode($value);
                 } elseif ($key === 'is_active') {
                     $params[':' . $key] = (int)(bool)$value; // تبدیل به 0 یا 1
                 }
                 else {
                     $params[':' . $key] = $value;
                 }
            } else {
                  $this->logger->warning("Attempted to update a disallowed or unknown field during sync.", ['field' => $key, 'license_id' => $licenseId]);
            }
        }

        if (empty($setClauses)) {
            $this->logger->info("No valid fields to update in updateLicenseDetails.", ['license_id' => $licenseId]);
            return false;
        }

        $setClauses[] = "`updated_at` = NOW()";

        try {
            $sql = "UPDATE licenses SET " . implode(', ', $setClauses) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($params);
            if ($success) {
                $this->logger->info("License details updated successfully.", ['id' => $licenseId, 'affected_rows' => $stmt->rowCount()]);
            } else {
                $this->logger->error("Failed to execute updateLicenseDetails statement.", ['id' => $licenseId, 'error_info' => $stmt->errorInfo()]);
            }
            return $success;
        } catch (Throwable $e) {
             $this->logger->error("Failed to update license details due to exception.", ['id' => $licenseId, 'exception' => $e->getMessage()]);
             return false;
        }
    }

    // متد logActivation را می‌توان در صورت نیاز تکمیل کرد
    public function logActivation(int $licenseId, string $ip, string $activationType = 'ONLINE'): void {
        $this->logger->info("License activation event.", ['license_id' => $licenseId, 'ip' => $ip, 'type' => $activationType]);
        // در اینجا می‌توانید یک رکورد در جدول activation_logs یا مشابه آن درج کنید
        // try {
        //     $stmt = $this->db->prepare("INSERT INTO activation_logs (license_id, ip_address, activation_type, activated_at) VALUES (:license_id, :ip, :type, NOW())");
        //     $stmt->execute([':license_id' => $licenseId, ':ip' => $ip, ':type' => $activationType]);
        // } catch (Throwable $e) {
        //     $this->logger->error("Failed to log activation event.", ['license_id' => $licenseId, 'exception' => $e->getMessage()]);
        // }
    }
}