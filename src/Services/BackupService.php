<?php

namespace App\Services;

use PDO;
use Monolog\Logger;
use Exception;
use Throwable;
use FilesystemIterator;
use RuntimeException;
// Import spatie/db-dumper classes
use Spatie\DbDumper\Databases\MySql; // Or PostgreSql, Sqlite
use Spatie\DbDumper\Exceptions\DumpFailed;
use Spatie\DbDumper\Exceptions\CannotStartDump;
use wapmorgan\UnifiedArchive\UnifiedArchive;

/**
 * BackupService class using spatie/db-dumper for reliable backups.
 */
class BackupService {

    private PDO $db; // Still needed for potential direct operations or config reading
    private Logger $logger;
    private array $config;
    private string $backupPath;
    private array $dbConfig; // Store DB config for dumper
    private $rootDir;
    private $backupDir;

    /**
     * Constructor.
     *
     * @param PDO $db Database connection instance.
     * @param Logger $logger Logger instance.
     * @param array $config Application configuration array.
     */
    public function __construct(PDO $db, Logger $logger, array $config, $rootDir, $backupDir) {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $config;
        $this->dbConfig = [
            'host' => $this->config['database']['host'],
            'username' => $this->config['database']['username'],
            'password' => $this->config['database']['password'],
            'database' => $this->config['database']['database'],
        ];

        // Standardize and validate backup path
        $configuredPath = $this->config['paths']['backups'] ?? ROOT_PATH . DIRECTORY_SEPARATOR . 'backups';
        // Normalize separators and resolve relative paths
        $this->backupPath = realpath($configuredPath) ?: $configuredPath;
        // If realpath fails (path doesn't exist yet), attempt to create it using the original path
        if (!is_dir($this->backupPath)) {
            $this->backupPath = $configuredPath; // Use original path for creation attempt
        }

        $this->ensureBackupDirectoryExists();
        $this->logger->debug("BackupService initialized.", ['backup_path' => $this->backupPath]);

        $this->rootDir = $rootDir;
        $this->backupDir = $backupDir;
    }

    /**
     * Ensures the backup directory exists and is writable.
     * (Implementation remains the same as previous refined version)
     */
    private function ensureBackupDirectoryExists(): void {
        if (!is_dir($this->backupPath)) {
            $this->logger->info("Backup directory does not exist, attempting to create.", ['path' => $this->backupPath]);
            if (!@mkdir($this->backupPath, 0775, true) && !is_dir($this->backupPath)) {
                 $error = error_get_last();
                $this->logger->error("Failed to create backup directory.", ['path' => $this->backupPath, 'error' => $error['message'] ?? 'Unknown error']);
                throw new RuntimeException("Failed to create backup directory: " . ($error['message'] ?? $this->backupPath));
            }
            @chmod($this->backupPath, 0775);
            $this->logger->info("Backup directory created.", ['path' => $this->backupPath]);
        } elseif (!is_writable($this->backupPath)) {
            $this->logger->error("Backup directory is not writable.", ['path' => $this->backupPath]);
            throw new RuntimeException("Backup directory is not writable: " . $this->backupPath);
        }
         if (!is_readable($this->backupPath)) {
             $this->logger->error("Backup directory is not readable.", ['path' => $this->backupPath]);
             throw new RuntimeException("Backup directory is not readable: " . $this->backupPath);
         }
    }

