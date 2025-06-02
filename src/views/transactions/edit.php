<?php
/**
 * View: ویرایش معامله
 * 
 * این فایل نمای ویرایش معاملات موجود را نمایش می‌دهد.
 * برخلاف فرم ثبت، این فرم ساده‌تر است و فقط داده‌های موجود را نمایش می‌دهد.
 */

// دریافت داده‌های نما
$transaction = $viewData['transaction'] ?? null;
$items = $viewData['items'] ?? [];
$assayOffices = $viewData['assay_offices'] ?? [];
$products = $viewData['products'] ?? [];
$contacts = $viewData['contacts'] ?? [];
$fields = $viewData['fields'] ?? [];
$formulas = $viewData['formulas'] ?? [];
$defaultSettings = $viewData['default_settings'] ?? [];

// اطمینان از وجود داده‌های اصلی
if (!$transaction) {
    echo '<div class="alert alert-danger">اطلاعات معامله یافت نشد.</div>';
    return;
}

// تبدیل داده‌ها به JSON برای استفاده در JavaScript
$transactionJson = json_encode($transaction, JSON_UNESCAPED_UNICODE);
$itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);
$assayOfficesJson = json_encode($assayOffices, JSON_UNESCAPED_UNICODE);
$productsJson = json_encode($products, JSON_UNESCAPED_UNICODE);
$contactsJson = json_encode($contacts, JSON_UNESCAPED_UNICODE);
$fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE);
$formulasJson = json_encode($formulas, JSON_UNESCAPED_UNICODE);
$defaultSettingsJson = json_encode($defaultSettings, JSON_UNESCAPED_UNICODE);

// دسته‌بندی فیلدها بر اساس گروه محصول
$fieldsByGroup = [];
if (is_array($fields)) {
    foreach ($fields as $field) {
        if (isset($field['group']) && isset($field['name'])) {
            $group = strtolower($field['group']);
            if (!isset($fieldsByGroup[$group])) {
                $fieldsByGroup[$group] = [];
            }
            $fieldsByGroup[$group][] = $field;
        }
    }
}
?>

