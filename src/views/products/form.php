<?php
/**
 * REFACTORED Template: src/views/products/form.php (Final Version 2.1)
 * This version corrects access to view variables, solving the 'Undefined property' error,
 * and implements the new tax logic UI.
 */
use App\Utils\Helper;

// --- Extract ALL data from $viewData first ---
$product = $viewData['product'] ?? new \App\Models\Product();
$categories = $viewData['categories'] ?? [];
$errors = $viewData['errors'] ?? [];
$isEditMode = $product->id !== null;

// **FIX: Correct Form Action URL generation**
$formAction = $baseUrl . '/app/products/save';
if ($isEditMode) {
    $formAction .= '/' . $product->id;
}
$submitButtonText = $isEditMode ? "به‌روزرسانی" : "ذخیره محصول";
$cancelUrl = $baseUrl . '/app/products';


// **FIX: Access baseUrl and all other config from $viewData**
$baseUrl = $viewData['baseUrl'] ?? '';

// --- Prepare Page Titles and Form Actions ---
$pageTitle = $isEditMode ? "ویرایش محصول: " . Helper::escapeHtml($product->name) : "افزودن محصول جدید";

// Helper array for tax dropdowns
$taxBaseTypes = [
    'NONE' => 'مالیات ندارد',
    'WAGE_PROFIT' => 'بر اساس اجرت و سود',
    'PROFIT_ONLY' => 'فقط بر اساس سود'
];
?>

