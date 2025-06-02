<?php
/**
 * Template: src/views/system/index.php (your file was named system_index.php)
 * Main system overview page.
 */
use App\Utils\Helper;
use Morilog\Jalali\Jalalian;
use Carbon\Carbon;

$backups = $viewData['backups'] ?? [];
$success_msg = $viewData['success_msg'] ?? null;
$error_msg = $viewData['error_msg'] ?? null;
$current_version = $viewData['current_version'] ?? '-';
$update_info = $viewData['update_info'] ?? [];
$update_history = $viewData['update_history'] ?? [];
$baseUrl = $viewData['baseUrl'] ?? '';
?>

<h1 class="mb-4">مدیریت سیستم</h1>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
<?php endif; ?>
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-light fw-bold">پشتیبان گیری</div>
            <div class="card-body">
                <form method="post" action="<?php echo $baseUrl; ?>/app/system/backup/run" style="display:inline-block" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerHTML = '<span class=\'spinner-border spinner-border-sm\'></span> در حال ایجاد...';">
                    <button type="submit" class="btn btn-success mb-3"><i class="fas fa-plus-circle me-1"></i>ایجاد نسخه پشتیبان جدید</button>
                </form>
                <?php if (!empty($backups)): ?>
                    <form method="post" action="<?php echo $baseUrl; ?>/app/system/backup/action" id="backup-action-form">
                        <ul class="list-group mb-3">
                            <?php foreach ($backups as $backup): ?>
                                <li class="list-group-item py-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <input type="radio" name="selected_backup" value="<?php echo htmlspecialchars($backup['name']); ?>" required class="form-check-input ms-2">
                                        <span class="fw-bold ms-2"> <?php echo htmlspecialchars($backup['name']); ?> </span>
                                        <span class="badge bg-secondary ms-2"> <?php echo round($backup['size']/1024/1024, 2); ?> MB </span>
                                    </div>
                                    <div class="mb-2 text-secondary small ps-4">
                                        <i class="far fa-calendar-alt me-1"></i>
                                        تاریخ ایجاد: 
                                        <?php 
                                            // FIX: Use the correct, safe way to convert timestamp to Jalali date
                                            try {
                                                echo Jalalian::fromCarbon(Carbon::createFromTimestamp($backup['modified']))->format('Y/m/d H:i');
                                            } catch (Exception $e) {
                                                echo date('Y-m-d H:i', $backup['modified']);
                                            }
                                        ?>
                                    </div>
                                    <div class="d-flex gap-2 justify-content-end">
                                        <button type="submit" name="action" value="restore" class="btn btn-outline-primary btn-sm" onclick="return confirm('آیا از بازگردانی این نسخه پشتیبان مطمئن هستید؟ تمام داده‌های فعلی بازنویسی خواهند شد.');"><i class="fas fa-undo-alt me-1"></i>بازگردانی</button>
                                        <button type="submit" name="action" value="delete" class="btn btn-outline-danger btn-sm" onclick="return confirm('آیا از حذف این فایل پشتیبان مطمئن هستید؟');"><i class="fas fa-trash-alt me-1"></i>حذف</button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </form>
                <?php else: ?>
                    <p class="text-muted">هیچ نسخه پشتیبانی وجود ندارد.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-light fw-bold">به‌روزرسانی سامانه</div>
            <div class="card-body">
                <div class="mb-2">نسخه فعلی: <span class="fw-bold number-fa text-primary"><?php echo htmlspecialchars($current_version ?? '-'); ?></span></div>
                
                <div id="update-status-container">
                    <!-- AJAX content will be loaded here -->
                </div>
                
                <form id="check-update-form" method="post" action="<?php echo $baseUrl; ?>/app/system/update/check">
                    <button type="submit" class="btn btn-info">بررسی به‌روزرسانی جدید</button>
                </form>
                <div id="update-check-result" class="mt-2"></div>

                <div class="card mt-4 mb-0 shadow-sm">
                    <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                        تاریخچه به‌روزرسانی
                        <a href="<?php echo $baseUrl; ?>/app/system/update" class="btn btn-sm btn-outline-secondary">مشاهده همه</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm align-middle mb-0">
                                <thead><tr><th>شماره نسخه</th><th>تاریخ</th><th>نتیجه</th><th>گزارش</th></tr></thead>
                                <tbody>
                                    <?php if (!empty($update_history)): ?>
                                        <?php foreach ($update_history as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['version']); ?></td>
                                                <td><?php echo jdate('Y/m/d H:i', strtotime($row['update_time'])); ?></td>
                                                <td><span class="badge bg-<?php echo $row['status'] === 'success' ? 'success' : 'danger'; ?>"><?php echo $row['status'] === 'success' ? 'موفق' : 'ناموفق'; ?></span></td>
                                                <td><a href="<?php echo $baseUrl; ?>/app/system/update/report/<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-info">مشاهده</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted p-2">هیچ سابقه‌ای ثبت نشده است.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
var createBackupForm = document.querySelector('form[action="/app/system/backup/run"]');
if (createBackupForm) {
    createBackupForm.addEventListener('submit', function() {
        var createButton = this.querySelector('button[type="submit"]');
        if (createButton) {
            createButton.disabled = true;
            createButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> در حال ایجاد بکاپ... لطفاً صبر کنید';
            // Optional: Add a message div
            var msgDiv = document.createElement('div');
            msgDiv.className = 'alert alert-info mt-3';
            msgDiv.textContent = 'عملیات بکاپ‌گیری آغاز شد. این فرآیند ممکن است چند دقیقه طول بکشد. لطفاً صفحه را نبندید.';
            this.parentNode.insertBefore(msgDiv, this.nextSibling); // Insert after the form
        }
    });
}
</script>