<div class="container-fluid mt-3">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">ویرایش معامله</h5>
        </div>
        <div class="card-body">
            <form id="transaction-form" action="<?php echo $baseUrl; ?>/app/transactions/save/<?php echo $transaction['id']; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?? \App\Utils\Helper::generateCsrfToken(); ?>">
                
                <!-- اطلاعات اصلی معامله -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">شماره معامله</label>
                        <input type="text" class="form-control" value="<?php echo $transaction['transaction_number'] ?? $transaction['id'] ?? ''; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاریخ معامله</label>
                        <input type="text" name="transaction_date" class="form-control" data-jdp value="<?php echo $transaction['transaction_date'] ?? ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">مظنه (ریال)</label>
                        <input type="text" id="mazaneh_price" name="mazaneh_price" class="form-control autonumeric" value="<?php echo $transaction['mazaneh_price'] ?? 0; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">نوع معامله</label>
                        <select id="transaction_type" name="transaction_type" class="form-select">
                            <option value="buy" <?php echo (isset($transaction['transaction_type']) && $transaction['transaction_type'] == 'buy') ? 'selected' : ''; ?>>خرید</option>
                            <option value="sell" <?php echo (isset($transaction['transaction_type']) && $transaction['transaction_type'] == 'sell') ? 'selected' : ''; ?>>فروش</option>
                        </select>
                    </div>
                </div>
                
                <!-- وضعیت تحویل -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">وضعیت تحویل</label>
                        <select id="delivery_status" name="delivery_status" class="form-select">
                            <option value="pending_receipt" <?php echo (isset($transaction['delivery_status']) && $transaction['delivery_status'] == 'pending_receipt') ? 'selected' : ''; ?>>منتظر دریافت</option>
                            <option value="pending_delivery" <?php echo (isset($transaction['delivery_status']) && $transaction['delivery_status'] == 'pending_delivery') ? 'selected' : ''; ?>>منتظر تحویل</option>
                            <option value="completed" <?php echo (isset($transaction['delivery_status']) && $transaction['delivery_status'] == 'completed') ? 'selected' : ''; ?>>تکمیل شده</option>
                            <option value="cancelled" <?php echo (isset($transaction['delivery_status']) && $transaction['delivery_status'] == 'cancelled') ? 'selected' : ''; ?>>لغو شده</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاریخ تحویل</label>
                        <input type="text" name="delivery_date" class="form-control" data-jdp value="<?php echo $transaction['delivery_date'] ?? ''; ?>" <?php echo (!isset($transaction['delivery_status']) || $transaction['delivery_status'] == 'completed') ? '' : 'disabled'; ?>>
                    </div>
                </div>
                
                <!-- اطلاعات طرف حساب -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">طرف حساب</label>
                        <input type="text" name="party_name" class="form-control" value="<?php echo $transaction['party_name'] ?? ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">شماره تماس</label>
                        <input type="text" name="party_phone" class="form-control" value="<?php echo $transaction['party_phone'] ?? ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">کد ملی</label>
                        <input type="text" name="party_national_code" class="form-control" value="<?php echo $transaction['party_national_code'] ?? ''; ?>">
                    </div>
                </div>
                
                <!-- اقلام معامله -->
                <h6 class="mb-3 mt-4">اقلام معامله</h6>
                <div id="transaction-items-container">
                    <?php foreach ($items as $index => $item): ?>
                        <div class="transaction-item-row card mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <!-- محصول -->
                                    <div class="col-md-4">
                                        <label class="form-label">محصول</label>
                                        <select name="items[<?php echo $index; ?>][product_id]" class="form-select product-select" readonly disabled>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?php echo is_object($product) ? $product->id : $product['id']; ?>" <?php echo ((is_object($product) ? $product->id : $product['id']) == $item['product_id']) ? 'selected' : ''; ?>>
                                                    <?php echo is_object($product) ? $product->name : $product['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="items[<?php echo $index; ?>][product_id]" value="<?php echo $item['product_id']; ?>">
                                        <input type="hidden" name="items[<?php echo $index; ?>][id]" value="<?php echo $item['id']; ?>">
                                    </div>
                                    
                                    <?php
                                    // تعیین گروه محصول - باید قبل از نمایش فیلدها انجام شود
                                    $productGroup = '';
                                    foreach ($products as $product) {
                                        $productId = is_object($product) ? $product->id : $product['id'];
                                        if ($productId == $item['product_id']) {
                                            if (is_object($product)) {
                                                $productGroup = $product->category ? $product->category->base_category ?? '' : '';
                                            } elseif (is_array($product)) {
                                                $productGroup = isset($product['category']) && isset($product['category']['base_category']) ? $product['category']['base_category'] : '';
                                            }
                                            break;
                                        }
                                    }
                                    
                                    $productGroup = strtolower($productGroup);
                                    
                                    // نمایش فیلد تعداد فقط برای کالاهایی که نیاز دارند (نه برای آبشده)
                                    if ($productGroup != 'melted'): 
                                    ?>
                                    <!-- فیلدهای اصلی -->
                                    <div class="col-md-2">
                                        <label class="form-label">تعداد</label>
                                        <input type="text" name="items[<?php echo $index; ?>][quantity]" class="form-control" value="<?php echo $item['quantity'] ?? 1; ?>">
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- فیلدهای داینامیک بر اساس گروه محصول -->
                                <div class="dynamic-fields-row row mt-3">
                                    <?php
                                    // نمایش فیلدهای مختلف بر اساس گروه محصول از فایل fields.json
                                    $groupFields = $fieldsByGroup[$productGroup] ?? [];
                                    
                                    if (!empty($groupFields)):
                                        foreach ($groupFields as $field):
                                            // اطمینان از وجود نام و برچسب فیلد
                                            if (!isset($field['name']) || !isset($field['label'])) continue;
                                            
                                            // ساخت نام فیلد با پیشوند items و شاخص
                                            $fieldName = "items[{$index}][{$field['name']}]";
                                            
                                            // تعیین مقدار فیلد
                                            $fieldValue = '';
                                            if (isset($item[$field['name']])) {
                                                $fieldValue = $item[$field['name']];
                                            } elseif (isset($field['name']) && str_starts_with($field['name'], 'item_') && isset($item[substr($field['name'], 5)])) {
                                                // تلاش برای یافتن فیلد بدون پیشوند item_
                                                $fieldValue = $item[substr($field['name'], 5)];
                                            }
                                            
                                            // تعیین کلاس‌های فیلد
                                            $fieldClasses = 'form-control';
                                            if (isset($field['is_numeric']) && $field['is_numeric']) {
                                                $fieldClasses .= ' autonumeric';
                                            }
                                            if (isset($field['readonly']) && $field['readonly']) {
                                                $fieldClasses .= ' readonly';
                                                $readonly = 'readonly';
                                            } else {
                                                $readonly = '';
                                            }
                                            
                                            // تعیین اندازه ستون
                                            $colSize = isset($field['column_size']) ? $field['column_size'] : 3;
                                    ?>
                                    <div class="col-md-<?php echo $colSize; ?>">
                                        <label class="form-label"><?php echo $field['label']; ?></label>
                                        <?php if (isset($field['type']) && $field['type'] == 'select'): ?>
                                            <select name="<?php echo $fieldName; ?>" class="form-select <?php echo $fieldClasses; ?>" <?php echo $readonly; ?>>
                                                <option value="">انتخاب کنید...</option>
                                                <?php 
                                                    if (isset($field['options']) && is_array($field['options'])):
                                                        foreach ($field['options'] as $option):
                                                            $selected = ($option['value'] == $fieldValue) ? 'selected' : '';
                                                ?>
                                                            <option value="<?php echo $option['value']; ?>" <?php echo $selected; ?>><?php echo $option['label']; ?></option>
                                                <?php 
                                                        endforeach;
                                                    endif;
                                                ?>
                                            </select>
                                        <?php elseif (isset($field['type']) && $field['type'] == 'assay_office_select'): ?>
                                            <select name="<?php echo $fieldName; ?>" class="form-select assay-office-select">
                                                <option value="0">انتخاب مرکز ری‌گیری...</option>
                                                <?php foreach ($assayOffices as $office): ?>
                                                    <option value="<?php echo $office['id']; ?>" <?php echo ($office['id'] == $fieldValue) ? 'selected' : ''; ?>>
                                                        <?php echo $office['name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="text" name="<?php echo $fieldName; ?>" class="<?php echo $fieldClasses; ?>" value="<?php echo $fieldValue; ?>" <?php echo $readonly; ?>>
                                        <?php endif; ?>
                                    </div>
                                    <?php 
                                        endforeach;
                                    else:
                                        // اگر فیلدی از فایل fields.json یافت نشد، فیلدهای پیش‌فرض را نمایش بده
                                        switch ($productGroup) {
                                            case 'melted':
                                    ?>
                                                <div class="col-md-3">
                                                    <label class="form-label">وزن (گرم)</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_weight_scale_melted]" class="form-control autonumeric" value="<?php echo $item['weight_grams'] ?? $item['item_weight_scale_melted'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">عیار</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_carat_melted]" class="form-control" value="<?php echo $item['carat'] ?? $item['item_carat_melted'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">مرکز ری‌گیری</label>
                                                    <select name="items[<?php echo $index; ?>][item_assay_office_melted]" class="form-select assay-office-select">
                                                        <option value="0">انتخاب مرکز ری‌گیری...</option>
                                                        <?php foreach ($assayOffices as $office): ?>
                                                            <option value="<?php echo $office['id']; ?>" <?php echo (isset($item['item_assay_office_melted']) && $office['id'] == $item['item_assay_office_melted']) || (isset($item['assay_office_id']) && $office['id'] == $item['assay_office_id']) ? 'selected' : ''; ?>>
                                                                <?php echo $office['name']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">قیمت واحد (ریال)</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_unit_price_melted]" class="form-control autonumeric" value="<?php echo $item['unit_price_rials'] ?? $item['item_unit_price_melted'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">قیمت کل</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_total_price_melted]" class="form-control autonumeric" value="<?php echo $item['total_value_rials'] ?? $item['item_total_price_melted'] ?? ''; ?>" readonly>
                                                </div>
                                                
                                                <div class="w-100 mt-2"></div>
                                                
                                                <div class="col-md-2">
                                                    <label class="form-label">شماره انگ</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_tag_number_melted]" class="form-control" value="<?php echo $item['tag_number'] ?? $item['item_tag_number_melted'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">نوع انگ</label>
                                                    <select name="items[<?php echo $index; ?>][item_tag_type_melted]" class="form-select">
                                                        <option value="">انتخاب کنید...</option>
                                                        <option value="conditional" <?php echo (isset($item['item_tag_type_melted']) && $item['item_tag_type_melted'] == 'conditional') || (isset($item['tag_type']) && $item['tag_type'] == 'conditional') ? 'selected' : ''; ?>>شرطی</option>
                                                        <option value="official_tag" <?php echo (isset($item['item_tag_type_melted']) && $item['item_tag_type_melted'] == 'official_tag') || (isset($item['tag_type']) && $item['tag_type'] == 'official_tag') ? 'selected' : ''; ?>>انگ رسمی</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">درصد سود</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_profit_percent_melted]" class="form-control" value="<?php echo $item['profit_percent'] ?? $item['item_profit_percent_melted'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">مبلغ سود</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_profit_amount_melted]" class="form-control autonumeric" value="<?php echo $item['profit_amount'] ?? $item['item_profit_amount_melted'] ?? ''; ?>" readonly>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">درصد کارمزد</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_fee_percent_melted]" class="form-control" value="<?php echo $item['fee_percent'] ?? $item['item_fee_percent_melted'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">مبلغ کارمزد</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_fee_amount_melted]" class="form-control autonumeric" value="<?php echo $item['fee_amount'] ?? $item['item_fee_amount_melted'] ?? ''; ?>" readonly>
                                                </div>
                                    <?php
                                                break;
                                                
                                            case 'manufactured':
                                    ?>
                                                <div class="col-md-3">
                                                    <label class="form-label">وزن (گرم)</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_weight_scale_manufactured]" class="form-control autonumeric" value="<?php echo $item['weight_grams'] ?? $item['item_weight_scale_manufactured'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">عیار</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_carat_manufactured]" class="form-control" value="<?php echo $item['carat'] ?? $item['item_carat_manufactured'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">قیمت واحد (ریال)</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_unit_price_manufactured]" class="form-control autonumeric" value="<?php echo $item['unit_price_rials'] ?? $item['item_unit_price_manufactured'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">قیمت کل</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_total_price_manufactured]" class="form-control autonumeric" value="<?php echo $item['total_value_rials'] ?? $item['item_total_price_manufactured'] ?? ''; ?>" readonly>
                                                </div>
                                                
                                                <div class="w-100 mt-2"></div>
                                                
                                                <div class="col-md-2">
                                                    <label class="form-label">درصد اجرت</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_manufacturing_fee_percent_manufactured]" class="form-control" value="<?php echo $item['manufacturing_fee_percent'] ?? $item['item_manufacturing_fee_percent_manufactured'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">مبلغ اجرت</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_manufacturing_fee_amount_manufactured]" class="form-control autonumeric" value="<?php echo $item['manufacturing_fee_amount'] ?? $item['item_manufacturing_fee_amount_manufactured'] ?? ''; ?>" readonly>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">درصد سود</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_profit_percent_manufactured]" class="form-control" value="<?php echo $item['profit_percent'] ?? $item['item_profit_percent_manufactured'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">مبلغ سود</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_profit_amount_manufactured]" class="form-control autonumeric" value="<?php echo $item['profit_amount'] ?? $item['item_profit_amount_manufactured'] ?? ''; ?>" readonly>
                                                </div>
                                    <?php
                                                break;
                                                
                                            case 'coin':
                                    ?>
                                                <div class="col-md-3">
                                                    <label class="form-label">تعداد</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_quantity_coin]" class="form-control" value="<?php echo $item['quantity'] ?? $item['item_quantity_coin'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">سال ضرب</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_year_coin]" class="form-control" value="<?php echo $item['coin_year'] ?? $item['item_year_coin'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">قیمت واحد (ریال)</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_unit_price_coin]" class="form-control autonumeric" value="<?php echo $item['unit_price_rials'] ?? $item['item_unit_price_coin'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">قیمت کل</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_total_price_coin]" class="form-control autonumeric" value="<?php echo $item['total_value_rials'] ?? $item['item_total_price_coin'] ?? ''; ?>" readonly>
                                                </div>
                                    <?php
                                                break;
                                                
                                            default:
                                    ?>
                                                <div class="col-md-3">
                                                    <label class="form-label">وزن (گرم)</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_weight_scale]" class="form-control autonumeric" value="<?php echo $item['weight_grams'] ?? $item['item_weight_scale'] ?? ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">قیمت واحد (ریال)</label>
                                                    <input type="text" name="items[<?php echo $index; ?>][item_unit_price]" class="form-control autonumeric" value="<?php echo $item['unit_price_rials'] ?? $item['item_unit_price'] ?? ''; ?>">
                                                </div>
                                    <?php
                                                break;
                                        }
                                    endif;
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- خلاصه معامله -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">خلاصه مالی معامله</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p>مجموع ارزش اقلام: <span id="summary-sum_base_items"><?php echo number_format($transaction['total_items_value_rials'] ?? 0); ?></span> ریال</p>
                            </div>
                            <div class="col-md-4">
                                <p>مجموع سود/اجرت/کارمزد: <span id="summary-sum_profit_wage_fee"><?php echo number_format($transaction['total_profit_wage_commission_rials'] ?? 0); ?></span> ریال</p>
                            </div>
                            <div class="col-md-4">
                                <p>مجموع مالیات عمومی: <span id="summary-total_general_tax"><?php echo number_format($transaction['total_general_tax_rials'] ?? 0); ?></span> ریال</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <p>جمع قبل از ارزش افزوده: <span id="summary-sum_before_vat"><?php echo number_format($transaction['total_before_vat_rials'] ?? 0); ?></span> ریال</p>
                            </div>
                            <div class="col-md-4">
                                <p>مجموع ارزش افزوده: <span id="summary-total_vat"><?php echo number_format($transaction['total_vat_rials'] ?? 0); ?></span> ریال</p>
                            </div>
                            <div class="col-md-4">
                                <p class="fw-bold">مبلغ نهایی قابل پرداخت: <span id="summary-final_payable"><?php echo number_format($transaction['final_payable_amount_rials'] ?? 0); ?></span> ریال</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- فیلدهای مخفی -->
                <input type="hidden" name="id" value="<?php echo $transaction['id']; ?>">
                <input type="hidden" name="total_items_value_rials" value="<?php echo $transaction['total_items_value_rials'] ?? 0; ?>">
                <input type="hidden" name="total_profit_wage_commission_rials" value="<?php echo $transaction['total_profit_wage_commission_rials'] ?? 0; ?>">
                <input type="hidden" name="total_general_tax_rials" value="<?php echo $transaction['total_general_tax_rials'] ?? 0; ?>">
                <input type="hidden" name="total_before_vat_rials" value="<?php echo $transaction['total_before_vat_rials'] ?? 0; ?>">
                <input type="hidden" name="total_vat_rials" value="<?php echo $transaction['total_vat_rials'] ?? 0; ?>">
                <input type="hidden" name="final_payable_amount_rials" value="<?php echo $transaction['final_payable_amount_rials'] ?? 0; ?>">
                
                <!-- دکمه‌های فرم -->
                <div class="row mt-4">
                    <div class="col-md-12 text-end">
                        <a href="<?php echo $baseUrl; ?>/app/transactions" class="btn btn-secondary">انصراف</a>
                        <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- اسکریپت‌های مورد نیاز -->
