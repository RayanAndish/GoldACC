<?php

namespace App\Services;

use Monolog\Logger;
use Exception;
use Throwable; // Catch errors and exceptions
use RuntimeException;

// Required dependencies
use App\Services\ApiClient;
use App\Services\BackupService;
use App\Services\LicenseService;
use App\Repositories\SettingsRepository;
use App\Repositories\UpdateHistoryRepository;
// For a real implementation, you'd likely need:
// use App\Services\FileManagerService; // Custom or library-based file manager
// use App\Services\MigrationService; // Service to handle DB schema changes
// use App\Services\BackupService; // To create backups before update

/**
 * UpdateService manages the application update process.
 * It checks for new versions via API, and (theoretically) downloads and applies updates.
 *
 * **WARNING:** The `applyUpdate` method is a **highly complex and critical placeholder**.
 *              A robust update mechanism requires careful planning, extensive testing,
 *              secure file handling, database migration management, and rollback capabilities.
 *              Implementing this requires significant effort beyond this placeholder.
 */
class UpdateService {

    private ApiClient $apiClient;
    private Logger $logger;
    private array $config;
    private string $versionFilePath; // Path to the file storing the current version
    private $settingsRepository;
    private $backupService;
    private $licenseService;
    private $rootDir;
    private UpdateHistoryRepository $updateHistoryRepository;

    // --- Dependencies for a real implementation (Placeholder) ---
    // private FileManagerService $fileManager;
    // private MigrationService $migrationService;
    // private BackupService $backupService;

    /**
     * Constructor.
     *
     * @param ApiClient $apiClient API client instance.
     * @param Logger $logger Logger instance.
     * @param array $config Application configuration array.
     * @param SettingsRepository $settingsRepository
     * @param BackupService $backupService
     * @param LicenseService $licenseService
     * @param string $rootDir
     * @param UpdateHistoryRepository $updateHistoryRepository
     * // @param FileManagerService $fileManager
     * // @param MigrationService $migrationService
     * // @param BackupService $backupService
     */
    public function __construct(
        ApiClient $apiClient,
        Logger $logger,
        array $config,
        SettingsRepository $settingsRepository,
        BackupService $backupService,
        LicenseService $licenseService,
        string $rootDir,
        UpdateHistoryRepository $updateHistoryRepository
        // FileManagerService $fileManager,
        // MigrationService $migrationService,
        // BackupService $backupService
    ) {
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        $this->config = $config;
        // Define path to version file (adjust as needed)
        $this->versionFilePath = $this->config['paths']['root'] . '/version.txt';
        $this->settingsRepository = $settingsRepository;
        $this->backupService = $backupService;
        $this->licenseService = $licenseService;
        $this->rootDir = $rootDir;
        $this->updateHistoryRepository = $updateHistoryRepository;
        // $this->fileManager = $fileManager;
        // $this->migrationService = $migrationService;
        // $this->backupService = $backupService;
        $this->logger->debug("UpdateService initialized.");
    }

    /**
     * Gets the currently installed application version.
     * Reads from a dedicated version file for reliability.
     *
     * @return string The current application version (e.g., '1.0.1') or a default ('0.0.0').
     */
    public function getCurrentVersion(): string {
        try {
            if (file_exists($this->versionFilePath) && is_readable($this->versionFilePath)) {
                $version = trim(file_get_contents($this->versionFilePath));
                // Basic validation of version format (e.g., Semantic Versioning)
                if (preg_match('/^\d+(\.\d+)+([-.].+)?$/', $version)) {
                    return $version;
                } else {
                    $this->logger->warning("Version file found but content is invalid.", ['path' => $this->versionFilePath, 'content' => $version]);
                }
            } else {
                 $this->logger->warning("Version file not found or not readable.", ['path' => $this->versionFilePath]);
            }
        } catch (Throwable $e) {
             $this->logger->error("Error reading version file.", ['path' => $this->versionFilePath, 'exception' => $e]);
        }
        // Fallback or default version if file reading fails
        return $this->config['app']['version'] ?? '0.0.0'; // Read from config as last resort, or return '0.0.0'
    }

