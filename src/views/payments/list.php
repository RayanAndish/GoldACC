<?php
use App\Utils\Helper;
use App\Core\CSRFProtector;

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'مدیریت پرداخت‌ها';
$payments = $viewData['payments'] ?? [];
$successMessage = $viewData['success_msg'] ?? null;
$errorMessage = $viewData['error_msg'] ?? null;
$searchTerm = $viewData['search_term'] ?? '';
$pagination = $viewData['pagination'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';

// Base URL for this page for search form and pagination links
$pageBaseUrl = $baseUrl . '/app/payments';
$queryStringParams = ['search' => $searchTerm]; // Only search for now
// Helper to generate full query string URL
function getPageUrl($pageNumber, $baseUrl, $queryParams) {
    $params = $queryParams;
    $params['p'] = $pageNumber;
    return $baseUrl . '?' . http_build_query($params);
}

?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    <a href="<?php echo $baseUrl; ?>/app/payments/add" class="btn btn-success btn-sm">
        <i class="fas fa-plus me-1"></i> ثبت پرداخت/دریافت
    </a>
</div>

<?php // --- Display Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo Helper::escapeHtml($successMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo Helper::escapeHtml($errorMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>


<?php // --- Search Form (Add filters later if needed) --- ?>
<div class="card shadow-sm mb-3">
    <div class="card-body p-2">
        <form method="GET" action="<?php echo $pageBaseUrl; ?>" class="row g-2 align-items-center">
            <div class="col-md-6 col-lg-5">
                <label for="search" class="visually-hidden">جستجو</label>
                 <input type="text" class="form-control form-control-sm" id="search" name="search" value="<?php echo Helper::escapeHtml($searchTerm); ?>" placeholder="جستجو در شرح، پرداخت/دریافت کننده...">
            </div>
            <div class="col-auto">
                 <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
             </div>
            <?php if (!empty($searchTerm)): ?>
            <div class="col-auto">
                <a href="<?php echo $pageBaseUrl; ?>" class="btn btn-sm btn-outline-secondary" title="پاک کردن جستجو"><i class="fas fa-times"></i></a>
            </div>
            <?php endif; ?>
         </form>
    </div>
</div>


<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">لیست پرداخت‌ها و دریافت‌ها</h5>
         <?php if ($pagination && $pagination['totalRecords'] > 0): ?>
            <small class="text-muted">
                نمایش <?php echo Helper::formatPersianNumber($pagination['firstItem']); ?>
                - <?php echo Helper::formatPersianNumber($pagination['lastItem']); ?>
                از <?php echo Helper::formatPersianNumber($pagination['totalRecords']); ?>
            </small>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($payments)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-nowrap">تاریخ</th>
                            <th>جهت</th>
                            <th class="text-center">مبلغ <small>(ریال)</small></th>
                            <th>پرداخت کننده</th>
                            <th>دریافت کننده</th>
                            <th class="text-center">معامله مرتبط</th>
                            <th>یادداشت</th>
                            <th class="text-center">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="small text-nowrap">
                                    <?php echo $p['payment_date_persian'] ?? '-'; ?>
                                </td>
                                <td class="small text-nowrap">
                                    <span class="badge bg-<?php echo ($p['direction'] === 'inflow') ? 'success' : 'danger'; ?>">
                                        <?php echo $p['direction_farsi'] ?? '?'; ?>
                                    </span>
                                </td>
                                <td class="text-center fw-bold number-fa">
                                    <?php echo $p['amount_rials_formatted'] ?? '-'; ?>
                                </td>
                                <td class="small">
                                    <?php if ($p['paying_contact_id']): ?>
                                        <a href="<?php echo $baseUrl; ?>/app/contacts/ledger/<?php echo (int)$p['paying_contact_id']; ?>" title="مشاهده کارت حساب">
                                            <?php echo Helper::escapeHtml($p['paying_contact_name'] ?: ($p['paying_details'] ?: '?')); ?> <i class="fas fa-external-link-alt fa-xs text-muted"></i>
                                        </a>
                                    <?php else: ?>
                                         <?php echo Helper::escapeHtml($p['paying_details'] ?: ($p['paying_contact_name'] ?: '-')); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                     <?php if ($p['receiving_contact_id']): ?>
                                        <a href="<?php echo $baseUrl; ?>/app/contacts/ledger/<?php echo (int)$p['receiving_contact_id']; ?>" title="مشاهده کارت حساب">
                                            <?php echo Helper::escapeHtml($p['receiving_contact_name'] ?: ($p['receiving_details'] ?: '?')); ?> <i class="fas fa-external-link-alt fa-xs text-muted"></i>
                                        </a>
                                    <?php else: ?>
                                         <?php echo Helper::escapeHtml($p['receiving_details'] ?: ($p['receiving_contact_name'] ?: '-')); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center small">
                                    <?php if (!empty($p['related_transaction_id']) && isset($p['related_transaction_display'])): ?>
                                        <a href="<?php echo $baseUrl; ?>/app/transactions/edit/<?php echo (int)$p['related_transaction_id']; ?>" target="_blank" title="مشاهده معامله مرتبط">
                                            <?php echo $p['related_transaction_display']; ?>
                                        </a>
                                    <?php else: echo '-'; endif; ?>
                                </td>
                                <td class="small" title="<?php echo Helper::escapeHtml($p['notes'] ?? ''); ?>">
                                     <?php echo Helper::escapeHtml(mb_substr($p['notes'] ?? '', 0, 40, 'UTF-8') . (mb_strlen($p['notes'] ?? '') > 40 ? '...' : '')); ?>
                                </td>
                                <td class="text-center text-nowrap">
                                     <?php // Edit Button ?>
                                    <a href="<?php echo $baseUrl; ?>/app/payments/edit/<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary btn-action me-1" data-bs-toggle="tooltip" title="ویرایش"><i class="fas fa-edit"></i></a>
                                     <?php // Delete Form (POST) ?>
                                    <form action="<?php echo $baseUrl; ?>/app/payments/delete/<?php echo (int)$p['id']; ?>" method="POST" class="d-inline" onsubmit="return confirm('آیا از حذف این رکورد مطمئن هستید؟ در صورت ارتباط با بانک، اثر آن نیز برمیگردد.');">
                                        <?php // Add CSRF token for safety. This comes from Controller: generateCsrfToken() ?>
                                        <input type="hidden" name="csrf_token" value="<?php echo Helper::generateCsrfToken(); ?>"> 
                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-action" data-bs-toggle="tooltip" title="حذف"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

             <?php // --- Pagination Links --- ?>
             <?php if ($pagination && isset($pagination['total_pages']) && $pagination['total_pages'] > 1): ?>
                 <nav class="d-flex justify-content-center my-3">
                     <ul class="pagination pagination-sm mb-0">
                         <li class="page-item <?php echo ($pagination['current_page'] <= 1) ? 'disabled' : ''; ?>">
                             <a class="page-link" href="<?php echo getPageUrl(1, $pageBaseUrl, $queryStringParams); ?>" aria-label="First">
                                 <span aria-hidden="true">««</span>
                             </a>
                         </li>
                         <li class="page-item <?php echo ($pagination['current_page'] <= 1) ? 'disabled' : ''; ?>">
                             <a class="page-link" href="<?php echo getPageUrl($pagination['current_page'] - 1, $pageBaseUrl, $queryStringParams); ?>" aria-label="Previous">
                                 <span aria-hidden="true">«</span>
                             </a>
                         </li>
                         <?php foreach ($pagination['pages'] as $page): ?>
                             <?php if ($page['is_ellipsis']): ?>
                                 <li class="page-item disabled"><span class="page-link">...</span></li>
                             <?php else: ?>
                                 <li class="page-item <?php echo $page['is_current'] ? 'active' : ''; ?>">
                                     <a class="page-link" href="<?php echo getPageUrl($page['num'], $pageBaseUrl, $queryStringParams); ?>">
                                         <?php echo Helper::formatPersianNumber($page['num']); ?>
                                     </a>
                                 </li>
                             <?php endif; ?>
                         <?php endforeach; ?>
                         <li class="page-item <?php echo ($pagination['current_page'] >= $pagination['total_pages']) ? 'disabled' : ''; ?>">
                             <a class="page-link" href="<?php echo getPageUrl($pagination['current_page'] + 1, $pageBaseUrl, $queryStringParams); ?>" aria-label="Next">
                                 <span aria-hidden="true">»</span>
                             </a>
                         </li>
                         <li class="page-item <?php echo ($pagination['current_page'] >= $pagination['total_pages']) ? 'disabled' : ''; ?>">
                             <a class="page-link" href="<?php echo getPageUrl($pagination['total_pages'], $pageBaseUrl, $queryStringParams); ?>" aria-label="Last">
                                 <span aria-hidden="true">»»</span>
                             </a>
                         </li>
                     </ul>
                 </nav>
             <?php endif; ?>

        <?php elseif (!$errorMessage): ?>
            <p class="text-center text-muted p-3 mb-0">هنوز هیچ پرداخت یا دریافتی ثبت نشده است.</p>
        <?php endif; ?>
    </div>
</div>

<?php // Tooltip activation script and Bootstrap's specific Tooltip JS initialization (should be in global JS or shared partial if common) ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Check and display flash messages based on URL params (similar to Transaction form if needed for lists)
    // Assuming messages are setup in a global way if they appear here via this method.
    // window.showMessage and window.Messages not provided for Payments, usually only in Transaction Module.
    const urlParams = new URLSearchParams(window.location.search);
    const message = urlParams.get('message');
    const type = urlParams.get('type'); // Get 'success' or 'danger' type
    if (message && type) {
        const messageContainer = document.querySelector('#alert-container'); // Need a container to place it.
        if (messageContainer) {
            messageContainer.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
            setTimeout(() => { // Auto-dismiss after 5 seconds
                const alertEl = messageContainer.querySelector('.alert');
                if (alertEl) {
                    new bootstrap.Alert(alertEl).close();
                }
            }, 5000);
        }
    }
});
</script>