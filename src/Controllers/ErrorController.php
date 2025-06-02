<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController; // Explicitly use parent

// Utilities
use App\Utils\Helper; // Assuming Helper class exists with escapeHtml method
// use App\Services\ErrorService; // Removed as it was unused

/**
 * ErrorController handles displaying standard error pages (404, 405, 500).
 * It's instantiated and called by the global ErrorHandler.
 * Inherits from AbstractController to access core dependencies.
 */
class ErrorController extends AbstractController {

    // $db, $logger, $config, $viewRenderer, $services, $authService, $licenseService inherited

    /**
     * Constructor. Relies on the parent constructor to store dependencies.
     *
     * @param PDO $db Database connection.
     * @param Logger $logger Logger instance.
     * @param array $config Application configuration.
     * @param ViewRenderer $viewRenderer View renderer instance.
     * @param array $services Array of available services.
     */
    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        $this->logger->debug("ErrorController initialized.");
        // $this->errorService = $services['errorService'] ?? null; // Removed - Was unused
    }

    /**
     * Displays the 404 Not Found error page.
     */
    public function showNotFound(): void {
        $this->logger->debug("Rendering 404 Not Found page.");
        http_response_code(404);
        
        $this->render('errors/404', [
            'page_title' => Helper::getMessageText('not_found', 'صفحه یافت نشد'),
            'error_message' => Helper::getMessageText('page_not_found', 'صفحه مورد نظر یافت نشد.'),
            'app_name' => $this->config['app']['name'] ?? 'حسابداری رایان طلا',
            'base_url' => $this->config['app']['base_url'] ?? '/',
            'is_logged_in' => $this->authService->isLoggedIn()
        ], true, 404);
    }

    /**
     * Displays the 405 Method Not Allowed error page.
     *
     * @param array $allowedMethods Array of allowed HTTP methods.
     */
    public function showMethodNotAllowed(array $allowedMethods): void {
        $this->logger->debug("Rendering 405 Method Not Allowed page.", ['allowed' => $allowedMethods]);
        http_response_code(405);
        if (!headers_sent()) {
            header('Allow: ' . implode(', ', $allowedMethods));
        }

        $this->render('errors/405', [
            'page_title' => Helper::getMessageText('method_not_allowed', 'متد غیرمجاز'),
            'error_message' => Helper::getMessageText('method_not_allowed_details', 'متد درخواست غیرمجاز است.'),
            'allowed_methods' => implode(', ', $allowedMethods),
            'app_name' => $this->config['app']['name'] ?? 'حسابداری رایان طلا',
            'base_url' => $this->config['app']['base_url'] ?? '/',
            'is_logged_in' => $this->authService->isLoggedIn()
        ], true, 405);
    }

    /**
     * Displays the 500 Internal Server Error page.
     * Shows exception details only if debug mode is enabled.
     *
     * @param Throwable|null $exception The exception/error object (for debug info).
     * @param array|null $fatalErrorInfo Raw error info from error_get_last() (for fatal errors).
     */
    public function showServerError(?Throwable $exception = null, ?array $fatalErrorInfo = null): void {
        $this->logger->debug("Rendering 500 Internal Server Error page.");
        if (http_response_code() < 500 && !headers_sent()) {
            http_response_code(500);
        }

        $isDebug = $this->config['app']['debug'] ?? false;
        $errorDetails = '';
        
        if ($isDebug) {
            if ($exception !== null) {
                $errorDetails .= "<h2>جزئیات خطا (" . Helper::escapeHtml(get_class($exception)) . ")</h2>";
                $errorDetails .= "<p><strong>پیام:</strong> " . Helper::escapeHtml($exception->getMessage()) . "</p>";
                $errorDetails .= "<p><strong>فایل:</strong> " . Helper::escapeHtml($exception->getFile()) . " : <strong>" . Helper::escapeHtml((string)$exception->getLine()) . "</strong></p>";
                $errorDetails .= "<h3>ردیابی خطا:</h3><pre>" . Helper::escapeHtml($exception->getTraceAsString()) . "</pre>";
            }
            if ($fatalErrorInfo !== null) {
                $errorDetails .= "<h2>جزئیات خطای مرگبار</h2>";
                $errorDetails .= "<p><strong>نوع:</strong> " . Helper::escapeHtml((string)($fatalErrorInfo['type'] ?? 'N/A')) . "</p>";
                $errorDetails .= "<p><strong>پیام:</strong> " . Helper::escapeHtml($fatalErrorInfo['message'] ?? 'N/A') . "</p>";
                $errorDetails .= "<p><strong>فایل:</strong> " . Helper::escapeHtml($fatalErrorInfo['file'] ?? 'N/A') . " : <strong>" . Helper::escapeHtml((string)($fatalErrorInfo['line'] ?? 'N/A')) . "</strong></p>";
            }
            if (empty($errorDetails)) {
                $errorDetails = "<p>هیچ جزئیات خطای خاصی توسط کنترلر ثبت نشده است.</p>";
            }
        }

        $this->render('errors/500', [
            'page_title' => Helper::getMessageText('server_error', 'خطای سرور'),
            'error_message' => Helper::getMessageText('server_error_details', 'خطای داخلی سرور رخ داده است.'),
            'error_details' => $errorDetails,
            'is_debug' => $isDebug,
            'app_name' => $this->config['app']['name'] ?? 'حسابداری رایان طلا',
            'base_url' => $this->config['app']['base_url'] ?? '/',
            'is_logged_in' => $this->authService->isLoggedIn()
        ], true, 500);
    }

    // Removed the redundant `handleError` and `show404` methods
    // as ErrorHandler calls specific methods (showNotFound, showServerError etc.)

} // End ErrorController class
