<?php
/**
 * Template: src/views/payments/form.php
 * Form for adding or editing a Payment/Receipt.
 * Receives data via $viewData array from PaymentController.
 */

use App\Utils\Helper; // Use the Helper class
use Morilog\Jalali\Jalalian; // Add Jalalian namespace

// --- Extract data from $viewData ---
$isEditMode = $viewData['is_edit_mode'] ?? false;
$pageTitle = $viewData['page_title'] ?? ($isEditMode ? 'ویرایش پرداخت/دریافت' : 'ثبت پرداخت/دریافت');
$formAction = $viewData['form_action'] ?? '';
$payment = $viewData['payment'] ?? [
    'id'=>null, 'payment_date'=>'', 'amount_rials'=>'',
    'paying_contact_id'=>null, 'paying_details'=>'', 'receiving_contact_id'=>null, 'receiving_details'=>'',
    'related_transaction_id'=>null, 'notes'=>'', 'source_bank_account_id'=>null, 'destination_bank_account_id'=>null
];
$contacts = $viewData['contacts'] ?? []; // List of contacts for dropdowns
$bankAccounts = $viewData['bank_accounts'] ?? []; // List of bank accounts for dropdowns
$transactions = $viewData['transactions'] ?? []; // List of transactions for dropdown (optional)
$validDirections = $viewData['valid_directions'] ?? ['inflow', 'outflow']; // Valid direction values
$submitButtonText = $viewData['submit_button_text'] ?? ($isEditMode ? 'به‌روزرسانی' : 'ثبت');
$errorMessage = $viewData['error_message'] ?? null; // Validation errors from POST
$loadingError = $viewData['loading_error'] ?? null; // Error loading dropdown data
$baseUrl = $viewData['baseUrl'] ?? '';
$paymentMethods = $viewData['payment_methods'] ?? []; // Get payment methods from controller

// Determine the initial state for the direction display based on selected bank accounts
$initialDirection = $payment['direction'] ?? 'outflow';
if (!empty($payment['source_bank_account_id'])) {
    $initialDirection = 'outflow';
} elseif (!empty($payment['destination_bank_account_id'])) {
    $initialDirection = 'inflow';
}
$isBankSelected = !empty($payment['source_bank_account_id']) || !empty($payment['destination_bank_account_id']);

