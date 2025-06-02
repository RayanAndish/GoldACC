<?php

namespace App\Repositories; // Namespace مطابق با پوشه src/Repositories

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;

/**
 * کلاس ContactRepository برای تعامل با جدول پایگاه داده contacts.
 */
class ContactRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * دریافت تمام مخاطبین.
     *
     * @return array آرایه‌ای از مخاطبین.
     * @throws PDOException.
     */
    public function getAll(): array {
        $this->logger->debug("Fetching all contacts.");
        try {
            $sql = "SELECT id, name, type, details, credit_limit, created_at FROM contacts ORDER BY name ASC";
            $stmt = $this->db->query($sql);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->info("Fetched " . count($contacts) . " contacts.");
            return $contacts;
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching all contacts: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

     /**
      * دریافت یک مخاطب بر اساس ID.
      *
      * @param int $contactId شناسه مخاطب.
      * @return array|null آرایه اطلاعات مخاطب یا null.
      * @throws PDOException.
      */
     public function getById(int $contactId): ?array {
         $this->logger->debug("Fetching contact with ID: {$contactId}.");
         try {
             $sql = "SELECT id, name, type, details, credit_limit, created_at FROM contacts WHERE id = :id LIMIT 1";
             $stmt = $this->db->prepare($sql);
             $stmt->bindValue(':id', $contactId, PDO::PARAM_INT);
             $stmt->execute();
             $contact = $stmt->fetch(PDO::FETCH_ASSOC);
             if ($contact) $this->logger->debug("Contact found.", ['id' => $contactId]);
             else $this->logger->debug("Contact not found.", ['id' => $contactId]);
             return $contact ?: null;
         } catch (PDOException $e) {
             $this->logger->error("Database error fetching contact by ID {$contactId}: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
         }
     }

    /**
     * ذخیره (افزودن یا ویرایش) یک مخاطب.
     * منطق از src/action/contact_save.php گرفته شده.
     *
     * @param array $contactData آرایه داده‌ها (باید شامل name, type و اختیاری id, details, credit_limit باشد).
     * @return int شناسه مخاطب ذخیره شده.
     * @throws PDOException.
     * @throws Exception در صورت داده نامعتبر یا خطای Unique Constraint.
     */
    public function save(array $contactData): int {
        $contactId = $contactData['id'] ?? null;
        $isEditing = $contactId !== null;
        $this->logger->info(($isEditing ? "Updating" : "Creating") . " contact.", ['id' => $contactId, 'name' => $contactData['name'] ?? 'N/A']);

        // *** به‌روز شده: انواع معتبر مطابق با enum `contacts`.`type` ***
        $valid_types = ['debtor','creditor_account','counterparty','mixed','other'];

        if (empty($contactData['name']) || empty($contactData['type']) || !in_array($contactData['type'], $valid_types)) {
            $this->logger->error("Attempted to save contact with missing or invalid data.", ['data' => $contactData]);
            throw new Exception("نام و نوع مخاطب الزامی است.");
        }

        try {
            if ($isEditing) {
                $sql = "UPDATE contacts SET name=:name, type=:type, details=:details, credit_limit=:limit, updated_at=CURRENT_TIMESTAMP WHERE id=:id";
                $stmt = $this->db->prepare($sql); $stmt->bindValue(':id', $contactId, PDO::PARAM_INT);
            } else {
                $sql = "INSERT INTO contacts (name, type, details, credit_limit, created_at, updated_at) VALUES (:name, :type, :details, :limit, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                $stmt = $this->db->prepare($sql);
            }
            $stmt->bindValue(':name', $contactData['name'], PDO::PARAM_STR);
            $stmt->bindValue(':type', $contactData['type'], PDO::PARAM_STR);
            $stmt->bindValue(':details', !empty($contactData['details']) ? $contactData['details'] : null, PDO::PARAM_STR);
            // credit_limit می تواند null باشد
            $stmt->bindValue(':limit', isset($contactData['credit_limit']) && $contactData['credit_limit'] !== '' ? $contactData['credit_limit'] : null, PDO::PARAM_STR); // Bind as string for decimal/float nullable

            $stmt->execute();

            if (!$isEditing) {
                $contactId = (int)$this->db->lastInsertId();
                $this->logger->info("Contact created successfully with ID: {$contactId}.", ['name' => $contactData['name']]);
            } else {
                 if ($stmt->rowCount() === 0) {
                      $this->logger->warning("Contact update attempted for ID {$contactId} but no row was affected.");
                 } else {
                      $this->logger->info("Contact updated successfully.", ['id' => $contactId, 'name' => $contactData['name']]);
                 }
            }

            return (int)$contactId;

        } catch (PDOException $e) {
             $this->logger->error("Database error saving contact: " . $e->getMessage(), ['exception' => $e, 'id' => $contactId, 'name' => $contactData['name'] ?? 'N/A']);
            if ($e->getCode() === '23000') { // Unique constraint violation (assuming name is unique)
                throw new Exception("مخاطب با نام '{$contactData['name']}' از قبل موجود است.", 0, $e);
            }
            throw $e;
        } catch (Throwable $e) {
             $this->logger->error("Error saving contact: " . $e->getMessage(), ['exception' => $e, 'id' => $contactId, 'name' => $contactData['name'] ?? 'N/A']);
             throw $e;
        }
    }

    /**
     * بررسی اینکه آیا یک مخاطب در جدول معاملات یا پرداخت ها استفاده شده است.
     * منطق از src/action/contact_delete.php گرفته شده.
     *
     * @param int $contactId شناسه مخاطب.
     * @return bool True اگر استفاده شده، False در غیر این صورت.
     * @throws PDOException.
     */
    public function isUsedInTransactionsOrPayments(int $contactId): bool {
        $this->logger->debug("Checking usage for contact ID {$contactId} in transactions or payments.");
        try {
            // بررسی در جدول معاملات
            $sql_check_trans = "SELECT 1 FROM transactions WHERE counterparty_contact_id = :id LIMIT 1";
            $stmt_check_trans = $this->db->prepare($sql_check_trans);
            $stmt_check_trans->bindValue(':id', $contactId, PDO::PARAM_INT);
            $stmt_check_trans->execute();
            $used_in_transactions = $stmt_check_trans->fetchColumn();

            // بررسی در جدول پرداخت ها
            $sql_check_pay = "SELECT 1 FROM payments WHERE paying_contact_id = :pid OR receiving_contact_id = :rid LIMIT 1";
            $stmt_check_pay = $this->db->prepare($sql_check_pay);
            $stmt_check_pay->bindValue(':pid', $contactId, PDO::PARAM_INT);
            $stmt_check_pay->bindValue(':rid', $contactId, PDO::PARAM_INT);
            $stmt_check_pay->execute();
            $used_in_payments = $stmt_check_pay->fetchColumn();

            $isUsed = (bool)($used_in_transactions || $used_in_payments);
            $this->logger->debug("Contact ID {$contactId} is used: " . ($isUsed ? 'Yes' : 'No'));
            return $isUsed;

        } catch (PDOException $e) {
            $this->logger->error("Database error checking contact usage for ID {$contactId}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

     /**
      * حذف یک مخاطب بر اساس ID.
      * منطق از src/action/contact_delete.php گرفته شده.
      *
      * @param int $contactId شناسه مخاطب برای حذف.
      * @return bool True اگر حذف شد، False اگر یافت نشد.
      * @throws PDOException در صورت خطای دیتابیس (شامل Foreign Key اگر چک نشده).
      * @throws Exception در صورت خطای دیگر.
      */
     public function delete(int $contactId): bool {
         $this->logger->info("Attempting to delete contact with ID: {$contactId}.");
         // ** نکته: چک کردن وابستگی با isUsedInTransactionsOrPayments() باید قبل از فراخوانی این متد در Service یا Controller انجام شود. **

         try {
             $sql = "DELETE FROM contacts WHERE id = :id";
             $stmt = $this->db->prepare($sql);
             $stmt->bindValue(':id', $contactId, PDO::PARAM_INT);
             $stmt->execute();

             $deletedCount = $stmt->rowCount();
             if ($deletedCount > 0) {
                 $this->logger->info("Contact deleted successfully.", ['id' => $contactId]);
                 return true;
             } else {
                 $this->logger->warning("Contact delete attempted for ID {$contactId} but no row was affected (Not found?).");
                 return false;
             }
         } catch (PDOException $e) {
             $this->logger->error("Database error deleting contact with ID {$contactId}: " . $e->getMessage(), ['exception' => $e]);
              // اگر Foreign Key Constraint تعریف شده باشد و چک کردن فراموش شده باشد، اینجا خطا می‌دهد.
             if ($e->getCode() === '23000') { // Foreign key violation
                  throw new Exception("امکان حذف مخاطب وجود ندارد: در معاملات یا پرداخت‌های مرتبط استفاده شده است.", 0, $e);
             }
             throw $e;
         } catch (Throwable $e) {
             $this->logger->error("Error deleting contact with ID {$contactId}: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
         }
     }

     /**
      * محاسبه مانده حساب ریالی برای یک مخاطب.
      * منطق از calculate_contact_balance در functions.php گرفته شده و بهینه شده.
      * **توجه:** این متد شامل چند کوئری است. اگر نیاز به مانده حساب در لیست دارید، کوئری را JOIN کنید.
      *
      * @param int $contactId شناسه مخاطب.
      * @return float مانده (مثبت:او بدهکار / منفی:او بستانکار - از دید ما).
      * @throws PDOException.
      */
     public function calculateBalance(int $contactId): float {
         $this->logger->debug("Calculating balance for contact ID: {$contactId}.");
         $balance = 0.0;
         try {
             // منطق از calculate_contact_balance
             // + Sales to contact (He owes us)
             $sql_sell = "SELECT SUM(total_value_rials) FROM transactions WHERE counterparty_contact_id = :id AND transaction_type = 'sell'";
             $stmt_sell = $this->db->prepare($sql_sell); $stmt_sell->bindValue(':id', $contactId, PDO::PARAM_INT); $stmt_sell->execute();
             $balance += (float)($stmt_sell->fetchColumn() ?: 0.0);

             // - Buys from contact (We owe him)
             $sql_buy = "SELECT SUM(total_value_rials) FROM transactions WHERE counterparty_contact_id = :id AND transaction_type = 'buy'";
             $stmt_buy = $this->db->prepare($sql_buy); $stmt_buy->bindValue(':id', $contactId, PDO::PARAM_INT); $stmt_buy->execute();
             $balance -= (float)($stmt_buy->fetchColumn() ?: 0.0);

             // + Payments We made to contact (He owes less or becomes our debtor)
             $sql_paid_to = "SELECT SUM(amount_rials) FROM payments WHERE receiving_contact_id = :id";
             $stmt_paid_to = $this->db->prepare($sql_paid_to); $stmt_paid_to->bindValue(':id', $contactId, PDO::PARAM_INT); $stmt_paid_to->execute();
             $balance += (float)($stmt_paid_to->fetchColumn() ?: 0.0);

             // - Payments He made to us (We owe less or he becomes our creditor)
             $sql_paid_by = "SELECT SUM(amount_rials) FROM payments WHERE paying_contact_id = :id";
             $stmt_paid_by = $this->db->prepare($sql_paid_by); $stmt_paid_by->bindValue(':id', $contactId, PDO::PARAM_INT); $stmt_paid_by->execute();
             $balance -= (float)($stmt_paid_by->fetchColumn() ?: 0.0);

         } catch (PDOException $e) {
              $this->logger->error("Database error calculating balance for contact ID {$contactId}: " . $e->getMessage(), ['exception' => $e]);
              throw $e; // Throw exception on DB error
         } catch (Throwable $e) {
              $this->logger->error("Error calculating balance for contact ID {$contactId}: " . $e->getMessage(), ['exception' => $e]);
              throw $e;
         }
         return round($balance, 2);
     }

    /**
     * Retrieves lists of debtors and creditors based on calculated balances.
     *
     * @param float $threshold The minimum absolute balance to consider.
     * @return array ['debtors' => array, 'creditors' => array]
     */
    public function getDebtorsAndCreditors(float $threshold = 0.01): array
    {
        $this->logger->debug("Fetching debtors and creditors list.", ['threshold' => $threshold]);
        $debtors = [];
        $creditors = [];

        try {
            // Query adapted from dash.php (calculates balance in SQL)
            $sql_bal = "SELECT c.id, c.name, 
                           SUM(COALESCE(m.amount, 0)) AS balance 
                    FROM contacts c 
                    LEFT JOIN (
                        SELECT counterparty_contact_id AS cid, total_value_rials AS amount 
                        FROM transactions 
                        WHERE transaction_type = 'sell' AND counterparty_contact_id IS NOT NULL 
                        UNION ALL 
                        SELECT counterparty_contact_id AS cid, -total_value_rials 
                        FROM transactions 
                        WHERE transaction_type = 'buy' AND counterparty_contact_id IS NOT NULL 
                        UNION ALL 
                        SELECT paying_contact_id AS cid, -amount_rials 
                        FROM payments 
                        WHERE paying_contact_id IS NOT NULL 
                        UNION ALL 
                        SELECT receiving_contact_id AS cid, amount_rials 
                        FROM payments 
                        WHERE receiving_contact_id IS NOT NULL
                    ) AS m ON c.id = m.cid 
                    GROUP BY c.id, c.name 
                    HAVING ABS(SUM(COALESCE(m.amount, 0))) > :thr 
                    ORDER BY c.name ASC";
            $stmt_bal = $this->db->prepare($sql_bal);
            $stmt_bal->bindValue(':thr', $threshold, PDO::PARAM_STR); // Bind threshold as string for precision
            $stmt_bal->execute();
            $contact_bals = $stmt_bal->fetchAll(PDO::FETCH_ASSOC);

            // Separate into debtors and creditors
            foreach ($contact_bals as $cb) {
                $bal = (float)$cb['balance'];
                $contactInfo = ['id' => $cb['id'], 'name' => $cb['name'], 'balance' => $bal];
                if ($bal > $threshold) {
                    $debtors[] = $contactInfo;
                } elseif ($bal < -$threshold) {
                    $creditors[] = $contactInfo;
                }
            }

            // Sort by balance amount (desc for debtors, asc for creditors)
            usort($debtors, fn($a, $b) => $b['balance'] <=> $a['balance']);
            usort($creditors, fn($a, $b) => $a['balance'] <=> $b['balance']);

            $this->logger->debug("Debtor/Creditor list fetched.", ['debtors' => count($debtors), 'creditors' => count($creditors)]);

        } catch (Throwable $e) {
            $this->logger->error("Database error fetching debtors/creditors list.", ['exception' => $e]);
            // Return empty lists on error
        }

        return ['debtors' => $debtors, 'creditors' => $creditors];
    }

    /**
     * جستجو و صفحه‌بندی مخاطبین با امکان جستجو در نام، نوع و جزئیات.
     * @param string $searchTerm عبارت جستجو
     * @param int $limit تعداد رکورد در هر صفحه
     * @param int $offset شروع رکورد
     * @return array ['contacts'=>[], 'total'=>int]
     */
    public function searchAndPaginate(string $searchTerm, int $limit, int $offset): array {
        $params = [];
        $where = '';
        if ($searchTerm !== '') {
            $where = "WHERE name LIKE :q1 OR type LIKE :q2 OR details LIKE :q3";
            $params[':q1'] = '%' . $searchTerm . '%';
            $params[':q2'] = '%' . $searchTerm . '%';
            $params[':q3'] = '%' . $searchTerm . '%';
        }
        // شمارش کل رکوردها
        $sqlCount = "SELECT COUNT(*) FROM contacts $where";
        $stmtCount = $this->db->prepare($sqlCount);
        foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
        $stmtCount->execute();
        $total = (int)$stmtCount->fetchColumn();
        // دریافت رکوردهای صفحه جاری
        $sql = "SELECT id, name, type, details, credit_limit, created_at FROM contacts $where ORDER BY name ASC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $contacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return ['contacts'=>$contacts, 'total'=>$total];
    }

    // متدهای دیگر برای کار با Ledger, Transactions, Payments مربوط به مخاطب
    // public function getLedgerEntries(int $contactId): array { /* ... */ }
    // public function getTransactionsByContactId(int $contactId): array { /* ... */ }
    // public function getPaymentsByContactId(int $contactId): array { /* ... */ }
    // ...
}