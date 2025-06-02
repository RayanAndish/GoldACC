<?php
/**
 * Template: src/views/system/index.php
 * مدیریت بکاپ‌های سیستم
 */
$backups = $viewData['backups'] ?? [];
$success_msg = $viewData['success_msg'] ?? null;
$error_msg = $viewData['error_msg'] ?? null;
$current_version = $viewData['current_version'] ?? null;
$update_info = $viewData['update_info'] ?? [];
$update_history = $viewData['update_history'] ?? [];
?>
<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
<?php endif; ?>
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
<?php endif; ?>
<h1 class="mb-4">مدیریت سیستم</h1>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-light fw-bold">پشتیبان گیری</div>
            <div class="card-body">
                <form method="post" action="/app/system/backup/run" style="display:inline-block">
                    <button type="submit" class="btn btn-success mb-3"><i class="fas fa-plus-circle me-1"></i>ایجاد نسخه پشتیبان جدید</button>
                </form>
                <?php if (!empty($backups)): ?>
                    <form method="post" action="/app/system/backup/action" id="backup-action-form">
                        <ul class="list-group mb-3">
                            <?php foreach ($backups as $backup): ?>
                                <li class="list-group-item py-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <input type="radio" name="selected_backup" value="<?php echo htmlspecialchars($backup['name']); ?>" required class="form-check-input ms-2">
                                        <span class="fw-bold ms-2"> <?php echo htmlspecialchars($backup['name']); ?> </span>
                                        <span class="badge bg-secondary ms-2"> <?php echo round($backup['size']/1024, 2); ?> KB </span>
                                    </div>
                                    <div class="mb-2 text-secondary small ps-4">
                                        <i class="far fa-calendar-alt me-1"></i>
                                        تاریخ ایجاد: <?php echo jdate('Y/m/d H:i', $backup['modified']); ?>
                                    </div>
                                    <div class="d-flex gap-2 justify-content-end">
                                        <button type="submit" name="action" value="restore" class="btn btn-outline-primary btn-sm"><i class="fas fa-undo-alt me-1"></i>بازگردانی</button>
                                        <button type="submit" name="action" value="delete" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash-alt me-1"></i>حذف</button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </form>
                <?php else: ?>
                    <p>هیچ نسخه پشتیبانی وجود ندارد.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-light fw-bold">به‌روزرسانی سامانه</div>
            <div class="card-body">
                <div class="mb-2">نسخه فعلی: <span class="fw-bold number-fa text-primary"><?php echo htmlspecialchars($current_version ?? '-'); ?></span></div>
                
                <!-- Container for update available/not available messages -->
                <div id="update-status-container">
                <?php if (!empty($update_info['error'])): ?>
                    <div id="update-error-message" class="alert alert-danger small mb-2"><?php echo htmlspecialchars($update_info['error']); ?></div>
                <?php elseif (!empty($update_info['update_available'])): ?>
                    <div id="update-available-section" class="alert alert-warning mb-2">
                        نسخه جدید <b id="update-latest-version" class="number-fa text-success"><?php echo htmlspecialchars($update_info['latest_version']); ?></b> موجود است.<br>
                        <b>تغییرات:</b><br>
                        <pre id="update-changelog" class="bg-light p-2 small border rounded"> <?php echo htmlspecialchars($update_info['changelog'] ?? 'توضیحات موجود نیست.'); ?> </pre>
                        <form id="apply-update-form" method="post" action="/app/system/update/apply">
                            <input id="apply-version" type="hidden" name="latest_version" value="<?php echo htmlspecialchars($update_info['latest_version']); ?>">
                            <input id="apply-url" type="hidden" name="download_url" value="<?php echo htmlspecialchars($update_info['download_url']); ?>">
                            <?php if (!empty($update_info['checksum'])): ?>
                                <input id="apply-checksum" type="hidden" name="checksum" value="<?php echo htmlspecialchars($update_info['checksum']); ?>">
                            <?php endif; ?>
                            <?php // TODO: Add CSRF token field here ?>
                            <button type="submit" class="btn btn-warning">به‌روزرسانی و پشتیبان گیری خودکار</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div id="update-not-available-message" class="alert alert-success small mb-2">سامانه به آخرین نسخه به‌روزرسانی شده است.</div>
                <?php endif; ?>
                </div> <!-- End update-status-container -->
                
                <form id="check-update-form" method="post" action="/app/system/update/check">
                    <button type="submit" class="btn btn-info">بررسی به‌روزرسانی جدید</button>
                </form>
                <div id="update-check-result" class="mt-2"></div> <!-- Result of AJAX check -->
                <script>
                document.getElementById('check-update-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    var resultDiv = document.getElementById('update-check-result');
                    var checkButton = this.querySelector('button[type="submit"]');
                    var originalButtonText = checkButton.innerHTML;
                    checkButton.disabled = true;
                    checkButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> در حال بررسی...';

                    // Clear previous results
                    resultDiv.innerHTML = '';

                    // Hide existing status messages/sections initially
                    var errorMsgDiv = document.getElementById('update-error-message');
                    var notAvailableMsgDiv = document.getElementById('update-not-available-message');
                    var availableSectionDiv = document.getElementById('update-available-section');
                    if(errorMsgDiv) errorMsgDiv.style.display = 'none';
                    if(notAvailableMsgDiv) notAvailableMsgDiv.style.display = 'none';
                    if(availableSectionDiv) availableSectionDiv.style.display = 'none';

                    fetch('/app/system/update/check', {method: 'POST'})
                        .then(res => res.json())
                        .then(data => {
                            if (data.update_info && data.update_info.update_available) {
                                // Update available: Show the main update section with new data
                                if (availableSectionDiv) {
                                    document.getElementById('update-latest-version').textContent = data.update_info.latest_version;
                                    document.getElementById('update-changelog').textContent = data.update_info.changelog || 'توضیحات موجود نیست.';
                                    document.getElementById('apply-version').value = data.update_info.latest_version;
                                    document.getElementById('apply-url').value = data.update_info.download_url;

                                    // Handle checksum field (create if not exists, update if exists)
                                    var checksumInput = document.getElementById('apply-checksum');
                                    if (data.update_info.checksum) {
                                        if (!checksumInput) {
                                             checksumInput = document.createElement('input');
                                             checksumInput.type = 'hidden';
                                             checksumInput.name = 'checksum';
                                             checksumInput.id = 'apply-checksum';
                                             document.getElementById('apply-update-form').appendChild(checksumInput);
                                        }
                                        checksumInput.value = data.update_info.checksum;
                                    } else {
                                         if (checksumInput) checksumInput.remove(); // Remove if checksum not provided in update
                                    }

                                    availableSectionDiv.style.display = 'block';
                                } else {
                                     // Should ideally not happen if the structure is consistent, but handle error
                                     resultDiv.innerHTML = '<div class="alert alert-danger">خطای داخلی: ساختار نمایش آپدیت یافت نشد.</div>';
                                }
                            } else if (data.update_info && data.update_info.error) {
                                // Specific error from server
                                if (errorMsgDiv) {
                                     errorMsgDiv.textContent = data.update_info.error;
                                     errorMsgDiv.style.display = 'block';
                                } else {
                                     resultDiv.innerHTML = '<div class="alert alert-danger">' + data.update_info.error + '</div>';
                                }
                            } else {
                                // No update available
                                if (notAvailableMsgDiv) {
                                     notAvailableMsgDiv.style.display = 'block';
                                } else {
                                     resultDiv.innerHTML = '<div class="alert alert-info">سیستم شما به‌روز است.</div>';
                                }
                            }
                        })
                        .catch(() => {
                            resultDiv.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور.</div>';
                        })
                        .finally(() => {
                             // Re-enable button and restore text
                             checkButton.disabled = false;
                            checkButton.innerHTML = originalButtonText;
                        });
                });
                </script>

                <!-- Script to show 'processing' state on apply update form submit -->
                <script>
                var applyForm = document.getElementById('apply-update-form');
                if (applyForm) {
                    applyForm.addEventListener('submit', function() {
                        var applyButton = this.querySelector('button[type="submit"]');
                        if (applyButton) {
                            applyButton.disabled = true;
                            applyButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> در حال انجام به‌روزرسانی... لطفاً صبر کنید.';
                        }
                        // Optional: You could add a message somewhere else on the page too.
                    });
                }
                </script>

                <div class="card mt-4 mb-0 shadow-sm">
                    <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                        تاریخچه به‌روزرسانی
                        <a href="/app/system/update" class="btn btn-sm btn-outline-secondary">مشاهده همه</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>شماره نسخه</th>
                                        <th>تاریخ به‌روزرسانی</th>
                                        <th>نتیجه</th>
                                        <th>گزارش</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($update_history)): ?>
                                        <?php foreach ($update_history as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['version']); ?></td>
                                                <td><?php echo jdate('Y/m/d H:i', strtotime($row['update_time'])); ?></td>
                                                <td>
                                                    <?php if ($row['status'] === 'success'): ?>
                                                        <span class="badge bg-success">موفق</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">ناموفق</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="/app/system/update/report/<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-info">مشاهده گزارش</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted">هیچ سابقه‌ای ثبت نشده است.</td></tr>
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

<!-- Script to show processing state for Create Backup -->
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