?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php // --- Display Messages --- ?>
<?php if ($loadingError): ?>
    <div class="alert alert-warning"><?php echo Helper::escapeHtml($loadingError); ?></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <strong>خطا:</strong><br><?php echo $errorMessage; /* Allow <br> */ ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0"><?php echo $isEditMode ? 'ویرایش رکورد #' . Helper::escapeHtml($payment['id']) : 'ورود اطلاعات پرداخت/دریافت جدید'; ?></h5>
    </div>
    <div class="card-body">
        <?php if ($loadingError && empty($contacts) && empty($bankAccounts)): // Prevent showing form if critical lists failed ?>
            <p class="text-danger">پیش‌نیازهای فرم بارگذاری نشدند. لطفا دوباره تلاش کنید.</p>
            <a href="<?php echo $baseUrl; ?>/app/payments" class="btn btn-secondary mt-2">بازگشت به لیست</a>
        <?php else: ?>
            <form id="payment-form" action="<?php echo Helper::escapeHtml($formAction); ?>" method="POST" class="needs-validation" novalidate>
                <?php // TODO: Add CSRF token ?>
                <?php if ($isEditMode): ?>
                    <input type="hidden" name="payment_id" value="<?php echo Helper::escapeHtml($payment['id']); ?>">
                <?php endif; ?>

                <?php // --- Row 1: Date, Amount, Direction --- ?>
                <div class="row g-3 mb-3 align-items-center">
                    <div class="col-md-4">
                        <label for="payment_date" class="form-label">تاریخ و زمان <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control form-control-sm jalali-datepicker <?php echo ($errorMessage && stripos($errorMessage, 'تاریخ') !== false) ? 'is-invalid' : ''; ?>"
                               id="payment_date" name="payment_date"
                               value="<?php echo Helper::escapeHtml($payment['payment_date_persian'] ?? ''); ?>"
                               required placeholder="Y/m/d H:i:s">
                         <div class="invalid-feedback">تاریخ و زمان معتبر انتخاب کنید.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="amount_rials" class="form-label">مبلغ <span class="text-danger">*</span></label>
                         <div class="input-group input-group-sm">
                             <input type="text"
                                    class="form-control format-number-js <?php echo ($errorMessage && stripos($errorMessage, 'مبلغ') !== false) ? 'is-invalid' : ''; ?>"
                                    id="amount_rials" name="amount_rials"
                                    value="<?php echo Helper::escapeHtml($payment['amount_rials'] ?? ''); ?>"
                                    required inputmode="numeric" placeholder="مثال: 1,250,000">
                             <span class="input-group-text">ریال</span>
                        </div>
                         <div class="invalid-feedback">مبلغ معتبر (عدد مثبت) وارد کنید.</div>
                    </div>
                    <div class="col-md-4">
                         <label for="payment_method" class="form-label">روش پرداخت/دریافت <span class="text-danger">*</span></label>
                         <select class="form-select form-select-sm <?php echo ($errorMessage && stripos($errorMessage, 'روش پرداخت') !== false) ? 'is-invalid' : ''; ?>" id="payment_method" name="payment_method" required>
                             <option value="">-- انتخاب کنید --</option>
                             <?php foreach ($paymentMethods as $methodKey => $methodLabel): ?>
                                 <option value="<?php echo Helper::escapeHtml($methodKey); ?>" <?php echo (($payment['payment_method'] ?? null) === $methodKey) ? 'selected' : ''; ?>>
                                     <?php echo Helper::escapeHtml($methodLabel); ?>
                                 </option>
                             <?php endforeach; ?>
                        </select>
                         <div class="invalid-feedback">روش پرداخت الزامی است.</div>
                    </div>
                </div>

                 <?php // --- Payment Method Details Sections (Initially Hidden) --- ?>
                 <div id="payment-method-details-container" class="mb-3">

                     <?php // --- Details for: cash --- ?>
                     <fieldset id="details-cash" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                         <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات نقدی</legend>
                         <div class="mb-3">
                             <label for="method_details_payer_receiver" class="form-label">نام پرداخت/دریافت کننده نقدی:</label>
                             <input type="text" class="form-control form-control-sm" id="method_details_payer_receiver" name="method_details_payer_receiver" value="<?php echo Helper::escapeHtml($payment['method_details_payer_receiver'] ?? ''); ?>" placeholder="مثال: تحویل حضوری به ...">
                         </div>
                     </fieldset>

                     <?php // --- Details for: barter --- ?>
                     <fieldset id="details-barter" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                         <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات تهاتر</legend>
                         <div class="mb-3">
                             <label for="method_details_clearing_type" class="form-label">نوع تهاتر:</label>
                             <input type="text" class="form-control form-control-sm" id="method_details_clearing_type" name="method_details_clearing_type" value="<?php echo Helper::escapeHtml($payment['method_details_clearing_type'] ?? ''); ?>" placeholder="مثال: تهاتر با فاکتور شماره ...، تهاتر با طلا...">
                         </div>
                     </fieldset>

                     <?php // --- Details for: bank_slip --- ?>
                     <fieldset id="details-bank_slip" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                         <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات فیش بانکی</legend>
                         <div class="row g-3">
                             <div class="col-md-4">
                                 <label for="method_details_slip_number" class="form-label">شماره فیش:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_slip_number" name="method_details_slip_number" value="<?php echo Helper::escapeHtml($payment['method_details_slip_number'] ?? ''); ?>">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_slip_date" class="form-label">تاریخ فیش:</label>
                                 <input type="text" class="form-control form-control-sm jalali-datepicker-nodate" id="method_details_slip_date" name="method_details_slip_date" value="<?php echo Helper::escapeHtml($payment['method_details_slip_date'] ?? ''); ?>" placeholder="Y/m/d">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_bank_agent_slip" class="form-label">بانک عامل:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_bank_agent_slip" name="method_details_bank_agent" value="<?php echo Helper::escapeHtml($payment['method_details_bank_agent'] ?? ''); ?>">
                             </div>
                             <div class="col-md-6">
                                 <label for="method_details_source_dest_info_slip" class="form-label">کارت/حساب مبدا/مقصد:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_source_dest_info_slip" name="method_details_source_dest_info" value="<?php echo Helper::escapeHtml($payment['method_details_source_dest_info'] ?? ''); ?>">
                             </div>
                             <div class="col-md-6">
                                 <label for="method_details_tracking_code_slip" class="form-label">شماره پیگیری (اختیاری):</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_tracking_code_slip" name="method_details_tracking_code" value="<?php echo Helper::escapeHtml($payment['method_details_tracking_code'] ?? ''); ?>">
                             </div>
                         </div>
                     </fieldset>

                     <?php // --- Details for: mobile_transfer, internet_transfer --- ?>
                     <fieldset id="details-mobile_transfer" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                          <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات انتقال (همراه/اینترنت بانک)</legend>
                         <div class="row g-3">
                             <div class="col-md-4">
                                 <label for="method_details_tracking_code_transfer" class="form-label">شماره پیگیری:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_tracking_code_transfer" name="method_details_tracking_code" value="<?php echo Helper::escapeHtml($payment['method_details_tracking_code'] ?? ''); ?>">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_transfer_date" class="form-label">تاریخ انتقال:</label>
                                 <input type="text" class="form-control form-control-sm jalali-datepicker-nodate" id="method_details_transfer_date" name="method_details_transfer_date" value="<?php echo Helper::escapeHtml($payment['method_details_transfer_date'] ?? ''); ?>" placeholder="Y/m/d">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_source_dest_info_transfer" class="form-label">کارت/حساب مبدا/مقصد:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_source_dest_info_transfer" name="method_details_source_dest_info" value="<?php echo Helper::escapeHtml($payment['method_details_source_dest_info'] ?? ''); ?>">
                             </div>
                         </div>
                     </fieldset>
                     <fieldset id="details-internet_transfer" class="payment-details-section" style="display: none;"> </fieldset> <?php /* No extra fields, uses mobile_transfer fields */ ?>

                    <?php // --- Details for: atm --- ?>
                    <fieldset id="details-atm" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                        <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات کارتخوان ATM</legend>
                         <div class="row g-3">
                            <div class="col-md-4">
                                 <label for="method_details_tracking_code_atm" class="form-label">شماره پیگیری:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_tracking_code_atm" name="method_details_tracking_code" value="<?php echo Helper::escapeHtml($payment['method_details_tracking_code'] ?? ''); ?>">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_transfer_date_atm" class="form-label">تاریخ واریز:</label>
                                 <input type="text" class="form-control form-control-sm jalali-datepicker-nodate" id="method_details_transfer_date_atm" name="method_details_transfer_date" value="<?php echo Helper::escapeHtml($payment['method_details_transfer_date'] ?? ''); ?>" placeholder="Y/m/d">
                             </div>
                              <div class="col-md-4">
                                 <label for="method_details_terminal_id_atm" class="form-label">شماره پایانه:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_terminal_id_atm" name="method_details_terminal_id" value="<?php echo Helper::escapeHtml($payment['method_details_terminal_id'] ?? ''); ?>">
                             </div>
                            <div class="col-md-12">
                                 <label for="method_details_source_dest_info_atm" class="form-label">کارت/حساب مبدا/مقصد:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_source_dest_info_atm" name="method_details_source_dest_info" value="<?php echo Helper::escapeHtml($payment['method_details_source_dest_info'] ?? ''); ?>">
                             </div>
                        </div>
                    </fieldset>

                    <?php // --- Details for: pos --- ?>
                    <fieldset id="details-pos" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                        <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات کارتخوان POS</legend>
                        <div class="row g-3">
                             <div class="col-md-4">
                                 <label for="method_details_pos_holder" class="form-label">دارنده POS:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_pos_holder" name="method_details_pos_holder" value="<?php echo Helper::escapeHtml($payment['method_details_pos_holder'] ?? ''); ?>">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_tracking_code_pos" class="form-label">شماره پیگیری:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_tracking_code_pos" name="method_details_tracking_code" value="<?php echo Helper::escapeHtml($payment['method_details_tracking_code'] ?? ''); ?>">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_transfer_date_pos" class="form-label">تاریخ واریز:</label>
                                 <input type="text" class="form-control form-control-sm jalali-datepicker-nodate" id="method_details_transfer_date_pos" name="method_details_transfer_date" value="<?php echo Helper::escapeHtml($payment['method_details_transfer_date'] ?? ''); ?>" placeholder="Y/m/d">
                             </div>
                              <div class="col-md-6">
                                 <label for="method_details_terminal_id_pos" class="form-label">شماره پایانه:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_terminal_id_pos" name="method_details_terminal_id" value="<?php echo Helper::escapeHtml($payment['method_details_terminal_id'] ?? ''); ?>">
                             </div>
                            <div class="col-md-6">
                                 <label for="method_details_source_dest_info_pos" class="form-label">کارت/حساب مبدا/مقصد:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_source_dest_info_pos" name="method_details_source_dest_info" value="<?php echo Helper::escapeHtml($payment['method_details_source_dest_info'] ?? ''); ?>">
                             </div>
                        </div>
                    </fieldset>

                     <?php // --- Details for: cheque --- ?>
                    <fieldset id="details-cheque" class="payment-details-section border p-3 rounded bg-light" style="display: none;">
                         <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3">جزئیات چک بانکی</legend>
                        <div class="row g-3">
                             <div class="col-md-4">
                                 <label for="method_details_cheque_sayad_id" class="form-label">شماره صیاد:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_cheque_sayad_id" name="method_details_cheque_sayad_id" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_sayad_id'] ?? ''); ?>">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_cheque_serial" class="form-label">سری و سریال:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_cheque_serial" name="method_details_cheque_serial" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_serial'] ?? ''); ?>">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_cheque_due_date" class="form-label">تاریخ سررسید:</label>
                                 <input type="text" class="form-control form-control-sm jalali-datepicker-nodate" id="method_details_cheque_due_date" name="method_details_cheque_due_date" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_due_date'] ?? ''); ?>" placeholder="Y/m/d">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_bank_agent_cheque" class="form-label">بانک عامل:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_bank_agent_cheque" name="method_details_bank_agent" value="<?php echo Helper::escapeHtml($payment['method_details_bank_agent'] ?? ''); ?>">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_cheque_account_number" class="form-label">شماره حساب:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_cheque_account_number" name="method_details_cheque_account_number" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_account_number'] ?? ''); ?>">
                             </div>
                             <div class="col-md-4">
                                 <label for="method_details_cheque_type" class="form-label">نوع چک:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_cheque_type" name="method_details_cheque_type" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_type'] ?? ''); ?>" placeholder="عادی، تضمینی، ...">
                             </div>
                             <div class="col-md-6">
                                 <label for="method_details_cheque_holder_name" class="form-label">نام صاحب حساب:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_cheque_holder_name" name="method_details_cheque_holder_name" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_holder_name'] ?? ''); ?>">
                             </div>
                              <div class="col-md-6">
                                 <label for="method_details_cheque_holder_nid" class="form-label">کد ملی صاحب حساب:</label>
                                 <input type="text" class="form-control form-control-sm" id="method_details_cheque_holder_nid" name="method_details_cheque_holder_nid" value="<?php echo Helper::escapeHtml($payment['method_details_cheque_holder_nid'] ?? ''); ?>">
                             </div>
                        </div>
                    </fieldset>

                    <?php // --- Details for: clearing_account --- ?>
                    <fieldset id="details-clearing_account" class="payment-details-section" style="display: none;">
                         <?php /* No extra fields needed, uses default payer/receiver fields */ ?>
                     </fieldset>

                 </div> <?php // End #payment-method-details-container ?>

                <?php // --- Row 2: Bank Account Links (Now optional or depends on method) --- ?>
                <fieldset class="border p-3 mb-3 rounded bg-light">
                    <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3"><i class="fas fa-university me-1"></i>ثبت در حساب بانکی <small>(اختیاری)</small></legend>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="source_bank_account_id" class="form-label"><i class="fas fa-arrow-alt-circle-up text-danger"></i> پرداخت از حساب:</label>
                             <select class="form-select form-select-sm bank-link" id="source_bank_account_id" name="source_bank_account_id" data-direction="outflow" <?php echo !empty($payment['destination_bank_account_id']) ? 'disabled' : ''; ?>>
                                 <option value="">-- انتخاب حساب مبدا --</option>
                                 <?php foreach ($bankAccounts as $acc): ?>
                                     <option value="<?php echo (int)$acc['id']; ?>" <?php echo (($payment['source_bank_account_id'] ?? null) == $acc['id']) ? 'selected' : ''; ?>>
                                         <?php echo Helper::escapeHtml($acc['account_name']); ?> <?php if($acc['bank_name']) echo '(' . Helper::escapeHtml($acc['bank_name']) . ')'; ?>
                                     </option>
                                 <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                             <label for="destination_bank_account_id" class="form-label"><i class="fas fa-arrow-alt-circle-down text-success"></i> واریز به حساب:</label>
                             <select class="form-select form-select-sm bank-link" id="destination_bank_account_id" name="destination_bank_account_id" data-direction="inflow" <?php echo !empty($payment['source_bank_account_id']) ? 'disabled' : ''; ?>>
                                  <option value="">-- انتخاب حساب مقصد --</option>
                                  <?php foreach ($bankAccounts as $acc): ?>
                                     <option value="<?php echo (int)$acc['id']; ?>" <?php echo (($payment['destination_bank_account_id'] ?? null) == $acc['id']) ? 'selected' : ''; ?>>
                                         <?php echo Helper::escapeHtml($acc['account_name']); ?> <?php if($acc['bank_name']) echo '(' . Helper::escapeHtml($acc['bank_name']) . ')'; ?>
                                     </option>
                                  <?php endforeach; ?>
                             </select>
                         </div>
                     </div>
                     <div class="form-text small text-muted mt-2">انتخاب حساب بانکی، این پرداخت/دریافت را در گردش آن حساب نیز ثبت می‌کند و جهت تراکنش را تعیین می‌کند. فقط یکی از دو فیلد بالا را انتخاب کنید.</div>
                 </fieldset>

                <?php // --- Row 3: Payer Details --- ?>
                <fieldset class="border p-3 mb-3 rounded">
                    <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3"><i class="fas fa-user-minus me-1"></i> پرداخت کننده <span class="text-danger">*</span></legend>
                    <div class="row g-3">
                         <div class="col-md-6">
                            <label for="paying_contact_id" class="form-label">مخاطب پرداخت کننده:</label>
                            <select class="form-select form-select-sm" id="paying_contact_id" name="paying_contact_id">
                                <option value="">-- انتخاب از لیست / یا ورود دستی --</option>
                                <?php foreach ($contacts as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (($payment['paying_contact_id'] ?? null) == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo Helper::escapeHtml($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                         </div>
                         <div class="col-md-6">
                             <label for="paying_details" class="form-label">یا جزئیات پرداخت کننده:</label>
                            <input type="text" class="form-control form-control-sm" id="paying_details" name="paying_details" value="<?php echo Helper::escapeHtml($payment['paying_details'] ?? ''); ?>" placeholder="نام، شماره کارت/شبا، توضیحات...">
                        </div>
                     </div>
                    <div class="form-text small text-muted mt-2">اگر پرداخت کننده در لیست مخاطبین نیست یا می‌خواهید جزئیات بیشتری ثبت کنید، از فیلد دوم استفاده نمایید.</div>
                 </fieldset>

                 <?php // --- Row 4: Receiver Details --- ?>
                <fieldset class="border p-3 mb-3 rounded">
                     <legend class="fs-6 fw-normal text-muted w-auto px-2 mb-3"><i class="fas fa-user-plus me-1"></i> دریافت کننده <span class="text-danger">*</span></legend>
                     <div class="row g-3">
                         <div class="col-md-6">
                             <label for="receiving_contact_id" class="form-label">مخاطب دریافت کننده:</label>
                            <select class="form-select form-select-sm" id="receiving_contact_id" name="receiving_contact_id">
                                 <option value="">-- انتخاب از لیست / یا ورود دستی --</option>
                                <?php foreach ($contacts as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (($payment['receiving_contact_id'] ?? null) == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo Helper::escapeHtml($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                         </div>
                         <div class="col-md-6">
                              <label for="receiving_details" class="form-label">یا جزئیات دریافت کننده:</label>
                            <input type="text" class="form-control form-control-sm" id="receiving_details" name="receiving_details" value="<?php echo Helper::escapeHtml($payment['receiving_details'] ?? ''); ?>" placeholder="نام، شماره حساب/شبا، توضیحات...">
                        </div>
                     </div>
                    <div class="form-text small text-muted mt-2">اگر دریافت کننده در لیست مخاطبین نیست یا می‌خواهید جزئیات بیشتری ثبت کنید، از فیلد دوم استفاده نمایید.</div>
                 </fieldset>

                 <?php // --- Row 5: Related Transaction and Notes --- ?>
                <div class="row g-3">
                     <div class="col-md-6">
                        <label for="related_transaction_id" class="form-label">ارتباط با معامله <small>(اختیاری)</small></label>
                         <select class="form-select form-select-sm" id="related_transaction_id" name="related_transaction_id">
                             <option value="">-- انتخاب معامله مرتبط --</option>
                             <?php // Populate this dropdown if $transactions is passed from controller ?>
                             <?php foreach ($transactions as $tx): ?>
                                 <option value="<?php echo (int)$tx['id']; ?>" <?php echo (($payment['related_transaction_id'] ?? null) == $tx['id']) ? 'selected' : ''; ?>>
                                      <?php // Create a descriptive text for the transaction ?>
                                      <?php echo '#' . $tx['id'] . ': ' . ($tx['transaction_type']=='buy'?'خرید':'فروش') . ' ' . Helper::translateProductType($tx['gold_product_type']) . ' (' . ($tx['transaction_date'] ? Jalalian::fromFormat('Y-m-d H:i:s', $tx['transaction_date'])->format('Y/m/d H:i') : '-') . ')'; ?>
                                  </option>
                              <?php endforeach; ?>
                          </select>
                     </div>
                     <div class="col-md-6">
                         <label for="notes" class="form-label">یادداشت / شرح بیشتر <small>(اختیاری)</small></label>
                        <input type="text" class="form-control form-control-sm" id="notes" name="notes" value="<?php echo Helper::escapeHtml($payment['notes'] ?? ''); ?>" maxlength="500">
                    </div>
                </div>

                <?php // --- Buttons --- ?>
                <hr class="my-4">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?php echo $baseUrl; ?>/app/payments" class="btn btn-outline-secondary px-4">
                        <i class="fas fa-times me-1"></i> انصراف
                    </a>
                    <button type="submit" class="btn btn-primary px-5">
                        <i class="fas fa-check me-1"></i> <?php echo Helper::escapeHtml($submitButtonText); ?>
                    </button>
                </div>
            </form>
        <?php endif; // End if no loading error ?>
    </div> <?php // <!-- End Card Body --> ?>
</div> <?php // <!-- End Card --> ?>

<?php // --- JavaScript for Form Logic (Datepicker, Number Format, Bank/Direction Link) --- ?>
<?php // Load datepicker JS if not already included globally ?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/jalalidatepicker.min.css" />
<script src="<?php echo $baseUrl; ?>/js/jalalidatepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Datepicker
     if (typeof jalaliDatepicker !== 'undefined') {
        jalaliDatepicker.startWatch({
            selector: '.jalali-datepicker',
            time: true,
            format: 'Y/m/d H:i:s',
            persianDigits: true,
            showCloseBtn: true,
            showTodayBtn: true,
            autoClose: true
        });
    } else { console.error("Jalali Datepicker not found."); }

    // Initialize Number Formatting (if format-number-js class is used)
    // This script should be defined globally (e.g., in footer) or included here
    // Example: initNumberFormatting();

    // Link Bank Account selection to Direction
    const sourceSelect = document.getElementById('source_bank_account_id');
    const destSelect = document.getElementById('destination_bank_account_id');
    const directionDisplaySelect = document.getElementById('direction_display');
    const directionHiddenInput = document.getElementById('direction_hidden');

    function handleBankLinkChange(event) {
        const changedSelect = event.target;
        if (!changedSelect || !directionDisplaySelect || !directionHiddenInput) return;

        const otherSelect = (changedSelect === sourceSelect) ? destSelect : sourceSelect;
        if (!otherSelect) return;

        let newDirection = directionHiddenInput.value; // Keep current direction by default

        if (changedSelect.value !== "") { // If a bank account IS selected
            newDirection = changedSelect.getAttribute('data-direction'); // Get direction from selected
            if (otherSelect.value !== "") { // Clear the other select if it was selected
                otherSelect.value = "";
                otherSelect.disabled = true; // Disable the other select
            } else {
                 otherSelect.disabled = true; // Disable the other select even if it was empty
            }
            directionDisplaySelect.value = newDirection; // Update display
            directionDisplaySelect.disabled = true; // Disable manual direction change
        } else { // If a bank account is DESELECTED
            otherSelect.disabled = false; // Enable the other select
            if (otherSelect.value === "") { // If the other select is ALSO empty
                directionDisplaySelect.disabled = false; // Allow manual direction change
                // Keep the current direction from the display select unless user changes it
                newDirection = directionDisplaySelect.value;
            } else {
                 // The other select IS selected, direction is based on it
                 newDirection = otherSelect.getAttribute('data-direction');
                 directionDisplaySelect.value = newDirection;
                 directionDisplaySelect.disabled = true;
            }
        }
        directionHiddenInput.value = newDirection; // Update the hidden input that gets submitted
    }

    if (sourceSelect && destSelect && directionDisplaySelect && directionHiddenInput) {
        sourceSelect.addEventListener('change', handleBankLinkChange);
        destSelect.addEventListener('change', handleBankLinkChange);

        // Optional: Update hidden input if user manually changes direction (only when no bank is selected)
        directionDisplaySelect.addEventListener('change', function() {
             if (!this.disabled) { // Only react if it's enabled
                 directionHiddenInput.value = this.value;
             }
        });

        // Run once on load to set initial state based on potentially pre-filled values
        handleBankLinkChange({ target: sourceSelect });
         // If destination was pre-selected, run again for it (in case source was also empty)
         if (destSelect.value !== '') {
             handleBankLinkChange({ target: destSelect });
         }
    }

    // Bootstrap Validation Activation
    const form = document.getElementById('payment-form');
    if(form) {
         form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                 event.preventDefault();
                 event.stopPropagation();
            }
            form.classList.add('was-validated');
         }, false);
    }

    // --- Logic for Payment Method Details ---
    const paymentMethodSelect = document.getElementById('payment_method');
    const detailsContainer = document.getElementById('payment-method-details-container');
    const detailSections = detailsContainer ? detailsContainer.querySelectorAll('.payment-details-section') : [];
    // Also get bank account section if it needs toggling based on method
    // const bankAccountSection = document.getElementById('bank-account-link-section'); // Assuming you add this ID

    function togglePaymentDetails(selectedMethod) {
        if (!detailsContainer || detailSections.length === 0) return;

        // Hide all detail sections first
        detailSections.forEach(section => {
            section.style.display = 'none';
            // Optional: Disable inputs within hidden sections to prevent submission?
            // section.querySelectorAll('input, select, textarea').forEach(input => input.disabled = true);
        });

        // Show the relevant section(s)
        let sectionToShowId = '';
        switch (selectedMethod) {
            case 'cash':
                sectionToShowId = 'details-cash';
                break;
            case 'barter':
                sectionToShowId = 'details-barter';
                break;
            case 'bank_slip':
                sectionToShowId = 'details-bank_slip';
                // Maybe show bank account link section too?
                break;
            case 'mobile_transfer':
            case 'internet_transfer': // Both use the same section
                sectionToShowId = 'details-mobile_transfer';
                // Also show the placeholder for internet_transfer to technically exist
                const internetSection = document.getElementById('details-internet_transfer');
                if(internetSection) internetSection.style.display = 'block'; // Show (empty) internet_transfer section too
                // Maybe show bank account link section too?
                break;
            case 'atm':
                sectionToShowId = 'details-atm';
                // Maybe show bank account link section too?
                break;
            case 'pos':
                sectionToShowId = 'details-pos';
                // Maybe show bank account link section too?
                break;
            case 'cheque':
                sectionToShowId = 'details-cheque';
                // Maybe show bank account link section too?
                break;
            case 'clearing_account':
                sectionToShowId = 'details-clearing_account'; // Show empty section for consistency
                break;
            // Add cases for other methods if needed
        }

        const sectionToShow = document.getElementById(sectionToShowId);
        if (sectionToShow) {
            sectionToShow.style.display = 'block';
            // Optional: Enable inputs within the shown section
            // sectionToShow.querySelectorAll('input, select, textarea').forEach(input => input.disabled = false);
        }

        // Optional: Show/Hide Bank Account link section based on method
        /*
        const needsBankAccount = ['bank_slip', 'mobile_transfer', 'internet_transfer', 'atm', 'pos', 'cheque'].includes(selectedMethod);
        if (bankAccountSection) {
             bankAccountSection.style.display = needsBankAccount ? 'block' : 'none';
             // Reset bank account selects if hidden?
             if (!needsBankAccount) {
                 sourceSelect.value = '';
                 destSelect.value = '';
                 sourceSelect.dispatchEvent(new Event('change')); // Trigger update
             }
        }
        */
    }

    if (paymentMethodSelect) {
        // Add event listener
        paymentMethodSelect.addEventListener('change', (event) => {
            togglePaymentDetails(event.target.value);
        });

        // Call once on load to set initial state
        togglePaymentDetails(paymentMethodSelect.value);
    }

    // Initialize Datepicker for fields WITHOUT time
     if (typeof jalaliDatepicker !== 'undefined') {
        jalaliDatepicker.startWatch({
            selector: '.jalali-datepicker-nodate', // Use the new class
            time: false, // Set time to false
            format: 'Y/m/d', // Format without time
            persianDigits: true,
            showCloseBtn: true,
            showTodayBtn: true,
            autoClose: true
        });
    } else { console.error("Jalali Datepicker not found for no-date fields."); }

    // Initialize Datepicker for fields WITH time
    if (typeof jalaliDatepicker !== 'undefined') {
        jalaliDatepicker.startWatch({
            selector: '.jalali-datepicker', // Target the main date field
            time: true, // Enable time selection
            format: 'Y/m/d H:i:s', // Match the PHP output format
            persianDigits: true,
            showCloseBtn: true,
            showTodayBtn: true,
            autoClose: true // Ensure it closes automatically
        });
    } else { console.error("Jalali Datepicker not found for date-time fields."); }

});
</script>