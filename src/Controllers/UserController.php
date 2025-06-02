<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions
use Exception; // For general exceptions

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Dependencies
use App\Repositories\UserRepository;
// use App\Services\UserService; // Inject if needed for more complex logic like registration emails
use App\Utils\Helper; // Utility functions
use Morilog\Jalali\Jalalian; // Add Jalalian namespace

/**
 * UserController handles HTTP requests related to user management.
 * Manages listing, add/edit forms, save/delete, status toggling, and user profile actions.
 * Inherits from AbstractController.
 */
class UserController extends AbstractController {

    private UserRepository $userRepository;
    // private ?UserService $userService; // Optional

    /**
     * Constructor. Injects dependencies.
     *
     * @param PDO $db
     * @param Logger $logger
     * @param array $config
     * @param ViewRenderer $viewRenderer
     * @param array $services Array of application services.
     * @throws \Exception If UserRepository is missing.
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services // Receive the $services array
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services); // Pass all to parent

        // Retrieve specific repository
        if (!isset($this->services['userRepository']) || !$this->services['userRepository'] instanceof UserRepository) {
            throw new \Exception('UserRepository not found for UserController.');
        }
        $this->userRepository = $this->services['userRepository'];

        // Optional: Inject UserService if complex logic is needed
        // if (isset($this->services['userService']) && $this->services['userService'] instanceof UserService) {
        //     $this->userService = $this->services['userService'];
        // } else { $this->userService = null; }

        $this->logger->debug("UserController initialized.");
    }

    /**
     * Displays the list of system users. (Admin only)
     * Route: /app/users (GET)
     */
    public function index(): void {
        $this->requireLogin();
        $this->requireAdmin(); // Only admins can view user list

        $pageTitle = "مدیریت کاربران";
        $users = [];
        $errorMessage = $this->getFlashMessage('user_error'); // Use specific key
        $successMessage = $this->getFlashMessage('user_success');

        try {
            // Fetch users with their role names (Assume repo method exists)
            $users = $this->userRepository->getAllUsersWithRole();
            // Escape data for display
            foreach ($users as &$user) {
                $user['username'] = Helper::escapeHtml($user['username']);
                $user['name'] = Helper::escapeHtml($user['name'] ?? '');
                $user['role_name'] = Helper::escapeHtml($user['role_name'] ?? 'نامشخص');
                $user['created_at_persian'] = $user['created_at'] ? Jalalian::fromFormat('Y-m-d H:i:s', $user['created_at'])->format('Y/m/d H:i') : '-';
            }
            unset($user);
            $this->logger->info("Users list fetched successfully.", ['count' => count($users)]);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching users list.", ['exception' => $e]);
            $errorMessage = $errorMessage ?: ['text' => "خطا در بارگذاری لیست کاربران."];
            if ($this->config['app']['debug']) { $errorMessage['text'] .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
            $users = [];
        }

        $this->render('users/list', [
            'page_title' => $pageTitle,
            'users' => $users,
            'error_msg' => $errorMessage ? $errorMessage['text'] : null,
            'success_msg' => $successMessage ? $successMessage['text'] : null,
        ]);
    }

    /**
     * Displays the form for adding a new user. (Admin only)
     * Route: /app/users/add (GET)
     */
    public function showAddForm(): void {
        $this->requireLogin();
        $this->requireAdmin();

        $pageTitle = "افزودن کاربر جدید";
        $roles = [];
        $loadingError = null;
        $formError = $this->getFlashMessage('form_error'); // Use shared key for form errors
        $sessionKey = 'user_add_data';
        $formData = $_SESSION[$sessionKey] ?? null;
        if ($formData) { unset($_SESSION[$sessionKey]); }

        // Fetch roles for dropdown
        try {
            $roles = $this->userRepository->getAllRoles(); // Assume repo method exists
            if (empty($roles)) { $loadingError = "نقشی در سیستم تعریف نشده."; }
        } catch (Throwable $e) {
            $this->logger->error("Error fetching roles for add user form.", ['exception' => $e]);
            $loadingError = "خطا در بارگذاری لیست نقش‌ها.";
        }

        // Default/Repopulated data
        $userData = [
            'id'        => null,
            'username'  => Helper::escapeHtml($formData['username'] ?? ''),
            'name'      => Helper::escapeHtml($formData['name'] ?? ''),
            'role_id'   => $formData['role_id'] ?? 2, // Default role ID (e.g., 2=Editor)
            'is_active' => $formData['is_active'] ?? 1, // Default active
        ];

        $this->render('users/form', [
            'page_title'          => $pageTitle,
            'form_action'         => $this->config['app']['base_url'] . '/app/users/save',
            'user'                => $userData,
            'roles'               => $roles,
            'is_edit_mode'        => false,
            'submit_button_text'  => 'ایجاد کاربر',
            'error_message'       => $formError ? $formError['text'] : null,
            'loading_error'       => $loadingError,
        ]);
    }

    /**
     * Displays the form for editing an existing user. (Admin only)
     * Route: /app/users/edit/{id} (GET)
     *
     * @param int $userId The ID of the user to edit.
     */
    public function showEditForm(int $userId): void {
        $this->requireLogin();
        $this->requireAdmin();

        // Prevent admin from fully editing self via this form (use profile page)
        if ($userId === ($_SESSION['user_id'] ?? null)) {
             $this->setSessionMessage('برای ویرایش اطلاعات خودتان، از صفحه پروفایل استفاده کنید.', 'info');
             $this->redirect('/app/profile'); // Redirect to profile
        }
         // Prevent editing the super admin (ID 1) by others? (Optional rule)
         // if ($userId === 1 && ($_SESSION['user_id'] ?? null) !== 1) { ... }


        $pageTitle = "ویرایش کاربر";
        $roles = [];
        $loadingError = null;
        $formError = $this->getFlashMessage('form_error');
        $userData = null;
        $sessionKey = 'user_edit_data_' . $userId;

        if ($userId <= 0) {
             $this->setSessionMessage('شناسه کاربر نامعتبر.', 'danger', 'user_error');
             $this->redirect('/app/users');
        }

        $sessionFormData = $_SESSION[$sessionKey] ?? null;
        if ($sessionFormData) {
             unset($_SESSION[$sessionKey]);
             $userData = $sessionFormData; // Use raw data from session
             $userData['id'] = $userId; // Ensure ID
             $pageTitle .= " (داده‌های اصلاح نشده)";
             $this->logger->debug("Repopulating edit user form from session.", ['user_id' => $userId]);
        } else {
            try {
                $userFromDb = $this->userRepository->getUserById($userId);
                if (!$userFromDb) {
                    $this->setSessionMessage('کاربر یافت نشد.', 'warning', 'user_error');
                    $this->redirect('/app/users');
                }
                $userData = [ // Prepare for form display
                    'id'        => (int)$userFromDb['id'],
                    'username'  => Helper::escapeHtml($userFromDb['username'] ?? ''),
                    'name'      => Helper::escapeHtml($userFromDb['name'] ?? ''),
                    'role_id'   => $userFromDb['role_id'] ?? null,
                    'is_active' => isset($userFromDb['is_active']) ? (int)$userFromDb['is_active'] : 1,
                ];
                $this->logger->debug("User data fetched from database.", ['user_id' => $userId]);
            } catch (Throwable $e) {
                $this->logger->error("Error loading user for editing.", ['user_id' => $userId, 'exception' => $e]);
                $loadingError = "خطا در بارگذاری اطلاعات کاربر.";
                $userData = ['id' => $userId]; // Minimal data
            }
        }

        // Fetch roles
        try {
            $roles = $this->userRepository->getAllRoles();
            if (empty($roles)) { $loadingError = ($loadingError ? $loadingError.'<br>':'') . "نقشی یافت نشد."; }
        } catch (Throwable $e) {
            $this->logger->error("Error fetching roles for edit user form.", ['exception' => $e]);
            $loadingError = ($loadingError ? $loadingError.'<br>':'') . "خطا در بارگذاری نقش‌ها.";
        }

        $this->render('users/form', [
            'page_title'          => $pageTitle,
            'form_action'         => $this->config['app']['base_url'] . '/app/users/save',
            'user'                => $userData,
            'roles'               => $roles,
            'is_edit_mode'        => true,
            'submit_button_text'  => 'به‌روزرسانی کاربر',
            'error_message'       => $formError ? $formError['text'] : null,
            'loading_error'       => $loadingError,
        ]);
    }


    /**
     * Processes the save request for a user (add or edit). (Admin only)
     * Route: /app/users/save (POST)
     */
    public function save(): void {
        $this->requireLogin();
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/users'); }
        
        // CSRF validation
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!Helper::verifyCsrfToken($submittedToken)) {
            $this->logger->error("CSRF token validation failed for user save.", ['user_id' => $userId]);
            $this->setSessionMessage(Helper::getMessageText('csrf_token_invalid'), 'danger', 'user_error');
            $this->redirect('/app/users');
        }

        // --- Input Extraction ---
        $userId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $isEditMode = ($userId !== null && $userId > 0);
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $roleId = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT) ?: null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $redirectUrlOnError = '/app/users/' . ($isEditMode ? 'edit/' . $userId : 'add');
        $sessionFormDataKey = $isEditMode ? 'user_edit_data_' . $userId : 'user_add_data';

