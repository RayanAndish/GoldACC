<?php
/**
 * Template: src/views/assays/form.php
 * Form for adding or editing an Assay Office.
 * Receives data via $viewData array from AssayOfficeController.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$isEditMode = $viewData['is_edit_mode'] ?? false;
$pageTitle = $viewData['page_title'] ?? ($isEditMode ? 'ویرایش مرکز ری‌گیری' : 'افزودن مرکز ری‌گیری');
$formAction = $viewData['form_action'] ?? '';
$assayOffice = $viewData['assay_office'] ?? ['id' => null, 'name' => '', 'phone' => '', 'address' => ''];
$submitButtonText = $viewData['submit_button_text'] ?? ($isEditMode ? 'به‌روزرسانی' : 'ذخیره');
$errorMessage = $viewData['error_message'] ?? null; // Validation errors
$loadingError = $viewData['loading_error'] ?? null; // Error fetching data for edit
$baseUrl = $viewData['baseUrl'] ?? '';

?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php // --- Display Messages --- ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo $errorMessage; // Error messages might contain <br>, do not escape fully? Check controller source. Or escape each line. ?>
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
        <h5 class="mb-0"><?php echo $isEditMode ? 'اطلاعات مرکز ری‌گیری' : 'افزودن مرکز جدید'; ?></h5>
    </div>
    <div class="card-body">
        <?php // Only show form if data loading was successful in edit mode, or if adding ?>
        <?php if (!$loadingError || !$isEditMode): ?>
            <form action="<?php echo Helper::escapeHtml($formAction); ?>" method="POST" id="assayOfficeForm">
                <?php // TODO: Add CSRF token input here ?>

                <?php /* Hidden ID field for edit mode */ ?>
                <?php if ($isEditMode): ?>
                    <input type="hidden" name="office_id" value="<?php echo Helper::escapeHtml($assayOffice['id'] ?? ''); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="name" class="form-label">نام مرکز <span class="text-danger" title="اجباری">*</span></label>
                    <input type="text"
                           class="form-control <?php echo ($errorMessage && str_contains($errorMessage, 'نام مرکز')) ? 'is-invalid' : ''; ?>"
                           id="name"
                           name="name"
                           value="<?php echo Helper::escapeHtml($assayOffice['name']); // Value from controller ?>"
                           required
                           maxlength="150"
                           placeholder="نام رسمی مرکز ری‌گیری">
                     <?php /* Display specific field error if available */ ?>
                </div>

                 <div class="mb-3">
                    <label for="phone" class="form-label">شماره تلفن</label>
                    <input type="text"
                           class="form-control"
                           id="phone"
                           name="phone"
                           value="<?php echo Helper::escapeHtml($assayOffice['phone'] ?? ''); ?>"
                           maxlength="50"
                           placeholder="اختیاری">
                     <div class="form-text">در صورت تمایل شماره تماس مرکز را وارد کنید.</div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">آدرس</label>
                    <textarea class="form-control"
                              id="address"
                              name="address"
                              rows="3"
                              placeholder="اختیاری"><?php echo Helper::escapeHtml($assayOffice['address'] ?? ''); ?></textarea>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                     <?php // Back button URL ?>
                     <a href="<?php echo $baseUrl; ?>/app/assay-offices" class="btn btn-outline-secondary px-4">
                        انصراف و بازگشت
                     </a>
                    <button type="submit" class="btn btn-primary px-5">
                       <?php echo Helper::escapeHtml($submitButtonText); ?>
                    </button>
                </div>
            </form>
        <?php else: ?>
             <p class="text-danger">خطا در بارگذاری اطلاعات. لطفاً به لیست بازگردید.</p>
             <a href="<?php echo $baseUrl; ?>/app/assay-offices" class="btn btn-outline-secondary px-4">
                بازگشت به لیست
             </a>
        <?php endif; ?>
    </div> <?php // end card body ?>
</div> <?php // end card ?>