    /**
     * Runs a manual database backup operation using spatie/db-dumper.
     *
     * @return string The full path to the created backup file.
     * @throws DumpFailed If the dump process fails.
     * @throws CannotStartDump If the dumper cannot be started (e.g., tool not found).
     * @throws RuntimeException If directory issues prevent backup.
     * @throws Exception For other unexpected errors.
     */
    public function runBackup(): string {
        $this->logger->info("Attempting to run manual database backup using spatie/db-dumper.");
        $this->ensureBackupDirectoryExists();

        $backupFileName = 'backup_' . ($this->dbConfig['database'] ?? 'db') . '_' . date('Ymd_His') . '.sql';
        $backupFilePath = $this->backupPath . DIRECTORY_SEPARATOR . $backupFileName;

        try {
            // Configure the dumper based on your database type (MySQL example)
            $dumper = MySql::create()
                ->setDbName($this->dbConfig['database'])
                ->setUserName($this->dbConfig['username'])
                ->setPassword($this->dbConfig['password']) // spatie/db-dumper handles this more securely
                ->setHost($this->dbConfig['host'] ?? 'localhost')
                ->setPort($this->dbConfig['port'] ?? 3306);

            // Optional: Add extra options like excluding tables, adding drop table statements etc.
            // $dumper->excludeTables(['logs', 'sessions']);
            // $dumper->addExtraOption('--skip-lock-tables'); // Example extra option
            // $dumper->doNotUseColumnStatistics(); // For specific MySQL versions/configs

            $this->logger->debug("Starting database dump.", ['target_file' => $backupFilePath]);

            // Perform the dump
            $dumper->dumpToFile($backupFilePath);

            // Verification (optional but recommended)
            clearstatcache();
            if (!file_exists($backupFilePath) || filesize($backupFilePath) < 10) { // Check if file exists and is not practically empty
                $this->logger->error("Backup file was not created or is empty after dump.", ['file' => $backupFilePath]);
                throw new DumpFailed("Backup file appears empty or was not created.");
            }

            $this->logger->info("Database backup created successfully.", ['file' => $backupFilePath, 'size_bytes' => filesize($backupFilePath)]);
            return $backupFilePath;

        } catch (CannotStartDump | DumpFailed $e) {
            // Catch specific dumper exceptions
            $this->logger->error("Database dump failed: " . $e->getMessage(), ['exception' => $e]);
            // Clean up potentially incomplete file
            if (file_exists($backupFilePath)) {
                @unlink($backupFilePath);
            }
            throw $e; // Rethrow the specific exception
        } catch (Throwable $e) {
            // Catch any other unexpected errors
            $this->logger->error("Unexpected error during backup process: " . $e->getMessage(), ['exception' => $e]);
            if (file_exists($backupFilePath)) {
                @unlink($backupFilePath);
            }
            throw new Exception("An unexpected error occurred during backup.", 0, $e);
        }
    }

