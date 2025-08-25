<?php
/**
 * Template: src/views/payments/form.php
 * Form for adding or editing a Payment/Receipt.
 * REVISED: Fix datepicker persian digits and formatting on load, re-enabled method details.
 */
use App\Utils\Helper; // Already imported Helper, assuming latest version.
use App\Core\CSRFProtector;

$isEditMode = $viewData['is_edit_mode'] ?? false;
$pageTitle = $viewData['page_title'] ?? ($isEditMode ? 'ویرایش پرداخت/دریافت' : 'ثبت پرداخت/دریافت');
$formAction = $viewData['form_action'] ?? '';
$payment = $viewData['payment'] ?? [];
$contacts = $viewData['contacts'] ?? [];
$bankAccounts = $viewData['bank_accounts'] ?? [];
$transactions = $viewData['transactions'] ?? []; // These should come formatted for display in dropdown.
$paymentMethods = $viewData['payment_methods'] ?? [];
$submitButtonText = $viewData['submit_button_text'] ?? ($isEditMode ? 'به‌روزرسانی' : 'ثبت');
$errorMessage = $viewData['error_message'] ?? null;
$loadingError = $viewData['loading_error'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';

$initialDirection = $payment['direction'] ?? 'outflow';
// Determine initial bank selection for disabled state
$isBankSelected = !empty($payment['source_bank_account_id']) || !empty($payment['destination_bank_account_id']);
?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php if ($loadingError): ?>
    <div class="alert alert-warning"><?php echo Helper::escapeHtml($loadingError); ?></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <strong>خطا:</strong><br><?php echo $errorMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0"><?php echo $isEditMode ? 'ویرایش رکورد #' . Helper::formatPersianNumber($payment['id'] ?? '') : 'ورود اطلاعات جدید'; ?></h5></div>
    <div class="card-body">
        <?php if ($loadingError && empty($contacts)): ?>
            <p class="text-danger">پیش‌نیازهای فرم بارگذاری نشدند.</p>
        <?php else: ?>
            <form id="payment-form" action="<?php echo Helper::escapeHtml($formAction); ?>" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo Helper::generateCsrfToken(); ?>">
                <?php if ($isEditMode): ?><input type="hidden" name="payment_id" value="<?php echo Helper::escapeHtml($payment['id']); ?>"><?php endif; ?>
                <input type="hidden" name="direction" id="direction_hidden" value="<?php echo Helper::escapeHtml($initialDirection); ?>">

                <div class="row g-3 mb-3 align-items-end">
                    <div class="col-md-3">
                        <label for="direction_display" class="form-label">جهت تراکنش <span class="text-danger">*</span></label>
                        <select id="direction_display" class="form-select form-select-sm" <?php echo $isBankSelected ? 'disabled' : ''; ?>>
                            <option value="outflow" <?php echo ($initialDirection === 'outflow') ? 'selected' : ''; ?>>پرداخت (خروج)</option>
                            <option value="inflow" <?php echo ($initialDirection === 'inflow') ? 'selected' : ''; ?>>دریافت (ورود)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="payment_date" class="form-label">تاریخ و زمان <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm jalali-datepicker" id="payment_date" name="payment_date" value="<?php echo Helper::escapeHtml($payment['payment_date_persian'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="amount_rials" class="form-label">مبلغ <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control format-number-js" id="amount_rials" name="amount_rials" value="<?php echo Helper::escapeHtml($payment['amount_rials'] ?? ''); ?>" required>
                            <span class="input-group-text">ریال</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="payment_method" class="form-label">روش پرداخت <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="payment_method" name="payment_method" required>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach ($paymentMethods as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo (($payment['payment_method'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="payment-method-details-container" class="mb-3">
                    <?php // The content of fieldsets below moved back directly here per latest provided template. ?>
                    <fieldset id="details-cash" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                        <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات نقدی</legend>
                        <div class="mb-3">
                            <label for="method_details_payer_receiver" class="form-label">نام پرداخت/دریافت کننده نقدی:</label>
                            <input type="text" class="form-control form-control-sm" name="method_details_payer_receiver" value="<?php echo Helper::escapeHtml($payment['method_details_payer_receiver'] ?? ''); ?>">
                        </div>
                    </fieldset>
                    <fieldset id="details-barter" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                        <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات تهاتر</legend>
                        <div class="mb-3">
                            <label for="method_details_clearing_type" class="form-label">نوع تهاتر:</label>
                            <input type="text" class="form-control form-control-sm" name="method_details_clearing_type" value="<?php echo Helper::escapeHtml($payment['method_details_clearing_type'] ?? ''); ?>" placeholder="مثال: تهاتر با فاکتور شماره ...">
                        </div>
                    </fieldset>
                    <fieldset id="details-bank_slip" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                        <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات فیش بانکی</legend>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">شماره فیش:</label><input type="text" class="form-control form-control-sm" name="method_details_slip_number" value="<?php echo Helper::escapeHtml($payment['method_details_slip_number'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label">تاریخ فیش:</label><input type="text" class="form-control form-control-sm jalali-datepicker-nodate" name="method_details_slip_date" value="<?php echo Helper::escapeHtml($payment['method_details_slip_date'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label">بانک عامل:</label><input type="text" class="form-control form-control-sm" name="method_details_bank_agent" value="<?php echo Helper::escapeHtml($payment['method_details_bank_agent'] ?? ''); ?>"></div>
                        </div>
                    </fieldset>
                    <fieldset id="details-mobile_transfer" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                        <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات انتقال (همراه/اینترنت بانک)</legend>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">شماره پیگیری:</label><input type="text" class="form-control form-control-sm" name="method_details_tracking_code" value="<?php echo Helper::escapeHtml($payment['method_details_tracking_code'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label">تاریخ انتقال:</label><input type="text" class="form-control form-control-sm jalali-datepicker-nodate" name="method_details_transfer_date" value="<?php echo Helper::escapeHtml($payment['method_details_transfer_date'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label">کارت/حساب مبدا/مقصد:</label><input type="text" class="form-control form-control-sm" name="method_details_source_dest_info" value="<?php echo Helper::escapeHtml($payment['method_details_source_dest_info'] ?? ''); ?>"></div>
                        </div>
                    </fieldset>
                    <fieldset id="details-internet_transfer" class="payment-details-section" style="display: none;"></fieldset>
                    <fieldset id="details-atm" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                        <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات کارتخوان ATM</legend>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">شماره پایانه:</label><input type="text" class="form-control form-control-sm" name="method_details_terminal_id" value="<?php echo Helper::escapeHtml($payment['method_details_terminal_id'] ?? ''); ?>"></div>
                            <div class="col-md-8"><label class="form-label">کارت/حساب مبدا/مقصد:</label><input type="text" class="form-control form-control-sm" name="method_details_source_dest_info" value="<?php echo Helper::escapeHtml($payment['method_details_source_dest_info'] ?? ''); ?>"></div>
                        </div>
                    </fieldset>
                    <fieldset id="details-pos" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                        <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات کارتخوان POS</legend>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">دارنده POS:</label><input type="text" class="form-control form-control-sm" name="method_details_pos_holder" value="<?php echo Helper::escapeHtml($payment['method_details_pos_holder'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label">شماره پایانه:</label><input type="text" class="form-control form-control-sm" name="method_details_terminal_id" value="<?php echo Helper::escapeHtml($payment['method_details_terminal_id'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label">کارت/حساب مبدا/مقصد:</label><input type="text" class="form-control form-control-sm" name="method_details_source_dest_info" value="<?php echo Helper::escapeHtml($payment['method_details_source_dest_info'] ?? ''); ?>"></div>
                        </div>
                    </fieldset>
                    <fieldset id="details-cheque" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                        <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات چک بانکی</legend>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">شماره صیاد:</label><input type="text" class="form-control form-control-sm" name="method_details_cheque_sayad_id" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_sayad_id'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label">سری و سریال:</label><input type="text" class="form-control form-control-sm" name="method_details_cheque_serial" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_serial'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label">تاریخ سررسید:</label><input type="text" class="form-control form-control-sm jalali-datepicker-nodate" name="method_details_cheque_due_date" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_due_date'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label">بانک عامل:</label><input type="text" class="form-control form-control-sm" name="method_details_bank_agent" value="<?php echo Helper::escapeHtml($payment['method_details_bank_agent'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label">شماره حساب:</label><input type="text" class="form-control form-control-sm" name="method_details_cheque_account_number" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_account_number'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label">نوع چک:</label><input type="text" class="form-control form-control-sm" name="method_details_cheque_type" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_type'] ?? ''); ?>"></div>
                            <div class="col-md-6"><label class="form-label">نام صاحب حساب:</label><input type="text" class="form-control form-control-sm" name="method_details_cheque_holder_name" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_holder_name'] ?? ''); ?>"></div>
                            <div class="col-md-6"><label class="form-label">کد ملی صاحب حساب:</label><input type="text" class="form-control form-control-sm" name="method_details_cheque_holder_nid" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_holder_nid'] ?? ''); ?>"></div>
                        </div>
                    </fieldset>
                    <fieldset id="details-clearing_account" class="payment-details-section" style="display: none;"></fieldset>
                </div>

                <fieldset class="border p-3 mb-3 rounded bg-light">
                    <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">ثبت در حساب بانکی <small>(اختیاری)</small></legend>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="source_bank_account_id" class="form-label">پرداخت از حساب:</label>
                             <select class="form-select form-select-sm bank-link" id="source_bank_account_id" name="source_bank_account_id" data-direction="outflow" <?php echo !empty($payment['destination_bank_account_id']) ? 'disabled' : ''; ?>>
                                 <option value="">-- انتخاب --</option>
                                 <?php foreach ($bankAccounts as $acc): ?>
                                     <option value="<?php echo $acc['id']; ?>" <?php echo (($payment['source_bank_account_id'] ?? null) == $acc['id']) ? 'selected' : ''; ?>><?php echo Helper::escapeHtml($acc['account_name']); ?></option>
                                 <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                             <label for="destination_bank_account_id" class="form-label">واریز به حساب:</label>
                             <select class="form-select form-select-sm bank-link" id="destination_bank_account_id" name="destination_bank_account_id" data-direction="inflow" <?php echo !empty($payment['source_bank_account_id']) ? 'disabled' : ''; ?>>
                                  <option value="">-- انتخاب --</option>
                                  <?php foreach ($bankAccounts as $acc): ?>
                                     <option value="<?php echo $acc['id']; ?>" <?php echo (($payment['destination_bank_account_id'] ?? null) == $acc['id']) ? 'selected' : ''; ?>><?php echo Helper::escapeHtml($acc['account_name']); ?></option>
                                  <?php endforeach; ?>
                             </select>
                         </div>
                     </div>
                </fieldset>
                
                <fieldset class="border p-3 mb-3 rounded">
                    <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">پرداخت کننده <span class="text-danger">*</span></legend>
                    <div class="row g-3">
                         <div class="col-md-6">
                            <label for="paying_contact_id" class="form-label">مخاطب:</label>
                            <select class="form-select form-select-sm" id="paying_contact_id" name="paying_contact_id">
                                <option value="">-- انتخاب / ورود دستی --</option>
                                <?php foreach ($contacts as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo (($payment['paying_contact_id'] ?? null) == $c['id']) ? 'selected' : ''; ?>><?php echo Helper::escapeHtml($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                         </div>
                         <div class="col-md-6">
                             <label for="paying_details" class="form-label">یا جزئیات دستی:</label>
                            <input type="text" class="form-control form-control-sm" id="paying_details" name="paying_details" value="<?php echo Helper::escapeHtml($payment['paying_details'] ?? ''); ?>">
                        </div>
                     </div>
                 </fieldset>
                 
                <fieldset class="border p-3 mb-3 rounded">
                     <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">دریافت کننده <span class="text-danger">*</span></legend>
                     <div class="row g-3">
                         <div class="col-md-6">
                             <label for="receiving_contact_id" class="form-label">مخاطب:</label>
                            <select class="form-select form-select-sm" id="receiving_contact_id" name="receiving_contact_id">
                                 <option value="">-- انتخاب / ورود دستی --</option>
                                <?php foreach ($contacts as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo (($payment['receiving_contact_id'] ?? null) == $c['id']) ? 'selected' : ''; ?>><?php echo Helper::escapeHtml($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                         </div>
                         <div class="col-md-6">
                              <label for="receiving_details" class="form-label">یا جزئیات دستی:</label>
                            <input type="text" class="form-control form-control-sm" id="receiving_details" name="receiving_details" value="<?php echo Helper::escapeHtml($payment['receiving_details'] ?? ''); ?>">
                        </div>
                     </div>
                 </fieldset>

                <div class="row g-3">
                     <div class="col-md-6">
                        <label for="related_transaction_id" class="form-label">ارتباط با معامله <small>(اختیاری)</small></label>
                         <select class="form-select form-select-sm" id="related_transaction_id" name="related_transaction_id">
                             <option value="">-- انتخاب --</option>
                             <?php foreach ($transactions as $tx): ?>
                                 <option value="<?php echo (int)$tx['id']; ?>" <?php echo (($payment['related_transaction_id'] ?? null) == $tx['id']) ? 'selected' : ''; ?>>
                                      <?php echo Helper::escapeHtml($tx['display']); // Assuming display string is now directly from controller. ?>
                                  </option>
                              <?php endforeach; ?>
                          </select>
                     </div>
                     <div class="col-md-6">
                         <label for="notes" class="form-label">یادداشت <small>(اختیاری)</small></label>
                        <input type="text" class="form-control form-control-sm" id="notes" name="notes" value="<?php echo Helper::escapeHtml($payment['notes'] ?? ''); ?>">
                    </div>
                </div>

                <hr class="my-4">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?php echo $baseUrl; ?>/app/payments" class="btn btn-outline-secondary px-4"><i class="fas fa-times me-1"></i> انصراف</a>
                    <button type="submit" class="btn btn-primary px-5"><i class="fas fa-check me-1"></i> <?php echo Helper::escapeHtml($submitButtonText); ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sourceSelect = document.getElementById('source_bank_account_id');
    const destSelect = document.getElementById('destination_bank_account_id');
    const directionDisplaySelect = document.getElementById('direction_display');
    const directionHiddenInput = document.getElementById('direction_hidden');

    function handleBankLinkChange(event) {
        const changedSelect = event.target;
        const otherSelect = (changedSelect === sourceSelect) ? destSelect : sourceSelect;
        if (changedSelect.value) {
            otherSelect.value = "";
            otherSelect.disabled = true;
            directionDisplaySelect.disabled = true;
            directionDisplaySelect.value = changedSelect.dataset.direction;
        } else {
            otherSelect.disabled = false;
            if (!otherSelect.value) directionDisplaySelect.disabled = false;
        }
        directionHiddenInput.value = directionDisplaySelect.value;
    }

    directionDisplaySelect.addEventListener('change', function() {
        if (!this.disabled) directionHiddenInput.value = this.value;
    });

    [sourceSelect, destSelect].forEach(el => el.addEventListener('change', handleBankLinkChange));
    if (sourceSelect.value) handleBankLinkChange({ target: sourceSelect });
    else if (destSelect.value) handleBankLinkChange({ target: destSelect });

    const paymentMethodSelect = document.getElementById('payment_method');
    const detailsContainer = document.getElementById('payment-method-details-container');
    function togglePaymentDetails(method) {
        detailsContainer.querySelectorAll('.payment-details-section').forEach(s => s.style.display = 'none');
        let sectionId = 'details-' + (method === 'internet_transfer' ? 'mobile_transfer' : method);
        const section = document.getElementById(sectionId);
        if (section) section.style.display = 'block';
    }
    paymentMethodSelect.addEventListener('change', e => togglePaymentDetails(e.target.value));
    togglePaymentDetails(paymentMethodSelect.value);

    if (typeof jalaliDatepicker !== 'undefined') {
        jalaliDatepicker.startWatch({ selector: '.jalali-datepicker', time: true, persianDigits: true }); // Enable persianDigits for main datepicker
        jalaliDatepicker.startWatch({ selector: '.jalali-datepicker-nodate', time: false, persianDigits: true }); // Enable persianDigits for others
    }
    
    // AutoNumeric init for amounts input.
    if (typeof AutoNumeric !== 'undefined') {
        new AutoNumeric('#amount_rials', {
            digitGroupSeparator: ',',
            decimalCharacter: '.',
            decimalPlaces: 0, // No decimals for Rials
            digitalGroupSpacing: '3'
        });
    } else {
        console.warn("AutoNumeric library not found for payment form.");
    }

});
</script>