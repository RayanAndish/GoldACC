<?php
// AllQueriesRepository.php
// این فایل فقط برای جمع‌آوری و مستندسازی کوئری‌های استخراج‌شده است و به مرور باید به Repositoryهای تخصصی منتقل شود.

namespace App\Repositories;

class AllQueriesRepository
{
    // --- کاربران (users) ---
    public static string $selectUsers = "SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC";
    public static string $countActiveUsers = "SELECT COUNT(*) FROM users WHERE is_active = 1";

    // --- کالاها (products) ---
    public static string $selectActiveProducts = "SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC";
    public static string $countProductByCode = "SELECT COUNT(*) FROM products WHERE product_code = :product_code";

    // --- دسته‌بندی کالا ---
    public static string $selectProductCategories = "SELECT * FROM product_categories ORDER BY name ASC";
    public static string $selectProductCategoryById = "SELECT * FROM product_categories WHERE id = :id";
    public static string $selectActiveProductCategories = "SELECT * FROM product_categories WHERE is_active = 1 ORDER BY name ASC";

    // --- مخاطبین (contacts) ---
    public static string $selectContacts = "SELECT id, name, type, details, credit_limit, created_at FROM contacts ORDER BY name ASC";
    public static string $countContacts = "SELECT COUNT(*) FROM contacts WHERE ...";

    // --- حساب بانکی ---
    public static string $selectBankAccounts = "SELECT id, account_name, bank_name, account_number, initial_balance, current_balance, created_at FROM bank_accounts ORDER BY account_name ASC";
    public static string $sumBankAccountsBalance = "SELECT SUM(current_balance) as total_balance FROM bank_accounts";

    // --- تراکنش‌ها ---
    public static string $selectTransactionById = "SELECT * FROM transactions WHERE id = :id LIMIT 1";
    public static string $selectTransactionsWithCounterparty = "SELECT t.*, c.name AS counterparty_name FROM transactions t LEFT JOIN contacts c ON t.counterparty_contact_id = c.id";
    public static string $countDistinctTransactions = "SELECT COUNT(DISTINCT t.id) FROM transactions t ...";

    // --- تنظیمات ---
    public static string $selectSettings = "SELECT `key`, `value` FROM settings";

    // --- گزارش فعالیت ---
    public static string $selectActivityLogs = "SELECT al.*, u.username FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC";
    public static string $countActivityLogs = "SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id";

    // --- سایر کوئری‌های آماری و گزارش‌گیری ---
    public static string $sumPayments = "SELECT SUM(amount_rials) FROM payments WHERE ...";
    public static string $sumTransactions = "SELECT SUM(total_value_rials) FROM transactions WHERE ...";
    public static string $countByField = "SELECT COUNT(*) FROM ... WHERE ...";
    public static string $selectWithJoin = "SELECT ... FROM ... INNER JOIN ... ON ... WHERE ...";
    // ... سایر کوئری‌های استخراج‌شده ...
}
 