        // --- Validation ---
        $errors = [];
        if (empty($username)) { $errors[] = 'نام کاربری الزامی است.'; }
        // Add username format validation if needed (e.g., alphanumeric)
        // elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) { $errors[] = 'فرمت نام کاربری نامعتبر.'; }

        if ($roleId === null) { $errors[] = 'نقش کاربری انتخاب شده نامعتبر است.'; }
        // Prevent changing role of super admin (ID 1) maybe?
        if ($isEditMode && $userId === 1 && $roleId !== 1) {
             // $errors[] = 'نقش کاربر ادمین اصلی قابل تغییر نیست.';
             // Silently ignore role change for admin 1? Or error? Let's ignore for now.
             $roleId = 1; // Force role back to admin
        }

        // Password validation (required on add, or if fields are filled on edit)
        $isPasswordChange = $isEditMode && (!empty($password) || !empty($confirmPassword));
        if (!$isEditMode || $isPasswordChange) { // If adding new OR changing password
            if (empty($password)) { $errors[] = 'رمز عبور الزامی است.'; }
            elseif ($password !== $confirmPassword) { $errors[] = 'رمز عبور و تکرار آن مطابقت ندارند.'; }
            elseif (strlen($password) < 6) { $errors[] = 'رمز عبور باید حداقل 6 کاراکتر باشد.'; }
            // Add password strength validation if desired
        }

