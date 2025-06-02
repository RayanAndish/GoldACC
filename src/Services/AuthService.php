<?php

namespace App\Services;

use Monolog\Logger;
use Exception;
use Throwable;

use App\Repositories\UserRepository; // Dependency for user data access
use App\Services\SecurityService; // Dependency for password hashing/verification and security checks

/**
 * AuthService encapsulates the business logic related to user authentication.
 * Handles login, logout, registration (placeholder), and failed attempt management.
 * It collaborates with UserRepository and SecurityService.
 * Session management itself (setting/clearing $_SESSION) is handled by the Controller.
 */
class AuthService {

    private UserRepository $userRepository;
    private SecurityService $securityService;
    private Logger $logger;
    private array $config;
    private int $maxLoginAttempts;
    private int $loginBlockTime;

    /**
     * Constructor. Injects dependencies.
     *
     * @param UserRepository $userRepository User repository instance.
     * @param SecurityService $securityService Security service instance.
     * @param Logger $logger Logger instance.
     * @param array $config Application configuration array.
     */
    public function __construct(
        UserRepository $userRepository,
        SecurityService $securityService,
        Logger $logger,
        array $config
    ) {
        $this->userRepository = $userRepository;
        $this->securityService = $securityService;
        $this->logger = $logger;
        $this->config = $config;

        // Get rate limiting config
        $this->maxLoginAttempts = $this->config['app']['max_login_attempts'] ?? 5;
        $this->loginBlockTime = $this->config['app']['login_block_time'] ?? 900; // 15 minutes

        $this->logger->debug("AuthService initialized.");
    }

