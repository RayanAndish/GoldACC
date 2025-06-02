<?php
/**
 * Template: src/views/auth/activation_status.php
 * Displays the result of the account activation attempt.
 * Receives data via $viewData from AuthController::activateAccount.
 */

use App\Utils\Helper;

$pageTitle = $viewData['page_title'] ?? 'وضعیت فعال‌سازی';
$statusMessage = $viewData['status_message'] ?? 'وضعیت نامشخص.';
$messageType = $viewData['message_type'] ?? 'info'; // e.g., 'success', 'danger', 'info'
$showLoginLink = $viewData['show_login_link'] ?? false;
$appName = $viewData['appName'] ?? 'حسابداری رایان طلا';
$baseUrl = $viewData['baseUrl'] ?? '/';

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Helper::escapeHtml($pageTitle); ?> | <?php echo Helper::escapeHtml($appName); ?></title>
    <base href="<?php echo Helper::escapeHtml(rtrim($baseUrl, '/') . '/'); ?>/">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css"> <?php // Include main style for consistency? ?>
    <style>
         /* Load Vazirmatn font */
        @font-face { font-family: 'Vazirmatn'; src: url('...') format('woff2 supports variations'), url('...') format('woff2-variations'); font-weight: 100 900; font-style: normal; font-display: swap; }
        body { font-family: 'Vazirmatn', sans-serif !important; background-color: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .status-container { text-align: center; max-width: 500px; background-color: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .alert { text-align: center; font-size: 1.1em; }
        .login-link { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="status-container">
        <h2><?php echo Helper::escapeHtml($pageTitle); ?></h2>
        <hr class="my-4">
        <div class="alert alert-<?php echo Helper::escapeHtml($messageType); ?>" role="alert">
            <?php echo Helper::escapeHtml($statusMessage); ?>
        </div>

        <?php if ($showLoginLink): ?>
            <div class="login-link">
                <a href="<?php echo $baseUrl; ?>/login" class="btn btn-primary">ورود به حساب کاربری</a>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>