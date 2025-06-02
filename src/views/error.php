<?php
/**
 * Template: src/views/error.php
 * Displays error pages (404, 405, 500, etc.)
 * Receives data via $viewData array from ErrorController.
 */

use App\Utils\Helper;

$pageTitle = $viewData['page_title'] ?? 'خطا';
$errorCode = $viewData['error_code'] ?? '404';
$errorMessage = $viewData['error_message'] ?? 'صفحه مورد نظر یافت نشد.';
$appName = $viewData['appName'] ?? 'حسابداری رایان طلا';
$baseUrl = $viewData['baseUrl'] ?? '/';

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Helper::escapeHtml($pageTitle); ?> | <?php echo Helper::escapeHtml($appName); ?></title>
    <base href="<?php echo Helper::escapeHtml(rtrim($baseUrl, '/') . '/'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        @font-face {
            font-family: 'Vazirmatn';
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Round-Dots/fonts/webfonts/Vazirmatn-RD[wght].woff2') format('woff2 supports variations'),
                 url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Round-Dots/fonts/webfonts/Vazirmatn-RD[wght].woff2') format('woff2-variations');
            font-weight: 100 900;
            font-style: normal;
            font-display: swap;
        }
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif !important;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
        }
        .error-code {
            font-size: 6rem;
            font-weight: bold;
            color: #dc3545;
            margin: 0;
            line-height: 1;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .error-message {
            font-size: 1.25rem;
            color: #6c757d;
            margin: 1rem 0 2rem;
        }
        .error-actions {
            margin-top: 2rem;
        }
        .error-actions .btn {
            margin: 0.5rem;
            padding: 0.5rem 1.5rem;
        }
        .error-details {
            margin-top: 2rem;
            padding: 1rem;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: right;
            direction: rtl;
        }
        .error-details pre {
            margin: 0;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            overflow-x: auto;
            text-align: left;
            direction: ltr;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <?php if ($errorCode === '404'): ?>
                <i class="fas fa-exclamation-circle"></i>
            <?php elseif ($errorCode === '405'): ?>
                <i class="fas fa-ban"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-triangle"></i>
            <?php endif; ?>
        </div>
        <h1 class="error-code"><?php echo Helper::escapeHtml($errorCode); ?></h1>
        <p class="error-message"><?php echo Helper::escapeHtml($errorMessage); ?></p>
        
        <div class="error-actions">
            <a href="<?php echo $baseUrl; ?>" class="btn btn-primary">
                <i class="fas fa-home me-1"></i> بازگشت به صفحه اصلی
            </a>
            <?php if ($errorCode === '404'): ?>
                <button onclick="window.history.back()" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right me-1"></i> بازگشت به صفحه قبل
                </button>
            <?php endif; ?>
        </div>

        <?php if (isset($viewData['debug_info']) && $this->config['app']['debug']): ?>
            <div class="error-details">
                <h5 class="mb-3">اطلاعات خطا (فقط در حالت توسعه):</h5>
                <pre><?php echo Helper::escapeHtml(print_r($viewData['debug_info'], true)); ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 