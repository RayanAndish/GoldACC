<?php
/**
 * Unified Template: src/views/transactions/form.php
 * A single, unified form for both creating and editing a transaction.
 * It bootstraps all necessary data for the refactored transaction-form.js.
 */

use App\Utils\Helper;
use Morilog\Jalali\Jalalian;

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'فرم معامله';
$isEditMode = $viewData['is_edit_mode'] ?? false;
$formAction = $viewData['form_action'] ?? '';
$csrfToken = $viewData['csrf_token'] ?? '';
$baseUrl = $viewData['baseUrl'] ?? '';
$config = $viewData['config'] ?? [];

// Data for dropdowns and JS
$contactsData = $viewData['contactsData'] ?? [];
$assayOfficesData = $viewData['assayOfficesData'] ?? [];
$productsData = $viewData['productsData'] ?? [];
$fieldsData = $viewData['fieldsData'] ?? [];
$formulasData = $viewData['formulasData'] ?? [];
$defaultSettings = $viewData['default_settings'] ?? [];

// FIX: More robust initialization to prevent "access array offset on null" warning.
$transactionData = (isset($viewData['transactionData']) && is_array($viewData['transactionData'])) ? $viewData['transactionData'] : [];
$transactionItemsData = $viewData['transactionItemsData'] ?? [];

// --- Prepare default/existing values for form fields ---
$defaultTransactionType = $transactionData['transaction_type'] ?? 'sell';
$defaultDeliveryStatus = $transactionData['delivery_status'] ?? (($defaultTransactionType === 'buy') ? 'pending_receipt' : 'pending_delivery');
if (!$isEditMode && empty($transactionData['delivery_status'])) {
    $defaultDeliveryStatus = ($defaultTransactionType === 'buy') ? 'pending_receipt' : 'pending_delivery';
}

$transactionDatePersian = Helper::formatPersianDateTime($transactionData['transaction_date'] ?? 'now');
$deliveryDatePersian = Helper::formatPersianDateTime($transactionData['delivery_date'] ?? null);

?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php if (isset($viewData['loading_error'])): ?>
    <div class="alert alert-danger"><?php echo Helper::escapeHtml($viewData['loading_error']); ?></div>
    <?php return; ?>
<?php endif; ?>

<div id="form-messages" class="mb-3"></div>

