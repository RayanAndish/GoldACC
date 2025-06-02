<?php
// --- شروع کد PHP برای آماده‌سازی داده‌ها ---

$categories = $viewData['categories'] ?? [];
$product = $viewData['product'] ?? null; // Object or null
// ========== START: Debug Dump ==========
if ($product instanceof \App\Models\Product) {
    echo '<!-- DEBUG INFO:';
    echo ' Product ID: ' . $product->id;
    echo ', Quantity: '; var_dump($product->quantity);
    echo ', Weight: '; var_dump($product->weight);
    echo ', Coin Year: '; var_dump($product->coin_year);
    echo ', Capital Quantity: '; var_dump($product->capital_quantity);
    echo ', Capital Weight: '; var_dump($product->capital_weight_grams);
    echo ' -->';
}
// ========== END: Debug Dump ==========
$errors = $viewData['errors'] ?? [];
$oldData = $viewData['form_data'] ?? [];

$categoryFeaturesData = [];
foreach ($categories as $cat) {
    if ($cat instanceof \App\Models\ProductCategory && $cat->id !== null) {
        $categoryFeaturesData[$cat->id] = [
            'requires_quantity' => (bool)$cat->requires_quantity,
            'requires_weight'   => (bool)$cat->requires_weight,
            'requires_carat'    => (bool)$cat->requires_carat,
            'requires_coin_year' => (bool)$cat->requires_coin_year,
        ];
    }
}

// --- دسترسی امن به نام محصول برای عنوان صفحه ---
$productName = ($product instanceof \App\Models\Product) ? $product->name : '';
$pageTitle = $product && $product->id ? "ویرایش محصول: " . htmlspecialchars($productName) : "افزودن محصول جدید";
// --- پایان اصلاح ---

// --- دسترسی امن به ID محصول برای فرم اکشن ---
$productIdForAction = ($product instanceof \App\Models\Product) ? $product->id : null;
$baseUrl = rtrim($viewData['baseUrl'] ?? '', '/'); // Ensure no trailing slash initially
$appPrefix = '/app'; // Assuming your app routes are prefixed with /app
$formAction = $productIdForAction
    ? "{$baseUrl}{$appPrefix}/products/update/{$productIdForAction}"
    : "{$baseUrl}{$appPrefix}/products/store";

$submitButtonText = $productIdForAction ? "به‌روزرسانی محصول" : "ذخیره محصول";
$cancelUrl = "{$baseUrl}{$appPrefix}/products"; // Consistent cancel URL

// --- پایان کد PHP ---
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- اطمینان از صحت لینک بوت‌استرپ -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        /* استایل‌های اضافی در صورت نیاز */
        .error-message { color: red; font-size: 0.875em; }
        /* می‌توانید برای کنترل عرض فیلدها از کلاس‌های col-* بوت‌استرپ استفاده کنید */
        /* .form-control, .form-select { max-width: 400px; } */ /* مثال: محدود کردن حداکثر عرض */
    </style>
