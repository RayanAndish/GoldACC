<?php
/**
 * Template: src/views/layouts/header.php
 * Main application header and navigation bar.
 * Included by ViewRenderer when $withLayout is true.
 * Receives data via $viewData array.
 */

use App\Utils\Helper; // Helper might be needed for escaping or formatting

// --- Extract common data from $viewData with defaults ---
$appName = $viewData['appName'] ?? 'حسابداری رایان طلا';
$baseUrl = $viewData['baseUrl'] ?? ''; // Should always be provided by AbstractController
$pageTitle = $viewData['pageTitle'] ?? 'Undefined'; // Controller should set this
$isLoggedIn = $viewData['isLoggedIn'] ?? false;
$loggedInUser = $viewData['loggedInUser'] ?? null; // Contains user info (id, username, name, role_id/role_name) if logged in
$currentUri = $viewData['currentUri'] ?? '/'; // Current request URI (relative to base path) for active menu highlighting
$flashMessage = $viewData['flashMessage'] ?? null; // Default flash message
$flashLicenseMessage = $viewData['flash_license_message'] ?? null; // Specific flash message
// Determine user display name
$userDisplayName = 'کاربر';
if ($loggedInUser) {
    $userDisplayName = $loggedInUser['name'] ?: $loggedInUser['username']; // Use name, fallback to username
}

