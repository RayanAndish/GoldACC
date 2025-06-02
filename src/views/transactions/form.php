<?php
/**
 * Template: src/views/transactions/form.php
 * فرم برای ایجاد یا ویرایش یک معامله.
 * این فایل تمامی فیلدهای داینامیک اقلام معامله را در سمت PHP رندر می‌کند.
 */

use App\Utils\Helper;
use Morilog\Jalali\Jalalian; // اضافه شدن برای فرمت دهی تاریخ شمسی

// --- استخراج داده‌ها از $viewData ---
$pageTitle = $viewData['page_title'] ?? 'فرم معامله';
$isEditMode = $viewData['is_edit_mode'] ?? false;
$formAction = $viewData['form_action'] ?? '';
$csrfToken = $viewData['csrf_token'] ?? ''; // CSRF Token از کنترلر می‌آید
$baseUrl = $viewData['baseUrl'] ?? '';
$contactsData = $viewData['contactsData'] ?? [];
$assayOfficesData = $viewData['assayOfficesData'] ?? [];
$productsData = $viewData['productsData'] ?? []; // Product objects
$fieldsData = $viewData['fieldsData'] ?? []; // داده‌های فیلدهای داینامیک
$formulasData = $viewData['formulasData'] ?? []; // داده‌های فرمول‌های داینامیک
$transactionData = $viewData['transactionData'] ?? null;
$transactionItemsData = $viewData['transactionItemsData'] ?? [];
$defaultSettings = $viewData['default_settings'] ?? [];
$config = $viewData['config'] ?? []; // برای دسترسی به app.debug

// --- توابع کمکی PHP برای رندرینگ فیلدها ---
// این توابع به دلیل پیچیدگی رندر فیلدهای داینامیک در PHP در اینجا تعریف می‌شوند.
// این توابع مشابه منطق FieldManager در JS هستند.

/**
 * فیلدهای fields.json را بر اساس گروه فیلتر می‌کند.
 * @param array $allFields
 * @param string $group
 * @return array
 */
function getFieldsByGroup(array $allFields, string $group): array {
    if (empty($group)) return [];
    $groupLower = strtolower($group);
    return array_filter($allFields, function($field) use ($groupLower) {
        return isset($field['group']) && strtolower($field['group']) === $groupLower;
    });
}

/**
 * HTML یک فیلد داینامیک را تولید می‌کند.
 * @param array $field - تعریف فیلد از fields.json
 * @param int $index - شاخص ردیف آیتم
 * @param mixed $fieldValue - مقدار فعلی فیلد
 * @param array $assayOffices - لیست مراکز ری‌گیری (برای select)
 * @return string HTML فیلد
 */
