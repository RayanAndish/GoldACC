<?php

namespace App\Repositories;

use PDO;
use Monolog\Logger;
use Throwable;

class ContactWeightLedgerRepository
{
    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Gets the last known weight balance for a specific contact and product category.
     */
    public function getLastBalance(int $contactId, int $productCategoryId): float
    {
        $sql = "SELECT balance_after_grams 
                FROM contact_weight_ledger 
                WHERE contact_id = :contact_id AND product_category_id = :category_id
                ORDER BY event_date DESC, id DESC 
                LIMIT 1";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':contact_id' => $contactId,
                ':category_id' => $productCategoryId
            ]);
            $result = $stmt->fetchColumn();
            return $result !== false ? (float)$result : 0.0;
        } catch (Throwable $e) {
            $this->logger->error("Failed to get last weight balance.", ['contact' => $contactId, 'exception' => $e]);
            throw $e;
        }
    }

    /**
     * Records a new entry in the contact's weight ledger.
     */
    public function recordEntry(array $data): bool
    {
        $sql = "INSERT INTO contact_weight_ledger (
                    contact_id, product_category_id, event_type, 
                    change_weight_grams, balance_after_grams, 
                    related_transaction_id, related_settlement_id, notes, event_date
                ) VALUES (
                    :contact_id, :product_category_id, :event_type,
                    :change_weight_grams, :balance_after_grams,
                    :related_transaction_id, :related_settlement_id, :notes, NOW()
                )";
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':contact_id' => $data['contact_id'],
                ':product_category_id' => $data['product_category_id'],
                ':event_type' => $data['event_type'],
                ':change_weight_grams' => $data['change_weight_grams'],
                ':balance_after_grams' => $data['balance_after_grams'],
                ':related_transaction_id' => $data['related_transaction_id'] ?? null,
                ':related_settlement_id' => $data['related_settlement_id'] ?? null,
                ':notes' => $data['notes'] ?? null
            ]);
        } catch (Throwable $e) {
            $this->logger->error("Failed to record contact weight ledger entry.", ['data' => $data, 'exception' => $e]);
            throw $e;
        }
    }

    /**
     * Deletes all ledger entries associated with a specific transaction ID.
     * This is crucial for when a transaction is deleted.
     */
    public function deleteByTransactionId(int $transactionId): void
    {
        $sql = "DELETE FROM contact_weight_ledger WHERE related_transaction_id = :transaction_id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':transaction_id' => $transactionId]);
            $this->logger->info("Deleted contact weight ledger entries for transaction.", ['transaction_id' => $transactionId]);
        } catch (Throwable $e) {
            $this->logger->error("Failed to delete weight ledger entries for transaction.", ['transaction_id' => $transactionId, 'exception' => $e]);
            throw $e;
        }
    }
    /**
     * (جدید) تمام رکوردهای کاردکس وزنی یک مخاطب را واکشی می‌کند.
     */
    public function getLedgerForContact(int $contactId): array
    {
        $this->logger->debug("Fetching weight ledger for contact.", ['contact_id' => $contactId]);
        $sql = "SELECT l.*, pc.name as category_name
                FROM contact_weight_ledger l
                LEFT JOIN product_categories pc ON l.product_category_id = pc.id
                WHERE l.contact_id = :contact_id
                ORDER BY l.event_date ASC, l.id ASC";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':contact_id' => $contactId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Error fetching weight ledger.", ['contact_id' => $contactId, 'exception' => $e]);
            return [];
        }
    }
    /**
     * (جدید) تمام رکوردهای کاردکس وزنی یک مخاطب را با فیلتر تاریخ واکشی می‌کند.
     */
    public function getLedgerForContactWithFilter(int $contactId, int $productCategoryId, ?string $startDate, ?string $endDate): array
    {
        $sql = "SELECT 'weight' as ledger_type, event_date as entry_date, notes, 
                       change_weight_grams 
                FROM contact_weight_ledger 
                WHERE contact_id = :contact_id AND product_category_id = :category_id";
       $params = [':contact_id' => $contactId, ':category_id' => $productCategoryId];
        if ($startDate) { $sql .= " AND event_date >= :start_date"; $params[':start_date'] = $startDate; }
        if ($endDate) { $sql .= " AND event_date <= :end_date"; $params[':end_date'] = $endDate; }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * (جدید) مانده حساب وزنی یک مخاطب را تا قبل از یک تاریخ مشخص محاسبه می‌کند.
     */
    public function getBalanceBeforeDate(int $contactId, int $productCategoryId, ?string $startDate): float
    {
        if (empty($startDate)) return 0.0;
        
        $sql = "SELECT SUM(change_weight_grams) 
                FROM contact_weight_ledger 
                WHERE contact_id = :contact_id 
                AND product_category_id = :category_id
                AND event_date < :start_date";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':contact_id' => $contactId,
                ':category_id' => $productCategoryId,
                ':start_date' => $startDate
            ]);
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $this->logger->error("Error fetching weight balance before date.", ['contact_id' => $contactId, 'category_id' => $productCategoryId, 'start_date' => $startDate, 'exception' => $e]);
            return 0.0;
        }
    }
}