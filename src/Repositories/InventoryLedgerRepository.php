<?php
// src/Repositories/InventoryLedgerRepository.php
namespace App\Repositories;

use App\Models\InventoryLedger; // Make sure to use the correct model
use PDO;
use DateTime;
use Throwable; // Import Throwable

class InventoryLedgerRepository
{
    private PDO $db;
    private $logger;

    public function __construct(PDO $db, $logger) // Added Logger to constructor
    {
        $this->db = $db;
        $this->logger = $logger; // Initialize logger
    }

    /**
     * دریافت آخرین موجودی ثبت شده برای یک محصول ( quantity_after و weight_grams_after)
     * This method fetches the true current balance by getting the last recorded state.
     * @param int $productId
     * @return array{quantity_after: int, weight_grams_after: float}|null
     */
    private function getLastBalance(int $productId): ?array
    {
        try {
            $sql = "SELECT balance_quantity_after_movement, balance_weight_grams_after_movement
                    FROM inventory_ledger
                    WHERE product_id = :product_id
                    ORDER BY movement_date DESC, id DESC
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Rename keys to match common naming used for current balance or directly use fetched names.
            // Using precise schema names:
            return $result ? [
                'balance_quantity_after_movement' => (int)$result['balance_quantity_after_movement'],
                'balance_weight_grams_after_movement' => (float)$result['balance_weight_grams_after_movement']
            ] : null;
        } catch (Throwable $e) {
            $this->logger->error("Error fetching last inventory balance for product ID {$productId}.", ['exception' => $e]);
            throw $e; // Re-throw to handle error in calling function.
        }
    }

    /**
     * محاسبه و دریافت موجودی فعلی یک محصول (safe wrapper for getLastBalance)
     * @param int $productId
     * @return array{quantity: int, weight_grams: float} Returns {0, 0.0} if no history.
     */
    public function getProductCurrentBalance(int $productId): array
    {
        // Try to fetch last balance. If none, start from 0.
        try {
            $lastBalance = $this->getLastBalance($productId);
            return $lastBalance ? [
                'quantity' => $lastBalance['balance_quantity_after_movement'],
                'weight_grams' => $lastBalance['balance_weight_grams_after_movement']
            ] : ['quantity' => 0, 'weight_grams' => 0.0];
        } catch (Throwable $e) {
             $this->logger->error("Failed to get product current balance for {$productId}. Returning zero balance.", ['exception' => $e]);
             return ['quantity' => 0, 'weight_grams' => 0.0]; // Safe fallback on error.
        }
    }

    /**
     * ثبت یک تغییر در دفتر موجودی
     * @param array $data Expected keys: product_id, change_quantity, change_weight_grams, event_type, notes.
     *                    Optional keys: transaction_id, transaction_item_id, related_initial_balance_id, event_date, price_per_unit_at_movement, total_value_in, total_value_out.
     * @return int|false ID of the recorded change or false on failure.
     */
    public function recordChange(array $data): int|false
    {
        $this->logger->info("Attempting to record inventory ledger change.", ['data' => $data]);
        
        // Retrieve current balance to calculate new balance values (using `balance_quantity_after_movement`, `balance_weight_grams_after_movement` schema fields).
        $currentBalances = $this->getProductCurrentBalance($data['product_id']);
        $quantityAfter = $currentBalances['quantity'] + ($data['change_quantity'] ?? 0);
        $weightGramsAfter = $currentBalances['weight_grams'] + ($data['change_weight_grams'] ?? 0.0);

        // Standardize column names for INSERT. Your `client-database.json` uses `movement_date`, `movement_type`, etc.
        $sql = "INSERT INTO inventory_ledger (
                    product_id, transaction_id, transaction_item_id,
                    movement_date, movement_type, notes,
                    change_quantity, change_weight_grams,
                    balance_quantity_after_movement, balance_weight_grams_after_movement,
                    carat, price_per_unit_at_movement, total_value_in, total_value_out, balance_total_value_after_movement
                ) VALUES (
                    :product_id, :transaction_id, :transaction_item_id,
                    :movement_date, :movement_type, :notes,
                    :change_quantity, :change_weight_grams,
                    :balance_quantity_after_movement, :balance_weight_grams_after_movement,
                    :carat, :price_per_unit_at_movement, :total_value_in, :total_value_out, :balance_total_value_after_movement
                )";
        $stmt = $this->db->prepare($sql);

