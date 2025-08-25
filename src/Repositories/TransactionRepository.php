<?php
// src/Repositories/TransactionRepository.php
namespace App\Repositories;

use App\Models\Transaction;
use App\Utils\Helper;
use PDO;
use Monolog\Logger;
use Throwable;
use RuntimeException;

/**
 * TransactionRepository: Handles database interactions for the 'transactions' table.
 * REVISED: Added Product Name to getLatestTransactions query for dashboard display.
 */
class TransactionRepository
{
    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

     /**
     * (جدید) یک رکورد معامله را در دیتابیس ذخیره (ایجاد یا به‌روزرسانی) می‌کند.
     * @param Transaction $transaction آبجکت معامله برای ذخیره
     * @return int شناسه معامله ذخیره شده
     * @throws Throwable
     */
    public function save(Transaction $transaction): int
    {
        $isEditMode = $transaction->id !== null && $transaction->id > 0;
        $this->logger->info("REPOSITORY: Saving transaction.", ['object_id' => $transaction->id, 'is_edit' => $isEditMode]);

        // لیست تمام فیلدهای جدول transactions که باید ذخیره شوند
        $fields = [
            'transaction_type', 'transaction_date', 'counterparty_contact_id', 'mazaneh_price',
            'delivery_status', 'delivery_date', 'delivery_person', 'notes',
            'total_items_value_rials', 'total_profit_wage_commission_rials',
            'total_general_tax_rials', 'total_before_vat_rials', 'total_vat_rials',
            'final_payable_amount_rials', 'created_by_user_id', 'updated_by_user_id'
        ];

        if ($isEditMode) {
            $setClauses = [];
            foreach ($fields as $field) {
                $setClauses[] = "`$field` = :$field";
            }
            $sql = "UPDATE `transactions` SET " . implode(', ', $setClauses) . " WHERE id = :id";
        } else {
            $columns = implode('`, `', $fields);
            $placeholders = ':' . implode(', :', $fields);
            $sql = "INSERT INTO `transactions` (`$columns`) VALUES ($placeholders)";
        }

        try {
            $stmt = $this->db->prepare($sql);

            foreach ($fields as $field) {
                $stmt->bindValue(":$field", $transaction->{$field});
            }

            if ($isEditMode) {
                $stmt->bindValue(':id', $transaction->id, PDO::PARAM_INT);
            }

            $stmt->execute();

            if (!$isEditMode) {
                return (int)$this->db->lastInsertId();
            }
            return $transaction->id;

        } catch (Throwable $e) {
            $this->logger->error("Database error saving transaction.", ['id' => $transaction->id, 'exception' => $e]);
            throw $e;
        }
    }