    /**
     * Updates the version file with the new version number.
     * Should be called after a successful update.
     *
     * @param string $newVersion The new version string.
     * @return bool True on success, false on failure.
     */
    private function updateVersionFile(string $newVersion): bool {
         $this->logger->info("Updating version file.", ['path' => $this->versionFilePath, 'new_version' => $newVersion]);
         try {
            // Validate format before writing
            if (!preg_match('/^\d+(\.\d+)+([-.].+)?$/', $newVersion)) {
                 $this->logger->error("Attempted to write invalid version format to file.", ['version' => $newVersion]);
                 return false;
            }
             if (@file_put_contents($this->versionFilePath, trim($newVersion)) === false) {
                 $this->logger->error("Failed to write to version file.", ['path' => $this->versionFilePath]);
                 return false;
             }
             return true;
         } catch (Throwable $e) {
              $this->logger->error("Error writing to version file.", ['path' => $this->versionFilePath, 'exception' => $e]);
              return false;
         }
    }


    /**
     * Checks the central server for available updates based on the current version.
     *
     * @return array|null An array with update details ('latest_version', 'description', 'download_url', etc.)
     *                    if an update is available, otherwise null.
     * @throws Exception If communication with the update server fails.
     */
    public function checkForUpdate(): ?array {
        // کنترل لایسنس
        if (!$this->licenseService->isLicenseValid()) {
            return ['update_available' => false, 'error' => 'لایسنس معتبر نیست.'];
        }
        $updateServerUrl = $this->settingsRepository->get('update_server_url');
        if (!$updateServerUrl) return ['update_available' => false, 'error' => 'آدرس سرور به‌روزرسانی تنظیم نشده است.'];
        $response = @file_get_contents($updateServerUrl . '?action=latest');
        if (!$response) return ['update_available' => false, 'error' => 'ارتباط با سرور به‌روزرسانی برقرار نشد.'];
        $data = json_decode($response, true);
        $currentVersion = $this->getCurrentVersion();
        if (isset($data['latest_version']) && version_compare($data['latest_version'], $currentVersion, '>')) {
            return [
                'update_available' => true,
                'latest_version' => $data['latest_version'],
                'changelog' => $data['changelog'] ?? '',
                'download_url' => $data['download_url'] ?? ''
            ];
        }
        return ['update_available' => false];
    }

