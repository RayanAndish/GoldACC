<?php

namespace App\Controllers;

use PDO;
use Monolog\Logger;
use Throwable;
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;
use App\Services\AuthService;
use App\Services\LicenseService;
// FIX: اضافه کردن use statement های لازم
use App\Repositories\UserRepository;
use App\Services\UserService;

class AuthController extends AbstractController {
    
    // FIX: تعریف پراپرتی‌های مورد نیاز برای این کنترلر
    protected UserRepository $userRepository;
    protected UserService $userService;

    /**
     * FIX: سازنده اصلاح شده برای مقداردهی وابستگی‌های خاص این کنترلر
     */
    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);

        // FIX: مقداردهی پراپرتی‌ها از کانتینر سرویس‌ها
        if (!isset($services['userRepository']) || !$services['userRepository'] instanceof UserRepository) {
            throw new \Exception('UserRepository not found for AuthController.');
        }
        $this->userRepository = $services['userRepository'];
        
        if (!isset($services['userService']) || !$services['userService'] instanceof UserService) {
            throw new \Exception('UserService not found for AuthController.');
        }
        $this->userService = $services['userService'];
        
        $this->logger->debug("AuthController initialized correctly.");
    }
    
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
                // FIX: استفاده صحیح از پراپرتی تعریف شده
                $activationResult = $this->userRepository->activateUserByCode($activationCode);

                if ($activationResult === 'success') {
                    $message = 'حساب کاربری شما با موفقیت فعال شد. اکنون می‌توانید وارد شوید.';
                    $messageType = 'success';
                    $this->logger->info("Account activated successfully.", ['code_prefix' => substr($activationCode, 0, 5)]);
                } elseif ($activationResult === 'not_found') {
                    $message = 'کد فعال‌سازی نامعتبر یا منقضی شده است.';
                    $this->logger->warning("Account activation failed: Code not found.", ['code_prefix' => substr($activationCode, 0, 5)]);
                } else { // 'already_active'
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

        $this->render('auth/activation_status', [
            'page_title' => $pageTitle,
            'status_message' => $message,
            'message_type' => $messageType,
            'show_login_link' => ($messageType === 'success' || $messageType === 'info')
        ], false);
    }

    public function handleRegistration(): void {
        if ($this->isLoggedIn()) {
            $this->redirect('/app/dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->logger->info("Processing registration form submission.");
            
            try {
                // FIX: استفاده از UserService برای منطق ثبت‌نام
                // فرض بر این است که متد registerUser در UserService پیاده‌سازی شده و آرایه POST را دریافت می‌کند
                $newUserId = $this->userService->registerUser($_POST);
                $this->setSessionMessage('ثبت نام شما با موفقیت انجام شد. لطفا وارد شوید.', 'success', 'login_success');
                $this->redirect('/login');

            } catch (Exception $e) {
                $this->logger->warning("Registration failed.", ['error' => $e->getMessage()]);
                $this->setSessionMessage($e->getMessage(), 'danger', 'register_error');
                $_SESSION['_flash']['register_old_input'] = ['username' => $_POST['username'] ?? '', 'email' => $_POST['email'] ?? ''];
                $this->redirect('/register');
            }

        } else {
            $this->logger->debug("Displaying registration form.");
            $this->render('auth/register', [
                'page_title' => 'ثبت نام کاربر جدید',
                'error' => $this->getFlashMessage('register_error')['text'] ?? null,
                'errors' => $_SESSION['_flash']['register_errors'] ?? [],
                'oldInput' => $_SESSION['_flash']['register_old_input'] ?? [],
            ], false);
            // پاک کردن داده‌های فلش سشن پس از استفاده
            unset($_SESSION['_flash']['register_errors'], $_SESSION['_flash']['register_old_input']);
        }
    }
    
    public function showLoginForm(): void {
        if ($this->isLoggedIn()) {
            $this->redirect('/app/dashboard');
        }
        $loginError = $this->getFlashMessage('login_error');
        $loginSuccess = $this->getFlashMessage('login_success');
        $this->render('auth/login', [
            'page_title' => 'ورود به سیستم',
            'error' => $loginError['text'] ?? null,
            'success' => $loginSuccess['text'] ?? null,
        ], false);
    }

    public function handleLogin(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/login');
        }
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($username) || empty($password)) {
            $this->setSessionMessage('نام کاربری و رمز عبور الزامی است.', 'danger', 'login_error');
            $this->redirect('/login');
        }
        try {
            $loginResult = $this->authService->login($username, $password, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if ($loginResult['success']) {
                $user = $loginResult['user'];
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role_id'] ?? null;
                $_SESSION['is_logged_in'] = true;
                $this->redirect('/app/dashboard');
            } else {
                $this->setSessionMessage($loginResult['message'] ?? 'نام کاربری یا رمز عبور اشتباه است.', 'danger', 'login_error');
                $this->redirect('/login');
            }
        } catch (Throwable $e) {
            $this->logger->error("Exception during login process.", ['username' => $username, 'exception' => $e]);
            $this->setSessionMessage('خطای سیستمی در هنگام ورود رخ داد. لطفا با پشتیبانی تماس بگیرید.', 'danger', 'login_error');
            $this->redirect('/login');
        }
    }

    public function logout(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();
        }
        $this->setSessionMessage('شما با موفقیت از حساب کاربری خود خارج شدید.', 'success', 'login_success');
        $this->redirect('/login');
    }
}