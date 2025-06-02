<?php

namespace App\Core; // Added Namespace

use PDO; // PHP Data Objects extension
use PDOException; // Specific exception for PDO errors
use Exception; // General Exception class
use Monolog\Logger; // For logging purposes

/**
 * Database class manages the database connection using the Singleton pattern.
 * Ensures only one instance of the PDO connection is created.
 */
class Database {
    /** @var Database|null The single instance of the Database class */
    private static ?Database $instance = null;

    /** @var PDO|null The active PDO connection object */
    private ?PDO $conn = null;

    /** @var array Database configuration details */
    private array $dbConfig;

    /** @var Logger Logger instance for logging database events */
    private Logger $logger;

    /**
     * Private constructor to prevent direct instantiation.
     * Initializes the database connection.
     *
     * @param array $dbConfig Database configuration array (host, database, username, password, charset, port, options).
     * @param Logger $logger Monolog Logger instance.
     * @throws PDOException If the connection fails.
     */
    private function __construct(array $dbConfig, Logger $logger) {
        $this->dbConfig = $dbConfig;
        $this->logger = $logger;
        $this->logger->debug("Database class constructor called. Attempting connection.");
        $this->connect(); // Establish connection on instantiation
        // The createSecurityTables() method was correctly removed from here. Migrations should handle schema.
    }

    /**
     * Gets the single instance of the Database class.
     * Creates the instance on the first call, requires config and logger then.
     * Subsequent calls return the existing instance.
     *
     * @param array|null $dbConfig Database configuration (required on first call).
     * @param Logger|null $logger Logger instance (required on first call).
     * @return Database The singleton Database instance.
     * @throws Exception If config or logger are missing on the first call.
     */
    public static function getInstance(?array $dbConfig = null, ?Logger $logger = null): Database {
        if (self::$instance === null) {
            if ($dbConfig === null || $logger === null) {
                // This indicates a bootstrapping problem in the application.
                error_log("FATAL ERROR: Database::getInstance() called for the first time without config or logger.");
                throw new Exception("Database service not properly initialized. Configuration or Logger missing.");
            }
            self::$instance = new self($dbConfig, $logger);
        }
        // Silently ignore config/logger if passed on subsequent calls, or log a warning:
        // elseif (($dbConfig !== null || $logger !== null) && self::$instance->logger) {
        //      self::$instance->logger->warning("Database::getInstance() called again with config/logger after initial creation. Arguments ignored.");
        // }
        return self::$instance;
    }

    /**
     * Returns the active PDO connection object.
     *
     * @return PDO The active PDO connection.
     * @throws Exception If the connection is not established (should not happen after successful construction).
     */
    public function getConnection(): PDO {
        if ($this->conn === null) {
            // This indicates a serious issue if it occurs after the constructor succeeded.
            $this->logger->critical("Attempted to get database connection, but it's null after initialization!");
            throw new Exception("Database connection is not established or has been lost.");
        }
        return $this->conn;
    }

    /**
     * Establishes the PDO database connection using the stored configuration.
     *
     * @throws PDOException If the connection attempt fails.
     */
    private function connect(): void {
        // Build DSN string
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            $this->dbConfig['host'] ?? 'localhost',
            (int)($this->dbConfig['port'] ?? 3306),
            $this->dbConfig['database'] ?? '',
            $this->dbConfig['charset'] ?? 'utf8mb4'
        );

