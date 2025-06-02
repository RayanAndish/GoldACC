<?php

namespace App\Core;

use PDO; // Need to use the fully qualified name or import with 'use'
use PDOException;
use Exception;
use ErrorException; // For converting PHP errors
use Throwable; // Catches both Errors and Exceptions in PHP 7+
use Monolog\Logger;
use App\Controllers\ErrorController; // Dependency for displaying error pages
use FastRoute\Dispatcher\MethodNotAllowedException as FastRouteMethodNotAllowedException; // Alias for clarity
use Psr\Log\LogLevel;

/**
 * ErrorHandler class for centralized handling of PHP errors and exceptions.
 * Acts as a static class to register handlers and hold dependencies needed by ErrorController.
 */
class ErrorHandler {

    // Static properties to hold dependencies, set during initialization
    private static ?PDO $db = null;
    private static ?Logger $logger = null;
    private static bool $isDebug = false;
    private static ?array $config = null;
    private static ?ViewRenderer $viewRenderer = null;
    private static ?array $services = null; // Holds the services array

    // Flag to prevent recursive error handling
    private static bool $handlingError = false;

    /**
     * Initializes the ErrorHandler.
     * Sets up dependencies and registers PHP error/exception handlers.
     * Should be called once during application bootstrap AFTER all dependencies are ready.
     *
     * @param PDO|null $db PDO instance (nullable if DB connection failed earlier).
     * @param Logger $logger Monolog Logger instance.
     * @param bool $isDebug Application debug status.
     * @param array $config Full application configuration array.
     * @param ViewRenderer $viewRenderer ViewRenderer instance.
     * @param array $services Array containing application services.
     */
    public static function initialize(?PDO $db, Logger $logger, bool $isDebug, array $config, ViewRenderer $viewRenderer, array $services): void {
        self::$db = $db;
        self::$logger = $logger;
        self::$isDebug = $isDebug;
        self::$config = $config;
        self::$viewRenderer = $viewRenderer;
        self::$services = $services; // Store services

        // Set the handlers *after* dependencies are stored
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleFatalError']);

        self::$logger->debug("ErrorHandler initialized and handlers registered.");
    }

    /**
     * PHP Error Handler.
     * Converts relevant PHP errors (Warnings, Notices, etc.) into ErrorExceptions
     * to be caught by the exception handler. Logs the error.
     *
     * @param int $severity The error level.
     * @param string $message The error message.
     * @param string $file The file where the error occurred.
     * @param int $line The line number where the error occurred.
     * @return bool Returns true to prevent default PHP error handling if error is handled.
     * @throws ErrorException Converts specified errors into exceptions.
     */
        // Log the error regardless
         public static function handleError(int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return true;
            }
    
            // ---- استفاده از LogLevel صحیح ----
            $logLevel = match ($severity) {
                E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR => LogLevel::CRITICAL, // استفاده از ثابت
                E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => LogLevel::WARNING, // استفاده از ثابت
                E_NOTICE, E_USER_NOTICE, E_STRICT => LogLevel::NOTICE, // استفاده از ثابت
                E_DEPRECATED, E_USER_DEPRECATED => LogLevel::INFO, // استفاده از ثابت
                default => LogLevel::WARNING, // استفاده از ثابت
            };
            // ---- پایان استفاده از LogLevel صحیح ----
    
            self::$logger?->log($logLevel, "PHP Error: {$message}", [
                'severity' => $severity,
                'file' => $file,
                'line' => $line,
            ]);
    
