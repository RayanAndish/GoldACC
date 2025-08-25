<?php
use App\Utils\Helper;
use App\Core\CSRFProtector;

$pageTitle = $viewData['page_title'] ?? 'تنظیمات سیستم';
$settings = $viewData['settings'] ?? [];
$formAction = $viewData['form_action'] ?? '';
$formErrorMessage = $viewData['error_message'] ?? null;
$loadingError = $viewData['loading_error'] ?? null;
$successMessage = $viewData['success_message'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';
?>
<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php // --- Display Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo Helper::escapeHtml($successMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($formErrorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo $formErrorMessage; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($loadingError): ?>
    <div class="alert alert-warning"><?php echo Helper::escapeHtml($loadingError); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>ویرایش تنظیمات عمومی</h5>
    </div>
     <form id="settings-form" action="<?php echo Helper::escapeHtml($formAction); ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo CSRFProtector::generateToken(); ?>">
        <div class="card-body">
            <?php if ($loadingError): ?>
                <p class="text-danger">خطا در بارگذاری تنظیمات. امکان ویرایش وجود ندارد.</p>
            <?php else: ?>
                <fieldset class="mb-4 border p-3 rounded">
                    <legend class="fs-6 fw-semibold w-auto px-2">تنظیمات اصلی برنامه</legend>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label for="app_name" class="form-label">نام برنامه <span class="text-danger">*</span></label>
                            <input type="text" id="app_name" name="app_name" class="form-control"
                                   value="<?php echo Helper::escapeHtml($settings['app_name'] ?? 'حسابداری رایان طلا'); ?>" required>
                             <div class="invalid-feedback">نام برنامه الزامی است.</div>
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="admin_email" class="form-label">ایمیل مدیر <small>(برای دریافت گزارش)</small></label>
                            <input type="email" id="admin_email" name="admin_email" class="form-control ltr"
                                   value="<?php echo Helper::escapeHtml($settings['admin_email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="items_per_page" class="form-label">تعداد آیتم در هر صفحه <span class="text-danger">*</span></label>
                            <input type="number" id="items_per_page" name="items_per_page" class="form-control"
                                   value="<?php echo Helper::escapeHtml($settings['items_per_page'] ?? 15); ?>" required min="5" max="100">
                            <div class="invalid-feedback">عددی بین ۵ تا ۱۰۰ وارد کنید.</div>
                        </div>
                    </div>
                </fieldset>

                 <fieldset class="mb-4 border p-3 rounded">
                    <legend class="fs-6 fw-semibold w-auto px-2">تنظیمات مشتری <small>(نمایش در 'درباره')</small></legend>
                     <div class="row g-3">
                         <div class="col-md-6 mb-3">
                             <label for="customer_name" class="form-label">نام مشتری/فروشگاه</label>
                             <input type="text" id="customer_name" name="customer_name" class="form-control"
                                    value="<?php echo Helper::escapeHtml($settings['customer_name'] ?? ''); ?>">
                         </div>
                          <div class="col-md-6 mb-3">
                             <label for="app_domain" class="form-label">دامنه اصلی برنامه <small>(بدون http/https)</small></label>
                             <input type="text" id="app_domain" name="app_domain" class="form-control ltr"
                                    value="<?php echo Helper::escapeHtml($settings['app_domain'] ?? ''); ?>" placeholder="e.g., mygoldapp.com">
                         </div>
                     </div>
                 </fieldset>

                 <fieldset class="mb-4 border p-3 rounded">
                    <legend class="fs-6 fw-semibold w-auto px-2">اطلاعات کسب‌وکار (برای فاکتور)</legend>
                     <div class="row g-3">
                         <div class="col-md-12 mb-3">
                             <label for="seller_address" class="form-label">آدرس</label>
                             <textarea class="form-control" id="seller_address" name="seller_address" rows="2"><?php echo Helper::escapeHtml($settings['seller_address'] ?? ''); ?></textarea>
                         </div>
                         <div class="col-md-6 mb-3">
                             <label for="seller_phone" class="form-label">تلفن</label>
                             <input type="text" id="seller_phone" name="seller_phone" class="form-control ltr"
                                    value="<?php echo Helper::escapeHtml($settings['seller_phone'] ?? ''); ?>">
                         </div>
                         <div class="col-md-6 mb-3">
                            <label for="seller_registration_code" class="form-label">شناسه ملی / کد اقتصادی</label>
                            <input type="text" id="seller_registration_code" name="seller_registration_code" class="form-control ltr"
                                   value="<?php echo Helper::escapeHtml($settings['seller_registration_code'] ?? ''); ?>">
                        </div>
                     </div>
                 </fieldset>

                  <fieldset class="mb-4 border p-3 rounded bg-light">
                    <legend class="fs-6 fw-normal text-muted w-auto px-2">تنظیمات به‌روزرسانی سامانه</legend>
                    <div class="row g-3">
                        <div class="col-md-12 mb-3">
                            <label for="update_server_url" class="form-label">آدرس سرور به‌روزرسانی</label>
                            <input type="text" class="form-control ltr" id="update_server_url" name="update_server_url" value="<?php echo Helper::escapeHtml($settings['update_server_url'] ?? ''); ?>" placeholder="https://update.example.com/api/check">
                        </div>
                    </div>
                </fieldset>

                 <fieldset class="mb-4 border p-3 rounded">
                    <legend class="fs-6 fw-semibold w-auto px-2">تنظیمات API قیمت طلا</legend>
                     <div class="mb-3">
                        <label for="gold_price_api_url" class="form-label">آدرس API</label>
                        <input type="text" class="form-control ltr" id="gold_price_api_url" name="gold_price_api_url" value="<?php echo htmlspecialchars($settings['gold_price_api_url'] ?? ''); ?>">
                     </div>
                     <div class="mb-3">
                        <label for="gold_price_api_interval" class="form-label">بازه زمانی دریافت (دقیقه)</label>
                        <input type="number" class="form-control ltr" id="gold_price_api_interval" name="gold_price_api_interval" value="<?php echo htmlspecialchars($settings['gold_price_api_interval'] ?? ''); ?>">
                     </div>
                     <div class="mb-3">
                        <label for="gold_price_api_key" class="form-label">کلید API</label>
                        <input type="text" class="form-control ltr" id="gold_price_api_key" name="gold_price_api_key" value="<?php echo htmlspecialchars($settings['gold_price_api_key'] ?? ''); ?>">
                     </div>
                     <div class="mb-3">
                        <label for="gold_price_api_username" class="form-label">نام کاربری API</label>
                        <input type="text" class="form-control ltr" id="gold_price_api_username" name="gold_price_api_username" value="<?php echo htmlspecialchars($settings['gold_price_api_username'] ?? ''); ?>">
                     </div>
                     <div class="mb-3">
                        <label for="gold_price_api_password" class="form-label">رمز عبور API</label>
                        <input type="password" class="form-control ltr" id="gold_price_api_password" name="gold_price_api_password" value="<?php echo htmlspecialchars($settings['gold_price_api_password'] ?? ''); ?>">
                     </div>
                 </fieldset>

            <?php endif; ?>
        </div>
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
</div>
<script>
    (() => { 'use strict'; const forms = document.querySelectorAll('.needs-validation'); Array.from(forms).forEach(form => { form.addEventListener('submit', event => { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false) }) })();
</script>