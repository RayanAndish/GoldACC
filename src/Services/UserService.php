<?php

namespace App\Services;

use Monolog\Logger;
use Exception;
use Throwable; // Catch errors and exceptions

// Dependencies
use App\Repositories\UserRepository;
use App\Services\SecurityService;
// use App\Services\MailerService; // Optional: For sending confirmation/welcome emails

/**
 * UserService encapsulates business logic related to user management.
 * Primarily handles user registration in this version, coordinating validation,
 * security checks, and data storage. Can be expanded for other complex user operations.
 */
class UserService {

    // Injected dependencies
    private UserRepository $userRepository;
    private SecurityService $securityService;
    private Logger $logger;
    private array $config;
    // private ?MailerService $mailerService; // Optional dependency

    /**
     * Constructor.
     *
     * @param UserRepository $userRepository User repository instance.
     * @param SecurityService $securityService Security service instance.
     * @param Logger $logger Logger instance.
     * @param array $config Application configuration array.
     * // @param MailerService|null $mailerService Optional mailer service instance.
     */
    public function __construct(
        UserRepository $userRepository,
        SecurityService $securityService,
        Logger $logger,
        array $config
        // MailerService $mailerService = null
    ) {
        $this->userRepository = $userRepository;
        $this->securityService = $securityService;
        $this->logger = $logger;
        $this->config = $config;
        // $this->mailerService = $mailerService;
        $this->logger->debug("UserService initialized.");
    }

    /**
     * Registers a new user based on provided data.
     * Handles validation, uniqueness checks, password hashing, setting defaults,
     * saving the user, and optionally triggers post-registration actions.
     *
     * @param array $userData Associative array of user data. Expected keys:
     *                        'username', 'password' (plain text), 'name', 'email' (optional), 'role_id' (optional).
     * @return int The ID of the newly registered user.
     * @throws Exception If validation fails, user exists, or a database/system error occurs.
     */
    public function registerUser(array $userData): int {
        $username = $userData['username'] ?? null; // For logging/error messages
        $this->logger->info("Attempting to register new user.", ['username' => $username]);

        // --- Stage 1: Validation ---
        $this->validateRegistrationData($userData);
        $this->logger->debug("Registration data passed validation.", ['username' => $username]);

        // --- Stage 2: Uniqueness Check ---
        $this->checkUserUniqueness($username, $userData['email'] ?? null);
        $this->logger->debug("User uniqueness check passed.", ['username' => $username]);

        // --- Stage 3: Prepare Data for Storage ---
        $preparedData = $this->prepareUserDataForStorage($userData);
        $this->logger->debug("User data prepared for storage.", ['username' => $username]);

        // --- Stage 4: Save User ---
        try {
            // Assumes UserRepository::saveUser handles INSERT and returns the new ID
            $newUserId = $this->userRepository->saveUser($preparedData);
            if (!$newUserId || $newUserId <= 0) {
                 // saveUser should ideally throw on failure, but check return value just in case
                 throw new Exception("User repository failed to return a valid new user ID.");
            }
            $this->logger->info("User registered successfully in database.", ['user_id' => $newUserId, 'username' => $username]);

            // --- Stage 5: Post-Registration Actions (Optional) ---
            $this->performPostRegistrationActions($newUserId, $preparedData);

            return $newUserId;

        } catch (Throwable $e) { // Catch DB errors from repository
            $this->logger->error("Database error during user registration save.", ['username' => $username, 'exception' => $e]);
            // Rethrow a user-friendly or specific exception
            throw new Exception("خطا در ذخیره سازی اطلاعات کاربر در پایگاه داده.", 0, $e);
        }
    }

    /**
     * Validates the data provided for user registration.
     * Throws an Exception if validation fails.
     *
     * @param array $userData
     * @throws Exception
     */
    private function validateRegistrationData(array $userData): void {
        $errors = [];
        // Username validation (example: 3-20 alphanumeric + underscore)
        if (empty($userData['username']) || !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $userData['username'])) {
            $errors[] = "نام کاربری نامعتبر است (باید 3 تا 20 کاراکتر شامل حروف انگلیسی، عدد یا زیرخط باشد).";
        }
        // Password validation (example: minimum 8 characters)
        if (empty($userData['password']) || strlen($userData['password']) < 8) {
            $errors[] = "رمز عبور باید حداقل 8 کاراکتر باشد.";
            // Add more complex password rules if needed (uppercase, number, symbol)
        }
        // Name validation (example: required)
        if (empty($userData['name']) || !is_string($userData['name'])) {
             $errors[] = "وارد کردن نام الزامی است.";
        }
        // Email validation (example: optional, but must be valid if provided)
        if (isset($userData['email']) && !empty($userData['email']) && !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
             $errors[] = "فرمت ایمیل وارد شده نامعتبر است.";
        }
        // Role validation (example: optional, must be integer if provided)
        if (isset($userData['role_id']) && !filter_var($userData['role_id'], FILTER_VALIDATE_INT)) {
             $errors[] = "نقش کاربر نامعتبر است.";
        }


