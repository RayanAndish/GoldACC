<?php
// public/index.php - Front Controller اصلی برنامه حسابداری طلا

declare(strict_types=1); // فعال کردن Strict Types برای بهبود کیفیت کد

// --- Maintenance Mode Check ---
// IMPORTANT: Adjust path if root is detected differently or if maintenance file is elsewhere
$maintenanceFile = dirname(__DIR__) . '/.maintenance'; // Assumes root is one level above public/
if (file_exists($maintenanceFile)) {
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    header('Status: 503 Service Temporarily Unavailable');
    header('Retry-After: 3600'); // Try again in 1 hour
    // Optional: Load a dedicated maintenance page
    // include(dirname(__DIR__) . '/views/maintenance.html');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>در حال به روز رسانی</title><style>body{font-family: sans-serif; background-color: #f1f1f1; padding: 50px; text-align: center;} h1{color: #555;} p{color: #777;}</style></head><body><h1>سامانه در حال به روز رسانی است</h1><p>لطفاً چند دقیقه دیگر دوباره تلاش کنید.</p></body></html>';
    exit;
}
// --- End Maintenance Mode Check ---

// --- سطح 0: راه‌اندازی اولیه و بارگذاری Autoloader ---

// تعریف مسیر ریشه پروژه (یک سطح بالاتر از public). مهم برای دسترسی به سایر پوشه‌ها.
define('ROOT_PATH', dirname(__DIR__));

// بارگذاری Autoloader تولید شده توسط Composer.
// در صورت عدم وجود، برنامه نمی تواند ادامه دهد.
if (!file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    // لاگ کردن این خطا بسیار دشوار است چون Logger هنوز راه اندازی نشده
    error_log("FATAL ERROR: Composer autoload file not found at " . ROOT_PATH . '/vendor/autoload.php');
    http_response_code(500);
    // پیام بسیار عمومی چون سیستم قادر به نمایش صفحه خطا نیست
    die("System configuration error. Please contact support.");
}
require ROOT_PATH . '/vendor/autoload.php';


// --- استفاده از کلاس‌هایی که در این فایل به آن‌ها نیاز داریم ---
// PSR Interfaces
use Psr\Log\LogLevel;

// Vendor Classes
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Dotenv\Dotenv;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
//use PDO;

// Core Classes
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\ViewRenderer;

// Utilities
use App\Utils\Helper; // فرض وجود کلاس Helper

// Repositories
use App\Repositories\ActivityLogRepository;
use App\Repositories\AssayOfficeRepository;
use App\Repositories\BankAccountRepository;
use App\Repositories\CoinInventoryRepository;
use App\Repositories\ContactRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\LicenseRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\UpdateHistoryRepository;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\InventoryLedgerRepository;
use App\Repositories\TransactionItemRepository;
use App\Repositories\InitialBalanceRepository;
use App\Repositories\InventoryCalculationRepository;

// Services
use App\Services\ApiClient;
use App\Services\AuthService;
use App\Services\BackupService;
use App\Services\DatabaseService;
use App\Services\DeliveryService;
use App\Services\LicenseService;
use App\Services\MonitoringService;
use App\Services\SecurityService;
use App\Services\UpdateService;
use App\Services\UserService; // اضافه شد
use App\Services\GoldPriceService;
use App\Services\InitialBalanceService;
use App\Services\InventoryCalculationService;

// Controllers (فقط نام کامل برای تعریف مسیر، نمونه سازی در زمان dispatch)
use App\Controllers\AbstractController;
use App\Controllers\ActivityLogsController;
use App\Controllers\AssayOfficeController;
use App\Controllers\AuthController;
use App\Controllers\BankAccountController;
use App\Controllers\CalculatorController;
use App\Controllers\CodeProtectionController;
use App\Controllers\ContactController;
use App\Controllers\DashboardController;
use App\Controllers\ErrorController;
use App\Controllers\HomeController; // اضافه شد
use App\Controllers\InventoryController;
use App\Controllers\InvoiceController;
use App\Controllers\LicenseController;
use App\Controllers\PaymentController;
use App\Controllers\SettingsController;
use App\Controllers\SystemController;
use App\Controllers\TransactionController;
use App\Controllers\UserController;
use App\Controllers\ProductCategoryController;
use App\Controllers\ProductController;
use App\Controllers\ApiController;
use App\Controllers\InitialBalanceController;

// --- سطح 1: بارگذاری متغیرهای محیطی و تنظیمات ---

