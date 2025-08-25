<?php
// src/Repositories/InventoryRepository.php
namespace App\Repositories;

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;
use Throwable;

/**
 * REVISED: InventoryRepository class to manage carat-based inventory (often for gold and precious metals)
 * Provides methods for updating and summarizing physical stock based on carats.
 */
class InventoryRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Updates gold inventory by carat. Uses INSERT ... ON DUPLICATE KEY UPDATE.
     * `total_value_rials` is an accumulative field in this specific `inventory` table
     * that directly reflects stock value at purchase/sale prices for a specific carat.
     * This update assumes that changes to inventory value can be simply added/subtracted,
     * which might be simplistic for complex FIFO/LIFO cost accounting but aligns with simpler tracking.
     * @param int $carat The carat of the gold.
     * @param float $weightChange The change in weight (grams). Positive for increase, negative for decrease.
     * @param float $valueChange The change in total value (rials) at purchase/sale price.
     * @return bool True if operation was successful.
     * @throws PDOException.
     */
    public function updateInventoryByCarat(int $carat, float $weightChange, float $valueChange): bool {
        $this->logger->info("Updating physical inventory (inventory table) for carat {$carat}.", ['weight_change' => $weightChange, 'value_change' => $valueChange]);
        try {
            $sql = "
                INSERT INTO inventory (carat, total_weight_grams, total_value_rials, last_updated)
                VALUES (:carat, :weight, :value, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE 
                    total_weight_grams = total_weight_grams + VALUES(total_weight_grams),
                    total_value_rials = total_value_rials + VALUES(total_value_rials),
                    last_updated = CURRENT_TIMESTAMP
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':carat', $carat, PDO::PARAM_INT);
            // Binding as STR for DECIMAL precision is crucial for large numbers or high precision.
            $stmt->bindValue(':weight', $weightChange, PDO::PARAM_STR); 
            $stmt->bindValue(':value', $valueChange, PDO::PARAM_STR);
            $executed = $stmt->execute();

            if ($executed) {
                 $this->logger->info("Physical inventory updated successfully for carat {$carat}.", ['rows_affected' => $stmt->rowCount()]);
            } else {
                 $this->logger->error("Failed to execute physical inventory update query for carat {$carat}.");
            }
            return $executed;
        } catch (PDOException $e) {
            $this->logger->error("Database error updating physical inventory for carat {$carat}: " . $e->getMessage(), ['exception' => $e]);
            throw $e; // Re-throw so TransactionService can rollback.
        } catch (Throwable $e) { // Catch all throwables in case of other errors (e.g., type errors during binding).
             $this->logger->error("Unexpected error updating physical inventory for carat {$carat}: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
        }
    }

    /**
     * (اصلاح شده) خلاصه‌ای از موجودی وزنی را بر اساس جمع جبری خرید و فروش‌های تکمیل شده محاسبه می‌کند.
     * این روش ساده و قابل اطمینان است و مشکل خالی بودن جداول و نمودارها را حل می‌کند.
     * @return array
     */
    public function getAllInventorySummary(): array
    {
        $this->logger->debug("Calculating weight inventory summary using direct SUM/SUBTRACT logic.");
        try {
            // این کوئری مجموع وزن و ارزش خریدها و فروش‌های تکمیل شده را برای هر عیار محاسبه می‌کند
            $sql = "
                SELECT
                    ti.carat,
                    SUM(CASE WHEN t.transaction_type = 'buy' THEN ti.weight_grams ELSE 0 END) as total_buy_weight,
                    SUM(CASE WHEN t.transaction_type = 'buy' THEN ti.total_value_rials ELSE 0 END) as total_buy_value,
                    SUM(CASE WHEN t.transaction_type = 'sell' THEN ti.weight_grams ELSE 0 END) as total_sell_weight,
                    SUM(CASE WHEN t.transaction_type = 'sell' THEN ti.total_value_rials ELSE 0 END) as total_sell_value
                FROM transaction_items ti
                JOIN transactions t ON ti.transaction_id = t.id
                JOIN products p ON ti.product_id = p.id
                WHERE
                    p.unit_of_measure = 'gram'
                    AND t.delivery_status = 'completed'
                    AND ti.carat IS NOT NULL
                GROUP BY ti.carat
            ";
            
            $stmt = $this->db->query($sql);
            $raw_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($raw_summary as $row) {
                $current_weight = (float)$row['total_buy_weight'] - (float)$row['total_sell_weight'];
                $current_value = (float)$row['total_buy_value'] - (float)$row['total_sell_value'];

                // فقط در صورتی که موجودی وزنی مثبت باشد، آن را نمایش بده
                if ($current_weight > 0.001) {
                    $result[] = [
                        'carat' => (int)$row['carat'],
                        'total_weight_grams' => $current_weight,
                        'total_value_rials' => $current_value,
                        'avg_buy_price' => $current_value / $current_weight,
                    ];
                }
            }
            
            $this->logger->debug("Weight inventory summary calculated.", ['count' => count($result)]);
            return $result;
        } catch (Throwable $e) {
            $this->logger->error("Error calculating weight inventory summary: " . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }
}