    /**
     * Lists available backup files.
     * (Implementation remains the same as previous refined version)
     *
     * @return array An array of backup file details ['name' => string, 'size' => int, 'modified' => int]. Sorted newest first.
     * @throws RuntimeException If the backup directory is not readable.
     */
    public function listBackups(): array {
        $this->logger->debug("Listing backup files.", ['path' => $this->backupPath]);
        $this->ensureBackupDirectoryExists();

        $files = [];
        try {
            $iterator = new FilesystemIterator($this->backupPath, FilesystemIterator::SKIP_DOTS);
            $validBackupPattern = '/^backup-.+\.(sql|tar|tar\.gz)$/i'; // Pattern for final backups
            foreach ($iterator as $fileinfo) {
                $filename = strtolower($fileinfo->getFilename());
                // Check if it's a file and matches the pattern for final backups
                if ($fileinfo->isFile() && preg_match($validBackupPattern, $fileinfo->getFilename())) {
                    $files[] = [
                        'name' => $fileinfo->getFilename(),
                        'size' => $fileinfo->getSize(),
                        'modified' => $fileinfo->getMTime(),
                    ];
                } else if ($fileinfo->isFile()) {
                    $this->logger->debug("Skipping file in backup listing (does not match pattern).", ['filename' => $fileinfo->getFilename()]);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error("Error iterating backup directory.", ['path' => $this->backupPath, 'exception' => $e]);
            return [];
        }

        usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);
        $this->logger->info("Found " . count($files) . " valid backup files matching pattern.", ["pattern" => $validBackupPattern]);
        return $files;
    }

    /**
     * Restores the database from a specified backup file.
     * **WARNING: Placeholder. Requires real implementation, potentially using mysql client or PHP processing.**
     * Restoring via PHP is complex for large files. Using the `mysql` command line client (if available)
     * via a secure method (like spatie/db-dumper's underlying mechanism, if it supports restore) is preferred.
     *
     * @param string $fileName The name of the backup file (without path).
     * @return bool True if the restore was successful.
     * @throws Exception If restore fails or is not implemented.
     */
    public function restoreBackup(string $fileName): bool {
        $this->logger->warning("Attempting database restore. THIS IS A DESTRUCTIVE OPERATION!", ['file' => $fileName]);
        $this->ensureBackupDirectoryExists();

        // Validate filename (Accept .sql, .tar, and .tar.gz)
        $validRestorePattern = '/^backup-.+\.(sql|tar|tar\.gz)$/i';
        if (basename($fileName) !== $fileName || !preg_match($validRestorePattern, $fileName)) {
             $this->logger->error("Invalid backup file name for restore.", ['filename' => $fileName, 'pattern' => $validRestorePattern]);
            throw new Exception("Invalid backup file name specified for restore.");
        }
        $backupFilePath = $this->backupPath . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($backupFilePath) || !is_readable($backupFilePath)) {
            throw new Exception("Backup file not found or not readable: " . $fileName);
        }

        $sqlFileToRestore = null;
        $tempDir = null;

        try {
            // Check if it's an archive file (.tar or .tar.gz)
            $isTarGz = strtolower(substr($fileName, -7)) === '.tar.gz';
            $isTar = strtolower(substr($fileName, -4)) === '.tar';

            if ($isTarGz || $isTar) {
                $this->logger->info("Backup is an archive file. Attempting to extract SQL file.", ['type' => $isTarGz ? 'tar.gz' : 'tar']);
                $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore_' . uniqid();
                if (!@mkdir($tempDir, 0777, true) && !is_dir($tempDir)) { // Use @ for suppression
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
                }

                try {
                    $archive = UnifiedArchive::open($backupFilePath);
                    if (!$archive) {
                        throw new Exception("Could not open the archive file: " . $fileName);
                    }
                    $archive->extract($tempDir);
                    $this->logger->debug("Archive extracted to temporary directory.", ['temp_dir' => $tempDir]);
                } catch (Throwable $e) {
                    $this->logger->error("Failed to extract archive.", ['file' => $fileName, 'exception' => $e]);
                    // Clean up potentially partially created temp dir
                    if (is_dir($tempDir)) { $this->recursiveDelete($tempDir); }
                    throw new Exception("Failed to extract backup archive: " . $e->getMessage(), 0, $e);
                }

                // Find the .sql file inside the extracted directory
                $extractedFiles = new FilesystemIterator($tempDir, FilesystemIterator::SKIP_DOTS);
                foreach ($extractedFiles as $fileinfo) {
                    if ($fileinfo->isFile() && strtolower($fileinfo->getExtension()) === 'sql') {
                        $sqlFileToRestore = $fileinfo->getRealPath();
                        $this->logger->info("SQL file found within archive.", ['sql_file' => $sqlFileToRestore]);
                        break;
                    }
                }

                if (!$sqlFileToRestore) {
                    throw new Exception("No .sql file found inside the tar.gz archive.");
                }
                $this->logger->warning("Restoring ONLY the database from the tar.gz archive. Application files are NOT restored automatically.");

            } else {
                // It's a .sql file directly
                $sqlFileToRestore = $backupFilePath;
                $this->logger->info("Backup is a direct .sql file.");
            }

            // --- Database Restore using PHP/PDO --- 
            $this->logger->info("Starting database restore process using PHP/PDO.", ['sql_file' => $sqlFileToRestore]);

            $fileHandle = null;
            try {
                // Disable foreign key checks
                $this->logger->debug("Disabling foreign key checks.");
                $this->db->exec('SET FOREIGN_KEY_CHECKS=0;');

                $fileHandle = @fopen($sqlFileToRestore, 'r');
                if (!$fileHandle) {
                    throw new Exception("Could not open SQL file for reading.");
                }

                $currentStatement = '';
                while (($line = fgets($fileHandle)) !== false) {
                    $trimmedLine = trim($line);

                    // Skip empty lines and simple comments
                    if (empty($trimmedLine) || strpos($trimmedLine, '--') === 0 || strpos($trimmedLine, '/*') === 0) {
                        continue;
                    }

                    $currentStatement .= $line;

                    // Check if the line ends with a semicolon (basic statement delimiter)
                    if (substr($trimmedLine, -1) === ';') {
                        try {
                            // Attempt to execute the statement
                            if (trim($currentStatement) !== '') {
                                $this->db->exec($currentStatement);
                            }
                        } catch (\PDOException $e) {
                            // Log the error and the failing statement (be careful with sensitive data in logs)
                            $this->logger->error("SQL statement execution failed during restore.", [
                                'error' => $e->getMessage(),
                                'statement_start' => substr(trim($currentStatement), 0, 100) // Log beginning of statement
                            ]);
                            // Don't close handle here, finally block will do it
                            throw new Exception("Failed to execute SQL statement during restore: " . $e->getMessage(), $e->getCode(), $e);
                        }
                        // Reset for the next statement
                        $currentStatement = '';
                    }
                }

                // Check if there's any leftover statement (without ending semicolon? unlikely with our backup format)
                if (!empty(trim($currentStatement))) {
                    $this->logger->warning("Potential leftover SQL statement found at the end of the file.", ['statement_start' => substr(trim($currentStatement), 0, 100)]);
                    // Optionally try to execute it or just log it
                    try {
                        $this->db->exec($currentStatement);
                    } catch (\PDOException $e) {
                         $this->logger->error("Execution of leftover SQL statement failed.", ['error' => $e->getMessage()]);
                         // Decide if this should throw an exception
                    }
                }

                $this->logger->info("Database restore completed successfully using PHP/PDO.", ['file' => $fileName]);
                return true;

            } catch (Throwable $e) {
                 // Log the primary error causing the restore to fail
                 $this->logger->error("Error during PHP/PDO database restore execution.", ['exception' => $e]);
                 // Rethrow exception after logging and cleanup attempt
                 throw new Exception("Restore failed during SQL execution: " . $e->getMessage(), 0, $e);
            } finally {
                // Ensure foreign key checks are re-enabled
                $this->logger->debug("Re-enabling foreign key checks.");
                $this->db->exec('SET FOREIGN_KEY_CHECKS=1;');

                 // Ensure file handle is closed
                 if (isset($fileHandle) && is_resource($fileHandle)) {
                    fclose($fileHandle);
                 }
            }
            // --- End PHP/PDO Restore ---

        } catch (Throwable $e) {
            // Clean up temporary directory in case of any error (including extraction errors)
            if ($tempDir && is_dir($tempDir)) {
                $this->recursiveDelete($tempDir);
                $this->logger->debug("Temporary restore directory cleaned up after error.", ['temp_dir' => $tempDir]);
            }
            $this->logger->error("Error during database restore process.", ['file' => $fileName, 'exception' => $e]);
            // Rethrow the exception to be caught by the controller
            throw new Exception("Restore failed: " . $e->getMessage(), 0, $e);
        }
    }