// 1. بارگذاری متغیرهای محیطی از فایل .env
if (file_exists(ROOT_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(ROOT_PATH);
    $dotenv->load();
}

// 2. بارگذاری تنظیمات پروژه فقط از config.php
$config = require ROOT_PATH . '/config/config.php';

// 3. بارگذاری و ترکیب تنظیمات برنامه از فایل‌های config
$configFromFile = [];
if (file_exists(ROOT_PATH . '/config/database.php')) {
    $configFromFile = require ROOT_PATH . '/config/database.php';
     if (!is_array($configFromFile)) { $configFromFile = []; error_log("WARNING: config/database.php did not return an array."); }
}

// ترکیب نهایی تنظیمات با اولویت .env
// Database Config
$config['database'] = [
    'host'     => $_ENV['DB_HOST']     ?? $configFromFile['host']     ?? 'localhost',
    'database' => $_ENV['DB_DATABASE'] ?? $configFromFile['database'] ?? '',
    'username' => $_ENV['DB_USERNAME'] ?? $configFromFile['username'] ?? '',
    'password' => $_ENV['DB_PASSWORD'] ?? $configFromFile['password'] ?? '',
    'charset'  => $_ENV['DB_CHARSET']  ?? $configFromFile['charset']  ?? 'utf8mb4',
    'port'     => (int)($_ENV['DB_PORT'] ?? $configFromFile['port'] ?? 3306), // Cast to int
    'options'  => $configFromFile['options'] ?? [ // گزینه‌های PDO
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // تنظیمات مربوط به MySQL برای اطمینان از UTF8
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];

// App Config
$config['app'] = [
    'name'     => $_ENV['APP_NAME']     ?? $config['app_name']     ?? 'حسابداری رایان طلا',
    'env'      => $_ENV['APP_ENV']      ?? $config['app_env']      ?? 'development', // e.g., 'development', 'production'
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? ($config['app_debug'] ?? false), FILTER_VALIDATE_BOOLEAN),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? $config['app_timezone'] ?? 'Asia/Tehran',
    // 'base_url' will be calculated dynamically below
    'csrf_token_lifetime' => (int)($_ENV['CSRF_TOKEN_LIFETIME'] ?? ($config['csrf_token_lifetime'] ?? 3600)),
    'max_login_attempts'  => (int)($_ENV['MAX_LOGIN_ATTEMPTS']  ?? ($config['max_login_attempts']  ?? 5)),
    'login_block_time'    => (int)($_ENV['LOGIN_BLOCK_TIME']    ?? ($config['login_block_time']    ?? 900)), // seconds
    'upload_path' => $_ENV['UPLOAD_PATH'] ?? ($config['upload_path'] ?? ROOT_PATH . '/uploads'),
    'upload_max_size' => (int)($_ENV['UPLOAD_MAX_SIZE'] ?? ($config['upload_max_size'] ?? 10485760)), // 10MB default
    'upload_allowed_types' => is_array($_ENV['UPLOAD_ALLOWED_TYPES'] ?? ($config['upload_allowed_types'] ?? 'image/jpeg,image/png,application/pdf')) 
        ? $_ENV['UPLOAD_ALLOWED_TYPES'] ?? ($config['upload_allowed_types'] ?? 'image/jpeg,image/png,application/pdf')
        : explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? ($config['upload_allowed_types'] ?? 'image/jpeg,image/png,application/pdf')),
    'items_per_page' => (int)($_ENV['ITEMS_PER_PAGE'] ?? ($config['items_per_page'] ?? 15)),
    'balance_threshold' => (float)($_ENV['BALANCE_THRESHOLD'] ?? ($config['balance_threshold'] ?? 0.01)),
    'security' => [
          'encryption_key' => $_ENV['SECURITY_ENCRYPTION_KEY'] ?? ($config['security']['encryption_key'] ?? ''), // **بسیار مهم: باید در .env تنظیم شود**
          'hardware_id_salt' => $_ENV['SECURITY_HARDWARE_ID_SALT'] ?? ($config['security']['hardware_id_salt'] ?? 'default_salt_change_me_in_config'),
     ],
];

// Session Config
$config['session'] = [
    'save_path' => $_ENV['SESSION_SAVE_PATH'] ?? ($config['session_save_folder'] ?? ROOT_PATH . '/sessions'),
    'gc_probability' => (int)($_ENV['SESSION_GC_PROBABILITY'] ?? 1),
    'gc_divisor' => (int)($_ENV['SESSION_GC_DIVISOR'] ?? 1000),
    'gc_maxlifetime' => (int)($_ENV['SESSION_GC_MAXLIFETIME'] ?? 1440), // 24 minutes default
    'cookie_lifetime' => (int)($_ENV['SESSION_COOKIE_LIFETIME'] ?? 0), // 0 = until browser close
];

// Log Config
$config['log'] = [
    'path' => $_ENV['LOG_FILE'] ?? ($config['log_file'] ?? ROOT_PATH . '/logs/application.log'),
    'level' => $_ENV['LOG_LEVEL'] ?? ($config['log_level'] ?? 'DEBUG'), // DEBUG, INFO, WARNING, ERROR, CRITICAL
];

// License Config
$config['license'] = [
    'secret_key' => $_ENV['LICENSE_SECRET_KEY'] ?? ($config['license_secret_key'] ?? ''), // **بسیار مهم: باید در .env تنظیم شود**
    'api_url' => $_ENV['LICENSE_API_URL'] ?? ($config['license_api_url'] ?? ''), // **بسیار مهم: باید در .env تنظیم شود**
    'online_check_interval_days' => (int)($_ENV['LICENSE_ONLINE_CHECK_INTERVAL_DAYS'] ?? ($config['online_check_interval_days'] ?? 7)),
];

// Paths Config (calculated based on ROOT_PATH and other configs)
$config['paths'] = [
    'root'     => ROOT_PATH,
    'public'   => __DIR__,
    'config'   => ROOT_PATH . '/config',
    'src'      => ROOT_PATH . '/src',
    'views'    => ROOT_PATH . '/src/views',
    'layouts'  => ROOT_PATH . '/src/views/layouts', // Specific layout path
    'logs'     => dirname($config['log']['path']), // Get dir from log path
    'sessions' => $config['session']['save_path'],
    'uploads'  => $config['app']['upload_path'],
];
// Ensure upload URL is consistent with BASE_URL (calculated next)
$config['app']['upload_url'] = rtrim($_ENV['UPLOAD_URL'] ?? ($config['upload_url'] ?? ''), '/') . '/uploads'; // Base URL will be prepended later


// --- محاسبه BASE_URL پویا و تعیین URI برای مسیریابی ---
$scriptName = $_SERVER['SCRIPT_NAME']; // e.g., /public/index.php or /index.php
$requestUri = $_SERVER['REQUEST_URI']; // e.g., /public/login?foo=bar or /login?foo=bar

// حذف Query String
$uriPath = $requestUri;
if (false !== $pos = strpos($requestUri, '?')) {
    $uriPath = substr($requestUri, 0, $pos);
}
$uriPath = rawurldecode($uriPath); // Decode URL encoded chars

