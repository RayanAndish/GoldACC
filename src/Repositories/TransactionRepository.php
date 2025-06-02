<?php

namespace App\Repositories;

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;
use Throwable;
use RuntimeException;
use App\Repositories\TransactionItemRepository;

/**
 * کلاس TransactionRepository برای تعامل با جدول transactions.
 * شامل خواندن، ذخیره، حذف و به‌روزرسانی وضعیت تحویل.
 */
class TransactionRepository {

    private PDO $db;
    private Logger $logger;
    private TransactionItemRepository $transactionItemRepository;

    public function __construct(PDO $db, Logger $logger, TransactionItemRepository $transactionItemRepository) {
        $this->db = $db;
        $this->logger = $logger;
        $this->transactionItemRepository = $transactionItemRepository;
    }

    /**
     * دریافت یک معامله بر اساس ID.
     *
     * @param int $transactionId شناسه معامله.
     * @param bool $lock For UPDATE (قفل کردن رکورد برای تراکنش).
     * @return array|null آرایه اطلاعات معامله یا null.
     * @throws PDOException.
     */
    public function getById(int $transactionId, bool $lock = false): ?array {
        $this->logger->debug("Fetching transaction with ID: {$transactionId} (Lock: " . ($lock ? 'Yes' : 'No') . ").");
        try {
            $sql = "SELECT * FROM transactions WHERE id = :id LIMIT 1" . ($lock ? " FOR UPDATE" : "");
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $transactionId, PDO::PARAM_INT);
            $stmt->execute();
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($transaction) $this->logger->debug("Transaction found.", ['id' => $transactionId]);
            else $this->logger->debug("Transaction not found.", ['id' => $transactionId]);
            return $transaction ?: null;
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching transaction by ID {$transactionId}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * متد کمکی برای دریافت معامله با قفل برای به‌روزرسانی.
     * توسط DeliveryService استفاده می شود.
     *
     * @param int $transactionId
     * @return array|null
     * @throws PDOException
     */
    public function getByIdWithLock(int $transactionId): ?array {
        return $this->getById($transactionId, true);
    }


     /**
      * ذخیره (افزودن یا ویرایش) یک رکورد معامله.
      * منطق از src/action/transaction_save.php گرفته شده.
      * این متد فقط رکورد اصلی transactions را ذخیره می کند. به‌روزرسانی موجودی یا تراکنش‌های بانکی مرتبط باید در Service انجام شود.
      *
      * @param array $transactionData آرایه داده‌ها.
      * @return int شناسه معامله ذخیره شده.
      * @throws PDOException.
      * @throws Exception در صورت داده نامعتبر یا خطای Unique Constraint.
      */
     public function save(array $transactionData): int {
         $transactionId = $transactionData['id'] ?? null;
         $isEditing = $transactionId !== null;
         $this->logger->info(($isEditing ? "Updating" : "Creating") . " transaction record.", ['id' => $transactionId, 'type' => $transactionData['transaction_type'] ?? 'N/A']);

         // اگر درج جدید است، کلید id را بدون توجه به مقدار حذف کن
         if (!$isEditing) {
             unset($transactionData['id']);
         }

         // اعتبار سنجی داده ها قبل از ارسال به Repository (بهتر است در Service یا Controller انجام شود)
         // اینجا فقط چک میکنیم که فیلدهای لازم در آرایه وجود داشته باشند.

         try {
             // استفاده از فهرست فیلدها برای ساخت دینامیک کوئری (مثل کد قدیمی)
             $fields = array_keys($transactionData);
             $placeholders = array_map(function ($f) { return ':' . $f; }, $fields);
             $this->logger->debug("TRANSACTION DATA FOR DB", ['transaction_data' => $transactionData]);
             if ($isEditing) { // Update
                 $sql_parts = array_map(function($f) { return "`" . $f . "` = :" . $f; }, $fields);
                 $sql = "UPDATE transactions SET " . implode(', ', $sql_parts) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
             } else { // Insert
                 // حذف فیلدهای created_at و updated_at از داده‌های ورودی
                 $fields = array_filter($fields, function($field) {
                     return !in_array($field, ['created_at', 'updated_at']);
                 });
                 $placeholders = array_map(function ($f) { return ':' . $f; }, $fields);
                 
                 // اضافه کردن فیلدهای created_at و updated_at با مقدار پیش‌فرض
                 $sql = "INSERT INTO transactions (`" . implode('`,`', $fields) . "`, created_at, updated_at) 
                         VALUES (" . implode(',', $placeholders) . ", NOW(), NOW())";
             }
             $stmt = $this->db->prepare($sql);

             // Bind Values (با در نظر گرفتن Type Hinting در PHP 8+ و Nullable)
             foreach ($transactionData as $key => $value) {
                 // فقط برای فیلدهایی که در کوئری استفاده شده‌اند bind انجام شود
                 if (in_array($key, $fields)) {
                     $type = PDO::PARAM_STR; // Default for most values (string, decimal, enum)
                     if (is_int($value)) {$type=PDO::PARAM_INT;} // Integer types (id, quantity, year, carat, role_id, contact_id, office_id, user_id)
                     elseif (is_null($value)) {$type=PDO::PARAM_NULL;}
                     elseif (is_bool($value)) {$type=PDO::PARAM_BOOL;} // tinyint(1)
                     $stmt->bindValue(':'.$key, $value, $type);
                 }
             }

             if (!$stmt->execute()) {
                  $einfo=$stmt->errorInfo();
                  $this->logger->error("Database error executing transaction save query: " . ($einfo[2]??'Unknown Error'), ['exception' => $einfo, 'id' => $transactionId, 'data' => $transactionData]);
                  throw new PDOException("خطا در اجرای کوئری ذخیره معامله: " . ($einfo[2]??'Unknown Error'), $einfo[1] ?? 0, $einfo); // پرتاب PDOException با جزئیات
             }

             if (!$isEditing) {
                 $transactionId = (int)$this->db->lastInsertId();
                 $this->logger->info("Transaction record created successfully with ID: {$transactionId}.", ['type' => $transactionData['transaction_type'] ?? 'N/A']);
             } else {
                  if ($stmt->rowCount() === 0) {
                       $this->logger->warning("Transaction update attempted for ID {$transactionId} but no row was affected.");
                  } else {
                       $this->logger->info("Transaction updated successfully.", ['id' => $transactionId, 'type' => $transactionData['transaction_type'] ?? 'N/A']);
                  }
             }

             return (int)$transactionId;

         } catch (PDOException $e) {
             $this->logger->error("Database error saving transaction: " . $e->getMessage(), ['exception' => $e, 'id' => $transactionId, 'data' => $transactionData]);
              // بررسی کدهای خطای خاص (مانند Foreign Key)
             if ($e->getCode() === '23000') { // Foreign key violation or Unique constraint violation
                  throw new Exception("خطا در داده‌های معامله: ارجاع نامعتبر یا داده تکراری.", 0, $e);
             }
             throw $e;
         } catch (Throwable $e) {
             $this->logger->error("Error saving transaction: " . $e->getMessage(), ['exception' => $e, 'id' => $transactionId, 'data' => $transactionData]);
             throw $e;
         }
     }


    /**
     * حذف یک معامله بر اساس ID.
     * منطق از src/action/transaction_delete.php گرفته شده.
     * **توجه:** این متد فقط رکورد transactions را حذف می کند. بازگرداندن موجودی باید در Service مدیریت شود.
     *
     * @param int $transactionId شناسه معامله برای حذف.
     * @return bool True اگر حذف شد، False اگر یافت نشد.
     * @throws PDOException.
     */
    public function delete(int $transactionId): bool {
        $this->logger->info("Attempting to delete transaction with ID: {$transactionId}.");
        // ** نکته: بازگرداندن موجودی مرتبط باید قبل از فراخوانی این متد در Service مدیریت شود. **
        try {
            $sql = "DELETE FROM transactions WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $transactionId, PDO::PARAM_INT);
            $stmt->execute();

            $deletedCount = $stmt->rowCount();
            if ($deletedCount > 0) {
                $this->logger->info("Transaction deleted successfully.", ['id' => $transactionId]);
                return true;
            } else {
                $this->logger->warning("Transaction delete attempted for ID {$transactionId} but no row was affected (Not found?).");
                return false;
            }
        } catch (PDOException $e) {
             $this->logger->error("Database error deleting transaction with ID {$transactionId}: " + $e->getMessage(), ['exception' => $e]);
             throw $e;
        } catch (Throwable $e) {
             $this->logger->error("Error deleting transaction with ID {$transactionId}: " + $e->getMessage(), ['exception' => $e]);
             throw $e;
        }
    }

    /**
     * به‌روزرسانی وضعیت تحویل/دریافت معامله.
     * منطق از src/action/complete_delivery.php گرفته شده.
     * **توجه:** این متد فقط وضعیت را به‌روز می کند. به‌روزرسانی موجودی مرتبط باید در Service مدیریت شود.
     *
     * @param int $transactionId شناسه معامله.
     * @param string $status وضعیت جدید (مثلاً 'completed').
     * @param string $deliveryDate تاریخ و زمان تکمیل تحویل (SQL datetime).
     * @param string $expectedStatus وضعیت مورد انتظار فعلی برای جلوگیری از Race Condition (مثلاً 'pending_receipt').
     * @return bool True اگر وضعیت به‌روز شد (و تعداد سطر تحت تاثیر ۱ بود)، False در غیر این صورت.
     * @throws PDOException.
     */
    public function updateDeliveryStatus(int $transactionId, string $status, string $deliveryDate, string $expectedStatus): bool {
         $this->logger->info("Attempting to update delivery status for transaction ID {$transactionId} to '{$status}'.");
         // ** نکته: به‌روزرسانی موجودی مرتبط باید قبل از فراخوانی این متد در Service مدیریت شود. **
         try {
             // کوئری از complete_delivery.php با چک وضعیت فعلی
             $sql = "UPDATE transactions SET delivery_status = :status, delivery_date = :ddate WHERE id = :id AND delivery_status = :expected_status";
             $stmt = $this->db->prepare($sql);
             $stmt->bindValue(':status', $status, PDO::PARAM_STR);
             $stmt->bindValue(':ddate', $deliveryDate, PDO::PARAM_STR);
             $stmt->bindValue(':id', $transactionId, PDO::PARAM_INT);
             $stmt->bindValue(':expected_status', $expectedStatus, PDO::PARAM_STR);
             $stmt->execute();

             $updatedCount = $stmt->rowCount();
             if ($updatedCount === 1) {
                  $this->logger->info("Transaction delivery status updated successfully.", ['id' => $transactionId, 'status' => $status]);
                 return true;
             } elseif ($updatedCount === 0) {
                  // این ممکن است به دلیل اینکه وضعیت از قبل تغییر کرده یا ID اشتباه است، رخ دهد.
                  $this->logger->warning("Transaction delivery status update attempted for ID {$transactionId} but no row was affected (Status mismatch or not found).", ['status' => $status, 'expected' => $expectedStatus]);
                  return false; // نشان دهنده عدم موفقیت در به‌روزرسانی وضعیت مورد انتظار
             } else {
                  // نباید رخ دهد، اما اگر بیش از یک سطر تحت تاثیر قرار گرفت (مشکل جدی)
                  $this->logger->critical("More than one row affected when updating transaction delivery status for ID {$transactionId}. Affected: {$updatedCount}", ['status' => $status]);
                  throw new Exception("خطای بحرانی در به‌روزرسانی وضعیت تحویل معامله.");
             }

         } catch (PDOException $e) {
             $this->logger->error("Database error updating transaction delivery status for ID {$transactionId}: " . $e->getMessage(), ['exception' => $e, 'status' => $status]);
             throw $e;
         } catch (Throwable $e) {
              $this->logger->error("Error updating transaction delivery status for ID {$transactionId}: " . $e->getMessage(), ['exception' => $e, 'status' => $status]);
              throw $e;
         }
    }

    /**
     * Retrieves the latest transactions (buy or sell) with contact names.
     *
     * @param string|null $type 'buy', 'sell', or null for both.
     * @param int $limit Maximum number of transactions to return.
     * @return array List of transactions.
     */
    public function getLatestTransactions(?string $type = null, int $limit = 5): array
    {
        $this->logger->debug("Fetching latest transactions.", ['type' => $type, 'limit' => $limit]);
        $params = [];
        $sql = "SELECT
                    t.id as transaction_id,
                    t.transaction_type,
                    t.transaction_date,
                    t.total_value_rials, -- مبلغ کل خود معامله اصلی
                    c.name as counterparty_name,
                    pc.base_category as gold_product_type, -- نوع پایه محصول از جدول product_categories
                    p.name as product_name,                -- نام محصول از جدول products
                    ti.weight_grams as gold_weight_grams,   -- وزن آیتم
                    ti.quantity,                            -- تعداد آیتم
                    ti.carat as gold_carat                  -- عیار آیتم
                FROM
                    transactions t
                JOIN
                    transaction_items ti ON t.id = ti.transaction_id
                JOIN
                    products p ON ti.product_id = p.id
                JOIN
                    product_categories pc ON p.category_id = pc.id
                LEFT JOIN
                    contacts c ON t.counterparty_contact_id = c.id";

        if ($type === 'buy' || $type === 'sell') {
            $sql .= " WHERE t.transaction_type = :type";
            $params[':type'] = $type;
        } elseif ($type !== null) {
            $this->logger->warning("Invalid transaction type specified for getLatestTransactions.", ['type' => $type]);
            // Return empty if invalid type specified?
             return [];
        }

        $sql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT :limit";
        $params[':limit'] = $limit;

        try {
            $stmt = $this->db->prepare($sql);
            // Bind parameters correctly (limit needs INT type)
            foreach ($params as $key => $value) {
                 $stmt->bindValue($key, $value, ($key === ':limit') ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->debug("Latest transactions fetched.", ['count' => count($results)]);
            return $results ?: [];
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching latest transactions.", ['exception' => $e]);
            return [];
        }
    }

    /**
     * Retrieves a summary of pending transactions (receipts or deliveries).
     *
     * @param string $deliveryStatus 'pending_receipt' or 'pending_delivery'.
     * @return array Summary list grouped by product type and carat.
     */
    public function getPendingSummary(string $deliveryStatus): array
    {
        $this->logger->debug("Fetching pending transaction summary.", ['status' => $deliveryStatus]);
        $transactionType = ($deliveryStatus === 'pending_receipt') ? 'buy' : (($deliveryStatus === 'pending_delivery') ? 'sell' : null);

        if ($transactionType === null) {
             $this->logger->error("Invalid delivery status specified for getPendingSummary.", ['status' => $deliveryStatus]);
             return [];
        }

        $sql = "SELECT
                    pc.base_category as gold_product_type,
                    ti.carat as gold_carat,
                    SUM(ti.quantity) as total_qty,
                    SUM(ti.weight_grams) as total_weight
                FROM
                    transactions t
                JOIN
                    transaction_items ti ON t.id = ti.transaction_id
                JOIN
                    products p ON ti.product_id = p.id
                JOIN
                    product_categories pc ON p.category_id = pc.id
                WHERE
                    t.transaction_type = :type AND t.delivery_status = :status
                GROUP BY
                    pc.base_category, ti.carat
                HAVING
                    SUM(ti.quantity) > 0 OR SUM(ti.weight_grams) > 0
                ORDER BY
                    pc.base_category";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':type', $transactionType, PDO::PARAM_STR);
            $stmt->bindParam(':status', $deliveryStatus, PDO::PARAM_STR);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->debug("Pending summary fetched.", ['status' => $deliveryStatus, 'count' => count($results)]);
            return $results ?: [];
        } catch (Throwable $e) {
             // Gracefully handle if delivery_status column doesn't exist (from dash.php error handling)
             if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'delivery_status') !== false) {
                  $this->logger->warning("Column 'delivery_status' not found, cannot fetch pending summary.", ['status' => $deliveryStatus]);
                  return [];
             }
            $this->logger->error("Database error fetching pending summary.", ['status' => $deliveryStatus, 'exception' => $e]);
            return [];
        }
    }