        // Prevent deactivating super admin (ID 1)
        if ($isEditMode && $userId === 1 && $isActive === 0) {
            $errors[] = 'کاربر ادمین اصلی را نمی‌توان غیرفعال کرد.';
            $isActive = 1; // Force active
        }

        // --- Handle Validation Failure ---
        if (!empty($errors)) {
            $this->logger->warning("User save validation failed.", ['errors' => $errors, 'user_id' => $userId]);
            $this->setSessionMessage(implode('<br>', $errors), 'danger', 'form_error');
            $_SESSION[$sessionFormDataKey] = $_POST; // Repopulate raw POST
            $this->redirect($redirectUrlOnError);
        }

        // --- Prepare Data for Repository ---
        $userData = [
            'id'        => $isEditMode ? $userId : null,
            'username'  => $username,
            'name'      => $name ?: null, // Store null if empty? Or keep empty string? Depends on DB schema.
            'role_id'   => $roleId,
            'is_active' => $isActive,
        ];
        // Hash password only if adding new or changing
        if (!$isEditMode || $isPasswordChange) {
            try {
                // Use SecurityService for hashing (assuming it's injected or available via $this->services)
                // $securityService = $this->services['securityService']; // Example access
                // $userData['password_hash'] = $securityService->hashPassword($password);
                // Or use PHP's function directly if SecurityService isn't handling it
                 $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                 if ($passwordHash === false) throw new Exception("Password hashing failed.");
                 $userData['password_hash'] = $passwordHash;
            } catch (Throwable $e) {
                 $this->logger->error("Error hashing password during user save.", ['user_id' => $userId, 'exception' => $e]);
                 $this->setSessionMessage('خطا در پردازش رمز عبور.', 'danger', 'form_error');
                 $_SESSION[$sessionFormDataKey] = $_POST;
                 $this->redirect($redirectUrlOnError);
            }
        }