     /**
     * (جدید) یک معامله را فقط با شناسه آن واکشی می‌کند (بدون جزئیات آیتم‌ها).
     */
    public function findById(int $id): ?array
    {
        $this->logger->debug("Fetching transaction by ID.", ['transaction_id' => $id]);
        $sql = "SELECT * FROM transactions WHERE id = :id LIMIT 1";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $this->logger->error("Error fetching transaction by ID.", ['id' => $id, 'exception' => $e]);
            return null;
        }
    }

    /**
     * (اصلاح شده) واکشی خلاصه‌ای از آیتم‌های معلق برای داشبورد.
     * این متد داده‌ها را بر اساس دسته‌بندی محصول جمع‌بندی می‌کند.
     * @param string $status 'pending_receipt' or 'pending_delivery'
     * @return array
     */
 public function getPendingSummary(string $status): array
    {
        $this->logger->info("Fetching PENDING SUMMARY by PRODUCT for status: " . $status);
        $sql = "
            SELECT 
                IFNULL(p.name, 'کالای نامشخص') as item_name, -- انتخاب نام محصول
                SUM(IFNULL((ti.weight_grams * ti.carat / 750), 0)) as total_weight_750,
                SUM(IFNULL(ti.quantity, 0)) as total_quantity
            FROM transaction_items ti
            INNER JOIN transactions t ON ti.transaction_id = t.id
            LEFT JOIN products p ON ti.product_id = p.id
            WHERE t.delivery_status = :status
            GROUP BY p.name -- گروه‌بندی بر اساس نام محصول
            ORDER BY p.name ASC
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Error fetching pending summary by product.", ['status' => $status, 'exception' => $e]);
            return [];
        }
    }
  
    /**
     * (جدید) واکشی لیست جزئیات آیتم‌های معلق برای صفحه موجودی.
     * این متد لیست کامل آیتم‌ها را بدون جمع‌بندی برمی‌گرداند.
     * @param string $status 'pending_receipt' or 'pending_delivery'
     * @return array
     */
    public function getPendingItemsDetails(string $status): array
    {
        $this->logger->info("Fetching PENDING ITEMS DETAILS for status: " . $status);
        $sql = "
            SELECT 
                ti.quantity, 
                ti.weight_grams, 
                ti.carat, 
                ti.coin_year,
                t.id as transaction_id, 
                t.transaction_date,
                p.name as product_name,
                c.name as counterparty_name,
                p.unit_of_measure
            FROM transaction_items ti
            JOIN transactions t ON ti.transaction_id = t.id
            LEFT JOIN products p ON ti.product_id = p.id
            LEFT JOIN contacts c ON t.counterparty_contact_id = c.id
            WHERE t.delivery_status = :status
            ORDER BY t.transaction_date DESC, t.id DESC
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Error fetching pending items details.", ['status' => $status, 'exception' => $e]);
            return [];
        }
    }
       
   /**
     * Fetches the latest transactions (e.g. for dashboard display),
     * including related product name (from one item for simplicity) and counterparty name.
     * REVISED: Includes `product_name` and formats fields for display.
     * @param string|null $type 'buy', 'sell', or null for all types.
     * @param int $limit The maximum number of transactions to return.
     * @return array Array of latest transaction records with product/contact details.
     * @throws Throwable
     */
    public function getLatestTransactions(?string $type = null, int $limit = 5): array
    {
        $this->logger->info("Fetching latest transactions (for Dashboard).", ['type' => $type, 'limit' => $limit]);
    
        $sql = "
            SELECT 
                t.id,
                t.transaction_type,
                t.transaction_date,
                t.final_payable_amount_rials,
                t.total_profit_wage_commission_rials,
                c.name as counterparty_name,
                -- Get a product name from one item for display
                MIN(p.name) as product_name
            FROM transactions t 
            LEFT JOIN contacts c ON t.counterparty_contact_id = c.id
            LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
            LEFT JOIN products p ON ti.product_id = p.id
        ";
    
        $params = [];
        $whereClauses = [];
        if ($type !== null) {
            $whereClauses[] = "t.transaction_type = :type";
            $params[':type'] = $type;
        }

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        // Grouping is essential because of the MIN(p.name) aggregation.
        // We need to group by all non-aggregated columns.
        $sql .= " GROUP BY t.id, t.transaction_type, t.transaction_date, t.final_payable_amount_rials, t.total_profit_wage_commission_rials, c.name";

        $sql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT :limit";
    
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Post-process for display (move display formatting to Helper if common)
            foreach ($results as &$tx) {
                 $tx['transaction_type_farsi'] = ($tx['transaction_type'] === 'buy' ? 'خرید' : 'فروش');
                 $tx['amount_formatted'] = Helper::formatRial($tx['final_payable_amount_rials'] ?? 0);
                 $tx['date_persian'] = Helper::formatPersianDate($tx['transaction_date']);
                 $tx['product_name_display'] = Helper::escapeHtml($tx['product_name'] ?? 'محصول نامشخص');
                 $tx['contact_name_display'] = Helper::escapeHtml($tx['counterparty_name'] ?? 'نامشخص');
                 // For simplified display strings used in lists/cards:
                 $tx['display_line'] = $tx['transaction_type_farsi'] . ' ' . $tx['product_name_display'] . ' ' . ($tx['transaction_type'] === 'buy' ? 'از' : 'به') . ' ' . $tx['contact_name_display'];
            }
            unset($tx); // Unset reference.

            $this->logger->debug("Fetched " . count($results) . " latest transactions for dashboard, formatted.", ['type' => $type]);
            return $results;
        } catch (Throwable $e) {
            $this->logger->error("Error fetching latest transactions for dashboard.", ['sql' => $sql, 'exception' => $e]);
            return [];
        }
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
     /**
     * Gets the total value (final_payable_amount_rials) for 'buy' or 'sell' transactions, for dashboard summary.
     * This sums only 'completed' transactions to reflect actual business volume.
     * @param string $type 'buy' or 'sell'
     * @return array Contains 'total_value_rials'. Defaulting to 0 if not exists.
     */
    public function getOverallTransactionSummary(string $type): array
    {
        $this->logger->debug("Getting overall transaction summary for type: {$type}.");
        try {
            $sql = "SELECT SUM(final_payable_amount_rials) AS total_value_rials FROM transactions WHERE transaction_type = :type AND delivery_status = 'completed'";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':type', $type, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ['total_value_rials' => (float)($result['total_value_rials'] ?? 0.0)];
        } catch (Throwable $e) {
            $this->logger->error("Error getting overall transaction summary for type {$type}.", ['exception' => $e]);
            return ['total_value_rials' => 0.0];
        }
    }
    
    /**
     * Fetches monthly transaction totals for charting.
     * Placeholder - Actual implementation would involve summing transaction amounts grouped by month.
     * @param int $numMonths The number of recent months to fetch.
     * @return array ['labels' => string[], 'data' => float[]] Monthly labels and total amounts.
     */
  /**
     * (اصلاح شده) داده‌های ماهانه خرید و فروش را برای نمودار داشبورد آماده می‌کند.
     * @param int $numMonths تعداد ماه‌های اخیر برای نمایش.
     * @return array شامل 'labels', 'buy_data', 'sell_data'.
     */
    public function getMonthlyTransactionData(int $numMonths = 6): array
    {
        $this->logger->info("Fetching monthly buy/sell data for dashboard chart.");
        $labels = [];
        $buy_data = [];
        $sell_data = [];

        try {
            // کوئری برای جمع‌بندی مبالغ خرید و فروش در هر ماه
            $sql = "
                SELECT 
                    DATE_FORMAT(transaction_date, '%Y-%m') AS month_key,
                    SUM(CASE WHEN transaction_type = 'buy' THEN final_payable_amount_rials ELSE 0 END) as total_buy,
                    SUM(CASE WHEN transaction_type = 'sell' THEN final_payable_amount_rials ELSE 0 END) as total_sell
                FROM transactions
                WHERE 
                    transaction_date >= DATE_SUB(CURDATE(), INTERVAL :num_months MONTH)
                    AND delivery_status = 'completed'
                GROUP BY month_key
                ORDER BY month_key ASC;
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':num_months', $numMonths, \PDO::PARAM_INT);
            $stmt->execute();
            $raw_data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $data_map = [];
            foreach ($raw_data as $row) {
                $data_map[$row['month_key']] = [
                    'buy' => (float)$row['total_buy'],
                    'sell' => (float)$row['total_sell']
                ];
            }

            // ایجاد لیبل‌ها و داده‌ها برای N ماه گذشته
            $current_date = new DateTime();
            for ($i = $numMonths - 1; $i >= 0; $i--) {
                $date = (clone $current_date)->modify("-{$i} month");
                $key = $date->format('Y-m');
                
                // استفاده از کتابخانه Morilog/Jalalian برای نام ماه‌های فارسی
                if (class_exists('Morilog\Jalali\Jalalian')) {
                    $labels[] = \Morilog\Jalali\Jalalian::fromDateTime($date)->format('F Y');
                } else {
                    $labels[] = $date->format('M Y'); // Fallback
                }
                
                $buy_data[] = $data_map[$key]['buy'] ?? 0.0;
                $sell_data[] = $data_map[$key]['sell'] ?? 0.0;
            }

        } catch (Throwable $e) {
            $this->logger->error("Error fetching monthly transaction data: " . $e->getMessage(), ['exception' => $e]);
            // در صورت خطا، داده خالی برگردان
        }

        return ['labels' => $labels, 'buy_data' => $buy_data, 'sell_data' => $sell_data];
    }

    /**
     * (جدید) تعداد کل معاملات را بر اساس فیلترها می‌شمارد.
     * @param array $filters
     * @param string|null $searchTerm
     * @return int
     */
    public function countFiltered(array $filters = [], ?string $searchTerm = null): int
    {
        $baseSql = "SELECT COUNT(DISTINCT t.id) FROM transactions t LEFT JOIN contacts c ON t.counterparty_contact_id = c.id";
        list($whereClause, $params) = $this->buildWhereClause($filters, $searchTerm);
        $sql = $baseSql . ' ' . $whereClause;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $this->logger->error("Database error counting filtered transactions.", ['exception' => $e]);
            return 0;
        }
    }

    /**
     * (جدید) لیست معاملات را به صورت صفحه‌بندی شده و با فیلتر برمی‌گرداند.
     * @param array $filters
     * @param string|null $searchTerm
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getFilteredAndPaginated(array $filters = [], ?string $searchTerm = null, int $limit = 15, int $offset = 0): array
    {
        $baseSql = "SELECT t.*, c.name as counterparty_name 
                    FROM transactions t 
                    LEFT JOIN contacts c ON t.counterparty_contact_id = c.id";
        list($whereClause, $params) = $this->buildWhereClause($filters, $searchTerm);
        $sql = $baseSql . ' ' . $whereClause . " ORDER BY t.transaction_date DESC, t.id DESC LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        try {
            $stmt = $this->db->prepare($sql);
            // bindValue is safer for LIMIT/OFFSET
            foreach ($params as $key => &$value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching paginated transactions.", ['exception' => $e]);
            return [];
        }
    }

       /**
     * (جدید و حیاتی) یک معامله را به همراه تمام آیتم‌ها و جزئیات مرتبط برای فرم ویرایش واکشی می‌کند.
     * @param int $id شناسه معامله
     * @return array|null
     */
  /**
     * (نهایی و کامل) یک معامله را به همراه تمام آیتم‌ها و جزئیات کامل برای فرم ویرایش واکشی می‌کند.
     */
  public function findByIdWithItems(int $id): ?array
    {
        $this->logger->debug("Fetching transaction with items for editing.", ['transaction_id' => $id]);
        
        // کوئری نهایی که تمام ستون‌های جدول transaction_items را شامل می‌شود
        $sql = "SELECT 
                    t.*, 
                    c.name as counterparty_name,
                    ti.*, 
                    ti.id as item_id, 
                    ti.description as item_description,
                    p.name as product_name,
                    pc.base_category
                FROM transactions t
                LEFT JOIN contacts c ON t.counterparty_contact_id = c.id
                LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
                LEFT JOIN products p ON ti.product_id = p.id
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                WHERE t.id = :id";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) { return null; }

            $transaction = [];
            $firstRow = $rows[0];
            
            // جدا کردن فیلدهای اصلی معامله از فیلدهای آیتم‌ها
            $itemKeys = array_keys((new \App\Models\TransactionItem())->toArray());
            $itemKeys[] = 'item_id';
            $itemKeys[] = 'item_description';
            $itemKeys[] = 'product_name';
            $itemKeys[] = 'base_category';


            foreach ($firstRow as $key => $value) {
                if (!in_array($key, $itemKeys)) {
                    $transaction[$key] = $value;
                }
            }
            $transaction['items'] = [];

            foreach ($rows as $row) {
                if (!is_null($row['item_id'])) {
                    $transaction['items'][] = $row;
                }
            }
            return $transaction;
        } catch (Throwable $e) {
            $this->logger->error("Error fetching transaction with items.", ['id' => $id, 'exception' => $e]);
            return null;
        }
    }


      /**
     * (اصلاح شده) حذف یک معامله بر اساس شناسه.
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $this->logger->info("Deleting transaction.", ['transaction_id' => $id]);
        try {
            $stmt = $this->db->prepare("DELETE FROM transactions WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Throwable $e) {
            $this->logger->error("Error deleting transaction.", ['id' => $id, 'exception' => $e]);
            return false;
        }
    }

    /**
     * (جدید) معاملاتی را برای صدور فاکتور بر اساس فیلترها واکشی می‌کند.
     * فقط معاملات تکمیل شده و لغو نشده را برمی‌گرداند.
     * @param array $filters شامل کلیدهای احتمالی: contact_id, transaction_type, start_date, end_date
     * @return array
     */
    public function getFilteredTransactionsForInvoice(array $filters): array
    {
        $this->logger->debug("Fetching transactions for invoice generation.", ['filters' => $filters]);

        $sql = "SELECT 
                    t.id, t.transaction_date, t.transaction_type, t.final_payable_amount_rials,
                    c.name as counterparty_name,
                    GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
                FROM transactions t
                LEFT JOIN contacts c ON t.counterparty_contact_id = c.id
                LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
                LEFT JOIN products p ON ti.product_id = p.id
                WHERE t.delivery_status = 'completed'"; // فقط معاملات تکمیل شده

        $params = [];

        if (!empty($filters['contact_id'])) {
            $sql .= " AND t.counterparty_contact_id = :contact_id";
            $params[':contact_id'] = $filters['contact_id'];
        }
        if (!empty($filters['transaction_type'])) {
            $sql .= " AND t.transaction_type = :transaction_type";
            $params[':transaction_type'] = $filters['transaction_type'];
        }
        if (!empty($filters['start_date'])) {
            $sql .= " AND t.transaction_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND t.transaction_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $sql .= " GROUP BY t.id ORDER BY t.transaction_date DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Error fetching transactions for invoice.", ['exception' => $e]);
            return [];
        }
    }

    /**
     * (جدید) معاملاتی را که برای صدور فاکتور در دسترس هستند، بر اساس فیلترها واکشی می‌کند.
     * این متد فقط معاملاتی را برمی‌گرداند که وضعیت آنها "تکمیل شده" است.
     */
    public function getUninvoicedTransactions(array $filters): array
    {
        $this->logger->debug("Fetching uninvoiced transactions for generator.", ['filters' => $filters]);
        
        $sql = "SELECT t.id, t.transaction_date, t.notes, ti.total_value_rials, p.name as product_name, 
                       ti.quantity, ti.weight_grams, ti.carat
                FROM transactions t
                JOIN transaction_items ti ON t.id = ti.transaction_id
                JOIN products p ON ti.product_id = p.id
                WHERE t.delivery_status = 'completed'";
        
        $params = [];

        if (!empty($filters['counterparty_contact_id'])) {
            $sql .= " AND t.counterparty_contact_id = :contact_id";
            $params[':contact_id'] = $filters['counterparty_contact_id'];
        }
        if (!empty($filters['transaction_type'])) {
            $sql .= " AND t.transaction_type = :type";
            $params[':type'] = $filters['transaction_type'];
        }
        if (!empty($filters['start_date_sql'])) {
            $sql .= " AND t.transaction_date >= :start_date";
            $params[':start_date'] = $filters['start_date_sql'];
        }
        if (!empty($filters['end_date_sql'])) {
            $sql .= " AND t.transaction_date <= :end_date";
            $params[':end_date'] = $filters['end_date_sql'];
        }
        
        $sql .= " ORDER BY t.transaction_date DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Error fetching transactions for invoice.", ['exception' => $e]);
            return [];
        }
    }

    /**
     * (جدید) چندین معامله را بر اساس آرایه‌ای از شناسه‌ها واکشی می‌کند.
     * برای استفاده در صفحه پیش‌نمایش فاکتور.
     */
    public function getTransactionsByIds(array $ids, int $contactId, string $type): array
        {            
        if (empty($ids)) return [];
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        // (اصلاح کلیدی) افزودن ti.total_general_tax_rials و ti.total_vat_rials به کوئری
        // کوئری اکنون تمام ستون‌های لازم از جمله مبالغ را می‌خواند
        $sql = "SELECT t.*, ti.*, ti.id as item_id, p.name as product_name, p.general_tax_base_type, p.vat_base_type, p.tax_rate, p.vat_rate, pc.base_category, ao.name as assay_office_name
            FROM transactions t
            JOIN transaction_items ti ON t.id = ti.transaction_id
            JOIN products p ON ti.product_id = p.id
            JOIN product_categories pc ON p.category_id = pc.id
            LEFT JOIN assay_offices ao ON ti.assay_office_id = ao.id
            WHERE t.id IN ({$placeholders}) 
            AND t.counterparty_contact_id = ? 
            AND t.transaction_type = ?";
        
        $params = array_merge($ids, [$contactId, $type]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Error fetching transactions by IDs.", ['exception' => $e]);
            return [];
        }
    }
}