// محاسبه Base URL (مسیر ساب‌فولدر برنامه نسبت به Document Root)
$basePath = dirname($scriptName); // e.g., /public or /
// نرمال سازی Base Path (حذف بک اسلش در ویندوز، اطمینان از اسلش ابتدایی، حذف اسلش انتهایی)
$basePath = str_replace('\\', '/', $basePath);
if ($basePath === '/' || $basePath === '.') {
    $basePath = ''; // برنامه در روت است
} else {
    $basePath = rtrim($basePath, '/'); // e.g., /public
}

// حذف Base Path محاسبه شده از ابتدای URI درخواست برای به دست آوردن URI قابل استفاده توسط Router
$uri = $uriPath;
if (!empty($basePath) && strpos($uriPath, $basePath) === 0) {
    $uri = substr($uriPath, strlen($basePath));
}

// اطمینان از اینکه URI با اسلش شروع می‌شود (برای تطابق با Router)
if (empty($uri) || $uri[0] !== '/') {
    $uri = '/' . $uri; // e.g., /login or /
}

// ذخیره Base URL محاسبه شده در config برای استفاده در Controller ها و View ها
$config['app']['base_url'] = $basePath; // e.g., "" or "/public"
// Update upload URL based on calculated base URL
$config['app']['upload_url'] = $basePath . '/' . trim($config['app']['upload_url'] ?? 'uploads', '/');


// --- سطح 2: تنظیمات محیط اجرا، لاگ‌برداری و Session ---

// 3. تنظیم مدیریت خطاهای PHP (قبل از لاگر)
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');
ini_set('display_startup_errors', $config['app']['debug'] ? '1' : '0');
error_reporting(E_ALL);
date_default_timezone_set($config['app']['timezone']);

// 4. راه‌اندازی Monolog Logger
$logger = new Logger('App');
try {
    $logLevelString = strtoupper($config['log']['level']);
    // Map string level to Monolog Level constant
    $logLevel = match ($logLevelString) {
        'DEBUG' => Logger::DEBUG,
        'INFO' => Logger::INFO,
        'NOTICE' => Logger::NOTICE,
        'WARNING' => Logger::WARNING,
        'ERROR' => Logger::ERROR,
        'CRITICAL' => Logger::CRITICAL,
        'ALERT' => Logger::ALERT,
        'EMERGENCY' => Logger::EMERGENCY,
        default => Logger::DEBUG, // Default level
    };

    $logPath = $config['log']['path'];
    $logDir = dirname($logPath);
    if (!is_dir($logDir)) {
        if (!@mkdir($logDir, 0775, true) && !is_dir($logDir)) {
             // Cannot log this error using Monolog itself yet
             error_log("FATAL ERROR: Cannot create log directory: " . $logDir);
             http_response_code(500); die("System initialization error: Cannot create log directory.");
        }
        @chmod($logDir, 0775); // Set permissions if created
    }

    // Check if log file is writable (or directory if file doesn't exist yet)
    if ((file_exists($logPath) && !is_writable($logPath)) || (!file_exists($logPath) && !is_writable($logDir))) {
        error_log("FATAL ERROR: Log path is not writable: " . $logPath);
        http_response_code(500); die("System initialization error: Log path not writable.");
    }

    $formatter = new LineFormatter(
        "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
        'Y-m-d H:i:s', // Date format
        false, // Allow inline line breaks in message
        true   // Ignore empty context/extra
    );
    $streamHandler = new StreamHandler($logPath, $logLevel);
    $streamHandler->setFormatter($formatter);
    $logger->pushHandler($streamHandler);
    $logger->setTimezone(new \DateTimeZone($config['app']['timezone']));
    $logger->info("------------- Application Start -------------");
    $logger->info("Monolog Logger initialized.", ['level' => $logLevelString]);

} catch (Throwable $e) {
    error_log("FATAL ERROR: Failed during logger initialization: " . $e->getMessage());
    http_response_code(500); die("System initialization error: Cannot configure logging.");
}

// 5. راه‌اندازی Session ها
$sessionSavePath = $config['session']['save_path'];
if (!is_dir($sessionSavePath)) {
    if (!@mkdir($sessionSavePath, 0700, true) && !is_dir($sessionSavePath)) { // 0700 permissions for session path
        $logger->critical("FATAL ERROR: Cannot create session save path: " . $sessionSavePath, ['error' => error_get_last()]);
        http_response_code(500); die("System initialization error: Cannot create session directory.");
    }
    @chmod($sessionSavePath, 0700);
}
if (!is_writable($sessionSavePath) || !is_readable($sessionSavePath)) { // Check read permission too
     $logger->critical("FATAL ERROR: Session save path not readable/writable: " . $sessionSavePath);
     http_response_code(500); die("System initialization error: Session path permissions invalid.");
}

session_save_path($sessionSavePath);
ini_set('session.use_strict_mode', '1'); // Prevent session fixation attacks
ini_set('session.use_only_cookies', '1'); // Sessions only via cookies
ini_set('session.cookie_httponly', '1'); // Prevent JS access to session cookie
$secure_cookie = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
              || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
ini_set('session.cookie_secure', $secure_cookie ? '1' : '0'); // Send cookie only over HTTPS if available
ini_set('session.cookie_samesite', 'Lax'); // CSRF protection measure
ini_set('session.gc_probability', (string)$config['session']['gc_probability']);
ini_set('session.gc_divisor', (string)$config['session']['gc_divisor']);
ini_set('session.gc_maxlifetime', (string)$config['session']['gc_maxlifetime']);
ini_set('session.cookie_lifetime', (string)$config['session']['cookie_lifetime']); // Set cookie lifetime

