<?php

namespace App\Repositories;

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;
use Throwable;

class PaymentRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function getById(int $paymentId): ?array {
        $this->logger->debug("Fetching payment with ID: {$paymentId}.");
        try {
            $sql = "SELECT p.*, 
                           pc.name AS paying_contact_name, 
                           rc.name AS receiving_contact_name 
                    FROM payments p 
                    LEFT JOIN contacts pc ON p.paying_contact_id = pc.id
                    LEFT JOIN contacts rc ON p.receiving_contact_id = rc.id
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

    public function save(array $paymentData): int {
        $paymentId = $paymentData['id'] ?? null;
        $isEditing = $paymentId !== null;
        $this->logger->info(($isEditing ? "Updating" : "Creating") . " payment record.", ['id' => $paymentId]);

        // FIX: Removed 'created_by_user_id' to match the user's actual database schema.
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
    }

    /**
     * دریافت آخرین پرداخت‌ها برای داشبورد
     * @param int $limit
     * @return array
     */
    public function getLatestPayments(int $limit = 10): array
    {
        $this->logger->debug("Fetching latest payments for dashboard.", ['limit' => $limit]);
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
            // اصلاح نوع پرداخت و نام مخاطب
            foreach ($rows as &$row) {
                // نوع پرداخت فارسی
                if (isset($row['direction'])) {
                    $dir = strtolower(trim($row['direction']));
                    if ($dir === 'inflow') {
                        $row['direction'] = 'دریافت';
                        $row['direction_class'] = 'text-success';
                        $row['contact_name'] = $row['receiving_contact_name'] ?? 'نامشخص';
                    } elseif ($dir === 'outflow') {
                        $row['direction'] = 'پرداخت';
                        $row['direction_class'] = 'text-danger';
                        $row['contact_name'] = $row['paying_contact_name'] ?? 'نامشخص';
                    } else {
                        $row['direction'] = '';
                        $row['direction_class'] = '';
                        $row['contact_name'] = 'نامشخص';
                    }
                } else {
                    $row['direction'] = '';
                    $row['direction_class'] = '';
                    $row['contact_name'] = 'نامشخص';
                }
                if (empty($row['contact_name'])) {
                    $row['contact_name'] = 'نامشخص';
                }

                // تاریخ شمسی
                if (!empty($row['payment_date'])) {
                    try {
                        $dt = new \DateTime($row['payment_date']);
                        if (class_exists('Morilog\\Jalali\\Jalalian')) {
                            $row['payment_date_jalali'] = \Morilog\Jalali\Jalalian::fromDateTime($dt)->format('Y/m/d');
                        } else {
                            $row['payment_date_jalali'] = $row['payment_date'];
                        }
                    } catch (\Exception $e) {
                        $row['payment_date_jalali'] = $row['payment_date'];
                    }
                } else {
                    $row['payment_date_jalali'] = '';
                }
            }
            unset($row);
            return $rows;
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching latest payments.", ['exception' => $e]);
            return [];
        }

        if (empty($fieldsToSave)) {
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
                $sql = "INSERT INTO payments (`" . implode('`,`', $insertFields) . "`) VALUES (" . implode(',', $placeholders) . ")";
                $stmt = $this->db->prepare($sql);
            }

            foreach ($fieldsToSave as $key => $value) {
                $type = PDO::PARAM_STR;
                if (is_int($value)) $type = PDO::PARAM_INT;
                elseif ($value === null || $value === '') $type = PDO::PARAM_NULL;
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
        }
    }
    
    public function update(int $paymentId, array $data): bool {
        if (empty($data)) return false;
        $allowedColumns = ['bank_transaction_id'];
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
        return $stmt->execute();
    }
    
    public function delete(int $paymentId): bool {
        $stmt = $this->db->prepare("DELETE FROM payments WHERE id = :id");
        $stmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
    
    public function findRelatedBankTransaction(int $paymentId): ?array {
        $stmt = $this->db->prepare("SELECT id, bank_account_id, amount FROM bank_transactions WHERE related_payment_id = :pid LIMIT 1");
        $stmt->bindValue(':pid', $paymentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    public function deleteBankTransaction(int $bankTxId): bool {
        $stmt = $this->db->prepare("DELETE FROM bank_transactions WHERE id = :id");
        $stmt->bindValue(':id', $bankTxId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
    
    public function getFilteredAndPaginated(string $searchTerm = '', int $limit = 15, int $offset = 0): array {
        $baseSql = "SELECT p.*, pc.name AS paying_contact_name, rc.name AS receiving_contact_name 
                    FROM payments p
                    LEFT JOIN contacts pc ON p.paying_contact_id = pc.id
                    LEFT JOIN contacts rc ON p.receiving_contact_id = rc.id";
        $whereClauses = [];
        $params = [];

        if (!empty($searchTerm)) {
            $searchPattern = '%' . $searchTerm . '%';
            $whereClauses[] = "(p.notes LIKE :search OR p.paying_details LIKE :search OR p.receiving_details LIKE :search OR pc.name LIKE :search OR rc.name LIKE :search)";
            $params[':search'] = $searchPattern;
        }

        $sql = $baseSql;
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $sql .= " ORDER BY p.payment_date DESC, p.id DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => &$value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function countFiltered(string $searchTerm = ''): int {
        $baseSql = "SELECT COUNT(p.id) FROM payments p
                    LEFT JOIN contacts pc ON p.paying_contact_id = pc.id
                    LEFT JOIN contacts rc ON p.receiving_contact_id = rc.id";
        $whereClauses = [];
        $params = [];

        if (!empty($searchTerm)) {
            $searchPattern = '%' . $searchTerm . '%';
            $whereClauses[] = "(p.notes LIKE :search OR p.paying_details LIKE :search OR p.receiving_details LIKE :search OR pc.name LIKE :search OR rc.name LIKE :search)";
            $params[':search'] = $searchPattern;
        }

        $sql = $baseSql;
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bindValue(':search', $params[':search']);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
