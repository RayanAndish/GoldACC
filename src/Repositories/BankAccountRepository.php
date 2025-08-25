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
 * REVISED: Added missing methods for saving bank transactions and updating account balances, and constructor logging.
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
     * @return array آرایه‌ای از حساب‌ها.
     * @throws Throwable
     */
    public function getAll(): array {
        $this->logger->debug("Fetching all bank accounts.");
        try {
            // Added created_at and other columns that were being fetched in your provided snippet (list.txt).
            $sql = "SELECT id, account_name, bank_name, account_number, initial_balance, current_balance, created_at, updated_at FROM bank_accounts ORDER BY account_name ASC";
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
      * @param int $accountId شناسه حساب.
      * @return array|null آرایه اطلاعات حساب یا null.
      * @throws Throwable
      */
    public function getById(int $accountId): ?array {
        $this->logger->debug("Fetching bank account with ID: {$accountId}.");
        try {
            $sql = "SELECT id, account_name, bank_name, account_number, initial_balance, current_balance, created_at, updated_at FROM bank_accounts WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $accountId, PDO::PARAM_INT);
            $stmt->execute();
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($account) $this->logger->debug("Bank account found.", ['id' => $accountId]);
            else $this->logger->debug("Bank account not found.", ['id' => $accountId]);
            return $account ?: null;
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching bank account by ID {$accountId}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Saves (inserts or updates) a bank account record.
     * Original logic taken from user's provided snippet. Refined binding and nullable fields.
     * @param array $accountData Array of data (must include account_name and optionally id, bank_name, etc.)
     * @return int ID of the saved account.
     * @throws Throwable
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
                if (isset($accountData['current_balance'])) { // Update current_balance only if explicitly provided in update.
                    $sql .= ", current_balance = :curr_bal";
                }
                $sql .= " WHERE id = :id";

                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':id', $accountId, PDO::PARAM_INT);
                if (isset($accountData['current_balance'])) {
                    $stmt->bindValue(':curr_bal', $accountData['current_balance'], PDO::PARAM_STR);
                }

            } else { // Create new
                if (!isset($accountData['initial_balance'])) {
                     $this->logger->error("Attempted to create bank account without initial balance.", ['data' => $accountData]);
                     throw new Exception("Initial balance is required for new bank account.");
                }
                $sql = "INSERT INTO bank_accounts (account_name, bank_name, account_number, initial_balance, current_balance, created_at, updated_at)
                        VALUES (:name, :b_name, :acc_num, :init_bal, :curr_bal, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':init_bal', $accountData['initial_balance'], PDO::PARAM_STR);
                $stmt->bindValue(':curr_bal', $accountData['initial_balance'], PDO::PARAM_STR); // On create, current balance equals initial.
            }
            // Bind common parameters for both insert/update
            $stmt->bindValue(':name', $accountData['account_name'], PDO::PARAM_STR);
            $stmt->bindValue(':b_name', !empty($accountData['bank_name']) ? $accountData['bank_name'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':acc_num', !empty($accountData['account_number']) ? $accountData['account_number'] : null, PDO::PARAM_STR);
            $stmt->execute();

            if (!$isEditing) {
                $accountId = (int)$this->db->lastInsertId();
                $this->logger->info("Bank account created successfully with ID: {$accountId}.", ['name' => $accountData['account_name']]);
            } else { 
                 if ($stmt->rowCount() === 0) { $this->logger->warning("Bank account update attempted for ID {$accountId} but no row was affected."); } 
                 else { $this->logger->info("Bank account updated successfully.", ['id' => $accountId, 'name' => $accountData['account_name']]); }
            }
            return (int)$accountId;

        } catch (Throwable $e) {
             $this->logger->error("Database error saving bank account: " . $e->getMessage(), ['exception' => $e, 'id' => $accountId, 'name' => $accountData['account_name'] ?? 'N/A']);
             throw $e;
         }
    }

    /**
     * Checks if a bank account has associated bank transactions.
     * @param int $accountId
     * @return bool True if used, false otherwise.
     * @throws Throwable
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
        } catch (Throwable $e) {
            $this->logger->error("Database error checking bank account transactions for ID {$accountId}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Deletes a bank account by ID.
     * @param int $accountId
     * @return bool True if deleted, false if not found.
     * @throws Throwable If database error occurs (or if foreign key violation, re-throws as generic Exception).
     */
    public function delete(int $accountId): bool {
        $this->logger->info("Attempting to delete bank account with ID: {$accountId}.");
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
        } catch (PDOException $e) { // Catch specific PDOException for FK.
             $this->logger->error("Database error deleting bank account with ID {$accountId}: " . $e->getMessage(), ['exception' => $e]);
             if ($e->getCode() === '23000') { // SQLSTATE for Integrity Constraint Violation.
                  throw new Exception("امکان حذف حساب بانکی وجود ندارد: تراکنش‌های مرتبط در سیستم وجود دارد.", 0, $e);
             }
            throw $e; // Re-throw other PDO exceptions.
        } catch (Throwable $e) { // Catch general throwables.
             $this->logger->error("Error deleting bank account with ID {$accountId}: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
        }
    }

    /**
     * Calculates the sum of current balances across all bank accounts.
     * @return float The total balance.
     * @throws Throwable
     */
    public function getTotalCurrentBalance(): float {
        $this->logger->debug("Fetching total current balance from bank accounts.");
        try {
            $sql = "SELECT SUM(current_balance) as total_balance FROM bank_accounts";
            $stmt = $this->db->query($sql);
            $total = (float)($stmt->fetchColumn() ?: 0.0);
            $this->logger->debug("Total bank balance fetched.", ['total' => $total]);
            return $total;
        } catch (Throwable $e) {
            $this->logger->error("Database error fetching total bank balance.", ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Updates the current_balance of a specific bank account.
     * This method directly modifies `current_balance` by adding `amountChange`.
     * @param int $accountId The ID of the bank account.
     * @param float $amountChange The amount to add (positive for deposit, negative for withdrawal).
     * @return bool True on success.
     * @throws Throwable If database error occurs or account not found.
     */
    public function updateCurrentBalance(int $accountId, float $amountChange): bool {
        $this->logger->info("Updating current balance.", ['account_id' => $accountId, 'change' => $amountChange]);

        if (abs($amountChange) < 0.001) { // Avoid unnecessary updates for very small changes.
            $this->logger->debug("Balance update skipped, amount change is negligibly zero.", ['account_id' => $accountId]);
            return true;
        }

        $sql = "UPDATE bank_accounts SET current_balance = current_balance + :amount_change, updated_at = CURRENT_TIMESTAMP WHERE id = :account_id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':amount_change', $amountChange, PDO::PARAM_STR); // Bind float as string for precision.
            $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
            $stmt->execute();

            $rowCount = $stmt->rowCount();
            if ($rowCount === 1) {
                $this->logger->info("Current balance updated successfully.", ['account_id' => $accountId, 'change' => $amountChange]);
                return true;
            } elseif ($rowCount === 0) {
                $this->logger->error("Failed to update balance: Account ID not found or balance unchanged.", ['account_id' => $accountId, 'change' => $amountChange]);
                throw new Exception("حساب بانکی برای به‌روزرسانی موجودی یافت نشد.", 0); // User friendly message.
            } else {
                $this->logger->critical("Multiple rows affected while updating balance for a single account (PrimaryKey problem?).", ['account_id' => $accountId, 'affected_rows' => $rowCount]);
                throw new Exception("خطای سیستمی: بیش از یک حساب بانکی هنگام به‌روزرسانی موجودی تحت تاثیر قرار گرفت.", 0);
            }
        } catch (Throwable $e) {
            $this->logger->error("Database error updating current balance for ID {$accountId}.", ['exception' => $e]);
            throw $e; // Re-throw.
        }
    }


    /**
     * Saves a record to the `bank_transactions` table (creates a new entry).
     * Used internally by `PaymentRepository` methods or direct calls for bank movements.
     * This handles records directly affecting bank account balance changes.
     *
     * @param array $bankTransactionData Data to save: bank_account_id, amount, type (deposit/withdrawal), transaction_date, description, related_payment_id.
     * @return int The ID of the newly created bank transaction record.
     * @throws Throwable
     */
    public function saveBankTransaction(array $bankTransactionData): int {
        $this->logger->info("Saving bank transaction record.", ['data' => $bankTransactionData]);
        
        // Define all columns that can be inserted, match DB schema
        $allowedColumns = [
            'bank_account_id', 'transaction_date', 'amount', 'type', 'description', 'related_payment_id',
        ];

        // Filter and sanitize incoming data
        $filteredData = array_intersect_key($bankTransactionData, array_flip($allowedColumns));

        // Basic validation before insert.
        if (empty($filteredData['bank_account_id']) || !isset($filteredData['amount']) || empty($filteredData['transaction_date'])) {
            $this->logger->error("Missing essential data for saving bank transaction.", ['data' => $filteredData]);
            throw new Exception("داده‌های ضروری برای ثبت تراکنش بانکی (شناسه حساب، مبلغ، تاریخ) ناقص است.");
        }

        try {
            $insertFields = array_keys($filteredData);
            $placeholders = array_map(fn($f) => ':' . $f, $insertFields);
            $sql = "INSERT INTO bank_transactions (`" . implode('`,`', $insertFields) . "`, created_at) VALUES (" . implode(',', $placeholders) . ", CURRENT_TIMESTAMP)";
            
            $stmt = $this->db->prepare($sql);

            foreach ($filteredData as $key => $value) {
                $type = PDO::PARAM_STR;
                if ($key === 'bank_account_id' || $key === 'related_payment_id') { $type = PDO::PARAM_INT; }
                else if ($key === 'amount') { $type = PDO::PARAM_STR; } // Bind as string for DECIMAL precision.
                else if ($value === null) { $type = PDO::PARAM_NULL; }
                $stmt->bindValue(':' . $key, $value, $type);
            }
            $stmt->execute();
            
            $lastInsertId = (int)$this->db->lastInsertId();
            $this->logger->info("Bank transaction created successfully.", ['id' => $lastInsertId, 'account_id' => $filteredData['bank_account_id']]);
            return $lastInsertId;

        } catch (Throwable $e) {
            $this->logger->error("Database error saving bank transaction record: " . $e->getMessage(), ['exception' => $e, 'data' => $bankTransactionData]);
            throw $e;
        }
    }
    
    /**
     * Fetches bank transactions for a specific account within a date range.
     * @param int $accountId
     * @param string|null $startDateSql Y-m-d H:i:s
     * @param string|null $endDateSql Y-m-d H:i:s
     * @return array
     * @throws Throwable
     */
    public function getTransactionsByAccountIdAndDateRange(int $accountId, ?string $startDateSql = null, ?string $endDateSql = null): array
    {
        $this->logger->debug("Fetching bank transactions for account ID: {$accountId}.", ['start_date' => $startDateSql, 'end_date' => $endDateSql]);
        
        $sql = "SELECT bt.*, ba.account_name
                FROM bank_transactions bt
                JOIN bank_accounts ba ON bt.bank_account_id = ba.id
                WHERE bt.bank_account_id = :account_id";
        $params = [':account_id' => $accountId];

        if ($startDateSql) {
            $sql .= " AND bt.transaction_date >= :start_date";
            $params[':start_date'] = $startDateSql;
        }
        if ($endDateSql) {
            $sql .= " AND bt.transaction_date <= :end_date";
            $params[':end_date'] = $endDateSql;
        }

        $sql .= " ORDER BY bt.transaction_date ASC, bt.id ASC";

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $type = (str_ends_with($key, '_id') || $key === ':account_id') ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $type);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Error fetching bank transactions for account ID {$accountId}.", ['exception' => $e, 'sql' => $sql, 'params' => $params]);
            throw $e;
        }
    }
}