if (session_status() === PHP_SESSION_NONE) {
    if(!session_start()){
        $logger->critical("FATAL ERROR: session_start() failed.", ['error' => error_get_last()]);
        http_response_code(500); die("System initialization error: Failed to start session.");
    } else {
        $logger->info("Session started successfully.", ['session_id' => session_id()]);
        // Regenerate ID periodically and on login/logout for security
        if (empty($_SESSION['last_regenerate']) || $_SESSION['last_regenerate'] < (time() - 1800)) { // Regenerate every 30 minutes
            session_regenerate_id(true);
            $_SESSION['last_regenerate'] = time();
            $logger->debug("Session ID regenerated periodically.");
        }
        // Ray ID for request tracing (optional)
        if (!isset($_SESSION['ray_id'])) {
            $_SESSION['ray_id'] = uniqid('Ray-' . str_replace(['.', ':'], '', $_SERVER['REMOTE_ADDR'] ?? 'unknown') . '-', true);
            $logger->debug("Ray ID set for session.", ['ray_id' => $_SESSION['ray_id']]);
        }
    }
}

// 6. ایجاد اتصال به پایگاه داده (Singleton)
// این بلوک باید قبل از ViewRenderer و Repositories و ErrorHandler باشد
$db = null; // PDO connection instance
try {
    // Pass config and logger ONCE. Ensure Database class uses these.
    $dbInstance = Database::getInstance($config['database'], $logger);
    $db = $dbInstance->getConnection(); // Get the active PDO connection
    $logger->info("Database connection verified.");
} catch (Throwable $e) {
    $logger->critical("FATAL ERROR: Failed to establish database connection.", ['exception' => (string)$e]);
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    $error_msg_db = "خطای اساسی در اتصال به پایگاه داده رخ داده است.";
    if ($config['app']['debug'] ?? false) {
        $error_msg_db .= "<br><pre>Detail (Debug Only): " . htmlspecialchars($e->getMessage()) . "</pre>";
    }
    die($error_msg_db);
}

// 7. ایجاد نمونه ViewRenderer (باید قبل از ErrorHandler و Controller ها باشد)
try {
    $viewRenderer = new ViewRenderer($config['paths']['views'], $config['paths']['layouts'], $logger); // Pass view, layout paths, and logger
    $logger->info("ViewRenderer initialized.");
} catch (Throwable $e) {
     $logger->critical("FATAL ERROR: Failed to initialize ViewRenderer.", ['exception' => $e]);
     if (ob_get_level() > 0) ob_end_clean();
     http_response_code(500);
     die("System initialization error: Cannot initialize ViewRenderer.");
}

// Initialize Repositories
// $productRepository = new ProductRepository($db, $logger); // REMOVED - Moved into services array
$contactRepository = new ContactRepository($db, $logger);
// $inventoryLedgerRepository = new InventoryLedgerRepository($db, $logger); // REMOVED - Moved into services array
// $userRepository = new UserRepository($db, $logger); // REMOVED - Moved into services array

// Initialize Services
$services = [];
try {
    // Repositories (mostly need DB and Logger)
    $services['activityLogRepository'] = new ActivityLogRepository($db, $logger);
    $services['assayOfficeRepository'] = new AssayOfficeRepository($db, $logger);
    $services['bankAccountRepository'] = new BankAccountRepository($db, $logger);
    $services['coinInventoryRepository'] = new CoinInventoryRepository($db, $logger);
    $services['contactRepository'] = new ContactRepository($db, $logger);
    $services['inventoryRepository'] = new InventoryRepository($db, $logger);
    $services['inventoryLedgerRepository'] = new InventoryLedgerRepository($db, $logger);
    $services['licenseRepository'] = new LicenseRepository($db, $logger);
    $services['paymentRepository'] = new PaymentRepository($db, $logger);
    $services['productRepository'] = new ProductRepository($db, $logger);
    $services['productCategoryRepository'] = new ProductCategoryRepository($db, $logger);
    $services['transactionItemRepository'] = new TransactionItemRepository($db, $logger);
    $services['transactionRepository'] = new TransactionRepository($db, $logger, $services['transactionItemRepository']);
    $services['userRepository'] = new UserRepository($db, $logger);
    $services['updateHistoryRepository'] = new UpdateHistoryRepository($db, $logger);
    $services['initialBalanceRepository'] = new InitialBalanceRepository($db, $logger);
    $services['settingsRepository'] = new SettingsRepository($db, $logger);
    $services['goldPriceService'] = new GoldPriceService($services['settingsRepository'], $logger);
    $services['inventoryCalculationRepository'] = new InventoryCalculationRepository($db, $logger);
    $logger->debug("Repositories initialized.");
    // ساخت ApiClient با وابستگی‌های جدید
    $services['apiClient'] = new ApiClient(
        $config['license']['api_url'] ?? '', // Get API URL from config
        $logger,
        $services['settingsRepository'],   // <--- پاس دادن نمونه SettingsRepository
        $config,                           // <--- پاس دادن آرایه کامل config
        null,                             // <--- SecurityService هنوز ساخته نشده
        15,                               // <--- timeout
        10                                // <--- connectTimeout
    );
    $logger->info("ApiClient initialized with new dependencies."); // لاگ برای تایید

    // Core Services (dependencies vary)
    // SecurityService بعد از ApiClient ساخته می‌شود
    $services['securityService'] = new SecurityService(
        $logger,
        $services['apiClient'],
        $db,
        $config
    );

    // به‌روزرسانی ApiClient با SecurityService
    $services['apiClient']->setSecurityService($services['securityService']);

    $services['licenseService'] = new LicenseService(
        $services['licenseRepository'],   // آرگومان اول: LicenseRepository
        $services['apiClient'],           // آرگومان دوم: ApiClient
        $services['securityService'],     // آرگومان سوم: SecurityService
        $services['settingsRepository'],  // آرگومان چهارم: SettingsRepository
        $logger,                          // آرگومان پنجم: Logger
        $config                           // آرگومان ششم: config
    );
    $services['authService'] = new AuthService( // Needs UserRepo, Security, Logger, Config
        $services['userRepository'],
        $services['securityService'],
        $logger,
        $config
    );
     $services['userService'] = new UserService( // Needs UserRepo, Security, Logger, Config
         $services['userRepository'],
         $services['securityService'],
         $logger,
         $config
     );
     $services['databaseService'] = new DatabaseService($db, $logger); // Needs DB, Logger (for optimize etc)
     $services['backupService'] = new BackupService(
         $db,
         $logger,
         $config,
         $config['paths']['root'], // از $config['paths'] استفاده شده که صحیح است
         $config['paths']['root'] . '/backups' // مسیر بکاپ
     );
     $services['deliveryService'] = new DeliveryService(
         $services['transactionRepository'],
         $services['inventoryRepository'],
         $services['coinInventoryRepository'],
         $logger,
         $db
     );
     $services['initialBalanceService'] = new InitialBalanceService(
         $services['initialBalanceRepository'],
         $services['inventoryRepository'],
         $services['transactionRepository'],
         $logger
     );
     $services['inventoryCalculationService'] = new InventoryCalculationService(
         $services['inventoryCalculationRepository'],
         $services['productRepository'],
         $logger
     );
     $services['updateService'] = new UpdateService(
         $services['apiClient'],
         $logger,
         $config,
         $services['settingsRepository'],
         $services['backupService'],
         $services['licenseService'],
         $config['paths']['root'],
         $services['updateHistoryRepository']
     );
     $services['monitoringService'] = new MonitoringService( // Needs many dependencies
         $services['apiClient'],
         $logger,
         $config,
         $services['activityLogRepository'],
         $services['userRepository'],
         $services['licenseService'],
         $services['securityService'],
         $services['updateService']
     );

     $logger->info("All Repositories and Services initialized.");

} catch (Throwable $e) {
     // این بخش برای گرفتن خطاهای زمان مقداردهی سرویس‌ها بسیار مهم است
     $logger->critical("FATAL ERROR: Failed to initialize core services or repositories.", ['exception' => (string)$e]); // تبدیل exception به string برای لاگ بهتر
     if (ob_get_level() > 0) ob_end_clean();
     http_response_code(500);
     // ErrorHandler isn't ready yet.
     // نمایش پیام خطا با جزئیات اگر debug فعال است
     $errorMessage = "System initialization error: Cannot initialize core application components.";
     if ($config['app']['debug'] ?? false) {
         $errorMessage .= "<br><pre><strong>Exception:</strong> " . get_class($e) . "<br><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " (" . $e->getLine() . ")" . "<br><strong>Trace:</strong><br>" . nl2br(htmlspecialchars($e->getTraceAsString())) . "</pre>";
     }
     die($errorMessage);
}


