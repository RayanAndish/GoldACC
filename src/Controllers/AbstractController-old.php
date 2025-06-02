<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

// Core dependencies
use App\Core\ViewRenderer;
use JetBrains\PhpStorm\NoReturn;
use PDO;
use Monolog\Logger;
use Exception; // For throwing exceptions in helpers

// Services needed by helper methods
use App\Services\AuthService;
use App\Services\LicenseService;
// Utilities
use App\Utils\Helper;

/**
 * Abstract base class for all application controllers.
 * Provides common dependencies (DB, Logger, Config, ViewRenderer, Services)
 * and helper methods like render, redirect, session messaging, and access control checks.
 */
abstract class AbstractController {

    // Core dependencies injected via constructor
    protected PDO $db;
    protected Logger $logger;
    protected array $config;
    protected ViewRenderer $viewRenderer;
    /** @var array Holds all injected services for access by child controllers */
    protected array $services;

    // Commonly used services extracted for convenience (ensure they exist in $services)
    protected AuthService $authService;
    protected LicenseService $licenseService;
    // Add other common services if needed (e.g., protected SecurityService $securityService;)

    /**
     * Constructor. Stores core dependencies.
     * Child controllers MUST call parent::__construct() and pass these dependencies.
     *
     * @param PDO $db PDO database connection instance.
     * @param Logger $logger Monolog Logger instance.
     * @param array $config Application configuration array.
     * @param ViewRenderer $viewRenderer ViewRenderer instance.
     * @param array $services Array containing all application services.
     * @throws Exception If required common services (AuthService, LicenseService) are missing.
     */
    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $config;
        $this->viewRenderer = $viewRenderer;
        $this->services = $services; // Store the whole services array

        // Extract commonly needed services and validate their presence
        if (!isset($services['authService']) || !$services['authService'] instanceof AuthService) {
            throw new Exception('AuthService missing or invalid in services provided to AbstractController.');
        }
        $this->authService = $services['authService'];

        if (!isset($services['licenseService']) || !$services['licenseService'] instanceof LicenseService) {
            throw new Exception('LicenseService missing or invalid in services provided to AbstractController.');
        }
        $this->licenseService = $services['licenseService'];