<form id="transaction-form" action="<?php echo Helper::escapeHtml($formAction); ?>" method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo Helper::escapeHtml($csrfToken); ?>">
    <?php if ($isEditMode && isset($transactionData['id'])): ?>
        <input type="hidden" name="id" value="<?php echo (int)$transactionData['id']; ?>">
    <?php endif; ?>

    <!-- Main Transaction Fields -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold">اطلاعات اصلی معامله</div>
        <div class="card-body">
            <div class="row g-3">
                <?php if ($isEditMode): ?>
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
                </div>
                <div class="col-md-2">
                    <label for="transaction_date" class="form-label">تاریخ معامله<span class="text-danger">*</span></label>
                    <?php
                    // اگر تاریخ معتبر نبود مقدار پیش‌فرض امروز شمسی
                    $validDate = ($transactionDatePersian && strpos($transactionDatePersian, 'NaN') === false) ? $transactionDatePersian : \Morilog\Jalali\Jalalian::now()->format('Y/m/d H:i');
                    ?>
                    <input type="text" id="transaction_date" name="transaction_date" class="form-control jalali-datepicker" value="<?php echo Helper::escapeHtml($validDate); ?>" required>
                </div>
                <div class="col-md-2">
                    <label for="mazaneh_price" class="form-label">قیمت مظنه</label>
                    <input type="text" id="mazaneh_price" name="mazaneh_price" class="form-control autonumeric" value="<?php echo Helper::formatNumber($transactionData['mazaneh_price'] ?? 0, 0, '٬', ''); ?>">
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
            </div>
        </div>
    </div>

    <!-- Transaction Items Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold">اقلام معامله</div>
        <div class="card-body">
            <div id="transaction-items-container">
                <!-- Item rows will be rendered here by JavaScript -->
            </div>
            <button type="button" id="add-transaction-item" class="btn btn-sm btn-outline-success mt-3">
                <i class="fas fa-plus me-1"></i> افزودن ردیف جدید
            </button>
        </div>
    </div>
    
    <!-- Financial Summary Section -->
    <div class="card shadow-sm mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">خلاصه مالی معامله</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-2">مجموع ارزش اقلام: <strong id="summary-total_items_value_rials" class="number-fa">0</strong></div>
                <div class="col-md-4 mb-2">مجموع سود/اجرت/کارمزد: <strong id="summary-total_profit_wage_commission_rials" class="number-fa">0</strong></div>
                <div class="col-md-4 mb-2">مجموع مالیات عمومی: <strong id="summary-total_general_tax_rials" class="number-fa">0</strong></div>
                <div class="col-md-4 mb-2">جمع قبل از ارزش افزوده: <strong id="summary-total_before_vat_rials" class="number-fa">0</strong></div>
                <div class="col-md-4 mb-2">مجموع ارزش افزوده: <strong id="summary-total_vat_rials" class="number-fa">0</strong></div>
                <div class="col-md-4 mb-2 fs-5">مبلغ نهایی: <strong id="summary-final_payable_amount_rials" class="text-danger number-fa">0</strong></div>
            </div>
        </div>
    </div>

    <!-- Notes and Actions -->
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <div class="mb-3">
                <label for="notes" class="form-label">یادداشت‌ها</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo Helper::escapeHtml($transactionData['notes'] ?? ''); ?></textarea>
            </div>
            <hr>
            <div class="d-flex justify-content-end">
                <a href="<?php echo $baseUrl; ?>/app/transactions" class="btn btn-secondary me-2">انصراف</a>
                <button type="submit" class="btn btn-primary px-4" id="submit-btn">
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <i class="fas fa-save me-1"></i> <?php echo $isEditMode ? 'به‌روزرسانی معامله' : 'ثبت معامله'; ?>
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Template for new transaction item rows -->
<template id="item-row-template">
    <div class="transaction-item-row border rounded p-3 mb-3 bg-light position-relative">
        <input type="hidden" name="items[{index}][id]" value="">
        <div class="position-absolute top-0 start-0 p-2">
             <button type="button" class="btn btn-sm btn-danger remove-item-btn" title="حذف ردیف"><i class="fas fa-trash-alt"></i></button>
        </div>
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <label class="form-label">کالا<span class="text-danger">*</span></label>
                <select name="items[{index}][product_id]" class="form-select form-select-sm product-select" required>
                    <option value="">انتخاب کالا...</option>
                </select>
            </div>
            <div class="col-12">
                <div class="dynamic-fields-container pt-2">
                    <!-- Dynamic fields will be injected here -->
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Bootstrapping all necessary data for JavaScript -->
<script>
    window.APP_CONFIG = {
        baseUrl: "<?php echo Helper::escapeHtml($baseUrl); ?>",
        isEditMode: <?php echo json_encode($isEditMode); ?>,
        debug: <?php echo json_encode($config['app']['debug'] ?? false); ?>
    };
    window.APP_DATA = {
        products: <?php echo json_encode($productsData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>,
        assayOffices: <?php echo json_encode($assayOfficesData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>,
        fields: <?php echo json_encode($fieldsData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>,
        formulas: <?php echo json_encode($formulasData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>,
        transaction: <?php echo json_encode($transactionData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>,
        transactionItems: <?php echo json_encode($transactionItemsData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>,
        defaultSettings: <?php echo json_encode($defaultSettings, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>
    };
</script>

<!-- Load the main transaction form script -->
<script src="<?php echo Helper::escapeHtml($baseUrl); ?>/js/transaction-form.js?v=2.2"></script>