// --- سطح 4: راه‌اندازی Error Handler سراسری ---

// 9. نهایی کردن راه‌اندازی ErrorHandler (بعد از تمام وابستگی های اصلی)
try {
    // ErrorHandler needs DB, Logger, Debug status, Config, ViewRenderer, and Services array
    ErrorHandler::initialize($db, $logger, $config['app']['debug'], $config, $viewRenderer, $services);
    $logger->info("Global ErrorHandler initialized.");
} catch (Throwable $e) {
    $logger->critical("FATAL ERROR: Failed to initialize ErrorHandler.", ['exception' => $e]);
                   if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    die("System initialization error: Cannot initialize ErrorHandler.");
}

// --- سطح 4.5: بارگذاری سراسری تنظیمات Fields و Formulas ---
$config['app']['global_json_strings'] = ['fields' => 'null', 'formulas' => 'null', 'error' => null]; // Defaults

try {
    $fieldsJsonPath = $config['paths']['config'] . '/fields.json';
    $formulasJsonPath = $config['paths']['config'] . '/formulas.json';
    $jsonLoadError = null;

    if (file_exists($fieldsJsonPath) && is_readable($fieldsJsonPath)) {
        $fieldsJsonContent = file_get_contents($fieldsJsonPath);
        if ($fieldsJsonContent === false) {
            $jsonLoadError = "Failed to read fields.json.";
            $logger->error($jsonLoadError);
        } elseif (json_decode($fieldsJsonContent) === null && json_last_error() !== JSON_ERROR_NONE) {
            $jsonLoadError = "Invalid JSON in fields.json: " . json_last_error_msg();
            $logger->error($jsonLoadError);
        } else {
            $config['app']['global_json_strings']['fields'] = $fieldsJsonContent; // Store as string
            $logger->info("Successfully loaded and validated fields.json for global injection.");
        }
    } else {
        $jsonLoadError = "fields.json not found or not readable at: " . $fieldsJsonPath;
        $logger->error($jsonLoadError);
    }

    if (file_exists($formulasJsonPath) && is_readable($formulasJsonPath)) {
        $formulasJsonContent = file_get_contents($formulasJsonPath);
        if ($formulasJsonContent === false) {
            $jsonLoadError = ($jsonLoadError ? $jsonLoadError . " " : "") . "Failed to read formulas.json.";
            $logger->error("Failed to read formulas.json.");
        } elseif (json_decode($formulasJsonContent) === null && json_last_error() !== JSON_ERROR_NONE) {
            $jsonLoadError = ($jsonLoadError ? $jsonLoadError . " " : "") . "Invalid JSON in formulas.json: " . json_last_error_msg();
            $logger->error("Invalid JSON in formulas.json: " . json_last_error_msg());
        } else {
            $config['app']['global_json_strings']['formulas'] = $formulasJsonContent; // Store as string
            $logger->info("Successfully loaded and validated formulas.json for global injection.");
        }
    } else {
        $jsonLoadError = ($jsonLoadError ? $jsonLoadError . " " : "") . "formulas.json not found or not readable at: " . $formulasJsonPath;
        $logger->error("formulas.json not found or not readable at: " . $formulasJsonPath);
    }

    if ($jsonLoadError) {
        $config['app']['global_json_strings']['error'] = $jsonLoadError;
    }

} catch (Throwable $e) {
    $logger->error("Exception during global JSON config loading.", ['exception' => $e]);
    $config['app']['global_json_strings']['error'] = "PHP Exception during JSON load: " . $e->getMessage();
}
// --- پایان سطح 4.5 ---


