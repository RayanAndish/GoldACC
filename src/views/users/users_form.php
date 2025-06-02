<?php
/**
 * Template: src/views/users/form.php
 * Form for adding or editing a User.
 * Receives data via $viewData array from UserController.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$isEditMode = $viewData['is_edit_mode'] ?? false;
$pageTitle = $viewData['page_title'] ?? ($isEditMode ? 'ویرایش کاربر' : 'افزودن کاربر');
$user = $viewData['user'] ?? ['id'=>null, 'username'=>'', 'name'=>'', 'role_id'=>null, 'is_active'=>1]; // Default structure
$roles = $viewData['roles'] ?? []; // Array of available roles ['id' => ..., 'role_name' => ...]
$formAction = $viewData['form_action'] ?? '';
$submitButtonText = $viewData['submit_button_text'] ?? ($isEditMode ? 'به‌روزرسانی' : 'ایجاد کاربر');
$errorMessage = $viewData['error_message'] ?? null; // Validation errors from POST
$loadingError = $viewData['loading_error'] ?? null; // Error loading roles etc.
$baseUrl = $viewData['baseUrl'] ?? '';

$currentUserId = $_SESSION['user_id'] ?? null; // Get current logged-in user ID
$isEditingSelf = $isEditMode && isset($user['id']) && $user['id'] == $currentUserId;
$isEditingSuperAdmin = $isEditMode && isset($user['id']) && $user['id'] === 1;

?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php // --- Display Messages --- ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo $errorMessage; // Allow potential <br> ?>
         <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($loadingError && !$errorMessage): // Show loading error only if no form error ?>
    <div class="alert alert-warning"><?php echo Helper::escapeHtml($loadingError); ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0"><?php echo $isEditMode ? 'ویرایش کاربر: '.Helper::escapeHtml($user['username']) : 'مشخصات کاربر جدید'; ?></h5>
    </div>
    <div class="card-body">
         <?php if ($loadingError && empty($roles) && !$isEditMode): // Prevent add form if roles failed loading ?>
              <p class="text-danger">خطا در بارگذاری نقش‌ها. امکان افزودن کاربر وجود ندارد.</p>
              <a href="<?php echo $baseUrl; ?>/app/users" class="btn btn-secondary mt-2">بازگشت به لیست</a>
         <?php else: ?>
             <form method="POST" action="<?php echo Helper::escapeHtml($formAction); ?>" class="needs-validation" novalidate>
                 <?php // TODO: Add CSRF token ?>
                 <?php if($isEditMode): ?>
                      <input type="hidden" name="id" value="<?php echo Helper::escapeHtml($user['id']); ?>">
                 <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="username" class="form-label">نام کاربری <span class="text-danger">*</span></label>
                         <input type="text" class="form-control form-control-sm <?php echo ($errorMessage && stripos($errorMessage, 'نام کاربری') !== false) ? 'is-invalid' : ''; ?>"
                                id="username" name="username"
                                value="<?php echo Helper::escapeHtml($user['username']); ?>"
                                required
                                <?php echo $isEditMode ? 'readonly style="background-color: #e9ecef;"' : ''; // Prevent username change on edit ?>
                                pattern="^[a-zA-Z0-9_]{3,20}$" <?php // Example validation pattern ?>
                                title="نام کاربری باید 3 تا 20 کاراکتر انگلیسی، عدد یا زیرخط باشد">
                        <?php if($isEditMode): ?><div class="form-text small text-muted">نام کاربری قابل تغییر نیست.</div><?php endif; ?>
                         <div class="invalid-feedback">نام کاربری معتبر (3-20 کاراکتر) وارد کنید.</div>
                     </div>
                    <div class="col-md-6">
                         <label for="name" class="form-label">نام نمایشی <small>(اختیاری)</small></label>
                         <input type="text" class="form-control form-control-sm" id="name" name="name"
                                value="<?php echo Helper::escapeHtml($user['name'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                         <label for="role_id" class="form-label">نقش کاربر <span class="text-danger">*</span></label>
                         <select class="form-select form-select-sm <?php echo ($errorMessage && stripos($errorMessage, 'نقش') !== false) ? 'is-invalid' : ''; ?>"
                                 id="role_id" name="role_id" required
                                 <?php echo $isEditingSuperAdmin ? 'disabled' : ''; // Disable role change for super admin ?> >
                              <option value="" disabled <?php echo empty($user['role_id']) ? 'selected' : ''; ?>>-- انتخاب نقش --</option>
                              <?php foreach ($roles as $role): ?>
                                 <option value="<?php echo (int)$role['id']; ?>" <?php echo (($user['role_id'] ?? 0) == $role['id']) ? 'selected' : ''; ?>>
                                     <?php echo Helper::escapeHtml($role['role_name']); // Assuming 'role_name' holds the display name ?>
                                 </option>
                             <?php endforeach; ?>
                              <?php if(empty($roles) && !$loadingError): ?><option value="" disabled>نقشی یافت نشد!</option><?php endif; ?>
                         </select>
                         <?php if($isEditingSuperAdmin): ?><div class="form-text small text-muted">نقش ادمین اصلی قابل تغییر نیست.</div><?php endif; ?>
                         <div class="invalid-feedback">انتخاب نقش الزامی است.</div>
                     </div>
                    <div class="col-md-6">
                         <label class="form-label d-block mb-2">وضعیت</label> <?php // Make label block ?>
                        <div class="form-check form-switch pt-1"> <?php // Add padding top for alignment ?>
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                    <?php echo (($user['is_active'] ?? 1) == 1) ? 'checked' : ''; ?>
                                    <?php echo $isEditingSuperAdmin ? 'disabled' : ''; // Disable status change for super admin ?> >
                             <label class="form-check-label small" for="is_active">کاربر فعال باشد</label>
                        </div>
                        <?php if($isEditingSuperAdmin): ?><div class="form-text small text-muted">وضعیت ادمین اصلی قابل تغییر نیست.</div><?php endif; ?>
                    </div>

                    <div class="col-12"><hr class="my-3"></div>

                    <?php // Password Section ?>
                    <div class="col-md-6">
                         <label for="password" class="form-label">
                             رمز عبور <?php echo !$isEditMode ? '<span class="text-danger">*</span>' : '<small>(در صورت عدم تغییر، خالی بگذارید)</small>'; ?>
                        </label>
                         <input type="password" class="form-control form-control-sm <?php echo ($errorMessage && stripos($errorMessage, 'رمز عبور') !== false) ? 'is-invalid' : ''; ?>"
                                id="password" name="password"
                                <?php echo !$isEditMode ? 'required' : ''; ?>
                                pattern=".{6,}" title="رمز عبور باید حداقل ۶ کاراکتر باشد">
                          <div class="invalid-feedback"><?php echo !$isEditMode ? 'رمز عبور (حداقل ۶ کاراکتر) الزامی است.' : 'اگر قصد تغییر دارید، رمز جدید (حداقل ۶ کاراکتر) را وارد کنید.'; ?></div>
                     </div>
                     <div class="col-md-6">
                          <label for="confirm_password" class="form-label">
                               تکرار رمز عبور <?php echo !$isEditMode ? '<span class="text-danger">*</span>' : ''; ?>
                          </label>
                         <input type="password" class="form-control form-control-sm" id="confirm_password" name="confirm_password"
                                <?php echo !$isEditMode ? 'required' : ''; ?>>
                         <div class="invalid-feedback">تکرار رمز عبور با رمز عبور جدید مطابقت ندارد.</div>
                    </div>

                </div> <?php // end row ?>

                <hr class="my-4">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?php echo $baseUrl; ?>/app/users" class="btn btn-outline-secondary px-4 btn-sm">
                       <i class="fas fa-arrow-left me-1"></i> بازگشت به لیست
                    </a>
                     <button type="submit" class="btn btn-primary px-5 btn-sm">
                        <i class="fas fa-save me-1"></i> <?php echo Helper::escapeHtml($submitButtonText); ?>
                     </button>
                 </div>

             </form>
        <?php endif; // End if !$loadingError ?>
     </div> <?php // end card-body ?>
</div> <?php // end card ?>

<?php // JS for validation ?>
<script>
 // Bootstrap starter validation script
 (() => {'use strict'; const forms = document.querySelectorAll('.needs-validation'); Array.from(forms).forEach(f => { f.addEventListener('submit', e => { if (!f.checkValidity()) { e.preventDefault(); e.stopPropagation(); } f.classList.add('was-validated'); }, false) }); })();

 // Password confirmation validation
 const newPassInput = document.getElementById('password');
 const confirmPassInput = document.getElementById('confirm_password');
 if (newPassInput && confirmPassInput) {
     const checkPasswordMatch = () => {
         if (newPassInput.value !== confirmPassInput.value && confirmPassInput.value !== '') {
             confirmPassInput.setCustomValidity('رمز عبور و تکرار آن مطابقت ندارند.');
             confirmPassInput.classList.add('is-invalid'); // Optional: Add class directly for feedback
         } else {
             confirmPassInput.setCustomValidity('');
             confirmPassInput.classList.remove('is-invalid');
         }
     };
     // Check on input in either field
     confirmPassInput.addEventListener('input', checkPasswordMatch);
     newPassInput.addEventListener('input', checkPasswordMatch);

     // Also check required state based on new password field in edit mode
     const isEdit = <?php echo $isEditMode ? 'true' : 'false'; ?>;
     if(isEdit) {
          newPassInput.addEventListener('input', () => {
               confirmPassInput.required = (newPassInput.value !== '');
          });
           // Set initial required state in case of repopulation
           confirmPassInput.required = (newPassInput.value !== '');
     }
 }
</script>