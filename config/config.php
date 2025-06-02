<?php
return [
    // نام برنامه
    'app_name' => $_ENV['APP_NAME'] ?? 'حسابداری رایان طلا',
    'base_url' => $_ENV['BASE_URL'] ?? '/',
    'app_env' => $_ENV['APP_ENV'] ?? 'development',
    'app_debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'app_timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Tehran',

    // تنظیمات دیتابیس
    'database' => [
        'host'     => $_ENV['DB_HOST'] ?? 'localhost',
        'database' => $_ENV['DB_DATABASE'] ?? '',
        'username' => $_ENV['DB_USERNAME'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'port'     => (int)($_ENV['DB_PORT'] ?? 3306),
    ],

    // تنظیمات سشن
    'session' => [
        'save_path' => $_ENV['SESSION_SAVE_PATH'] ?? 'sessions',
        'gc_probability' => (int)($_ENV['SESSION_GC_PROBABILITY'] ?? 1),
        'gc_divisor' => (int)($_ENV['SESSION_GC_DIVISOR'] ?? 1000),
        'gc_maxlifetime' => (int)($_ENV['SESSION_GC_MAXLIFETIME'] ?? 1440),
        'cookie_lifetime' => (int)($_ENV['SESSION_COOKIE_LIFETIME'] ?? 0),
    ],

    // تنظیمات لاگ
    'log' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'DEBUG',
        'file'  => $_ENV['LOG_FILE'] ?? 'logs/application.log',
    ],

    // تنظیمات لایسنس
    'license' => [
        'secret_key' => $_ENV['LICENSE_SECRET_KEY'] ?? '',
        'api_url' => $_ENV['LICENSE_API_URL'] ?? '',
        'online_check_interval_days' => (int)($_ENV['LICENSE_ONLINE_CHECK_INTERVAL_DAYS'] ?? 7),
    ],

    // تنظیمات امنیتی
    'security' => [
        'encryption_key' => $_ENV['SECURITY_ENCRYPTION_KEY'] ?? '',
        'hardware_id_salt' => $_ENV['SECURITY_HARDWARE_ID_SALT'] ?? '',
        'request_code_salt' => $_ENV['SECURITY_REQUEST_CODE_SALT'] ?? '',
    ],

    // سایر تنظیمات
    'upload_path' => $_ENV['UPLOAD_PATH'] ?? 'uploads',
    'upload_max_size' => (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 10485760),
    'upload_allowed_types' => explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'image/jpeg,image/png,application/pdf'),
    'items_per_page' => (int)($_ENV['ITEMS_PER_PAGE'] ?? 15),
    'balance_threshold' => (float)($_ENV['BALANCE_THRESHOLD'] ?? 0.01),

    // لیست فایل‌های حساس
    'code_protection' => [
        'sensitive_files' => [
            'src/Core/LicenseService.php',
            'src/Services/SecurityService.php',
        ]
    ],
];