    /**
     * Processes user login attempt.
     * Verifies credentials, checks account status, logs attempts, and manages rate limiting.
     * Returns user data on success, or failure details. Session is handled by the caller (Controller).
     *
     * @param string $username Entered username.
     * @param string $password Entered raw password.
     * @param string $ip User's IP address.
     * @return array ['success' => bool, 'message' => string, 'user' => ?array]
     * @throws Exception Rethrows critical exceptions from dependencies.
     */
    public function login(string $username, string $password, string $ip): array {
        $this->logger->info("Login attempt.", ['username' => $username, 'ip' => $ip]);

        try {
            // 1. Check Rate Limiting / Blocked IP (using SecurityService)
            // Assumes SecurityService::isLoginBlocked($ip, $username) exists
            if ($this->securityService->isLoginBlocked($ip, $username)) {
                $this->logger->warning("Login attempt blocked due to rate limiting.", ['username' => $username, 'ip' => $ip]);
                return ['success' => false, 'message' => 'تلاش بیش از حد مجاز. لطفاً چند دقیقه دیگر دوباره امتحان کنید.'];
            }

            // 2. Fetch user from database via UserRepository
            // Assumes UserRepository::getUserByUsername($username) exists and returns user array or null
            $user = $this->userRepository->getUserByUsername($username);

            // 3. Verify user existence and status
            if (!$user || ($user['is_active'] ?? 0) !== 1) {
                $this->logger->warning("Login failed: User not found or inactive.", ['username' => $username, 'ip' => $ip]);
                $this->logFailedLoginAttempt($username, $ip); // Log failed attempt
                return ['success' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است.']; // Generic message
            }

            // 4. Verify password using SecurityService
            // Assumes SecurityService::verifyPassword($password, $hash) exists
            if (!$this->securityService->verifyPassword($password, $user['password_hash'] ?? '')) {
                $this->logger->warning("Login failed: Incorrect password.", ['username' => $username, 'ip' => $ip]);
                $this->logFailedLoginAttempt($username, $ip); // Log failed attempt
                return ['success' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است.']; // Generic message
            }

            // 5. Login successful
            $this->logger->info("Login successful.", ['user_id' => $user['id'], 'username' => $user['username'], 'ip' => $ip]);

            // 6. Reset failed login attempts for this user/ip on success (via SecurityService or UserRepository)
            // Assumes SecurityService::clearLoginAttempts($ip, $username) exists
            $this->securityService->clearLoginAttempts($ip, $username);

            // 7. Return success and user data. Session management is done in the Controller.
            return ['success' => true, 'message' => 'ورود موفقیت آمیز بود.', 'user' => $user];

        } catch (Throwable $e) {
            $this->logger->error("Exception during login process.", ['username' => $username, 'exception' => $e, 'ip' => $ip]);
            // Rethrow the exception to be handled by the global error handler
            throw new Exception("خطای سیستمی در پردازش ورود رخ داد.", 0, $e);
        }
    }

    /**
     * Logs a failed login attempt and potentially triggers rate limiting checks.
     * Delegates actions to UserRepository and SecurityService.
     *
     * @param string $username Attempted username.
     * @param string $ip User's IP address.
     */
    private function logFailedLoginAttempt(string $username, string $ip): void {
        $this->logger->debug("Logging failed login attempt.", ['username' => $username, 'ip' => $ip]);
        try {
            // Log the attempt in the database
            // Assumes UserRepository::logFailedLoginAttempt($username, $ip) exists
            $this->userRepository->logFailedLoginAttempt($username, $ip);

            // Check if the IP/user should be blocked based on recent failures
            // Assumes SecurityService::checkAndBlockLoginAttempts($ip, $username) exists
            $this->securityService->checkAndBlockLoginAttempts($ip, $username); // This method encapsulates the logic using config values

        } catch (Throwable $e) {
            // Log error but don't let it break the main login flow (user still gets generic error)
            $this->logger->error("Error during failed login attempt logging/blocking.", ['username' => $username, 'ip' => $ip, 'exception' => $e]);
        }
    }

    /**
     * Registers a new user.
     * **Placeholder - Requires full implementation.**
     * Includes validation, uniqueness checks, password hashing, and saving the user.
     *
     * @param array $userData User data (username, password, name, email, etc.).
     * @return int The ID of the newly registered user.
     * @throws Exception If validation fails or registration encounters an error.
     */
    public function registerUser(array $userData): int {
         $this->logger->warning("AuthService::registerUser called, but it's a placeholder. Registration requires implementation.", ['username' => $userData['username'] ?? 'N/A']);

         // --- Implementation Steps ---
         // 1. **Validate** $userData thoroughly (required fields, formats, password strength etc.). Throw Exception on failure.
         // 2. Check if username or email already **exists** using UserRepository. Throw Exception if duplicate.
         // 3. **Hash** the password using $this->securityService->hashPassword().
         // 4. Prepare the final user data array (add 'password_hash', 'is_active' = 1, default 'role_id', 'created_at', etc.). Remove raw 'password'.
         // 5. **Save** the user using $this->userRepository->saveUser($finalUserData). This should return the new user ID.
         // 6. Optionally, send a **confirmation email** (if MailerService is integrated).
         // 7. **Log** the successful registration.
         // 8. Return the new user ID.

         // Example Exception for placeholder:
         throw new Exception("User registration functionality is not yet implemented.");
         // return 0; // Placeholder return
    }

    /**
     * Logs the user logout event.
     * Session clearing is handled by the Controller.
     *
     * @param int $userId User ID logging out.
     * @param string $username Username logging out.
     * @param string $ip User's IP address.
     */
    public function logout(int $userId, string $username, string $ip): void {
        // This method primarily serves as a hook for logging or other actions needed on logout
        // The actual session destruction happens in the AuthController.
        $this->logger->info("User logged out.", ['user_id' => $userId, 'username' => $username, 'ip' => $ip]);

        // Add any other logic needed on logout (e.g., invalidating specific tokens) here.
    }

    /**
     * Retrieves the data of the currently logged-in user.
     *
     * @return array|null User data array if logged in, null otherwise.
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            $this->logger->debug("Attempted to get current user but not logged in.");
            return null;
        }

        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            $this->logger->warning("User is marked as logged in, but user_id not found in session.");
            // Force logout or handle appropriately
            session_unset();
            session_destroy();
            return null;
        }

        try {
            // Fetch user details from the repository
            $user = $this->userRepository->getUserById((int)$userId);
            if (!$user) {
                $this->logger->error("Logged in user_id [{$userId}] not found in database.");
                // Force logout
                 session_unset();
                 session_destroy();
                 return null;
            }
            // Optionally remove sensitive data like password hash before returning
            // unset($user['password_hash']);
            $this->logger->debug("Retrieved current user data.", ['user_id' => $userId]);
            return $user;
        } catch (Throwable $e) {
            $this->logger->error("Error fetching current user data from repository.", ['user_id' => $userId, 'exception' => $e]);
            return null; // Return null on error
        }
    }

    /**
     * Checks if a user is currently logged in based on session data.
     *
     * @return bool True if logged in, false otherwise.
     */
    public function isLoggedIn(): bool
    {
        // Check if the session is active and the specific keys are set
        return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['is_logged_in']) && !empty($_SESSION['user_id']);
    }

    // Other methods like requestPasswordReset, resetPassword, etc., would go here.
}