        if (!empty($errors)) {
            $this->logger->warning("User registration validation failed.", ['errors' => $errors, 'username' => $userData['username'] ?? 'N/A']);
            // Combine errors into a single message or throw a specific ValidationException
            throw new Exception("خطا در اطلاعات ثبت نام: " . implode(" ", $errors));
        }
    }

    /**
     * Checks if the username or email already exists in the database.
     * Throws an Exception if a duplicate is found.
     *
     * @param string $username
     * @param string|null $email
     * @throws Exception
     */
    private function checkUserUniqueness(string $username, ?string $email): void {
        try {
            // Check username
            // Assumes UserRepository::getUserByUsername exists
            if ($this->userRepository->getUserByUsername($username) !== null) {
                $this->logger->warning("Registration failed: Username already exists.", ['username' => $username]);
                throw new Exception("این نام کاربری ('" . htmlspecialchars($username) . "') قبلاً ثبت شده است.");
            }
            // Check email if provided
            // Assumes UserRepository::getUserByEmail exists
            if ($email && $this->userRepository->getUserByEmail($email) !== null) {
                 $this->logger->warning("Registration failed: Email already exists.", ['email' => $email]);
                 throw new Exception("این ایمیل ('" . htmlspecialchars($email) . "') قبلاً ثبت شده است.");
            }
        } catch (Throwable $e) { // Catch DB errors during check
             $this->logger->error("Error checking user uniqueness during registration.", ['username' => $username, 'email' => $email, 'exception' => $e]);
             throw new Exception("خطا در بررسی یکتایی اطلاعات کاربر.", 0, $e);
        }
    }

    /**
     * Prepares user data array for database insertion.
     * Hashes password, sets defaults, removes unnecessary fields.
     *
     * @param array $userData Validated user data.
     * @return array Data ready for UserRepository::saveUser.
     * @throws Exception If password hashing fails.
     */
    private function prepareUserDataForStorage(array $userData): array {
        $preparedData = $userData; // Start with validated data

        // Hash the password
        try {
            $preparedData['password_hash'] = $this->securityService->hashPassword($userData['password']);
        } catch (Throwable $e) { // Catch hashing errors
            $this->logger->error("Failed to hash password during user data preparation.", ['username' => $userData['username'], 'exception' => $e]);
            throw new Exception("خطا در پردازش امنیتی رمز عبور.", 0, $e);
        }
        unset($preparedData['password']); // CRITICAL: Remove plain text password

        // Set default values if not provided
        $preparedData['is_active'] = $preparedData['is_active'] ?? 1; // Default to active
        $preparedData['role_id'] = $preparedData['role_id'] ?? 2; // Default role ID (e.g., 2 = 'Editor') - Use constants or config

        // 'created_at', 'updated_at' should be handled by the database (DEFAULT CURRENT_TIMESTAMP)
        // or set within the UserRepository::saveUser method.

        // Remove any other fields not meant for the users table
        // unset($preparedData['password_confirmation']); // If exists

        return $preparedData;
    }

    /**
     * Performs actions after successful user registration (e.g., sending email).
     *
     * @param int $userId The ID of the newly registered user.
     * @param array $userData The prepared user data (contains email if available).
     */
    private function performPostRegistrationActions(int $userId, array $userData): void {
        // Example: Send Welcome/Confirmation Email
        /*
        if ($this->mailerService && !empty($userData['email'])) {
            try {
                $this->mailerService->sendWelcomeEmail($userData['email'], $userData['username']);
                $this->logger->info("Welcome email sent successfully.", ['user_id' => $userId, 'email' => $userData['email']]);
            } catch (Throwable $e) {
                // Log failure but don't fail the overall registration process
                $this->logger->warning("Failed to send welcome email.", ['user_id' => $userId, 'email' => $userData['email'], 'exception' => $e]);
            }
        }
        */
        // Add other actions like logging an activity, setting up default profile, etc.
    }


    // --- Other potential UserService methods ---
    // public function requestPasswordReset(string $email): bool { /* ... */ }
    // public function resetPassword(string $token, string $newPassword): bool { /* ... */ }
    // public function changeEmail(int $userId, string $newEmail): bool { /* ... */ }
    // public function verifyEmail(string $token): bool { /* ... */ }

} // End UserService class