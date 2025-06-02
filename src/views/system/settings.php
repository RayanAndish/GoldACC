<?php
/**
 * Template: src/views/system/settings.php
 * Displays the system settings form.
 * Receives settings data via $viewData['settings'] array from SettingsController.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'تنظیمات سیستم';
$settings = $viewData['settings'] ?? []; // Associative array of settings [key => value]
$formAction = $viewData['form_action'] ?? ''; // POST URL from controller
$formErrorMessage = $viewData['error_message'] ?? null; // Validation error from previous POST
$loadingError = $viewData['loading_error'] ?? null; // Error loading settings
$successMessage = $viewData['success_message'] ?? null; // Success message from previous save
$baseUrl = $viewData['baseUrl'] ?? '';

// Helper function to get setting value safely
function get_setting(string $key, $default = '') {
    global $settings; // Access the settings array
    return $settings[$key] ?? $default;
}

?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php // --- Display Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo Helper::escapeHtml($successMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($formErrorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo $formErrorMessage; /* Allow <br> */ ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($loadingError): ?>
    <div class="alert alert-warning"><?php echo Helper::escapeHtml($loadingError); ?></div>
<?php endif; ?>


<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>ویرایش تنظیمات عمومی</h5>
    </div>
    <form id="settings-form" action="<?php echo Helper::escapeHtml($formAction); ?>" method="POST" class="needs-validation" novalidate>
         <?php // TODO: Add CSRF token input ?>
        <div class="card-body">
            <?php if ($loadingError): ?>
                <p class="text-danger">خطا در بارگذاری تنظیمات. امکان ویرایش وجود ندارد.</p>
            <?php else: ?>
                <?php // Group settings logically ?>

                <fieldset class="mb-4 border p-3 rounded">
                    <legend class="fs-6 fw-semibold w-auto px-2">تنظیمات اصلی برنامه</legend>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label for="app_name" class="form-label">نام برنامه <span class="text-danger">*</span></label>
                            <input type="text" id="app_name" name="app_name" class="form-control"
                                   value="<?php echo Helper::escapeHtml(get_setting('app_name', 'حسابداری رایان طلا')); ?>" required>
                             <div class="invalid-feedback">نام برنامه الزامی است.</div>
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="admin_email" class="form-label">ایمیل مدیر <small>(برای دریافت گزارش)</small></label>
                            <input type="email" id="admin_email" name="admin_email" class="form-control ltr"
                                   value="<?php echo Helper::escapeHtml(get_setting('admin_email')); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="items_per_page" class="form-label">تعداد آیتم در هر صفحه <span class="text-danger">*</span></label>
                            <input type="number" id="items_per_page" name="items_per_page" class="form-control"
                                   value="<?php echo Helper::escapeHtml(get_setting('items_per_page', 15)); ?>" required min="5" max="100">
                            <div class="invalid-feedback">عددی بین ۵ تا ۱۰۰ وارد کنید.</div>
                        </div>
                        <?php /* Add other general settings like timezone if needed */ ?>
                    </div>
                </fieldset>

                 <fieldset class="mb-4 border p-3 rounded">
                    <legend class="fs-6 fw-semibold w-auto px-2">تنظیمات مشتری <small>(نمایش در 'درباره')</small></legend>
                     <div class="row g-3">
                         <div class="col-md-6 mb-3">
                             <label for="customer_name" class="form-label">نام مشتری/فروشگاه</label>
                             <input type="text" id="customer_name" name="customer_name" class="form-control"
                                    value="<?php echo Helper::escapeHtml(get_setting('customer_name')); ?>">
                         </div>
                          <div class="col-md-6 mb-3">
                             <label for="app_domain" class="form-label">دامنه اصلی برنامه <small>(بدون http/https)</small></label>
                             <input type="text" id="app_domain" name="app_domain" class="form-control ltr"
                                    value="<?php echo Helper::escapeHtml(get_setting('app_domain')); ?>" placeholder="e.g., mygoldapp.com">
                         </div>
                          <?php /* Install date might be better handled automatically */ ?>
                     </div>
                 </fieldset>

                 <?php // Example: License Settings (Read-only display from config) ?>
                  <fieldset class="mb-4 border p-3 rounded bg-light">
                    <legend class="fs-6 fw-normal text-muted w-auto px-2">تنظیمات به‌روزرسانی سامانه</legend>
                    <div class="row g-3">
                        <div class="col-md-12 mb-3">
                            <label for="update_server_url" class="form-label">آدرس سرور به‌روزرسانی</label>
                            <input type="text" class="form-control ltr" id="update_server_url" name="update_server_url" value="<?php echo Helper::escapeHtml(get_setting('update_server_url')); ?>" placeholder="https://update.example.com/api/check">
                        </div>
                    </div>
                </fieldset>

                 <?php // Add other fieldsets for SMTP, Backup schedule, etc. ?>

                 <fieldset class="mb-4 border p-3 rounded">
                    <legend class="fs-6 fw-semibold w-auto px-2">تنظیمات مالیات</legend>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label for="tax_rate" class="form-label">نرخ مالیات عمومی (%)</label>
                            <input type="number" step="0.01" min="0" max="100" id="tax_rate" name="tax_rate" class="form-control"
                                   value="<?php echo Helper::escapeHtml(get_setting('tax_rate', '9')); ?>">
                            <div class="form-text">این نرخ فقط برای اجرت، سود و کارمزد اعمال می‌شود.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="vat_rate" class="form-label">نرخ مالیات بر ارزش افزوده (%)</label>
                            <input type="number" step="0.01" min="0" max="100" id="vat_rate" name="vat_rate" class="form-control"
                                   value="<?php echo Helper::escapeHtml(get_setting('vat_rate', '7')); ?>">
                            <div class="form-text">این نرخ بر کل فاکتور پس از سایر محاسبات اعمال می‌شود.</div>
                        </div>
                    </div>
                </fieldset>

                 <div class="mb-3">
                    <label for="gold_price_api_url" class="form-label">آدرس API قیمت طلا</label>
                    <input type="text" class="form-control" id="gold_price_api_url" name="gold_price_api_url" value="<?php echo htmlspecialchars($settings['gold_price_api_url'] ?? ''); ?>">
                 </div>
                 <div class="mb-3">
                    <label for="gold_price_api_interval" class="form-label">بازه زمانی دریافت قیمت (دقیقه)</label>
                    <input type="number" class="form-control" id="gold_price_api_interval" name="gold_price_api_interval" value="<?php echo htmlspecialchars($settings['gold_price_api_interval'] ?? ''); ?>">
                 </div>
                 <div class="mb-3">
                    <label for="gold_price_api_params" class="form-label">پارامترهای اضافی (در صورت نیاز)</label>
                    <input type="text" class="form-control" id="gold_price_api_params" name="gold_price_api_params" value="<?php echo htmlspecialchars($settings['gold_price_api_params'] ?? ''); ?>">
                 </div>
                 <div class="mb-3">
                    <label for="gold_price_api_key" class="form-label">کلید API (در صورت نیاز)</label>
                    <input type="text" class="form-control" id="gold_price_api_key" name="gold_price_api_key" value="<?php echo htmlspecialchars($settings['gold_price_api_key'] ?? ''); ?>">
                 </div>
                 <div class="mb-3">
                    <label for="gold_price_api_username" class="form-label">نام کاربری API (در صورت نیاز)</label>
                    <input type="text" class="form-control" id="gold_price_api_username" name="gold_price_api_username" value="<?php echo htmlspecialchars($settings['gold_price_api_username'] ?? ''); ?>">
                 </div>
                 <div class="mb-3">
                    <label for="gold_price_api_password" class="form-label">رمز عبور API (در صورت نیاز)</label>
                    <input type="password" class="form-control" id="gold_price_api_password" name="gold_price_api_password" value="<?php echo htmlspecialchars($settings['gold_price_api_password'] ?? ''); ?>">
                 </div>

            <?php endif; // End if !$loadingError ?>
        </div> <?php // end card-body ?>
        <div class="card-footer text-center">
            <?php if (!$loadingError): ?>
            <button type="submit" class="btn btn-primary px-5">
                <i class="fas fa-save me-1"></i> ذخیره تنظیمات
            </button>
            <?php else: ?>
             <button type="submit" class="btn btn-primary px-5" disabled>ذخیره تنظیمات</button>
             <a href="<?php echo $baseUrl; ?>/app/settings" class="btn btn-secondary ms-2">تلاش مجدد</a>
            <?php endif; ?>
        </div>
    </form>
</div> <?php // end card ?>

<?php // JS for Bootstrap validation ?>
<script>
    (() => { 'use strict'; const forms = document.querySelectorAll('.needs-validation'); Array.from(forms).forEach(form => { form.addEventListener('submit', event => { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false) }) })();
</script>