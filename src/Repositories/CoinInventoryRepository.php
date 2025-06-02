<?php

namespace App\Repositories; // Namespace مطابق با پوشه src/Repositories

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;
use Throwable;

/**
 * کلاس CoinInventoryRepository برای تعامل با جدول پایگاه داده coin_inventory (موجودی سکه).
 */
class CoinInventoryRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * به‌روزرسانی موجودی سکه بر اساس نوع سکه.
     * منطق از complete_delivery.php گرفته شده.
     * از INSERT ... ON DUPLICATE KEY UPDATE استفاده می کند.
     *
     * @param string $coinType نوع سکه (مطابق ENUM در coin_inventory).
     * @param int $quantityChange تغییر در تعداد سکه. مثبت برای افزایش، منفی برای کاهش.
     * @return bool True اگر عملیات موفقیت آمیز بود.
     * @throws PDOException.
     * @throws Exception در صورت نوع سکه نامعتبر.
     */
    public function updateCoinInventoryByType(string $coinType, int $quantityChange): bool {
        $this->logger->info("Updating coin inventory for type '{$coinType}'.", ['quantity_change' => $quantityChange]);

         // اعتبارسنجی نوع سکه (اختیاری اما خوب است)
         $validCoinTypes = ['coin_bahar_azadi_new','coin_bahar_azadi_old','coin_emami','coin_half','coin_quarter','coin_gerami','other_coin'];
         if (!in_array($coinType, $validCoinTypes)) {
              $this->logger->error("Attempted to update coin inventory with invalid coin type: " . $coinType);
              throw new Exception("Invalid coin type provided: " . $coinType);
         }


        try {
            // ID در جدول coin_inventory روی coin_type UNIQUE INDEX دارد (بر اساس SQL dump شما)
            // بنابراین می توان از ON DUPLICATE KEY UPDATE استفاده کرد.
            $sql = "
                INSERT INTO coin_inventory (coin_type, quantity, last_updated)
                VALUES (:ct, :q, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    last_updated = CURRENT_TIMESTAMP
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':ct', $coinType, PDO::PARAM_STR);
            $stmt->bindValue(':q', $quantityChange, PDO::PARAM_INT);
            $executed = $stmt->execute();

             if ($executed) {
                 $this->logger->info("Coin inventory updated successfully for type '{$coinType}'.", ['rows_affected' => $stmt->rowCount()]);
            } else {
                 $this->logger->error("Failed to execute coin inventory update query for type '{$coinType}'.");
            }

            return $executed;

        } catch (PDOException $e) {
            $this->logger->error("Database error updating coin inventory for type '{$coinType}': " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        } catch (Throwable $e) {
             $this->logger->error("Error updating coin inventory for type '{$coinType}': " . $e->getMessage(), ['exception' => $e]);
             throw $e;
        }
    }

    /**
     * Retrieves all coin inventory items with non-zero quantity.
     *
     * @return array Associative array of coin inventory items.
     *               Each item contains: 'coin_type', 'quantity'.
     */
    public function getAllCoinInventory(): array
    {
        $this->logger->debug("Fetching all coin inventory for dashboard.");
        try {
            // Query from dash.php
            $sql = "SELECT coin_type, quantity 
                    FROM coin_inventory 
                    WHERE quantity != 0 
                    ORDER BY coin_type ASC";
            $stmt = $this->db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->debug("Coin inventory fetched for dashboard.", ['count' => count($results)]);
            return $results ?: [];
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching coin inventory for dashboard.", ['exception' => $e]);
            return [];
        }
    }

    // متدهای دیگر برای خواندن موجودی سکه (getAllCoinInventory, getCoinInventoryByType)
}