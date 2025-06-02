<?php
/**
 * Template: src/views/system/maintenance.php
 * System Maintenance Page
 */

$pageTitle = $viewData['page_title'] ?? 'نگهداری سیستم';
$success_msg = $viewData['success_msg'] ?? null;
$error_msg = $viewData['error_msg'] ?? null;
$reset_code_message = $viewData['reset_code_message'] ?? null; // Message containing the one-time code
$reset_code_hash_exists = $viewData['reset_code_hash_exists'] ?? false;

?>

<h1 class="mb-4"><?php echo htmlspecialchars($pageTitle); ?></h1>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
<?php endif; ?>
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
<?php endif; ?>

<!-- Section for Database Optimization -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-light fw-bold">بهینه‌سازی پایگاه داده</div>
    <div class="card-body">
        <p class="card-text small text-muted">اجرای دستور OPTIMIZE TABLE روی جداول پایگاه داده برای بهبود عملکرد و آزاد کردن فضای استفاده نشده.</p>
        <form method="post" action="/app/system/optimize-db">
            <?php // TODO: Add CSRF token ?>
            <button type="submit" class="btn btn-primary">شروع بهینه‌سازی</button>
        </form>
    </div>
</div>

<!-- Section for System Reset Code Generation -->
<div class="card mb-4 shadow-sm border-warning">
    <div class="card-header bg-warning text-dark fw-bold">بازنشانی داده‌های سیستم (عملیات حساس)</div>
    <div class="card-body">
        <p class="card-text small text-muted">
            این بخش به شما امکان می‌دهد یک کد بازنشانی یکتا ایجاد کنید. با استفاده از این کد از طریق نقطه پایانی مخصوص، می‌توانید تمام داده‌های برنامه (تراکنش‌ها، طرف حساب‌ها، ...) را به جز تنظیمات اصلی و کاربران حذف کنید.
            <strong class="text-danger">این عملیات غیرقابل بازگشت است و فقط در موارد ضروری باید استفاده شود.</strong>
        </p>

        <?php if (!empty($reset_code_message)): ?>
            <div class="alert alert-warning">
                <h5 class="alert-heading">کد بازنشانی ایجاد شد!</h5>
                <p><strong>این کد فقط همین یک بار نمایش داده می‌شود. لطفاً آن را کپی کرده و در مکانی بسیار امن نگهداری کنید.</strong></p>
                <hr>
                <p class="mb-0" style="font-family: monospace; font-size: 1.1em; font-weight: bold; user-select: all;">
                    <?php echo nl2br(htmlspecialchars($reset_code_message)); // Display the code from flash message ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="/app/system/maintenance/generate-reset-code" onsubmit="return confirm('<?php echo $reset_code_hash_exists ? 'آیا مطمئن هستید که می‌خواهید یک کد بازنشانی جدید ایجاد کنید؟ کد قبلی غیرفعال خواهد شد.' : 'آیا مطمئن هستید که می‌خواهید کد بازنشانی سیستم را ایجاد کنید؟'; ?>');">
             <?php // TODO: Add CSRF token ?>
            <button type="submit" class="btn btn-danger">
                <?php echo $reset_code_hash_exists ? 'ایجاد کد بازنشانی جدید (کد قبلی باطل می‌شود)' : 'ایجاد کد بازنشانی سیستم'; ?>
            </button>
        </form>
        <?php if ($reset_code_hash_exists && empty($reset_code_message)): ?>
             <p class="mt-2 small text-info">یک کد بازنشانی قبلاً ایجاد شده است. برای امنیت، کد فقط یک بار پس از ایجاد نمایش داده می‌شود. اگر کد را فراموش کرده‌اید، می‌توانید کد جدیدی ایجاد کنید که کد قبلی را باطل خواهد کرد.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Add more maintenance sections here if needed (e.g., Clear Cache, View Logs)
?> 