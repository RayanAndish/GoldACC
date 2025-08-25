<?php
/**
 * View: ویرایش معامله
 * * این فایل نمای ویرایش معاملات موجود را نمایش می‌دهد.
 */

use App\Utils\Helper;
use Morilog\Jalali\Jalalian; // اضافه شدن برای فرمت دهی تاریخ شمسی

// دریافت داده‌های نما
$transaction = $viewData['transaction'] ?? null;
$items = $viewData['items'] ?? [];
$assayOffices = $viewData['assay_offices'] ?? [];
$products = $viewData['products'] ?? []; // Product objects
$contacts = $viewData['contacts'] ?? [];
$fields = $viewData['fields'] ?? []; // داده‌های فیلدهای داینامیک
$formulas = $viewData['formulas'] ?? []; // داده‌های فرمول‌های داینامیک
$defaultSettings = $viewData['default_settings'] ?? [];
$baseUrl = $viewData['baseUrl'] ?? '';
$csrfToken = $viewData['csrf_token'] ?? ''; // CSRF Token از کنترلر می‌آید
$config = $viewData['config'] ?? []; // برای دسترسی به app.debug

// اطمینان از وجود داده‌های اصلی
if (!$transaction) {
    echo '<div class="alert alert-danger">اطلاعات معامله یافت نشد.</div>';
    return;
}

// فرمت دهی تاریخ‌ها به شمسی برای نمایش در فیلدهای ورودی
$transactionDatePersian = '';
if (!empty($transaction['transaction_date'])) {
    try {
        $dt = new DateTime($transaction['transaction_date']);
        $transactionDatePersian = Jalalian::fromDateTime($dt)->format('Y/m/d H:i:s');
    } catch (Exception $e) {
        $transactionDatePersian = '-';
    }
}

$deliveryDatePersian = '';
if (!empty($transaction['delivery_date'])) {
    try {
        $dt = new DateTime($transaction['delivery_date']);
        $deliveryDatePersian = Jalalian::fromDateTime($dt)->format('Y/m/d H:i:s');
    } catch (Exception $e) {
        $deliveryDatePersian = '-';
    }
}

?>

