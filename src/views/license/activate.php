<?php
/**
 * Template: src/views/license/activate.php
 * Displays the license activation form.
 * Receives data via $viewData array from LicenseController.
 * Request code is generated server-side and passed via $viewData.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'فعال‌سازی سامانه';
$appName = $viewData['appName'] ?? 'حسابداری رایان طلا';
$baseUrl = $viewData['baseUrl'] ?? '/';
$errorMessage = $viewData['error'] ?? null;
$successMessage = $viewData['success'] ?? null;
$flashMessage = $viewData['flash_license_message'] ?? ($viewData['flashMessage'] ?? null);

$domain = $viewData['domain'] ?? '[نامشخص]';
$hardware_id = $viewData['hardware_id'] ?? '[نامشخص]';
$request_code = $viewData['request_code'] ?? '[خطا در تولید کد]'; // Generated by controller/service
$license_key = $viewData['license_key'] ?? ''; // For repopulation on error
$formAction = '/activate'; // مسیر ثابت برای فرم فعال‌سازی

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Helper::escapeHtml($pageTitle); ?> | <?php echo Helper::escapeHtml($appName); ?></title>
    <base href="<?php echo Helper::escapeHtml(rtrim($baseUrl, '/') . '/'); ?>">

    <?php // --- CSS Includes (Same as login) --- ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="css/style.css"> <?php // Main style ?>
    <style>
        /* Reuse login styles with slight adjustments */
        @font-face { font-family: 'Vazirmatn'; src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Round-Dots/fonts/webfonts/Vazirmatn-RD[wght].woff2') format('woff2 supports variations'), url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Round-Dots/fonts/webfonts/Vazirmatn-RD[wght].woff2') format('woff2-variations'); font-weight: 100 900; font-style: normal; font-display: swap; }
        html, body { height: 100%; }
        body { font-family: 'Vazirmatn', Tahoma, sans-serif !important; background-color: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .activate-container { width: 100%; max-width: 650px; padding: 15px; } /* Slightly wider */
        .card { border: none; border-radius: 0.75rem; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        .card-header { background-color: #17a2b8; color: #fff; text-align: center; padding: 1.5rem 1rem; border-top-left-radius: 0.75rem; border-top-right-radius: 0.75rem; border-bottom: none; }
        .card-header .fa-key { color: #ffc107; }
        .card-body { padding: 2rem 2.5rem; }
        .card-footer { background-color: #f8f9fa; text-align: center; padding: 0.8rem; font-size: 0.85rem; border-bottom-left-radius: 0.75rem; border-bottom-right-radius: 0.75rem; border-top: 1px solid #eee; }
        .form-label { font-weight: 500; margin-bottom: 0.5rem; font-size: 0.9rem; }
        .form-control, .form-select { font-family: 'Vazirmatn', sans-serif !important; font-size: 0.95rem; border-radius: 0.375rem; padding: 0.6rem 0.8rem; border: 1px solid #ced4da; }
        .form-control:read-only, .form-control[readonly] { background-color: #e9ecef; cursor: not-allowed; opacity: 0.8; }
        .btn-info { background-color: #17a2b8; border-color: #17a2b8; padding: 0.65rem 1.25rem; font-size: 1rem; font-weight: 500; border-radius: 0.375rem; transition: background-color 0.2s ease; color: white;}
        .btn-info:hover { background-color: #138496; border-color: #117a8b; }
        .alert { font-size: 0.9rem; margin-bottom: 1rem; padding: 0.8rem 1rem; border-radius: 0.375rem; }
        .request-code-display { background-color: #e9ecef; border: 1px solid #ced4da; padding: 10px 15px; border-radius: 5px; font-family: monospace; direction: ltr; word-break: break-all; text-align: left; cursor: copy; }
        .request-code-display:hover { background-color: #dde2e6; }
        .flash-messages-container { position: absolute; top: 10px; left: 50%; transform: translateX(-50%); z-index: 1050; width: 90%; max-width: 600px; }
    </style>
</head>
<body>
    <div class="activate-container">
        <div class="card shadow-lg">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-key me-2"></i><?php echo Helper::escapeHtml($pageTitle); ?></h4>
            </div>
            <div class="card-body">

                <?php // --- Display Messages --- ?>
                <?php if ($flashMessage && isset($flashMessage['text'])): ?>
                    <div class="alert alert-<?php echo Helper::escapeHtml($flashMessage['type'] ?? 'warning'); ?> alert-dismissible fade show shadow-sm" role="alert">
                        <?php echo Helper::escapeHtml($flashMessage['text']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($successMessage): ?>
                    <div class="alert alert-success small" role="alert"><?php echo Helper::escapeHtml($successMessage); ?></div>
                <?php endif; ?>
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger small" role="alert"><?php echo Helper::escapeHtml($errorMessage); ?></div>
                <?php endif; ?>

                <p class="text-danger fw-bold text-center mb-4">برای استفاده از سامانه، ابتدا آن را فعال‌سازی نمایید.<br> اطلاعات زیر را با کلیک بر روی دکمه "درخواست کد فعال‌سازی" برای واحد پشتیبانی ارسال کرده و سپس کد فعالسازی دریافتی را در فیلد "کد فعال‌سازی" وارد کنید.</p>

                <!-- مرحله 1: نمایش اطلاعات امنیتی و کد درخواست -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">مرحله 1: دریافت کد فعالسازی</h6>
                                        
                    <!-- نمایش تایمر -->
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-clock me-2"></i>
                        <span id="timerDisplay">در حال محاسبه...</span>
                    </div>
                    
                    <!-- نمایش اطلاعات سیستم -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">دامنه/آدرس:</label>
                            <input type="text" class="form-control form-control-sm" value="<?php echo Helper::escapeHtml($domain); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">شناسه سخت‌افزاری:</label>
                            <input type="text" class="form-control form-control-sm" value="<?php echo Helper::escapeHtml($hardware_id); ?>" readonly>
                        </div>
                    </div>

                    <!-- کد درخواست -->
                    <div class="mb-3">
                        <label class="form-label">شناسه درخواست کد فعالسازی</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm request-code-display" 
                                   value="<?php echo Helper::escapeHtml($request_code); ?>" readonly>
                            </div>
                            <p class="text-muted small mb-3">لطفاً اطلاعات بالا را با کلیک بر روی دکمه زیر ارسال کنید تا کد فعالسازی را دریافت نمایید:</p>
                    </div>

                    <!-- دکمه درخواست کد فعالسازی -->
                    <div class="text-center mb-4">
                        <button type="button" class="btn btn-outline-primary" id="emailSupportBtn">
                            <i class="fas fa-envelope"></i> درخواست کد فعالسازی
                        </button>
                    </div>
                </div>

                <!-- مودال درخواست کد فعالسازی -->
                <div class="modal fade" id="emailSupportModal" tabindex="-1" aria-labelledby="emailSupportModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="emailSupportModalLabel">درخواست کد فعالسازی</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="emailSupportForm">
                                    <div class="mb-3">
                                        <label for="supportEmail" class="form-label">ایمیل پشتیبانی</label>
                                        <input type="email" class="form-control" id="supportEmail" name="support_email" 
                                               value="support@<?php echo $_SERVER['HTTP_HOST']; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="additionalInfo" class="form-label">اطلاعات تکمیلی (اختیاری)</label>
                                        <textarea class="form-control" id="additionalInfo" name="additional_info" rows="3" 
                                                  placeholder="توضیحات یا اطلاعات تکمیلی خود را اینجا وارد کنید"></textarea>
                                    </div>
                                    <div class="alert alert-info small">
                                        <i class="fas fa-info-circle me-1"></i>
                                        اطلاعات سیستم و کد درخواست به صورت خودکار به ایمیل ارسال خواهد شد.
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                <button type="button" class="btn btn-primary" id="sendEmailBtn">
                                    <i class="fas fa-paper-plane me-1"></i> ارسال
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- مرحله 2: فرم وارد کردن کد فعالسازی -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">مرحله 2: وارد کردن کد فعالسازی</h6>
                    <p class="text-muted small mb-3">پس از دریافت کد فعالسازی از پشتیبانی، آن را در فرم زیر وارد کنید:</p>

                    <form method="post" action="<?php echo Helper::escapeHtml($formAction); ?>" class="needs-validation" novalidate>
                        <!-- فیلد کد فعالسازی -->
                        <div class="mb-3">
                            <label for="license_key" class="form-label">کد فعالسازی</label>
                            <input type="text" class="form-control" id="license_key" name="license_key" 
                                   value="<?php echo Helper::escapeHtml($license_key); ?>" required
                                   placeholder="لطفاً کد فعالسازی دریافتی از پشتیبانی را وارد کنید">
                            <div class="invalid-feedback">لطفاً کد فعالسازی را وارد کنید.</div>
                        </div>

                        <!-- دکمه‌های عملیات -->
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check me-1"></i> فعال‌سازی
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="refreshBtn" onclick="window.location.reload()" disabled>
                                <i class="fas fa-sync-alt me-1"></i> بارگذاری مجدد
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-footer text-muted small">
                در صورت بروز مشکل در کد فعالسازی با پشتیبانی تماس بگیرید.
            </div>
        </div>
    </div>

    <?php // --- JS Includes --- ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- JavaScript for Copy Button ---
        const copyBtn = document.getElementById('copyRequestCodeBtn');
        const requestCodeInput = document.querySelector('.request-code-display');
        const copySuccessMsg = document.getElementById('copySuccessMessage');

        if (copyBtn && requestCodeInput && copySuccessMsg) {
            copyBtn.addEventListener('click', () => {
                requestCodeInput.select();
                document.execCommand('copy');
                copySuccessMsg.style.display = 'block';
                setTimeout(() => {
                    copySuccessMsg.style.display = 'none';
                }, 2000);
            });
        }

        // --- Email Support Button ---
        const emailSupportBtn = document.getElementById('emailSupportBtn');
        const emailSupportForm = document.getElementById('emailSupportForm');
        const sendEmailBtn = document.getElementById('sendEmailBtn');
        const emailSupportModal = document.getElementById('emailSupportModal');
        const modal = new bootstrap.Modal(emailSupportModal);

        if (sendEmailBtn && emailSupportForm) {
            sendEmailBtn.addEventListener('click', async () => {
                try {
                    const formData = new FormData(emailSupportForm);
                    formData.append('domain', '<?php echo Helper::escapeHtml($domain); ?>');
                    formData.append('hardware_id', '<?php echo Helper::escapeHtml($hardware_id); ?>');
                    formData.append('request_code', '<?php echo Helper::escapeHtml($request_code); ?>');

                    sendEmailBtn.disabled = true;
                    sendEmailBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> در حال ارسال...';

                    const response = await fetch('/api/send-activation-info', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        // نمایش پیام موفقیت
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success alert-dismissible fade show';
                        alertDiv.innerHTML = `
                            <i class="fas fa-check-circle me-1"></i>
                            ${result.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        emailSupportForm.insertAdjacentElement('beforebegin', alertDiv);
                        
                        // بستن مودال بعد از 2 ثانیه
                        setTimeout(() => {
                            modal.hide();
                            emailSupportForm.reset();
                        }, 2000);
                    } else {
                        throw new Error(result.message || 'خطا در ارسال ایمیل');
                    }
                } catch (error) {
                    // نمایش پیام خطا
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="fas fa-exclamation-circle me-1"></i>
                        ${error.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    emailSupportForm.insertAdjacentElement('beforebegin', alertDiv);
                } finally {
                    sendEmailBtn.disabled = false;
                    sendEmailBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> ارسال';
                }
            });
        }
        
        // --- Bootstrap Validation ---
        (() => {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        let formTimer = null;
        let formExpiryTime = null;
        const FORM_TIMEOUT = 5 * 60 * 1000; // 5 minutes in milliseconds

        function startFormTimer() {
            const lastRequestTime = <?php echo $viewData['last_request_time'] ?? 0; ?>;
            const currentTime = Math.floor(Date.now() / 1000);
            const timeDiff = currentTime - lastRequestTime;
            const timeRemaining = Math.max(0, 300 - timeDiff) * 1000; // Convert to milliseconds
            
            formExpiryTime = Date.now() + timeRemaining;
            updateTimerDisplay();
            
            formTimer = setInterval(() => {
                const currentTime = Date.now();
                const timeLeft = formExpiryTime - currentTime;
                
                if (timeLeft <= 0) {
                    clearInterval(formTimer);
                    enableFormRefresh();
                    return;
                }
                
                updateTimerDisplay();
            }, 1000);
        }

        function updateTimerDisplay() {
            const currentTime = Date.now();
            const timeLeft = formExpiryTime - currentTime;
            
            if (timeLeft <= 0) {
                document.getElementById('timerDisplay').innerHTML = 'زمان به پایان رسیده است';
                return;
            }
            
            const minutes = Math.floor(timeLeft / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
            
            document.getElementById('timerDisplay').innerHTML = 
                `زمان باقی‌مانده: ${minutes}:${seconds.toString().padStart(2, '0')}`;
        }

        function enableFormRefresh() {
            document.getElementById('refreshBtn').disabled = false;
            document.getElementById('timerDisplay').innerHTML = 'می‌توانید فرم را رفرش کنید';
        }

        function disableFormRefresh() {
            document.getElementById('refreshBtn').disabled = true;
        }

        // Start timer when page loads
        document.addEventListener('DOMContentLoaded', () => {
            startFormTimer();
            disableFormRefresh();
        });
    </script>
</body>
</html>