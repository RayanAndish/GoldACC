<?php
// src/Repositories/PaymentRepository.php
namespace App\Repositories;

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;
use Throwable;
use App\Utils\Helper;

class PaymentRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function getById(int $paymentId): ?array {
        $this->logger->debug("Fetching payment with ID: {$paymentId} for edit.");
        try {
            $sql = "SELECT p.*, 
                           pc.name AS paying_contact_name, 
                           rc.name AS receiving_contact_name,
                           t.transaction_type AS related_transaction_type,
                           t.id AS related_transaction_id,
                           c_tx.name AS related_transaction_contact_name
                    FROM payments p 
                    LEFT JOIN contacts pc ON p.paying_contact_id = pc.id
                    LEFT JOIN contacts rc ON p.receiving_contact_id = rc.id
                    LEFT JOIN transactions t ON p.related_transaction_id = t.id 
                    LEFT JOIN contacts c_tx ON t.counterparty_contact_id = c_tx.id 
                    WHERE p.id = :id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching payment by ID {$paymentId}.", ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Get the latest payments for the dashboard or a list.
     * REVISED: Ensures contact_name, amount, and date are formatted and correct for display.
     * @param int $limit
     * @return array List of latest payment records.
     * @throws Throwable
     */
    public function getLatestPayments(int $limit = 10): array {
        $this->logger->debug("Fetching latest payments for dashboard or list, limit: {$limit}.");
        try {
            $sql = "SELECT p.*, 
                           pc.name AS paying_contact_name, 
                           rc.name AS receiving_contact_name 
                    FROM payments p 
                    LEFT JOIN contacts pc ON p.paying_contact_id = pc.id
                    LEFT JOIN contacts rc ON p.receiving_contact_id = rc.id
                    ORDER BY p.payment_date DESC, p.id DESC
                    LIMIT :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Post-process results for display (dashboard uses this data) ---
            foreach ($rows as &$row) {
                // Determine `contact_name` (display `receiving_contact_name` for inflow, `paying_contact_name` for outflow).
                if (($row['direction'] ?? '') === 'inflow') {
                    $row['contact_name'] = $row['receiving_contact_name'] ?? ($row['receiving_details'] ?? 'مخاطب نامشخص');
                    $row['direction_farsi'] = 'دریافت';
                } elseif (($row['direction'] ?? '') === 'outflow') {
                    $row['contact_name'] = $row['paying_contact_name'] ?? ($row['paying_details'] ?? 'مخاطب نامشخص');
                    $row['direction_farsi'] = 'پرداخت';
                } else {
                    $row['contact_name'] = 'مخاطب نامشخص';
                    $row['direction_farsi'] = 'نامشخص';
                }

                // Format amount and date for direct display in view (reusing DashboardHelper formatters or in view itself).
                $row['amount_rials_formatted'] = Helper::formatRial($row['amount_rials'] ?? 0);
                $row['payment_date_jalali'] = !empty($row['payment_date']) ? Helper::formatPersianDate($row['payment_date']) : '-'; // Only date part

                $row['description_short'] = Helper::escapeHtml(mb_substr($row['notes'] ?? '', 0, 30, 'UTF-8')) . (mb_strlen($row['notes'] ?? '') > 30 ? '...' : '');

            }
            unset($row);
            
            $this->logger->debug("Fetched " . count($rows) . " latest payments and formatted for display.");
            return $rows;
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching latest payments: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }


    /**
     * Saves a payment record (create or update).
     * @param array $paymentData All validated and prepared data. Assumes 'id' is set for updates.
     * @return int The ID of the saved/updated payment.
     * @throws Exception
     */
    public function save(array $paymentData): int {
        $paymentId = $paymentData['id'] ?? null;
        $isEditing = $paymentId !== null;
        $this->logger->info(($isEditing ? "Updating" : "Creating") . " payment record.", ['id' => $paymentId]);

        $allowedColumns = [
            'payment_date', 'amount_rials', 'direction', 'payment_method',
            'paying_contact_id', 'paying_details', 'receiving_contact_id', 'receiving_details',
            'related_transaction_id', 'notes', 'bank_transaction_id',
            'method_details_payer_receiver', 'method_details_clearing_type', 'method_details_slip_number',
            'method_details_slip_date', 'method_details_bank_agent', 'method_details_tracking_code',
            'method_details_transfer_date', 'method_details_source_dest_info', 'method_details_terminal_id',
            'method_details_pos_holder', 'method_details_cheque_holder_nid', 'method_details_cheque_account_number',
            'method_details_cheque_holder_name', 'method_details_cheque_type', 'method_details_cheque_serial',
            'method_details_cheque_sayad_id', 'method_details_cheque_due_date'
        ];

        $fieldsToSave = array_intersect_key($paymentData, array_flip($allowedColumns));
        if (!array_key_exists('related_transaction_id', $fieldsToSave)) $fieldsToSave['related_transaction_id'] = null;
        if (!array_key_exists('bank_transaction_id', $fieldsToSave)) $fieldsToSave['bank_transaction_id'] = null;

        if (empty($fieldsToSave) && !$isEditing) {
            throw new Exception("No valid data provided to save payment.");
        }

        try {
            if ($isEditing) {
                $updateParts = [];
                foreach (array_keys($fieldsToSave) as $field) {
                    $updateParts[] = "`" . $field . "` = :" . $field;
                }
                $sql = "UPDATE payments SET " . implode(', ', $updateParts) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
            } else {
                $insertFields = array_keys($fieldsToSave);
                $placeholders = array_map(fn($f) => ':' . $f, $insertFields);
                $sql = "INSERT INTO payments (`" . implode('`,`', $insertFields) . "`, created_at, updated_at) VALUES (" . implode(',', $placeholders) . ", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                $stmt = $this->db->prepare($sql);
            }

            foreach ($fieldsToSave as $key => $value) {
                if (is_int($value)) {
                    $type = PDO::PARAM_INT;
                } elseif (is_float($value)) { 
                    $type = PDO::PARAM_STR; 
                } elseif ($value === null || $value === '') { 
                    $type = PDO::PARAM_NULL;
                    $value = null; 
                } else {
                    $type = PDO::PARAM_STR;
                }
                $stmt->bindValue(':' . $key, $value, $type);
            }

            $stmt->execute();

            if (!$isEditing) {
                $paymentId = (int)$this->db->lastInsertId();
            }
            return (int)$paymentId;

        } catch (PDOException $e) {
            $this->logger->error("Database error saving payment.", ['exception' => $e, 'id' => $paymentId]);
            throw $e;
        } catch (Throwable $e) {
             $this->logger->error("Error saving payment: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
        }
    }
    
    /**
     * Fetches paginated and filtered list of payments.
     * Joins with contacts for paying/receiving names AND with transactions for related info.
     * REVISED: Ensure proper binding of LIMIT/OFFSET parameters.
     */
    public function getFilteredAndPaginated(string $searchTerm = '', int $limit = 15, int $offset = 0): array {
        $this->logger->debug("Fetching filtered payments for list view.", ['search_term' => $searchTerm, 'limit' => $limit, 'offset' => $offset]);
        $baseSql = "SELECT p.*, 
                           pc.name AS paying_contact_name, 
                           rc.name AS receiving_contact_name,
                           t.transaction_type AS related_transaction_type, 
                           t.id AS related_transaction_id, 
                           c_tx.name AS related_transaction_contact_name 
                    FROM payments p
                    LEFT JOIN contacts pc ON p.paying_contact_id = pc.id
                    LEFT JOIN contacts rc ON p.receiving_contact_id = rc.id
                    LEFT JOIN transactions t ON p.related_transaction_id = t.id 
                    LEFT JOIN contacts c_tx ON t.counterparty_contact_id = c_tx.id";

        $whereClauses = [];
        $params = [];

        if (!empty($searchTerm)) {
            $searchPattern = '%' . $searchTerm . '%';
            $whereClauses[] = "(p.notes LIKE :search OR p.paying_details LIKE :search OR p.receiving_details LIKE :search OR pc.name LIKE :search OR rc.name LIKE :search OR c_tx.name LIKE :search OR t.id = :search_int)"; // Changed LIKE to = for t.id
            $params[':search'] = $searchPattern;
            $params[':search_int'] = (is_numeric($searchTerm) ? (int)$searchTerm : -1); // Allows direct search by Transaction ID
        }

        $sql = $baseSql;
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $sql .= " ORDER BY p.payment_date DESC, p.id DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            
            // --- CRITICAL FIX for HY093: Ensure LIMIT and OFFSET are always bound. ---
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            // --- End CRITICAL FIX ---

            // Bind other parameters from WHERE clause
            foreach ($params as $key => $value) { 
                $type = PDO::PARAM_STR;
                if ($key === ':search_int' && is_int($value)) { 
                    $type = PDO::PARAM_INT; 
                }
                $stmt->bindValue($key, $value, $type);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching filtered and paginated payments.", ['exception' => $e, 'sql' => $sql, 'params' => $params]);
            throw $e;
        } catch (Throwable $e) { 
             $this->logger->error("Error fetching filtered and paginated payments: " . $e->getMessage(), ['exception' => $e, 'sql' => $sql, 'params' => $params]);
             throw $e;
        }
    }
    
    public function countFiltered(string $searchTerm = ''): int {
        $this->logger->debug("Counting filtered payments.", ['search_term' => $searchTerm]);
        $baseSql = "SELECT COUNT(DISTINCT p.id) FROM payments p
                    LEFT JOIN contacts pc ON p.paying_contact_id = pc.id
                    LEFT JOIN contacts rc ON p.receiving_contact_id = rc.id
                    LEFT JOIN transactions t ON p.related_transaction_id = t.id
                    LEFT JOIN contacts c_tx ON t.counterparty_contact_id = c_tx.id";

        $whereClauses = [];
        $params = [];

        if (!empty($searchTerm)) {
            $searchPattern = '%' . $searchTerm . '%';
            $whereClauses[] = "(p.notes LIKE :search OR p.paying_details LIKE :search OR p.receiving_details LIKE :search OR pc.name LIKE :search OR rc.name LIKE :search OR c_tx.name LIKE :search OR t.id = :search_int)";
            $params[':search'] = $searchPattern;
            $params[':search_int'] = (is_numeric($searchTerm) ? (int)$searchTerm : -1);
        }

        $sql = $baseSql;
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        try {
            $stmt = $this->db->prepare($sql);
            if (!empty($params)) {
                 foreach ($params as $key => $value) {
                     $type = PDO::PARAM_STR;
                     if ($key === ':search_int' && is_int($value)) { $type = PDO::PARAM_INT; }
                     $stmt->bindValue($key, $value, $type);
                 }
            }
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logger->error("Database error counting filtered payments.", ['exception' => $e, 'sql' => $sql, 'params' => $params]);
            throw $e;
        } catch (Throwable $e) {
             $this->logger->error("Error counting filtered payments: " . $e->getMessage(), ['exception' => $e, 'sql' => $sql, 'params' => $params]);
             throw $e;
        }
    }

    public function update(int $paymentId, array $data): bool {
        if (empty($data)) return false;
        $allowedColumns = ['bank_transaction_id']; // This list is only allowing bank_transaction_id
        $fieldsToUpdate = array_intersect_key($data, array_flip($allowedColumns));
        if(empty($fieldsToUpdate)) return false;

        $updateParts = [];
        foreach (array_keys($fieldsToUpdate) as $field) {
            $updateParts[] = "`" . $field . "` = :" . $field;
        }
        $sql = "UPDATE payments SET " . implode(', ', $updateParts) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
        foreach ($fieldsToUpdate as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        try {
            return $stmt->execute();
        } catch (Throwable $e) {
             $this->logger->error("Error updating payment in PaymentRepository.", ['id' => $paymentId, 'data' => $data, 'exception' => $e]);
             throw $e;
        }
    }
    
    public function delete(int $paymentId): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM payments WHERE id = :id");
            $stmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
             $this->logger->error("Error deleting payment from PaymentRepository.", ['id' => $paymentId, 'exception' => $e]);
             throw $e;
        }
    }
    
    public function findRelatedBankTransaction(int $paymentId): ?array {
        try {
            $stmt = $this->db->prepare("SELECT id, bank_account_id, amount FROM bank_transactions WHERE related_payment_id = :pid LIMIT 1");
            $stmt->bindValue(':pid', $paymentId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
             $this->logger->error("Error finding related bank transaction in PaymentRepository.", ['payment_id' => $paymentId, 'exception' => $e]);
             throw $e;
        }
    }
    
    public function deleteBankTransaction(int $bankTxId): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM bank_transactions WHERE id = :id");
            $stmt->bindValue(':id', $bankTxId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
             $this->logger->error("Error deleting bank transaction in PaymentRepository.", ['bank_tx_id' => $bankTxId, 'exception' => $e]);
             throw $e;
        }
    }
}