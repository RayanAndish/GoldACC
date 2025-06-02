<?php
/**
 * Template: src/views/bank_accounts/form.php
 * Form for adding or editing a Bank Account.
 * Receives data via $viewData array from BankAccountController.
 */

use App\Utils\Helper; // Use the Helper class
use App\Core\CSRFProtector; // Use the CSRFProtector class

// --- Extract data from $viewData ---
$isEditMode = $viewData['is_edit_mode'] ?? false;
$pageTitle = $viewData['page_title'] ?? ($isEditMode ? 'ویرایش حساب بانکی' : 'افزودن حساب بانکی');
$formAction = $viewData['form_action'] ?? '';
$account = $viewData['account'] ?? ['id' => null, 'account_name' => '', 'bank_name' => '', 'account_number' => '', 'initial_balance' => '0', 'current_balance' => ''];
$submitButtonText = $viewData['submit_button_text'] ?? ($isEditMode ? 'به‌روزرسانی' : 'ذخیره');
$errorMessage = $viewData['error_message'] ?? null; // Validation errors from POST
$loadingError = $viewData['loading_error'] ?? null; // Error loading data in edit mode
$baseUrl = $viewData['baseUrl'] ?? '';

// Note: $account['initial_balance'] and $account['current_balance'] received from controller
// should be raw strings (as entered by user or formatted number without separators from DB)
// for correct display in the input fields. The JS will format them.

?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php // --- Display Messages --- ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo $errorMessage; // Allow potential <br> from controller ?>
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
        <h5 class="mb-0"><?php echo $isEditMode ? 'اطلاعات حساب بانکی' : 'افزودن حساب جدید'; ?></h5>
    </div>
    <div class="card-body">
        <?php if (!$loadingError || !$isEditMode): // Show form if no loading error or if adding ?>
            <form method="post" action="<?php echo $formAction; ?>" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo CSRFProtector::generateToken(); ?>">
                <?php if ($isEditMode): ?>
                    <input type="hidden" name="account_id" value="<?php echo Helper::escapeHtml($account['id']); ?>">
                     <?php // Store original initial balance for display if needed, or send it from controller ?>
                     <input type="hidden" name="initial_balance_display" value="<?php echo Helper::escapeHtml($account['initial_balance_display'] ?? $account['initial_balance']); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="account_name" class="form-label">نام حساب <span class="text-danger">*</span></label>
                    <input type="text"
                           class="form-control <?php echo ($errorMessage && stripos($errorMessage, 'نام حساب') !== false) ? 'is-invalid' : ''; ?>"
                           id="account_name" name="account_name"
                           value="<?php echo Helper::escapeHtml($account['account_name']); ?>" required maxlength="150"
                           placeholder="مثال: ملی اصلی، سامان مشترک">
                </div>

                <div class="row g-3">
                    <div class="col-md-6 mb-3"> <?php /* Add mb-3 here too */ ?>
                        <label for="bank_name" class="form-label">نام بانک</label>
                        <input type="text" class="form-control" id="bank_name" name="bank_name"
                               value="<?php echo Helper::escapeHtml($account['bank_name'] ?? ''); ?>" maxlength="100" placeholder="اختیاری">
                    </div>
                    <div class="col-md-6 mb-3"> <?php /* Add mb-3 here too */ ?>
                        <label for="account_number" class="form-label">شماره حساب / کارت</label>
                        <input type="text" dir="ltr" class="form-control" id="account_number" name="account_number"
                               value="<?php echo Helper::escapeHtml($account['account_number'] ?? ''); ?>" maxlength="50" placeholder="اختیاری">
                    </div>
                </div>

                 <div class="row g-3"> <?php /* Removed mt-2, using mb-3 on columns */ ?>
                    <div class="col-md-6 mb-3">
                        <label for="initial_balance" class="form-label">موجودی اولیه <span class="text-danger <?php echo $isEditMode ? 'd-none' : ''; ?>">*</span></label>
                         <div class="input-group">
                            <input type="text" <?php // Use text for JS formatting ?>
                                   class="form-control format-number-js <?php echo ($errorMessage && stripos($errorMessage, 'موجودی اولیه') !== false) ? 'is-invalid' : ''; ?>"
                                   id="initial_balance"
                                   name="initial_balance"
                                   value="<?php echo Helper::escapeHtml($account['initial_balance'] ?? '0'); // Default to '0' ?>"
                                   required
                                   <?php echo $isEditMode ? 'readonly style="background-color: #e9ecef;"' : ''; // Make readonly and visually disabled in edit mode ?>
                                   aria-describedby="initial-balance-addon"
                                   inputmode="numeric" <?php // Hint for mobile keyboards ?>
                                   >
                            <span class="input-group-text" id="initial-balance-addon">ریال</span>
                        </div>
                        <?php if ($isEditMode): ?>
                            <div class="form-text text-muted small">موجودی اولیه در زمان ویرایش قابل تغییر نیست.</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($isEditMode): ?>
                         <div class="col-md-6 mb-3">
                            <label for="current_balance" class="form-label">موجودی فعلی</label>
                            <div class="input-group">
                                 <input type="text" <?php // Use text for JS formatting ?>
                                       class="form-control format-number-js <?php echo ($errorMessage && stripos($errorMessage, 'موجودی فعلی') !== false) ? 'is-invalid' : ''; ?>"
                                       id="current_balance"
                                       name="current_balance"
                                       value="<?php echo Helper::escapeHtml($account['current_balance'] ?? '0'); ?>"
                                       <?php // Editability is dangerous - make readonly or add strong warning? Let's allow edit but with warning. ?>
                                       aria-describedby="current-balance-addon"
                                       inputmode="numeric">
                                <span class="input-group-text" id="current-balance-addon">ریال</span>
                            </div>
                            <div class="form-text text-warning small">
                                <i class="fas fa-exclamation-triangle fa-xs me-1"></i>
                                <strong>هشدار:</strong> تغییر دستی این مقدار فقط عدد نمایش داده شده را عوض می‌کند و مغایرت ایجاد خواهد کرد. برای اصلاح مانده، تراکنش بانکی (واریز/برداشت) ثبت کنید.
                            </div>
                        </div>
                     <?php endif; ?>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                     <a href="<?php echo $baseUrl; ?>/app/bank-accounts" class="btn btn-outline-secondary px-4">انصراف</a>
                    <button type="submit" class="btn btn-primary px-5"><?php echo Helper::escapeHtml($submitButtonText); ?></button>
                </div>
            </form>
        <?php else: ?>
            <p class="text-danger">خطا در بارگذاری اطلاعات. لطفاً به لیست بازگردید.</p>
            <a href="<?php echo $baseUrl; ?>/app/bank-accounts" class="btn btn-outline-secondary px-4">بازگشت به لیست</a>
        <?php endif; ?>
    </div>
</div>

<?php
// Add JS for number formatting if not included globally in layout footer
// This requires a JS library or custom script to handle formatting.
// Example placeholder:
?>
<script>
// Placeholder for number formatting script initialization
document.addEventListener('DOMContentLoaded', function() {
  // Find elements with class 'format-number-js' and apply formatting logic
  // Example (using a hypothetical formatNumberInput function):
  // const numberInputs = document.querySelectorAll('.format-number-js');
  // numberInputs.forEach(input => formatNumberInput(input));
  console.log("Initialize number formatting for inputs here.");
});
</script>