<?php

namespace App\Repositories; // Namespace مطابق با پوشه src/Repositories

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;
use Throwable;
use RuntimeException;

/**
 * کلاس BankAccountRepository برای تعامل با جداول پایگاه داده bank_accounts و bank_transactions.
 */
class BankAccountRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * دریافت تمام حساب‌های بانکی.
     *
     * @return array آرایه‌ای از حساب‌ها.
     * @throws PDOException.
     */
    public function getAll(): array {
        $this->logger->debug("Fetching all bank accounts.");
        try {
            $sql = "SELECT id, account_name, bank_name, account_number, initial_balance, current_balance, created_at FROM bank_accounts ORDER BY account_name ASC";
            $stmt = $this->db->query($sql);
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->info("Fetched " . count($accounts) . " bank accounts.");
            return $accounts;
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching all bank accounts: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

     /**
      * دریافت یک حساب بانکی بر اساس ID.
      *
      * @param int $accountId شناسه حساب.
      * @return array|null آرایه اطلاعات حساب یا null.
      * @throws PDOException.
      */
    public function getById(int $accountId): ?array {
        $this->logger->debug("Fetching bank account with ID: {$accountId}.");
        try {
            $sql = "SELECT id, account_name, bank_name, account_number, initial_balance, current_balance, created_at FROM bank_accounts WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $accountId, PDO::PARAM_INT);
            $stmt->execute();
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($account) $this->logger->debug("Bank account found.", ['id' => $accountId]);
            else $this->logger->debug("Bank account not found.", ['id' => $accountId]);
            return $account ?: null;
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching bank account by ID {$accountId}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }


    /**
     * ذخیره (افزودن یا ویرایش) یک حساب بانکی.
     * منطق از src/action/bank_account_save.php گرفته شده.
     *
     * @param array $accountData آرایه داده‌ها (باید شامل account_name و اختیاری id, bank_name, account_number, initial_balance, current_balance باشد).
     * @return int شناسه حساب ذخیره شده.
     * @throws PDOException.
     * @throws Exception در صورت داده نامعتبر.
     */
    public function save(array $accountData): int {
        $accountId = $accountData['id'] ?? null;
        $isEditing = $accountId !== null;
        $this->logger->info(($isEditing ? "Updating" : "Creating") . " bank account.", ['id' => $accountId, 'name' => $accountData['account_name'] ?? 'N/A']);

        if (empty($accountData['account_name'])) {
            $this->logger->error("Attempted to save bank account with missing name.", ['data' => $accountData]);
            throw new Exception("نام حساب بانکی الزامی است.");
        }

        try {
            if ($isEditing) {
                $sql = "UPDATE bank_accounts SET account_name = :name, bank_name = :b_name, account_number = :acc_num, updated_at = CURRENT_TIMESTAMP";
                // Update current_balance ONLY IF it's explicitly provided and validated.
                // Balance should ideally be updated through transactions, not direct edit.
                if (isset($accountData['current_balance'])) { // Check if key exists AND value is not null
                    $sql .= ", current_balance = :curr_bal";
                }
                $sql .= " WHERE id = :id";

                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':id', $accountId, PDO::PARAM_INT);
                if (isset($accountData['current_balance'])) {
                    $stmt->bindValue(':curr_bal', $accountData['current_balance'], PDO::PARAM_STR);
                }

            } else { // Add
                // For add, initial_balance is required in $accountData
                if (!isset($accountData['initial_balance'])) {
                     $this->logger->error("Attempted to create bank account without initial balance.", ['data' => $accountData]);
                     throw new Exception("Initial balance is required for new bank account.");
                }
                $sql = "INSERT INTO bank_accounts (account_name, bank_name, account_number, initial_balance, current_balance, created_at, updated_at)
                        VALUES (:name, :b_name, :acc_num, :init_bal, :curr_bal, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                $stmt = $this->db->prepare($sql);
                // In add, current balance is same as initial
                $stmt->bindValue(':init_bal', $accountData['initial_balance'], PDO::PARAM_STR);
                $stmt->bindValue(':curr_bal', $accountData['initial_balance'], PDO::PARAM_STR);
            }
            // Bind common parameters
            $stmt->bindValue(':name', $accountData['account_name'], PDO::PARAM_STR);
            $stmt->bindValue(':b_name', !empty($accountData['bank_name']) ? $accountData['bank_name'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':acc_num', !empty($accountData['account_number']) ? $accountData['account_number'] : null, PDO::PARAM_STR);
            $stmt->execute();

            if (!$isEditing) {
                $accountId = (int)$this->db->lastInsertId();
                $this->logger->info("Bank account created successfully with ID: {$accountId}.", ['name' => $accountData['account_name']]);
            } else {
                 if ($stmt->rowCount() === 0) {
                      $this->logger->warning("Bank account update attempted for ID {$accountId} but no row was affected.");
                 } else {
                      $this->logger->info("Bank account updated successfully.", ['id' => $accountId, 'name' => $accountData['account_name']]);
                 }
            }

            return (int)$accountId;

        } catch (PDOException $e) {
             $this->logger->error("Database error saving bank account: " . $e->getMessage(), ['exception' => $e, 'id' => $accountId, 'name' => $accountData['account_name'] ?? 'N/A']);
             // Add checks for specific constraints if needed (e.g., UNIQUE on account_name?)
             // if ($e->getCode() === '23000') { ... }
             throw $e;
         } catch (Throwable $e) {
             $this->logger->error("Error saving bank account: " . $e->getMessage(), ['exception' => $e, 'id' => $accountId, 'name' => $accountData['account_name'] ?? 'N/A']);
             throw $e;
         }
    }

     /**
      * بررسی اینکه آیا یک حساب بانکی در جدول bank_transactions استفاده شده است.
      * منطق از src/action/bank_account_delete.php گرفته شده.
      *
      * @param int $accountId شناسه حساب.
      * @return bool True اگر استفاده شده، False در غیر این صورت.
      * @throws PDOException.
      */
     public function hasTransactions(int $accountId): bool {
         $this->logger->debug("Checking for transactions for bank account ID {$accountId}.");
         try {
             $sql = "SELECT 1 FROM bank_transactions WHERE bank_account_id = :account_id LIMIT 1";
             $stmt = $this->db->prepare($sql);
             $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
             $stmt->execute();
             $used = $stmt->fetchColumn();
             $this->logger->debug("Bank account ID {$accountId} has transactions: " . ($used ? 'Yes' : 'No'));
             return (bool)$used;
         } catch (PDOException $e) {
             $this->logger->error("Database error checking bank account transactions for ID {$accountId}: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
         }
     }

    /**
     * حذف یک حساب بانکی بر اساس ID.
     * منطق از src/action/bank_account_delete.php گرفته شده.
     *
     * @param int $accountId شناسه حساب برای حذف.
     * @return bool True اگر حذف شد، False اگر یافت نشد.
     * @throws PDOException در صورت خطای دیتابیس (شامل Foreign Key اگر چک نشده).
     * @throws Exception در صورت خطای دیگر.
     */
    public function delete(int $accountId): bool {
        $this->logger->info("Attempting to delete bank account with ID: {$accountId}.");
        // ** نکته: چک کردن وابستگی با hasTransactions() باید قبل از فراخوانی این متد در Service یا Controller انجام شود. **
        // اگر Foreign Key ON DELETE CASCADE روی bank_transactions به bank_accounts دارید، این متد به تنهایی کافی است
        // اما چک کردن قبلی برای نمایش پیام مناسب به کاربر بهتر است.

        try {
            $sql = "DELETE FROM bank_accounts WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $accountId, PDO::PARAM_INT);
            $stmt->execute();

            $deletedCount = $stmt->rowCount();
            if ($deletedCount > 0) {
                $this->logger->info("Bank account deleted successfully.", ['id' => $accountId]);
                return true;
            } else {
                $this->logger->warning("Bank account delete attempted for ID {$accountId} but no row was affected (Not found?).");
                return false;
            }
        } catch (PDOException $e) {
             $this->logger->error("Database error deleting bank account with ID {$accountId}: " . $e->getMessage(), ['exception' => $e]);
              // اگر Foreign Key Constraint تعریف شده باشد و چک کردن فراموش شده باشد، اینجا خطا می‌دهد.
             if ($e->getCode() === '23000') { // Foreign key violation
                  throw new Exception("امکان حذف حساب بانکی وجود ندارد: تراکنش‌های مرتبط در سیستم وجود دارد.", 0, $e);
             }
            throw $e;
        } catch (Throwable $e) {
             $this->logger->error("Error deleting bank account with ID {$accountId}: " + $e->getMessage(), ['exception' => $e]);
             throw $e;
        }
    }

    /**
     * Calculates the sum of current balances across all bank accounts.
     *
     * @return float The total balance.
     */
    public function getTotalCurrentBalance(): float
    {
        $this->logger->debug("Fetching total current balance from bank accounts.");
        try {
            // Query from dash.php
            $sql = "SELECT SUM(current_balance) as total_balance FROM bank_accounts";
            $stmt = $this->db->query($sql);
            $total = (float)($stmt->fetchColumn() ?: 0.0);
            $this->logger->debug("Total bank balance fetched.", ['total' => $total]);
            return $total;
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching total bank balance.", ['exception' => $e]);
            return 0.0; // Return 0 on error
        }
    }

    /**
     * Updates the current balance of a specific bank account by a given amount.
     * Uses an atomic operation (balance = balance + change).
     *
     * @param int $accountId The ID of the bank account.
     * @param float $amountChange The amount to add (positive) or subtract (negative).
     * @return bool True on success, false otherwise.
     * @throws PDOException | RuntimeException
     */
    public function updateCurrentBalance(int $accountId, float $amountChange): bool
    {
        $this->logger->info("Updating current balance.", ['account_id' => $accountId, 'change' => $amountChange]);

        // Avoid doing anything if change is zero (or very close to it)
        if (abs($amountChange) < 0.001) {
            $this->logger->debug("Balance update skipped, amount change is zero.", ['account_id' => $accountId]);
            return true;
        }

        $sql = "UPDATE bank_accounts SET current_balance = current_balance + :amount_change WHERE id = :account_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':amount_change', $amountChange, PDO::PARAM_STR); // Bind float as string for precision
            $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
            $stmt->execute();

            $rowCount = $stmt->rowCount();
            if ($rowCount === 1) {
                $this->logger->info("Current balance updated successfully.", ['account_id' => $accountId, 'change' => $amountChange]);
                return true;
            } elseif ($rowCount === 0) {
                $this->logger->error("Failed to update balance: Account ID not found or balance unchanged.", ['account_id' => $accountId, 'change' => $amountChange]);
                throw new RuntimeException("حساب بانکی برای به‌روزرسانی موجودی یافت نشد.");
            } else {
                // Should not happen with a primary key condition
                $this->logger->critical("Multiple rows affected while updating balance for a single account.", ['account_id' => $accountId, 'affected_rows' => $rowCount]);
                throw new RuntimeException("خطای سیستمی: بیش از یک حساب بانکی هنگام به‌روزرسانی موجودی تحت تاثیر قرار گرفت.");
            }
        } catch (PDOException $e) {
            $this->logger->error("Database error updating current balance.", ['account_id' => $accountId, 'change' => $amountChange, 'exception' => $e]);
            throw $e; // Re-throw PDOException
        } catch (Throwable $e) {
            // Catch any other potential error (like the RuntimeExceptions thrown above)
            $this->logger->error("Generic error updating current balance.", ['account_id' => $accountId, 'change' => $amountChange, 'exception' => $e]);
            throw $e; // Re-throw
        }
    }

    // متدهای دیگر برای کار با bank_transactions
    // public function getTransactionsByAccountId(int $accountId): array { /* ... */ }
    // public function saveTransaction(array $transactionData): int { /* ... */ }
    // public function deleteTransaction(int $transactionId): bool { /* ... */ }
    // ...
}