            if ($severity & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)) {
                 throw new ErrorException($message, 0, $severity, $file, $line);
            }
            return true;
        }    
    /**
     * PHP Exception Handler.
     * Catches all uncaught exceptions and errors (Throwable).
     * Logs the exception and triggers the appropriate error page display via ErrorController.
     *
     * @param Throwable $exception The caught exception or error.
     */
    public static function handleException(Throwable $exception): void {
        // Prevent recursive error handling
        if (self::$handlingError) {
             error_log("Recursive error handling detected. Aborting. Original: " . $exception->getMessage());
             // Maybe try the final fallback directly?
             // self::finalFallbackErrorPage($exception); // Risky, could recurse again
             exit(1); // Hard exit
        }
        self::$handlingError = true;

        try {
            // Clean any existing output buffer
            if (ob_get_level() > 0 && ob_get_length() > 0) { // Only clean if there's content
                ob_end_clean();
            }

            // Log the exception
            self::$logger?->error("Uncaught Exception: " . $exception->getMessage(), [
                'exception' => get_class($exception),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString() // Include stack trace in log
            ]);

            // --- Display Error Page using ErrorController ---
            // Check if core dependencies needed by ErrorController are available
            if (self::$logger === null || self::$config === null || self::$viewRenderer === null || self::$services === null || self::$db === null /* DB needed by AbstractController */) {
                 self::$logger?->critical("Error handler dependencies missing! Cannot use ErrorController. Falling back.");
                 self::finalFallbackErrorPage($exception);
                 exit(1); // Ensure script stops
            }

            // Instantiate ErrorController with all required dependencies
            $errorController = new ErrorController(self::$db, self::$logger, self::$config, self::$viewRenderer, self::$services);

            // Determine appropriate HTTP status code
            $statusCode = 500; // Default: Internal Server Error
            if ($exception instanceof FastRouteMethodNotAllowedException) {
                $statusCode = 405;
            }
             // Check for Exception code being a valid HTTP status (4xx or 5xx)
            elseif (is_int($exception->getCode()) && $exception->getCode() >= 400 && $exception->getCode() < 600) {
                 $statusCode = $exception->getCode();
            }
            // Add checks for custom application exceptions here if needed
            // elseif ($exception instanceof \App\Exceptions\NotFoundException) { $statusCode = 404; }
            // elseif ($exception instanceof \App\Exceptions\AuthenticationException) { $statusCode = 401; }
            // elseif ($exception instanceof \App\Exceptions\AuthorizationException) { $statusCode = 403; }
            // elseif ($exception instanceof \App\Exceptions\ValidationException) { $statusCode = 422; }


            // Set HTTP response code before sending output
             if (!headers_sent()) {
                http_response_code($statusCode);
             }

            // Call the appropriate method on ErrorController
            if ($statusCode === 404) {
                 $errorController->showNotFound();
            } elseif ($statusCode === 405 && $exception instanceof FastRouteMethodNotAllowedException) {
                 $errorController->showMethodNotAllowed($exception->getAllowedMethods());
            } else {
                 // For 500 or other general errors
                 // Pass the exception object if debug mode is on
                 $errorController->showServerError(self::$isDebug ? $exception : null);
            }

        } catch (Throwable $e) {
            // OMG! An error occurred *while* handling the error!
            // Log this critical failure.
            error_log("FATAL: Exception occurred DURING exception handling: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            // Log the original exception too, if possible
            if ($exception) {
                error_log("Original Exception: " . $exception->getMessage());
            }
            // Display the ultimate fallback page.
            self::finalFallbackErrorPage($exception, $e);
        } finally {
             self::$handlingError = false; // Reset flag
        }

        exit(1); // Ensure script terminates after handling the exception
    }

    /**
     * PHP Shutdown Handler.
     * Checks for fatal errors that weren't caught by the error/exception handlers.
     */
    public static function handleFatalError(): void {
         // Prevent recursive error handling if shutdown is triggered by an handled error
        if (self::$handlingError) { return; }

        $lastError = error_get_last();

        // Check if a fatal error occurred
        if ($lastError !== null && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Prevent handling the same error twice if handleException was already called for it
            // This check might be needed depending on PHP version and error type
            // For simplicity, assume handleException didn't catch these fatal types.

             self::$handlingError = true; // Set flag for fatal error handling

            try {
                // Clean any existing output buffer
                if (ob_get_level() > 0 && ob_get_length() > 0) {
                    ob_end_clean();
                }

                 // Log the fatal error
                 self::$logger?->critical("FATAL Error: " . ($lastError['message'] ?? 'Unknown Fatal Error'), [
                     'type' => $lastError['type'],
                     'file' => $lastError['file'],
                     'line' => $lastError['line'],
                 ]);

                 // --- Display Error Page using ErrorController (Best effort) ---
                 if (self::$logger === null || self::$config === null || self::$viewRenderer === null || self::$services === null || self::$db === null) {
                     self::$logger?->critical("Fatal error handler dependencies missing! Cannot use ErrorController. Falling back.");
                     self::finalFallbackErrorPage(null, null, $lastError);
                 } else {
                     // Set 500 status code if possible
                     if (!headers_sent()) {
                         http_response_code(500);
                     }
                     // Instantiate ErrorController and show server error page
                     $errorController = new ErrorController(self::$db, self::$logger, self::$config, self::$viewRenderer, self::$services);
                     // Pass the raw fatal error info if debug is on
                     $errorController->showServerError(null, self::$isDebug ? $lastError : null);
                 }

            } catch (Throwable $e) {
                 // Error during fatal error handling!
                 error_log("FATAL: Exception occurred DURING fatal error handling: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
                 if ($lastError) { error_log("Original Fatal Error: " . $lastError['message']); }
                 self::finalFallbackErrorPage(null, $e, $lastError);
            } finally {
                 self::$handlingError = false; // Reset flag
            }
             // No exit needed, shutdown function terminates script
        }
    }

    /**
     * Displays a minimal, safe fallback error page when the main error handling fails.
     * Avoids using complex dependencies.
     *
     * @param Throwable|null $originalException The original exception (if available).
     * @param Throwable|null $handlerException The exception during error handling (if available).
     * @param array|null $fatalErrorInfo Raw fatal error info (if available).
     */
    private static function finalFallbackErrorPage(?Throwable $originalException = null, ?Throwable $handlerException = null, ?array $fatalErrorInfo = null): void {
        // Ensure no output before this point
        if (ob_get_level() > 0) { @ob_end_clean(); } // Use @ to suppress errors during cleanup
    
        // Set 500 status code if possible
        if (!headers_sent()) {
             http_response_code(500);
             header('Content-Type: text/html; charset=UTF-8'); // Set content type
        }
    
        // Check debug status one last time (directly, as config might be unavailable)
        $isDebug = self::$isDebug; // Use the static property
    
        // ---- Improved HTML and CSS ----
        echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><title>خطای سیستمی</title>";
        echo "<style>";
        // Embed Vazirmatn font directly
        echo "@font-face {";
        echo "font-family: 'Vazirmatn';";
        echo "src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Round-Dots/fonts/webfonts/Vazirmatn-RD[wght].woff2') format('woff2 supports variations'),";
        echo "url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Round-Dots/fonts/webfonts/Vazirmatn-RD[wght].woff2') format('woff2-variations');";
        echo "font-weight: 100 900;";
        echo "font-style: normal;";
        echo "font-display: swap;";
        echo "}";
        // Basic professional styling
        echo "body { font-family: 'Vazirmatn', Tahoma, Arial, sans-serif; padding: 30px; background-color: #f4f4f4; color: #333; line-height: 1.6; text-align: right; }";
        echo ".container { max-width: 800px; margin: 30px auto; background-color: #fff; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 30px; border-radius: 5px; }";
        echo "h1 { color: #d9534f; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px; font-weight: 500; }";
        echo "p { margin-bottom: 15px; font-size: 1.05em; }";
        echo ".error-box { background-color: #f9f2f4; border: 1px solid #d9534f; padding: 15px; margin-top: 25px; border-radius: 4px; }";
        echo ".error-box h2 { color: #d9534f; margin-top: 0; font-size: 1.2em; }";
        echo ".error-box h3 { margin-top: 20px; margin-bottom: 5px; font-size: 1.1em; color: #555; }";
        echo ".error-box p { margin-bottom: 5px; font-size: 0.95em; }";
        echo "pre { background-color: #eee; padding: 10px; border: 1px solid #ddd; overflow-x: auto; font-family: monospace; font-size: 0.9em; white-space: pre-wrap; word-wrap: break-word; }";
        echo "</style>";
        echo "</head><body>";
        echo "<div class='container'>"; // Wrap content in a container
        echo "<h1><span style='font-size: 1.5em; margin-left: 10px;'>⚠️</span>خطای بحرانی سیستم</h1>"; // Add an emoji
        echo "<p>یک خطای غیرمنتظره و غیرقابل بازیابی رخ داده است که مانع از اجرای صحیح برنامه شد.</p>";
        echo "<p>این مشکل به تیم فنی گزارش شده است. لطفاً دقایقی دیگر دوباره تلاش کنید یا در صورت تداوم مشکل با پشتیبانی تماس بگیرید.</p>";
    
        if ($isDebug) {
            echo "<div class='error-box'>";
            echo "<h2>جزئیات خطا (فقط در حالت اشکال زدایی):</h2>";
            echo "<p><strong>توجه:</strong> این اطلاعات هرگز نباید در محیط عملیاتی (Production) نمایش داده شوند.</p>";
    
            if ($handlerException) {
                 echo "<h3>خطا در زمان پردازش خطا:</h3>";
                 echo "<p><strong>Type:</strong> " . htmlspecialchars(get_class($handlerException)) . "</p>";
                 echo "<p><strong>Message:</strong> " . htmlspecialchars($handlerException->getMessage()) . "</p>";
                 echo "<p><strong>File:</strong> " . htmlspecialchars($handlerException->getFile()) . ":" . htmlspecialchars((string)$handlerException->getLine()) . "</p>";
                 echo "<h4>Trace:</h4><pre>" . htmlspecialchars($handlerException->getTraceAsString()) . "</pre>";
                 echo "<hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>";
            }
    
            if ($originalException) {
                 echo "<h3>خطای اصلی:</h3>";
                 echo "<p><strong>Type:</strong> " . htmlspecialchars(get_class($originalException)) . "</p>";
                 echo "<p><strong>Message:</strong> " . htmlspecialchars($originalException->getMessage()) . "</p>";
                 echo "<p><strong>File:</strong> " . htmlspecialchars($originalException->getFile()) . ":" . htmlspecialchars((string)$originalException->getLine()) . "</p>";
                 echo "<h4>Trace:</h4><pre>" . htmlspecialchars($originalException->getTraceAsString()) . "</pre>";
                 if ($fatalErrorInfo) echo "<hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>";
            }
    
            if ($fatalErrorInfo) {
               echo "<h3>خطای Fatal اصلی:</h3>";
               echo "<p><strong>Type:</strong> " . htmlspecialchars((string)($fatalErrorInfo['type'] ?? 'N/A')) . "</p>";
               echo "<p><strong>Message:</strong> " . htmlspecialchars($fatalErrorInfo['message'] ?? 'N/A') . "</p>";
               echo "<p><strong>File:</strong> " . htmlspecialchars($fatalErrorInfo['file'] ?? 'N/A') . ":" . htmlspecialchars((string)($fatalErrorInfo['line'] ?? 'N/A')) . "</p>";
            }
    
            echo "</div>"; // End error-box
        } else {
             echo "<p style='font-size: 0.9em; color: #666;'>جزئیات فنی خطا در گزارشات سرور ثبت شده است.</p>";
        }
    
        echo "</div>"; // End container
        echo "</body></html>";
        // ---- End Improved HTML and CSS ----
    }
}
