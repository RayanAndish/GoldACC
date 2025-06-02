<?php
/**
 * Template: src/views/invoices/generator.php
 * Form for selecting transactions to generate an invoice.
 * Receives data via $viewData array from InvoiceController::showGeneratorForm.
 */

use App\Utils\Helper; // Use the Helper class
use App\core\CSRFProtector; // Use the CSRFProtector class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'صدور فاکتور معاملات';
$contacts = $viewData['contacts'] ?? []; // List of contacts for dropdown
$transactions = $viewData['transactions'] ?? []; // Filtered transactions list (after POST)
$selectedContactId = $viewData['selected_contact_id'] ?? null;
$selectedTransactionType = $viewData['selected_transaction_type'] ?? null;
$filters = $viewData['filters'] ?? ['start_date'=>'', 'end_date'=>'']; // Jalali dates for form
$errorMessage = $viewData['error_msg'] ?? null; // Errors related to filtering/loading
$loadingError = $viewData['loading_error'] ?? null; // Error loading contacts
$flashMessage = $viewData['flashMessage'] ?? null; // General flash messages
$baseUrl = $viewData['baseUrl'] ?? '';
$formActionFilter = $viewData['form_action_filter'] ?? '';
$formActionPreview = $viewData['form_action_preview'] ?? '';
$validTransactionTypes = $viewData['valid_transaction_types'] ?? ['buy', 'sell']; // Get from controller