<script>
    // انتقال داده‌ها به اسکریپت
    window.transactionData = <?php echo $transactionJson; ?>;
    window.transactionItemsData = <?php echo $itemsJson; ?>;
    window.assayOfficesData = <?php echo $assayOfficesJson; ?>;
    window.productsData = <?php echo $productsJson; ?>;
    window.contactsData = <?php echo $contactsJson; ?>;
    window.fieldsData = <?php echo $fieldsJson; ?>;
    window.formulasData = <?php echo $formulasJson; ?>;
    window.defaultSettings = <?php echo $defaultSettingsJson; ?>;
    window.baseUrl = '<?php echo $baseUrl; ?>';
    
    // ابزار دیباگ پیشرفته - فقط در محیط توسعه
    const debugBtn = document.createElement('button');
    debugBtn.innerText = 'نمایش داده‌های دیباگ';
    debugBtn.style.position = 'fixed';
    debugBtn.style.bottom = '10px';
    debugBtn.style.right = '10px';
    debugBtn.style.zIndex = '9999';
    debugBtn.style.padding = '5px 10px';
    debugBtn.style.background = '#007bff';
    debugBtn.style.color = 'white';
    debugBtn.style.border = 'none';
    debugBtn.style.borderRadius = '5px';
    debugBtn.style.cursor = 'pointer';
    
    debugBtn.onclick = function() {
        console.log('=== DEBUG DATA ===');
        console.log('Transaction:', window.transactionData);
        console.log('Items:', window.transactionItemsData);
        console.log('Products:', window.productsData);
        console.log('Fields:', window.fieldsData);
        
        // ایجاد یک دیالوگ ساده برای نمایش اطلاعات
        const debugOutput = document.createElement('div');
        debugOutput.style.position = 'fixed';
        debugOutput.style.top = '10%';
        debugOutput.style.left = '10%';
        debugOutput.style.width = '80%';
        debugOutput.style.height = '80%';
        debugOutput.style.background = 'white';
        debugOutput.style.border = '1px solid #ccc';
        debugOutput.style.padding = '20px';
        debugOutput.style.zIndex = '10000';
        debugOutput.style.overflow = 'auto';
        debugOutput.style.direction = 'ltr';
        
        // محتوای دیباگ
        const deliveryStatus = window.transactionData.delivery_status || 'نامشخص';
        const transactionType = window.transactionData.transaction_type || 'نامشخص';
        
        debugOutput.innerHTML = `
            <h3>اطلاعات دیباگ</h3>
            <p><strong>نوع معامله:</strong> ${transactionType}</p>
            <p><strong>وضعیت تحویل:</strong> ${deliveryStatus}</p>
            <p><strong>تعداد آیتم‌ها:</strong> ${(window.transactionItemsData || []).length}</p>
            <p><strong>اطلاعات طرف حساب:</strong></p>
            <ul>
                <li>نام: ${window.transactionData.party_name || window.transactionData.counterparty_name || 'نامشخص'}</li>
                <li>تلفن: ${window.transactionData.party_phone || 'نامشخص'}</li>
                <li>کد ملی: ${window.transactionData.party_national_code || 'نامشخص'}</li>
            </ul>
            <h4>داده‌های محصولات:</h4>
            <div style="max-height: 150px; overflow: auto;">
                ${(window.transactionItemsData || []).map((item, index) => `
                    <div style="border: 1px solid #eee; padding: 10px; margin-bottom: 10px;">
                        <p><strong>آیتم ${index + 1}:</strong></p>
                        <ul>
                            <li>محصول: ${item.product_name || item.name || 'نامشخص'}</li>
                            <li>دسته: ${item.product_category_base || 'نامشخص'}</li>
                            <li>کد دسته: ${item.product_category_code || 'نامشخص'}</li>
                            <li>واحد اندازه‌گیری: ${item.product_unit_of_measure || 'نامشخص'}</li>
                            <li>وزن: ${item.weight_grams || '0'} گرم</li>
                            <li>تعداد: ${item.quantity || '1'}</li>
                            <li>عیار: ${item.carat || item.gold_carat || '18'}</li>
                            <li>قیمت واحد: ${item.unit_price_rials || '0'} ریال</li>
                        </ul>
                    </div>
                `).join('')}
            </div>
            <h4>داده‌های کامل معامله:</h4>
            <pre style="direction: ltr; text-align: left; background: #f5f5f5; padding: 10px; max-height: 200px; overflow: auto;">${JSON.stringify(window.transactionData, null, 2)}</pre>
            <h4>داده‌های کامل آیتم‌ها:</h4>
            <pre style="direction: ltr; text-align: left; background: #f5f5f5; padding: 10px; max-height: 200px; overflow: auto;">${JSON.stringify(window.transactionItemsData, null, 2)}</pre>
            
            <div style="margin-top: 20px;">
                <button id="close-debug" style="background: #dc3545; color: white; border: none; padding: 5px 10px; margin-top: 10px; cursor: pointer;">بستن</button>
                <button id="db-fix-delivery-status" style="background: #28a745; color: white; border: none; padding: 5px 10px; margin-top: 10px; margin-right: 10px; cursor: pointer;">تصحیح وضعیت تحویل</button>
            </div>
        `;
        
        document.body.appendChild(debugOutput);
        
        // دکمه بستن
        document.getElementById('close-debug').onclick = function() {
            document.body.removeChild(debugOutput);
        };
        
        // دکمه تصحیح وضعیت تحویل
        document.getElementById('db-fix-delivery-status').onclick = function() {
            const select = document.getElementById('delivery_status');
            if (!select) {
                alert('فیلد وضعیت تحویل یافت نشد!');
                return;
            }
            
            // تنظیم وضعیت تحویل بر اساس نوع معامله
            const transactionType = window.transactionData.transaction_type;
            if (transactionType === 'buy') {
                select.value = 'pending_receipt';
                alert('وضعیت تحویل به "منتظر دریافت" تغییر یافت.');
            } else if (transactionType === 'sell') {
                select.value = 'pending_delivery';
                alert('وضعیت تحویل به "منتظر تحویل" تغییر یافت.');
            }
            
            // فراخوانی رویداد تغییر برای هماهنگ‌سازی سایر بخش‌ها
            select.dispatchEvent(new Event('change'));
        };
    };
    
    // فقط در محیط توسعه اضافه شود
    <?php if ($config['app']['debug'] ?? false): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.body.appendChild(debugBtn);
    });
    <?php endif; ?>
</script>
<script src="<?php echo $baseUrl; ?>/js/transaction-edit-form.js"></script> 