    /**
     * Applies an update using the provided update information (e.g., download URL).
     * **WARNING: THIS IS A CRITICAL PLACEHOLDER! DO NOT USE IN PRODUCTION WITHOUT A ROBUST IMPLEMENTATION.**
     * A real implementation needs: secure download, signature/checksum verification, atomic file replacement,
     * database migrations, backup/rollback strategy, proper permissions handling, and extensive testing.
     *
     * @param array $updateInfo Information about the update (e.g., 'download_url', 'latest_version', 'checksum', 'migrations').
     * @return bool True if the update was successfully applied (according to placeholder).
     * @throws Exception If any step of the update process fails.
     */
    public function applyUpdate(array $updateInfo): bool {
        // کنترل لایسنس
        if (!$this->licenseService->isLicenseValid()) {
            throw new Exception('لایسنس معتبر نیست.');
        }

        $maintenanceFilePath = $this->rootDir . '/.maintenance';
        $updateSuccessful = false; // Flag to track success

        try {
            // --- Activate Maintenance Mode ---
            $this->logger->info('Activating maintenance mode...');
            if (@file_put_contents($maintenanceFilePath, 'Updating to version ' . ($updateInfo['latest_version'] ?? 'N/A') . ' at ' . date('c')) === false) {
                throw new Exception('Failed to activate maintenance mode. Check file permissions for root directory.');
            }

        $latestVersion = $updateInfo['latest_version'] ?? 'N/A';
        $this->logger->critical("Attempting to apply update (CRITICAL PLACEHOLDER EXECUTION!). Target Version: {$latestVersion}");

        // --- STAGE 0: Pre-checks ---
        $this->logger->info("Performing update pre-checks...");
        if (!isset($updateInfo['download_url'])) { throw new Exception("Update information missing download URL."); }
        if (!isset($updateInfo['latest_version'])) { throw new Exception("Update information missing target version."); }
        // Add checks for writability of root directory, temporary directory, sufficient disk space etc.
        // Add check for required PHP extensions (e.g., ZipArchive if using zip files)
        $this->logger->info("Pre-checks passed (Placeholder).");


        // --- STAGE 1: Backup ---
        $this->logger->info("Creating pre-update backup (Placeholder)...");
            $backupFile = null; // Initialize backup file variable
            try {
                $backupFile = $this->backupService->createFullBackup('update-' . date('Ymd-His'));
                $this->logger->info("Pre-update backup created.", ['file' => $backupFile]);
            } catch (Throwable $e) {
                $this->logger->error("Backup creation failed.", ['exception' => $e]);
                throw new Exception("Update aborted: Backup creation failed.", 0, $e); // Re-throw to stop update
            }


        // --- STAGE 2: Download & Verify ---
        $this->logger->info("Downloading update package (Placeholder)...");
        $downloadUrl = $updateInfo['download_url'];
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'app_update_' . uniqid();
            if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
                throw new RuntimeException(sprintf('Failed to create temporary update directory: %s', $tempDir));
            }
            $updateFilePath = $tempDir . DIRECTORY_SEPARATOR . 'update.zip';

            $this->logger->debug("Downloading update from URL.", ['url' => $downloadUrl, 'target_path' => $updateFilePath]);

            try {
                $ch = curl_init($downloadUrl);
                if ($ch === false) throw new Exception('Failed to initialize cURL for download.');

                $fp = fopen($updateFilePath, 'wb');
                if ($fp === false) throw new Exception('Failed to open temporary file for writing: ' . $updateFilePath);

                curl_setopt($ch, CURLOPT_FILE, $fp); // Write response directly to file
                curl_setopt($ch, CURLOPT_HEADER, 0); // Exclude header from output
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
                curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Set timeout (e.g., 5 minutes)
                curl_setopt($ch, CURLOPT_FAILONERROR, true); // Fail on HTTP errors >= 400
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verify SSL certificate
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);

                curl_close($ch);
                fclose($fp);

                if ($result === false) {
                    @unlink($updateFilePath); // Clean up partial file
                    throw new Exception("cURL download failed. Code: {$httpCode}. Error: {$curlError}");
                }

                 $this->logger->info("Update package downloaded successfully.", ['path' => $updateFilePath, 'size' => filesize($updateFilePath)]);

            } catch (Throwable $e) {
                if (isset($fp) && $fp) fclose($fp); // Ensure file handle is closed
                if (isset($ch) && $ch) curl_close($ch); // Ensure curl handle is closed
                if (file_exists($updateFilePath)) @unlink($updateFilePath); // Clean up
                if (is_dir($tempDir)) @rmdir($tempDir); // Clean up temp dir if empty
                $this->logger->error("Failed to download update package.", ['exception' => $e]);
                 throw new Exception("Update aborted: Failed to download package. " . $e->getMessage(), 0, $e);
            }

