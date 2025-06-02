<?php
// Namespace برای Helper باید در ابتدای فایل view include یا use شود،
// اما چون این فایل ممکن است در زمان خطا include شود، از مسیر کامل استفاده می کنیم.
use App\Utils\Helper; // اضافه کردن use

// استفاده از متغیرهای ارسالی از کنترلر
$page_title = $page_title ?? Helper::getMessageText('server_error', 'خطای سرور');
$app_name = $app_name ?? 'حسابداری رایان طلا';
$is_logged_in = $is_logged_in ?? false;
$base_url = $base_url ?? '/';
$error_message = $error_message ?? Helper::getMessageText('server_error_details', 'خطای داخلی سرور رخ داده است.');
$error_details = $error_details ?? '';
$is_debug = $is_debug ?? false;

// لینک بازگشت بر اساس وضعیت لاگین
$back_link = $is_logged_in ? ($base_url . '/') : ($base_url . '/login');
$back_link_text = $is_logged_in ? Helper::getMessageText('dashboard', 'داشبورد') : Helper::getMessageText('login_page', 'صفحه ورود');

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= Helper::escapeHtml($page_title) ?> | <?= Helper::escapeHtml($app_name) ?></title> <!-- استفاده از Helper -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="css/all.min.css"/>
    <link rel="stylesheet" href="css/style.css">
   <style>
        body {
            font-family: 'Vazirmatn', sans-serif !important;
            background-color: #f0f2f5; /* Light grey background */
            display: flex;
            align-items: center; /* Vertically center */
            justify-content: center; /* Horizontally center */
            min-height: 100vh;
            margin: 0;
        }
     </style>
</head>      
<body class="bg-light">

<div class="container py-5 d-flex align-items-center justify-content-center" style="min-height: 100vh">
    <div class="row justify-content-center w-100">
        <div class="col-md-10 col-lg-8 col-xl-7">
            <div class="card shadow rounded-4 border-danger">
                <div class="card-body text-center p-5">
                    <h1 class="display-5 text-danger fw-bold mb-2">خطای سرور-500</h1>
                    <h4 class="mb-4"><?= Helper::escapeHtml($page_title) ?></h4> <!-- استفاده از Helper -->
                    <p class="text-muted mb-4">
                        <?= Helper::escapeHtml($error_message) ?> <!-- استفاده از Helper -->
                    </p>
                    <div class="d-grid gap-2 d-sm-flex justify-content-center">
                        <a href="<?= Helper::escapeHtml($back_link) ?>" class="btn btn-danger px-4 btn-lg">بازگشت به <?= Helper::escapeHtml($back_link_text) ?></a> <!-- استفاده از Helper -->
                        <a href="javascript:history.back()" class="btn btn-outline-secondary px-4 btn-lg">بازگشت به صفحه قبلی</a>
                    </div>

                    <?php if ($is_debug && !empty($error_details)): ?>
                        <div class="debug-details">
                            <?= $error_details /* جزئیات خطا از قبل escape شده اند */ ?>
                        </div>
                    <?php endif; ?>

                </div>
                <div class="card-footer text-center bg-white small text-muted py-2">
                    © <?= date('Y') ?> <?= Helper::escapeHtml($app_name) ?> <!-- استفاده از Helper -->
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
