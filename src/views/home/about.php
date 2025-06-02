<?php
/**
 * Template: src/views/home/about.php
 * Displays the About page with system, operational, and company information.
 * Receives data via $viewData array from HomeController::about.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'درباره سامانه';
$systemInfo = $viewData['system_info'] ?? [];
$operationalInfo = $viewData['operational_info'] ?? [];
$companyInfo = $viewData['company_info'] ?? [];
$errorMessage = $viewData['error_msg'] ?? null; // Error loading info
$successMessage = $viewData['flashMessage']['text'] ?? null; // General flash message
$successType = $viewData['flashMessage']['type'] ?? 'info';
$baseUrl = $viewData['baseUrl'] ?? '';

?>

<?php // --- Display Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-<?php echo Helper::escapeHtml($successType); ?> alert-dismissible fade show">
        <?php echo Helper::escapeHtml($successMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger">
        <?php echo Helper::escapeHtml($errorMessage); ?>
    </div>
<?php endif; ?>


<div class="card shadow-sm">
    <div class="card-header bg-light"> <?php // Use bg-light for less emphasis ?>
        <h1 class="h4 mb-0 fw-bold"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    </div>
    <div class="card-body">

        <?php // --- System Information --- ?>
        <h2 class="h5 border-bottom pb-2 mb-3 fw-semibold">
            <i class="fas fa-cogs me-2 text-secondary"></i> اطلاعات سیستم
        </h2>
        <div class="row mb-4">
            <div class="col-md-6 mb-3 mb-md-0">
                <p><strong>کد لایسنس:</strong>
                    <span class="text-muted user-select-all small">
                        <?php echo Helper::escapeHtml($systemInfo['license_key_display'] ?? 'N/A'); // Use display version ?>
                    </span>
                </p>
                <p><strong>وضعیت لایسنس:</strong>
                    <span class="badge bg-<?php echo ($systemInfo['license_status_class'] ?? 'secondary'); ?>">
                        <?php echo Helper::escapeHtml($systemInfo['license_status_farsi'] ?? 'نامشخص'); ?>
                    </span>
                </p>
                 <p><strong>تاریخ انقضای لایسنس:</strong>
                    <span class="text-muted">
                        <?php echo Helper::escapeHtml($systemInfo['license_expiry_farsi'] ?? 'N/A'); ?> 
                    </span>
                </p>
            </div>
            <div class="col-md-6">
                <p><strong>تعداد کاربران فعال:</strong>
                    <span class="badge bg-info"><?php echo Helper::formatNumber($systemInfo['active_users'] ?? 0, 0); ?></span>
                </p>
                <p><strong>حجم کل دیتابیس:</strong>
                    <span class="text-primary fw-bold number-fa">
                        <?php echo Helper::escapeHtml($systemInfo['total_db_size_formatted'] ?? 'N/A'); ?>
                    </span>
                </p>
                <?php if (!empty($systemInfo['table_details'])): ?>
                <p class="mb-1">
                    <strong>جزئیات حجم جداول:</strong>
                    <button class="btn btn-sm btn-outline-secondary ms-2 py-0 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#tableDetails" aria-expanded="false" aria-controls="tableDetails">
                        <i class="fas fa-list"></i> <span class="d-none d-sm-inline">نمایش</span>
                    </button>
                </p>
                <div class="collapse mt-1" id="tableDetails">
                    <ul class="list-group list-group-flush small border rounded" style="max-height: 150px; overflow-y: auto;">
                        <?php foreach ($systemInfo['table_details'] as $table): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-2 py-1">
                                <small><?php echo Helper::escapeHtml($table['name']); ?></small>
                                <span class="badge bg-light text-dark rounded-pill number-fa"><?php echo Helper::escapeHtml($table['size']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php // --- Operational Information --- ?>
        <h2 class="h5 border-bottom pb-2 mb-3 mt-4 fw-semibold">
            <i class="fas fa-user-cog me-2 text-secondary"></i> اطلاعات مشتری
        </h2>
        <div class="row mb-4">
             <div class="col-md-6 mb-3 mb-md-0">
                <p><strong>دامین تنظیم شده:</strong> <?php echo Helper::escapeHtml($operationalInfo['domain'] ?? 'N/A'); ?></p>
                <p><strong>ایمیل مدیر سیستم:</strong> <?php echo Helper::escapeHtml($operationalInfo['email'] ?? 'N/A'); ?></p>
             </div>
             <div class="col-md-6">
                <p><strong>نام مشتری:</strong> <?php echo Helper::escapeHtml($operationalInfo['customer_name'] ?? 'N/A'); ?></p>
                <p><strong>تاریخ نصب/راه‌اندازی:</strong> 
                    <span class="text-muted">
                        <?php echo Helper::escapeHtml($operationalInfo['install_date_farsi'] ?? 'N/A'); ?> 
                    </span>
                </p>
            </div>
        </div>

        <?php // --- Company Information --- ?>
        <h2 class="h5 border-bottom pb-2 mb-3 mt-4 fw-semibold">
            <i class="fas fa-building me-2 text-secondary"></i> اطلاعات توسعه دهنده
        </h2>
        <div class="row">
             <div class="col-md-6 mb-3 mb-md-0">
                 <p><strong>نام شرکت:</strong> <?php echo Helper::escapeHtml($companyInfo['name'] ?? ''); ?></p>
                 <p><strong>ایمیل پشتیبانی:</strong> <a href="mailto:<?php echo Helper::escapeHtml($companyInfo['email'] ?? ''); ?>"><?php echo Helper::escapeHtml($companyInfo['email'] ?? ''); ?></a></p>
             </div>
             <div class="col-md-6">
                <p><strong>شماره تماس:</strong> <?php echo Helper::escapeHtml($companyInfo['phone'] ?? ''); ?></p>
                <p class="text-muted small mt-2">
                    <?php echo Helper::escapeHtml($companyInfo['copyright'] ?? ''); ?>
                    - سال <?php echo Helper::formatNumber(date('Y'), 0, '.', ''); // Current year in Farsi ?>
                </p>
             </div>
        </div>

         <hr class="my-4">
         <a href="<?php echo $baseUrl; ?>/app/dashboard" class="btn btn-secondary">
             <i class="fas fa-arrow-left me-2"></i> بازگشت به داشبورد
         </a>

    </div> <!-- /card-body -->
</div> <!-- /card -->

<?php // JS for collapse ?>
<script> /* Ensure Bootstrap JS is loaded */ </script>