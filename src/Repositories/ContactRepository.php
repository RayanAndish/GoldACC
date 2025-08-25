<?php

namespace App\Repositories; // Namespace مطابق با پوشه src/Repositories

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;
use App\Utils\Helper;

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
    public function calculateBalance(int $contactId): float
    {
        $this->logger->debug("Calculating final RIAl balance for contact ID: {$contactId}.");
        $balance = 0.0;
        try {
            $params = [':id' => $contactId];

            // فروش‌های تکمیل شده (او به ما بدهکار می‌شود -> مثبت)
            $stmt_sell = $this->db->prepare("SELECT SUM(final_payable_amount_rials) FROM transactions WHERE counterparty_contact_id = :id AND transaction_type = 'sell' AND delivery_status = 'completed'");
            $stmt_sell->execute($params);
            $balance += (float)$stmt_sell->fetchColumn();

            // خریدهای تکمیل شده (ما به او بدهکار می‌شویم -> منفی)
            $stmt_buy = $this->db->prepare("SELECT SUM(final_payable_amount_rials) FROM transactions WHERE counterparty_contact_id = :id AND transaction_type = 'buy' AND delivery_status = 'completed'");
            $stmt_buy->execute($params);
            $balance -= (float)$stmt_buy->fetchColumn();

            // پرداخت‌های او به ما (بدهی او کم می‌شود -> منفی)
            $stmt_paid_by = $this->db->prepare("SELECT SUM(amount_rials) FROM payments WHERE paying_contact_id = :id");
            $stmt_paid_by->execute($params);
            $balance -= (float)$stmt_paid_by->fetchColumn();

            // پرداخت‌های ما به او (بدهی ما کم می‌شود -> مثبت)
            $stmt_paid_to = $this->db->prepare("SELECT SUM(amount_rials) FROM payments WHERE receiving_contact_id = :id");
            $stmt_paid_to->execute($params);
            $balance += (float)$stmt_paid_to->fetchColumn();

        } catch (Throwable $e) {
              $this->logger->error("Error calculating balance for contact ID {$contactId}", ['exception' => $e]);
              throw $e;
        }
        return round($balance, 2);
    }


 /**
     * (نسخه نهایی با رفع خطای Undefined variable $params_pays و منطق کامل)
     */
    public function getUnifiedLedgerEntries(int $contactId, ?string $startDate, ?string $endDate): array
    {
        $this->logger->debug("Fetching unified ledger entries with FINAL logic.", [
            'contact_id' => $contactId, 'start_date' => $startDate, 'end_date' => $endDate
        ]);
        $allEntries = [];
        
        // تعریف متغیرهای فیلتر تاریخ
        $dateFilterTrans = '';
        $dateFilterPays = '';
        $dateFilterSettle = '';
        
        // تعریف پارامترهای نام‌گذاری شده برای کوئری‌هایی که از آن استفاده می‌کنند
        $baseParams = [':contact_id' => $contactId];
        if ($startDate) {
            $dateFilterTrans = " AND t.transaction_date >= :start_date";
            $dateFilterSettle = " AND ps.settlement_date >= :start_date";
            $dateFilterPays = " AND p.payment_date >= ?"; // استفاده از placeholder موقعیتی
            $baseParams[':start_date'] = $startDate;
        }
        if ($endDate) {
            $dateFilterTrans .= " AND t.transaction_date <= :end_date";
            $dateFilterSettle .= " AND ps.settlement_date <= :end_date";
            $dateFilterPays .= " AND p.payment_date <= ?"; // استفاده از placeholder موقعیتی
            $baseParams[':end_date'] = $endDate;
        }

        
          try {
            // **اصلاح نهایی: تکمیل سینتکس ناقص SQL**
            $sql_trans_items = "
                SELECT 
                    ti.id AS entry_id, 'transaction_item' AS entry_type, t.transaction_date AS entry_date,
                    t.transaction_type, p.name AS product_name, t.id AS transaction_id,
                    ti.weight_grams, ti.carat, ti.quantity, ti.coin_year, ti.tag_number,
                    pc.base_category,

                    (CASE 
                        WHEN t.transaction_type = 'sell' THEN 
                            (IFNULL(ti.total_value_rials, 0) + IFNULL(ti.ajrat_rials, 0) + IFNULL(ti.profit_amount_rials, 0) + IFNULL(ti.fee_amount_rials, 0) + IFNULL(ti.general_tax_rials, 0) + IFNULL(ti.vat_rials, 0))
                        ELSE 0 
                    END) as debit_rial,
                    (CASE 
                        WHEN t.transaction_type = 'buy' THEN 
                            (IFNULL(ti.total_value_rials, 0) + IFNULL(ti.ajrat_rials, 0) + IFNULL(ti.profit_amount_rials, 0) + IFNULL(ti.fee_amount_rials, 0) + IFNULL(ti.general_tax_rials, 0) + IFNULL(ti.vat_rials, 0))
                        ELSE 0 
                    END) as credit_rial
                FROM transaction_items ti
                JOIN transactions t ON ti.transaction_id = t.id
                JOIN products p ON ti.product_id = p.id
                JOIN product_categories pc ON p.category_id = pc.id
                WHERE t.counterparty_contact_id = :contact_id 
                  AND t.delivery_status = 'completed'
                  {$dateFilterTrans}
                ";
            $stmt_trans = $this->db->prepare($sql_trans_items);
            $stmt_trans->execute($baseParams);
            $trans_items = $stmt_trans->fetchAll(PDO::FETCH_ASSOC);

            // منطق PHP برای پردازش نتایج (این بخش کاملا صحیح است و بدون تغییر باقی می‌ماند)
            foreach($trans_items as $item){
                 // ... (منطق switch بر اساس base_category) ...
                 switch ($item['base_category']) {
                    case 'COIN':
                         $item['is_countable_display'] = true;
                         $value = (float)($item['quantity'] ?? 0);
                         if ($item['transaction_type'] == 'buy') $item['display_debit'] = $value;
                         else $item['display_credit'] = $value;
                         break;
                    case 'JEWELRY': // جواهر نیز اکنون به عنوان تعدادی در نظر گرفته می‌شود
                         $item['is_countable_display'] = true;
                         $value = (float)($item['quantity'] ?? 0);
                         if ($item['transaction_type'] == 'buy') {
                            $item['display_debit'] = $value;
                            $item['debit_count_for_balance'] = $value;
                         } else {
                            $item['display_credit'] = $value;
                            $item['credit_count_for_balance'] = $value;
                         }
                         break;
                     
                     default: // برای هر کالای وزنی دیگر
                         $item['is_countable_display'] = false;
                         $weight750 = Helper::convertGoldToCarat((float)($item['weight_grams'] ?? 0), (int)($item['carat'] ?? 0));
                         
                         if ($item['transaction_type'] == 'buy') {
                             $item['display_debit'] = $weight750;
                             $item['debit_weight_for_balance'] = $weight750;
                         } else { // sell
                             $item['display_credit'] = $weight750;
                             $item['credit_weight_for_balance'] = $weight750;
                         }
                         break;
                 }
                 $allEntries[] = $item;
            }

            // 2. واکشی تمام پرداخت‌ها (با تعریف صحیح پارامترها)
            $sql_pays = "
                SELECT 
                    p.id AS entry_id, 'payment' AS entry_type, p.payment_date AS entry_date, 
                    p.notes, p.payment_method,
                    (CASE WHEN p.receiving_contact_id = ? THEN p.amount_rials ELSE 0 END) as debit_rial,
                    (CASE WHEN p.paying_contact_id = ? THEN p.amount_rials ELSE 0 END) as credit_rial,
                    0 as debit_weight_750, 0 as credit_weight_750,
                    p.related_transaction_id
                FROM payments p
                WHERE (p.paying_contact_id = ? OR p.receiving_contact_id = ?)
                {$dateFilterPays}
            ";
            
            $params_pays = [$contactId, $contactId, $contactId, $contactId];
            if ($startDate) {
                $params_pays[] = $startDate;
            }
            if ($endDate) {
                $params_pays[] = $endDate;
            }
            $stmt_pays = $this->db->prepare($sql_pays);
            $stmt_pays->execute($params_pays);
            $payment_items = $stmt_pays->fetchAll(PDO::FETCH_ASSOC);
            foreach($payment_items as $item) {
                $item['is_countable_display'] = false;
                $item['display_debit'] = 0; $item['display_credit'] = 0;
                $item['debit_weight_for_balance'] = 0; $item['credit_weight_for_balance'] = 0;
                $item['debit_count_for_balance'] = 0; $item['credit_count_for_balance'] = 0;
                $allEntries[] = $item;
            }

            // 3. واکشی تسویه‌های فیزیکی (این بخش صحیح بود)
            $sql_settlements = "
                SELECT
                    psi.id as entry_id, 'physical_settlement' as entry_type, ps.settlement_date as entry_date,
                    ps.direction, ps.notes, p.name as product_name, psi.weight_scale, psi.carat, psi.weight_750,
                    0 as debit_rial, 0 as credit_rial,
                    (CASE WHEN ps.direction = 'outflow' THEN psi.weight_750 ELSE 0 END) as credit_weight_750,
                    (CASE WHEN ps.direction = 'inflow' THEN psi.weight_750 ELSE 0 END) as debit_weight_750
                FROM physical_settlement_items psi
                JOIN physical_settlements ps ON psi.settlement_id = ps.id
                JOIN products p ON psi.product_id = p.id
                WHERE ps.contact_id = :contact_id
                {$dateFilterSettle}
            ";
            $stmt_settle = $this->db->prepare($sql_settlements);
            $stmt_settle->execute($baseParams);
            $settlement_items = $stmt_settle->fetchAll(PDO::FETCH_ASSOC);
            foreach($settlement_items as $item) {
                $item['is_countable_display'] = false;
                $item['display_debit'] = (float)($item['debit_weight_750'] ?? 0);
                $item['display_credit'] = (float)($item['credit_weight_750'] ?? 0);
                $item['debit_weight_for_balance'] = (float)($item['debit_weight_750'] ?? 0);
                $item['credit_weight_for_balance'] = (float)($item['credit_weight_750'] ?? 0);
                $item['debit_count_for_balance'] = 0;
                $item['credit_count_for_balance'] = 0;
                $allEntries[] = $item;
            }

            usort($allEntries, fn($a, $b) => strtotime($a['entry_date']) <=> strtotime($b['entry_date']));
            return $allEntries;
        } catch (Throwable $e) {
            $this->logger->error("Error fetching unified ledger entries.", ['contact_id' => $contactId, 'exception' => $e]);
            throw $e;
        }
    }
    
    /**
     * (نهایی) مانده حساب یک مخاطب را تا قبل از یک تاریخ مشخص محاسبه می‌کند.
     */
    public function calculateBalanceBeforeDate(int $contactId, ?string $startDate): float
    {
        if (empty($startDate)) {
            return 0.0;
        }

        $balance = 0.0;
        $params = [':id' => $contactId, ':date' => $startDate];
        try {
            // فروش‌های تکمیل شده قبل از تاریخ شروع
            $stmt = $this->db->prepare("SELECT SUM(final_payable_amount_rials) FROM transactions WHERE counterparty_contact_id = :id AND transaction_type = 'sell' AND delivery_status = 'completed' AND transaction_date < :date");
            $stmt->execute($params);
            $balance += (float)$stmt->fetchColumn();

            // خریدهای تکمیل شده قبل از تاریخ شروع
            $stmt = $this->db->prepare("SELECT SUM(final_payable_amount_rials) FROM transactions WHERE counterparty_contact_id = :id AND transaction_type = 'buy' AND delivery_status = 'completed' AND transaction_date < :date");
            $stmt->execute($params);
            $balance -= (float)$stmt->fetchColumn();

            // پرداخت‌های او به ما قبل از تاریخ شروع
            $stmt = $this->db->prepare("SELECT SUM(amount_rials) FROM payments WHERE paying_contact_id = :id AND payment_date < :date");
            $stmt->execute($params);
            $balance -= (float)$stmt->fetchColumn();

            // پرداخت‌های ما به او قبل از تاریخ شروع
            $stmt = $this->db->prepare("SELECT SUM(amount_rials) FROM payments WHERE receiving_contact_id = :id AND payment_date < :date");
            $stmt->execute($params);
            $balance += (float)$stmt->fetchColumn();

        } catch (Throwable $e) {
              $this->logger->error("Error calculating balance before date for contact ID {$contactId}", ['exception' => $e]);
              throw $e;
        }
        return round($balance, 2);
    }

    /**
     * (بازنویسی کامل) لیست بدهکاران و بستانکاران را واکشی می‌کند.
     */
    public function getDebtorsAndCreditors(float $threshold = 0.01): array
    {
        $debtors = [];
        $creditors = [];
        try {
            $allContacts = $this->getAll();
            foreach ($allContacts as $contact) {
                $balance = $this->calculateBalance((int)$contact['id']);
                if (abs($balance) > $threshold) {
                    $contactInfo = ['id' => $contact['id'], 'name' => $contact['name'], 'balance' => $balance];
                    if ($balance > 0) $debtors[] = $contactInfo;
                    else $creditors[] = $contactInfo;
                }
            }
            usort($debtors, fn($a, $b) => $b['balance'] <=> $a['balance']);
            usort($creditors, fn($a, $b) => abs($b['balance']) <=> abs($a['balance']));
        } catch (Throwable $e) {
            $this->logger->error("Error fetching debtors/creditors list.", ['exception' => $e]);
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
/**
     * (اصلاح شده) تعداد کل مخاطبین را بر اساس عبارت جستجو می‌شمارد.
     */
    public function countFiltered(string $searchTerm = ''): int
    {
        $sql = "SELECT COUNT(id) FROM contacts";
        $params = [];
        if (!empty($searchTerm)) {
            $sql .= " WHERE name LIKE :search OR details LIKE :search";
            $params[':search'] = '%' . $searchTerm . '%';
        }
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $this->logger->error("Database error counting filtered contacts.", ['exception' => $e]);
            return 0;
        }
    }

    /**
     * (اصلاح شده) لیست مخاطبین را به صورت صفحه‌بندی شده و با جستجو برمی‌گرداند.
     */
    public function searchAndPaginate(string $searchTerm, int $limit, int $offset): array
    {
        $sql = "SELECT * FROM contacts";
        $params = [];
        if (!empty($searchTerm)) {
            $sql .= " WHERE name LIKE :search OR details LIKE :search";
            $params[':search'] = '%' . $searchTerm . '%';
        }
        $sql .= " ORDER BY name ASC LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => &$value) {
                $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching paginated contacts.", ['exception' => $e]);
            return [];
        }
    }
}