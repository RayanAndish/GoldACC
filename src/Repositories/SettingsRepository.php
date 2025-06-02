<?php
namespace App\Repositories;

use PDO;
use Monolog\Logger;
use Throwable;

class SettingsRepository {

    protected PDO $db;
    protected Logger $logger;
    private array $settingsCache = []; // Simple cache for settings

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Gets a specific setting value by key.
     * Uses a simple cache to avoid repeated DB queries within a request.
     *
     * @param string $key The setting key.
     * @param mixed $default Default value if key not found.
     * @return mixed The setting value or default.
     */
    public function getSetting(string $key, mixed $default = null): mixed {
        // Check cache first
        if (array_key_exists($key, $this->settingsCache)) {
            return $this->settingsCache[$key];
        }

        try {
            $sql = "SELECT `value` FROM settings WHERE `key` = :key LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':key', $key, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetchColumn();

            if ($result !== false) {
                // Attempt to unserialize if it looks like serialized data
                $value = @unserialize($result);
                if ($value === false && $result !== serialize(false)) {
                     $value = $result; // Not serialized or error unserializing, use raw value
                 }
                $this->settingsCache[$key] = $value; // Cache the result
                return $value;
            } else {
                $this->settingsCache[$key] = $default; // Cache the default value
                return $default;
            }
        } catch (Throwable $e) {
            $this->logger->error("Error fetching setting.", ['key' => $key, 'exception' => $e]);
            return $default; // Return default on error
        }
    }

    /**
     * Gets all settings as an associative array [key => value].
     * Populates the cache.
     *
     * @return array Associative array of all settings.
     */
    public function getAllSettingsAsAssoc(): array {
         // If cache is already populated by multiple getSetting calls, use it?
         // Or always fetch fresh? Let's fetch fresh for now to ensure consistency.
         $this->settingsCache = []; // Clear cache before fetching all
         try {
            $sql = "SELECT `key`, `value` FROM settings";
            $stmt = $this->db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Fetch as key => value

            if ($results) {
                 foreach ($results as $key => $value) {
                     // Unserialize values
                     $unserializedValue = @unserialize($value);
                      if ($unserializedValue === false && $value !== serialize(false)) {
                          $this->settingsCache[$key] = $value; // Use raw if unserialize failed
                      } else {
                           $this->settingsCache[$key] = $unserializedValue;
                      }
                 }
            }
            return $this->settingsCache;

         } catch (Throwable $e) {
             $this->logger->error("Error fetching all settings.", ['exception' => $e]);
             return []; // Return empty on error
         }
    }

    /**
     * Saves multiple settings at once.
     * Expects an associative array [key => value].
     * Values will be serialized before saving.
     *
     * @param array $settings Associative array of settings to save.
     * @return bool True on success, false on failure.
     * @throws \PDOException On database error during transaction.
     */
    public function saveSettings(array $settings): bool {
        $this->logger->info("Attempting to save multiple settings.", ['keys' => array_keys($settings)]);
        $this->db->beginTransaction();
        try {
            // Use INSERT ... ON DUPLICATE KEY UPDATE for efficiency
            $sql = "INSERT INTO settings (`key`, `value`, `updated_at`) VALUES (:key, :value, NOW())
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()";
            $stmt = $this->db->prepare($sql);

            foreach ($settings as $key => $value) {
                $serializedValue = serialize($value);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $serializedValue);
                if (!$stmt->execute()) {
                     // If one fails, rollback immediately
                    $this->db->rollBack();
                    $this->logger->error("Failed to save setting.", ['key' => $key]);
                     // Clear cache as state is inconsistent
                    $this->settingsCache = [];
                    return false;
                }
                // Update cache immediately on successful save
                $this->settingsCache[$key] = $value;
            }

            $this->db->commit();
            $this->logger->info("Settings saved successfully.");
            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logger->error("Error saving settings.", ['exception' => $e]);
             // Clear cache as state is inconsistent
            $this->settingsCache = [];
            throw $e; // Rethrow the exception
        }
    }

    /**
     * خواندن مقدار یک کلید تنظیمات (ساده)
     */
    public function get(string $key, $default = null) {
        return $this->getSetting($key, $default);
    }

    /**
     * ذخیره مقدار یک کلید تنظیمات (ساده)
     */
    public function set(string $key, $value): bool {
        return $this->saveSettings([$key => $value]);
    }

} // End SettingsRepository