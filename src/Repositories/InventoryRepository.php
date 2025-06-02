<?php

namespace App\Repositories; // Namespace مطابق با پوشه src/Repositories

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;
use Throwable;

/**
 * کلاس InventoryRepository برای تعامل با جدول پایگاه داده inventory (موجودی طلا بر اساس عیار).
 */
class InventoryRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * به‌روزرسانی موجودی طلا بر اساس عیار.
     * منطق از complete_delivery.php گرفته شده.
     * از INSERT ... ON DUPLICATE KEY UPDATE استفاده می کند.
     *
     * @param int $carat عیار طلا.
     * @param float $weightChange تغییر در وزن (گرم). مثبت برای افزایش، منفی برای کاهش.
     * @param float $valueChange تغییر در ارزش (ریال). مثبت برای افزایش، منفی برای کاهش.
     * @return bool True اگر عملیات موفقیت آمیز بود.
     * @throws PDOException.
     */
    public function updateInventoryByCarat(int $carat, float $weightChange, float $valueChange): bool {
        $this->logger->info("Updating inventory for carat {$carat}.", ['weight_change' => $weightChange, 'value_change' => $valueChange]);
        try {
            // ID در جدول inventory روی carat UNIQUE INDEX دارد (بر اساس SQL dump شما)
            // بنابراین می توان از ON DUPLICATE KEY UPDATE استفاده کرد.
            $sql = "
                INSERT INTO inventory (carat, total_weight_grams, total_value_rials, last_updated)
                VALUES (:c, :w, :v, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE 
                    total_weight_grams = total_weight_grams + VALUES(total_weight_grams),
                    total_value_rials = total_value_rials + VALUES(total_value_rials),
                    last_updated = CURRENT_TIMESTAMP
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':c', $carat, PDO::PARAM_INT);
            $stmt->bindValue(':w', $weightChange, PDO::PARAM_STR); // Bind as STR for DECIMAL precision
            $stmt->bindValue(':v', $valueChange, PDO::PARAM_STR);
            $executed = $stmt->execute();

            if ($executed) {
                 $this->logger->info("Inventory updated successfully for carat {$carat}.", ['rows_affected' => $stmt->rowCount()]);
            } else {
                 $this->logger->error("Failed to execute inventory update query for carat {$carat}.");
            }

            return $executed;

        } catch (PDOException $e) {
            $this->logger->error("Database error updating inventory for carat {$carat}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        } catch (Throwable $e) {
             $this->logger->error("Error updating inventory for carat {$carat}: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
        }
    }

    // متدهای دیگر برای خواندن موجودی (getAllInventory, getInventoryByCarat)

    /**
     * Retrieves a summary of all gold inventory grouped by carat.
     *
     * @return array Associative array of inventory items, or empty array on error/no data.
     *               Each item contains: 'carat', 'total_weight_grams', 'total_value_rials'.
     */
    public function getAllInventorySummary(): array
    {
        $this->logger->debug("Calculating weight inventory summary using real FIFO logic.");
        try {
            // همه تراکنش‌های تکمیل‌شده وزنی (آبشده، ساخته‌شده، دست دوم و ...)
            $sql = "SELECT
                        t.id as transaction_main_id,
                        t.transaction_type,
                        t.transaction_date,
                        t.price_per_reference_gram, -- قیمت مرجع از جدول transactions
                        ti.id as transaction_item_id,
                        ti.weight_grams,            -- وزن از جدول transaction_items
                        ti.carat                    -- عیار از جدول transaction_items
                    FROM
                        transactions t
                    JOIN
                        transaction_items ti ON t.id = ti.transaction_id
                    WHERE
                        ti.weight_grams > 0 AND ti.carat IS NOT NULL AND t.delivery_status = 'completed'
                    ORDER BY
                        t.transaction_date ASC, t.id ASC";
            $stmt = $this->db->query($sql);
            $transactions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$transactions_data) return [];

            // گروه‌بندی تراکنش‌ها بر اساس عیار
            $byCarat = [];
            foreach ($transactions_data as $tx) {
                $carat = (int)$tx['carat']; // استفاده از tx['carat'] از transaction_items
                $byCarat[$carat][] = $tx;
            }

            $result = [];
            foreach ($byCarat as $carat => $txs) {
                // FIFO واقعی برای هر عیار
                $fifo = [];
                $totalSold = 0;
                foreach ($txs as $tx) {
                    if ($tx['transaction_type'] === 'buy') {
                        $qty = floatval($tx['weight_grams'] ?? 0); // استفاده از tx['weight_grams']
                        $price = floatval($tx['price_per_reference_gram'] ?? 0); // استفاده از tx['price_per_reference_gram']
                        if ($qty > 0 && $price > 0) {
                            $fifo[] = [ 'qty' => $qty, 'price' => $price ];
                        }
                    } elseif ($tx['transaction_type'] === 'sell') {
                        $totalSold += floatval($tx['weight_grams'] ?? 0); // استفاده از tx['weight_grams']
                    }
                }
                // کسر فروش‌ها از خریدها (FIFO)
                foreach ($fifo as &$buy) {
                    if ($totalSold <= 0) break;
                    if ($buy['qty'] <= $totalSold) {
                        $totalSold -= $buy['qty'];
                        $buy['qty'] = 0;
                    } else {
                        $buy['qty'] -= $totalSold;
                        $totalSold = 0;
                    }
                }
                unset($buy);
                $stock = 0;
                $weightedSum = 0;
                foreach ($fifo as $buy) {
                    $stock += $buy['qty'];
                    $weightedSum += $buy['qty'] * $buy['price'];
                }
                if ($stock > 0) {
                    $avgPrice = $weightedSum / $stock;
                    $result[] = [
                        'carat' => $carat,
                        'total_weight_grams' => $stock,
                        'avg_buy_price' => $avgPrice,
                        'total_value_rials' => $stock * $avgPrice,
                    ];
                }
            }
            $this->logger->debug("Weight inventory summary (FIFO) calculated.", ['count' => count($result)]);
            return $result;
        } catch (Throwable $e) {
            $this->logger->error("Error calculating FIFO weight inventory summary: " . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * محاسبه موجودی و میانگین قیمت خرید با الگوریتم FIFO برای کالاهای وزنی و تعدادی
     * @param array $transactions آرایه تراکنش‌ها (خرید و فروش تکمیل‌شده)
     * @param string $productType نوع کالا (مثلاً melted یا coin_emami)
     * @param string $mode 'weight' برای کالاهای وزنی، 'quantity' برای تعدادی
     * @return array ['stock' => float, 'avg_price' => float]
     */
    public static function calculateFIFOStockAndAvgPrice(array $transactions, string $productType, string $mode = 'weight'): array {
        $buys = [];
        $totalSold = 0;
        $qtyField = $mode === 'weight' ? 'weight_grams' : 'quantity';
        $priceField = $mode === 'weight' ? 'price_per_reference_gram' : 'unit_price';
        foreach ($transactions as $tx) {
            if (($tx['product_type'] ?? null) !== $productType || ($tx['delivery_status'] ?? null) !== 'completed') continue;
            if (($tx['transaction_type'] ?? null) === 'buy') {
                $qty = floatval($tx[$qtyField] ?? 0);
                $price = floatval($tx[$priceField] ?? 0);
                if ($qty > 0 && $price > 0) {
                    $buys[] = [
                        'qty' => $qty,
                        'price' => $price,
                    ];
                }
            } elseif (($tx['transaction_type'] ?? null) === 'sell') {
                $totalSold += floatval($tx[$qtyField] ?? 0);
            }
        }
        foreach ($buys as &$buy) {
            if ($totalSold <= 0) break;
            if ($buy['qty'] <= $totalSold) {
                $totalSold -= $buy['qty'];
                $buy['qty'] = 0;
            } else {
                $buy['qty'] -= $totalSold;
                $totalSold = 0;
            }
        }
        unset($buy);
        $stock = 0;
        $weightedSum = 0;
        foreach ($buys as $buy) {
            $stock += $buy['qty'];
            $weightedSum += $buy['qty'] * $buy['price'];
        }
        $avgPrice = $stock > 0 ? $weightedSum / $stock : 0;
        return [
            'stock' => $stock,
            'avg_price' => $avgPrice,
        ];
    }
}