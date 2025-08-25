<?php
/**
 * REFACTORED Template: src/views/transactions/form.php (Final Version 1.1)
 * This version fixes access to view variables, solving the 'Undefined property' error.
 */

use App\Utils\Helper;

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'فرم معامله';
$isEditMode = $viewData['is_edit_mode'] ?? false;
$transactionData = $viewData['transactionData'] ?? [];
$transactionItemsData = $viewData['transactionItemsData'] ?? [];
$productsDataForJs = $viewData['productsData'] ?? []; 
$fieldsData = $viewData['fieldsData'] ?? [];
$formulasData = $viewData['formulasData'] ?? [];
$contactsData = $viewData['contactsData'] ?? [];
$assayOfficesData = $viewData['assayOfficesData'] ?? [];

// **FIX: Access baseUrl directly from $viewData, not $this->config**
$baseUrl = $viewData['baseUrl'] ?? '';

// --- Prepare default/existing values for main form fields ---
$defaultTransactionType = $transactionData['transaction_type'] ?? 'sell';
$defaultContactId = $transactionData['counterparty_contact_id'] ?? null;
$defaultMazaneh = $transactionData['mazaneh_price'] ?? 0;
$defaultDeliveryStatus = $transactionData['delivery_status'] ?? (($defaultTransactionType === 'buy') ? 'pending_receipt' : 'pending_delivery');
$transactionDatePersian = $transactionData['transaction_date_persian'] ?? Helper::formatPersianDateTime('now', 'Y/m/d');

// --- Form Action URL ---
$formActionUrl = $baseUrl . '/app/transactions/save';
if ($isEditMode && isset($transactionData['id'])) {
    $formActionUrl .= '/' . (int)$transactionData['id'];
}
?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php if (isset($viewData['loading_error'])): ?>
    <div class="alert alert-danger"><?php echo Helper::escapeHtml($viewData['loading_error']); ?></div>
    <?php return; ?>
<?php endif; ?>

<div id="form-messages" class="mb-3"></div>

<form id="transaction-form" action="<?= Helper::escapeHtml($formActionUrl) ?>" method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo Helper::generateCsrfToken(); ?>">
    <?php if ($isEditMode && isset($transactionData['id'])): ?>
        <input type="hidden" name="id" value="<?php echo (int)$transactionData['id']; ?>">
    <?php endif; ?>

    <!-- Main Transaction Fields -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold">اطلاعات اصلی معامله</div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-2">
                    <label for="transaction_type" class="form-label">نوع معامله<span class="text-danger">*</span></label>
                    <select id="transaction_type" name="transaction_type" class="form-select" required>
                        <option value="sell" <?= ($defaultTransactionType === 'sell') ? 'selected' : ''; ?>>فروش</option>
                        <option value="buy" <?= ($defaultTransactionType === 'buy') ? 'selected' : ''; ?>>خرید</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="counterparty_contact_id" class="form-label">طرف حساب<span class="text-danger">*</span></label>
                    <select id="counterparty_contact_id" name="counterparty_contact_id" class="form-select" required>
                        <option value="">انتخاب کنید...</option>
                        <?php foreach ($contactsData as $contact): ?>
                            <option value="<?= (int)$contact['id']; ?>" <?= ($defaultContactId == $contact['id']) ? 'selected' : ''; ?>>
                                <?= Helper::escapeHtml($contact['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="transaction_date" class="form-label">تاریخ<span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="transaction_date" name="transaction_date" value="<?= Helper::escapeHtml($transactionDatePersian); ?>" required>
                </div>
                <div class="col-md-2">
                    <label for="mazaneh_price" class="form-label">قیمت مظنه</label>
                    <input type="text" id="mazaneh_price" name="mazaneh_price" class="form-control autonumeric" value="<?= Helper::escapeHtml($defaultMazaneh); ?>">
                </div>
                 <div class="col-md-3">
                    <label for="delivery_status" class="form-label">وضعیت تحویل</label>
                    <select id="delivery_status" name="delivery_status" class="form-select">
                        <option value="pending_receipt" <?= ($defaultDeliveryStatus === 'pending_receipt') ? 'selected' : ''; ?>>منتظر دریافت</option>
                        <option value="pending_delivery" <?= ($defaultDeliveryStatus === 'pending_delivery') ? 'selected' : ''; ?>>منتظر تحویل</option>
                        <option value="completed" <?= ($defaultDeliveryStatus === 'completed') ? 'selected' : ''; ?>>تکمیل شده</option>
                        <option value="cancelled" <?= ($defaultDeliveryStatus === 'cancelled') ? 'selected' : ''; ?>>لغو شده</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Items Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold">اقلام معامله</div>
        <div class="card-body">
            <div id="transaction-items-container"></div>
            <button type="button" id="add-transaction-item" class="btn btn-sm btn-outline-success mt-3">
                <i class="fas fa-plus me-1"></i> افزودن ردیف جدید
            </button>
        </div>
    </div>
    
    <!-- Financial Summary Section -->
    <div class="card shadow-sm mt-4">
        <div class="card-header"><h5 class="card-title mb-0">خلاصه مالی معامله</h5></div>
        <div class="card-body">
            <div class="row" id="summary-container">
                <!-- Summary fields will be injected by JS -->
            </div>
        </div>
    </div>

    <!-- Notes and Actions -->
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <div class="mb-3">
                <label for="notes" class="form-label">یادداشت‌ها</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"><?= Helper::escapeHtml($transactionData['notes'] ?? ''); ?></textarea>
            </div>
            <hr>
            <div class="d-flex justify-content-end">
                <a href="<?= $baseUrl ?>/app/transactions" class="btn btn-secondary me-2">انصراف</a>
                <button type="submit" class="btn btn-primary px-4" id="submit-btn">
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    <i class="fas fa-save me-1"></i> <?= $isEditMode ? 'به‌روزرسانی معامله' : 'ثبت معامله'; ?>
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
                <select name="items[{index}][product_id]" class="form-select form-select-sm product-select" required></select>
            </div>
            <div class="col-12">
                <div class="dynamic-fields-container pt-2"></div>
            </div>
        </div>
    </div>
</template>

<!-- Bootstrapping all necessary data for JavaScript -->
<script>
    window.APP_CONFIG = {
        baseUrl: "<?= Helper::escapeHtml($baseUrl); ?>",
        isEditMode: <?= json_encode($isEditMode); ?>,
        debug: <?= json_encode($viewData['config']['app']['debug'] ?? false); ?>
    };
    window.APP_DATA = {
        products: <?= json_encode($productsDataForJs, JSON_UNESCAPED_UNICODE); ?>,
        assayOffices: <?= json_encode($assayOfficesData, JSON_UNESCAPED_UNICODE); ?>,
        fields: <?= json_encode($fieldsData, JSON_UNESCAPED_UNICODE); ?>,
        formulas: <?= json_encode($formulasData, JSON_UNESCAPED_UNICODE); ?>,
        transactionData: <?= json_encode($transactionData, JSON_UNESCAPED_UNICODE); ?>,
        transactionItems: <?= json_encode($transactionItemsData, JSON_UNESCAPED_UNICODE); ?>
    };
</script>

<!-- Load the new transaction form script -->
<script src="<?= Helper::escapeHtml($baseUrl); ?>/js/transaction-form.js"></script>