function renderDynamicFieldHtml(array $field, int $index, $fieldValue, array $assayOffices): string {
    $fieldName = $field['name'] ?? '';
    $fieldLabel = $field['label'] ?? '';
    $fieldType = $field['type'] ?? 'text';
    $isRequired = $field['required'] ?? false;
    $colClass = $field['col_class'] ?? 'col-md-2';
    $isReadonly = $field['readonly'] ?? false;
    $step = $field['step'] ?? null;
    $min = $field['min'] ?? null;
    $max = $field['max'] ?? null;

    $html = "<div class=\"{$colClass}\">";
    $html .= "<label class=\"form-label\">{$fieldLabel}";
    if ($isRequired) $html .= " <span class=\"text-danger\">*</span>";
    $html .= "</label>";

    $inputClasses = ['form-control', 'form-control-sm'];
    if (isset($field['is_numeric']) && $field['is_numeric']) $inputClasses[] = 'autonumeric';
    if ($isReadonly) $inputClasses[] = 'readonly';

    $attributes = '';
    if ($isRequired) $attributes .= ' required';
    if ($isReadonly) $attributes .= ' readonly';
    if ($step !== null) $attributes .= " step=\"{$step}\"";
    if ($min !== null) $attributes .= " min=\"{$min}\"";
    if ($max !== null) $attributes .= " max=\"{$max}\"";

    // برای فیلدهای autonumeric، مقدار را بدون فرمت (فقط عدد) نمایش می‌دهیم
    if (isset($field['is_numeric']) && $field['is_numeric'] && is_numeric($fieldValue)) {
        $fieldValue = (float)$fieldValue; // اطمینان از نوع float
    }
    
    // مدیریت خاص برای item_has_attachments_manufactured
    if ($fieldName === 'item_has_attachments_manufactured') {
        $html .= "<select name=\"items[{$index}][{$fieldName}]\" class=\"form-select " . implode(' ', $inputClasses) . " item-attachments-toggle\"{$attributes}>";
        $html .= "<option value=\"yes\" " . (($fieldValue === 'yes') ? 'selected' : '') . ">دارد</option>";
        $html .= "<option value=\"no\" " . (($fieldValue === 'no') ? 'selected' : '') . ">ندارد</option>";
        $html .= "</select>";
    } elseif ($fieldType === 'select') {
        $html .= "<select name=\"items[{$index}][{$fieldName}]\" class=\"form-select " . implode(' ', $inputClasses) . "\"{$attributes}>";
        $html .= "<option value=\"\">انتخاب کنید...</option>";
        if (isset($field['options']) && is_array($field['options'])) {
            foreach ($field['options'] as $option) {
                $selected = (string)$option['value'] === (string)$fieldValue ? 'selected' : ''; // مقایسه به عنوان رشته
                $html .= "<option value=\"" . Helper::escapeHtml($option['value']) . "\" {$selected}>" . Helper::escapeHtml($option['label']) . "</option>";
            }
        } elseif ($fieldName === 'item_assay_office_melted') { // مورد خاص برای مراکز ری‌گیری
            foreach ($assayOffices as $office) {
                $selected = (string)$office['id'] === (string)$fieldValue ? 'selected' : ''; // مقایسه به عنوان رشته
                $html .= "<option value=\"" . (int)$office['id'] . "\" {$selected}>" . Helper::escapeHtml($office['name']) . "</option>";
            }
        }
        $html .= "</select>";
    } elseif ($fieldType === 'textarea') {
        $html .= "<textarea name=\"items[{$index}][{$fieldName}]\" class=\"form-control " . implode(' ', $inputClasses) . "\"{$attributes}>" . Helper::escapeHtml($fieldValue) . "</textarea>";
    } elseif ($fieldType === 'checkbox') {
        $checked = (bool)$fieldValue ? 'checked' : '';
        $html .= "<input type=\"checkbox\" name=\"items[{$index}][{$fieldName}]\" class=\"form-check-input " . implode(' ', $inputClasses) . "\" value=\"1\" {$checked}{$attributes}>";
    } else { // text, number, etc.
        $html .= "<input type=\"text\" name=\"items[{$index}][{$fieldName}]\" class=\"form-control " . implode(' ', $inputClasses) . "\" value=\"" . Helper::escapeHtml($fieldValue) . "\"{$attributes}>";
    }

    $html .= "<div class=\"invalid-feedback\">لطفا {$fieldLabel} را وارد کنید.</div>";
    $html .= "</div>"; // بستن col div
    return $html;
}

// --- تعیین مقادیر پیش‌فرض برای فیلدهای اصلی فرم ---
$defaultTransactionType = $transactionData['transaction_type'] ?? 'sell'; // پیش‌فرض فروش
$defaultDeliveryStatus = $transactionData['delivery_status'] ?? 'completed'; // وضعیت پیش‌فرض از دیتابیس یا 'completed'

// اگر در حالت افزودن هستیم و وضعیت تحویل به صورت دستی تنظیم نشده، آن را بر اساس نوع معامله تنظیم می‌کنیم
if (!$isEditMode && empty($transactionData['delivery_status'])) {
    $defaultDeliveryStatus = ($defaultTransactionType === 'buy') ? 'pending_receipt' : 'pending_delivery';
}

// فرمت دهی تاریخ معامله به شمسی برای نمایش در فیلد ورودی
$transactionDatePersian = '';
if (!empty($transactionData['transaction_date'])) {
    try {
        $dt = new DateTime($transactionData['transaction_date']);
        $transactionDatePersian = Jalalian::fromDateTime($dt)->format('Y/m/d H:i:s');
    } catch (Exception $e) {
        $transactionDatePersian = ''; // در صورت خطا، مقدار خالی
    }
} elseif (!$isEditMode) {
    $transactionDatePersian = Jalalian::now()->format('Y/m/d H:i:s'); // تاریخ فعلی برای حالت افزودن
}

