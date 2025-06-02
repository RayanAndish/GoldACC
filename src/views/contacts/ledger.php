<?php
/**
 * Template: src/views/contacts/ledger.php
 * Displays the Contact Ledger (Transaction and Payment History).
 * Receives data via $viewData from ContactController::showLedger.
 */

use App\Utils\Helper; // Use the Helper class
use Morilog\Jalali\Jalalian; // Add Jalalian namespace

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'کارت حساب مخاطب';
$contactInfo = $viewData['contact_info'] ?? null;
$contactId = $viewData['contact_id'] ?? $contactInfo['id'] ?? null;
$contactName = $contactInfo['name'] ?? 'ناشناس';
$ledgerEntries = $viewData['ledger_entries'] ?? []; // Combined transactions and payments
$startBalancePeriod = (float)($viewData['start_balance_period'] ?? 0.0); // Opening balance for the filtered period
$totalBalance = (float)($viewData['total_balance'] ?? 0.0); // Overall total balance
$filters = $viewData['filters'] ?? ['start_date_jalali'=>'', 'end_date_jalali'=>''];
$errorMessage = $viewData['error_msg'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';

$pageBaseUrl = $baseUrl . '/app/contacts/ledger/' . ($contactId ?? '');

?>

<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    <?php if ($contactInfo && $contactId): ?>
    <span class="text-muted small">
        شناسه: <?php echo (int)$contactId; ?> |
        نوع: <?php echo Helper::getContactTypeFarsi($contactInfo['type'] ?? null); ?> |
        مانده کل فعلی: <strong class="<?php echo $totalBalance == 0 ? 'text-secondary' : ($totalBalance < 0 ? 'text-success' : 'text-danger'); ?>">
            <?php echo Helper::formatRial(abs($totalBalance)); ?>
            (<?php echo ($totalBalance < 0) ? 'بس' : (($totalBalance > 0) ? 'بد' : '-'); ?>)
        </strong>
    </span>
    <?php endif; ?>
</div>

<?php // --- Display Messages --- ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger">
        <?php echo Helper::escapeHtml($errorMessage); ?>
    </div>
<?php endif; ?>

<?php // --- Date Filter Form --- ?>
<?php if ($contactId): ?>
<form method="GET" action="<?php echo $pageBaseUrl; ?>" class="mb-4 p-3 border rounded bg-light shadow-sm">
    <?php /* ID is part of the action URL */ ?>
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="start_date" class="form-label small mb-1">از تاریخ:</label>
            <input type="text" class="form-control form-control-sm jalali-datepicker" id="start_date" name="start_date" value="<?php echo Helper::escapeHtml($filters['start_date_jalali']); ?>" placeholder="مثال: ۱۴۰۳/۰۱/۰۱">
        </div>
        <div class="col-md-4">
            <label for="end_date" class="form-label small mb-1">تا تاریخ:</label>
            <input type="text" class="form-control form-control-sm jalali-datepicker" id="end_date" name="end_date" value="<?php echo Helper::escapeHtml($filters['end_date_jalali']); ?>" placeholder="مثال: ۱۴۰۳/۰۱/۳۱">
        </div>
        <div class="col-md-auto">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>اعمال</button>
        </div>
        <?php if (!empty($filters['start_date_jalali']) || !empty($filters['end_date_jalali'])): ?>
        <div class="col-md-auto">
             <a href="<?php echo $pageBaseUrl; ?>" class="btn btn-sm btn-secondary w-100" title="پاک کردن فیلترها"><i class="fas fa-times"></i></a>
        </div>
        <?php endif; ?>
        <div class="col-md-auto ms-md-auto"> <?php // Push back button to end on larger screens ?>
             <a href="<?php echo $baseUrl; ?>/app/contacts" class="btn btn-sm btn-outline-secondary w-100">
                <i class="fas fa-arrow-left me-1"></i> بازگشت به لیست
             </a>
        </div>
    </div>
</form>
<?php endif; ?>


<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">گردش حساب ریالی</h5>
         <?php if (!empty($filters['start_date_jalali']) || !empty($filters['end_date_jalali'])): ?>
             <small class="text-muted">
                 <?php echo !empty($filters['start_date_jalali']) ? 'از: ' . Helper::escapeHtml($filters['start_date_jalali']) : ''; ?>
                 <?php echo (!empty($filters['start_date_jalali']) && !empty($filters['end_date_jalali'])) ? ' تا ' : ''; ?>
                 <?php echo !empty($filters['end_date_jalali']) ? Helper::escapeHtml($filters['end_date_jalali']) : ''; ?>
             </small>
         <?php endif; ?>
    </div>
    <div class="card-body p-0 <?php echo (empty($ledgerEntries) && empty($errorMessage)) ? 'p-md-4' : 'p-md-0'; ?>">
        <?php if (!$errorMessage && $contactId): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="text-nowrap px-3" style="width: 120px;">تاریخ</th>
                            <th>شرح عملیات</th>
                            <th class="text-center text-nowrap">بدهکار<br><small>(ریال)</small></th>
                            <th class="text-center text-nowrap">بستانکار<br><small>(ریال)</small></th>
                            <th class="text-center text-nowrap" style="width: 150px;">مانده<br><small>(ریال)</small></th>
                            <th class="text-center text-nowrap" style="width: 80px;">تشخیص</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php // --- Opening Balance Row ---
                            $openingBalanceDisplay = $startBalancePeriod;
                            $ob_class = ($openingBalanceDisplay == 0) ? 'text-secondary' : ($openingBalanceDisplay < 0 ? 'text-success' : 'text-danger');
                            $ob_status = ($openingBalanceDisplay < -0.01) ? 'بس' : (($openingBalanceDisplay > 0.01) ? 'بد' : '-');
                        ?>
                        <tr class="table-secondary">
                            <td colspan="2" class="text-start fw-bold px-3">
                                <i class="fas fa-flag me-1 text-muted"></i>
                                مانده اول دوره <?php echo ($filters['start_date_jalali'] ? ' (' . Helper::escapeHtml($filters['start_date_jalali']) . ')' : ''); ?>
                            </td>
                            <td class="text-center">-</td><td class="text-center">-</td> <?php /* Debit/Credit */ ?>
                            <td class="text-center fw-bold number-fa <?php echo $ob_class; ?>">
                                <?php echo Helper::formatNumber(abs($openingBalanceDisplay), 0); ?>
                            </td>
                            <td class="text-center small fw-bold <?php echo $ob_class; ?>">
                                <?php echo $ob_status; ?>
                            </td>
                        </tr>

                        <?php // --- Ledger Entries Loop ---
                            $currentRunningBalance = $openingBalanceDisplay;
                            foreach ($ledgerEntries as $entry):
                                // Debit: Customer owes us MORE (Our Sale, Their Payment Received by Us)
                                // Credit: Customer owes us LESS (Our Purchase, Our Payment Sent to Them)
                                $debit = (float)($entry['debit'] ?? 0);
                                $credit = (float)($entry['credit'] ?? 0);
                                $change = $debit - $credit; // Change in customer's debt TO US
                                $currentRunningBalance += $change;

                                $balance_class = ($currentRunningBalance == 0) ? 'text-secondary' : ($currentRunningBalance < 0 ? 'text-success' : 'text-danger');
                                $balance_status = ($currentRunningBalance < -0.01) ? 'بس' : (($currentRunningBalance > 0.01) ? 'بد' : '-');
                        ?>
                            <tr>
                                <td class="text-nowrap small px-3">
                                     <?php echo $entry['entry_date'] ? Jalalian::fromFormat('Y-m-d H:i:s', $entry['entry_date'])->format('Y/m/d H:i') : '-'; ?>
                                 </td>
                                <td class="small">
                                    <?php echo Helper::escapeHtml($entry['description'] ?? '-'); ?>
                                     <?php // Link to related transaction or payment ?>
                                     <?php if(($entry['entry_type'] ?? '') === 'transaction' && !empty($entry['entry_id'])): ?>
                                         <a href="<?php echo $baseUrl; ?>/app/transactions/edit/<?php echo (int)$entry['entry_id']; ?>" target="_blank" class="ms-1 text-muted link-secondary" title="مشاهده معامله مرتبط"><i class="fas fa-exchange-alt fa-xs"></i></a>
                                     <?php elseif(($entry['entry_type'] ?? '') === 'payment' && !empty($entry['entry_id'])): ?>
                                         <a href="<?php echo $baseUrl; ?>/app/payments/edit/<?php echo (int)$entry['entry_id']; ?>" target="_blank" class="ms-1 text-muted link-secondary" title="مشاهده پرداخت مرتبط"><i class="fas fa-receipt fa-xs"></i></a>
                                     <?php endif; ?>
                                 </td>
                                <td class="text-center text-danger small number-fa"><?php echo ($debit != 0) ? Helper::formatNumber($debit, 0) : '-'; ?></td>
                                <td class="text-center text-success small number-fa"><?php echo ($credit != 0) ? Helper::formatNumber($credit, 0) : '-'; ?></td>
                                <td class="text-center fw-bold small number-fa <?php echo $balance_class; ?>">
                                    <?php echo Helper::formatNumber(abs($currentRunningBalance), 0); // Show absolute value ?>
                                </td>
                                <td class="text-center small fw-bold <?php echo $balance_class; ?>"><?php echo $balance_status; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php // --- Footer with Final Balance of the Period ---
                            $final_period_balance = $currentRunningBalance ?? $startBalancePeriod;
                            $final_period_class = ($final_period_balance == 0) ? 'text-secondary' : ($final_period_balance < 0 ? 'text-success' : 'text-danger');
                            $final_period_status = ($final_period_balance < -0.01) ? 'بس' : (($final_period_balance > 0.01) ? 'بد' : '-');
                    ?>
                    <tfoot class="table-light fw-bold">
                         <tr>
                             <td colspan="2" class="text-start px-3">
                                 مانده نهایی دوره <?php echo ($filters['end_date_jalali'] ? ' (تا ' . Helper::escapeHtml($filters['end_date_jalali']) . ')' : ''); ?>
                             </td>
                             <td class="text-center">-</td><td class="text-center">-</td> <?php /* Debit/Credit */ ?>
                             <td class="text-center <?php echo $final_period_class; ?>">
                                 <?php echo Helper::formatNumber(abs($final_period_balance), 0); ?>
                             </td>
                             <td class="text-center <?php echo $final_period_class; ?>"><?php echo $final_period_status; ?></td>
                         </tr>
                     </tfoot>
                </table>
            </div>
         <?php elseif (!$errorMessage): ?>
            <p class="text-center text-muted py-4 mb-0">هیچ گردش حسابی برای این مخاطب در بازه زمانی انتخاب شده یافت نشد.</p>
      <?php endif; ?>
    </div>
</div>

<?php // JS for datepicker and tooltips ?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/jalalidatepicker.min.css" />
<script src="<?php echo $baseUrl; ?>/js/jalalidatepicker.min.js"></script>
<script>
    jalaliDatepicker.startWatch({ selector: '.jalali-datepicker', showTodayBtn: true, showCloseBtn: true, format: 'Y/m/d' });
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
</script>