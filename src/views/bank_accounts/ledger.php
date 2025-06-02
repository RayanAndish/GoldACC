<?php
/**
 * Template: src/views/bank_accounts/ledger.php
 * Displays the Bank Account Ledger (Transaction History).
 * Calculates running balance within the view.
 * Receives data via $viewData array from BankAccountController::showLedger.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'گردش حساب بانکی';
$accountInfo = $viewData['account_info'] ?? null; // Basic account info
$accountId = $viewData['account_id'] ?? $accountInfo['id'] ?? null;
$accountName = $accountInfo['account_name'] ?? 'ناشناس';
$ledgerEntries = $viewData['ledger_entries'] ?? []; // Array of transaction records
$startBalancePeriod = (float)($viewData['start_balance_period'] ?? 0.0); // Starting balance for the filtered period
$currentTotalBalance = (float)($viewData['current_total_balance'] ?? 0.0); // Overall current balance
$filters = $viewData['filters'] ?? ['start_date_jalali'=>'', 'end_date_jalali'=>''];
$errorMessage = $viewData['error_msg'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';

// Base URL for this page for filter form and links
$pageBaseUrl = $baseUrl . '/app/bank-accounts/ledger/' . ($accountId ?? '');

?>

<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    <?php if ($accountInfo && $accountId): ?>
         <span class="text-muted small">
             شناسه: <?php echo (int)$accountId; ?> |
             موجودی کل فعلی: <strong class="<?php echo $currentTotalBalance < 0 ? 'text-danger' : ''; ?>"><?php echo Helper::formatRial($currentTotalBalance); ?></strong>
         </span>
     <?php endif; ?>
</div>

<?php // --- Display Messages --- ?>
<?php /* Success messages are usually shown on the list page after redirect */ ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger">
        <?php echo Helper::escapeHtml($errorMessage); ?>
    </div>
<?php endif; ?>

<?php // --- Date Filter Form --- ?>
<?php if ($accountId): // Show filter only if account ID is valid ?>
<form method="GET" action="<?php echo $pageBaseUrl; ?>" class="mb-4 p-3 border rounded bg-light shadow-sm">
    <?php /* No hidden page/id needed as they are in the action URL */ ?>
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="start_date" class="form-label small mb-1">از تاریخ:</label>
            <input type="text"
                   class="form-control form-control-sm jalali-datepicker" <?php /* Add datepicker class */?>
                   id="start_date" name="start_date"
                   value="<?php echo Helper::escapeHtml($filters['start_date_jalali']); ?>"
                   placeholder="مثال: ۱۴۰۳/۰۱/۰۱">
        </div>
        <div class="col-md-4">
             <label for="end_date" class="form-label small mb-1">تا تاریخ:</label>
            <input type="text"
                   class="form-control form-control-sm jalali-datepicker" <?php /* Add datepicker class */?>
                   id="end_date" name="end_date"
                   value="<?php echo Helper::escapeHtml($filters['end_date_jalali']); ?>"
                   placeholder="مثال: ۱۴۰۳/۰۱/۳۱">
        </div>
         <div class="col-md-auto">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>اعمال فیلتر</button>
         </div>
         <?php if (!empty($filters['start_date_jalali']) || !empty($filters['end_date_jalali'])): ?>
         <div class="col-md-auto">
             <a href="<?php echo $pageBaseUrl; ?>" class="btn btn-sm btn-secondary w-100" title="پاک کردن فیلترها">
                <i class="fas fa-times me-1 d-none d-sm-inline"></i>پاک کردن
             </a>
         </div>
         <?php endif; ?>
         <?php /* Optional Print Button - needs specific route/controller action
         <div class="col-md-auto ms-auto"> <?php // Push to end ?>
             <a href="<?php // echo $pageBaseUrl . '/print?' . http_build_query($filters); ?>" target="_blank" class="btn btn-sm btn-outline-danger w-100" title="چاپ گردش حساب"><i class="fas fa-print"></i></a>
         </div>
         */ ?>
    </div>
</form>
<?php endif; ?>


