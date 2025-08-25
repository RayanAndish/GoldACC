<?php
/**
 * Template: src/views/contacts/form.php
 * Form for adding or editing a Contact (Customer/Supplier).
 * Receives data via $viewData array from ContactController.
 */

use App\Utils\Helper; // Use the Helper class
use App\Core\CSRFProtector; // Use the CSRFProtector class

// --- Extract data from $viewData ---
$isEditMode = $viewData['is_edit_mode'] ?? false;
$pageTitle = $viewData['page_title'] ?? ($isEditMode ? 'ویرایش مخاطب' : 'افزودن مخاطب');
$contact = $viewData['contact'] ?? ['id'=>null,'name'=>'','type'=>'counterparty','details'=>'','credit_limit'=>''];
$validContactTypes = $viewData['valid_contact_types'] ?? ['debtor', 'creditor_account', 'counterparty', 'mixed', 'other']; // Get valid types from controller
$formAction = $viewData['form_action'] ?? '';
$submitButtonText = $viewData['submit_button_text'] ?? ($isEditMode ? 'به‌روزرسانی' : 'ذخیره');
$errorMessage = $viewData['error_message'] ?? null; // Validation errors
$loadingError = $viewData['loading_error'] ?? null; // Error loading data for edit
$baseUrl = $viewData['baseUrl'] ?? '';

// Note: $contact['credit_limit'] from controller should be raw input value for repopulation
// or number formatted without separators for edit display.

?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php // --- Display Messages --- ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $errorMessage; /* Allow <br> */ ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($loadingError): ?>
    <div class="alert alert-warning">
        <?php echo Helper::escapeHtml($loadingError); ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
     <div class="card-header">
        <h5 class="mb-0"><?php echo $isEditMode ? 'ویرایش اطلاعات: ' . Helper::escapeHtml($contact['name']) : 'ورود اطلاعات مخاطب جدید'; ?></h5>
    </div>
    <div class="card-body">
         <?php if (!$loadingError || !$isEditMode): // Show form if no loading error or if adding ?>
            <form method="post" action="<?php echo $formAction; ?>" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo CSRFProtector::generateToken(); ?>">
                <?php if ($isEditMode): ?>
                    <input type="hidden" name="contact_id" value="<?php echo Helper::escapeHtml($contact['id']); ?>">
                <?php endif; ?>

                 <div class="mb-3">
                    <label for="name" class="form-label">نام / عنوان<span class="text-danger">*</span></label>
                    <input type="text"
                           class="form-control <?php echo ($errorMessage && stripos($errorMessage, 'نام / عنوان') !== false) ? 'is-invalid' : ''; ?>"
                           id="name" name="name"
                           value="<?php echo Helper::escapeHtml($contact['name']); ?>"
                           required maxlength="150">
                     <div class="invalid-feedback">لطفا نام یا عنوان مخاطب را وارد کنید.</div>
                </div>

                 <div class="mb-3">
                    <label for="type" class="form-label">ماهیت<span class="text-danger">*</span></label>
                    <select class="form-select <?php echo ($errorMessage && stripos($errorMessage, 'ماهیت') !== false) ? 'is-invalid' : ''; ?>" id="type" name="type" required>
                         <option value="" disabled <?php echo empty($contact['type']) ? 'selected' : ''; ?>>-- انتخاب کنید --</option>
                         <?php // Generate options from valid types ?>
                         <?php foreach ($validContactTypes as $typeValue): ?>
                             <option value="<?php echo Helper::escapeHtml($typeValue); ?>"
                                     <?php echo (($contact['type'] ?? '') === $typeValue) ? 'selected' : ''; ?>>
                                 <?php echo Helper::escapeHtml(Helper::getContactTypeFarsi($typeValue)); // Use Helper to translate ?>
                             </option>
                         <?php endforeach; ?>
                    </select>
                     <div class="invalid-feedback">لطفا ماهیت مخاطب را انتخاب کنید.</div>
                </div>

                 <div class="mb-3">
                    <label for="details" class="form-label">جزئیات <small>(اختیاری)</small></label>
                    <textarea class="form-control" id="details" name="details" rows="3" placeholder="تلفن، آدرس، شماره حساب، توضیحات..."><?php echo Helper::escapeHtml($contact['details'] ?? ''); ?></textarea>
                </div>

                <div class="mb-3">
                     <label for="credit_limit" class="form-label">سقف اعتبار <small class="text-muted">(ریال - اختیاری)</small></label>
                     <div class="input-group">
                          <input type="text" <?php // Use text for JS formatting ?>
                                 class="form-control format-number-js <?php echo ($errorMessage && stripos($errorMessage, 'اعتبار') !== false) ? 'is-invalid' : ''; ?>"
                                 id="credit_limit" name="credit_limit"
                                 value="<?php echo Helper::escapeHtml($contact['credit_limit'] ?? ''); // Raw value for input ?>"
                                 placeholder="0 یا خالی برای نامحدود"
                                 inputmode="numeric"
                                 aria-describedby="credit-limit-addon">
                         <span class="input-group-text" id="credit-limit-addon">ریال</span>
                     </div>
                     <div class="form-text small text-muted">حداکثر میزان بدهی مجاز این مخاطب به شما (یا طلب شما از او، بسته به ماهیت).</div>
                </div>

                <hr class="my-4">

                 <div class="d-flex justify-content-between align-items-center">
                     <a href="<?php echo $baseUrl; ?>/app/contacts" class="btn btn-outline-secondary px-4">
                       انصراف
                     </a>
                    <button type="submit" class="btn btn-primary px-5">
                        <?php echo Helper::escapeHtml($submitButtonText); ?>
                    </button>
                </div>
            </form>
        <?php else: ?>
            <p class="text-danger">خطا در بارگذاری اطلاعات. لطفاً به لیست بازگردید.</p>
            <a href="<?php echo $baseUrl; ?>/app/contacts" class="btn btn-outline-secondary px-4">بازگشت به لیست</a>
        <?php endif; ?>
    </div>
</div>

<?php // JS for Bootstrap validation and Number Formatting (if not global) ?>
<script>
    // Bootstrap validation
    (() => { 'use strict'; const forms = document.querySelectorAll('.needs-validation'); Array.from(forms).forEach(form => { form.addEventListener('submit', event => { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false) }) })();

    // Placeholder for number formatting script initialization
    // document.addEventListener('DOMContentLoaded', function() { ... });
</script>