// Helper function to check if a menu item should be active
// Checks if the current URI starts with the given path(s)
if (!function_exists('isActiveMenu')) {
    function isActiveMenu(string|array $paths, string $currentUri): bool {
        if (!is_array($paths)) {
            $paths = [$paths];
        }
        foreach ($paths as $path) {
            // Ensure path starts with / and handle the exact match for '/'
            if ($path === '/') {
                if ($currentUri === '/' || $currentUri === '/app/dashboard') return true; // Special case for dashboard
            } else {
                $path = '/' . ltrim($path, '/');
                if (str_starts_with($currentUri, $path)) {
                    return true;
                }
            }
        }
        return false;
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php /* Title Tag */ ?>
    <title><?php echo Helper::escapeHtml($pageTitle); ?> | <?php echo Helper::escapeHtml($appName); ?></title>

    <?php // --- CSS Links using base URL --- ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <?php // Datepicker CSS ?>
    <link rel="stylesheet" href="<?php echo Helper::escapeHtml($baseUrl); ?>/css/jalalidatepicker.min.css">
    <?php // Main Stylesheet with cache busting ?>
    <?php $mainCssPath = '/css/style.css'; ?>
    <link rel="stylesheet" href="<?php echo Helper::escapeHtml($baseUrl . $mainCssPath); ?>?v=<?php echo @filemtime(ROOT_PATH . '/public' . $mainCssPath) ?: time(); ?>">
    <?php // Favicon ?>
    <link rel="icon" href="<?php echo Helper::escapeHtml($baseUrl); ?>/favicon.ico" type="image/x-icon">

</head>
<body id="app-body" class="d-flex flex-column min-vh-100 bg-light"> <?php // Standard body structure ?>

<?php // --- Navigation Bar (Only if Logged In) --- ?>
<?php if ($isLoggedIn): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm"> <?php // bg-dark from original ?>
        <div class="container">
            <?php // Brand Logo/Name ?>
            <a class="navbar-brand fw-bold" href="<?php echo Helper::escapeHtml($baseUrl); ?>/"> <?php // Link to dashboard ?>
                <img src="<?php echo Helper::escapeHtml($baseUrl); ?>/images/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                 <?php /* Helper::escapeHtml($appName) */ ?>
            </a>
            <?php // Mobile Toggler ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <?php // Navbar Content ?>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <?php // --- Main Menu (Right Aligned) --- ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu(['/', '/app/dashboard'], $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/"><i class="fas fa-tachometer-alt fa-fw me-1"></i>داشبورد</a>
                    </li>

                    <?php // Transactions Dropdown ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isActiveMenu(['/app/transactions', '/app/invoice-generator'], $currentUri) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="fas fa-exchange-alt fa-fw me-1"></i> معاملات
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?php echo isActiveMenu('/app/transactions', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/transactions">لیست معاملات</a></li>
                            <li><a class="dropdown-item <?php echo isActiveMenu('/app/transactions/add', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/transactions/add">ثبت معامله جدید</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item <?php echo isActiveMenu('/app/invoice-generator', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/invoice-generator">صدور فاکتور</a></li>
                        </ul>
                    </li>

                    <?php // Financial Dropdown ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isActiveMenu(['/app/payments', '/app/bank-accounts'], $currentUri) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                             <i class="fas fa-dollar-sign fa-fw me-1"></i> مالی
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?php echo isActiveMenu('/app/payments', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/payments">پرداخت‌ها / دریافت‌ها</a></li>
                            <li><a class="dropdown-item <?php echo isActiveMenu('/app/bank-accounts', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/bank-accounts">حساب‌های بانکی</a></li>
                        </ul>
                    </li>

                    <?php // Inventory Dropdown ?>
                    <li class="nav-item dropdown">
                         <a class="nav-link dropdown-toggle <?php echo isActiveMenu('/app/inventory', $currentUri) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="fas fa-boxes-stacked fa-fw me-1"></i> انبارداری
                         </a>
                         <ul class="dropdown-menu">
                             <li><a class="dropdown-item <?php echo isActiveMenu('/app/inventory', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/inventory">موجودی کالا</a></li>
                             <li><a class="dropdown-item <?php echo isActiveMenu('/app/initial-balance', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/initial-balance">موجودی اولیه</a></li>
                             <li><a class="dropdown-item <?php echo isActiveMenu('/app/products', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/products">محصولات</a></li>
                         </ul>
                     </li>

                     <?php // Base Info Dropdown ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isActiveMenu(['/app/contacts', '/app/assay-offices', '/app/product-categories', '/app/calculator', '/app/activity-logs'], $currentUri) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                             <i class="fas fa-info-circle fa-fw me-1"></i> اطلاعات پایه
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?php echo isActiveMenu('/app/contacts', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/contacts">مخاطبین</a></li>
                            <li><a class="dropdown-item <?php echo isActiveMenu('/app/assay-offices', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/assay-offices">مراکز ری‌گیری</a></li>
                            <?php // <-- خط جدید اضافه شده در اینجا --> ?>
                            <li><a class="dropdown-item <?php echo isActiveMenu('/app/product-categories', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/product-categories">دسته‌بندی محصولات</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item <?php echo isActiveMenu('/app/calculator', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/calculator">ماشین حساب</a></li>
                            <?php if ($loggedInUser && ($loggedInUser['role_name'] ?? '') === 'admin'): // Show logs only for admin ?>
                               <li><a class="dropdown-item <?php echo isActiveMenu('/app/activity-logs', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/activity-logs">گزارش فعالیت سیستم</a></li>
                             <?php endif; ?>
                        </ul>
                    </li>

                     <?php // Admin Menu (Only if user is admin) ?>
                     <?php if ($loggedInUser && ($loggedInUser['role_name'] ?? '') === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo isActiveMenu(['/app/users', '/app/settings', '/app/system'], $currentUri) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                 <i class="fas fa-cogs fa-fw me-1"></i> مدیریت
                            </a>
                            <ul class="dropdown-menu">
                                 <li><a class="dropdown-item <?php echo isActiveMenu('/app/users', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/users">مدیریت کاربران</a></li>
                                <li><a class="dropdown-item <?php echo isActiveMenu('/app/settings', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/settings">تنظیمات سیستم</a></li>
                                <li><a class="dropdown-item <?php echo isActiveMenu('/app/system', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/system/overview">بهینه سازی سیستم </a></li>
                                 <?php /* Optional Code Protection Link - Discouraged
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-warning <?php echo isActiveMenu('/app/code-protection', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/code-protection">'رمزنگاری' کد (ناامن)</a></li>
                                */ ?>
                            </ul>
                        </li>
                    <?php endif; // End Admin Menu ?>
                </ul>

                <?php // --- User Menu (Left Aligned) --- ?>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                   <li class="nav-item dropdown">
                       <a class="nav-link dropdown-toggle <?php echo isActiveMenu('/app/profile', $currentUri) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="fas fa-user-circle fa-fw me-1"></i><?php echo Helper::escapeHtml($userDisplayName); ?>
                       </a>
                       <ul class="dropdown-menu dropdown-menu-start"> <?php // Align dropdown to start (left in LTR, right in RTL) ?>
                           <li><a class="dropdown-item <?php echo isActiveMenu('/app/profile', $currentUri) ? 'active' : ''; ?>" href="<?php echo Helper::escapeHtml($baseUrl); ?>/app/profile"><i class="fas fa-user-edit fa-fw me-2 text-muted"></i>پروفایل من</a></li>
                           <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/about"><i class="fas fa-info-circle fa-fw me-2 text-muted"></i>درباره سامانه</a></li>
                           <li><hr class="dropdown-divider"></li>
                           <li><a class="dropdown-item" href="<?php echo Helper::escapeHtml($baseUrl); ?>/logout"><i class="fas fa-sign-out-alt fa-fw me-2 text-muted"></i>خروج از سیستم</a></li>
                       </ul>
                    </li>
                </ul>
            </div> <?php // End navbar-collapse ?>
        </div> <?php // End container ?>
    </nav>
<?php endif; // End if ($isLoggedIn) ?>

<?php // --- Main Content Area START --- ?>
<main class="container flex-shrink-0 mb-4" style="padding-top: <?php echo $isLoggedIn ? '70px' : '20px'; ?> !important;"> <?php // Adjust top padding if user not logged in ?>

    <?php // --- Display Flash Messages --- ?>
    <?php if ($flashMessage && isset($flashMessage['text'])): ?>
        <div class="alert alert-<?php echo Helper::escapeHtml($flashMessage['type'] ?? 'info'); ?> alert-dismissible fade show mt-3">
            <?php echo Helper::escapeHtml($flashMessage['text']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php // <!-- Page specific content will be injected here by ViewRenderer --> ?>