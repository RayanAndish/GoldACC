<?php

namespace App\Repositories;

use App\Models\TransactionItem;
use PDO;
use Monolog\Logger;
use Throwable;

/**
 * REFACTORED: TransactionItemRepository
 * Handles database interactions for the 'transaction_items' table.
 */
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
     *
     * @param TransactionItem $item The item model object.
     * @return int The ID of the saved item.
     * @throws Throwable
     */
    public function save(TransactionItem $item): int
    {
        $isUpdate = !empty($item->id);
        $data = $item->toArray();

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
                // Bools should be converted to 0/1 for DB
                if (is_bool($value)) {
                    $value = (int)$value;
                }
                $stmt->bindParam(":{$key}", $value);
            }
            $stmt->execute();

            return $isUpdate ? $item->id : (int)$this->db->lastInsertId();
        } catch (Throwable $e) {
            $this->logger->error("Database error saving transaction item.", ['sql' => $sql, 'exception' => $e]);
            throw $e;
        }
    }

    /**
     * Finds all items for a given transaction ID.
     *
     * @param int $transactionId
     * @return array
     */
    public function findByTransactionId(int $transactionId): array
    {
        $sql = "SELECT ti.*, 
                       p.name as product_name, 
                       p.tax_enabled, p.tax_rate, p.vat_enabled, p.vat_rate,
                       pc.base_category
                FROM transaction_items ti
                JOIN products p ON ti.product_id = p.id
                JOIN product_categories pc ON p.category_id = pc.id
                WHERE ti.transaction_id = :transaction_id
                ORDER BY ti.id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Deletes all items for a given transaction ID.
     *
     * @param int $transactionId
     * @return bool
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
     * Used in edit mode to remove deleted rows.
     *
     * @param int $transactionId
     * @param array $idsToKeep
     * @return bool
     */
    public function deleteRemovedItems(int $transactionId, array $idsToKeep): bool
    {
        if (empty($idsToKeep)) {
            // If no items are submitted, delete all for this transaction
            return $this->deleteByTransactionId($transactionId);
        }
        
        $placeholders = implode(',', array_fill(0, count($idsToKeep), '?'));
        $sql = "DELETE FROM transaction_items WHERE transaction_id = ? AND id NOT IN ({$placeholders})";
        
        $params = array_merge([$transactionId], $idsToKeep);
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