        // Default PDO options, merge with any provided in config
        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
            PDO::ATTR_PERSISTENT => false, // Typically avoid persistent connections unless specifically needed
             // Set UTF-8 for MySQL connection
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . ($this->dbConfig['charset'] ?? 'utf8mb4') . " COLLATE " . ($this->dbConfig['collation'] ?? 'utf8mb4_unicode_ci')
        ];
        $options = array_replace($defaultOptions, $this->dbConfig['options'] ?? []);

        try {
            // فقط اطلاعات غیر حساس مانند هاست و نام دیتابیس را لاگ کنید.
            $this->logger->debug("Connecting to database.", [
            'host' => $this->dbConfig['host'] ?? 'N/A',
            'port' => $this->dbConfig['port'] ?? 'N/A',
            'database' => $this->dbConfig['database'] ?? 'N/A',
            // username و dsn کامل لاگ نشوند!
            ]);
        

            $this->conn = new PDO(
                $dsn,
                $this->dbConfig['username'] ?? '',
                $this->dbConfig['password'] ?? '',
                $options
            );

            $this->logger->info("Database connection established successfully.");

        } catch (PDOException $e) {
            // Log the detailed error, but rethrow a more generic or the original exception
            $this->logger->critical("Database connection failed: " . $e->getMessage(), [
                 // 'exception' => $e, // Logging the full exception can be verbose, message is often enough here.
                 'code' => $e->getCode(),
                 'db_config_summary' => [ // Log non-sensitive parts of config
                     'host' => $this->dbConfig['host'] ?? 'N/A',
                     'port' => $this->dbConfig['port'] ?? 'N/A',
                     'database' => $this->dbConfig['database'] ?? 'N/A',
                     'charset' => $this->dbConfig['charset'] ?? 'N/A',
                 ]
            ]);

            // Rethrow the original PDOException. The global ErrorHandler will catch it.
            throw $e;
        }
    }

    /**
     * Prevents cloning of the instance.
     */
    private function __clone(): void {
        $this->logger?->warning("Attempted to clone Singleton Database instance.");
    }

    /**
     * Prevents unserialization of the instance.
     * @throws Exception
     */
    public function __wakeup(): void {
        $this->logger?->error("Attempted to unserialize Singleton Database instance.");
        throw new Exception("Cannot unserialize a singleton instance of " . __CLASS__);
    }

    /**
     * Manually creates security-related tables (for reference or manual setup).
     * **It's strongly recommended to use a database migration system instead.**
     *
     * @return bool True on success.
     * @throws PDOException If a query fails.
     * @throws Exception If connection is not established.
     */
    public function createSecurityTablesManually(): bool {
         $this->logger->warning("Executing createSecurityTablesManually. Use migrations for schema management.");

         if ($this->conn === null) {
             throw new Exception("Cannot create tables: Database connection is not established.");
         }

         // Use backticks for table/column names if they might be reserved words
         $sqlStatements = [
            "CREATE TABLE IF NOT EXISTS `allowed_ips` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `ip_address` VARCHAR(45) NOT NULL UNIQUE COMMENT 'IPv4 or IPv6',
                `description` TEXT NULL COMMENT 'Reason for allowing',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", // Recommend utf8mb4_unicode_ci

            "CREATE TABLE IF NOT EXISTS `blocked_ips` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `ip_address` VARCHAR(45) NOT NULL UNIQUE COMMENT 'IPv4 or IPv6',
                `block_until` TIMESTAMP NOT NULL COMMENT 'Timestamp until blocking expires',
                `reason` TEXT NULL COMMENT 'Reason for blocking (e.g., failed logins)',
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_block_until` (`block_until`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(255) NULL COMMENT 'Username attempted (can be null if IP blocked)',
                `ip_address` VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6',
                `success` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=Successful, 0=Failed',
                `attempt_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_ip_time` (`ip_address`, `attempt_time`),
                INDEX `idx_username_time` (`username`, `attempt_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
         ];

         // Execute statements one by one
         // Consider wrapping in a transaction if needed, though CREATE TABLE IF NOT EXISTS is usually safe.
         foreach ($sqlStatements as $sql) {
            try {
                 $this->conn->exec($sql);
                 // Log concisely
                 $tableName = preg_match('/CREATE TABLE IF NOT EXISTS `(.*?)`/', $sql, $matches) ? $matches[1] : 'Unknown Table';
                 $this->logger->info("Executed schema statement successfully.", ['table' => $tableName]);
            } catch(PDOException $e) {
                 $this->logger->error("Failed to execute schema statement: " . $e->getMessage(), [
                     'sql_summary' => substr(trim($sql), 0, 100) . '...',
                     'exception' => $e
                 ]);
                 throw $e; // Stop execution if one statement fails
            }
         }

         $this->logger->info("Manual security tables creation process finished.");
         return true;
    }

    // Convenience methods (optional additions):

    /**
     * Prepares and executes a SQL statement, returning the statement object.
     * Handles potential exceptions during preparation or execution.
     *
     * @param string $sql SQL query with placeholders.
     * @param array $params Parameters to bind.
     * @return \PDOStatement|false The PDOStatement object, or false on failure.
     */
    public function run(string $sql, array $params = []): \PDOStatement|false
    {
        try {
            if (empty($params)) {
                return $this->conn->query($sql); // Use query for simple statements
            } else {
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            }
        } catch (PDOException $e) {
            $this->logger->error("Database query failed.", [
                'sql' => substr(trim($sql), 0, 200) . '...', // Log truncated SQL
                'params' => $params, // Be careful logging parameters if they contain sensitive info
                'errorInfo' => $e->errorInfo,
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ]);
            // Depending on application needs, you might re-throw, return false, or handle differently
             // throw $e; // Rethrow if the caller should handle it
            return false; // Indicate failure
        }
    }

    /**
     * Fetches a single row from a query result.
     *
     * @param string $sql
     * @param array $params
     * @param int $fetchMode PDO fetch mode (default FETCH_ASSOC)
     * @return mixed The fetched row or false if no row.
     */
    public function fetchOne(string $sql, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): mixed
    {
        $stmt = $this->run($sql, $params);
        return $stmt ? $stmt->fetch($fetchMode) : false;
    }

    /**
     * Fetches all rows from a query result.
     *
     * @param string $sql
     * @param array $params
     * @param int $fetchMode PDO fetch mode (default FETCH_ASSOC)
     * @return array An array of fetched rows.
     */
    public function fetchAll(string $sql, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): array
    {
        $stmt = $this->run($sql, $params);
        return $stmt ? $stmt->fetchAll($fetchMode) : [];
    }

    /**
     * Returns the ID of the last inserted row.
     *
     * @param string|null $name Name of the sequence object (driver-dependent).
     * @return string|false The last insert ID, or false on failure.
     */
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->conn->lastInsertId($name);
    }

     /**
     * Initiates a transaction.
     * @return bool True on success, false on failure.
     */
    public function beginTransaction(): bool {
        if ($this->conn->inTransaction()) {
            $this->logger->warning("beginTransaction() called when already in transaction.");
            return false; // Or throw exception?
        }
        $this->logger->debug("Beginning database transaction.");
        return $this->conn->beginTransaction();
    }

    /**
     * Commits the current transaction.
     * @return bool True on success, false on failure.
     */
    public function commit(): bool {
        if (!$this->conn->inTransaction()) {
            $this->logger->warning("commit() called with no active transaction.");
            return false;
        }
        $this->logger->debug("Committing database transaction.");
        return $this->conn->commit();
    }

    /**
     * Rolls back the current transaction.
     * @return bool True on success, false on failure.
     */
    public function rollBack(): bool {
        if (!$this->conn->inTransaction()) {
            $this->logger->warning("rollBack() called with no active transaction.");
            return false;
        }
        $this->logger->warning("Rolling back database transaction."); // Warning level as it often indicates an error state
        return $this->conn->rollBack();
    }

     /**
     * Checks if inside a transaction.
     * @return bool True if a transaction is active, false otherwise.
     */
    public function inTransaction(): bool {
        return $this->conn->inTransaction();
    }

} // End Database class
