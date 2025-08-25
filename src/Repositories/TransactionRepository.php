<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Utils\Helper;
use PDO;
use Monolog\Logger;
use Throwable;
use RuntimeException;

/**
 * REFACTORED: TransactionRepository
 * Handles database interactions for the 'transactions' table.
 */
class TransactionRepository
{
    /**
     * جمع‌بندی معاملات در وضعیت‌های تحویل معلق (pending_delivery, pending_receipt)
     * @return array
     */
    public function getPendingSummary(): array
    {
        $sql = "SELECT delivery_status, COUNT(*) as count, SUM(total_items_value_rials) as total_value
                FROM transactions
                WHERE delivery_status IN ('pending_delivery', 'pending_receipt')
                GROUP BY delivery_status";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $summary = [
            'pending_delivery' => ['count' => 0, 'total_value' => 0],
            'pending_receipt' => ['count' => 0, 'total_value' => 0],
        ];
        foreach ($result as $row) {
            $status = $row['delivery_status'];
            $summary[$status] = [
                'count' => (int)$row['count'],
                'total_value' => (float)$row['total_value'],
            ];
        }
        return $summary;
    }
    /**
     * واکشی آخرین معاملات با امکان فیلتر نوع معامله (buy/sell) و محدودیت تعداد
     * @param string|null $type نوع معامله (buy/sell/null=همه)
     * @param int $limit تعداد رکورد (پیش‌فرض 5)
     * @return array
     */
    public function getLatestTransactions(?string $type = null, int $limit = 5): array
    {
        $sql = "SELECT t.*, c.name as counterparty_name FROM transactions t LEFT JOIN contacts c ON t.counterparty_contact_id = c.id";
        $params = [];
        if ($type !== null) {
            $sql .= " WHERE t.transaction_type = :type";
            $params[':type'] = $type;
        }
        $sql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Saves (creates or updates) a transaction record.
     *
     * @param Transaction $transaction The Transaction model object.
     * @return int The ID of the saved transaction.
     * @throws Throwable
     */
    public function save(Transaction $transaction): int
    {
        $isUpdate = !empty($transaction->id);
        $data = $transaction->toArray();

        if ($isUpdate) {
            $this->logger->info("Updating transaction record.", ['id' => $transaction->id]);
            $data['updated_at'] = date('Y-m-d H:i:s');
            $setClauses = [];
            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $setClauses[] = "`{$key}` = :{$key}";
                }
            }
            $sql = "UPDATE transactions SET " . implode(', ', $setClauses) . " WHERE id = :id";
        } else {
            $this->logger->info("Creating new transaction record.");
            unset($data['id'], $data['created_at'], $data['updated_at']); // Let DB handle these
            $columns = array_keys($data);
            $placeholders = array_map(fn($c) => ":$c", $columns);
            $sql = "INSERT INTO transactions (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";
        }
        
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($data as $key => &$value) {
                 $stmt->bindParam(":{$key}", $value);
            }
            $stmt->execute();

            if ($isUpdate) {
                return $transaction->id;
            } else {
                return (int)$this->db->lastInsertId();
            }
        } catch (Throwable $e) {
            $this->logger->error("Database error saving transaction.", ['sql' => $sql, 'exception' => $e]);
            throw $e;
        }
    }

    /**
     * Deletes a transaction by its ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM transactions WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Finds a single transaction by its ID, joining related item data.
     *
     * @param int $id
     * @return array|null
     */
    public function findByIdWithItems(int $id): ?array
    {
        $this->logger->debug("Fetching transaction with items for ID: {$id}.");
        $transaction = $this->findById($id);

        if (!$transaction) {
            return null;
        }
        
        // Using TransactionItemRepository to fetch items
        $itemRepo = new TransactionItemRepository($this->db, $this->logger); // This could be injected
        $transaction['items'] = $itemRepo->findByTransactionId($id);

        return $transaction;
    }

    /**
     * Finds a single transaction by its ID.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT t.*, c.name as counterparty_name 
                FROM transactions t 
                LEFT JOIN contacts c ON t.counterparty_contact_id = c.id
                WHERE t.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Counts the total number of transactions matching the given filters.
     */
    public function countFiltered(array $filters = [], ?string $searchTerm = null): int
    {
        [$whereClause, $params] = $this->buildWhereClause($filters, $searchTerm);
        $sql = "SELECT COUNT(DISTINCT t.id) FROM transactions t LEFT JOIN contacts c ON t.counterparty_contact_id = c.id {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Retrieves a paginated list of transactions matching the given filters.
     */
    public function getFilteredAndPaginated(array $filters = [], ?string $searchTerm = null, int $limit = 15, int $offset = 0): array
    {
        [$whereClause, $params] = $this->buildWhereClause($filters, $searchTerm);
        
        $sql = "SELECT t.*, c.name AS counterparty_name 
                FROM transactions t
                LEFT JOIN contacts c ON t.counterparty_contact_id = c.id
                {$whereClause}
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Builds the WHERE clause for filtering and searching.
     */
    private function buildWhereClause(array $filters, ?string $searchTerm): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = 't.transaction_type = :type';
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['contact_id'])) {
            $where[] = 't.counterparty_contact_id = :contact_id';
            $params[':contact_id'] = $filters['contact_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 't.delivery_status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['start_date_sql'])) {
            $where[] = 't.transaction_date >= :start_date';
            $params[':start_date'] = $filters['start_date_sql'];
        }
        if (!empty($filters['end_date_sql'])) {
            $where[] = 't.transaction_date <= :end_date';
            $params[':end_date'] = $filters['end_date_sql'];
        }

        if (!empty($searchTerm)) {
            $where[] = '(c.name LIKE :search OR t.notes LIKE :search OR t.id = :search_id)';
            $params[':search'] = '%' . $searchTerm . '%';
            $params[':search_id'] = is_numeric($searchTerm) ? (int)$searchTerm : 0;
        }

        return $where ? ['WHERE ' . implode(' AND ', $where), $params] : ['', []];
    }
}