?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php // --- Display Messages --- ?>
<?php if ($flashMessage && isset($flashMessage['text'])): ?>
    <div class="alert alert-<?php echo Helper::escapeHtml($flashMessage['type'] ?? 'info'); ?> alert-dismissible fade show">
        <?php echo Helper::escapeHtml($flashMessage['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
-<?php if ($errorMessage): ?>
-    <div class="alert alert-danger"><?php echo Helper::escapeHtml($errorMessage); ?></div>
-<?php endif; ?>
<?php if ($loadingError): ?>
    <div class="alert alert-warning"><?php echo Helper::escapeHtml($loadingError); ?></div>
<?php endif; ?>


<?php // --- Filter Form to Load Transactions --- ?>
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">۱. انتخاب طرف حساب و بازه زمانی</h5>
    </div>
    <div class="card-body">
        <?php if ($loadingError): /* Don't show form if contacts failed to load */ ?>
            <p class="text-danger">خطا در بارگذاری پیش‌نیازها. لطفا صفحه را دوباره بارگذاری کنید.</p>
        <?php else: ?>
            <form method="post" action="<?php echo $form_action_filter; ?>" id="filter-transactions-form" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo CSRFProtector::generateToken(); ?>">
                <input type="hidden" name="action" value="load_transactions">
                 <?php // TODO: Add CSRF token ?>

                <div class="row g-3 align-items-end">
                    <div class="col-md-4 col-lg-3">
                        <label for="contact_id" class="form-label">طرف حساب <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm <?php echo ($errorMessage && stripos($errorMessage, 'طرف حساب') !== false) ? 'is-invalid' : ''; ?>" id="contact_id" name="contact_id" required>
                             <option value="">-- انتخاب کنید --</option>
                               <?php foreach ($contacts as $contact): ?>
                                     <option value="<?php echo (int)$contact['id']; ?>" <?php echo ($selectedContactId == $contact['id']) ? 'selected' : ''; ?>>
                                         <?php echo Helper::escapeHtml($contact['name']); ?>
                                     </option>
                               <?php endforeach; ?>
                         </select>
                         <div class="invalid-feedback">انتخاب طرف حساب الزامی است.</div>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label for="transaction_type_filter" class="form-label">نوع فاکتور <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm <?php echo ($errorMessage && stripos($errorMessage, 'نوع فاکتور') !== false) ? 'is-invalid' : ''; ?>" id="transaction_type_filter" name="transaction_type_filter" required>
                            <option value="">-- خرید/فروش --</option>
                            <option value="buy" <?php echo ($selectedTransactionType === 'buy') ? 'selected' : ''; ?>>فاکتور خرید (کالاها/خدماتی که شما خریده‌اید)</option>
                            <option value="sell" <?php echo ($selectedTransactionType === 'sell') ? 'selected' : ''; ?>>فاکتور فروش (کالاها/خدماتی که شما فروخته‌اید)</option>
                        </select>
                         <div class="invalid-feedback">انتخاب نوع فاکتور الزامی است.</div>
                    </div>
                    <div class="col-md-2">
                        <label for="start_date" class="form-label small mb-1">از تاریخ</label>
                        <input type="text" class="form-control form-control-sm jalali-datepicker" id="start_date" name="start_date" value="<?php echo Helper::escapeHtml($filters['start_date'] ?? ''); ?>" placeholder="اختیاری">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label small mb-1">تا تاریخ</label>
                        <input type="text" class="form-control form-control-sm jalali-datepicker" id="end_date" name="end_date" value="<?php echo Helper::escapeHtml($filters['end_date'] ?? ''); ?>" placeholder="اختیاری">
                    </div>
                    <div class="col-md-auto flex-grow-1">
                           <button type="submit" class="btn btn-sm btn-primary w-100" title="نمایش معاملات مطابق با فیلتر">
                               <i class="fas fa-search me-1"></i> مشاهده معاملات
                           </button>
                    </div>
                </div>
            </form>
        <?php endif; // End if no loading error ?>
    </div>
</div>


<?php // --- Transaction Selection Table (Show only after successful POST filter) --- ?>
<?php if ($formActionFilter == $_SERVER['REQUEST_URI'] && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'load_transactions' && empty($errorMessage) && !empty($transactions)): ?>
    <hr class="my-4">
    <form id="invoice-items-form" method="post" action="<?php echo $form_action_preview; ?>" target="_blank" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo CSRFProtector::generateToken(); ?>">
        <input type="hidden" name="contact_id_for_invoice" value="<?php echo $selected_contact_id; ?>">
        <input type="hidden" name="invoice_type" value="<?php echo $selected_transaction_type; ?>">
         <?php // TODO: Add CSRF token ?>

         <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">۲. انتخاب ردیف‌های فاکتور <small class="text-muted">(نوع: <?php echo $selectedTransactionType == 'buy' ? 'خرید از طرف حساب' : 'فروش به طرف حساب'; ?>)</small></h5>
             </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 30px;">
                                     <input type="checkbox" class="form-check-input" id="select-all-tx" title="انتخاب / عدم انتخاب همه">
                                 </th>
                                <th style="width: 100px;">تاریخ</th>
                                <th>نوع محصول</th>
                                <th class="text-center">مقدار</th>
                                <th class="text-center">مبلغ کل <small>(ریال)</small></th>
                                <th>یادداشت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td class="text-center">
                                         <input type="checkbox" class="form-check-input tx-select" name="transaction_ids[]" value="<?php echo (int)$tx['id']; ?>">
                                     </td>
                                    <td class="small text-nowrap"><?php echo $tx['date_farsi'] ?? '-'; // Formatted in Controller ?></td>
                                    <td class="small"><?php echo $tx['product_farsi'] ?? '-'; // Translated in Controller ?></td>
                                    <td class="small text-center text-nowrap"> <?php echo $tx['display_amount'] ?? '-'; // Formatted in Controller ?> </td>
                                    <td class="fw-bold small text-center number-fa"><?php echo $tx['value_formatted'] ?? '-'; // Formatted in Controller ?></td>
                                    <td class="small" title="<?php echo Helper::escapeHtml($tx['notes'] ?? ''); ?>"> <?php echo $tx['notes_short'] ?? ''; // Shortened in Controller ?> </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <?php // Tax options ?>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="apply_tax_checkbox" name="apply_tax" value="1" checked> <?php // Default checked? ?>
                            <label class="form-check-label small" for="apply_tax_checkbox">محاسبه مالیات بر ارزش افزوده</label>
                        </div>
                        <div class="input-group input-group-sm" style="max-width: 150px;">
                            <input type="text" class="form-control format-number-js" data-decimals="2"
                                   id="tax_rate_percent_input" name="tax_rate_percent" value="9" <?php // Default tax rate? Make configurable ?>
                                   inputmode="decimal" aria-label="درصد مالیات">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <?php // Generate button ?>
                    <div>
                        <button type="submit" class="btn btn-success px-4" id="generate-invoice-btn" disabled>
                            <i class="fas fa-file-invoice me-1"></i> مشاهده پیش‌نمایش فاکتور
                        </button>
                    </div>
                </div>
            </div>
        </div><?php // End card ?>
    </form>

    <?php // JS for checkbox interactions and tax field toggle ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('invoice-items-form'); if (!form) return;
        const selectAll = document.getElementById('select-all-tx');
        const checkboxes = form.querySelectorAll('.tx-select');
        const btn = document.getElementById('generate-invoice-btn');
        const applyTaxCheckbox = document.getElementById('apply_tax_checkbox');
        const taxRateInput = document.getElementById('tax_rate_percent_input');

        function checkSelected() {
            if (!btn) return;
            let anyChecked = false;
            checkboxes.forEach(c => { if (c.checked) anyChecked = true; });
            btn.disabled = !anyChecked;

            if(selectAll) {
                let allChecked = true;
                if(checkboxes.length === 0) allChecked = false;
                else { checkboxes.forEach(c => { if (!c.checked) allChecked = false; }); }
                selectAll.checked = allChecked;
                selectAll.indeterminate = anyChecked && !allChecked;
            }
        }

        function toggleTaxRateInput() {
            if (taxRateInput) {
                 taxRateInput.disabled = !applyTaxCheckbox.checked;
             }
        }

        if(selectAll) { selectAll.addEventListener('change', function() { checkboxes.forEach(c => { c.checked = selectAll.checked; }); checkSelected(); }); }
        checkboxes.forEach(c => { c.addEventListener('change', checkSelected); });
        if(applyTaxCheckbox) { applyTaxCheckbox.addEventListener('change', toggleTaxRateInput); }

        // Initial states
        checkSelected();
        toggleTaxRateInput();
    });
    </script>

<?php elseif ($formActionFilter == $_SERVER['REQUEST_URI'] && $_SERVER['REQUEST_METHOD'] === 'POST'): // If form was submitted but no transactions found (and no other error) ?>
    <?php /* Message is now handled by controller setting flash message */ ?>
<?php endif; ?>


<?php // JS Includes for Datepicker and Number Formatting (if needed) ?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/jalalidatepicker.min.css" />
<script src="<?php echo $baseUrl; ?>/js/jalalidatepicker.min.js"></script>
<script>
    jalaliDatepicker.startWatch({ selector: '.jalali-datepicker', showTodayBtn: true, showCloseBtn: true, format: 'Y/m/d' });
    // Initialize number formatters if not done globally
</script>
<?php // JS for Bootstrap validation ?>
<script>
    (() => { 'use strict'; const forms = document.querySelectorAll('.needs-validation'); Array.from(forms).forEach(form => { form.addEventListener('submit', event => { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false) }) })();
</script>