            // --- Checksum Verification ---
            $expectedChecksum = $updateInfo['checksum'] ?? null;
            if ($expectedChecksum) {
                $this->logger->info("Verifying checksum...");
                $actualChecksum = hash_file('sha256', $updateFilePath);
                $this->logger->debug("Checksum comparison.", ['expected' => $expectedChecksum, 'actual' => $actualChecksum]);
                if ($actualChecksum === false) {
                    throw new Exception("Failed to calculate checksum of downloaded file.");
                }
                if (!hash_equals($expectedChecksum, $actualChecksum)) {
                    @unlink($updateFilePath);
                    if (is_dir($tempDir)) @rmdir($tempDir);
                    throw new Exception("Checksum verification failed. The downloaded file might be corrupt or tampered with.");
                }
                $this->logger->info("Checksum verified successfully.");
            } else {
                $this->logger->warning("Checksum not provided in update information. Skipping verification. This is NOT recommended.");
            }


        // --- STAGE 3: Extract ---
        $this->logger->info("Extracting update package (Placeholder)...");
        $extractPath = $tempDir . DIRECTORY_SEPARATOR . 'extracted';
            if (!mkdir($extractPath, 0700, true) && !is_dir($extractPath)) {
                throw new RuntimeException(sprintf('Failed to create temporary extraction directory: %s', $extractPath));
            }

            $this->logger->debug("Extracting update ZIP file.", ['source' => $updateFilePath, 'target' => $extractPath]);

            $zip = new \ZipArchive();
            $res = $zip->open($updateFilePath);

            if ($res !== TRUE) {
                // Clean up before throwing
                @unlink($updateFilePath);
                $this->recursiveDelete($tempDir); // Use the existing helper
                throw new Exception("Failed to open downloaded ZIP archive. Error code: " . $res);
            }

            // Extract the archive
            if (!$zip->extractTo($extractPath)) {
                $zip->close(); // Close handle first
                // Clean up
                @unlink($updateFilePath);
                $this->recursiveDelete($tempDir);
                throw new Exception("Failed to extract ZIP archive to: " . $extractPath);
            }

            $extractedFileCount = $zip->numFiles;
            $zip->close();

            // Optional: Delete the zip file after successful extraction to save space
            @unlink($updateFilePath);

            $this->logger->info("Update package extracted successfully.", ['extracted_files' => $extractedFileCount, 'path' => $extractPath]);


        // --- STAGE 4: Apply Database Migrations ---
        $this->logger->info("Applying database migrations (Placeholder)...");

            // Define the path to the migrations extracted from the update package
            $migrationPath = $extractPath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';

            // Check if the migrations directory exists in the extracted package
            if (is_dir($migrationPath)) {
                $this->logger->info("Migrations directory found in update package. Attempting to run migrations.", ['path' => $migrationPath]);

                try {
                    // We need to load the phinx config file
                    $phinxConfigFile = $this->rootDir . DIRECTORY_SEPARATOR . 'phinx.php';
                    if (!file_exists($phinxConfigFile)) {
                        throw new Exception('Phinx configuration file not found: phinx.php');
                    }

                    // Use TextWrapper to run Phinx commands programmatically
                    // Make sure Composer's autoloader has access to Phinx classes
                    require_once $this->rootDir . '/vendor/autoload.php';

                    $output = new \Symfony\Component\Console\Output\BufferedOutput();
                    $phinx = new \Phinx\Wrapper\TextWrapper(new \Phinx\Console\PhinxApplication(), [], $output);

                    // Run the migrate command
                    // Specify the environment (e.g., 'production') and the config file
                    // Importantly, override the migration path to use the EXTRACTED path
                    $callResult = $phinx->getMigrate('production', $phinxConfigFile, false, $migrationPath);

                    $outputText = $output->fetch();

                    // Check the result code (0 means success)
                    if ($callResult !== 0) {
                        $this->logger->error("Phinx migration failed.", ['exit_code' => $callResult, 'output' => $outputText]);
                        // ** CRITICAL: Trigger Rollback Here **
                        $this->rollback($backupFile);
                        throw new Exception("Database migration failed. See logs for details. Output: " . $outputText);
                    } else {
                        $this->logger->info("Phinx migrations applied successfully.", ['output' => $outputText]);
                    }

                } catch (Throwable $e) {
                    $this->logger->error("Error during Phinx migration process.", ['exception' => $e]);
                    // ** CRITICAL: Trigger Rollback Here **
                    $this->rollback($backupFile);
                    throw new Exception("Update aborted: Database migration process encountered an error.", 0, $e);
                }
            } else {
                $this->logger->info("No migrations directory found in the update package. Skipping migration step.");
            }