    // Helper function to recursively delete a directory
    private function recursiveDelete(string $dir): void {
        if (!is_dir($dir)) return;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath()); // Use @ to suppress errors if dir is not empty initially
            } else {
                @unlink($file->getRealPath()); // Use @ to suppress errors
            }
        }
        @rmdir($dir); // Use @ to suppress errors
    }

    /**
     * Helper function to recursively copy a directory.
     *
     * @param string $source Source directory path.
     * @param string $dest Destination directory path.
     */
    private function recursiveCopy(string $source, string $dest): void {
        if (!is_dir($dest)) {
            @mkdir($dest, 0777, true); // Create destination if it doesn't exist
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $destPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    @mkdir($destPath, 0777, true);
                }
            } else {
                if (!copy($item->getPathname(), $destPath)) {
                    $this->logger->warning("Failed to copy file during recursive copy.", ['source' => $item->getPathname(), 'dest' => $destPath]);
                    // Optionally throw an exception here if needed
                }
            }
        }
    }

    /**
     * Deletes a specified backup file.
     * (Implementation remains the same as previous refined version)
     *
     * @param string $fileName The name of the backup file to delete (without path).
     * @return bool True if deleted successfully.
     * @throws Exception If the file cannot be deleted.
     * @throws RuntimeException If directory issues prevent deletion.
     */
    public function deleteBackup(string $fileName): bool {
        $this->logger->info("Attempting to delete backup file.", ['file' => $fileName]);
        $this->ensureBackupDirectoryExists();

        // Updated Regex: Check if starts with 'backup-' and ends with '.sql', '.tar', or '.tar.gz'
        $validBackupPattern = '/^backup-.+\.(sql|tar|tar\.gz)$/i';
        if (basename($fileName) !== $fileName || !preg_match($validBackupPattern, $fileName)) {
            $this->logger->error("Invalid backup file name for deletion.", ['filename' => $fileName, 'pattern' => $validBackupPattern]);
            throw new Exception("Invalid backup file name specified.");
        }
        $backupFilePath = $this->backupPath . DIRECTORY_SEPARATOR . $fileName;

        if (!file_exists($backupFilePath)) {
            $this->logger->warning("Attempted to delete backup file that does not exist.", ['file' => $backupFilePath]);
            return false;
        }
        if (!is_writable($backupFilePath) || !is_writable($this->backupPath)) {
             $this->logger->error("Cannot delete backup file due to permissions.", ['file' => $backupFilePath, 'dir' => $this->backupPath]);
             throw new Exception("Cannot delete backup file due to permissions: " . $fileName);
        }

        if (@unlink($backupFilePath)) {
            $this->logger->info("Backup file deleted successfully.", ['file' => $backupFilePath]);
            return true;
        } else {
             $error = error_get_last();
            $this->logger->error("Failed to delete backup file.", ['file' => $backupFilePath, 'error' => $error['message'] ?? 'Unknown error']);
            throw new Exception("Failed to delete backup file: " . ($error['message'] ?? $fileName));
        }
    }

    /**
     * بکاپ دیتابیس به صورت فایل SQL
     */
    public function backupDatabase($backupFile)
    {
        $mysqli = new \mysqli(
            $this->dbConfig['host'],
            $this->dbConfig['username'],
            $this->dbConfig['password'],
            $this->dbConfig['database']
        );
        $mysqli->set_charset("utf8");
        $tables = [];
        $result = $mysqli->query("SHOW TABLES");
        while ($row = $result->fetch_row()) $tables[] = $row[0];

        $sql = "SET NAMES utf8;\n";
        foreach ($tables as $table) {
            $res = $mysqli->query("SELECT * FROM `$table`");
            $numFields = $res->field_count;

            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $row2 = $mysqli->query("SHOW CREATE TABLE `$table`")->fetch_row();
            $sql .= $row2[1] . ";\n\n";

            while ($row = $res->fetch_row()) {
                $sql .= "INSERT INTO `$table` VALUES(";
                for ($j = 0; $j < $numFields; $j++) {
                    $sql .= isset($row[$j]) ? "'" . $mysqli->real_escape_string($row[$j]) . "'" : "NULL";
                    if ($j < ($numFields - 1)) $sql .= ",";
                }
                $sql .= ");\n";
            }
            $sql .= "\n";
        }
        file_put_contents($backupFile, $sql);
        $mysqli->close();
    }

    /**
     * بکاپ کامل سامانه (دیتابیس + فایل‌ها) به صورت tar.gz
     */
    public function createFullBackup()
    {
        set_time_limit(300); // افزایش زمان اجرای اسکریپت به 5 دقیقه
        $date = date('Ymd-His');
        $dbBackupFileName = "db-{$date}.sql"; // فقط نام فایل
        $archiveFile = $this->backupDir . "/backup-{$date}.tar.gz";

        // --- Step 1: Backup Database using the manual backupDatabase method ---
        $dbBackupFileFullPath = $this->backupDir . DIRECTORY_SEPARATOR . $dbBackupFileName; // مسیر کامل برای ایجاد فایل
         try {
             $this->logger->info("Creating temporary database backup using manual method...");
             $this->backupDatabase($dbBackupFileFullPath); // ایجاد فایل SQL در مسیر کامل آن
             $this->logger->info("Temporary database backup created successfully using manual method.", ['file' => $dbBackupFileFullPath]);
         } catch (Throwable $e) {
              $this->logger->error("Database backup (manual method) failed during full backup process. Aborting archive creation.", ['exception' => $e]);
              // Clean up potentially created (but failed) db file?
              if ($dbBackupFileFullPath && file_exists($dbBackupFileFullPath)) @unlink($dbBackupFileFullPath);
              throw new Exception("Failed to create database backup (manual method), cannot proceed with full backup.", 0, $e);
         }

        // --- Step 2: Create TAR Archive using PharData (No Gzip compression) ---
        $archiveFileTar = $this->backupDir . DIRECTORY_SEPARATOR . "backup-{$date}.tar"; // Create .tar first
        $tempBackupDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_temp_' . uniqid(); // Create unique temp dir path
        $this->logger->info('Creating temporary directory for backup structure.', ['temp_dir' => $tempBackupDir]);

        if (!@mkdir($tempBackupDir, 0777, true) && !is_dir($tempBackupDir)) {
            $this->logger->error("Failed to create temporary backup directory.", ['path' => $tempBackupDir]);
            if ($dbBackupFileFullPath && file_exists($dbBackupFileFullPath)) @unlink($dbBackupFileFullPath);
            throw new RuntimeException(sprintf('Failed to create temporary directory "%s"', $tempBackupDir));
        }

        try {
            // --- Copy sources to temporary directory maintaining structure ---
            $this->logger->debug("Copying source directories to temporary location.");
            $directoriesToCopy = [
                'src' => $this->rootDir . DIRECTORY_SEPARATOR . 'src',
                'public' => $this->rootDir . DIRECTORY_SEPARATOR . 'public',
                'config' => $this->rootDir . DIRECTORY_SEPARATOR . 'config',
                // Add other root directories requested by user
                'logs' => $this->rootDir . DIRECTORY_SEPARATOR . 'logs',           // Adding logs as requested
                'sessions' => $this->rootDir . DIRECTORY_SEPARATOR . 'sessions',     // Adding sessions as requested
                'UpdateServer' => $this->rootDir . DIRECTORY_SEPARATOR . 'UpdateServer', // Adding UpdateServer
                'vendor' => $this->rootDir . DIRECTORY_SEPARATOR . 'vendor', // Keep vendor excluded for now
            ];
            foreach ($directoriesToCopy as $dirName => $sourceDir) {
                if (is_dir($sourceDir)) {
                    $this->recursiveCopy($sourceDir, $tempBackupDir . DIRECTORY_SEPARATOR . $dirName);
                } else {
                    $this->logger->warning("Source directory not found, skipping copy.", ['path' => $sourceDir]);
                }
            }

            $this->logger->debug("Copying individual files to temporary location.");
            $filesToCopy = [
                // Existing files
                basename($this->rootDir . DIRECTORY_SEPARATOR . '.env') => $this->rootDir . DIRECTORY_SEPARATOR . '.env',
                'version.txt' => $this->rootDir . DIRECTORY_SEPARATOR . 'version.txt',
                $dbBackupFileName => $dbBackupFileFullPath, // Copy the temp SQL file
                // Add other root files requested by user
                '.htaccess' => $this->rootDir . DIRECTORY_SEPARATOR . '.htaccess',
                'composer.json' => $this->rootDir . DIRECTORY_SEPARATOR . 'composer.json',
                'composer.lock' => $this->rootDir . DIRECTORY_SEPARATOR . 'composer.lock',
                'phinx.php' => $this->rootDir . DIRECTORY_SEPARATOR . 'phinx.php',
                'tree.txt' => $this->rootDir . DIRECTORY_SEPARATOR . 'tree.txt',
            ];
            foreach ($filesToCopy as $fileName => $sourcePath) {
                if (file_exists($sourcePath) && is_file($sourcePath)) {
                    if (!copy($sourcePath, $tempBackupDir . DIRECTORY_SEPARATOR . $fileName)) {
                        $this->logger->warning("Failed to copy individual file to temp directory.", ['source' => $sourcePath, 'target' => $tempBackupDir . DIRECTORY_SEPARATOR . $fileName]);
                        // Decide if this is critical - maybe throw exception?
                    }
                } else {
                    $this->logger->warning("Source file not found or is not a file, skipping copy.", ['path' => $sourcePath]);
                }
            }

            // --- Create archive FROM the temporary directory ---
            $this->logger->info('Start archiving files from temporary directory to TAR.', ['temp_dir' => $tempBackupDir, 'archive' => $archiveFileTar]);
            $phar = new \PharData($archiveFileTar); // Creates .tar
            $phar->buildFromDirectory($tempBackupDir);

            $this->logger->info('TAR archive created successfully from temporary directory.', ['archive' => $archiveFileTar]);

        } catch (Throwable $e) {
            $this->logger->error("Failed during backup process (copying or archiving).", ['exception' => $e]);
            // Clean up failed archive
            if (file_exists($archiveFileTar)) @unlink($archiveFileTar);
            throw new Exception("Failed to create backup archive file: " . $e->getMessage(), 0, $e);
        } finally {
            // --- Always clean up temporary directory and original temp DB file ---
            $this->logger->debug("Cleaning up temporary backup directory.", ['temp_dir' => $tempBackupDir]);
            if (is_dir($tempBackupDir)) {
                $this->recursiveDelete($tempBackupDir);
            }
             // --- Step 4: Clean up original temporary DB file (moved from earlier) ---
             if ($dbBackupFileFullPath && file_exists($dbBackupFileFullPath)) {
                 @unlink($dbBackupFileFullPath);
                 $this->logger->debug("Original temporary DB file cleaned up.", ['file' => $dbBackupFileFullPath]);
             }
        }

        // --- Step 3: Optional Gzip Compression (If TAR creation was fast enough) ---
        // We'll keep it as .tar for now to maximize speed.
        // If you want .tar.gz later and TAR is fast, uncomment this section.
        /*
        $this->logger->info('Compressing TAR archive to TAR.GZ...');
        try {
            $phar->compress(\Phar::GZ);
            $this->logger->info('TAR archive compressed successfully.', ['archive' => $archiveFileTar . '.gz']);
            @unlink($archiveFileTar); // Remove the uncompressed .tar
            $finalArchiveFile = $archiveFileTar . '.gz';
        } catch (Throwable $e) {
            $this->logger->error('Failed to compress TAR archive to GZ.', ['exception' => $e]);
            // Keep the uncompressed .tar as the backup
            $finalArchiveFile = $archiveFileTar;
            $this->logger->warning('Using uncompressed TAR as backup due to compression failure.');
        }
        */

        // For now, the final file is the .tar file
        $finalArchiveFile = $archiveFileTar;

        return $finalArchiveFile;
    }

    /**
     * Creates a full backup (database + files) and returns the path.
     * The controller is responsible for handling redirection after calling this.
     *
     * @return string The path to the created backup file.
     * @throws Exception if backup creation fails.
     */
    public function createBackup(): string
    {
        // Simply call the full backup method and return its result
        $filePath = $this->createFullBackup();
        return $filePath;
    }
}