<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">گردش حساب بانکی</h5>
         <?php // Optional: Add date range display ?>
         <?php if (!empty($filters['start_date_jalali']) || !empty($filters['end_date_jalali'])): ?>
             <small class="text-muted">
                 <?php echo !empty($filters['start_date_jalali']) ? 'از: ' . Helper::escapeHtml($filters['start_date_jalali']) : ''; ?>
                 <?php echo (!empty($filters['start_date_jalali']) && !empty($filters['end_date_jalali'])) ? ' - ' : ''; ?>
                 <?php echo !empty($filters['end_date_jalali']) ? 'تا: ' . Helper::escapeHtml($filters['end_date_jalali']) : ''; ?>
             </small>
         <?php endif; ?>
    </div>
    <div class="card-body p-0 <?php echo empty($ledgerEntries) ? 'p-md-4' : 'p-md-0'; ?>">
        <?php if (!$errorMessage && $accountId): ?>
            <?php if (!empty($ledgerEntries)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap px-3">تاریخ / زمان</th>
                                <th>شرح عملیات</th>
                                <th class="text-center text-nowrap">واریز <small>(ریال)</small></th>
                                <th class="text-center text-nowrap">برداشت <small>(ریال)</small></th>
                                <th class="text-center text-nowrap">مانده <small>(ریال)</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php // --- Opening Balance Row --- ?>
                            <tr class="table-secondary">
                                <td colspan="2" class="text-start fw-bold px-3">
                                     <i class="fas fa-flag me-1 text-muted"></i>
                                     مانده <?php echo ($filters['start_date_jalali'] ? ' در ابتدای ' . Helper::escapeHtml($filters['start_date_jalali']) : 'اولیه حساب'); ?>
                                </td>
                                <td class="text-center">-</td> <?php /* Deposit */ ?>
                                <td class="text-center">-</td> <?php /* Withdrawal */ ?>
                                <td class="text-center fw-bold"><?php echo Helper::formatRial($startBalancePeriod); ?></td>
                            </tr>

                            <?php // --- Ledger Entries Loop ---
                                $currentRunningBalance = $startBalancePeriod; // Initialize running balance
                                foreach ($ledgerEntries as $entry):
                                    // Data is pre-formatted in controller, except running balance
                                    $amount = (float)($entry['amount'] ?? 0); // Get the raw amount
                                    $deposit = ($amount > 0) ? $amount : 0;
                                    $withdrawal = ($amount < 0) ? abs($amount) : 0;
                                    $currentRunningBalance += $amount; // Update running balance
                            ?>
                                <tr>
                                    <td class="text-nowrap small px-3">
                                         <?php echo $entry['transaction_date_persian'] ?? '-'; ?>
                                     </td>
                                    <td class="small">
                                        <?php echo $entry['description'] ?? '-'; // Already escaped ?>
                                        <?php // Optional: Link to related payment if ID exists ?>
                                        <?php if(!empty($entry['related_payment_id'])): ?>
                                            <a href="<?php echo $baseUrl; ?>/app/payments/edit/<?php echo (int)$entry['related_payment_id']; ?>"
                                                class="ms-1 link-secondary" data-bs-toggle="tooltip" title="مشاهده/ویرایش پرداخت مرتبط (#<?php echo (int)$entry['related_payment_id']; ?>)">
                                                <i class="fas fa-external-link-alt fa-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-success small number-fa"><?php echo ($deposit != 0) ? Helper::formatNumber($deposit, 0) : '-'; ?></td>
                                    <td class="text-center text-danger small number-fa"><?php echo ($withdrawal != 0) ? Helper::formatNumber($withdrawal, 0) : '-'; ?></td>
                                    <td class="text-center fw-bold small number-fa <?php echo $currentRunningBalance < 0 ? 'text-danger' : ''; ?>">
                                        <?php echo Helper::formatNumber($currentRunningBalance, 0); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                             <tr>
                                <td colspan="2" class="text-start px-3">
                                    مانده نهایی دوره
                                    <?php if (!empty($filters['end_date_jalali'])) echo '(تا انتهای ' . Helper::escapeHtml($filters['end_date_jalali']) . ')'; ?>
                                </td>
                                <td class="text-center">-</td><td class="text-center">-</td> <?php /* Deposit/Withdrawal */ ?>
                                <td class="text-center <?php echo $currentRunningBalance < 0 ? 'text-danger' : ''; ?>">
                                    <?php echo Helper::formatRial($currentRunningBalance); ?>
                                </td>
                             </tr>
                         </tfoot>
                    </table>
                </div>
             <?php elseif (!$errorMessage): // Show if no error and no entries ?>
                <p class="text-center text-muted py-4 mb-0">هیچ گردش حسابی برای این حساب در بازه زمانی انتخاب شده یافت نشد.</p>
             <?php endif; ?>
        <?php endif; // End check for valid ID and no load error ?>
    </div>
</div>

<?php
// Include JS for datepicker and tooltips if not global
// Needs jalali-date.js, jalali-date-bindings.js, bootstrap.bundle.min.js
?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/jalalidatepicker.min.css" />
<script src="<?php echo $baseUrl; ?>/js/jalalidatepicker.min.js"></script>
<script>
    // Initialize Jalali Datepickers
    jalaliDatepicker.startWatch({
        selector: '.jalali-datepicker',
        showTodayBtn: true,
        showCloseBtn: true,
        format: 'Y/m/d', // Format matching the backend expectation
        locale: 'fa'
    });

    // Initialize Bootstrap Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })
</script>