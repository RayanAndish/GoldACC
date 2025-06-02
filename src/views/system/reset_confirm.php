<?php
/**
 * Template: src/views/system/reset_confirm.php
 * Final Confirmation Page for System Reset
 */

$pageTitle = $viewData['page_title'] ?? 'تأیید نهایی بازنشانی سیستم';
$isValid = $viewData['is_valid'] ?? false;
$errorMessage = $viewData['error_message'] ?? 'خطای نامشخص در اعتبارسنجی توکن.';
$confirmationToken = $viewData['confirmation_token'] ?? '';

?>

<h1 class="mb-4 text-danger"><?php echo htmlspecialchars($pageTitle); ?></h1>

<?php if (!$isValid): ?>
    <div class="alert alert-danger">
        <h4 class="alert-heading">خطا!</h4>
        <p>امکان ادامه فرآیند بازنشانی وجود ندارد.</p>
        <hr>
        <p class="mb-0"><?php echo htmlspecialchars($errorMessage); ?></p>
        <p class="mt-3"><a href="/app/system/maintenance" class="btn btn-secondary">بازگشت به صفحه نگهداری</a></p>
    </div>
<?php else: ?>
    <div class="alert alert-danger border-danger">
        <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>هشدار بسیار مهم!</h4>
        <p>شما در آستانه **حذف تمام داده‌های اصلی** این سامانه هستید. این عملیات شامل موارد زیر اما محدود به آن‌ها نیست:</p>
        <ul>
            <li>تمام تراکنش‌های ثبت شده (خرید، فروش، دریافت، پرداخت و ...)</li>
            <li>تمام طرف حساب‌ها و اطلاعات تماس آن‌ها</li>
            <li>تمام مراکز هزینه/پیگیری</li>
            <li>تمام معاملات طلا و سکه</li>
            <li>تمام سوابق دریافت و پرداخت</li>
            <li>موجودی‌های انبار طلا و سکه</li>
            <li>و سایر داده‌های عملیاتی...</li>
        </ul>
        <p>داده‌های زیر حذف **نخواهند** شد:</p>
        <ul>
            <li>تنظیمات اصلی سامانه</li>
            <li>کاربران و اطلاعات ورود آن‌ها</li>
            <li>تاریخچه به‌روزرسانی‌ها</li>
            <li>سوابق فعالیت‌ها (Logs)</li>
            <li>جداول مربوط به مایگریشن (phinxlog)</li>
        </ul>
        <hr>
        <p class="fw-bold text-danger">این عملیات به هیچ عنوان قابل بازگشت نیست! آیا از انجام این کار کاملاً مطمئن هستید؟</p>

        <form method="post" action="/app/system/reset/execute">
            <input type="hidden" name="confirmation_token" value="<?php echo htmlspecialchars($confirmationToken); ?>">
            <?php // TODO: Add CSRF Token here ?>
            <button type="submit" class="btn btn-danger btn-lg"><i class="fas fa-skull-crossbones me-2"></i> بله، تأیید نهایی می‌کنم و تمام داده‌ها را حذف کن</button>
            <a href="/app/dashboard" class="btn btn-secondary btn-lg ms-2">انصراف</a>
        </form>
    </div>
<?php endif; ?> 