// --- سطح 5: مسیریابی و Dispatch ---

$logger->debug("Starting routing process.", ['uri' => $uri, 'method' => $_SERVER['REQUEST_METHOD']]);

// 10. ایجاد Router Dispatcher با FastRoute
$dispatcher = FastRoute\simpleDispatcher(function(RouteCollector $r) use ($config) {
    // Helper to add routes with base path prefix if needed (though $uri already has it stripped)
    // $add = fn($method, $route, $handler) => $r->addRoute($method, $route, $handler);

    // --- Public Routes ---
    $r->addRoute('GET', '/', [HomeController::class, 'landing']); // <-- مسیر روت به لندینگ
    $r->addRoute('GET', '/landing', [HomeController::class, 'landing']); // مسیر جایگزین
    $r->addRoute('GET', '/about', [HomeController::class, 'about']);


    // --- Authentication & License ---
    $r->addRoute('GET', '/login', [AuthController::class, 'showLoginForm']);
    $r->addRoute('POST', '/login', [AuthController::class, 'handleLogin']);
    $r->addRoute('GET', '/logout', [AuthController::class, 'logout']);
    $r->addRoute(['GET', 'POST'], '/register', [AuthController::class, 'handleRegistration']); // Registration Route

    $r->addRoute('GET', '/activate', [LicenseController::class, 'showActivateForm']);
    $r->addRoute('POST', '/activate', [LicenseController::class, 'processActivation']);
    // API endpoints for license interaction (usually called via JS/AJAX)
    $r->addRoute('POST', '/api/license/validate', [LicenseController::class, 'validateLicenseApi']); // Validate license key from server
    // --- Application Routes (Require Login & Valid License - checked in Controllers/Middleware) ---
    // --- Application Routes (Require Login) ---
    $appPrefix = '/app'; // Optional prefix for app routes
    $r->addRoute('GET', '/dashboard', [DashboardController::class, 'index']); // <-- مسیر اصلی داشبورد
    $r->addRoute('GET', '/users', [UserController::class, 'index']);
    $r->addRoute('GET', $appPrefix.'/dashboard', [DashboardController::class, 'index']);

    // Users Management
    $r->addRoute('GET', $appPrefix.'/users', [UserController::class, 'index']);
    $r->addRoute('GET', $appPrefix.'/users/add', [UserController::class, 'showAddForm']);
    $r->addRoute('GET', $appPrefix.'/users/edit/{id:\d+}', [UserController::class, 'showEditForm']); // id must be digit
    $r->addRoute('POST', $appPrefix.'/users/save', [UserController::class, 'save']); // Handles both add & edit save
    $r->addRoute('POST', $appPrefix.'/users/delete/{id:\d+}', [UserController::class, 'delete']);
    $r->addRoute('POST', $appPrefix.'/users/toggle-active/{id:\d+}', [UserController::class, 'toggleActive']);
    $r->addRoute('GET', $appPrefix.'/profile', [UserController::class, 'showProfile']);
    $r->addRoute('POST', $appPrefix.'/profile/save-password', [UserController::class, 'savePassword']);

    // Assay Offices Management
    $r->addRoute('GET', $appPrefix.'/assay-offices', [AssayOfficeController::class, 'index']);
    $r->addRoute('GET', $appPrefix.'/assay-offices/add', [AssayOfficeController::class, 'showAddForm']);
    $r->addRoute('GET', $appPrefix.'/assay-offices/edit/{id:\d+}', [AssayOfficeController::class, 'showEditForm']);
    $r->addRoute('POST', $appPrefix.'/assay-offices/save', [AssayOfficeController::class, 'save']);
    $r->addRoute('POST', $appPrefix.'/assay-offices/delete/{id:\d+}', [AssayOfficeController::class, 'delete']);
    $r->addRoute('GET', $appPrefix.'/assay-offices/list', [AssayOfficeController::class, 'getList']); // اضافه کردن مسیر API

    // Bank Accounts Management
    $r->addRoute('GET', $appPrefix.'/bank-accounts', [BankAccountController::class, 'index']);
    $r->addRoute('GET', $appPrefix.'/bank-accounts/add', [BankAccountController::class, 'showAddForm']);
    $r->addRoute('GET', $appPrefix.'/bank-accounts/edit/{id:\d+}', [BankAccountController::class, 'showEditForm']);
    $r->addRoute('POST', $appPrefix.'/bank-accounts/save', [BankAccountController::class, 'save']);
    $r->addRoute('POST', $appPrefix.'/bank-accounts/delete/{id:\d+}', [BankAccountController::class, 'delete']);
    $r->addRoute('GET', $appPrefix.'/bank-accounts/ledger/{id:\d+}', [BankAccountController::class, 'showLedger']);
    $r->addRoute('GET', $appPrefix.'/bank-accounts/transactions', [BankAccountController::class, 'listTransactions']); // Maybe with filters?

    // Contacts Management
    $r->addRoute('GET', $appPrefix.'/contacts', [ContactController::class, 'index']);
    $r->addRoute('GET', $appPrefix.'/contacts/add', [ContactController::class, 'showAddForm']);
    $r->addRoute('GET', $appPrefix.'/contacts/edit/{id:\d+}', [ContactController::class, 'showEditForm']);
    $r->addRoute('POST', $appPrefix.'/contacts/save', [ContactController::class, 'save']);
    $r->addRoute('POST', $appPrefix.'/contacts/delete/{id:\d+}', [ContactController::class, 'delete']);
    $r->addRoute('GET', $appPrefix.'/contacts/ledger/{id:\d+}', [ContactController::class, 'showLedger']);

    // ProductCategory Management 
    $r->addRoute('GET', $appPrefix.'/product-categories', [ProductCategoryController::class, 'index']);
    $r->addRoute('GET', $appPrefix.'/product-categories/add', [ProductCategoryController::class, 'showAddForm']);
    $r->addRoute('GET', $appPrefix.'/product-categories/edit/{id:\d+}', [ProductCategoryController::class, 'showEditForm']);
    $r->addRoute('POST', $appPrefix.'/product-categories/save', [ProductCategoryController::class, 'save']);
    $r->addRoute('POST', $appPrefix.'/product-categories/delete/{id:\d+}', [ProductCategoryController::class, 'delete']);
    
    // Product Management
    $r->addRoute('GET', $appPrefix.'/products', [ProductController::class, 'index']);
    $r->addRoute('GET', $appPrefix.'/products/add', [ProductController::class, 'showAddForm']);
    $r->addRoute('GET', $appPrefix.'/products/edit/{id:\d+}', [ProductController::class, 'showEditForm']);
    $r->addRoute('POST', $appPrefix.'/products/store', [ProductController::class, 'save']);
    $r->addRoute('POST', $appPrefix.'/products/update/{id:\d+}', [ProductController::class, 'save']);
    $r->addRoute('POST', $appPrefix.'/products/delete/{id:\d+}', [ProductController::class, 'delete']);
    
    // Payments Management
    $r->addRoute('GET', $appPrefix.'/payments', [PaymentController::class, 'index']);
    $r->addRoute('GET', $appPrefix.'/payments/add', [PaymentController::class, 'showAddForm']);
    $r->addRoute('GET', $appPrefix.'/payments/edit/{id:\d+}', [PaymentController::class, 'showEditForm']);
    $r->addRoute('POST', $appPrefix.'/payments/save', [PaymentController::class, 'save']);
    $r->addRoute('POST', $appPrefix.'/payments/delete/{id:\d+}', [PaymentController::class, 'delete']);

    // Transactions Management
    $r->addRoute('GET', $appPrefix.'/transactions', [TransactionController::class, 'index']);
    $r->addRoute('GET', $appPrefix.'/transactions/add', [TransactionController::class, 'showAddForm']);
    $r->addRoute('GET', $appPrefix.'/transactions/edit/{id:\d+}', [TransactionController::class, 'showEditForm']);
    $r->addRoute('POST', $appPrefix.'/transactions/save', [TransactionController::class, 'save']);
    $r->addRoute('POST', $appPrefix.'/transactions/update/{id:\d+}', [TransactionController::class, 'save']);
    $r->addRoute('POST', $appPrefix.'/transactions/delete/{id:\d+}', [TransactionController::class, 'delete']);
    $r->addRoute('POST', $appPrefix.'/transactions/complete-delivery/{id:\d+}/{action:receipt|delivery}', [TransactionController::class, 'completeDeliveryAction']);

    // Inventory Management
    $r->addRoute('GET', $appPrefix.'/inventory', [InventoryController::class, 'index']);

    // Invoice Generation
    $r->addRoute(['GET', 'POST'], $appPrefix.'/invoice-generator', [InvoiceController::class, 'showGeneratorForm']); // Show form on GET, process on POST?
    $r->addRoute('POST', $appPrefix.'/invoice-preview', [InvoiceController::class, 'preview']); // Generate preview from POST data

    // Reports
    $r->addRoute('GET', $appPrefix.'/activity-logs', [ActivityLogsController::class, 'index']);
    // Add other report routes here

    // Calculator
    $r->addRoute('GET', $appPrefix.'/calculator', [CalculatorController::class, 'index']);

    // System Management (Requires Admin role - checked in Controller)
    $r->addRoute('GET', $appPrefix.'/system/overview', [SystemController::class, 'index']); // Default system page

    $r->addRoute('GET', $appPrefix.'/system/backup', [SystemController::class, 'index']);
    $r->addRoute('POST', $appPrefix.'/system/backup/run', [SystemController::class, 'runBackupAction']);
    $r->addRoute('POST', $appPrefix.'/system/backup/action', [SystemController::class, 'handleBackupAction']); // <-- اضافه کردن روت برای اکشن بکاپ
    //$r->addRoute('POST', $appPrefix.'/system/backup/delete', [SystemController::class, 'deleteBackupAction']); // Replaced by handleBackupAction
    //$r->addRoute('POST', $appPrefix.'/system/backup/restore', [SystemController::class, 'restoreBackupAction']); // Replaced by handleBackupAction
    $r->addRoute('GET', $appPrefix.'/system/maintenance', [SystemController::class, 'showMaintenanceSection']);
    $r->addRoute('POST', $appPrefix.'/system/optimize-db', [SystemController::class, 'optimizeDatabaseAction']);

    $r->addRoute('GET', $appPrefix.'/system/update', [SystemController::class, 'showUpdateSection']);
    $r->addRoute('POST', $appPrefix.'/system/update/check', [SystemController::class, 'checkUpdateAction']); // Check for updates via API
    $r->addRoute('POST', $appPrefix.'/system/update/apply', [SystemController::class, 'applyUpdateAction']); // Apply update

    // Code Protection (if applicable)
    $r->addRoute('GET', $appPrefix.'/code-protection', [CodeProtectionController::class, 'index']);
    $r->addRoute('POST', $appPrefix.'/code-protection', [CodeProtectionController::class, 'handlePostActions']);

    // Settings
    $r->addRoute('GET', $appPrefix.'/settings', [SettingsController::class, 'index']);
    $r->addRoute('POST', $appPrefix.'/settings/save', [SettingsController::class, 'save']);

    // Account Activation Route
    $r->addRoute('GET', '/activate-account/{code:.+}', [AuthController::class, 'activateAccount']);

    // --- Backup Management ---
    $r->addRoute('GET', $appPrefix.'/system/backups', [SystemController::class, 'backups']);
    $r->addRoute('GET', $appPrefix.'/system/create-backup', [SystemController::class, 'createBackup']);
    $r->addRoute('GET', $appPrefix.'/system/download-backup/{filename}', [SystemController::class, 'downloadBackup']);

    // --- API Endpoints (e.g., for AJAX calls from frontend) ---
    $r->addRoute('POST', '/api/send-activation-info', [ApiController::class, 'sendActivationInfo']); // مسیر جدید برای ارسال اطلاعات فعال‌سازی
    // Example: $r->addRoute('GET', '/api/contacts/search', [ContactController::class, 'searchApi']);

    // Initial Balance Routes
    $r->addRoute('GET', $appPrefix.'/initial-balance', [InitialBalanceController::class, 'index']);
    $r->addRoute('GET', $appPrefix.'/initial-balance/form', [InitialBalanceController::class, 'showForm']);
    $r->addRoute('POST', $appPrefix.'/initial-balance/save', [InitialBalanceController::class, 'save']);
    $r->addRoute('GET', $appPrefix.'/initial-balance/edit/{id}', [InitialBalanceController::class, 'edit']);
    $r->addRoute('POST', $appPrefix.'/initial-balance/update/{id}', [InitialBalanceController::class, 'update']);
    $r->addRoute('POST', $appPrefix.'/initial-balance/delete/{id}', [InitialBalanceController::class, 'delete']);

});


