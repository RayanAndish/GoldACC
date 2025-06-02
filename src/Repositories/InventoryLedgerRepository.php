<?php

namespace App\Repositories;

use App\Models\InventoryLedger;
use PDO;
use DateTime;

class InventoryLedgerRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * دریافت آخرین موجودی ثبت شده برای یک محصول
     * @param int $productId
     * @return array{quantity: int, weight_grams: float}|null
     */
    private function getLastBalance(int $productId): ?array
    {
        $sql = "SELECT quantity_after, weight_grams_after
                FROM inventory_ledger
                WHERE product_id = :product_id
                ORDER BY event_date DESC, id DESC
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? ['quantity' => (int)$result['quantity_after'], 'weight_grams' => (float)$result['weight_grams_after']] : null;
    }

    /**
     * محاسبه و دریافت موجودی فعلی یک محصول
     * @param int $productId
     * @return array{quantity: int, weight_grams: float}
     */
    public function getProductCurrentBalance(int $productId): array
    {
        $lastBalance = $this->getLastBalance($productId);
        return $lastBalance ?? ['quantity' => 0, 'weight_grams' => 0.0];
    }

    /**
     * ثبت یک تغییر در دفتر موجودی
     * @param array $data داده‌های رکورد شامل کلیدهای: product_id, transaction_id, transaction_item_id, change_quantity, change_weight_grams, event_type, notes, event_date (optional, default now)
     * @return int|false ID رکورد ثبت شده یا false در صورت خطا
     */
    public function recordChange(array $data): int|false
    {
        // دریافت موجودی فعلی قبل از اعمال تغییر
        $currentBalance = $this->getProductCurrentBalance($data['product_id']);

        // محاسبه موجودی بعد از تغییر
        $quantityAfter = $currentBalance['quantity'] + ($data['change_quantity'] ?? 0);
        $weightGramsAfter = $currentBalance['weight_grams'] + ($data['change_weight_grams'] ?? 0.0);

        $sql = "INSERT INTO inventory_ledger (
                    product_id, transaction_id, transaction_item_id, change_quantity,
                    change_weight_grams, quantity_after, weight_grams_after,
                    event_type, event_date, notes
                ) VALUES (
                    :product_id, :transaction_id, :transaction_item_id, :change_quantity,
                    :change_weight_grams, :quantity_after, :weight_grams_after,
                    :event_type, :event_date, :notes
                )";
        $stmt = $this->db->prepare($sql);

        $eventDate = $data['event_date'] ?? (new DateTime())->format('Y-m-d H:i:s');

        $stmt->bindValue(':product_id', $data['product_id'], PDO::PARAM_INT);
        $stmt->bindValue(':transaction_id', $data['transaction_id'], PDO::PARAM_INT);
        $stmt->bindValue(':transaction_item_id', $data['transaction_item_id'], PDO::PARAM_INT);
        $stmt->bindValue(':change_quantity', $data['change_quantity'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':change_weight_grams', $data['change_weight_grams'] ?? 0.0); // PDO handles float
        $stmt->bindValue(':quantity_after', $quantityAfter, PDO::PARAM_INT);
        $stmt->bindValue(':weight_grams_after', $weightGramsAfter); // PDO handles float
        $stmt->bindValue(':event_type', $data['event_type'], PDO::PARAM_STR);
        $stmt->bindValue(':event_date', $eventDate);
        $stmt->bindValue(':notes', $data['notes'] ?? null, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return (int)$this->db->lastInsertId();
        }

        return false;
    }

    /**
     * حذف رکوردهای دفتر موجودی مربوط به یک معامله خاص
     * @param int $transactionId
     * @return bool True on success, false on failure
     */
    public function deleteByTransactionId(int $transactionId): bool
    {
        $sql = "DELETE FROM inventory_ledger WHERE transaction_id = :transaction_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_INT);
        return $stmt->execute();
    }

} 