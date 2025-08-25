<?php

namespace App\Repositories;

use App\Models\TransactionItem;
use PDO;
use Monolog\Logger;
use Throwable;

class TransactionItemRepository
{
    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Saves (creates or updates) a transaction item record.
     */
 public function save(TransactionItem $item): int
{
    $isUpdate = !empty($item->id);
    $data = $item->toArray();

    // Debugging step: Log the data array right before preparing the SQL
    $this->logger->debug("Transaction Item data before saving:", ['data' => $data]); // Add this line

    if ($isUpdate) {
            $this->logger->debug("Updating transaction item.", ['id' => $item->id]);
            $data['updated_at'] = date('Y-m-d H:i:s');
            $setClauses = [];
            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $setClauses[] = "`{$key}` = :{$key}";
                }
            }
            $sql = "UPDATE transaction_items SET " . implode(', ', $setClauses) . " WHERE id = :id";
    } else {
        $this->logger->debug("Creating new transaction item.");
        unset($data['id'], $data['created_at'], $data['updated_at']);
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ":$c", $columns);
        $sql = "INSERT INTO transaction_items (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";
    }

    try {
        $stmt = $this->db->prepare($sql);
        foreach ($data as $key => &$value) {
            // Check specific field: if unit_price_rials is null, set to 0.0 to prevent NULL constraint violation
            if ($key === 'unit_price_rials' && $value === null) { // Add this check
                $value = 0.0; // Ensure it's a float or integer, not string "0"
            }
            if (is_bool($value)) {
                $value = (int)$value;
            }
            $stmt->bindValue(":{$key}", $value); // Use bindValue for type safety
        }
        $stmt->execute(); // This is line 55 for INSERT case
        return $isUpdate ? $item->id : (int)$this->db->lastInsertId();
    } catch (Throwable $e) {
        $this->logger->error("Database error saving transaction item.", ['sql' => $sql, 'exception' => $e]);
        throw $e;
    }
}

    /**
     * Finds all items for a given transaction ID, including necessary product and category info.
     * **FINAL REVISED VERSION**
     */
    public function findByTransactionId(int $transactionId): array
    {
        // **CRITICAL FIX: Removed reference to deleted columns 'tax_enabled', 'vat_enabled'.**
        // Selecting new tax columns instead.
        $sql = "SELECT 
                    ti.*, 
                    p.name AS product_name,
                    p.vat_base_type, 
                    p.general_tax_base_type,
                    p.tax_rate, 
                    p.vat_rate,
                    pc.base_category
                FROM transaction_items ti
                JOIN products p ON ti.product_id = p.id
                JOIN product_categories pc ON p.category_id = pc.id
                WHERE ti.transaction_id = :transaction_id
                ORDER BY ti.id ASC";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Database error finding transaction items by transaction ID.", ['sql' => $sql, 'exception' => $e]);
            throw $e;
        }
    }

    /**
     * Deletes all items for a given transaction ID.
     */
    public function deleteByTransactionId(int $transactionId): bool
    {
        $sql = "DELETE FROM transaction_items WHERE transaction_id = :transaction_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Deletes items for a transaction that are NOT in the provided list of IDs.
     */
    public function deleteRemovedItems(int $transactionId, array $idsToKeep): bool
    {
        if (empty($idsToKeep)) {
            return $this->deleteByTransactionId($transactionId);
        }
        
        $placeholders = implode(',', array_fill(0, count($idsToKeep), '?'));
        $sql = "DELETE FROM transaction_items WHERE transaction_id = ? AND id NOT IN ({$placeholders})";
        
        $params = array_merge([$transactionId], $idsToKeep);
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}