// 11. Dispatch کردن درخواست
$httpMethod = $_SERVER['REQUEST_METHOD'];
$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

// 12. پردازش نتیجه Dispatcher
switch ($routeInfo[0]) {
    case Dispatcher::NOT_FOUND:
        $logger->warning("Route not found.", ['method' => $httpMethod, 'uri' => $uri]);
        // Let ErrorHandler handle the 404 display
        // ErrorHandler::handleNotFound() is implicitly called via handleException
        http_response_code(404);
        throw new Exception("صفحه مورد نظر یافت نشد. ({$uri})", 404); // Throw exception for ErrorHandler

    case Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        $logger->warning("Method not allowed for route.", ['method' => $httpMethod, 'uri' => $uri, 'allowed' => $allowedMethods]);
        // Let ErrorHandler handle the 405 display
        http_response_code(405);
        // Create specific exception or use generic with code
        throw new Exception("متد {$httpMethod} برای این آدرس مجاز نیست.", 405); // Throw exception for ErrorHandler

    case Dispatcher::FOUND:
        $handler = $routeInfo[1]; // Controller class and method array, or Closure
        $vars = $routeInfo[2];    // URL parameters

        $logger->debug("Route found.", ['handler' => is_array($handler) ? implode('::', $handler) : (is_string($handler) ? $handler : 'Closure'), 'vars' => $vars]);

        // --- Pre-dispatch Checks (e.g., Middleware like License Check) ---
        // The commented-out license check logic here can be implemented as a middleware
        // or kept within specific controllers (like AuthController, AbstractController base checks).
        // For now, relying on checks within controllers.
        // $licenseService = $services['licenseService']; // Get service
        // if (/* is a protected route based on $handler or $uri */ && !$licenseService->checkLicense()['valid']) {
        //     // Redirect to activation or throw error
        // }
        // --- End Pre-dispatch Checks ---


        // --- Execute Handler ---
        try {
            // If handler is a Closure
            if ($handler instanceof Closure) {
                $logger->debug("Executing closure handler.");
                // Execute closure, passing URL vars. Dependencies must be 'use'd in the closure definition.
                echo call_user_func_array($handler, $vars); // Echo return value if any

            }
            // If handler is [ControllerClass, method]
            elseif (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
                $controllerClass = $handler[0];
                $method = $handler[1];

                if (!class_exists($controllerClass)) {
                    throw new Exception("Controller class '{$controllerClass}' not found.");
                }
                 if (!method_exists($controllerClass, $method)) {
                     throw new Exception("Method '{$method}' not found in controller '{$controllerClass}'.");
                 }

                 // --- Instantiate Controller with Manual Dependency Injection ---
                 // This part requires careful maintenance. Ensure all dependencies for each controller are listed correctly.
                 $logger->debug("Instantiating controller '{$controllerClass}' with dependencies.");
                 if (!isset($services) || !is_array($services)) {
                      throw new Exception("Services array is not defined before controller instantiation.");
                 }

                 // Define common dependencies needed by AbstractController
                 $commonDependencies = [$db, $logger, $config, $viewRenderer, $services]; // Pass the whole services array

                 // Specific dependencies per controller
                 $controllerDependencies = [];
                 switch ($controllerClass) {
                     case InitialBalanceController::class:
                         $controllerDependencies = [
                             $services['initialBalanceRepository'],
                             $services['productRepository'],
                             $services['bankAccountRepository'],
                             $services['initialBalanceService'],
                             $services['inventoryCalculationService'],
                             $logger,
                             $db
                         ];
                         break;
                     default:
                         $controllerDependencies = $commonDependencies;
                         break;
                 }
                 $controllerInstance = new $controllerClass(...$controllerDependencies);


                 // --- Call the controller method ---
                 $logger->debug("Calling method '{$method}' on controller '{$controllerClass}'.", ['vars' => $vars]);
                 // تبدیل id به int اگر وجود دارد و عددی است
                 if (isset($vars['id']) && is_numeric($vars['id'])) {
                     $vars['id'] = (int)$vars['id'];
                 }
                 // Call the method, passing URL parameters from the route match
                 // Use array_values to ensure parameters are passed positionally
                 echo call_user_func_array([$controllerInstance, $method], array_values($vars)); // Echo return value if any

        } else {
                 // Invalid handler format defined in routes
                 throw new Exception("Invalid handler format defined for route: " . json_encode($handler));
            }

            $logger->info("Request handled successfully.", ['handler' => is_array($handler) ? implode('::', $handler) : 'Closure']);

        } catch (Throwable $e) {
             // Catch ANY exception or error during controller execution
             $logger->error("Exception during controller execution.", ['exception' => $e]);
             // Let the global ErrorHandler handle display and final logging
             ErrorHandler::handleException($e); // This will typically display an error page and exit.
        }
        break; // End of Dispatcher::FOUND case
}

$logger->info("------------- Application End -------------");