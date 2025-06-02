<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions
use Exception; // For throwing general exceptions

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Dependencies (Likely only Helper needed)
use App\Utils\Helper;

/**
 * CodeProtectionController handles an internal tool for basic PHP file encoding (base64 + eval).
 *
 * ==========================================================================
 * === WARNING: THIS METHOD OF CODE PROTECTION IS INSECURE AND OBSOLETE ===
 * ==========================================================================
 * - base64 is easily reversible.
 * - `eval()` is a major security risk and performance killer.
 * - This provides **NO REAL PROTECTION** against determined individuals.
 * - Consider proper licensing mechanisms (LicenseService) or professional obfuscation tools
 *   (like IonCube, Zend Guard - though even they have limitations) if source code protection
 *   is a strict requirement, but focus on robust licensing first.
 * - **USE OF THIS FEATURE IS STRONGLY DISCOURAGED.**
 * ==========================================================================
 *
 * Inherits from AbstractController. Access restricted to Admins.
 */
class CodeProtectionController extends AbstractController {

    // No specific repository dependencies needed.

    /**
     * Constructor. Relies on parent.
     *
     * @param PDO $db
     * @param Logger $logger
     * @param array $config
     * @param ViewRenderer $viewRenderer
     * @param array $services
     */
    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
         $this->logger->warning("CodeProtectionController initialized. NOTE: This feature uses insecure methods (base64/eval) and is discouraged.");
    }

    /**
     * Displays the code 'encoding' management page.
     * Checks the status of potentially 'encoded' files.
     * Route: /app/code-protection (GET)
     */
    public function index(): void {
        // Access Control: Admins only
        $this->requireLogin();
        $this->requireAdmin(); // Ensure only admins can access

        $pageTitle = "مدیریت 'رمزنگاری' کد (ناامن)"; // Add warning to title
        $statusMessage = $this->getFlashMessage('protection_status'); // Get status from POST actions
        $encodedPath = ROOT_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'encoded'; // Use constant

        // Define sensitive files (should ideally be managed centrally in config)
        $sensitiveFiles = $this->config['code_protection']['sensitive_files'] ?? [
            'src/Core/License.php', // Example, adjust paths relative to ROOT_PATH
            'src/Services/LicenseService.php',
        ];
        $this->logger->debug("Sensitive files list for protection check.", ['files' => $sensitiveFiles]);


        // Check status of each sensitive file
        $fileStatuses = [];
        $allEncoded = !empty($sensitiveFiles); // Assume encoded unless proven otherwise
        foreach ($sensitiveFiles as $relativeFilePath) {
            $sourceFile = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFilePath);
            $encodedFile = $encodedPath . DIRECTORY_SEPARATOR . basename($relativeFilePath, '.php') . '.enc.php';
            $status = 'Source Missing';
            $isEncoded = false;
            if (file_exists($sourceFile)) {
                 $status = 'Source Only';
                 if (file_exists($encodedFile)) {
                     $status = 'Encoded';
                     $isEncoded = true;
                 } else {
                     $allEncoded = false; // At least one is not encoded
                 }
            } else {
                $allEncoded = false; // Source missing means not properly encoded/managed
                if (file_exists($encodedFile)) {
                    $status = 'Encoded (Source Missing!)'; // Warning state
                    $isEncoded = true; // Encoded file exists
                }
            }
            $fileStatuses[$relativeFilePath] = ['status' => $status, 'encoded_exists' => $isEncoded];
        }

        // Overall status based on individual files
        $isSystemEncoded = $allEncoded && !empty($sensitiveFiles);


        // Render the management view
        $this->render('system/code_protection', [
            'page_title'        => $pageTitle,
            'is_system_encoded' => $isSystemEncoded, // Overall status
            'file_statuses'     => $fileStatuses, // Status per file
            'sensitive_files'   => $sensitiveFiles, // Pass the list for iteration in view
            'encoded_path_display' => str_replace(ROOT_PATH, '[ROOT]', $encodedPath), // Show relative path
            'form_action'       => $this->config['app']['base_url'] . '/app/code-protection', // POST target
            'status_message'    => $statusMessage // Display result from POST action
        ]);
    }

    /**
     * Handles POST requests for encoding/decoding actions.
     * Returns JSON response.
     * Route: /app/code-protection (POST)
     */
    public function handlePostActions(): void {
        // Access Control & Request Method Check
        $this->requireLogin();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             http_response_code(405); // Method Not Allowed
             header('Content-Type: application/json');
             echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
             exit;
        }
        // TODO: Add CSRF token validation

        header('Content-Type: application/json'); // Set JSON header early
        $response = ['success' => false, 'message' => 'Invalid action.'];
        $action = $_POST['action'] ?? null;

        // Ensure encoded directory exists and is writable
        $encodedPath = ROOT_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'encoded';
        try {
            $this->ensureDirectory($encodedPath);
        } catch (RuntimeException $e) {
             $this->logger->critical("Code Protection Action Failed: Directory Error.", ['path' => $encodedPath, 'exception' => $e]);
             $response['message'] = "خطای سیستمی: مشکل در دسترسی به پوشه 'encoded'.";
             http_response_code(500);
             echo json_encode($response);
             exit;
        }

        // Get sensitive files list (ensure consistency)
        $sensitiveFiles = $this->config['code_protection']['sensitive_files'] ?? [];

        try {
            switch ($action) {
                case 'encode_file':
                case 'remove_encoded':
                    $relativeFilePath = $_POST['file'] ?? null;
                    if (!$relativeFilePath || !in_array($relativeFilePath, $sensitiveFiles)) {
                        throw new Exception('فایل نامعتبر یا غیرمجاز انتخاب شده است.');
                    }
                    if ($action === 'encode_file') {
                         $result = $this->encodeSingleFile($relativeFilePath, $encodedPath);
                    } else { // remove_encoded
                         $result = $this->removeSingleEncodedFile($relativeFilePath, $encodedPath);
                    }
                    $response = $result;
                    break;

                case 'encode_all':
                    $response = $this->encodeAllFiles($sensitiveFiles, $encodedPath);
                    break;

                case 'remove_all_encoded':
                    $response = $this->removeAllEncodedFiles($encodedPath);
                    break;

                default:
                    http_response_code(400); // Bad Request
                    // $response['message'] is already set to 'Invalid action.'
                    break;
            }
        } catch (Throwable $e) {
            $this->logger->error("Error during code protection action.", ['action' => $action, 'exception' => $e]);
            http_response_code(500); // Internal Server Error
            $response['message'] = 'خطای داخلی سرور: ' . Helper::escapeHtml($e->getMessage());
             if ($this->config['app']['debug']) {
                 $response['debug_detail'] = $e->getTraceAsString(); // Be cautious exposing trace
             }
        }

        // Set flash message for next page load (index) based on response
        // $this->setSessionMessage($response['message'], $response['success'] ? 'success' : 'danger', 'protection_status');

        echo json_encode($response);
        exit;
    }

    /** Helper to encode a single file */
    private function encodeSingleFile(string $relativeFilePath, string $encodedPath): array {
        $sourceFile = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFilePath);
        $encodedFile = $encodedPath . DIRECTORY_SEPARATOR . basename($relativeFilePath, '.php') . '.enc.php';

        if (!file_exists($sourceFile) || !is_readable($sourceFile)) {
             $this->logger->error("Cannot encode: Source file missing or not readable.", ['file' => $relativeFilePath]);
            throw new Exception("فایل منبع یافت نشد یا قابل خواندن نیست: " . $relativeFilePath);
        }

        $content = file_get_contents($sourceFile);
        if ($content === false) { throw new Exception("خطا در خواندن محتوای فایل منبع: " . $relativeFilePath); }

        // Basic base64 encoding - HIGHLY INSECURE
        $encodedContent = base64_encode($content);
        $wrapper = "<?php\n";
        // Add a basic check (won't stop execution if included directly elsewhere, only if ROOT_PATH is checked)
        $wrapper .= "// WARNING: INSECURE EVAL METHOD\n";
        $wrapper .= "if (!defined('ROOT_PATH')) die('Direct access forbidden.');\n";
        $wrapper .= "eval(base64_decode('" . $encodedContent . "'));\n";
        $wrapper .= "?>";

        if (@file_put_contents($encodedFile, $wrapper) === false) {
            $this->logger->error("Failed to write encoded file.", ['file' => $encodedFile]);
            throw new Exception("خطا در نوشتن فایل رمزنگاری شده برای: " . $relativeFilePath);
        }

        $this->logger->info("File encoded (insecurely).", ['file' => $relativeFilePath]);
        return ['success' => true, 'message' => "فایل '" . basename($relativeFilePath) . "' با موفقیت 'رمزنگاری' شد."];
    }

     /** Helper to remove a single encoded file */
    private function removeSingleEncodedFile(string $relativeFilePath, string $encodedPath): array {
        $encodedFile = $encodedPath . DIRECTORY_SEPARATOR . basename($relativeFilePath, '.php') . '.enc.php';

        if (!file_exists($encodedFile)) {
             $this->logger->info("Encoded file already removed or never existed.", ['file' => $relativeFilePath]);
            return ['success' => true, 'message' => "فایل 'رمزنگاری شده' برای '" . basename($relativeFilePath) . "' وجود نداشت."];
        }
        if (!is_writable($encodedFile)) {
              $this->logger->error("Cannot remove encoded file (not writable).", ['file' => $encodedFile]);
             throw new Exception("خطا در دسترسی برای حذف فایل 'رمزنگاری شده': " . basename($relativeFilePath));
        }

        if (@unlink($encodedFile)) {
            $this->logger->info("Encoded file removed.", ['file' => $relativeFilePath]);
            return ['success' => true, 'message' => "فایل 'رمزنگاری شده' برای '" . basename($relativeFilePath) . "' با موفقیت حذف شد."];
        } else {
            $this->logger->error("Failed to remove encoded file.", ['file' => $encodedFile]);
            throw new Exception("خطا در حذف فایل 'رمزنگاری شده': " . basename($relativeFilePath));
        }
    }

     /** Helper to encode all sensitive files */
    private function encodeAllFiles(array $sensitiveFiles, string $encodedPath): array {
         $this->logger->info("Attempting to encode all sensitive files.");
         $successCount = 0;
         $failCount = 0;
         $errors = [];
         foreach ($sensitiveFiles as $file) {
             try {
                 $this->encodeSingleFile($file, $encodedPath);
                 $successCount++;
             } catch (Throwable $e) {
                  $errors[] = basename($file) . ": " . $e->getMessage();
                  $failCount++;
             }
         }
         $message = "عملیات 'رمزنگاری' همه فایل‌ها انجام شد. موفق: {$successCount}, ناموفق: {$failCount}.";
         if ($failCount > 0) {
             $message .= " خطاها: " . implode('; ', $errors);
             $this->logger->warning("Encoding all files finished with errors.", ['errors' => $errors]);
         } else {
              $this->logger->info("Encoding all files finished successfully.");
         }
         return ['success' => ($failCount === 0), 'message' => $message];
    }

     /** Helper to remove all .enc.php files from the encoded path */
    private function removeAllEncodedFiles(string $encodedPath): array {
          $this->logger->info("Attempting to remove all encoded files.");
          $files = glob($encodedPath . DIRECTORY_SEPARATOR . "*.enc.php");
          if ($files === false) { // Error in glob
                $this->logger->error("Failed to scan encoded directory for removal.");
               throw new Exception("خطا در خواندن پوشه فایل‌های 'رمزنگاری شده'.");
          }
          $successCount = 0;
          $failCount = 0;
          $errors = [];
          if (empty($files)) {
               return ['success' => true, 'message' => "هیچ فایل 'رمزنگاری شده‌ای' برای حذف یافت نشد."];
          }

          foreach ($files as $file) {
              try {
                  if (!is_writable($file)) { throw new Exception('Permission denied.'); }
                  if (!@unlink($file)) { throw new Exception('unlink() failed.'); }
                  $successCount++;
              } catch (Throwable $e) {
                   $errors[] = basename($file) . ": " . $e->getMessage();
                   $failCount++;
                    $this->logger->warning("Failed to remove single encoded file during remove all.", ['file' => $file, 'exception' => $e]);
              }
          }
          $message = "عملیات حذف همه فایل‌های 'رمزنگاری شده' انجام شد. موفق: {$successCount}, ناموفق: {$failCount}.";
           if ($failCount > 0) {
               $message .= " خطاها: " . implode('; ', $errors);
               $this->logger->warning("Removing all encoded files finished with errors.", ['errors' => $errors]);
           } else {
                $this->logger->info("Removing all encoded files finished successfully.");
           }
           return ['success' => ($failCount === 0), 'message' => $message];
    }

     /** Helper to ensure a directory exists and is writable */
     private function ensureDirectory(string $path): void {
          if (!is_dir($path)) {
              if (!@mkdir($path, 0755, true) && !is_dir($path)) { // Use 0755 for directories
                  throw new RuntimeException("Failed to create directory: " . $path);
              }
              @chmod($path, 0755);
          } elseif (!is_writable($path)) {
               throw new RuntimeException("Directory is not writable: " . $path);
          }
     }

} // End CodeProtectionController class