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
        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        $commonViewData = [
            'appName' => $this->config['app']['name'] ?? 'App',
            'baseUrl' => $this->config['app']['base_url'] ?? '/',
            'isLoggedIn' => $this->authService->isLoggedIn(),
            'loggedInUser' => $this->authService->isLoggedIn() ? $this->authService->getCurrentUser() : null,
            'currentUri' => $_SERVER['REQUEST_URI'] ?? '/',
            'pageTitle' => $data['page_title'] ?? $this->config['app']['name'] ?? 'Application',
            'flashMessage' => $this->getFlashMessage(),
            'flash_license_message' => $this->getFlashMessage('license_message'),
            'global_json_strings_for_footer' => $this->config['app']['global_json_strings'] ?? ['fields' => 'null', 'formulas' => 'null', 'error' => 'Global JSON strings not loaded in config'],
        ];

        $finalViewData = array_merge($commonViewData, $data);
        $this->viewRenderer->render($viewName, $finalViewData, $withLayout);
    }

    /**
     * Redirects the user to a specified URL within the application.
     * Sends a Location header and terminates script execution.
     *
     * @param string $path The application path to redirect to (e.g., '/users', '/login'). Should start with '/'.
     * @param int $statusCode HTTP status code (default 302 Found).
     */
    #[NoReturn]
    protected function redirect(string $path, int $statusCode = 302): void {
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        $redirectUrl = rtrim($this->config['app']['base_url'] ?? '', '/') . $path;
        $this->logger->debug("Redirecting user.", ['to_url' => $redirectUrl, 'status' => $statusCode]);
        $redirectUrl = filter_var($redirectUrl, FILTER_SANITIZE_URL);
        header('Location: ' . $redirectUrl, true, $statusCode);
        exit;
    }

    /**
     * Sets a flash message in the session.
     *
     * @param string $message The message text.
     * @param string $type Message type ('success', 'danger', 'warning', 'info').
     * @param string $key Session key for the message (default 'default').
     */
    protected function setSessionMessage(string $message, string $type = 'info', string $key = 'default'): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->logger->warning("Attempted to set session message, but session is not active.");
            return;
        }
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }
        $_SESSION['flash_messages'][$key] = ['text' => $message, 'type' => $type];
        $this->logger->debug("Flash message set.", ['key' => $key, 'type' => $type, 'message' => $message]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
            session_start();
        }
    }

    /**
     * Gets a flash message from the session and removes it.
     * * @param string $key Session key for the message (default 'default').
     * @return array|null Message array with 'text' and 'type' keys, or null if not found.
     */
    protected function getFlashMessage(string $key = 'default'): ?array {
        if (isset($_SESSION['flash_messages'][$key])) {
            $message = $_SESSION['flash_messages'][$key];
            unset($_SESSION['flash_messages'][$key]);
            if (empty($_SESSION['flash_messages'])) {
                 unset($_SESSION['flash_messages']);
            }
            return is_array($message) ? $message : ['text' => $message, 'type' => 'info'];
        }
        return null;
    }
    
    /**
     * NEW: Helper method to send a JSON response and exit script execution.
     *
     * @param array $data The data to encode as JSON.
     * @param int $statusCode The HTTP status code to send.
     */
    #[NoReturn]
    protected function jsonResponse(array $data, int $statusCode = 200): void {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    protected function isLoggedIn(): bool {
        return $this->authService->isLoggedIn();
    }

    protected function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            $this->logger->warning("Access denied: Login required.", ['requested_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A']);
            $this->setSessionMessage('برای دسترسی به این صفحه، لطفاً ابتدا وارد شوید.', 'warning');
            $this->redirect('/login');
        }
    }

    protected function userIsAdmin(): bool {
        $userRoleId = $_SESSION['user_role'] ?? null;
        return ($userRoleId === 1 || $userRoleId === '1' || strtolower((string)$userRoleId) === 'admin');
    }

    protected function requireAdmin(): void {
        $this->requireLogin();
        if (!$this->userIsAdmin()) {
            $this->logger->warning("Access denied: Admin privileges required.", [
                'user_id' => $_SESSION['user_id'] ?? null,
                'requested_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'
            ]);
            $this->setSessionMessage('شما دسترسی لازم برای مشاهده این بخش را ندارید.', 'danger');
            $this->redirect('/app/dashboard');
        }
    }
}