        // --- Database Operation ---
        try {
             // Check username uniqueness before saving (especially on edit if username changed)
             $existingUser = $this->userRepository->getUserByUsername($username);
             if ($existingUser && (!$isEditMode || (int)$existingUser['id'] !== $userId)) {
                  throw new Exception("نام کاربری '{$username}' قبلاً توسط کاربر دیگری استفاده شده است.");
             }

            $savedUserId = $this->userRepository->saveUser($userData); // Assumes repo handles INSERT/UPDATE
            $actionWord = $isEditMode ? 'به‌روزرسانی' : 'ایجاد';
            $this->logger->info("User saved successfully.", ['id' => $savedUserId, 'action' => $actionWord]);
            Helper::logActivity($this->db, "User {$actionWord}ed: {$username}", 'SUCCESS', "User ID: " . $savedUserId);

            $this->setSessionMessage("کاربر '{$username}' با موفقیت {$actionWord} شد.", 'success', 'user_success');
            $this->redirect('/app/users');

        } catch (Throwable $e) {
            $this->logger->error("Error saving user.", ['user_id' => $userId, 'exception' => $e]);
            $errorMessage = "خطا در ذخیره کاربر.";
            if ($e instanceof PDOException && $e->getCode() == '23000') { // Handle DB unique constraint
                 $errorMessage = "نام کاربری یا اطلاعات دیگر تکراری است.";
            } elseif ($this->config['app']['debug']) {
                 $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
            }
            $this->setSessionMessage($errorMessage, 'danger', 'form_error');
            $_SESSION[$sessionFormDataKey] = $_POST;
            $this->redirect($redirectUrlOnError);
        }
    }


    /**
     * Processes the user delete request. (Admin only)
     * Route: /app/users/delete/{id} (POST)
     *
     * @param int $userId The ID of the user to delete.
     */
    public function delete(int $userId): void {
        $this->requireLogin();
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/users'); }
        
        // CSRF validation
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!Helper::verifyCsrfToken($submittedToken)) {
            $this->logger->error("CSRF token validation failed for user delete.", ['user_id' => $userId]);
            $this->setSessionMessage(Helper::getMessageText('csrf_token_invalid'), 'danger', 'user_error');
            $this->redirect('/app/users');
        }

        if ($userId <= 0) {
             $this->setSessionMessage('شناسه کاربر نامعتبر.', 'danger', 'user_error');
             $this->redirect('/app/users');
        }
        // Prevent deleting super admin or self
        if ($userId === 1) { $this->setSessionMessage('کاربر ادمین اصلی قابل حذف نیست.', 'warning', 'user_error'); $this->redirect('/app/users'); }
        if ($userId === ($_SESSION['user_id'] ?? null)) { $this->setSessionMessage('شما نمی‌توانید حساب خودتان را حذف کنید.', 'warning', 'user_error'); $this->redirect('/app/users'); }

        $this->logger->info("Attempting delete.", ['user_id' => $userId]);

        try {
            $userToDelete = $this->userRepository->getUserById($userId); // Get username for logging
            if (!$userToDelete) { throw new Exception('کاربر یافت نشد.'); }
            $usernameToDelete = $userToDelete['username'];

            // Check dependencies (e.g., if user created transactions/logs - implement in repo)
            // if ($this->userRepository->hasAssociatedRecords($userId)) {
            //      throw new Exception("امکان حذف نیست: کاربر دارای سوابق مرتبط است.");
            // }

            $isDeleted = $this->userRepository->deleteUser($userId);
            if ($isDeleted) {
                $this->logger->info("User deleted.", ['user_id' => $userId, 'username' => $usernameToDelete]);
                Helper::logActivity($this->db, "User deleted: {$usernameToDelete}", 'SUCCESS', "User ID: " . $userId);
                $this->setSessionMessage("کاربر '{$usernameToDelete}' حذف شد.", 'success', 'user_success');
            } else {
                $this->logger->warning("User delete failed (not found?).", ['user_id' => $userId]);
                throw new Exception('کاربر یافت نشد یا حذف نشد.'); // Throw exception if delete returns false
            }
        } catch (Throwable $e) {
            $this->logger->error("Error deleting user.", ['user_id' => $userId, 'exception' => $e]);
            $errorMessage = "خطا در حذف کاربر.";
            if ($e instanceof PDOException && $e->getCode() == '23000') { $errorMessage = "امکان حذف نیست: کاربر به رکوردهای دیگر مرتبط است."; }
            elseif ($this->config['app']['debug']) { $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
            $this->setSessionMessage($errorMessage, 'danger', 'user_error');
        }
        $this->redirect('/app/users');
    }

     /**
      * Processes the request to toggle user active status. (Admin only)
      * Route: /app/users/toggle-active/{id} (POST)
      *
      * @param int $userId The ID of the user to toggle.
      */
     public function toggleActive(int $userId): void {
         $this->requireLogin();
         $this->requireAdmin();
         if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/users'); }
         
         // CSRF validation
         $submittedToken = $_POST['csrf_token'] ?? null;
         if (!Helper::verifyCsrfToken($submittedToken)) {
             $this->logger->error("CSRF token validation failed for user toggle active.", ['user_id' => $userId]);
             $this->setSessionMessage(Helper::getMessageText('csrf_token_invalid'), 'danger', 'user_error');
             $this->redirect('/app/users');
         }

         if ($userId <= 0) { $this->setSessionMessage('شناسه کاربر نامعتبر.', 'danger', 'user_error'); $this->redirect('/app/users'); }
         if ($userId === 1) { $this->setSessionMessage('وضعیت ادمین اصلی قابل تغییر نیست.', 'warning', 'user_error'); $this->redirect('/app/users'); }
         if ($userId === ($_SESSION['user_id'] ?? null)) { $this->setSessionMessage('شما نمی‌توانید وضعیت حساب خودتان را تغییر دهید.', 'warning', 'user_error'); $this->redirect('/app/users'); }

          $this->logger->info("Attempting toggle active status.", ['user_id' => $userId]);

         try {
             $userToToggle = $this->userRepository->getUserById($userId);
             if (!$userToToggle) { throw new Exception('کاربر یافت نشد.'); }

             $newStatusBool = !((bool)($userToToggle['is_active'] ?? false)); // Toggle status
             $newStatusInt = $newStatusBool ? 1 : 0;
             $newStatusFarsi = $newStatusBool ? 'فعال' : 'غیرفعال';

             $isUpdated = $this->userRepository->updateUserStatus($userId, $newStatusInt); // Assume repo expects int 0 or 1

             if ($isUpdated) {
                  $this->logger->info("User status toggled.", ['user_id' => $userId, 'username' => $userToToggle['username'], 'new_status' => $newStatusInt]);
                  Helper::logActivity($this->db, "User status toggled: {$userToToggle['username']} -> {$newStatusFarsi}", 'SUCCESS', "User ID: " . $userId);
                  $this->setSessionMessage("وضعیت کاربر '{$userToToggle['username']}' به {$newStatusFarsi} تغییر یافت.", 'success', 'user_success');
             } else {
                   throw new Exception('خطا در به‌روزرسانی وضعیت کاربر.');
             }
         } catch (Throwable $e) {
             $this->logger->error("Error toggling user active status.", ['user_id' => $userId, 'exception' => $e]);
             $errorMessage = "خطا در تغییر وضعیت کاربر.";
             if ($this->config['app']['debug']) { $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage()); }
             $this->setSessionMessage($errorMessage, 'danger', 'user_error');
         }
         $this->redirect('/app/users');
     }


    /**
     * Displays the profile page for the currently logged-in user.
     * Route: /app/profile (GET)
     */
    public function showProfile(): void {
        $this->requireLogin(); // Ensure user is logged in

        $pageTitle = "پروفایل کاربری";
        $userId = $_SESSION['user_id'];
        $userData = null;
        $loadingError = null;
        $formErrorPassword = $this->getFlashMessage('profile_password_error'); // Specific key for password errors
        $formSuccessMessage = $this->getFlashMessage('profile_success'); // Specific key for success

        try {
             $userFromDb = $this->userRepository->getUserById($userId);
             if (!$userFromDb) {
                  // Critical error: Logged-in user doesn't exist? Force logout.
                 $this->logger->critical("Logged-in user ID {$userId} not found in database!", ['session' => $_SESSION]);
                 $this->authService->logout($userId, $_SESSION['username'] ?? '?', $_SERVER['REMOTE_ADDR'] ?? '?'); // Attempt clean logout via service
                 // Force session destruction if still active
                 if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
                 $this->redirect('/login?error=session_invalid'); // Redirect with an error query param?
             }
             // Prepare user data (exclude sensitive info like password hash)
             $userData = [
                 'id'        => (int)$userFromDb['id'],
                 'username'  => Helper::escapeHtml($userFromDb['username'] ?? ''),
                 'name'      => Helper::escapeHtml($userFromDb['name'] ?? ''),
                 'role_id'   => $userFromDb['role_id'] ?? null, // Role ID might be needed
                 // 'role_name' => Helper::escapeHtml($userFromDb['role_name'] ?? ''), // Get role name if available from repo query
                 'is_active' => (int)($userFromDb['is_active'] ?? 1), // Should always be active if logged in
             ];
             $this->logger->debug("Profile data fetched.", ['user_id' => $userId]);

         } catch (Throwable $e) {
             $this->logger->error("Error loading profile data.", ['user_id' => $userId, 'exception' => $e]);
             $loadingError = "خطا در بارگذاری اطلاعات پروفایل.";
             $userData = ['id' => $userId]; // Minimal data
         }

        $this->render('users/profile', [
            'page_title'             => $pageTitle,
            'form_action_password'   => $this->config['app']['base_url'] . '/app/profile/save-password',
            'user'                   => $userData,
            'error_message_password' => $formErrorPassword ? $formErrorPassword['text'] : null,
            'success_message'        => $formSuccessMessage ? $formSuccessMessage['text'] : null,
            'loading_error'          => $loadingError,
        ]);
    }


    /**
     * Processes the request to change the logged-in user's password.
     * Route: /app/profile/save-password (POST)
     */
    public function savePassword(): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/app/profile'); }
        
        // CSRF validation
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!Helper::verifyCsrfToken($submittedToken)) {
            $this->logger->error("CSRF token validation failed for password change.", ['user_id' => $userId]);
            $this->setSessionMessage(Helper::getMessageText('csrf_token_invalid'), 'danger', 'profile_password_error');
            $this->redirect('/app/profile');
        }

        $userId = $_SESSION['user_id'];

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate input
        $errors = [];
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
             $errors[] = 'تمام فیلدهای رمز عبور (فعلی، جدید، تکرار) الزامی هستند.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'رمز عبور جدید و تکرار آن یکسان نیستند.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'رمز عبور جدید باید حداقل 6 کاراکتر باشد.';
        }
        // Add password strength validation if needed

        if (!empty($errors)) {
             $this->logger->warning("Password change validation failed.", ['user_id' => $userId, 'errors' => $errors]);
             $this->setSessionMessage(implode('<br>', $errors), 'danger', 'profile_password_error');
             $this->redirect('/app/profile');
        }

        // Process password change
        try {
            $currentHash = $this->userRepository->getUserPasswordHash($userId);
            if (!$currentHash) { throw new Exception('Hash رمز عبور فعلی یافت نشد.'); } // Should not happen

            // Verify current password (use SecurityService if available, otherwise direct call)
            // if (!$this->securityService->verifyPassword($currentPassword, $currentHash)) {
            if (!password_verify($currentPassword, $currentHash)) {
                 $this->logger->warning("Password change failed: Incorrect current password.", ['user_id' => $userId]);
                 throw new Exception('رمز عبور فعلی وارد شده صحیح نیست.');
            }

            // Hash the new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($newPasswordHash === false) { throw new Exception('خطا در پردازش رمز عبور جدید.'); }

            // Update password in DB
            $isUpdated = $this->userRepository->updateUserPassword($userId, $newPasswordHash);
            if (!$isUpdated) { throw new Exception('خطا در به‌روزرسانی رمز عبور در پایگاه داده.'); }

            // Success
            $this->logger->info("User changed password successfully.", ['user_id' => $userId]);
            Helper::logActivity($this->db, "User changed own password.", 'SUCCESS', "User ID: " . $userId);
            $this->setSessionMessage('رمز عبور شما با موفقیت تغییر یافت.', 'success', 'profile_success');

            // Optional: Force re-login after password change for security
            // $this->authService->logout(...);
            // $this->redirect('/login?relogin=1');

            $this->redirect('/app/profile');

        } catch (Throwable $e) {
             $this->logger->error("Error changing user password.", ['user_id' => $userId, 'exception' => $e]);
             $errorMessage = "خطای غیرمنتظره: " . Helper::escapeHtml($e->getMessage());
             if (!$this->config['app']['debug']) { // Provide less detail in production
                  if ($e->getMessage() === 'رمز عبور فعلی وارد شده صحیح نیست.') {
                       $errorMessage = $e->getMessage();
                  } else {
                       $errorMessage = "خطا در هنگام تغییر رمز عبور رخ داد.";
                  }
             }
             $this->setSessionMessage($errorMessage, 'danger', 'profile_password_error');
             $this->redirect('/app/profile');
        }
    }

} // End UserController class