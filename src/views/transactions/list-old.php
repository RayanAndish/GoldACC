<?php
/**
 * Template: src/views/transactions/list.php
 * نمایش لیست معاملات با فیلتر، جستجو و صفحه‌بندی
 * داده‌ها از کنترلر TransactionController به صورت $viewData ارسال می‌شود
 */

use App\Utils\Helper;

// --- استخراج داده‌ها از $viewData ---
$pageTitle = $viewData['page_title'] ?? 'لیست معاملات';
$transactions = $viewData['transactions'] ?? [];
$successMessage = $viewData['success_msg'] ?? null;
$errorMessage = $viewData['error_msg'] ?? null;
$searchTerm = $viewData['search_term'] ?? '';
$filters = $viewData['filters'] ?? [];
$pagination = $viewData['pagination'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';
$contactsForFilter = $viewData['contacts_for_filter'] ?? [];
$deliveryStatuses = $viewData['delivery_statuses'] ?? [];
$csrfToken = $viewData['csrf_token'] ?? (function_exists('csrf_token') ? csrf_token() : '');

$pageBaseUrl = $baseUrl . '/app/transactions';
$queryParams = array_filter([
    'search' => $searchTerm,
    'type' => $filters['type'] ?? null,
    'contact' => $filters['contact_id'] ?? null,
    'status' => $filters['status'] ?? null,
    'start_date' => $filters['start_date_jalali'] ?? null,
    'end_date' => $filters['end_date_jalali'] ?? null,
]);
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?= Helper::escapeHtml($pageTitle) ?></h1>
    <a href="<?= $baseUrl ?>/app/transactions/add" class="btn btn-success btn-sm">
        <i class="fas fa-plus me-1"></i> افزودن معامله جدید
    </a>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= Helper::escapeHtml($successMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= Helper::escapeHtml($errorMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-header">
        <a class="text-decoration-none text-dark" data-bs-toggle="collapse" href="#filterCollapse" role="button" aria-expanded="false" aria-controls="filterCollapse">
           <i class="fas fa-filter me-1"></i> فیلتر و جستجو
           <i class="fas fa-chevron-down fa-xs ms-1"></i>
        </a>
    </div>
    <div class="collapse" id="filterCollapse">
        <div class="card-body p-2">
            <form method="GET" action="<?= $pageBaseUrl ?>" class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <input type="text" class="form-control form-control-sm" name="search" value="<?= Helper::escapeHtml($searchTerm) ?>" placeholder="جستجو در شرح، شماره انگ، طرف حساب...">
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select form-select-sm" name="contact">
                        <option value="">همه</option>
                        <?php foreach($contactsForFilter as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (($filters['contact_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= Helper::escapeHtml($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select form-select-sm" name="type">
                        <option value="">همه</option>
                        <option value="buy" <?= (($filters['type'] ?? '') === 'buy') ? 'selected' : '' ?>>خرید</option>
                        <option value="sell" <?= (($filters['type'] ?? '') === 'sell') ? 'selected' : '' ?>>فروش</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select form-select-sm" name="status">
                        <option value="">همه</option>
                        <?php foreach($deliveryStatuses as $key => $label): ?>
                         <option value="<?= Helper::escapeHtml($key) ?>" <?= (($filters['status'] ?? '') === $key) ? 'selected' : '' ?>><?= Helper::escapeHtml($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <input type="text" class="form-control form-control-sm jalali-datepicker" name="start_date" value="<?= Helper::escapeHtml($filters['start_date_jalali'] ?? '') ?>" placeholder="از تاریخ">
                </div>
                <div class="col-lg-2 col-md-4">
                    <input type="text" class="form-control form-control-sm jalali-datepicker" name="end_date" value="<?= Helper::escapeHtml($filters['end_date_jalali'] ?? '') ?>" placeholder="تا تاریخ">
                </div>
                <div class="col-lg-auto col-md-4 d-flex align-items-end">
                     <button type="submit" class="btn btn-sm btn-primary me-1 flex-grow-1"><i class="fas fa-filter"></i></button>
                     <a href="<?= $pageBaseUrl ?>" class="btn btn-sm btn-secondary flex-grow-1" title="پاک کردن فیلترها"><i class="fas fa-times"></i></a>
                 </div>
            </form>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">لیست معاملات</h5>
        <?php if ($pagination && $pagination['total_records'] > 0): ?>
            <small class="text-muted">
                نمایش <?= (($pagination['current_page']-1) * $pagination['items_per_page']) + 1; ?>
                - <?= min($pagination['total_records'], $pagination['current_page'] * $pagination['items_per_page']); ?>
                از <?= $pagination['total_records']; ?>
            </small>
        <?php endif; ?>
    </div>
    <div class="card-body p-0 <?= empty($transactions) ? 'p-md-4' : 'p-md-0'; ?>">
        <?php if (!empty($transactions)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0 small">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="text-center">#</th>
                            <th class="text-center">نوع</th>
                            <th>تاریخ</th>
                            <th>طرف حساب</th>
                            <th class="text-center">مبلغ کل (ریال)</th>
                            <th class="text-center">وضعیت تحویل</th>
                            <th class="text-center" style="width: 85px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="text-center small fw-bold"><?= (int)$tx['id'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= ($tx['transaction_type'] == 'buy') ? 'success' : 'danger'; ?>">
                                        <?= $tx['transaction_type_farsi'] ?? '?' ?>
                                    </span>
                                </td>
                                <td class="small text-nowrap"><?= $tx['transaction_date_persian'] ?? '?' ?></td>
                                <td class="small">
                                    <?php if (!empty($tx['counterparty_contact_id']) && !empty($tx['counterparty_name'])): ?>
                                        <a href="<?= $baseUrl ?>/app/contacts/ledger/<?= (int)$tx['counterparty_contact_id'] ?>" title="کارت حساب" class="text-decoration-none">
                                            <?= $tx['counterparty_name'] ?>
                                        </a>
                                    <?php else: ?>
                                        <?= $tx['counterparty_name'] ?: '-' ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold number-fa"><?= $tx['total_value_rials_formatted'] ?? '?' ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $tx['delivery_status_class'] ?? 'bg-secondary' ?>">
                                        <?= $tx['delivery_status_farsi'] ?? '?' ?>
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    <a href="<?= $baseUrl ?>/app/transactions/edit/<?= (int)$tx['id'] ?>" class="btn btn-sm btn-outline-primary btn-action me-1 py-0 px-1" data-bs-toggle="tooltip" title="ویرایش"><i class="fas fa-edit fa-xs"></i></a>
                                    <form action="<?= $baseUrl ?>/app/transactions/delete/<?= (int)$tx['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('آیا از حذف معامله #<?= (int)$tx['id'] ?> مطمئن هستید؟');">
                                        <input type="hidden" name="csrf_token" value="<?= Helper::escapeHtml($csrfToken) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-action me-1 py-0 px-1" data-bs-toggle="tooltip" title="حذف"><i class="fas fa-trash fa-xs"></i></button>
                                    </form>
                                    <?php if ($tx['can_complete_receipt'] ?? false): ?>
                                        <form action="<?= $baseUrl ?>/app/transactions/complete-delivery/<?= (int)$tx['id'] ?>/receipt" method="POST" class="d-inline" onsubmit="return confirm('آیا دریافت کالای معامله #<?= (int)$tx['id'] ?> را تایید می‌کنید؟');">
                                            <input type="hidden" name="csrf_token" value="<?= Helper::escapeHtml($csrfToken) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success btn-action py-0 px-1" data-bs-toggle="tooltip" title="تایید دریافت کالا"><i class="fas fa-download fa-xs"></i></button>
                                        </form>
                                    <?php elseif ($tx['can_complete_delivery'] ?? false): ?>
                                        <form action="<?= $baseUrl ?>/app/transactions/complete-delivery/<?= (int)$tx['id'] ?>/delivery" method="POST" class="d-inline" onsubmit="return confirm('آیا تحویل کالای معامله #<?= (int)$tx['id'] ?> را تایید می‌کنید؟');">
                                            <input type="hidden" name="csrf_token" value="<?= Helper::escapeHtml($csrfToken) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning btn-action py-0 px-1" data-bs-toggle="tooltip" title="تایید تحویل کالا"><i class="fas fa-upload fa-xs"></i></button>
                                        </form>
                                    <?php elseif (($tx['delivery_status'] ?? '') === 'completed'): ?>
                                        <span class="text-success ms-1" data-bs-toggle="tooltip" title="تکمیل شده"><i class="fas fa-check-circle fa-xs"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!$errorMessage): ?>
            <p class="text-center text-muted p-4 mb-0">
                <?= !empty($queryParams) ? 'هیچ معامله‌ای مطابق با فیلترهای انتخابی یافت نشد.' : 'هیچ معامله‌ای ثبت نشده است.'; ?>
            </p>
        <?php endif; ?>
    </div>
    <?php if ($pagination && $pagination['total_pages'] > 1): ?>
        <?php
        $baseUrlForPagination = $pageBaseUrl . $queryString;
        ?>
        <nav class="d-flex justify-content-center my-3">
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <li class="page-item <?= ($i == $pagination['current_page']) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $baseUrlForPagination . (strpos($baseUrlForPagination, '?') !== false ? '&' : '?') . 'p=' . $i ?>"> <?= $i ?> </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php // JS for datepicker and tooltips ?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/jalalidatepicker.min.css" />
<script src="<?php echo $baseUrl; ?>/js/jalalidatepicker.min.js"></script>
<script>
    jalaliDatepicker.startWatch({ selector: '.jalali-datepicker', showTodayBtn: true, showCloseBtn: true, format: 'Y/m/d' });
    
    // اصلاح استفاده از bootstrap برای tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        if (typeof bootstrap !== 'undefined') {
            var tooltipList = tooltipTriggerList.map(function (el) { 
                return new bootstrap.Tooltip(el); 
            });
        }
    });
</script>