<div class="container-fluid mt-3">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">ویرایش معامله</h5>
        </div>
        <div class="card-body">
            <form id="transaction-form" action="<?php echo Helper::escapeHtml($baseUrl); ?>/app/transactions/save/<?php echo (int)$transaction['id']; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo Helper::escapeHtml($csrfToken); ?>">
                <input type="hidden" name="transaction_id" value="<?php echo (int)$transaction['id']; ?>">
                
                <!-- اطلاعات اصلی معامله -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">شماره معامله</label>
                        <input type="text" class="form-control" value="<?php echo (int)$transaction['id']; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاریخ معامله</label>
                        <input type="text" name="transaction_date" class="form-control jalali-datepicker" value="<?php echo Helper::escapeHtml($transactionDatePersian); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">مظنه (ریال)</label>
                        <input type="text" id="mazaneh_price" name="mazaneh_price" class="form-control autonumeric" value="<?php echo Helper::escapeHtml($transaction['mazaneh_price'] ?? 0); ?>">
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
                        <input type="text" name="delivery_date" class="form-control jalali-datepicker" value="<?php echo Helper::escapeHtml($deliveryDatePersian); ?>" <?php echo (!isset($transaction['delivery_status']) || $transaction['delivery_status'] == 'completed') ? '' : 'disabled'; ?>>
                    </div>
                </div>
                
                <!-- اطلاعات طرف حساب -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">طرف حساب</label>
                        <select name="counterparty_contact_id" class="form-select" required>
                            <option value="">انتخاب کنید...</option>
                            <?php foreach ($contacts as $contact): ?>
                                <option value="<?php echo (int)$contact['id']; ?>" <?php echo (($transaction['counterparty_contact_id'] ?? '') == $contact['id']) ? 'selected' : ''; ?>>
                                    <?php echo Helper::escapeHtml($contact['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- فیلدهای party_name, party_phone, party_national_code باید از طریق JS و بر اساس انتخاب counterparty_contact_id پر شوند -->
                        <!-- یا اگر قرار است اینها فیلدهای مستقیم باشند، باید در دیتابیس ذخیره شوند -->
                        <input type="hidden" name="party_name" value="<?php echo Helper::escapeHtml($transaction['party_name'] ?? ''); ?>">
                        <input type="hidden" name="party_phone" value="<?php echo Helper::escapeHtml($transaction['party_phone'] ?? ''); ?>">
                        <input type="hidden" name="party_national_code" value="<?php echo Helper::escapeHtml($transaction['party_national_code'] ?? ''); ?>">
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
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">محصول</label>
                                        <select name="items[<?php echo $index; ?>][product_id]" class="form-select product-select" readonly disabled>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?php echo is_object($product) ? $product->id : $product['id']; ?>" <?php echo ((is_object($product) ? $product->id : $product['id']) == $item['product_id']) ? 'selected' : ''; ?>>
                                                    <?php echo Helper::escapeHtml(is_object($product) ? $product->name : $product['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <!-- فیلدهای مخفی برای product_id و id آیتم -->
                                        <input type="hidden" name="items[<?php echo $index; ?>][product_id]" value="<?php echo (int)$item['product_id']; ?>">
                                        <input type="hidden" name="items[<?php echo $index; ?>][id]" value="<?php echo (int)$item['id']; ?>">
                                    </div>
                                    <!-- فیلدهای داینامیک بر اساس گروه محصول توسط JS رندر می‌شوند -->
                                    <div class="col-12 col-md-8">
                                        <div class="dynamic-fields-row row g-2">
                                            <!-- فیلدهای داینامیک توسط JavaScript در اینجا قرار می‌گیرند -->
                                        </div>
                                    </div>
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
                                <p>مجموع ارزش اقلام: <span id="summary-sum_base_items"><?php echo Helper::formatRial($transaction['total_items_value_rials'] ?? 0); ?></span></p>
                            </div>
                            <div class="col-md-4">
                                <p>مجموع سود/اجرت/کارمزد: <span id="summary-sum_profit_wage_fee"><?php echo Helper::formatRial($transaction['total_profit_wage_commission_rials'] ?? 0); ?></span></p>
                            </div>
                            <div class="col-md-4">
                                <p>مجموع مالیات عمومی: <span id="summary-total_general_tax"><?php echo Helper::formatRial($transaction['total_general_tax_rials'] ?? 0); ?></span></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <p>جمع قبل از ارزش افزوده: <span id="summary-sum_before_vat"><?php echo Helper::formatRial($transaction['total_before_vat_rials'] ?? 0); ?></span></p>
                            </div>
                            <div class="col-md-4">
                                <p>مجموع ارزش افزوده: <span id="summary-total_vat"><?php echo Helper::formatRial($transaction['total_vat_rials'] ?? 0); ?></span></p>
                            </div>
                            <div class="col-md-4">
                                <p class="fw-bold">مبلغ نهایی قابل پرداخت: <span id="summary-final_payable"><?php echo Helper::formatRial($transaction['final_payable_amount_rials'] ?? 0); ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- فیلدهای مخفی برای خلاصه‌سازی که توسط JavaScript به‌روز می‌شوند -->
                <input type="hidden" name="total_items_value_rials" value="<?php echo (float)($transaction['total_items_value_rials'] ?? 0); ?>">
                <input type="hidden" name="total_profit_wage_commission_rials" value="<?php echo (float)($transaction['total_profit_wage_commission_rials'] ?? 0); ?>">
                <input type="hidden" name="total_general_tax_rials" value="<?php echo (float)($transaction['total_general_tax_rials'] ?? 0); ?>">
                <input type="hidden" name="total_before_vat_rials" value="<?php echo (float)($transaction['total_before_vat_rials'] ?? 0); ?>">
                <input type="hidden" name="total_vat_rials" value="<?php echo (float)($transaction['total_vat_rials'] ?? 0); ?>">
                <input type="hidden" name="final_payable_amount_rials" value="<?php echo (float)($transaction['final_payable_amount_rials'] ?? 0); ?>">
                
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

<!-- Bootstrapping تمام داده‌های مورد نیاز برای JavaScript -->
<script>
    window.baseUrl = "<?php echo Helper::escapeHtml($baseUrl); ?>";
    window.productsData = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.assayOfficesData = <?php echo json_encode($assayOffices, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.transactionItemsData = <?php echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.contactsData = <?php echo json_encode($contacts, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.fieldsData = <?php echo json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.formulasData = <?php echo json_encode($formulas, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.defaultSettings = <?php echo json_encode($defaultSettings, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    window.transactionData = <?php echo json_encode($transaction, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;

    // ابزار دیباگ پیشرفته - فقط در محیط توسعه
    // این کد باید به صورت یک فایل JS جداگانه (مثلاً debug-tool.js) اضافه شود و فقط در محیط توسعه بارگذاری شود.
    // فعلاً برای حفظ عملکرد فعلی و جلوگیری از خطای "alert" در محیط Canvas، آن را به یک پیام console.log تغییر می‌دهیم.
    <?php if ($config['app']['debug'] ?? false): ?>
    document.addEventListener('DOMContentLoaded', function() {
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
        document.body.appendChild(debugBtn);

        debugBtn.onclick = function() {
            console.log('=== DEBUG DATA ===');
            console.log('Transaction:', window.transactionData);
            console.log('Items:', window.transactionItemsData);
            console.log('Products:', window.productsData);
            console.log('Fields:', window.fieldsData);
            console.log('Formulas:', window.formulasData);
            console.log('Default Settings:', window.defaultSettings);
            console.log('Contacts:', window.contactsData);
            console.log('Assay Offices:', window.assayOfficesData);

            // نمایش داده‌ها در یک دیالوگ ساده (به جای alert)
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
            debugOutput.innerHTML = `
                <h3>اطلاعات دیباگ</h3>
                <p><strong>نوع معامله:</strong> ${window.transactionData.transaction_type || 'نامشخص'}</p>
                <p><strong>وضعیت تحویل:</strong> ${window.transactionData.delivery_status || 'نامشخص'}</p>
                <p><strong>تعداد آیتم‌ها:</strong> ${(window.transactionItemsData || []).length}</p>
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

            document.getElementById('close-debug').onclick = function() {
                document.body.removeChild(debugOutput);
            };

            document.getElementById('db-fix-delivery-status').onclick = function() {
                const select = document.getElementById('delivery_status');
                if (!select) {
                    console.error('فیلد وضعیت تحویل یافت نشد!');
                    return;
                }
                const transactionType = window.transactionData.transaction_type;
                if (transactionType === 'buy') {
                    select.value = 'pending_receipt';
                    console.log('وضعیت تحویل به "منتظر دریافت" تغییر یافت.');
                } else if (transactionType === 'sell') {
                    select.value = 'pending_delivery';
                    console.log('وضعیت تحویل به "منتظر تحویل" تغییر یافت.');
                }
                select.dispatchEvent(new Event('change'));
            };
        };
    });
    <?php endif; ?>
</script>
<!-- بارگذاری اسکریپت اصلی فرم ویرایش تراکنش -->
<script src="<?php echo Helper::escapeHtml($baseUrl); ?>/js/transaction-edit-form.js"></script>