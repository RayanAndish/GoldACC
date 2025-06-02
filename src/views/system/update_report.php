<?php
/**
 * Template: src/views/system/update_report.php
 * نمایش گزارش یک به‌روزرسانی خاص
 */
$pageTitle = $viewData['page_title'] ?? 'گزارش به‌روزرسانی';
$error_msg = $viewData['error_msg'] ?? null;
$version = $viewData['version'] ?? null;
$update_time = $viewData['update_time'] ?? null;
$status = $viewData['status'] ?? null;
$log = $viewData['log'] ?? null;
?>

<h1 class="mb-4"><?php echo htmlspecialchars($pageTitle); ?></h1>

<?php if ($error_msg): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
<?php else: ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
            نسخه: <span class="fw-bold number-fa text-primary"><?php echo htmlspecialchars($version); ?></span>
            <span class="ms-3">تاریخ: <?php echo jdate('Y/m/d H:i', strtotime($update_time)); ?></span>
            <span class="ms-3">
                وضعیت:
                <?php if ($status === 'success'): ?>
                    <span class="badge bg-success">موفق</span>
                <?php else: ?>
                    <span class="badge bg-danger">ناموفق</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="card-body">
            <h6 class="fw-bold mb-2">گزارش عملیات:</h6>
            <pre class="bg-light p-3 border rounded small" style="white-space:pre-wrap;direction:ltr;">
<?php echo htmlspecialchars($log ?: 'گزارشی ثبت نشده است.'); ?>
            </pre>
        </div>
    </div>
    <a href="javascript:history.back();" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> بازگشت</a>
<?php endif; ?> 