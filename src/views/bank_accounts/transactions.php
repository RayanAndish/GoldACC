<?php
/**
 * Template: src/views/bank_accounts/transactions.php
 * Displays the list of all Bank Transactions with filters and pagination.
 * Receives data via $viewData array from BankAccountController::listTransactions.
 * NOTE: This is a basic structure based on activity logs list. Controller needs implementation.
 */

use App\Utils\Helper; // Use the Helper class
use Morilog\Jalali\Jalalian; // Add Jalalian namespace

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'لیست تراکنش‌های بانکی';
$transactions = $viewData['transactions'] ?? []; // Array of bank transaction records
$bankAccounts = $viewData['bank_accounts_for_filter'] ?? []; // List of accounts for filter dropdown
$successMessage = $viewData['success_msg'] ?? null;
$errorMessage = $viewData['error_msg'] ?? null;
$filters = $viewData['filters'] ?? ['start_date_jalali'=>'', 'end_date_jalali'=>'', 'bank_account_id'=>null, 'type'=>null]; // Filters applied
$pagination = $viewData['pagination'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';

// Base URL for this page (including potential filters for pagination links)
$pageBaseUrl = $baseUrl . '/app/bank-accounts/transactions';
$queryStringParams = array_filter([ // Build query string from active filters
    'start_date' => $filters['start_date_jalali'],
    'end_date' => $filters['end_date_jalali'],
    'bank_account_id' => $filters['bank_account_id'],
    'type' => $filters['type'],
    // Add other filters like search term if implemented
]);
$queryString = !empty($queryStringParams) ? '?' . http_build_query($queryStringParams) : '';

?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    <?php /* Optional: Add button to record manual bank transaction? */ ?>
    <?php /* <a href="<?php echo $baseUrl; ?>/app/bank-transactions/add" class="btn btn-success btn-sm"> <i class="fas fa-plus me-1"></i> ثبت تراکنش دستی </a> */ ?>
</div>

<?php // --- Display Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show"> <?php echo Helper::escapeHtml($successMessage); ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show"> <?php echo Helper::escapeHtml($errorMessage); ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>


<?php // --- Filter Form --- ?>
<form method="GET" action="<?php echo $pageBaseUrl; ?>" class="mb-4 p-3 border rounded bg-light shadow-sm">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label for="bank_account_id_filter" class="form-label small mb-1">حساب بانکی:</label>
            <select class="form-select form-select-sm" id="bank_account_id_filter" name="bank_account_id">
                <option value="">همه حساب‌ها</option>
                <?php foreach ($bankAccounts as $acc): ?>
                    <option value="<?php echo (int)$acc['id']; ?>" <?php echo ($filters['bank_account_id'] == $acc['id']) ? 'selected' : ''; ?>>
                        <?php echo Helper::escapeHtml($acc['account_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
         <div class="col-md-2">
            <label for="type_filter" class="form-label small mb-1">نوع تراکنش:</label>
            <select class="form-select form-select-sm" id="type_filter" name="type">
                <option value="">همه انواع</option>
                <option value="deposit" <?php echo ($filters['type'] == 'deposit') ? 'selected' : ''; ?>>واریز</option>
                <option value="withdrawal" <?php echo ($filters['type'] == 'withdrawal') ? 'selected' : ''; ?>>برداشت</option>
                <?php // Add other types if they exist ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="start_date_filter" class="form-label small mb-1">از تاریخ:</label>
            <input type="text" class="form-control form-control-sm jalali-datepicker" id="start_date_filter" name="start_date" value="<?php echo Helper::escapeHtml($filters['start_date_jalali']); ?>">
        </div>
        <div class="col-md-3">
             <label for="end_date_filter" class="form-label small mb-1">تا تاریخ:</label>
            <input type="text" class="form-control form-control-sm jalali-datepicker" id="end_date_filter" name="end_date" value="<?php echo Helper::escapeHtml($filters['end_date_jalali']); ?>">
        </div>
         <div class="col-md-auto">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>اعمال</button>
         </div>
         <?php if (!empty($queryStringParams)): ?>
         <div class="col-md-auto">
             <a href="<?php echo $pageBaseUrl; ?>" class="btn btn-sm btn-secondary w-100" title="پاک کردن فیلترها">
                <i class="fas fa-times"></i>
             </a>
         </div>
         <?php endif; ?>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">لیست تراکنش‌ها</h5>
         <?php if ($pagination && $pagination['totalRecords'] > 0): ?>
            <small class="text-muted">
                نمایش <?php echo (($pagination['currentPage']-1) * $pagination['limit']) + 1; ?>
                - <?php echo min($pagination['totalRecords'], $pagination['currentPage'] * $pagination['limit']); ?>
                از <?php echo $pagination['totalRecords']; ?>
            </small>
        <?php endif; ?>
    </div>
    <div class="card-body p-0 <?php echo empty($transactions) ? 'p-md-4' : 'p-md-0'; ?>">
        <?php if (!empty($transactions)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                         <tr>
                            <th scope="col">تاریخ / زمان</th>
                            <th scope="col">حساب بانکی</th>
                            <th scope="col">نوع</th>
                            <th scope="col">شرح</th>
                            <th scope="col" class="text-center">مبلغ (ریال)</th>
                            <th scope="col" class="text-center">پرداخت مرتبط</th> <?php /* Optional */ ?>
                        </tr>
                    </thead>
                    <tbody>
                         <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="text-nowrap small">
                                    <?php echo $tx['transaction_date'] ? Jalalian::fromFormat('Y-m-d H:i:s', $tx['transaction_date'])->format('Y/m/d H:i') : '-'; ?>
                                </td>
                                <td><?php echo Helper::escapeHtml($tx['account_name'] ?? 'N/A'); // Assume JOIN in repo ?></td>
                                <td><?php echo ($tx['type'] ?? '') === 'deposit' ? 'واریز' : (($tx['type'] ?? '') === 'withdrawal' ? 'برداشت' : Helper::escapeHtml($tx['type'] ?? '')); ?></td>
                                <td class="small"><?php echo Helper::escapeHtml($tx['description'] ?? ''); ?></td>
                                <td class="text-center fw-bold number-fa <?php echo ($tx['amount'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo Helper::formatRial(abs($tx['amount'] ?? 0), false); // Show absolute value, sign indicated by color/type ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($tx['related_payment_id'])): ?>
                                        <a href="<?php echo $baseUrl; ?>/app/payments/edit/<?php echo (int)$tx['related_payment_id']; ?>"
                                           class="btn btn-sm btn-outline-secondary btn-action" data-bs-toggle="tooltip" title="مشاهده پرداخت مرتبط (#<?php echo (int)$tx['related_payment_id']; ?>)">
                                            <i class="fas fa-link"></i>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php // --- Pagination Links --- ?>
            <?php if ($pagination && $pagination['totalPages'] > 1): ?>
                 <?php $baseUrlForPagination = $pageBaseUrl . $queryString;
                 include __DIR__ . '/../partials/pagination.php'; ?>
            <?php endif; ?>

        <?php elseif (empty($errorMessage)): ?>
            <p class="text-center text-muted p-4 mb-0">
                 <?php echo !empty($queryStringParams) ? 'هیچ تراکنشی مطابق با فیلترهای انتخابی یافت نشد.' : 'هیچ تراکنش بانکی ثبت نشده است.'; ?>
            </p>
        <?php endif; ?>
    </div> <?php // end card body ?>
</div> <?php // end card ?>

<?php // JS for datepicker and tooltips (if not global) ?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/jalalidatepicker.min.css" />
<script src="<?php echo $baseUrl; ?>/js/jalalidatepicker.min.js"></script>
<script>
    jalaliDatepicker.startWatch({ selector: '.jalali-datepicker', showTodayBtn: true, showCloseBtn: true, format: 'Y/m/d' });
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
</script>