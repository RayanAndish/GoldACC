<?php

namespace App\Services;

use PDO;
use PDOException; // Catch specific DB errors
use Monolog\Logger;
use Exception; // General exceptions
use Throwable; // Catch errors and exceptions

/**
 * DatabaseService provides utilities for database maintenance operations,
 * such as optimizing tables.
 */
class DatabaseService {

    private PDO $db;
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param PDO $db Database connection instance.
     * @param Logger $logger Logger instance.
     */
    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
        $this->logger->debug("DatabaseService initialized.");
    }

    /**
     * Runs OPTIMIZE TABLE on all tables in the current database.
     * Note: For InnoDB, OPTIMIZE TABLE often maps to ALTER TABLE ... ENGINE=InnoDB,
     * which rebuilds the table and can take time and resources.
     *
     * @return array An associative array with table names as keys and their optimization results (or error messages) as values.
     * @throws Exception If listing tables fails or an unexpected error occurs.
     */
    public function optimizeTables(): array {
        $this->logger->info("Starting database table optimization process.");
        $results = [];
        $startTime = microtime(true);

        try {
            // 1. Get list of tables
            // Using SHOW TABLES is generally safe and simpler than INFORMATION_SCHEMA for this task.
            $stmt = $this->db->query("SHOW TABLES");
            // Fetch all table names into a simple array
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $this->logger->debug("Found tables to optimize.", ['tables' => $tables]);

            if (empty($tables)) {
                $this->logger->info("No tables found in the database to optimize.");
                return [];
            }

            // 2. Execute OPTIMIZE TABLE for each table
            foreach ($tables as $table) {
                // Basic sanity check on table name (though SHOW TABLES should be safe)
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                    $this->logger->warning("Skipping potentially unsafe table name found by SHOW TABLES.", ['table_name' => $table]);
                    $results[$table] = ['Status' => 'Skipped', 'Msg_type' => 'Warning', 'Msg_text' => 'Invalid table name format'];
                    continue;
                }

                $this->logger->debug("Optimizing table.", ['table' => $table]);
                try {
                    // Use backticks for table name safety
                    $optimizeStmt = $this->db->query("OPTIMIZE TABLE `" . $table . "`");
                    // Fetch results (usually one row per table with status info)
                    $optimizeResult = $optimizeStmt->fetchAll(PDO::FETCH_ASSOC);
                    $results[$table] = $optimizeResult;
                    // Log the primary message from the result
                    $this->logger->debug("Optimization result.", ['table' => $table, 'result' => $optimizeResult[0]['Msg_text'] ?? 'N/A']);
                    $optimizeStmt->closeCursor(); // Close cursor after fetching

                } catch (PDOException $e) {
                    $this->logger->error("Database error optimizing table.", ['table' => $table, 'exception' => $e]);
                    // Store error message in results for this table
                    $results[$table] = [['Status' => 'Error', 'Msg_type' => 'Error', 'Msg_text' => $e->getMessage()]];
                    // Continue with the next table despite the error on this one
                }
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->logger->info("Database table optimization process finished.", ['duration_sec' => $duration, 'tables_processed' => count($tables)]);
            return $results;

        } catch (PDOException $e) { // Catch errors from SHOW TABLES query
            $this->logger->error("Database error listing tables for optimization.", ['exception' => $e]);
            throw new Exception("Failed to list database tables for optimization.", 0, $e);
        } catch (Throwable $e) { // Catch any other unexpected errors
             $this->logger->error("Unexpected error during database optimization.", ['exception' => $e]);
             throw new Exception("An unexpected error occurred during database optimization.", 0, $e);
        }
    }

    /**
     * Purges application data by truncating specific tables.
     * WARNING: This is a destructive operation.
     *
     * @return bool True on success, false if any truncate operation fails.
     * @throws \PDOException if there's a general database error.
     */
    public function purgeApplicationData(): bool {
        $this->logger->critical("!!! INITIATING APPLICATION DATA PURGE !!!");

        // --- Define tables --- 
        // IMPORTANT: Carefully review and adjust these lists according to your actual schema!
        $tablesToTruncate = [ // Tables containing operational data to be purged
            'transactions',
            'payments',
            'inventory',        // General inventory
            'coin_inventory',   // Specific coin inventory
            'contacts',         // Counterparties/Contacts
            'assay_offices',
            'bank_accounts',
            'bank_transactions',
             // Add any other purely operational data tables here
        ];

        $tablesToKeep = [
             // Core system and configuration tables
            'settings',
            'users',
            'roles',
            'phinxlog',          // Phinx migration history table
            'update_history',
            'activity_logs',
            // Licensing related tables
            'licenses',
            'license_activations',
            'license_checks',
            'license_logs',
            'license_requests',
            // Security related tables
            'allowed_ips',
            'blocked_ips',
            'login_attempts',
            'rate_limits',
             // Add any other tables that should persist after reset
        ];

        // Optional: Double-check against all tables in the DB to ensure nothing is missed
        // try {
        //     $stmt = $this->db->query('SHOW TABLES');
        //     $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        //     $knownTables = array_merge($tablesToTruncate, $tablesToKeep);
        //     $unknownTables = array_diff($allTables, $knownTables);
        //     if (!empty($unknownTables)) {
        //         $this->logger->warning('Unknown tables found during purge prep. These will NOT be truncated:', ['tables' => $unknownTables]);
        //     }
        // } catch (Throwable $e) {
        //     $this->logger->error('Could not get full table list for purge validation.', ['exception' => $e]);
        // }

        $allSuccessful = true;
        $this->db->beginTransaction();

        try {
            // Disable foreign key checks temporarily for TRUNCATE
            $this->db->exec('SET FOREIGN_KEY_CHECKS=0;');
            $this->logger->info('Disabled foreign key checks for truncation.');

            foreach ($tablesToTruncate as $table) {
                $this->logger->warning("Truncating table: {$table}");
                try {
                    $sql = "TRUNCATE TABLE `" . $table . "`";
                    $stmt = $this->db->prepare($sql); // Prepare to be safe, though TRUNCATE often doesn't take params
                    $stmt->execute();
                    $this->logger->info("Successfully truncated table: {$table}");
                } catch (Throwable $e) {
                    $this->logger->error("Failed to truncate table: {$table}", ['exception' => $e]);
                    $allSuccessful = false;
                    // Decide whether to continue truncating other tables or stop immediately
                    // break; // Stop on first error
                }
            }

            if ($allSuccessful) {
                 $this->logger->critical("All specified tables truncated successfully.");
                 $this->db->commit();
                 $this->logger->info('Re-enabling foreign key checks after commit.');
                 $this->db->exec('SET FOREIGN_KEY_CHECKS=1;');
                 return true;
            } else {
                 $this->logger->error("One or more tables failed to truncate. Rolling back transaction.");
                 $this->db->rollBack();
                 $this->logger->info('Re-enabling foreign key checks after rollback.');
                 $this->db->exec('SET FOREIGN_KEY_CHECKS=1;');
                 return false;
            }

        } catch (Throwable $e) {
            // General exception during the process
             $this->logger->error("Exception during data purge transaction.", ['exception' => $e]);
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
                 $this->logger->info('Rolled back transaction due to exception during purge.');
            }
            // Ensure foreign keys are re-enabled even on general error
            try { $this->db->exec('SET FOREIGN_KEY_CHECKS=1;'); } catch (Throwable $fkError) { /* Ignore */ }
            throw $e; // Re-throw the exception
        }
    }

    // Potential future methods:
    // - repairTables(): array
    // - checkTables(): array
    // - getDatabaseSize(): float
    // - getTableSizes(): array

} // End DatabaseService class