        $movementDate = $data['movement_date'] ?? (new DateTime())->format('Y-m-d H:i:s');

        // Bind parameters carefully, ensuring nulls are sent as PDO::PARAM_NULL and numerics are floats/ints.
        $stmt->bindValue(':product_id', $data['product_id'], PDO::PARAM_INT);
        $stmt->bindValue(':transaction_id', $data['transaction_id'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':transaction_item_id', $data['transaction_item_id'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':movement_date', $movementDate);
        $stmt->bindValue(':movement_type', $data['movement_type'] ?? 'ADJUSTMENT', PDO::PARAM_STR); // Default 'ADJUSTMENT' for missing
        $stmt->bindValue(':notes', $data['notes'] ?? null, PDO::PARAM_STR);
        
        $stmt->bindValue(':change_quantity', $data['change_quantity'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':change_weight_grams', (float)($data['change_weight_grams'] ?? 0.0), PDO::PARAM_STR); // Bind as string for DECIMAL column accuracy
        
        $stmt->bindValue(':balance_quantity_after_movement', $quantityAfter, PDO::PARAM_INT);
        $stmt->bindValue(':balance_weight_grams_after_movement', (float)$weightGramsAfter, PDO::PARAM_STR);

        // Optional fields from inventory_ledger schema. Need to be nullable for database.
        $stmt->bindValue(':carat', $data['carat'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':price_per_unit_at_movement', (float)($data['price_per_unit_at_movement'] ?? 0.0), PDO::PARAM_STR);
        $stmt->bindValue(':total_value_in', (float)($data['total_value_in'] ?? 0.0), PDO::PARAM_STR);
        $stmt->bindValue(':total_value_out', (float)($data['total_value_out'] ?? 0.0), PDO::PARAM_STR);
        $stmt->bindValue(':balance_total_value_after_movement', (float)($data['balance_total_value_after_movement'] ?? 0.0), PDO::PARAM_STR);


        try {
            $executed = $stmt->execute();
            if ($executed) {
                $lastId = (int)$this->db->lastInsertId();
                $this->logger->info("Inventory ledger record created successfully.", ['id' => $lastId, 'product_id' => $data['product_id'], 'event_type' => $data['movement_type'] ?? 'ADJUSTMENT']);
                return $lastId;
            }
            $this->logger->error("Failed to record inventory ledger change.", ['errorInfo' => $stmt->errorInfo(), 'data' => $data]);
            return false;

        } catch (Throwable $e) {
            $this->logger->error("Database error recording inventory ledger change.", ['exception' => $e, 'data' => $data]);
            throw $e;
        }
    }

    /**
     * حذف رکوردهای دفتر موجودی مربوط به یک معامله خاص
     * @param int $transactionId
     * @return bool True on success, false on failure
     */
    public function deleteByTransactionId(int $transactionId): bool
    {
        try {
            $sql = "DELETE FROM inventory_ledger WHERE transaction_id = :transaction_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_INT);
            $executed = $stmt->execute();
            if ($executed) {
                 $this->logger->info("Inventory ledger records deleted for transaction ID: {$transactionId}.", ['rows_affected' => $stmt->rowCount()]);
            }
            return $executed;
        } catch (Throwable $e) {
            $this->logger->error("Error deleting inventory ledger records for transaction ID {$transactionId}.", ['exception' => $e]);
            throw $e;
        }
    }
}