<h1 class="mb-4"><?= htmlspecialchars($pageTitle) ?></h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <p class="fw-bold">لطفاً خطاهای زیر را برطرف کنید:</p>
        <ul class="mb-0">
            <?php foreach ($errors as $errorMsg): ?>
                <li><?= htmlspecialchars($errorMsg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="<?= htmlspecialchars($formAction) ?>" method="POST" class="needs-validation" novalidate>
    <input type="hidden" name="id" value="<?= $product->id ?? '' ?>">
    <?php // CSRF token can be added here if needed, or handled globally ?>

    <!-- ====== بخش اطلاعات اصلی محصول ====== -->
    <div class="card mb-4">
        <div class="card-header bg-light fw-bold">اطلاعات اصلی محصول</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="name" class="form-label">نام محصول <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" required value="<?= Helper::escapeHtml($product->name) ?>">
                </div>
                <div class="col-md-4">
                    <label for="category_id" class="form-label">دسته بندی <span class="text-danger">*</span></label>
                    <select class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" id="category_id" name="category_id" required>
                        <option value="">انتخاب کنید...</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category->id ?>" <?= ($product->category_id == $category->id) ? 'selected' : '' ?>>
                                <?= Helper::escapeHtml($category->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="unit_of_measure" class="form-label">واحد سنجش <span class="text-danger">*</span></label>
                    <select class="form-select <?= isset($errors['unit_of_measure']) ? 'is-invalid' : '' ?>" id="unit_of_measure" name="unit_of_measure" required>
                        <option value="gram" <?= ($product->unit_of_measure === 'gram') ? 'selected' : '' ?>>گرم (وزنی)</option>
                        <option value="count" <?= ($product->unit_of_measure === 'count') ? 'selected' : '' ?>>عدد (تعدادی)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="product_code" class="form-label">کد محصول (اختیاری)</label>
                    <input type="text" class="form-control <?= isset($errors['product_code']) ? 'is-invalid' : '' ?>" id="product_code" name="product_code" value="<?= Helper::escapeHtml($product->product_code ?? '') ?>">
                </div>
                <div class="col-md-8">
                    <label for="description" class="form-label">توضیحات (اختیاری)</label>
                    <textarea class="form-control" id="description" name="description" rows="1"><?= Helper::escapeHtml($product->description ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== بخش جدید مالیات ====== -->
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning-subtle fw-bold">تنظیمات مالیات (بر اساس قوانین سامانه مودیان)</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="vat_base_type" class="form-label">پایه محاسبه مالیات بر ارزش افزوده</label>
                    <select class="form-select <?= isset($errors['vat_base_type']) ? 'is-invalid' : '' ?>" id="vat_base_type" name="vat_base_type">
                        <?php foreach($taxBaseTypes as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($product->vat_base_type === $key) ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">مشخص کنید مالیات بر ارزش افزوده بر چه اساسی محاسبه شود.</div>
                </div>
                <div class="col-md-6">
                    <label for="vat_rate" class="form-label">نرخ مالیات بر ارزش افزوده (%)</label>
                    <input type="number" step="0.01" min="0" max="100" id="vat_rate" name="vat_rate" class="form-control" value="<?= Helper::escapeHtml($product->vat_rate ?? '9') ?>">
                    <div class="form-text">نرخ پیش‌فرض ۹٪ است.</div>
                </div>
            </div>
            <hr class="my-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="general_tax_base_type" class="form-label">پایه محاسبه مالیات عمومی</label>
                    <select class="form-select <?= isset($errors['general_tax_base_type']) ? 'is-invalid' : '' ?>" id="general_tax_base_type" name="general_tax_base_type">
                        <?php foreach($taxBaseTypes as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($product->general_tax_base_type === $key) ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                     <div class="form-text">در صورت وجود، پایه محاسبه مالیات عمومی را مشخص کنید.</div>
                </div>
                 <div class="col-md-6">
                    <label for="tax_rate" class="form-label">نرخ مالیات عمومی (%)</label>
                    <input type="number" step="0.01" min="0" max="100" id="tax_rate" name="tax_rate" class="form-control" value="<?= Helper::escapeHtml($product->tax_rate ?? '0') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- ====== بخش ویژگی‌های خاص و سرمایه ====== -->
    <div class="card mb-4 border-info">
      <div class="card-header bg-info-subtle fw-bold">ویژگی‌های تکمیلی</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4" id="carat_field_container">
            <label for="default_carat" class="form-label">عیار پیش‌فرض محصول</label>
            <input type="number" step="0.01" class="form-control" id="default_carat" name="default_carat" value="<?= Helper::escapeHtml($product->default_carat ?? '') ?>">
            <small class="form-text text-muted">برای محصولات وزنی مانند آبشده یا ساخته شده.</small>
          </div>
          <div class="col-md-4">
            <label for="capital_quantity" class="form-label">سرمایه تعدادی (هدف)</label>
            <input type="number" step="1" class="form-control" id="capital_quantity" name="capital_quantity" value="<?= Helper::escapeHtml($product->capital_quantity ?? '') ?>">
            <small class="form-text text-muted">موجودی هدف برای کالاهای تعدادی مانند سکه.</small>
          </div>
          <div class="col-md-4">
            <label for="capital_weight_grams" class="form-label">سرمایه وزنی (هدف)</label>
            <div class="input-group">
              <input type="number" step="0.001" class="form-control" id="capital_weight_grams" name="capital_weight_grams" value="<?= Helper::escapeHtml($product->capital_weight_grams ?? '') ?>">
              <span class="input-group-text">گرم</span>
            </div>
            <small class="form-text text-muted">موجودی هدف برای کالاهای وزنی.</small>
          </div>
        </div>
      </div>
    </div>

    <!-- ====== وضعیت فعال بودن ====== -->
    <div class="mb-4">
      <div class="form-check form-switch fs-5">
        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= $product->is_active ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_active">محصول فعال باشد</label>
      </div>
    </div>

    <!-- ====== دکمه‌ها ====== -->
    <hr>
    <div class="d-flex justify-content-end gap-2 mb-2">
      <a href="<?= htmlspecialchars($cancelUrl) ?>" class="btn btn-secondary">انصراف</a>
      <button type="submit" class="btn btn-primary px-4"><?= htmlspecialchars($submitButtonText) ?></button>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const unitSelect = document.getElementById('unit_of_measure');
        const caratFieldContainer = document.getElementById('carat_field_container');

        function toggleCaratField() {
            if (unitSelect.value === 'count') {
                caratFieldContainer.style.display = 'none';
            } else {
                caratFieldContainer.style.display = 'block';
            }
        }
        unitSelect.addEventListener('change', toggleCaratField);
        toggleCaratField();
    });
</script>