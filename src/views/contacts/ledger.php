<?php
use App\Utils\Helper;
use Morilog\Jalali\Jalalian;

// استخراج متغیرها از $viewData
$pageTitle = $viewData['page_title'] ?? 'کارت حساب';
$contactInfo = $viewData['contact_info'] ?? null;
$ledgerEntries = $viewData['ledger_entries'] ?? [];
$totalRialBalance = (float)($viewData['total_rial_balance'] ?? 0.0);
$totalWeightBalance = (float)($viewData['total_weight_balance'] ?? 0.0);
$startRialBalance = (float)($viewData['start_rial_balance'] ?? 0.0);
$startWeightBalance = (float)($viewData['start_weight_balance'] ?? 0.0);
$filters = $viewData['filters'] ?? ['start_date_jalali'=>'', 'end_date_jalali'=>''];
$baseUrl = $viewData['baseUrl'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0 text-primary fw-bold"><?php echo Helper::escapeHtml($pageTitle); ?></h4>
    <div class="no-print">
        <a href="<?php echo $baseUrl; ?>/app/contacts" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> بازگشت به لیست
        </a>
        <button class="btn btn-sm btn-success" onclick="window.print();">
            <i class="fas fa-print me-1"></i> چاپ کارت حساب
        </button>
    </div>
</div>

<!-- بخش خلاصه مانده کل -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-light">
            <div class="card-body text-center p-3">
                <h6 class="card-subtitle mb-2 text-muted">مانده کل ریالی</h6>
                <p class="card-text fs-5 fw-bold <?php echo $totalRialBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo Helper::formatRial(abs($totalRialBalance)); ?>
                </p>
                <span class="badge <?php echo $totalRialBalance > 0 ? 'bg-danger-soft' : ($totalRialBalance < 0 ? 'bg-success-soft' : 'bg-secondary-soft'); ?>">
                    <?php echo ($totalRialBalance > 0) ? 'بدهکار' : (($totalRialBalance < 0) ? 'بستانکار' : 'بی‌حساب'); ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-light">
             <div class="card-body text-center p-3">
                <h6 class="card-subtitle mb-2 text-muted">مانده کل وزنی (عیار ۷۵۰)</h6>
                <p class="card-text fs-5 fw-bold <?php echo $totalWeightBalance < 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo Helper::formatPersianNumber(abs($totalWeightBalance), 3); ?> <small class="text-muted">گرم</small>
                </p>
                <span class="badge <?php echo $totalWeightBalance < 0 ? 'bg-danger-soft' : ($totalWeightBalance > 0 ? 'bg-success-soft' : 'bg-secondary-soft'); ?>">
                     <?php echo ($totalWeightBalance < 0) ? 'بدهکار' : (($totalWeightBalance > 0) ? 'بستانکار' : 'بی‌حساب'); ?>
                </span>
            </div>
        </div>
    </div>
</div>
  <div class="col-md-4">
        <div class="card shadow-sm border-light">
             <div class="card-body text-center p-3">
                <h6 class="card-subtitle mb-2 text-muted">مانده کل تعدادی</h6>
                <p class="card-text fs-5 fw-bold <?php echo $totalCountableBalance < 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo Helper::formatPersianNumber(abs($totalCountableBalance), 0); ?> <small class="text-muted">عدد</small>
                </p>
                <span class="badge <?php echo $totalCountableBalance < 0 ? 'bg-danger-soft' : ($totalCountableBalance > 0 ? 'bg-success-soft' : 'bg-secondary-soft'); ?>">
                     <?php echo ($totalCountableBalance < 0) ? 'بدهکار' : (($totalCountableBalance > 0) ? 'بستانکار' : 'بی‌حساب'); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- فرم فیلتر تاریخ -->
<div class="card shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-3 align-items-center">
            <div class="col-md-5">
                <label for="start_date" class="form-label">از تاریخ</label>
                <input type="text" class="form-control form-control-sm" id="start_date" name="start_date" value="<?php echo Helper::escapeHtml($filters['start_date_jalali'] ?? ''); ?>" placeholder="مثال: ۱۴۰۴/۰۱/۱۵">
            </div>
            <div class="col-md-5">
                <label for="end_date" class="form-label">تا تاریخ</label>
                <input type="text" class="form-control form-control-sm" id="end_date" name="end_date" value="<?php echo Helper::escapeHtml($filters['end_date_jalali'] ?? ''); ?>" placeholder="مثال: ۱۴۰۴/۰۵/۲۲">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100">اعمال فیلتر</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light"><h5 class="mb-0">گردش حساب یکپارچه</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover table-striped align-middle mb-0 fs-7">
                <thead class="table-light sticky-top">
                    <tr>
                        <th rowspan="2" class="text-center align-middle" style="min-width: 110px;">تاریخ</th>
                        <th rowspan="2" class="align-middle" style="min-width: 250px;">شرح عملیات</th>
                        <th colspan="2" class="text-center border-start">گردش ریالی</th>
                        <th colspan="2" class="text-center border-start">گردش <br>وزنی/تعدادی</th>
                        <th colspan="2" class="text-center border-start">مانده نهایی</th>
                    </tr>
                    <tr class="small">
                        <th class="text-center text-danger">(بدهکار)</th><th class="text-center text-success">(بستانکار)</th>
                        <th class="text-center text-danger border-start">(بدهکار)</th><th class="text-center text-success">(بستانکار)</th>
                        <th class="text-center border-start">ریالی</th><th class="text-center">وزنی</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-secondary fw-bold">
                        <td colspan="2">مانده اول دوره</td>
                        <td colspan="4" class="text-center border-start">-</td>
                        <td class="text-center border-start <?php echo $startRialBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo Helper::formatRial(abs($startRialBalance)); ?>
                        </td>
                        <td class="text-center <?php echo $startWeightBalance < 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo Helper::formatPersianNumber(abs($startWeightBalance), 3); ?>
                        </td>
                    </tr>
                      <?php
                        $runningRialBalance = $startRialBalance;
                        $runningWeightBalance = $startWeightBalance;
                        foreach ($ledgerEntries as $entry):
                            // ================== اصلاح نهایی: استفاده از متغیرهای محاسبه ==================
                            // مانده ریالی مثل قبل محاسبه می‌شود
                            $runningRialBalance += ((float)($entry['debit_rial'] ?? 0) - (float)($entry['credit_rial'] ?? 0));
                            // **مانده وزنی فقط با متغیرهای مخصوص محاسبه، آپدیت می‌شود**
                            $runningWeightBalance += ((float)($entry['credit_weight_750_calc'] ?? 0) - (float)($entry['debit_weight_750_calc'] ?? 0));
                            // =========================================================================
                            ?>
                          <tr>
                        <td class="small text-center"><?php echo Helper::formatPersianDateTime($entry['entry_date'], 'Y/m/d H:i'); ?></td>
                        <td class="small">
                            <?php
                                // ساخت شرح کامل و پویا بر اساس نوع رویداد
                                $description = '';
                                switch ($entry['entry_type']) {
                                    case 'transaction_item':
                                        $prefix = ($entry['transaction_type'] == 'sell') ? 'فروش' : 'خرید';
                                        $description = "<strong>{$prefix}: " . Helper::escapeHtml($entry['product_name']) . "</strong>";
                                        $details = [];
                                        if (!empty($entry['tag_number'])) $details[] = "انگ: " . Helper::escapeHtml($entry['tag_number']);
                                        if (!empty($entry['coin_year'])) $details[] = "سال: " . Helper::escapeHtml($entry['coin_year']);
                                        if ((float)($entry['weight_grams'] ?? 0) > 0) $details[] = "وزن: " . Helper::formatPersianNumber($entry['weight_grams'], 3) . "g";
                                        if ((int)($entry['carat'] ?? 0) > 0) $details[] = "عیار: " . Helper::formatPersianNumber($entry['carat']);
                                        if ((int)($entry['quantity'] ?? 0) > 0) $details[] = "تعداد: " . Helper::formatPersianNumber($entry['quantity']);
                                        if ((float)($entry['ajrat_rials'] ?? 0) > 0) $details[] = "اجرت: " . Helper::formatRial($entry['ajrat_rials']);
                                        if(!empty($details)) $description .= "<br><span class='text-muted'>" . implode(' | ', $details) . "</span>";
                                        break;
                                    case 'payment':
                                        $prefix = ($entry['debit_rial'] ?? 0) > 0 ? 'دریافت از مغازه' : 'پرداخت به مغازه';
                                        $description = "<strong>" . Helper::escapeHtml($prefix) . "</strong>";
                                        if(!empty($entry['related_transaction_id'])) $description .= "<br><span class='text-muted'>بابت تسویه معامله #" . $entry['related_transaction_id'] . "</span>";
                                        elseif(!empty($entry['notes'])) $description .= "<br><span class='text-muted'>" . Helper::escapeHtml($entry['notes']) . "</span>";
                                        break;

                                    case 'physical_settlement':
                                        $prefix = ($entry['direction'] == 'outflow') ? 'تسویه فیزیکی (تحویل به مغازه)' : 'تسویه فیزیکی (دریافت از مغازه)';
                                        $description = "<strong>{$prefix}: ". Helper::escapeHtml($entry['product_name']) ."</strong>";
                                        $details = "وزن: " . Helper::formatPersianNumber($entry['weight_scale'], 3) . "g | عیار: " . Helper::formatPersianNumber($entry['carat']);
                                        $description .= "<br><span class='text-muted'>{$details}</span>";
                                        break;
                                }
                                echo $description;
                            ?>
                        </td>
                        <td class="text-center text-danger border-start"><?php echo ($entry['debit_rial'] ?? 0) > 0 ? Helper::formatRial($entry['debit_rial']) : '-'; ?></td>
                        <td class="text-center text-success"><?php echo ($entry['credit_rial'] ?? 0) > 0 ? Helper::formatRial($entry['credit_rial']) : '-'; ?></td>
                          <!-- ================== اصلاح نهایی: استفاده از متغیرهای نمایش ================== -->
                        <?php
                            // از فلگ is_countable که در ریپازیتوری ساختیم استفاده می‌کنیم
                            $isCountable = !empty($entry['is_countable']);
                            $debit_display = (float)($entry['display_debit'] ?? 0);
                            $credit_display = (float)($entry['display_credit'] ?? 0);
                        ?>
                        <td class="text-center text-danger border-start fw-bold">
                            <?php echo $debit_display > 0 ? Helper::formatPersianNumber($debit_display, $isCountable ? 0 : 3) : '-'; ?>
                        </td>
                        <td class="text-center text-success fw-bold">
                            <?php echo $credit_display > 0 ? Helper::formatPersianNumber($credit_display, $isCountable ? 0 : 3) : '-'; ?>
                        </td>
                        <!-- ======================================================================= -->
                        <td class="text-center border-start <?php echo $runningRialBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo Helper::formatRial(abs($runningRialBalance)); ?>
                        </td>
                        <td class="text-center <?php echo $runningWeightBalance < 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo Helper::formatPersianNumber(abs($runningWeightBalance), 3); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                 
                    <tr class="table-light fw-bold fs-6">
                         <td colspan="2">مانده نهایی دوره</td>
                         <td colspan="4" class="text-center border-start">-</td>
                         <td class="text-center border-start">
                             <div class="<?php echo $runningRialBalance > 0 ? 'text-danger' : ($runningRialBalance < 0 ? 'text-success' : ''); ?>">
                                <?php echo Helper::formatRial(abs($runningRialBalance)); ?>
                             </div>
                             <small class="d-block">(<?php echo ($runningRialBalance > 0) ? 'بدهکار' : (($runningRialBalance < 0) ? 'بستانکار' : 'بی‌حساب'); ?>)</small>
                         </td>
                         <td class="text-center">
                             <div class="<?php echo $runningWeightBalance < 0 ? 'text-danger' : ($runningWeightBalance > 0 ? 'text-success' : ''); ?>">
                                <?php echo Helper::formatPersianNumber(abs($runningWeightBalance), 3); ?>
                             </div>
                             <small class="d-block">(<?php echo ($runningWeightBalance < 0) ? 'بدهکار' : (($runningWeightBalance > 0) ? 'بستانکار' : 'بی‌حساب'); ?>)</small>
                         </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- بخش مانده به حروف -->
<div class="card shadow-sm mt-4 no-print">
    <div class="card-body text-center text-muted medium p-3">
        <p class="mb-1">
            <?php echo Helper::escapeHtml($contactInfo['name'] ?? 'طرف حساب'); ?> عزیز، شما تا تاریخ
            <?php echo Helper::formatPersianNumber(Jalalian::now()->format('l j F Y، ساعت H:i')); ?>
            مبلغ
            <strong class="<?php echo $runningRialBalance < 0 ? 'text-success' : ($runningRialBalance > 0 ? 'text-danger' : ''); ?>">
                <?php echo Helper::formatRial(abs($runningRialBalance)); ?> ریال
            </strong>
            <?php echo ($runningRialBalance < 0) ? 'بستانکار' : (($runningRialBalance > 0) ? 'بدهکار' : 'بی‌حساب'); ?>
            و مقدار
            <strong class="<?php echo $runningWeightBalance > 0 ? 'text-success' : ($runningWeightBalance < 0 ? 'text-danger' : ''); ?>">
                <?php echo Helper::formatPersianNumber(abs($runningWeightBalance), 3); ?> گرم (عیار ۷۵۰)
            </strong>
            <?php echo ($runningWeightBalance > 0) ? 'بستانکار' : (($runningWeightBalance < 0) ? 'بدهکار' : 'بی‌حساب'); ?>
            هستید.
        </p>
     </div>
</div>

<style>
/* استایل‌های مخصوص چاپ */
@media print {
    .no-print { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ccc; }
    .table-responsive { overflow: visible; }
    .table { font-size: 10px; } /* کوچک کردن فونت برای چاپ */
    thead.sticky-top { position: static; }
}
.bg-success-soft { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
.bg-danger-soft { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
.bg-secondary-soft { background-color: rgba(108, 117, 125, 0.1); color: #6c757d; }
.fs-7 { font-size: .9rem; }
</style>