        // Initialize other common services if needed
        // if (!isset($services['securityService']) || !$services['securityService'] instanceof SecurityService) {
        //     throw new Exception('SecurityService missing or invalid in services provided to AbstractController.');
        // }
        // $this->securityService = $services['securityService'];
    }

    /**
     * Renders a view using the ViewRenderer service.
     * Automatically includes common view data (app name, base URL, login status).
     *
     * @param string $viewName View file name relative to views path (e.g., 'users/list').
     * @param array $data Data specific to the view.
     * @param bool $withLayout Whether to include the main layout (header/footer).
     * @param int $statusCode HTTP status code for the response (default 200).
     * @throws Exception If the view file is not found (rethrown from ViewRenderer).
     */
    protected function render(string $viewName, array $data = [], bool $withLayout = true, int $statusCode = 200): void {
        // Set HTTP status code if not already sent
        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        // Prepare common data available to all views and layouts
        $commonViewData = [
            'appName' => $this->config['app']['name'] ?? 'App',
            'baseUrl' => $this->config['app']['base_url'] ?? '/',
            'isLoggedIn' => $this->authService->isLoggedIn(),
            'loggedInUser' => $this->authService->isLoggedIn() ? $this->authService->getCurrentUser() : null, // Get user info if logged in
            'currentUri' => $_SERVER['REQUEST_URI'] ?? '/',
            'pageTitle' => $data['page_title'] ?? $this->config['app']['name'] ?? 'Application', // Default page title
            'flashMessage' => $this->getFlashMessage(), // Get default flash message
            'flash_license_message' => $this->getFlashMessage('license_message'), // Read specific message if needed
            // Pass global JSON strings for footer injection
            'global_json_strings_for_footer' => $this->config['app']['global_json_strings'] ?? ['fields' => 'null', 'formulas' => 'null', 'error' => 'Global JSON strings not loaded in config'],
        
            // Add other global view variables here if needed
        ];

        // Merge common data with specific view data (specific data overrides common data)
        $finalViewData = array_merge($commonViewData, $data);

        // Delegate rendering to the ViewRenderer service
        $this->viewRenderer->render($viewName, $finalViewData, $withLayout);
    }

    /**
     * Redirects the user to a specified URL within the application.
     * Sends a Location header and terminates script execution.
     *
     * @param string $path The application path to redirect to (e.g., '/users', '/login'). Should start with '/'.
     * @param int $statusCode HTTP status code (default 302 Found).
     */
    #[NoReturn] protected function redirect(string $path, int $statusCode = 302): void {
        // Ensure path starts with '/' for consistency
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        // Construct the full URL using the configured base URL
        $redirectUrl = rtrim($this->config['app']['base_url'] ?? '', '/') . $path;

        $this->logger->debug("Redirecting user.", ['to_url' => $redirectUrl, 'status' => $statusCode]);

        // Prevent header injection vulnerabilities (though unlikely with internal paths)
        $redirectUrl = filter_var($redirectUrl, FILTER_SANITIZE_URL);

        // Send Location header
        header('Location: ' . $redirectUrl, true, $statusCode);

        // Terminate script execution
        exit;
    }

    /**
     * Sets a flash message in the session.
     * Flash messages are typically displayed once and then cleared.
     *
     * @param string $message The message text.
     * @param string $type Message type ('success', 'danger', 'warning', 'info').
     * @param string $key Session key for the message (default 'default').
     */
    protected function setSessionMessage(string $message, string $type = 'info', string $key = 'default'): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->logger->warning("Attempted to set session message, but session is not active.");
            return; // Cannot set message if session isn't running
        }

        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }
        $_SESSION['flash_messages'][$key] = ['text' => $message, 'type' => $type];
        $this->logger->debug("Flash message set.", ['key' => $key, 'type' => $type, 'message' => $message]);
        
        // برای اطمینان از ذخیره شدن پیام در سشن
        session_write_close();
        session_start();
    }

    /**
     * Gets a flash message from the session and removes it.
     * 
     * @param string $key Session key for the message (default 'default').
     * @return array|null Message array with 'text' and 'type' keys, or null if not found.
     */
    protected function getFlashMessage(string $key = 'default'): ?array {
        // ثبت وضعیت سشن در لاگ
        $this->logger->debug("Session status in getFlashMessage", [
            'session_status' => session_status(),
            'session_id' => session_id(),
            'has_flash_messages' => isset($_SESSION['flash_messages']),
            'has_key' => isset($_SESSION['flash_messages'][$key]),
            'key' => $key,
            'all_keys' => isset($_SESSION['flash_messages']) ? array_keys($_SESSION['flash_messages']) : []
        ]);
        
        if (isset($_SESSION['flash_messages'][$key])) {
            $message = $_SESSION['flash_messages'][$key];
            unset($_SESSION['flash_messages'][$key]); // Clear after reading
            // Optional: Remove the 'flash_messages' array entirely if it's now empty
            if (empty($_SESSION['flash_messages'])) {
                 unset($_SESSION['flash_messages']);
            }
            $this->logger->debug("Flash message retrieved and cleared.", ['key' => $key, 'message' => $message]);
            
            // اگر پیام به صورت رشته باشد، آن را به آرایه تبدیل کنیم
            if (is_string($message)) {
                return ['text' => $message, 'type' => 'info'];
            }
            
            return $message;
        }
        $this->logger->debug("No flash message found for key.", ['key' => $key]);
        return null;
    }

    /**
     * Checks if the user is logged in using AuthService.
     *
     * @return bool True if logged in, false otherwise.
     */
    protected function isLoggedIn(): bool {
        // Delegate check to AuthService
        return $this->authService->isLoggedIn();
    }

    /**
     * Requires the user to be logged in.
     * If not logged in, sets a flash message and redirects to the login page.
     */
    protected function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            $this->logger->warning("Access denied: Login required.", ['requested_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A']);
            $this->setSessionMessage('برای دسترسی به این صفحه، لطفاً ابتدا وارد شوید.', 'warning');
            $this->redirect('/login'); // Assumes login route is '/login'
        }
    }

    /**
     * Requires a valid and active license.
     * If the license is invalid, sets a flash message and redirects to the activation page.
     */
    //protected function requireLicense(): void {
    //    try {
    //        $licenseCheck = $this->licenseService->checkLicense();
    //        if (!$licenseCheck['valid']) {
    //            $this->logger->warning("Access denied: Invalid or inactive license.", ['message' => $licenseCheck['message']]);
    //            $this->setSessionMessage($licenseCheck['message'] ?: 'سامانه نیاز به فعال‌سازی دارد.', 'warning', 'license_message'); // Use specific key
    //            $this->redirect('/activate'); // Assumes activation route is '/activate'
    //        }
    //    } catch (Throwable $e) {
    //        // Handle potential errors during license check itself
    //        $this->logger->critical("Critical error during license check enforcement.", ['exception' => $e]);
    //        // Redirect to an error page or activation page with a generic error
    //        $this->setSessionMessage('خطای سیستمی در بررسی وضعیت فعال‌سازی رخ داد.', 'danger');
    //        // Depending on severity, maybe redirect to login or a specific error display?
    //         $this->redirect('/activate'); // Redirecting to activate might be safest
    //    }
    //}

    /**
     * Checks if the current logged-in user has administrator privileges.
     * **Placeholder:** Needs implementation based on how roles/permissions are stored.
     *
     * @return bool True if the user is an admin, false otherwise.
     */
    protected function userIsAdmin(): bool {
        $userRoleId = $_SESSION['user_role'] ?? null;
        return ($userRoleId === 1 || $userRoleId === '1' || strtolower($userRoleId) === 'admin');
    }

    /**
     * Requires administrator privileges. Redirects if the user is not an admin.
     */
     protected function requireAdmin(): void {
          $this->requireLogin(); // Must be logged in first
          if (!$this->userIsAdmin()) {
               $this->logger->warning("Access denied: Admin privileges required.", [
                   'user_id' => $_SESSION['user_id'] ?? null,
                   'requested_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'
               ]);
               $this->setSessionMessage('شما دسترسی لازم برای مشاهده این بخش را ندارید.', 'danger');
               $this->redirect('/app/dashboard'); // Redirect to dashboard or a specific 'access denied' page
          }
     }


    // Add other common helper methods as needed (e.g., checkPermission, getUser)

} // End AbstractController class