        // --- STAGE 5: Replace Application Files ---
        $this->logger->info("Replacing application files (Placeholder)...");

            try {
                $this->logger->debug("Starting synchronization of extracted files to root directory.", [
                    'source' => $extractPath,
                    'destination' => $this->rootDir
                ]);

                $this->syncDirectories($extractPath, $this->rootDir);

                $this->logger->info("Application files replaced successfully.");

                // --- Clear Cache ---
                $this->logger->info("Clearing OpCache (if possible)...");
                if (function_exists('opcache_reset')) {
                    if (@opcache_reset()) {
                        $this->logger->info("OpCache reset successfully.");
                    } else {
                        $this->logger->warning("opcache_reset() called but failed. Check OpCache configuration and permissions.");
                    }
                } else {
                    $this->logger->info("OpCache is not available or opcache_reset function does not exist.");
                }
                // Note: Autoloader cache (like composer's) cannot be reliably cleared from PHP alone without shell access.
                // It usually rebuilds on the next request if needed.

            } catch (Throwable $e) {
                $this->logger->error("Failed to replace application files.", ['exception' => $e]);
                // ** CRITICAL: Trigger Rollback Here **
                $this->rollback($backupFile);
                throw new Exception("Update aborted: Failed during application file replacement.", 0, $e);
            }


        // --- STAGE 6: Finalize ---
        $this->logger->info("Finalizing update (Placeholder)...");
        // Update the version file
            $newVersion = $updateInfo['latest_version'];
            if (!$this->updateVersionFile($newVersion)) {
            // Log error but might not need to abort the whole update if files/DB are done.
             $this->logger->error("Failed to update version file after update application.");
                 // Consider if this failure warrants a full rollback - depends on severity
            }
            
            // Clean up temporary download/extraction directory
            $this->logger->info("Cleaning up temporary update directory.", ['path' => $tempDir]);
            $this->recursiveDelete($tempDir); // Use helper function

            // Clean up temporary files
        // $this->fileManager->deleteDirectory($tempDir);
        // $this->logger->info("Temporary update files cleaned up.");


