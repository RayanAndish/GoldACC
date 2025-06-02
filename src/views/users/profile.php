<?php
/**
 * Template: src/views/users/profile.php
 * Displays the logged-in user's profile and password change form.
 * Receives data via $viewData from UserController::showProfile.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'پروفایل کاربری';
$user = $viewData['user'] ?? null; // User info array (id, username, name)
$formActionPassword = $viewData['form_action_password'] ?? ''; // URL for password change POST
$successMessage = $viewData['success_message'] ?? ($viewData['flashMessage']['text'] ?? null); // Get success message
$errorMessagePassword = $viewData['error_message_password'] ?? null; // Password specific errors
$loadingError = $viewData['loading_error'] ?? null; // Error loading profile data
$baseUrl = $viewData['baseUrl'] ?? '';

?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php // --- Display Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo Helper::escapeHtml($successMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($errorMessagePassword): // Specific error for password section ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo Helper::escapeHtml($errorMessagePassword); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($loadingError): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo Helper::escapeHtml($loadingError); ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>اطلاعات حساب شما</h5>
    </div>
    <div class="card-body">
        <?php if ($user && !$loadingError): // Show info only if user data loaded ?>
            <form action="<?php echo Helper::escapeHtml($formActionPassword); ?>" method="POST" class="needs-validation" novalidate>
                 <input type="hidden" name="csrf_token" value="<?php echo Helper::generateCsrfToken(); ?>">
                 <div class="row">
                    <div class="col-md-6">
                        <?php // --- Display User Info (Read-only) --- ?>
                        <div class="mb-3">
                            <label class="form-label text-muted small">نام کاربری:</label>
                            <p class="form-control-plaintext fw-bold"><?php echo Helper::escapeHtml($user['username']); ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">نام نمایشی:</label>
                            <p class="form-control-plaintext"><?php echo Helper::escapeHtml($user['name'] ?: '-'); ?></p>
                        </div>
                        <?php // Display Role if needed (from user data) ?>
                        <?php /*
                        <div class="mb-4">
                            <label class="form-label text-muted small">نقش:</label>
                             <p class="form-control-plaintext"><?php echo Helper::escapeHtml($user['role_name'] ?? 'نامشخص'); ?></p>
                         </div>
                         */ ?>
                     </div>
                    <div class="col-md-6">
                         <?php // --- Password Change Form --- ?>
                         <div class="card bg-light border">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3"><i class="fas fa-key me-2"></i>تغییر رمز عبور</h6>
                                <p class="card-text small text-muted mb-3">برای تغییر رمز عبور، هر سه فیلد زیر را تکمیل کنید. در غیر این صورت، آن‌ها را خالی بگذارید.</p>

                                <div class="mb-3">
                                    <label for="current_password" class="form-label">رمز عبور فعلی <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control form-control-sm <?php echo ($errorMessagePassword && stripos($errorMessagePassword, 'فعلی') !== false) ? 'is-invalid' : ''; ?>"
                                           id="current_password" name="current_password" required>
                                     <div class="invalid-feedback">رمز عبور فعلی الزامی است.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">رمز عبور جدید <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control form-control-sm <?php echo ($errorMessagePassword && (stripos($errorMessagePassword, 'جدید') !== false || stripos($errorMessagePassword, 'مطابقت') !== false)) ? 'is-invalid' : ''; ?>"
                                           id="new_password" name="new_password" required pattern=".{6,}" title="حداقل ۶ کاراکتر">
                                    <div class="invalid-feedback">رمز عبور جدید (حداقل ۶ کاراکتر) الزامی است.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">تکرار رمز عبور جدید <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control form-control-sm <?php echo ($errorMessagePassword && stripos($errorMessagePassword, 'مطابقت') !== false) ? 'is-invalid' : ''; ?>"
                                           id="confirm_password" name="confirm_password" required>
                                    <div class="invalid-feedback">لطفا رمز عبور جدید را مجدداً وارد کنید (باید مطابقت داشته باشد).</div>
                                </div>
                                 <div class="text-end mt-3">
                                     <button type="submit" class="btn btn-primary btn-sm px-4">
                                         <i class="fas fa-save me-1"></i> ذخیره رمز عبور
                                     </button>
                                 </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php elseif (!$loadingError): ?>
             <p class="text-danger">اطلاعات پروفایل شما در دسترس نیست.</p>
        <?php endif; // End if user data loaded ?>
    </div> <?php // end card-body ?>
</div> <?php // end card ?>

<?php // --- JavaScript --- ?>
<script>
    // Bootstrap Validation Starter
    (() => { 'use strict'; const forms = document.querySelectorAll('.needs-validation'); Array.from(forms).forEach(f => { f.addEventListener('submit', e => { if (!f.checkValidity()) { e.preventDefault(); e.stopPropagation(); } f.classList.add('was-validated'); }, false) }) })();

    // Password confirmation validation
    const currentPassInput = document.getElementById('current_password');
    const newPassInput = document.getElementById('new_password');
    const confirmPassInput = document.getElementById('confirm_password');

    if (newPassInput && confirmPassInput && currentPassInput) {
        const checkPasswords = () => {
            let hasError = false;
            // Reset custom validity
            confirmPassInput.setCustomValidity('');
            newPassInput.setCustomValidity('');

            // Require all fields if any password field is filled
            const needsValidation = currentPassInput.value !== '' || newPassInput.value !== '' || confirmPassInput.value !== '';

            // Only set required if form is being submitted
            if (needsValidation && document.activeElement.tagName === 'FORM') {
                currentPassInput.required = true;
                newPassInput.required = true;
                confirmPassInput.required = true;
            }

            // Check match only if new password is valid according to pattern (length)
            if (needsValidation && newPassInput.validity.valid && newPassInput.value !== confirmPassInput.value) {
                confirmPassInput.setCustomValidity('رمز عبور جدید و تکرار آن مطابقت ندارند.');
                hasError = true;
            }

            // Optional: Add minimum length check for new password via JS as well
            if (needsValidation && newPassInput.value.length > 0 && newPassInput.value.length < 6) {
                newPassInput.setCustomValidity('رمز عبور جدید باید حداقل 6 کاراکتر باشد.');
                hasError = true;
            }

            // Trigger report validity for confirm password to show message immediately
            if (!hasError) {
                // If match is okay or fields are empty, report validity for confirm field
                confirmPassInput.setCustomValidity(''); // Ensure it's clear if match is okay
                // Don't call reportValidity() on input, let Bootstrap handle it on submit or blur
            } else {
                // Force reporting validity to show the mismatch message
                confirmPassInput.reportValidity();
            }
        };

        // Check on input in relevant fields
        confirmPassInput.addEventListener('input', checkPasswords);
        newPassInput.addEventListener('input', checkPasswords);
        currentPassInput.addEventListener('input', checkPasswords);

        // Add form submit event listener
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', () => {
                currentPassInput.required = true;
                newPassInput.required = true;
                confirmPassInput.required = true;
                checkPasswords();
            });
        }
    }
</script>