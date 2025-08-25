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
$pagination = $viewData['pagination'] ?? null; // شامل 'pages' آرایه برای لینک‌ها
$baseUrl = $viewData['baseUrl'] ?? '';
$contactsForFilter = $viewData['contacts_for_filter'] ?? [];
$deliveryStatuses = $viewData['delivery_statuses'] ?? []; // از کنترلر می‌آید
$csrfToken = $viewData['csrf_token'] ?? ''; // CSRF Token از کنترلر می‌آید

$pageBaseUrl = $baseUrl . '/app/transactions';

// ساخت Query String پایه برای Pagination
$currentQueryParams = array_filter([
    'search' => $searchTerm,
    'type' => $filters['type'] ?? null,
    'contact' => $filters['contact_id'] ?? null,
    'status' => $filters['status'] ?? null,
    'start_date' => $filters['start_date_jalali'] ?? null,
    'end_date' => $filters['end_date_jalali'] ?? null,
]);

// تابع کمکی برای ساخت URL صفحه
function getPageUrl($pageNumber, $baseUrl, $baseQueryParams) {
    $params = $baseQueryParams;
    $params['p'] = $pageNumber;
    return $baseUrl . '?' . http_build_query($params);
}
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
    <div class="collapse <?= !empty(array_filter($filters)) || !empty($searchTerm) ? 'show' : '' ?>" id="filterCollapse">
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
        <?php if ($pagination && $pagination['totalRecords'] > 0): ?>
            <small class="text-muted">
                نمایش <?= $pagination['firstItem']; ?>
                - <?= $pagination['lastItem']; ?>
                از <?= $pagination['totalRecords']; ?>
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
                                        <?= Helper::escapeHtml($tx['transaction_type_farsi'] ?? '?') ?>
                                    </span>
                                </td>
                                <td class="small text-nowrap"><?= Helper::escapeHtml($tx['transaction_date_persian'] ?? '?') ?></td>
                                <td class="small">
                                    <?php if (!empty($tx['counterparty_contact_id']) && !empty($tx['counterparty_name'])): ?>
                                        <a href="<?= Helper::escapeHtml($baseUrl) ?>/app/contacts/ledger/<?= (int)$tx['counterparty_contact_id'] ?>" title="کارت حساب" class="text-decoration-none">
                                            <?= Helper::escapeHtml($tx['counterparty_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= Helper::escapeHtml($tx['counterparty_name'] ?: '-') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold number-fa">
                                    <?= Helper::formatRial($tx['total_value_rials'] ?? 0, false) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= Helper::escapeHtml($tx['delivery_status_class'] ?? 'bg-secondary') ?>">
                                        <?= Helper::escapeHtml($tx['delivery_status_farsi'] ?? '?') ?>
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    <a href="<?= Helper::escapeHtml($baseUrl) ?>/app/transactions/edit/<?= (int)$tx['id'] ?>" class="btn btn-sm btn-outline-primary btn-action me-1 py-0 px-1" data-bs-toggle="tooltip" title="ویرایش"><i class="fas fa-edit fa-xs"></i></a>
                                    <form action="<?= Helper::escapeHtml($baseUrl) ?>/app/transactions/delete/<?= (int)$tx['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('آیا از حذف معامله #<?= (int)$tx['id'] ?> مطمئن هستید؟');">
                                        <input type="hidden" name="csrf_token" value="<?= Helper::escapeHtml($csrfToken) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-action me-1 py-0 px-1" data-bs-toggle="tooltip" title="حذف"><i class="fas fa-trash fa-xs"></i></button>
                                    </form>
                                    <?php if ($tx['can_complete_receipt'] ?? false): ?>
                                        <form action="<?= Helper::escapeHtml($baseUrl) ?>/app/transactions/complete-delivery/<?= (int)$tx['id'] ?>/receipt" method="POST" class="d-inline" onsubmit="return confirm('آیا دریافت کالای معامله #<?= (int)$tx['id'] ?> را تایید می‌کنید؟');">
                                            <input type="hidden" name="csrf_token" value="<?= Helper::escapeHtml($csrfToken) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success btn-action py-0 px-1" data-bs-toggle="tooltip" title="تایید دریافت کالا"><i class="fas fa-download fa-xs"></i></button>
                                        </form>
                                    <?php elseif ($tx['can_complete_delivery'] ?? false): ?>
                                        <form action="<?= Helper::escapeHtml($baseUrl) ?>/app/transactions/complete-delivery/<?= (int)$tx['id'] ?>/delivery" method="POST" class="d-inline" onsubmit="return confirm('آیا تحویل کالای معامله #<?= (int)$tx['id'] ?> را تایید می‌کنید؟');">
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
                <?= !empty(array_filter($currentQueryParams)) || !empty($searchTerm) ? 'هیچ معامله‌ای مطابق با فیلترهای انتخابی یافت نشد.' : 'هیچ معامله‌ای ثبت نشده است.'; ?>
            </p>
        <?php endif; ?>
    </div>
    <?php if ($pagination && $pagination['total_pages'] > 1): ?>
        <nav class="d-flex justify-content-center my-3">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $pagination['hasPrevPage'] ? '' : 'disabled' ?>">
                    <a class="page-link" href="<?= getPageUrl($pagination['prevPage'], $pageBaseUrl, $currentQueryParams) ?>" aria-label="قبلی">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php foreach ($pagination['pages'] as $page): ?>
                    <?php if ($page['isEllipsis']): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php else: ?>
                        <li class="page-item <?= $page['isCurrent'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= getPageUrl($page['num'], $pageBaseUrl, $currentQueryParams) ?>">
                                <?= $page['num'] ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li class="page-item <?= $pagination['hasNextPage'] ? '' : 'disabled' ?>">
                    <a class="page-link" href="<?= getPageUrl($pagination['nextPage'], $pageBaseUrl, $currentQueryParams) ?>" aria-label="بعدی">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>