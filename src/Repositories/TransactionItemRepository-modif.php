<?php

namespace App\Repositories;

use App\Models\TransactionItem;
use Monolog\Logger; // FIX: Add Logger dependency
use PDO;
use PDOException;
use Throwable;

class TransactionItemRepository
{
    private PDO $db;
    private Logger $logger; // FIX: Add logger property

    /**
     * FIX: Add Logger to the constructor for better error handling.
     */
    public function __construct(PDO $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * REFACTORED: Saves or updates a transaction item.
     * This method now dynamically builds the query based on the properties of the
     * provided TransactionItem object, ensuring only valid columns are used.
     *
     * @param TransactionItem $item The item object to save.
     * @return int The ID of the saved item.
     * @throws PDOException on database error.
     */
    public function save(TransactionItem $item): int
    {
        $itemData = $item->toArray();
        unset($itemData['product'], $itemData['assayOffice']); // Remove non-DB properties

        // Filter out null values to only include fields that should be set.
        $dataToSave = array_filter($itemData, function ($value) {
            return $value !== null;
        });

        $isUpdate = !empty($item->id);
        $this->logger->debug(($isUpdate ? "Updating" : "Inserting") . " transaction item.", ['item_id' => $item->id, 'tx_id' => $item->transaction_id]);

        if ($isUpdate) {
            // --- UPDATE ---
            unset($dataToSave['id'], $dataToSave['created_at']); // Do not update these
            if (empty($dataToSave)) {
                $this->logger->warning("Update called for transaction item, but no data to update.", ['item_id' => $item->id]);
                return $item->id;
            }
            $setClauses = [];
            foreach (array_keys($dataToSave) as $field) {
                $setClauses[] = "`{$field}` = :{$field}";
            }
            $sql = "UPDATE transaction_items SET " . implode(', ', $setClauses) . " WHERE id = :id";
            $dataToSave['id'] = $item->id; // Add ID for the WHERE clause
        } else {
            // --- INSERT ---
            unset($dataToSave['id']); // Let the database generate the ID
            $fields = array_keys($dataToSave);
            $placeholders = array_map(fn($f) => ":{$f}", $fields);
            $sql = "INSERT INTO transaction_items (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        }

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($dataToSave as $key => &$value) {
                // Bind value with appropriate type
                $type = PDO::PARAM_STR;
                if (is_int($value)) $type = PDO::PARAM_INT;
                if (is_bool($value)) $type = PDO::PARAM_BOOL;
                if (is_null($value)) $type = PDO::PARAM_NULL;
                $stmt->bindParam(":{$key}", $value, $type);
            }
            $stmt->execute();

            if ($isUpdate) {
                return $item->id;
            } else {
                return (int)$this->db->lastInsertId();
            }
        } catch (PDOException $e) {
            $this->logger->error("Database error saving transaction item.", [
                'item_id' => $item->id,
                'sql' => $sql, // Be careful logging SQL in production
                'exception' => $e->getMessage()
            ]);
            throw $e; // Re-throw the exception
        }
    }
    
    /**
     * Deletes all items associated with a specific transaction ID.
     * Used before updating items to clear the old ones.
     *
     * @param int $transactionId
     * @return bool
     */
    public function deleteByTransactionId(int $transactionId): bool
    {
        $this->logger->info("Deleting all items for transaction ID: {$transactionId}");
        try {
            $sql = "DELETE FROM transaction_items WHERE transaction_id = :transaction_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            $this->logger->error("Database error deleting items by transaction ID.", ['tx_id' => $transactionId, 'exception' => $e]);
            throw $e;
        }
    }

    // Other methods remain largely the same as they were fetching correct data
    public function findByTransactionId(int $transactionId): array {
        // This method was correct, it fetches real columns.
        $sql = "SELECT ti.*,
                       p.name AS product_name,
                       p.product_code AS product_code,
                       pc.base_category AS product_category_base,
                       pc.code AS product_category_code
                FROM transaction_items ti
                LEFT JOIN products p ON ti.product_id = p.id
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                WHERE ti.transaction_id = :transaction_id
                ORDER BY ti.id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findPendingItemsWithProductDetails(string $deliveryStatus): array {
        // This method was also correct
        $sql = "SELECT 
                    ti.*,
                    t.transaction_date,
                    t.transaction_type,
                    t.delivery_status,
                    c.name as counterparty_name
                FROM 
                    transaction_items ti
                    JOIN transactions t ON ti.transaction_id = t.id
                    LEFT JOIN contacts c ON t.counterparty_contact_id = c.id
                WHERE 
                    t.delivery_status = :delivery_status
                ORDER BY 
                    t.transaction_date DESC, ti.id ASC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':delivery_status', $deliveryStatus, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