</head>
<body>
<div class="container mt-4">
    <h1><?= htmlspecialchars($pageTitle) ?></h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $field => $errorMsg): ?>
                    <li><?= htmlspecialchars($errorMsg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

    <form action="<?= htmlspecialchars($formAction) ?>" method="POST">
        <?php if ($product && $product->id): ?>
            <input type="hidden" name="_method" value="PUT">
        <?php endif; ?>

        <!-- ====== بخش اطلاعات اصلی محصول ====== -->
        <div class="card mb-4">
          <div class="card-header bg-light fw-bold">اطلاعات اصلی محصول</div>
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label for="product_name" class="form-label">نام محصول:</label>
                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="product_name" name="name" required
                       value="<?= htmlspecialchars($oldData['name'] ?? ($product instanceof \App\Models\Product ? $product->name : '')) ?>">
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
                <?php endif; ?>
              </div>
              <div class="col-md-4">
                <label for="product_category_id" class="form-label">دسته بندی:</label>
                <select class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" id="product_category_id" name="category_id" required>
                    <option value="">انتخاب کنید...</option>
                    <?php foreach ($categories as $category): ?>
                        <?php if ($category instanceof \App\Models\ProductCategory): ?>
                            <option value="<?= $category->id ?>"
                                <?php
                                    $currentProdCatId = ($product instanceof \App\Models\Product) ? $product->category_id : null;
                                    $selectedCategoryId = $oldData['category_id'] ?? $currentProdCatId;
                                    if ($selectedCategoryId !== null && (string)$selectedCategoryId === (string)$category->id) {
                                        echo 'selected';
                                    }
                                ?>
                                data-features='<?= json_encode($categoryFeaturesData[$category->id] ?? []) ?>'
                                data-code="<?= htmlspecialchars($category->code ?? '') ?>">
                                <?= htmlspecialchars($category->name) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category_id'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['category_id']) ?></div>
                <?php endif; ?>
              </div>
              <div class="col-md-4">
                <label for="unit_of_measure" class="form-label">واحد سنجش <span class="text-danger">*</span></label><small class="form-text text-muted">(گرم برای وزنی، عدد برای تعدادی)</small>
                <select class="form-select <?= isset($errors['unit_of_measure']) ? 'is-invalid' : '' ?>" id="unit_of_measure" name="unit_of_measure" required>
                    <?php
                        $selectedUnit = $oldData['unit_of_measure'] ?? ($product instanceof \App\Models\Product ? $product->unit_of_measure : 'gram');
                    ?>
                    <option value="gram" <?= ($selectedUnit === 'gram') ? 'selected' : ''; ?>>گرم (وزنی)</option>
                    <option value="count" <?= ($selectedUnit === 'count') ? 'selected' : ''; ?>>عدد (تعدادی)</option>
                </select>
                <?php if (isset($errors['unit_of_measure'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['unit_of_measure']) ?></div>
                <?php endif; ?>
            </div>
            </div>
            <div class="row g-3 mt-2">
              <div class="col-md-4">
                <label for="product_code" class="form-label">کد محصول (اختیاری):</label>
                <input type="text" class="form-control <?= isset($errors['product_code']) ? 'is-invalid' : '' ?>" id="product_code" name="product_code"
                       value="<?= htmlspecialchars((string)($oldData['product_code'] ?? ($product instanceof \App\Models\Product ? $product->product_code : ''))) ?>">
                <?php if (isset($errors['product_code'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['product_code']) ?></div>
                <?php endif; ?>
              </div>
              <div class="col-md-8">
                <label for="description" class="form-label">توضیحات (اختیاری):</label>
                <textarea class="form-control" id="description" name="description" rows="2"><?= htmlspecialchars((string)($oldData['description'] ?? ($product instanceof \App\Models\Product ? $product->description : ''))) ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- ====== بخش مالیات ====== -->
        <div class="card mb-4 border-warning">
          <div class="card-header bg-warning-subtle fw-bold">تنظیمات مالیات برای این محصول</div>
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="tax_enabled" name="tax_enabled" value="1" <?php if (!empty($product) && $product->tax_enabled) echo 'checked'; ?>>
                  <label class="form-check-label" for="tax_enabled">فعال بودن مالیات عمومی</label>
                </div>
              </div>
              <div class="col-md-3">
                <label for="tax_rate" class="form-label">نرخ مالیات عمومی (%)</label>
                <input type="number" step="0.01" min="0" max="100" id="tax_rate" name="tax_rate" class="form-control" value="<?php echo !empty($product) ? htmlspecialchars($product->tax_rate) : ''; ?>">
                <div class="form-text">در صورت فعال بودن، این نرخ برای محاسبه مالیات عمومی این کالا استفاده می‌شود.</div>
              </div>
              <div class="col-md-3">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="vat_enabled" name="vat_enabled" value="1" <?php if (!empty($product) && $product->vat_enabled) echo 'checked'; ?>>
                  <label class="form-check-label" for="vat_enabled">فعال بودن مالیات بر ارزش افزوده</label>
                </div>
              </div>
              <div class="col-md-3">
                <label for="vat_rate" class="form-label">نرخ مالیات بر ارزش افزوده (%)</label>
                <input type="number" step="0.01" min="0" max="100" id="vat_rate" name="vat_rate" class="form-control" value="<?php echo !empty($product) ? htmlspecialchars($product->vat_rate) : ''; ?>">
                <div class="form-text">در صورت فعال بودن، این نرخ برای محاسبه مالیات بر ارزش افزوده این کالا استفاده می‌شود.</div>
              </div>
            </div>
          </div>
        </div>

        <!-- ====== بخش ویژگی‌های خاص و سرمایه ====== -->
        <div class="card mb-4 border-info">
          <div class="card-header bg-info-subtle fw-bold">ویژگی‌های خاص دسته‌بندی و سرمایه</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4" id="field_quantity">
                <label for="quantity" class="form-label">تعداد ( موجودی اولیه):</label>
                <input type="number" step="any" class="form-control form-control-sm" id="quantity" name="quantity"
                       value="<?= htmlspecialchars((string)($oldData['quantity'] ?? ($product instanceof \App\Models\Product ? $product->quantity : ''))) ?>">
                <small class="form-text text-muted">این فیلد در حال حاضر استفاده نمی‌شود. موجودی اولیه از صفحه 'موجودی اول دوره' ثبت می‌شود.</small>
              </div>
              <div class="col-md-4" id="field_weight">
                <label for="weight" class="form-label">وزن ( موجودی اولیه):</label>
                <input type="number" step="any" class="form-control form-control-sm" id="weight" name="weight"
                       value="<?= htmlspecialchars((string)($oldData['weight'] ?? ($product instanceof \App\Models\Product ? $product->weight : ''))) ?>">
                <small class="form-text text-muted">این فیلد در حال حاضر استفاده نمی‌شود. موجودی اولیه از صفحه 'موجودی اول دوره' ثبت می‌شود.</small>
              </div>
              <div class="col-md-4" id="field_carat">
                <label for="carat" class="form-label">عیار پیش‌فرض محصول:</label>
                <input type="number" step="any" class="form-control form-control-sm" id="carat" name="default_carat"
                       value="<?= htmlspecialchars((string)($oldData['default_carat'] ?? ($product instanceof \App\Models\Product ? $product->default_carat : ''))) ?>">
                <small class="form-text text-muted">عیار پیش‌فرض این محصول برای نمایش و محاسبات.</small>
              </div>
              <div class="col-md-4" id="field_coin_year">
                <label for="coin_year" class="form-label">سال ضرب سکه (پیش‌فرض):</label>
                <input type="number" class="form-control form-control-sm" id="coin_year" name="coin_year"
                       value="<?= htmlspecialchars((string)($oldData['coin_year'] ?? ($product instanceof \App\Models\Product ? $product->coin_year : ''))) ?>">
                <small class="form-text text-muted">این فیلد در حال حاضر استفاده نمی‌شود.</small>
              </div>
              <div class="col-md-4" id="field_capital_quantity">
                <label for="capital_quantity" class="form-label">سرمایه تعدادی (عدد):</label>
                <input type="number" step="any" class="form-control form-control-sm format-number-js" data-decimals="0" id="capital_quantity" name="capital_quantity"
                       value="<?= htmlspecialchars((string)($oldData['capital_quantity'] ?? ($product instanceof \App\Models\Product ? $product->capital_quantity : ''))) ?>">
                <small class="form-text text-muted">تعداد هدف برای تراز موجودی این کالا.</small>
              </div>
              <div class="col-md-4" id="field_capital_weight">
                <label for="capital_weight_grams" class="form-label">سرمایه وزنی (گرم):</label>
                <div class="input-group input-group-sm">
                  <input type="number" step="any" class="form-control form-control-sm format-number-js" data-decimals="4" id="capital_weight_grams" name="capital_weight_grams"
                         value="<?= htmlspecialchars((string)($oldData['capital_weight_grams'] ?? ($product instanceof \App\Models\Product ? $product->capital_weight_grams : ''))) ?>">
                  <span class="input-group-text">گرم</span>
                </div>
                <small class="form-text text-muted">وزن هدف برای تراز موجودی این کالا.</small>
              </div>
              <div class="col-md-4">
                <label for="capital_reference_carat" class="form-label">عیار مبنای سرمایه:</label>
                <input type="number" step="1" class="form-control form-control-sm" id="capital_reference_carat" name="capital_reference_carat"
                       value="<?= htmlspecialchars((string)($oldData['capital_reference_carat'] ?? ($product instanceof \App\Models\Product ? $product->capital_reference_carat : '750'))) ?>">
                <small class="form-text text-muted">عیاری که وزن سرمایه بر اساس آن است.</small>
              </div>
            </div>
          </div>
        </div>

        <!-- ====== وضعیت فعال بودن ====== -->
        <div class="mb-4">
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                <?php
                    $currentProdIsActive = ($product instanceof \App\Models\Product) ? $product->is_active : true;
                    $isActive = $oldData['is_active'] ?? $currentProdIsActive;
                    echo ($isActive == '1' || $isActive === true) ? 'checked' : '';
                ?>>
            <label class="form-check-label" for="is_active">محصول فعال باشد</label>
          </div>
        </div>

        <!-- ====== دکمه‌ها ====== -->
        <div class="d-flex justify-content-end gap-2 mb-2">
          <button type="submit" class="btn btn-primary px-4"><?= htmlspecialchars($submitButtonText) ?></button>
          <a href="<?= htmlspecialchars($cancelUrl) ?>" class="btn btn-secondary">انصراف</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->

<script>
    // No need for categoryFeaturesData here as we add it directly to options

    function updateFields() {
        const categorySelect = document.getElementById('product_category_id');
        if (!categorySelect) {
             console.error("Category select element not found!");
             return;
        }
        const selectedOption = categorySelect.options[categorySelect.selectedIndex];
        let features = null;
        try {
            // Get features directly from the selected option's data attribute
            features = selectedOption ? JSON.parse(selectedOption.getAttribute('data-features') || '{}') : null;
        } catch (e) {
             console.error("Error parsing category features JSON:", e);
             features = null; // Fallback to null if JSON is invalid
        }

        // Get all dependent field containers
        const quantityField = document.getElementById('field_quantity');
        const weightField = document.getElementById('field_weight');
        const caratField = document.getElementById('field_carat');
        const coinYearField = document.getElementById('field_coin_year');
        const capitalQuantityField = document.getElementById('field_capital_quantity');
        const capitalWeightField = document.getElementById('field_capital_weight');

        // Helper function to toggle visibility
        const toggleVisibility = (element, shouldShow) => {
            if (element) {
                if (shouldShow) {
                    element.classList.remove('d-none');
                } else {
                    element.classList.add('d-none');
                }
            }
        };

        // Toggle visibility based on features
        if (features) {
            toggleVisibility(quantityField, features.requires_quantity);
            toggleVisibility(weightField, features.requires_weight);
            toggleVisibility(caratField, features.requires_carat); // This is for default_carat
            toggleVisibility(coinYearField, features.requires_coin_year);
            // Toggle capital fields based on the same logic
            toggleVisibility(capitalQuantityField, features.requires_quantity);
            toggleVisibility(capitalWeightField, features.requires_weight);
        } else {
             // Hide all if no category selected or features invalid
             toggleVisibility(quantityField, false);
             toggleVisibility(weightField, false);
             toggleVisibility(caratField, false);
             toggleVisibility(coinYearField, false);
             toggleVisibility(capitalQuantityField, false);
             toggleVisibility(capitalWeightField, false);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('product_category_id');
        if (categorySelect) {
            categorySelect.addEventListener('change', updateFields);
            // Run on initial load to set the correct state based on pre-selected category
            updateFields();
        } else {
             console.warn("Category select element not found on DOMContentLoaded.");
        }

        // Initialize number formatting if the function exists
        if (typeof initNumberFormatting === 'function') {
            initNumberFormatting();
        } else {
             console.warn("initNumberFormatting function not found. Skipping number format initialization.");
             // You might need to include the JS file that defines this function
        }
    });

    // ========== START: Add Unit Update Logic ==========
    function updateUnitBasedOnCategory() {
        const categorySelect = document.getElementById('product_category_id');
        const unitSelect = document.getElementById('unit_of_measure');
        if (!categorySelect || !unitSelect) {
             console.warn("Category or Unit select not found for unit update.");
             return;
        }

        const selectedCategoryOption = categorySelect.options[categorySelect.selectedIndex];
        const categoryCode = selectedCategoryOption ? selectedCategoryOption.getAttribute('data-code') : null; // Get the code

        if (categoryCode === 'COIN') { // Check based on code
            unitSelect.value = 'count';
            unitSelect.disabled = true;
        } else {
            unitSelect.disabled = false;
            // Optional: If the unit was 'count' (e.g., switched from COIN) and wasn't pre-selected by PHP as 'count',
            // set it back to 'gram'. This prevents staying on 'count' after switching away from COIN.
            if (unitSelect.value === 'count') {
                 let phpSelectedCount = false;
                 for(let opt of unitSelect.options) {
                     if (opt.value === 'count' && opt.hasAttribute('selected')) {
                         phpSelectedCount = true;
                         break;
                     }
                 }
                 if(!phpSelectedCount) {
                    // Check if 'gram' option exists before setting
                    if (unitSelect.querySelector('option[value="gram"]')) {
                        unitSelect.value = 'gram';
                    }
                 }
            }
        }
    }

    // Modify updateFields to call the new function
    function updateFields() {
        const categorySelect = document.getElementById('product_category_id');
        if (!categorySelect) {
             console.error("Category select element not found!");
             return;
        }
        const selectedOption = categorySelect.options[categorySelect.selectedIndex];
        let features = null;
        try {
            features = selectedOption ? JSON.parse(selectedOption.getAttribute('data-features') || '{}') : null;
        } catch (e) {
             console.error("Error parsing category features JSON:", e);
             features = null;
        }

        const quantityField = document.getElementById('field_quantity');
        const weightField = document.getElementById('field_weight');
        const caratField = document.getElementById('field_carat');
        const coinYearField = document.getElementById('field_coin_year');
        const capitalQuantityField = document.getElementById('field_capital_quantity');
        const capitalWeightField = document.getElementById('field_capital_weight');

        const toggleVisibility = (element, shouldShow) => {
            if (element) {
                element.classList.toggle('d-none', !shouldShow);
            }
        };

        if (features) {
            toggleVisibility(quantityField, features.requires_quantity);
            toggleVisibility(weightField, features.requires_weight);
            toggleVisibility(caratField, features.requires_carat);
            toggleVisibility(coinYearField, features.requires_coin_year);
            toggleVisibility(capitalQuantityField, features.requires_quantity);
            toggleVisibility(capitalWeightField, features.requires_weight);
        } else {
             toggleVisibility(quantityField, false);
             toggleVisibility(weightField, false);
             toggleVisibility(caratField, false);
             toggleVisibility(coinYearField, false);
             toggleVisibility(capitalQuantityField, false);
             toggleVisibility(capitalWeightField, false);
        }

        // Call the unit update logic AFTER updating other fields
        updateUnitBasedOnCategory();
    }
    // ========== END: Add Unit Update Logic ==========

</script>

</body>
</html>