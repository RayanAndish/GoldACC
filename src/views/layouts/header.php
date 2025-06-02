<?php
/**
 * Template: src/views/layouts/header.php
 * Main application header and navigation bar.
 */

use App\Utils\Helper;

// --- Extract common data from $viewData with defaults ---
$appName = $viewData['appName'] ?? 'حسابداری رایان طلا';
$baseUrl = $viewData['baseUrl'] ?? '';
$pageTitle = $viewData['pageTitle'] ?? 'Undefined';
$isLoggedIn = $viewData['isLoggedIn'] ?? false;
$loggedInUser = $viewData['loggedInUser'] ?? null;
$currentUri = $viewData['currentUri'] ?? '/';
$flashMessage = $viewData['flashMessage'] ?? null;
$flashLicenseMessage = $viewData['flash_license_message'] ?? null;

$userDisplayName = 'کاربر';
if ($loggedInUser) {
    $userDisplayName = $loggedInUser['name'] ?: $loggedInUser['username'];
}

if (!function_exists('isActive')) {
    function isActive(string $currentUri, array|string $paths): string {
        $paths = is_array($paths) ? $paths : [$paths];
        foreach ($paths as $path) {
            if (str_starts_with($currentUri, $path)) {
                return 'active';
            }
        }
        return '';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Helper::escapeHtml($pageTitle); ?> | <?php echo Helper::escapeHtml($appName); ?></title>
    <base href="<?php echo Helper::escapeHtml(rtrim($baseUrl, '/') . '/'); ?>/">
    
    <!-- FIX: Changed CDN links to local paths -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/jalalidatepicker.min.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $baseUrl; ?>/css/toastify.min.css">

</head>
<body id="app-body">

<?php if ($isLoggedIn): ?>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/dashboard">
                <i class="fas fa-gem me-2"></i> <?php echo Helper::escapeHtml($appName); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link <?php echo isActive($currentUri, [$baseUrl . '/app/dashboard', $baseUrl . '/']); ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/dashboard">داشبورد</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo isActive($currentUri, $baseUrl . '/app/transactions'); ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/transactions">معاملات</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo isActive($currentUri, $baseUrl . '/app/payments'); ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/payments">پرداخت/دریافت</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo isActive($currentUri, $baseUrl . '/app/inventory'); ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/inventory">موجودی انبار</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isActive($currentUri, [$baseUrl . '/app/contacts', $baseUrl . '/app/products', $baseUrl . '/app/bank-accounts']); ?>" href="#" id="definitionsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            تعاریف
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="definitionsDropdown">
                            <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/contacts">مخاطبین</a></li>
                            <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/products">کالاها و محصولات</a></li>
                            <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/product-categories">دسته‌بندی کالاها</a></li>
                            <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/bank-accounts">حساب‌های بانکی</a></li>
                            <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/assay-offices">مراکز ری‌گیری</a></li>
                        </ul>
                    </li>
                     <li class="nav-item"><a class="nav-link <?php echo isActive($currentUri, $baseUrl . '/app/invoice-generator'); ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/invoice-generator">صدور فاکتور</a></li>
                     <li class="nav-item"><a class="nav-link <?php echo isActive($currentUri, $baseUrl . '/app/calculator'); ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/calculator">ماشین حساب</a></li>
                </ul>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                       <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="fas fa-user-circle me-1"></i> <?php echo Helper::escapeHtml($userDisplayName); ?>
                       </a>
                       <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                           <?php if ($loggedInUser && ($loggedInUser['role_id'] ?? 2) == 1): // Admin-only links ?>
                               <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/users"><i class="fas fa-users-cog fa-fw me-2 text-muted"></i>مدیریت کاربران</a></li>
                               <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/activity-logs"><i class="fas fa-history fa-fw me-2 text-muted"></i>گزارش فعالیت‌ها</a></li>
                               <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/system/overview"><i class="fas fa-cogs fa-fw me-2 text-muted"></i>مدیریت سیستم</a></li>
                               <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/settings"><i class="fas fa-sliders-h fa-fw me-2 text-muted"></i>تنظیمات</a></li>
                               <li><hr class="dropdown-divider"></li>
                           <?php endif; ?>
                           <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/profile"><i class="fas fa-user-edit fa-fw me-2 text-muted"></i>پروفایل من</a></li>
                           <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/about"><i class="fas fa-info-circle fa-fw me-2 text-muted"></i>درباره سامانه</a></li>
                           <li><hr class="dropdown-divider"></li>
                           <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/logout"><i class="fas fa-sign-out-alt fa-fw me-2 text-muted"></i>خروج از سیستم</a></li>
                       </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
<?php endif; ?>

<main class="container flex-shrink-0 mb-4" style="padding-top: <?php echo $isLoggedIn ? '70px' : '20px'; ?> !important;">

    <?php if ($flashMessage && isset($flashMessage['text'])): ?>
        <div class="alert alert-<?php echo Helper::escapeHtml($flashMessage['type'] ?? 'info'); ?> alert-dismissible fade show mt-3">
            <?php echo Helper::escapeHtml($flashMessage['text']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
