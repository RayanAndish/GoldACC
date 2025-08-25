<?php
// src/Repositories/CoinInventoryRepository.php
namespace App\Repositories;

use PDO;
use Monolog\Logger;
use Exception;
use Throwable; // Import Throwable

class CoinInventoryRepository {

    private PDO $db;
    private Logger $logger; // Added Logger

    public function __construct(PDO $db, Logger $logger) { // Added Logger to constructor
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Retrieves all coin inventory items.
     * This provides the raw coin types and quantities stored.
     */
    public function getAllCoinInventory(): array {
        try {
            $sql = "SELECT ci.coin_type, ci.quantity, ci.last_updated,
                            (CASE 
                                WHEN ci.coin_type = 'coin_bahar_azadi_new' THEN 'سکه بهار آزادی جدید'
                                WHEN ci.coin_type = 'coin_bahar_azadi_old' THEN 'سکه بهار آزادی قدیم'
                                WHEN ci.coin_type = 'coin_emami' THEN 'سکه امامی'
                                WHEN ci.coin_type = 'coin_half' THEN 'نیم سکه'
                                WHEN ci.coin_type = 'coin_quarter' THEN 'ربع سکه'
                                WHEN ci.coin_type = 'coin_gerami' THEN 'سکه گرمی'
                                ELSE 'سایر'
                            END) as type_farsi
                    FROM coin_inventory ci
                    WHERE ci.quantity != 0 OR ci.last_updated IS NOT NULL -- Show even zero quantity if exists in DB, or if updated.
                    ORDER BY ci.coin_type ASC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Error fetching all coin inventory: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Updates the quantity of a specific coin type in inventory.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE.
     * @param string $coinType The coin_type enum value.
     * @param int $quantityChange The change in quantity (can be positive or negative).
     * @return bool True on success, false on failure.
     * @throws PDOException on database error.
     */
    public function updateCoinInventoryQuantity(string $coinType, int $quantityChange): bool {
        $this->logger->info("Updating coin inventory for type '{$coinType}'.", ['quantity_change' => $quantityChange]);
        try {
            $sql = "
                INSERT INTO coin_inventory (coin_type, quantity, last_updated)
                VALUES (:coin_type, :quantity_change, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    last_updated = CURRENT_TIMESTAMP
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':coin_type', $coinType, PDO::PARAM_STR);
            $stmt->bindValue(':quantity_change', $quantityChange, PDO::PARAM_INT);
            $executed = $stmt->execute();

            if ($executed) {
                $this->logger->info("Coin inventory updated successfully for '{$coinType}'.", ['rows_affected' => $stmt->rowCount()]);
            } else {
                $this->logger->error("Failed to execute coin inventory update query for '{$coinType}'.");
            }
            return $executed;

        } catch (PDOException $e) {
            $this->logger->error("Database error updating coin inventory for '{$coinType}': " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        } catch (Throwable $e) {
             $this->logger->error("Unexpected error updating coin inventory for '{$coinType}': " . $e->getMessage(), ['exception' => $e]);
             throw $e;
        }
    }

}