    /**
     * Counts the total number of transactions matching the given filters.
     * Used for pagination.
     *
     * @param array $filters Associative array of filters (e.g., ['transaction_type' => 'sell', 'status' => 'completed']).
     * @param string|null $searchTerm Optional search term for filtering.
     * @return int Total number of matching transactions.
     */
    public function countFiltered(array $filters = [], ?string $searchTerm = null): int
    {
        $this->logger->debug("Counting filtered transactions.", ['filters' => $filters, 'searchTerm' => $searchTerm]);
        [$whereClause, $params] = $this->buildWhereClause($filters, $searchTerm);

        $sql = "SELECT COUNT(DISTINCT t.id) 
                FROM transactions t 
                LEFT JOIN contacts c ON t.counterparty_contact_id = c.id 
                -- LEFT JOIN assay_offices ao ON t.assay_office_id = ao.id -- REMOVED: Column doesn't exist 
                {$whereClause}";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $count = (int)$stmt->fetchColumn();
            $this->logger->debug("Filtered transaction count retrieved.", ['count' => $count]);
            return $count;
        } catch (Throwable $e) {
            $this->logger->error("Database error counting filtered transactions.", ['exception' => $e]);
            throw new \RuntimeException("Could not count transactions due to a database error.", 0, $e);
        }
    }

    /**
     * متد خصوصی برای ساخت WHERE داینامیک بر اساس فیلترها و جستجو
     */
    private function buildWhereClause(array $filters = [], ?string $searchTerm = null): array {
        $where = [];
        $params = [];
        // فیلتر نوع معامله
        if (isset($filters['type']) && $filters['type'] !== '' && $filters['type'] !== false && $filters['type'] !== null) {
            $where[] = 't.transaction_type = :type';
            $params[':type'] = $filters['type'];
        }
        // فیلتر نوع محصول
        if (isset($filters['product_type']) && $filters['product_type'] !== '' && $filters['product_type'] !== false && $filters['product_type'] !== null) {
            $where[] = 't.product_type = :product_type';
            $params[':product_type'] = $filters['product_type'];
        }
        // فیلتر مخاطب
        if (isset($filters['contact_id']) && is_numeric($filters['contact_id']) && $filters['contact_id'] > 0) {
            $where[] = 't.counterparty_contact_id = :contact_id';
            $params[':contact_id'] = $filters['contact_id'];
        }
        // فیلتر وضعیت تحویل
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== false && $filters['status'] !== null) {
            $where[] = 't.delivery_status = :status';
            $params[':status'] = $filters['status'];
        }
        // فیلتر تاریخ شروع
        if (isset($filters['start_date_sql']) && $filters['start_date_sql'] !== '' && $filters['start_date_sql'] !== false && $filters['start_date_sql'] !== null) {
            $where[] = 't.transaction_date >= :date_start';
            $params[':date_start'] = $filters['start_date_sql'];
        }
        // فیلتر تاریخ پایان
        if (isset($filters['end_date_sql']) && $filters['end_date_sql'] !== '' && $filters['end_date_sql'] !== false && $filters['end_date_sql'] !== null) {
            $where[] = 't.transaction_date <= :date_end';
            $params[':date_end'] = $filters['end_date_sql'];
        }
        // جستجوی عمومی (در نام مخاطب یا توضیحات)
        if (!empty($searchTerm)) {
            $whereOr = [];
            $params = [];
            if (is_numeric($searchTerm)) {
                $whereOr[] = 't.id = :search_id';
                $whereOr[] = 't.weight_grams = :search_weight';
                $whereOr[] = 't.quantity = :search_qty';
                $whereOr[] = 't.mazaneh_price = :search_base';
                $whereOr[] = 't.total_value_rials = :search_total';
                $whereOr[] = 't.carat = :search_carat';
                $whereOr[] = 't.melted_tag_number LIKE :search_tag';
                $params[':search_id'] = (int)$searchTerm;
                $params[':search_weight'] = $searchTerm;
                $params[':search_qty'] = $searchTerm;
                $params[':search_base'] = $searchTerm;
                $params[':search_total'] = $searchTerm;
                $params[':search_carat'] = $searchTerm;
                $params[':search_tag'] = '%' . $searchTerm . '%';
            }
            // همیشه جستجو در فیلدهای متنی (حتی اگر فقط عدد باشد)
            $whereOr[] = 'c.name LIKE :search1';
            $whereOr[] = 't.notes LIKE :search2';
            $whereOr[] = 't.product_type LIKE :search3';
            // $whereOr[] = 'ao.name LIKE :search4'; // REMOVED
            $params[':search1'] = '%' . $searchTerm . '%';
            $params[':search2'] = '%' . $searchTerm . '%';
            $params[':search3'] = '%' . $searchTerm . '%';
            // $params[':search4'] = '%' . $searchTerm . '%'; // REMOVED
            $where[] = '(' . implode(' OR ', $whereOr) . ')';
        }
        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        return [$whereClause, $params];
    }

    /**
     * دریافت لیست معاملات با فیلتر، جستجو، صفحه‌بندی و مرتب‌سازی
     */
    public function getFilteredAndPaginated(array $filters = [], ?string $searchTerm = null, int $limit = 15, int $offset = 0): array {
        $this->logger->debug("Fetching filtered & paginated transactions.", ['filters' => $filters, 'searchTerm' => $searchTerm, 'limit' => $limit, 'offset' => $offset]);
        [$whereClause, $params] = $this->buildWhereClause($filters, $searchTerm);
        // REMOVED assay_office_name from SELECT and the JOIN
        $sql = "SELECT t.*, c.name AS counterparty_name -- REMOVED assay_office info
                FROM transactions t
                LEFT JOIN contacts c ON t.counterparty_contact_id = c.id
                -- LEFT JOIN assay_offices ao ON t.assay_office_id = ao.id -- REMOVED
                $whereClause
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT :limit OFFSET :offset";
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->debug("Filtered transactions fetched.", ['count' => count($results)]);
            return $results ?: [];
        } catch (\Throwable $e) {
            $this->logger->error("Database error fetching filtered transactions.", ['exception' => $e]);
            return [];
        }
    }

    /**
     * واکشی معاملات بر اساس فیلترهای صدور فاکتور (مخاطب، نوع معامله، بازه تاریخ)
     * @param array $filters
     * @return array
     */
    public function getFilteredTransactionsForInvoice(array $filters): array
    {
        $sql = "SELECT * FROM transactions WHERE counterparty_contact_id = :contact_id AND transaction_type = :type";
        $params = [
            ':contact_id' => $filters['counterparty_contact_id'],
            ':type' => $filters['transaction_type'],
        ];
        if (!empty($filters['start_date_sql'])) {
            $sql .= " AND DATE(transaction_date) >= :start_date";
            $params[':start_date'] = $filters['start_date_sql'];
        }
        if (!empty($filters['end_date_sql'])) {
            $sql .= " AND DATE(transaction_date) <= :end_date";
            $params[':end_date'] = $filters['end_date_sql'];
        }
        $sql .= " ORDER BY transaction_date ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * دریافت لیست معاملات بر اساس آرایه‌ای از شناسه‌ها و فیلتر بر اساس مخاطب و نوع معامله
     * @param int[] $ids
     * @param int|null $contactId
     * @param string|null $transactionType
     * @return array
     */
    public function getTransactionsByIds(array $ids, ?int $contactId = null, ?string $transactionType = null): array
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM transactions WHERE id IN ($placeholders)";
        $params = $ids;
        if ($contactId !== null) {
            $sql .= " AND counterparty_contact_id = ?";
            $params[] = $contactId;
        }
        if ($transactionType !== null) {
            $sql .= " AND transaction_type = ?";
            $params[] = $transactionType;
        }
        $sql .= " ORDER BY transaction_date ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $val) {
            $stmt->bindValue($k + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت تمام معاملات با نام طرف حساب.
     *
     * @return array لیست تمام معاملات.
     * @throws PDOException
     */
    public function getAll(): array {
        $this->logger->debug("Fetching all transactions with counterparty names.");
        try {
            $sql = "SELECT
                        t.*,
                        c.name as counterparty_name
                    FROM
                        transactions t
                    LEFT JOIN contacts c ON t.counterparty_contact_id = c.id
                    ORDER BY
                        t.transaction_date DESC"; // Order by most recent first
            $stmt = $this->db->query($sql);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->info("Fetched " . count($transactions) . " transactions.");
            return $transactions;
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching all transactions: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * دریافت یک معامله بر اساس ID به همراه تمام اقلام مرتبط با آن.
     *
     * @param int $transactionId شناسه معامله.
     * @return array|null آرایه اطلاعات معامله شامل کلید 'items' حاوی آرایه اقلام، یا null.
     * @throws PDOException.
     */
    public function findByIdWithItems(int $transactionId): ?array
    {
        $this->logger->debug("Fetching transaction with items for ID: {$transactionId}.");
        try {
            // 1. Get the transaction data with joins for complete data
            $sql = "SELECT t.* FROM transactions t WHERE t.id = :id LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $transactionId, PDO::PARAM_INT);
            $stmt->execute();
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transaction) {
                $this->logger->debug("Transaction not found, cannot fetch items.", ['id' => $transactionId]);
                return null;
            }
            
            // اطلاعات مخاطب را دریافت کنیم - فقط نام و details را دریافت می‌کنیم
            if (!empty($transaction['counterparty_contact_id'])) {
                $contactSql = "SELECT id, name, details, type FROM contacts WHERE id = :contact_id";
                $contactStmt = $this->db->prepare($contactSql);
                $contactStmt->bindValue(':contact_id', $transaction['counterparty_contact_id'], PDO::PARAM_INT);
                $contactStmt->execute();
                $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($contact) {
                    $transaction['party_name'] = $contact['name'] ?? '';
                    $transaction['party_details'] = $contact['details'] ?? '';
                    $transaction['party_type'] = $contact['type'] ?? '';
                    
                    // تلاش برای استخراج شماره تماس و کد ملی از details
                    $details = $contact['details'] ?? '';
                    
                    // الگوهای احتمالی شماره تماس در متن details
                    $phonePatterns = [
                        '/(\b\d{8,11}\b)/',                           // اعداد 8 تا 11 رقمی
                        '/(\b\d{3,4}[\s\-]\d{3,4}[\s\-]\d{4}\b)/',    // الگوی 123-456-7890
                        '/(\b\d{4}[\s\-]\d{7,8}\b)/',                 // الگوی 0123-4567890
                    ];
                    
                    // تلاش برای پیدا کردن شماره تماس
                    $phoneFound = false;
                    foreach ($phonePatterns as $pattern) {
                        if (preg_match($pattern, $details, $matches)) {
                            $transaction['party_phone'] = $matches[1];
                            $phoneFound = true;
                            break;
                        }
                    }
                    
                    if (!$phoneFound) {
                        // اگر هیچ شماره‌ای پیدا نشد، اطلاعات details را استفاده کن
                        $transaction['party_phone'] = $details;
                    }
                    
                    // بررسی برای کد ملی (معمولاً 10 رقم)
                    if (preg_match('/\b(\d{10})\b/', $details, $matches)) {
                        $transaction['party_national_code'] = $matches[1];
                    } else {
                        $transaction['party_national_code'] = '';
                    }
                }
            }
            
            // اگر وضعیت تحویل تنظیم نشده، بر اساس نوع معامله مقدار پیش‌فرض بگذاریم
            if (empty($transaction['delivery_status'])) {
                if ($transaction['transaction_type'] === 'buy') {
                    $transaction['delivery_status'] = 'pending_receipt';
                } else if ($transaction['transaction_type'] === 'sell') {
                    $transaction['delivery_status'] = 'pending_delivery';
                } else {
                    $transaction['delivery_status'] = 'completed';
                }
            }
            
            // 2. Get associated items with complete data
            $items = $this->transactionItemRepository->findByTransactionId($transactionId);

            // 3. Attach items to the transaction array
            $transaction['items'] = $items; // $items will be an array (possibly empty)

            $this->logger->debug("Transaction and items fetched successfully.", ['id' => $transactionId, 'item_count' => count($transaction['items'])]);
            return $transaction;

        } catch (PDOException $e) {
            $this->logger->error("Database error fetching transaction with items for ID {$transactionId}: " . $e->getMessage(), ['exception' => $e]);
            throw $e; // Re-throw the exception
        } catch (Throwable $e) { // Catch any other potential errors from item repository
            $this->logger->error("General error fetching transaction with items for ID {$transactionId}: " . $e->getMessage(), ['exception' => $e]);
            throw $e; // Re-throw
        }
    }

    // سایر متدهای مربوط به لیست، فیلتر، گزارشات معاملات
    // public function getAll(): array { /* ... */ }
    // public function getFilteredTransactions(array $filters): array { /* ... */ }
    // ...
}