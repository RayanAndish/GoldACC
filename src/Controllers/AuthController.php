<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController; // Explicitly use parent

// Dependencies (will be accessed via $this->authService and $this->licenseService from parent)
use App\Services\AuthService;
use App\Services\LicenseService;

/**
 * AuthController handles user authentication processes (Login, Logout).
 * Inherits from AbstractController to access core dependencies and methods.
 */
class AuthController extends AbstractController {

    // Dependencies $authService and $licenseService are inherited from AbstractController
    // No need to redefine them here unless overriding constructor for *additional* dependencies.

    /**
     * Constructor. Relies on the parent constructor to inject and store common dependencies
     * including AuthService and LicenseService.
     *
     * @param PDO $db Database connection.
     * @param Logger $logger Logger instance.
     * @param array $config Application configuration.
     * @param ViewRenderer $viewRenderer View renderer instance.
     * @param array $services Array of available services.
     */
    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        // Call parent constructor to initialize $db, $logger, $config, $viewRenderer, $services,
        // and also set $this->authService, $this->licenseService.
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        $this->logger->debug("AuthController initialized.");
        // No need to access $this->errorService unless it's specifically added and needed here.
    }
/**
 * Activates a user account based on the activation code provided (usually from email link).
 * Route: /activate-account/{code} (GET) - Example Route
 *
 * @param string $activationCode The activation code from the URL parameter.
 */
    public function activateAccount(string $activationCode): void {
        $this->logger->info("Attempting account activation.", ['code_prefix' => substr($activationCode, 0, 5)]);
        $pageTitle = "فعال‌سازی حساب کاربری";
        $message = '';
        $messageType = 'danger'; // Default to error

        if (empty($activationCode)) {
            $message = 'کد فعال‌سازی ارائه نشده است.';
            $this->logger->warning("Account activation attempted with empty code.");
        } else {
            try {
                // Use UserRepository to find and activate the user
                // Assume UserRepository::activateUserByCode returns true on success, false if not found/already active
                $activationResult = $this->userRepository->activateUserByCode($activationCode);

                if ($activationResult === 'success') {
                    $message = 'حساب کاربری شما با موفقیت فعال شد. اکنون می‌توانید وارد شوید.';
                    $messageType = 'success';
                    $this->logger->info("Account activated successfully.", ['code_prefix' => substr($activationCode, 0, 5)]);
                    // Optional: Log activity
                    // Helper::logActivity($this->db, "User account activated.", 'SUCCESS', ['activation_code' => $activationCode]);

                } elseif ($activationResult === 'not_found') {
                    $message = 'کد فعال‌سازی نامعتبر یا منقضی شده است.';
                    $this->logger->warning("Account activation failed: Code not found or user already active.", ['code_prefix' => substr($activationCode, 0, 5)]);
                } else { // 'already_active' or other states if repo returns them
                    $message = 'این حساب کاربری قبلاً فعال شده است.';
                    $messageType = 'info';
                    $this->logger->info("Account activation attempt for already active account.", ['code_prefix' => substr($activationCode, 0, 5)]);
                }

            } catch (Throwable $e) {
                $this->logger->error("Error during account activation process.", ['code_prefix' => substr($activationCode, 0, 5), 'exception' => $e]);
                $message = 'خطایی در فرآیند فعال‌سازی حساب رخ داد. لطفاً با پشتیبانی تماس بگیرید.';
                $messageType = 'danger';
            }
        }

        // Render a simple status view (no form needed)
        // Assuming view exists at: src/views/auth/activation_status.php
        $this->render('auth/activation_status', [
            'page_title' => $pageTitle,
            'status_message' => $message,
            'message_type' => $messageType, // Pass type for alert class
            'show_login_link' => ($messageType === 'success' || $messageType === 'info') // Show login link on success/already active
        ], false); // Render without main layout
    }
    /**
     * Displays the login form (Handles GET /login).
     */
    public function showLoginForm(): void {
        // Redirect if already logged in
        if ($this->isLoggedIn()) {
            $this->logger->debug("User already logged in, redirecting to dashboard.");
            $this->redirect('/app/dashboard'); // Adjusted dashboard route
            return;
        }
    
        // --- Check License Status BEFORE showing login form ---
        /*
        try {
            $licenseCheck = $this->licenseService->checkLicense();
            if (!$licenseCheck['valid']) {
                $this->logger->warning("Access to login page denied: License invalid.", ['message' => $licenseCheck['message']]);
                $this->setSessionMessage($licenseCheck['message'] ?: 'برای ورود، ابتدا باید سامانه را فعال کنید.', 'warning', 'license_message');
                $this->redirect('/activate'); // Redirect to activation page
                return; // Stop execution
            }
             $this->logger->debug("License check passed for accessing login form.");
        } catch (Throwable $e) {
             // Critical error during license check
             $this->logger->critical("Error checking license before showing login form.", ['exception' => $e]);
             // Show a generic error or redirect to activation? Maybe redirect is safer.
             $this->setSessionMessage('خطای سیستمی در بررسی وضعیت فعال‌سازی رخ داد.', 'danger');
             $this->redirect('/activate'); // Redirect to activation page on error too? Or show 500?
             return;
        }
        */
        // --- End License Check ---
    
    
        $this->logger->debug("Displaying login form.");
        $loginError = $this->getFlashMessage('login_error');
        $loginSuccess = $this->getFlashMessage('login_success');
    
        $this->render('auth/login', [ // Assuming login view is auth/login.php
            'page_title' => 'ورود به سیستم',
            'error' => $loginError ? $loginError['text'] : null,
            'success' => $loginSuccess ? $loginSuccess['text'] : null,
        ], false); // No layout for login page
    }

    /**
     * Processes the login form submission (Handles POST /login).
     */
    public function handleLogin(): void {
        // Ensure it's a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning("Non-POST request received on handleLogin endpoint.");
            $this->redirect('/login');
            // exit;
        }

        // TODO: Implement CSRF token validation here

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? ''; // Do not trim password

        // Basic Input Validation
        if (empty($username) || empty($password)) {
            $this->logger->warning("Login attempt with empty username or password.");
            $this->setSessionMessage('نام کاربری و رمز عبور الزامی است.', 'danger', 'login_error');
            $this->redirect('/login');
            // exit;
        }

        $this->logger->info("Processing login attempt.", ['username' => $username]);

        try {
            // Attempt login using AuthService (provides user data on success)
            $loginResult = $this->authService->login($username, $password, $_SERVER['REMOTE_ADDR'] ?? 'unknown');

            if ($loginResult['success']) {
                $user = $loginResult['user'];
                $this->logger->info("Login successful.", ['user_id' => $user['id'], 'username' => $username]);

                // --- Login Success: Set up Session ---
                // Regenerate session ID *after* successful authentication
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                } else {
                    // Should not happen, but log if it does
                    $this->logger->error("Session not active during login success handling.");
                }
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role_id'] ?? null; // Store role ID or name if available
                $_SESSION['is_logged_in'] = true;
                // Reset regeneration timer if used
                // unset($_SESSION['last_regenerate']);
                // --- End Session Setup ---


                // --- Check License Status ---
                /*
                // Use the inherited $this->licenseService
                $licenseCheck = $this->licenseService->checkLicense();
                if ($licenseCheck['valid']) {
                    $this->logger->info("License valid after login. Redirecting to dashboard.");
                    // $this->setSessionMessage('خوش آمدید!', 'success', 'dashboard_message'); // Optional welcome message
                    $this->redirect('/'); // Redirect to main dashboard
                } else {
                    $this->logger->warning("License invalid after successful login. Redirecting to activation.", [
                        'message' => $licenseCheck['message'],
                        'user_id' => $user['id']
                    ]);
                    // Store license message to display on activation page
                    $this->setSessionMessage($licenseCheck['message'] ?: 'سامانه نیاز به فعال‌سازی دارد.', 'warning', 'license_message');
                    $this->redirect('/activate'); // Redirect to license activation page
                }
                */
                // --- End License Check ---
                $this->redirect('/'); // Redirect to main dashboard

            } else {
                // Login failed (AuthService handled logging of reason)
                $this->setSessionMessage($loginResult['message'] ?? 'نام کاربری یا رمز عبور اشتباه است.', 'danger', 'login_error');
                $this->redirect('/login'); // Redirect back to login form with error
            }
        } catch (Throwable $e) {
            // Catch potential exceptions from AuthService or LicenseService
            $this->logger->error("Exception during login process.", ['username' => $username, 'exception' => $e]);
            $this->setSessionMessage('خطای سیستمی در هنگام ورود رخ داد. لطفا با پشتیبانی تماس بگیرید.', 'danger', 'login_error');
            $this->redirect('/login'); // Redirect back to login form
        }
        // exit; // Redirect includes exit
    }

    /**
     * Processes user logout (Handles GET /logout).
     */
    public function logout(): void {
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'N/A';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $this->logger->info("Processing user logout.", ['user_id' => $userId, 'username' => $username]);

        // Call AuthService::logout (primarily for logging or other hooks)
        // $this->authService->logout($userId, $username, $ip); // Assuming it exists and is needed

        // --- Session Destruction ---
        // Ensure session is active before trying to destroy
        if (session_status() === PHP_SESSION_ACTIVE) {
            // 1. Unset all session variables
            $_SESSION = [];

            // 2. Delete the session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, // Set expiry in the past
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }

            // 3. Destroy the session data on the server
            session_destroy();
             $this->logger->info("Session destroyed for logout.");
        } else {
             $this->logger->warning("Logout attempted but no active session found.");
        }
        // --- End Session Destruction ---

        // Set a success message for the login page
        $this->setSessionMessage('شما با موفقیت از حساب کاربری خود خارج شدید.', 'success', 'login_success');

        // Redirect to login page
        $this->redirect('/login');
        // exit; // Redirect includes exit
    }

    /**
     * Displays the registration form (GET /register) or handles submission (POST /register).
     */
    public function handleRegistration(): void {
        // Redirect if already logged in
        if ($this->isLoggedIn()) {
            $this->logger->debug("User already logged in, redirecting to dashboard from register page.");
            $this->redirect('/app/dashboard');
            return;
        }

        // Check License Status (Registration might be allowed even without license, depending on policy)
        // Example: Redirect if license is invalid and registration is not allowed
        /*
        $licenseCheck = $this->licenseService->checkLicense();
        if (!$licenseCheck['valid']) {
             $this->logger->warning("Registration attempt denied: License invalid.");
             $this->setSessionMessage('امکان ثبت نام وجود ندارد. لطفا ابتدا سامانه را فعال کنید.', 'danger', 'license_message');
             $this->redirect('/activate');
             return;
        }
        */

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // --- Handle POST Request (Process Registration) ---
            $this->logger->info("Processing registration form submission.");

            // TODO: Implement CSRF validation

            // Get data from POST
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';
            $email = trim($_POST['email'] ?? '');

            // --- Basic Validation --- (More robust validation needed)
            $errors = [];
            if (empty($username)) { $errors['username'] = 'نام کاربری الزامی است.'; }
            // TODO: Add more username validation (length, format, uniqueness check via UserService/Repo)
            if (empty($email)) { $errors['email'] = 'ایمیل الزامی است.'; }
            // TODO: Add email validation (format, uniqueness check via UserService/Repo)
            if (empty($password)) { $errors['password'] = 'رمز عبور الزامی است.'; }
            // TODO: Add password strength validation
            if ($password !== $passwordConfirm) { $errors['password_confirm'] = 'تکرار رمز عبور مطابقت ندارد.'; }

            if (!empty($errors)) {
                // Redirect back to form with errors and input data
                $this->logger->warning("Registration validation failed.", ['errors' => $errors]);
                $this->setSessionMessage('لطفاً خطاهای فرم را اصلاح کنید.', 'danger', 'register_error');
                // Store errors and old input in flash session data
                $_SESSION['_flash']['register_errors'] = $errors;
                $_SESSION['_flash']['register_old_input'] = ['username' => $username, 'email' => $email]; // Don't re-fill password
                $this->redirect('/register');
                return;
            }

            // --- Attempt Registration via Service ---
            try {
                // Assume UserService::registerUser handles hashing password, checking uniqueness etc.
                $registrationResult = $this->userService->registerUser($username, $password, $email);

                if ($registrationResult['success']) {
                    $this->logger->info("User registered successfully.", ['username' => $username, 'email' => $email, 'user_id' => $registrationResult['user_id'] ?? null]);
                    // TODO: Send activation email if required
                    $this->setSessionMessage('ثبت نام شما با موفقیت انجام شد. لطفا وارد شوید.', 'success', 'login_success'); // Show success on login page
                    $this->redirect('/login');
                    return;
                } else {
                    // Registration failed (e.g., username/email exists)
                    $this->logger->error("User registration failed.", ['username' => $username, 'email' => $email, 'message' => $registrationResult['message'] ?? 'Unknown reason']);
                    $this->setSessionMessage($registrationResult['message'] ?? 'خطایی در هنگام ثبت نام رخ داد. ممکن است نام کاربری یا ایمیل تکراری باشد.', 'danger', 'register_error');
                    // Re-populate form
                    $_SESSION['_flash']['register_old_input'] = ['username' => $username, 'email' => $email];
                    $this->redirect('/register');
                    return;
                }
            } catch (Throwable $e) {
                 $this->logger->error("Exception during user registration.", ['username' => $username, 'exception' => $e]);
                 $this->setSessionMessage('خطای سیستمی در هنگام ثبت نام رخ داد. لطفا با پشتیبانی تماس بگیرید.', 'danger', 'register_error');
                 // Re-populate form
                 $_SESSION['_flash']['register_old_input'] = ['username' => $username, 'email' => $email];
                 $this->redirect('/register');
                 return;
            }

        } else {
            // --- Handle GET Request (Show Form) ---
            $this->logger->debug("Displaying registration form.");

            // Get potential errors and old input from flash session data
            $registerError = $this->getFlashMessage('register_error');
            $registerErrors = $this->getFlashMessage('register_errors', true); // Get raw data
            $oldInput = $this->getFlashMessage('register_old_input', true); // Get raw data

            $this->render('auth/register', [
                'page_title' => 'ثبت نام کاربر جدید',
                'error' => $registerError ? $registerError['text'] : null, // General error message
                'errors' => $registerErrors ?? [], // Field-specific errors
                'oldInput' => $oldInput ?? [], // Old input data to repopulate form
            ], false); // No layout for registration page
        }
    }

} // End of AuthController class