<?php
/**
 * Template: src/views/system/code_protection.php
 * UI for the insecure code 'encoding' tool. (Admin only)
 * Receives data via $viewData array from CodeProtectionController.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? "مدیریت 'رمزنگاری' کد (ناامن)";
$isSystemEncoded = $viewData['is_system_encoded'] ?? false; // Overall status
$fileStatuses = $viewData['file_statuses'] ?? []; // ['path/file.php' => ['status' => 'Encoded', 'encoded_exists' => true], ...]
$sensitiveFiles = $viewData['sensitive_files'] ?? []; // List of relative paths
$encodedPathDisplay = $viewData['encoded_path_display'] ?? '[unknown]';
$formAction = $viewData['form_action'] ?? ''; // URL for POST actions
$statusMessage = $viewData['status_message'] ?? null; // Message from previous action
$baseUrl = $viewData['baseUrl'] ?? '';

?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php // --- Display Status Message --- ?>
<?php if ($statusMessage): ?>
    <div class="alert alert-<?php echo Helper::escapeHtml($statusMessage['type'] ?? 'info'); ?> alert-dismissible fade show">
        <?php echo Helper::escapeHtml($statusMessage['text'] ?? ''); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php // --- Security Warning --- ?>
<div class="alert alert-danger" role="alert">
    <h4 class="alert-heading"><i class="fas fa-triangle-exclamation me-2"></i> هشدار جدی امنیتی!</h4>
    <p>این روش "رمزنگاری" (استفاده از base64 و eval) <strong>بسیار ناامن</strong> است و به راحتی قابل برگشت می‌باشد. این روش هیچ محافظت واقعی در برابر دسترسی یا تغییر کد ایجاد نمی‌کند و استفاده از تابع <code>eval()</code> ریسک‌های امنیتی قابل توجهی به همراه دارد و عملکرد را کاهش می‌دهد.</p>
    <hr>
    <p class="mb-0">استفاده از این بخش به هیچ وجه توصیه نمی‌شود. به جای آن، بر روی مکانیزم‌های صحیح لایسنس‌دهی و روش‌های استاندارد محافظت از مالکیت معنوی تمرکز کنید.</p>
</div>


<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">وضعیت فایل‌های حساس</h5>
    </div>
    <div class="card-body">
        <p class="small text-muted">فایل‌های زیر به عنوان فایل‌های حساس سیستم شناسایی شده‌اند. وضعیت 'رمزنگاری' (base64) آن‌ها در پوشه <code><?php echo Helper::escapeHtml($encodedPathDisplay); ?></code> نمایش داده می‌شود.</p>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>مسیر فایل</th>
                        <th class="text-center">وضعیت</th>
                        <th class="text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($sensitiveFiles)): ?>
                        <?php foreach ($sensitiveFiles as $relativeFilePath):
                            $statusInfo = $fileStatuses[$relativeFilePath] ?? ['status' => 'Unknown', 'encoded_exists' => false];
                            $statusText = $statusInfo['status'];
                            $encodedExists = $statusInfo['encoded_exists'];
                            $statusClass = 'secondary'; // Default for Unknown
                            if ($statusText === 'Encoded') $statusClass = 'success';
                            elseif ($statusText === 'Source Only') $statusClass = 'warning';
                            elseif ($statusText === 'Source Missing') $statusClass = 'danger';
                            elseif ($statusText === 'Encoded (Source Missing!)') $statusClass = 'danger';
                        ?>
                            <tr>
                                <td class="font-monospace small"><?php echo Helper::escapeHtml($relativeFilePath); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo Helper::escapeHtml($statusText); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($statusText !== 'Source Missing'): // Show buttons only if source exists or encoded exists ?>
                                        <?php if (!$encodedExists): ?>
                                            <button type="button" class="btn btn-primary btn-sm action-btn"
                                                    data-action="encode_file" data-file="<?php echo Helper::escapeHtml($relativeFilePath); ?>"
                                                    title="ایجاد فایل 'رمزنگاری شده' (ناامن)">
                                                <i class="fas fa-lock"></i> 'رمزنگاری'
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-danger btn-sm action-btn"
                                                    data-action="remove_encoded" data-file="<?php echo Helper::escapeHtml($relativeFilePath); ?>"
                                                    title="حذف فایل 'رمزنگاری شده'">
                                                <i class="fas fa-unlock"></i> حذف 'رمزنگاری'
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">هیچ فایل حساسی در تنظیمات تعریف نشده است.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php // --- Bulk Actions --- ?>
        <?php if (!empty($sensitiveFiles)): ?>
        <div class="mt-4 border-top pt-3 text-center">
             <h6 class="mb-3">عملیات گروهی</h6>
             <div class="btn-group" role="group">
                <button type="button" class="btn btn-primary action-btn" id="encode-all" data-action="encode_all"
                        title="همه فایل‌ها را 'رمزنگاری' می‌کند (اگر فایل منبع وجود داشته باشد)">
                    <i class="fas fa-lock"></i> 'رمزنگاری' همه
                </button>
                <button type="button" class="btn btn-danger action-btn" id="remove-all-encoded" data-action="remove_all_encoded"
                        title="فایل 'رمزنگاری شده' همه موارد را حذف می‌کند">
                    <i class="fas fa-unlock"></i> حذف همه 'رمزنگاری'‌ها
                </button>
             </div>
        </div>
        <?php endif; ?>

    </div> <?php // end card-body ?>
</div> <?php // end card ?>

<?php // --- Spinner Modal (Optional) --- ?>
<div class="modal fade" id="processingModal" tabindex="-1" aria-labelledby="processingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body text-center p-4">
        <div class="spinner-border text-primary mb-3" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <h5 id="processingModalLabel">در حال پردازش...</h5>
        <p class="text-muted small mb-0">لطفاً منتظر بمانید و صفحه را ترک نکنید.</p>
      </div>
    </div>
  </div>
</div>

<?php // --- JavaScript for AJAX Actions --- ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const actionButtons = document.querySelectorAll('.action-btn');
    const formActionUrl = '<?php echo Helper::escapeJs($formAction); ?>'; // Get URL from PHP
    const csrfToken = '<?php // TODO: Output CSRF token here ?>'; // Get CSRF token if implemented
    const processingModalElement = document.getElementById('processingModal');
    const processingModal = processingModalElement ? new bootstrap.Modal(processingModalElement) : null;

    async function handleActionClick(event) {
        const button = event.currentTarget;
        const action = button.dataset.action;
        const file = button.dataset.file; // Will be undefined for bulk actions
        let confirmMessage = 'آیا از انجام این عملیات مطمئن هستید؟';

        if (action === 'encode_file') confirmMessage = `آیا از 'رمزنگاری' فایل ${file} مطمئن هستید؟ (ناامن)`;
        else if (action === 'remove_encoded') confirmMessage = `آیا از حذف 'رمزنگاری' فایل ${file} مطمئن هستید؟`;
        else if (action === 'encode_all') confirmMessage = `آیا از 'رمزنگاری' همه فایل‌های حساس مطمئن هستید؟ (ناامن)`;
        else if (action === 'remove_all_encoded') confirmMessage = `آیا از حذف 'رمزنگاری' همه فایل‌ها مطمئن هستید؟`;

        if (!action || !confirm(confirmMessage)) {
            return;
        }

        // Show spinner modal
        if (processingModal) processingModal.show();
        disableAllButtons(true); // Disable buttons during processing

        const formData = new FormData();
        formData.append('action', action);
        if (file) { formData.append('file', file); }
        // formData.append('csrf_token', csrfToken); // Add CSRF token

        try {
            const response = await fetch(formActionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    // Add CSRF header if needed: 'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();

            if (response.ok && result.success) {
                // Success - reload the page to show updated status
                // Optionally show a success message briefly before reload
                alert('عملیات موفق: ' + (result.message || 'انجام شد.'));
                window.location.reload();
            } else {
                // Failure - show error message
                const message = result.message || `خطای ناشناخته (${response.status})`;
                alert('خطا: ' + message);
                console.error("Code Protection Error:", result);
                if (processingModal) processingModal.hide(); // Hide modal on error
                disableAllButtons(false); // Re-enable buttons on error
            }
        } catch (error) {
            console.error("Fetch Error:", error);
            alert('خطای شبکه در هنگام اجرای عملیات رخ داد.');
            if (processingModal) processingModal.hide();
            disableAllButtons(false);
        }
    }

    function disableAllButtons(disabled) {
         actionButtons.forEach(btn => btn.disabled = disabled);
    }

    actionButtons.forEach(button => {
        button.addEventListener('click', handleActionClick);
    });

});
</script>