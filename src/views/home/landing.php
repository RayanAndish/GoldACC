<?php
/**
 * Template: src/views/home/landing.php
 * The main landing page of the application.
 * Receives data via $viewData array from HomeController::landing.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'خوش آمدید';
$appName = $viewData['appName'] ?? ' حسابداری رایان طلا';
$baseUrl = $viewData['baseUrl'] ?? ''; // Base URL from config

// Get potential flash message (e.g., license error redirected here)
// Use a specific key if LicenseController sets one, otherwise default
$flashMessage = $viewData['flashMessage'] ?? ($viewData['flash_license_message'] ?? null);

// Start session if not already started (needed for flash messages and Ray ID)
if (session_status() == PHP_SESSION_NONE) {
    // Warning: Starting session here might be too late for some operations.
    // It's better if the Front Controller always starts it.
    session_start();
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Helper::escapeHtml($pageTitle); ?> | <?php echo Helper::escapeHtml($appName); ?></title>
    <base href="<?php echo Helper::escapeHtml(rtrim($baseUrl, '/') . '/'); ?>/">

    <?php // --- CSS --- ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .landing-container {
            display: flex;
            flex-direction: row; /* تصویر در سمت چپ */
            align-items: center;
            justify-content: space-between;
            padding: 30px;
            background-color: #f8f9fa;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            margin: auto;
        }
        .landing-image {
            flex: 1;
            margin-left: 20px; /* فاصله بین تصویر و متن */
        }
        .landing-image img {
            max-width: 300px;
            border-radius: 10px;
        }
        .landing-content {
            flex: 2;
            text-align: justify;
        }
        .landing-content h1 {
            font-size: 2rem;
            color: #333;
        }
        .landing-content p {
            color: #555;
            font-size: 1.2rem;
            line-height: 1.8;
        }
        .buttons {
            margin-top: 20px;
            display: flex; /* دکمه‌ها در یک ردیف */
            gap: 10px;
            flex-wrap: nowrap; /* جلوگیری از ردیف شدن دکمه‌ها */
        }
        .btn {
            padding: 10px 20px;
            color: #fff;
            background-color: #007bff;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body class="landing-page">

    <?php // --- Flash Message Display --- ?>
    <?php if ($flashMessage && isset($flashMessage['text'])): ?>
        <div class="flash-messages-container position-absolute top-0 start-50 translate-middle-x pt-3" style="z-index: 1050; width: 90%; max-width: 600px;">
            <div class="alert alert-<?php echo Helper::escapeHtml($flashMessage['type'] ?? 'warning'); ?> alert-dismissible fade show shadow-sm" role="alert">
                <?php echo Helper::escapeHtml($flashMessage['text']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
    <?php // --- End Flash Message Display --- ?>

    <div class="landing-container">
        <!-- تصویر سمت چپ -->
        <div class="landing-image">
            <img src="images/gold.jpg" alt="تصویر طلا">
        </div>
        <!-- محتوای متن سمت راست -->
        <div class="landing-content">
            <h1><?php echo Helper::escapeHtml($appName); ?></h1>
            <p>
                سامانه‌ای امن، مدرن و کارآمد برای مدیریت دقیق معاملات و محاسبه سود و زیان دارایی‌های طلای شما.
            </p>
            <div class="buttons">
                <?php
                    $loginRoute = $baseUrl . '/login';
                    $registerRoute = $baseUrl . '/register';
                    $aboutRoute = $baseUrl . '/about';
                ?>
                <a href="<?php echo Helper::escapeHtml($loginRoute); ?>" class="btn">
                   <i class="fas fa-sign-in-alt"></i> ورود به سامانه
                </a>
                <a href="<?php echo Helper::escapeHtml($registerRoute); ?>" class="btn">
                   <i class="fas fa-user-plus"></i> ثبت نام
                </a>
                <a href="<?php echo Helper::escapeHtml($aboutRoute); ?>" class="btn">
                   <i class="fas fa-info-circle"></i> درباره ما
                </a>
            </div>
        </div>
    </div>

    <?php // --- Footer --- ?>
    <footer class="landing-footer">
        <div class="container">
            <small>
                 © <?php echo date('Y'); ?> <?php echo Helper::escapeHtml($appName); ?> | تمامی حقوق محفوظ است.
                <span class="text-separator">|</span>
                 <a href="https://www.rar-co.ir/" target="_blank">شرکت رایان اندیش رشد</a>
             </small>
        </div>
    </footer>

    <script src="<?php echo $baseUrl; ?>/js/bootstrap.bundle.min.js"></script>
</body>
</html>