        // --- If all steps were successful (in a real implementation) ---
            $this->logger->critical("Update to version {$newVersion} applied successfully.");
            $this->logUpdateHistory($newVersion, 'Success', 'Update completed successfully.');
            $updateSuccessful = true; // Mark as successful
        // return true;


        } catch (Throwable $e) {
            // Log the error that stopped the update
            $this->logger->error("Update process failed.", ['exception' => $e, 'target_version' => ($updateInfo['latest_version'] ?? 'N/A')]);
            // Log failure to history
            $this->logUpdateHistory(($updateInfo['latest_version'] ?? 'N/A'), 'Failed', 'Error: ' . $e->getMessage());

            // ** Placeholder for Rollback **
            // $this->rollback($backupFile); // Attempt rollback if backup file exists

            // Rethrow the exception so the controller knows it failed
            throw $e;
        } finally {
            // --- Deactivate Maintenance Mode ---
            // This runs whether the try block succeeded or failed
            $this->logger->info('Deactivating maintenance mode...');
            if (file_exists($maintenanceFilePath)) {
                if (!@unlink($maintenanceFilePath)) {
                    $this->logger->error('Failed to automatically deactivate maintenance mode. Manual removal of .maintenance file might be required.');
                    // Potentially send an alert here
                }
            }
        }

        // --- Placeholder return ---
        // $this->logger->error("UpdateService::applyUpdate requires a proper implementation.");
        // throw new Exception("Update functionality is not fully implemented.");
        // return false; // Indicate failure for placeholder
        if (!$updateSuccessful) {
            // This part should ideally not be reached if exceptions are handled correctly
            $this->logger->error("UpdateService::applyUpdate reached end without success or exception. Update likely incomplete.");
             throw new Exception("Update functionality is not fully implemented or failed silently.");
        }
        return true; // Return true only if $updateSuccessful was set
    }

    /**
     * Attempts to roll back the system to a previous state using a backup file.
     *
     * @param string|null $backupFilePath Full path to the .tar.gz backup file created before the update attempt.
     */
    private function rollback(?string $backupFilePath): void {
        $this->logger->critical("!!! ATTEMPTING SYSTEM ROLLBACK !!!", ['backup_file' => $backupFilePath]);

        if (empty($backupFilePath) || !file_exists($backupFilePath)) {
            $this->logger->error("Rollback failed: Backup file path is missing or file does not exist.", ['path' => $backupFilePath]);
            // Cannot proceed without a valid backup
            return;
        }

        $rollbackTempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'app_rollback_' . uniqid();

        try {
            // --- Step 1: Extract Backup Archive ---
            $this->logger->info("Rollback Step 1: Extracting backup archive.", ['source' => $backupFilePath, 'target' => $rollbackTempDir]);
            if (!mkdir($rollbackTempDir, 0700, true) && !is_dir($rollbackTempDir)) {
                throw new RuntimeException(sprintf('Failed to create temporary rollback directory: %s', $rollbackTempDir));
            }

            $archive = \wapmorgan\UnifiedArchive\UnifiedArchive::open($backupFilePath);
            if (!$archive) throw new Exception("Could not open backup archive: " . $backupFilePath);

            if (!$archive->extract($rollbackTempDir)) {
                 throw new Exception("Failed to extract backup archive to: " . $rollbackTempDir);
            }
            $this->logger->info("Backup archive extracted for rollback.");

            // --- Step 2: Restore Database ---
            $this->logger->info("Rollback Step 2: Restoring database from backup.");
            // Assuming the backup archive name follows the pattern used in createFullBackup
            // We pass the ORIGINAL archive path to restoreBackup, as it handles extraction internally.
            if (!$this->backupService->restoreBackup(basename($backupFilePath))) {
                 throw new Exception("Database restore failed during rollback.");
            }
            $this->logger->info("Database restored successfully during rollback.");

            // --- Step 3: Restore Files ---
            // Sync the extracted backup content (excluding the temporary DB file if needed)
            // back to the application root directory.
            $this->logger->info("Rollback Step 3: Restoring application files from backup.", ['source' => $rollbackTempDir, 'destination' => $this->rootDir]);

            // Identify the SQL file within the extracted backup to avoid syncing it back
            $dbBackupFilePattern = 'db-*.sql'; // Pattern used in createFullBackup
            $sqlFilePathInBackup = null;
            $iterator = new \DirectoryIterator($rollbackTempDir);
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isDot() && !$fileInfo->isDir() && fnmatch($dbBackupFilePattern, $fileInfo->getFilename())) {
                    $sqlFilePathInBackup = $fileInfo->getRealPath();
                    $this->logger->debug("Found SQL file in extracted backup, will exclude from file sync.", ['path' => $sqlFilePathInBackup]);
                    break;
                }
            }
            // Temporarily move the SQL file out if found, so syncDirectories doesn't copy it.
            $movedSqlPath = null;
            if($sqlFilePathInBackup && file_exists($sqlFilePathInBackup)){
                $movedSqlPath = $rollbackTempDir . DIRECTORY_SEPARATOR . '../' . basename($sqlFilePathInBackup) . '.moved';
                 if(!rename($sqlFilePathInBackup, $movedSqlPath)){
                     $this->logger->warning("Could not move SQL file out of extracted backup before sync.", ['source' => $sqlFilePathInBackup, 'target' => $movedSqlPath]);
                     $movedSqlPath = null; // Reset if rename failed
                 }
            }

            // Now sync the remaining files/directories from the extracted backup
            $this->syncDirectories($rollbackTempDir, $this->rootDir); // Sync restored files

            // Move the SQL file back if it was moved, then clean up
            if($movedSqlPath && file_exists($movedSqlPath)){
                rename($movedSqlPath, $sqlFilePathInBackup); // Move it back (best effort)
            }

            $this->logger->info("Application files restored successfully during rollback.");

            // --- Step 4: Clear Cache (Again) ---
            $this->logger->info("Rollback Step 4: Clearing OpCache again after restoring files.");
            if (function_exists('opcache_reset')) { @opcache_reset(); }

            $this->logger->critical("!!! SYSTEM ROLLBACK COMPLETED SUCCESSFULLY !!!");

        } catch (Throwable $e) {
            $this->logger->emergency("!!! CRITICAL ERROR DURING ROLLBACK PROCESS !!! System might be in an unstable state.", ['exception' => $e]);
            // At this point, manual intervention is likely required.
        } finally {
            // Clean up the temporary rollback directory
            if (isset($rollbackTempDir) && is_dir($rollbackTempDir)) {
                $this->logger->info("Cleaning up temporary rollback directory.", ['path' => $rollbackTempDir]);
                $this->recursiveDelete($rollbackTempDir);
            }
        }
    }

    /**
     * ثبت تاریخچه به‌روزرسانی
     */
    public function logUpdateHistory(string $version, string $status, string $log = null): bool {
        return $this->updateHistoryRepository->add($version, $status, $log);
    }

    /**
     * دریافت لیست تاریخچه به‌روزرسانی با صفحه‌بندی
     */
    public function getUpdateHistoryPaginated(int $limit, int $offset): array {
        return $this->updateHistoryRepository->getList($limit, $offset);
    }

    public function countUpdateHistory(): int {
        return $this->updateHistoryRepository->countAll();
    }

    /**
     * Recursively synchronizes the contents of a source directory to a destination directory.
     * Overwrites existing files in the destination.
     *
     * @param string $sourceDir The source directory path.
     * @param string $destDir The destination directory path.
     * @throws Exception If copying fails or directories cannot be read/created.
     */
    private function syncDirectories(string $sourceDir, string $destDir): void {
        $this->logger->debug("Syncing directory.", ['source' => $sourceDir, 'destination' => $destDir]);

        // Ensure trailing slashes are consistent (optional, but helps clarity)
        $sourceDir = rtrim($sourceDir, '/') . '/';
        $destDir = rtrim($destDir, '/') . '/';

        // Ensure destination directory exists
        if (!is_dir($destDir)) {
            if (!@mkdir($destDir, 0775, true) && !is_dir($destDir)) { // Use permissions suitable for web server
                throw new Exception("Failed to create destination directory: {$destDir}");
            }
        }

        $dir = opendir($sourceDir);
        if ($dir === false) {
            throw new Exception("Failed to open source directory for reading: {$sourceDir}");
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourceFile = $sourceDir . $file;
            $destFile = $destDir . $file;

            if (is_dir($sourceFile)) {
                // Recursively sync subdirectories
                $this->syncDirectories($sourceFile, $destFile);
            } else {
                // Copy file, overwriting the destination if it exists
                $this->logger->debug("Copying file.", ['from' => $sourceFile, 'to' => $destFile]);
                if (!@copy($sourceFile, $destFile)) {
                    $error = error_get_last();
                    closedir($dir); // Close handle before throwing
                    throw new Exception("Failed to copy file '{$sourceFile}' to '{$destFile}'. Error: " . ($error['message'] ?? 'Unknown'));
                }
                // Optional: Attempt to set permissions on the copied file if needed
                // @chmod($destFile, 0664);
            }
        }
        closedir($dir);
    }

} // End UpdateService class