// فرمت دهی تاریخ تحویل به شمسی برای نمایش در فیلد ورودی
$deliveryDatePersian = '';
if (!empty($transactionData['delivery_date'])) {
    try {
        $dt = new DateTime($transactionData['delivery_date']);
        $deliveryDatePersian = Jalalian::fromDateTime($dt)->format('Y/m/d H:i:s');
    } catch (Exception $e) {
        $deliveryDatePersian = ''; // در صورت خطا، مقدار خالی
    }
}

// --- نمایش پیام‌های خطا یا موفقیت از کنترلر ---
?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php if (isset($viewData['loading_error']) && $viewData['loading_error']): ?>
    <div class="alert alert-danger"><?php echo Helper::escapeHtml($viewData['loading_error']); ?></div>
<?php return; endif; ?>

<form id="transaction-form" action="<?php echo Helper::escapeHtml($formAction); ?>" method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo Helper::escapeHtml($csrfToken); ?>">
    <?php if ($isEditMode && isset($transactionData['id'])): ?>
        <input type="hidden" name="transaction_id" value="<?php echo (int)$transactionData['id']; ?>">
    <?php endif; ?>

    <!-- فیلدهای اصلی معامله -->
    <div class="row g-3">
        <?php if ($isEditMode): // نمایش شماره معامله فقط در حالت ویرایش ?>
        <div class="col-md-2">
            <label class="form-label">شماره معامله</label>
            <input type="text" class="form-control" value="<?php echo (int)$transactionData['id']; ?>" readonly>
        </div>
        <?php endif; ?>

        <div class="col-md-2">
            <label for="transaction_type" class="form-label">نوع معامله<span class="text-danger">*</span></label>
            <select id="transaction_type" name="transaction_type" class="form-select" required>
                <option value="sell" <?php echo ($defaultTransactionType === 'sell') ? 'selected' : ''; ?>>فروش</option>
                <option value="buy" <?php echo ($defaultTransactionType === 'buy') ? 'selected' : ''; ?>>خرید</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="counterparty_contact_id" class="form-label">طرف حساب<span class="text-danger">*</span></label>
            <select id="counterparty_contact_id" name="counterparty_contact_id" class="form-select" required>
                <option value="">انتخاب کنید...</option>
                <?php foreach ($contactsData as $contact): ?>
                    <option value="<?php echo (int)$contact['id']; ?>" <?php echo (($transactionData['counterparty_contact_id'] ?? '') == $contact['id']) ? 'selected' : ''; ?>>
                        <?php echo Helper::escapeHtml($contact['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <!-- فیلدهای مخفی برای اطلاعات طرف حساب که توسط JS پر می‌شوند -->
            <input type="hidden" name="party_name" value="<?php echo Helper::escapeHtml($transactionData['party_name'] ?? ''); ?>">
            <input type="hidden" name="party_phone" value="<?php echo Helper::escapeHtml($transactionData['party_phone'] ?? ''); ?>">
            <input type="hidden" name="party_national_code" value="<?php echo Helper::escapeHtml($transactionData['party_national_code'] ?? ''); ?>">
        </div>
        <div class="col-md-2">
            <label for="transaction_date" class="form-label">تاریخ معامله<span class="text-danger">*</span></label>
            <input type="text" id="transaction_date" name="transaction_date" class="form-control jalali-datepicker" value="<?php echo Helper::escapeHtml($transactionDatePersian); ?>" required>
        </div>
        <div class="col-md-2">
            <label for="mazaneh_price" class="form-label">قیمت مظنه</label>
            <input type="text" id="mazaneh_price" name="mazaneh_price" class="form-control autonumeric" value="<?php echo Helper::escapeHtml(Helper::formatNumber($transactionData['mazaneh_price'] ?? '0', 0, '.', '')); ?>">
        </div>
        <div class="col-md-3">
            <label for="delivery_status" class="form-label">وضعیت تحویل</label>
            <select id="delivery_status" name="delivery_status" class="form-select">
                <option value="completed" <?php echo ($defaultDeliveryStatus === 'completed') ? 'selected' : ''; ?>>تکمیل شده</option>
                <option value="pending_receipt" <?php echo ($defaultDeliveryStatus === 'pending_receipt') ? 'selected' : ''; ?>>منتظر دریافت</option>
                <option value="pending_delivery" <?php echo ($defaultDeliveryStatus === 'pending_delivery') ? 'selected' : ''; ?>>منتظر تحویل</option>
                <option value="cancelled" <?php echo ($defaultDeliveryStatus === 'cancelled') ? 'selected' : ''; ?>>لغو شده</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="delivery_date" class="form-label">تاریخ تحویل</label>
            <input type="text" id="delivery_date" name="delivery_date" class="form-control jalali-datepicker" value="<?php echo Helper::escapeHtml($deliveryDatePersian); ?>" <?php echo (!isset($transactionData['delivery_status']) || $transactionData['delivery_status'] == 'completed') ? '' : 'disabled'; ?>>
        </div>
    </div>

    <!-- بخش اقلام معامله -->
    <div class="card shadow-sm mb-4 mt-4">
        <div class="card-header fw-bold">اقلام معامله</div>
        <div class="card-body">
            <div id="transaction-items-container">
                <?php
                // اگر در حالت ویرایش هستیم و آیتم‌ها موجودند، آن‌ها را رندر می‌کنیم
                if ($isEditMode && !empty($transactionItemsData)):
                    foreach ($transactionItemsData as $index => $item):
                        // پیدا کردن محصول مربوط به این آیتم
                        $selectedProduct = null;
                        foreach ($productsData as $product) {
                            if ((is_object($product) ? $product->id : $product['id']) == $item['product_id']) {
                                $selectedProduct = $product;
                                break;
                            }
                        }

                        // تعیین گروه محصول (base_category)
                        $productGroup = '';
                        if ($selectedProduct) {
                            if (is_object($selectedProduct)) {
                                $productGroup = $selectedProduct->category->base_category ?? '';
                            } elseif (is_array($selectedProduct)) {
                                $productGroup = $selectedProduct['product_category_base'] ?? ''; // از JOIN در Repository
                            }
                            $productGroup = strtolower($productGroup);
                        }
                ?>
                        <div class="transaction-item-row border rounded p-3 mb-3 bg-light">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-3">
                                    <label class="form-label">کالا<span class="text-danger">*</span></label>
                                    <select name="items[<?php echo $index; ?>][product_id]" class="form-select form-select-sm product-select" required>
                                        <option value="">انتخاب کالا...</option>
                                        <?php foreach ($productsData as $product): ?>
                                            <option value="<?php echo is_object($product) ? $product->id : $product['id']; ?>" <?php echo ((is_object($product) ? $product->id : $product['id']) == $item['product_id']) ? 'selected' : ''; ?>>
                                                <?php echo Helper::escapeHtml(is_object($product) ? $product->name : $product['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="items[<?php echo $index; ?>][id]" value="<?php echo (int)$item['id']; ?>">
                                </div>
                                <div class="col-12 col-md-9">
                                    <div class="dynamic-fields-row row g-2">
                                        <?php
                                        // رندر فیلدهای داینامیک بر اساس گروه محصول
                                        $groupFields = getFieldsByGroup($fieldsData, $productGroup);
                                        
                                        // گروه‌بندی فیلدها بر اساس row_display
                                        $rows = [];
                                        foreach ($groupFields as $field) {
                                            $rowDisplay = $field['row_display'] ?? 'row1';
                                            if (!isset($rows[$rowDisplay])) {
                                                $rows[$rowDisplay] = [];
                                            }
                                            $rows[$rowDisplay][] = $field;
                                        }

                                        // رندر کردن هر ردیف فیلد
                                        foreach (array_keys($rows) as $rowKey) {
                                            echo '<div class="row g-2 mt-1 w-100">'; // ردیف جدید برای فیلدها
                                            foreach ($rows[$rowKey] as $field) {
                                                // تعیین مقدار فیلد از $item
                                                $fieldValue = $item[$field['name']] ?? null;

                                                // نگاشت نام فیلد در fields.json به نام ستون در $item (از دیتابیس)
                                                switch ($field['name']) {
                                                    case 'item_carat_melted':
                                                    case 'item_carat_manufactured':
                                                    case 'item_carat_goldbullion':
                                                    case 'item_carat_silverbullion':
                                                        $fieldValue = $item['carat'] ?? $fieldValue; break;
                                                    case 'item_weight_scale_melted':
                                                    case 'item_weight_scale_manufactured':
                                                    case 'item_weight_scale_goldbullion':
                                                    case 'item_weight_scale_silverbullion':
                                                    case 'item_weight_carat_jewelry':
                                                        $fieldValue = $item['weight_grams'] ?? $fieldValue; break;
                                                    case 'item_quantity_manufactured':
                                                    case 'item_quantity_coin':
                                                    case 'item_quantity_jewelry':
                                                        $fieldValue = $item['quantity'] ?? $fieldValue; break;
                                                    case 'item_unit_price_melted':
                                                    case 'item_unit_price_manufactured':
                                                    case 'item_unit_price_coin':
                                                    case 'item_unit_price_goldbullion':
                                                    case 'item_unit_price_silverbullion':
                                                    case 'item_unit_price_jewelry':
                                                        $fieldValue = $item['unit_price_rials'] ?? $fieldValue; break;
                                                    case 'item_total_price_melted':
                                                    case 'item_total_price_manufactured':
                                                    case 'item_total_price_coin':
                                                    case 'item_total_price_goldbullion':
                                                    case 'item_total_price_silverbullion':
                                                    case 'item_total_price_jewelry':
                                                        $fieldValue = $item['total_value_rials'] ?? $fieldValue; break;
                                                    case 'item_tag_number_melted':
                                                    case 'item_bullion_number_goldbullion':
                                                    case 'item_bullion_number_silverbullion':
                                                        $fieldValue = $item['tag_number'] ?? $fieldValue; break;
                                                    case 'item_assay_office_melted':
                                                        $fieldValue = $item['assay_office_id'] ?? $fieldValue; break;
                                                    case 'item_coin_year_coin':
                                                        $fieldValue = $item['coin_year'] ?? $fieldValue; break;
                                                    case 'item_vacuum_name_coin':
                                                        $fieldValue = $item['seal_name'] ?? $fieldValue; break;
                                                    case 'item_manufacturing_fee_amount_manufactured':
                                                        $fieldValue = $item['ajrat_rials'] ?? $fieldValue; break;
                                                    case 'item_workshop_manufactured':
                                                    case 'item_manufacturer_goldbullion':
                                                    case 'item_manufacturer_silverbullion':
                                                        $fieldValue = $item['workshop_name'] ?? $fieldValue; break;
                                                    case 'item_attachment_weight_manufactured':
                                                        $fieldValue = $item['stone_weight_grams'] ?? $fieldValue; break;
                                                    case 'item_description': // فیلد توضیحات عمومی آیتم
                                                        $fieldValue = $item['description'] ?? $fieldValue; break;
                                                    case 'item_type_coin': // برای سکه (بانکی/متفرقه)
                                                        $fieldValue = ($item['is_bank_coin'] ?? false) ? 'bank' : 'misc'; break;
                                                    case 'item_has_attachments_manufactured': // برای مصنوعات (دارد/ندارد)
                                                        $fieldValue = ($item['stone_weight_grams'] > 0 || ($item['description'] ?? '') !== '') ? 'yes' : 'no'; break;
                                                    // فیلدهای سود/کارمزد/مالیات/ارزش افزوده (اینها در دیتابیس ذخیره می‌شوند)
                                                    case 'item_profit_percent_melted':
                                                    case 'item_profit_percent_manufactured':
                                                    case 'item_profit_percent_coin':
                                                    case 'item_profit_percent_goldbullion':
                                                    case 'item_profit_percent_silverbullion':
                                                    case 'item_profit_percent_jewelry':
                                                        $fieldValue = $item['profit_percent'] ?? $fieldValue; break;
                                                    case 'item_profit_amount_melted':
                                                    case 'item_profit_amount_manufactured':
                                                    case 'item_profit_amount_coin':
                                                    case 'item_profit_amount_goldbullion':
                                                    case 'item_profit_amount_silverbullion':
                                                    case 'item_profit_amount_jewelry':
                                                        $fieldValue = $item['profit_amount'] ?? $fieldValue; break;
                                                    case 'item_fee_percent_melted':
                                                    case 'item_fee_percent_manufactured':
                                                    case 'item_fee_percent_goldbullion':
                                                    case 'item_fee_percent_silverbullion':
                                                    case 'item_fee_percent_jewelry':
                                                        $fieldValue = $item['fee_percent'] ?? $fieldValue; break;
                                                    case 'item_fee_amount_melted':
                                                    case 'item_fee_amount_manufactured':
                                                    case 'item_fee_amount_goldbullion':
                                                    case 'item_fee_amount_silverbullion':
                                                    case 'item_fee_amount_jewelry':
                                                        $fieldValue = $item['fee_amount'] ?? $fieldValue; break;
                                                    case 'item_general_tax_melted':
                                                    case 'item_general_tax_manufactured':
                                                    case 'item_general_tax_coin':
                                                    case 'item_general_tax_goldbullion':
                                                    case 'item_general_tax_silverbullion':
                                                    case 'item_general_tax_jewelry':
                                                        // اینها در DB ذخیره می‌شوند و باید نمایش داده شوند
                                                        $fieldValue = $item['general_tax'] ?? $fieldValue; break;
                                                    case 'item_vat_melted':
                                                    case 'item_vat_manufactured':
                                                    case 'item_vat_coin':
                                                    case 'item_vat_goldbullion':
                                                    case 'item_vat_silverbullion':
                                                    case 'item_vat_jewelry':
                                                        // اینها در DB ذخیره می‌شوند و باید نمایش داده شوند
                                                        $fieldValue = $item['vat'] ?? $fieldValue; break;
                                                }
                                                // رندر فیلد
                                                echo renderDynamicFieldHtml($field, $index, $fieldValue, $assayOfficesData);
                                            }
                                            echo '</div>'; // بستن ردیف فیلد
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="button" class="btn btn-sm btn-danger remove-item-btn"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach;
                else: // اگر در حالت افزودن هستیم یا هیچ آیتمی وجود ندارد، یک ردیف خالی رندر می‌کنیم ?>
                    <!-- ردیف‌های اقلام توسط JavaScript رندر می‌شوند -->
                <?php endif; ?>
            </div>
            <button type="button" id="add-transaction-item" class="btn btn-sm btn-outline-success mt-3">
                <i class="fas fa-plus me-1"></i> افزودن ردیف جدید
            </button>
        </div>
    </div>
    
    <!-- خلاصه مالی معامله -->
    <div class="card shadow-sm mt-4">
        <div class="card-header">
            <h5 class="card-title">خلاصه مالی معامله</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <p>مجموع ارزش اقلام: <span id="summary-sum_base_items"><?php echo Helper::formatRial($transactionData['total_items_value_rials'] ?? 0); ?></span></p>
                </div>
                <div class="col-md-4">
                    <p>مجموع سود/اجرت/کارمزد: <span id="summary-sum_profit_wage_fee"><?php echo Helper::formatRial($transactionData['total_profit_wage_commission_rials'] ?? 0); ?></span></p>
                </div>
                <div class="col-md-4">
                    <p>مجموع مالیات عمومی: <span id="summary-total_general_tax"><?php echo Helper::formatRial($transactionData['total_general_tax_rials'] ?? 0); ?></span></p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <p>جمع قبل از ارزش افزوده: <span id="summary-sum_before_vat"><?php echo Helper::formatRial($transactionData['total_before_vat_rials'] ?? 0); ?></span></p>
                </div>
                <div class="col-md-4">
                    <p>مجموع ارزش افزوده: <span id="summary-total_vat"><?php echo Helper::formatRial($transactionData['total_vat_rials'] ?? 0); ?></span></p>
                </div>
                <div class="col-md-4">
                    <p class="fw-bold">مبلغ نهایی قابل پرداخت: <span id="summary-final_payable"><?php echo Helper::formatRial($transactionData['final_payable_amount_rials'] ?? 0); ?></span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- یادداشت‌ها و دکمه‌های ارسال -->
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <div class="mb-3">
                <label for="notes" class="form-label">یادداشت‌ها</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo Helper::escapeHtml($transactionData['notes'] ?? ''); ?></textarea>
            </div>
            <hr>
            <div class="d-flex justify-content-end">
                <a href="<?php echo $baseUrl; ?>/app/transactions" class="btn btn-secondary me-2">انصراف</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i> <?php echo $isEditMode ? 'به‌روزرسانی معامله' : 'ثبت معامله'; ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- فیلدهای مخفی برای خلاصه‌سازی که توسط JavaScript به‌روز می‌شوند -->
    <input type="hidden" name="total_items_value_rials" value="<?php echo (float)($transactionData['total_items_value_rials'] ?? 0); ?>">
    <input type="hidden" name="total_profit_wage_commission_rials" value="<?php echo (float)($transactionData['total_profit_wage_commission_rials'] ?? 0); ?>">
    <input type="hidden" name="total_general_tax_rials" value="<?php echo (float)($transactionData['total_general_tax_rials'] ?? 0); ?>">
    <input type="hidden" name="total_before_vat_rials" value="<?php echo (float)($transactionData['total_before_vat_rials'] ?? 0); ?>">
    <input type="hidden" name="total_vat_rials" value="<?php echo (float)($transactionData['total_vat_rials'] ?? 0); ?>">
    <input type="hidden" name="final_payable_amount_rials" value="<?php echo (float)($transactionData['final_payable_amount_rials'] ?? 0); ?>">
</form>

<!-- Template برای ردیف‌های آیتم معامله که توسط JavaScript استفاده می‌شود -->
<template id="item-row-template">
    <div class="transaction-item-row border rounded p-3 mb-3 bg-light">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <label class="form-label">کالا<span class="text-danger">*</span></label>
                <select name="items[{index}][product_id]" class="form-select form-select-sm product-select" required>
                    <option value="">انتخاب کالا...</option>
                </select>
            </div>
            <div class="col-12 col-md-9">
                <div class="dynamic-fields-row row g-2">
                    <!-- فیلدهای داینامیک توسط JS در اینجا قرار می‌گیرند -->
                </div>
            </div>
            <div class="col-12 text-end">
                <button type="button" class="btn btn-sm btn-danger remove-item-btn"><i class="fas fa-trash-alt"></i></button>
            </div>
        </div>
    </div>
</template>

<!-- Bootstrapping تمام داده‌های مورد نیاز برای JavaScript -->
<script>
    window.baseUrl = "<?php echo Helper::escapeHtml($baseUrl); ?>";
    window.productsData = <?php echo json_encode($productsData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.assayOfficesData = <?php echo json_encode($assayOfficesData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.transactionItemsData = <?php echo json_encode($transactionItemsData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.contactsData = <?php echo json_encode($contactsData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.allFieldsData = <?php echo json_encode(['fields' => $fieldsData], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.allFormulasData = <?php echo json_encode(['formulas' => $formulasData], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    // transactionData فقط در حالت ویرایش وجود دارد
    <?php if ($isEditMode): ?>
        window.transactionData = <?php echo json_encode($transactionData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    <?php else: ?>
        window.transactionData = null; // برای حالت افزودن، داده‌ای وجود ندارد
    <?php endif; ?>
    window.defaultSettings = <?php echo json_encode($defaultSettings, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    // برای دسترسی به app.debug در جاوااسکریپت
    window.phpConfig = {
        app: {
            debug: <?php echo json_encode($config['app']['debug'] ?? false); ?>
        }
    };
</script>
<!-- بارگذاری اسکریپت اصلی فرم تراکنش -->
<script src="<?php echo Helper::escapeHtml($baseUrl); ?>/js/transaction-form.js"></script>

<!-- بارگذاری اسکریپت ابزار دیباگ (فقط در محیط توسعه) -->
<?php if ($config['app']['debug'] ?? false): ?>
<script src="<?php echo Helper::escapeHtml($baseUrl); ?>/js/debug-tool.js"></script>
<?php endif; ?>
