<?php

namespace App\Repositories;

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;
use Throwable;

/**
 * کلاس PaymentRepository برای تعامل با جدول payments.
 */
class PaymentRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * دریافت یک پرداخت بر اساس ID.
     *
     * @param int $paymentId شناسه پرداخت.
     * @return array|null آرایه اطلاعات پرداخت یا null.
     * @throws PDOException.
     */
    public function getById(int $paymentId): ?array {
        $this->logger->debug("Fetching payment with ID: {$paymentId}.");
        try {
            // Select all columns including new ones
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
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($payment) $this->logger->debug("Payment found.", ['id' => $paymentId]);
            else $this->logger->debug("Payment not found.", ['id' => $paymentId]);
            return $payment ?: null;
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching payment by ID {$paymentId}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

     /**
      * ذخیره (افزودن یا ویرایش) یک رکورد پرداخت/دریافت در جدول payments.
      * منطق از src/action/payment_save.php گرفته شده.
      *
      * @param array $paymentData آرایه داده‌ها (شامل payment_date, amount_rials, direction, paying_contact_id, paying_details, receiving_contact_id, receiving_details, related_transaction_id, notes).
      * @return int شناسه پرداخت ذخیره شده.
      * @throws PDOException.
      * @throws Exception در صورت داده نامعتبر.
      */
     public function save(array $paymentData): int {
         $paymentId = $paymentData['id'] ?? null;
         $isEditing = $paymentId !== null;
         $this->logger->info(($isEditing ? "Updating" : "Creating") . " payment record.", ['id' => $paymentId, 'amount' => $paymentData['amount_rials'] ?? 'N/A', 'method' => $paymentData['payment_method'] ?? 'N/A']);

         // Prepare list of all possible fields (including new method details)
         $allFields = [
             'payment_date', 'amount_rials', 'direction', 'payment_method',
             'paying_contact_id', 'paying_details', 'receiving_contact_id', 'receiving_details',
             'related_transaction_id', 'notes',
             'method_details_payer_receiver', 'method_details_clearing_type', 'method_details_slip_number',
             'method_details_slip_date', 'method_details_bank_agent', 'method_details_tracking_code',
             'method_details_transfer_date', 'method_details_source_dest_info', 'method_details_terminal_id',
             'method_details_pos_holder', 'method_details_cheque_holder_nid', 'method_details_cheque_account_number',
             'method_details_cheque_holder_name', 'method_details_cheque_type', 'method_details_cheque_serial',
             'method_details_cheque_sayad_id', 'method_details_cheque_due_date'
             // Add 'created_by_user_id' if you added it earlier
         ];

         // Filter $paymentData to only include fields that exist in $allFields and are set
         $fieldsToSave = [];
         foreach ($allFields as $field) {
             if (array_key_exists($field, $paymentData)) { // Check if key exists in input data
                 $fieldsToSave[$field] = $paymentData[$field];
             }
         }

         if (empty($fieldsToSave)) {
             $this->logger->error("No valid fields found to save for payment.", ['id' => $paymentId, 'data' => $paymentData]);
             throw new Exception("داده‌ای برای ذخیره پرداخت یافت نشد.");
         }

         try {
             if ($isEditing) {
                 // Update Query
                 $updateParts = [];
                 foreach (array_keys($fieldsToSave) as $field) {
                     $updateParts[] = "`" . $field . "` = :" . $field;
                 }
                 $sql = "UPDATE payments SET " . implode(', ', $updateParts) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
                 $stmt = $this->db->prepare($sql);
                 $stmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
             } else {
                 // Insert Query
                 $insertFields = array_keys($fieldsToSave);
                 $placeholders = array_map(function ($f) { return ':' . $f; }, $insertFields);
                 $sql = "INSERT INTO payments (`" . implode('`,`', $insertFields) . "`, created_at, updated_at)
                         VALUES (" . implode(',', $placeholders) . ", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                 $stmt = $this->db->prepare($sql);
             }

             // Bind Values
             foreach ($fieldsToSave as $key => $value) {
                  $type = PDO::PARAM_STR; // Default type
                  if (is_int($value)) { $type = PDO::PARAM_INT; }
                  elseif (is_null($value) || $value === '') { $type = PDO::PARAM_NULL; }
                  // Handle date fields specifically if they need formatting check (usually done in Controller)
                  $stmt->bindValue(':' . $key, $value, $type);
             }

             if (!$stmt->execute()) {
                  throw new PDOException("خطا در اجرای کوئری ذخیره پرداخت.");
             }

             if (!$isEditing) {
                 $paymentId = (int)$this->db->lastInsertId();
                 $this->logger->info("Payment record created successfully with ID: {$paymentId}.", ['amount' => $paymentData['amount_rials'] ?? 'N/A']);
             } else {
                  if ($stmt->rowCount() === 0) {
                       $this->logger->warning("Payment update attempted for ID {$paymentId} but no row was affected.");
                  } else {
                       $this->logger->info("Payment updated successfully.", ['id' => $paymentId, 'amount' => $paymentData['amount_rials'] ?? 'N/A']);
                  }
             }

             return (int)$paymentId;

         } catch (PDOException $e) {
             $this->logger->error("Database error saving payment: " . $e->getMessage(), ['exception' => $e, 'id' => $paymentId, 'data' => $paymentData]);
              // می توانید کدهای خطای خاص (مثلاً Foreign Key) را اینجا مدیریت کنید
              throw $e;
         } catch (Throwable $e) {
             $this->logger->error("Error saving payment: " . $e->getMessage(), ['exception' => $e, 'id' => $paymentId, 'data' => $paymentData]);
             throw $e;
         }
     }

     /**
      * حذف یک رکورد پرداخت از جدول payments.
      * منطق از src/action/payment_delete.php گرفته شده.
      * **توجه:** این متد فقط رکورد payments را حذف می کند. حذف تراکنش بانکی مرتبط و برگرداندن موجودی بانکی باید در Service مدیریت شود.
      *
      * @param int $paymentId شناسه پرداخت برای حذف.
      * @return bool True اگر حذف شد، False اگر یافت نشد.
      * @throws PDOException.
      */
     public function delete(int $paymentId): bool {
         $this->logger->info("Attempting to delete payment with ID: {$paymentId}.");
         try {
             $sql = "DELETE FROM payments WHERE id = :id";
             $stmt = $this->db->prepare($sql);
             $stmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
             $stmt->execute();

             $deletedCount = $stmt->rowCount();
             if ($deletedCount > 0) {
                 $this->logger->info("Payment deleted successfully.", ['id' => $paymentId]);
                 return true;
             } else {
                 $this->logger->warning("Payment delete attempted for ID {$paymentId} but no row was affected (Not found?).");
                 return false;
             }
         } catch (PDOException $e) {
              $this->logger->error("Database error deleting payment with ID {$paymentId}: " . $e->getMessage(), ['exception' => $e]);
              throw $e;
         } catch (Throwable $e) {
             $this->logger->error("Error deleting payment with ID {$paymentId}: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
         }
     }

    /**
     * پیدا کردن تراکنش بانکی مرتبط با یک رکورد پرداخت.
     * منطق از src/action/payment_delete.php و payment_save.php گرفته شده.
     *
     * @param int $paymentId شناسه پرداخت.
     * @return array|null آرایه اطلاعات تراکنش بانکی مرتبط یا null.
     * @throws PDOException.
     */
    public function findRelatedBankTransaction(int $paymentId): ?array {
        $this->logger->debug("Searching for related bank transaction for payment ID: {$paymentId}.");
        try {
            $sql = "SELECT id, bank_account_id, amount FROM bank_transactions WHERE related_payment_id = :pid LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':pid', $paymentId, PDO::PARAM_INT);
            $stmt->execute();
            $bankTx = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($bankTx) $this->logger->debug("Related bank transaction found.", ['payment_id' => $paymentId, 'bank_tx_id' => $bankTx['id']]);
            else $this->logger->debug("No related bank transaction found.", ['payment_id' => $paymentId]);
            return $bankTx ?: null;
        } catch (PDOException $e) {
            $this->logger->error("Database error finding related bank transaction for payment ID {$paymentId}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * حذف یک رکورد تراکنش بانکی بر اساس ID.
     * منطق از src/action/payment_delete.php گرفته شده.
     *
     * @param int $bankTxId شناسه تراکنش بانکی برای حذف.
     * @return bool True اگر حذف شد، False اگر یافت نشد.
     * @throws PDOException.
     */
    public function deleteBankTransaction(int $bankTxId): bool {
        $this->logger->info("Attempting to delete bank transaction with ID: {$bankTxId}.");
        try {
            $sql = "DELETE FROM bank_transactions WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $bankTxId, PDO::PARAM_INT);
            $stmt->execute();

            $deletedCount = $stmt->rowCount();
            if ($deletedCount > 0) {
                $this->logger->info("Bank transaction deleted successfully.", ['id' => $bankTxId]);
                return true;
            } else {
                $this->logger->warning("Bank transaction delete attempted for ID {$bankTxId} but no row was affected (Not found?).");
                return false;
            }
        } catch (PDOException $e) {
             $this->logger->error("Database error deleting bank transaction with ID {$bankTxId}: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
        }
    }

     /**
      * ثبت یک رکورد تراکنش بانکی جدید در جدول bank_transactions.
      * منطق از src/action/payment_save.php گرفته شده.
      *
      * @param array $bankTxData آرایه داده‌ها (شامل bank_account_id, transaction_date, amount, type, description, related_payment_id).
      * @return int شناسه تراکنش بانکی ثبت شده.
      * @throws PDOException.
      * @throws Exception در صورت داده نامعتبر.
      */
     public function saveBankTransaction(array $bankTxData): int {
         $this->logger->info("Creating new bank transaction record.", ['amount' => $bankTxData['amount'] ?? 'N/A', 'account_id' => $bankTxData['bank_account_id'] ?? 'N/A']);

         try {
             $sql = "INSERT INTO bank_transactions (bank_account_id, transaction_date, amount, type, description, related_payment_id)
                         VALUES (:acc_id, :tx_date, :amount, :type, :desc, :p_id)";
             $stmt = $this->db->prepare($sql);

             // Bind Values
             $stmt->bindValue(':acc_id', $bankTxData['bank_account_id'] ?? null, PDO::PARAM_INT);
             $stmt->bindValue(':tx_date', $bankTxData['transaction_date'] ?? null, PDO::PARAM_STR);
             $stmt->bindValue(':amount', $bankTxData['amount'] ?? null, PDO::PARAM_STR); // Decimal bind as STR
             $stmt->bindValue(':type', $bankTxData['type'] ?? null, PDO::PARAM_STR);
             $stmt->bindValue(':desc', $bankTxData['description'] ?? null, PDO::PARAM_STR);
             $stmt->bindValue(':p_id', $bankTxData['related_payment_id'] ?? null, PDO::PARAM_INT);

             if (!$stmt->execute()) {
                  throw new PDOException("خطا در اجرای کوئری ثبت تراکنش بانکی.");
             }

             $bankTxId = (int)$this->db->lastInsertId();
             $this->logger->info("Bank transaction created successfully with ID: {$bankTxId}.", ['payment_id' => $bankTxData['related_payment_id'] ?? 'N/A']);
             return $bankTxId;

         } catch (PDOException $e) {
             $this->logger->error("Database error saving bank transaction: " . $e->getMessage(), ['exception' => $e, 'data' => $bankTxData]);
              throw $e;
         } catch (Throwable $e) {
             $this->logger->error("Error saving bank transaction: " . $e->getMessage(), ['exception' => $e, 'data' => $bankTxData]);
             throw $e;
         }
     }

    /**
     * Retrieves the latest payments with contact names.
     *
     * @param int $limit Maximum number of payments to return.
     * @return array List of payments.
     */
    public function getLatestPayments(int $limit = 5): array
    {
        $this->logger->debug("Fetching latest payments.", ['limit' => $limit]);
        $sql = "SELECT p.id, p.payment_date, p.amount_rials, p.direction, 
                       pc.name AS paying_contact_name, rc.name AS receiving_contact_name 
                FROM payments p 
                LEFT JOIN contacts pc ON p.paying_contact_id = pc.id 
                LEFT JOIN contacts rc ON p.receiving_contact_id = rc.id 
                ORDER BY p.payment_date DESC, p.id DESC 
                LIMIT :limit";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->debug("Latest payments fetched.", ['count' => count($results)]);
            return $results ?: [];
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching latest payments.", ['exception' => $e]);
            return [];
        }
    }

    /**
     * دریافت لیست پرداخت‌ها با فیلتر، مرتب‌سازی و صفحه‌بندی.
     *
     * @param string $searchTerm عبارت جستجو (در توضیحات، نام مخاطب پرداخت‌کننده/دریافت‌کننده).
     * @param int $limit تعداد رکورد در هر صفحه.
     * @param int $offset شروع رکوردها برای صفحه‌بندی.
     * @param array $filters آرایه فیلترهای اضافی (مثلاً ['direction' => 'inflow', 'contact_id' => 5]).
     * @param string $orderBy ستون برای مرتب‌سازی.
     * @param string $orderDir جهت مرتب‌سازی (ASC یا DESC).
     * @return array لیست پرداخت‌ها.
     * @throws PDOException.
     */
    public function getFilteredAndPaginated(
        string $searchTerm = '',
        int $limit = 15,
        int $offset = 0,
        array $filters = [],
        string $orderBy = 'p.payment_date', // Default order by payment date
        string $orderDir = 'DESC' // Default newest first
    ): array {
        $this->logger->debug("Fetching filtered and paginated payments.", [
            'search' => $searchTerm, 'limit' => $limit, 'offset' => $offset, 'filters' => $filters, 'order' => "$orderBy $orderDir"
        ]);

        $baseSql = "SELECT
                        p.*, /* Select all columns from payments */
                        pc.name AS paying_contact_name,
                        rc.name AS receiving_contact_name,
                        bt.id AS related_bank_transaction_id,
                        ba.account_name AS bank_account_name,
                        ba.id AS bank_account_id
                    FROM
                        payments p
                    LEFT JOIN contacts pc ON p.paying_contact_id = pc.id
                    LEFT JOIN contacts rc ON p.receiving_contact_id = rc.id
                    LEFT JOIN bank_transactions bt ON bt.related_payment_id = p.id
                    LEFT JOIN bank_accounts ba ON bt.bank_account_id = ba.id";

        $whereClauses = [];
        $params = [];

        // Search Term (simple search in notes, details, contact names)
        if (!empty($searchTerm)) {
            $searchPattern = '%' . $searchTerm . '%';
            $whereClauses[] = "(p.notes LIKE :search_notes OR p.paying_details LIKE :search_p_details OR p.receiving_details LIKE :search_r_details OR pc.name LIKE :search_pc_name OR rc.name LIKE :search_rc_name)";
            $params[':search_notes'] = $searchPattern;
            $params[':search_p_details'] = $searchPattern;
            $params[':search_r_details'] = $searchPattern;
            $params[':search_pc_name'] = $searchPattern;
            $params[':search_rc_name'] = $searchPattern;
        }

        // Additional Filters (Example: Direction)
        if (!empty($filters['direction']) && in_array($filters['direction'], ['inflow', 'outflow'])) {
            $whereClauses[] = "p.direction = :direction";
            $params[':direction'] = $filters['direction'];
        }
        // Example: Contact ID (either payer or receiver)
        if (!empty($filters['contact_id']) && filter_var($filters['contact_id'], FILTER_VALIDATE_INT)) {
            $whereClauses[] = "(p.paying_contact_id = :contact_id OR p.receiving_contact_id = :contact_id)";
            $params[':contact_id'] = (int)$filters['contact_id'];
        }
        // Add more filters as needed (date range, bank account, etc.)

        $sql = $baseSql;
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        // Validate Order By column to prevent SQL injection
        // Define allowed columns for ordering
        $allowedOrderByColumns = ['p.id', 'p.payment_date', 'p.amount_rials', 'p.direction', 'pc.name', 'rc.name', 'ba.account_name'];
        if (!in_array($orderBy, $allowedOrderByColumns)) {
            $this->logger->warning("Invalid orderBy column specified: {$orderBy}. Defaulting to p.payment_date.", ['requested' => $orderBy]);
            $orderBy = 'p.payment_date'; // Default to a safe column
        }
        // Validate Order Direction
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC'; // Default to DESC if invalid

        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        // Add Pagination
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        try {
            $stmt = $this->db->prepare($sql);
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->debug("Successfully fetched " . count($results) . " payments.");
            return $results;
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching filtered payments: " . $e->getMessage(), [
                'exception' => $e, 'sql' => $sql, 'params' => $params // Log SQL and params on error
            ]);
            throw $e;
        }
    }

    /**
     * شمارش تعداد کل پرداخت‌های فیلتر شده.
     *
     * @param string $searchTerm عبارت جستجو.
     * @param array $filters آرایه فیلترهای اضافی.
     * @return int تعداد کل رکوردهای یافت شده.
     * @throws PDOException.
     */
    public function countFiltered(
        string $searchTerm = '',
        array $filters = []
    ): int {
        $this->logger->debug("Counting filtered payments.", [
            'search' => $searchTerm, 'filters' => $filters
        ]);

        // Base SQL without selecting specific columns, just join for filtering
        $baseSql = "SELECT COUNT(p.id)
                    FROM payments p
                    LEFT JOIN contacts pc ON p.paying_contact_id = pc.id
                    LEFT JOIN contacts rc ON p.receiving_contact_id = rc.id
                    LEFT JOIN bank_transactions bt ON bt.related_payment_id = p.id
                    LEFT JOIN bank_accounts ba ON bt.bank_account_id = ba.id";

        $whereClauses = [];
        $params = [];

        // Search Term (same logic as getFilteredAndPaginated)
        if (!empty($searchTerm)) {
            $searchPattern = '%' . $searchTerm . '%';
            $whereClauses[] = "(p.notes LIKE :search_notes OR p.paying_details LIKE :search_p_details OR p.receiving_details LIKE :search_r_details OR pc.name LIKE :search_pc_name OR rc.name LIKE :search_rc_name)";
            $params[':search_notes'] = $searchPattern;
            $params[':search_p_details'] = $searchPattern;
            $params[':search_r_details'] = $searchPattern;
            $params[':search_pc_name'] = $searchPattern;
            $params[':search_rc_name'] = $searchPattern;
        }

        // Additional Filters (same logic as getFilteredAndPaginated)
        if (!empty($filters['direction']) && in_array($filters['direction'], ['inflow', 'outflow'])) {
            $whereClauses[] = "p.direction = :direction";
            $params[':direction'] = $filters['direction'];
        }
        if (!empty($filters['contact_id']) && filter_var($filters['contact_id'], FILTER_VALIDATE_INT)) {
            $whereClauses[] = "(p.paying_contact_id = :contact_id OR p.receiving_contact_id = :contact_id)";
            $params[':contact_id'] = (int)$filters['contact_id'];
        }
        // Add more filters as needed

        $sql = $baseSql;
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        try {
            $stmt = $this->db->prepare($sql);
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            $stmt->execute();
            $count = (int)($stmt->fetchColumn() ?: 0);
            $this->logger->debug("Found {$count} matching payments.");
            return $count;
        } catch (PDOException $e) {
            $this->logger->error("Database error counting filtered payments: " . $e->getMessage(), [
                'exception' => $e, 'sql' => $sql, 'params' => $params
            ]);
            throw $e;
        }
    }

    /**
     * دریافت آخرین پرداخت‌ها (برای داشبورد و ...)
     * منطق از src/includes/dashboard_data.php گرفته شده.
     */
}