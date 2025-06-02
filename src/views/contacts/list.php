<?php
/**
 * Template: src/views/contacts/list.php
 * Displays the list of Contacts (Customers/Suppliers) with search and pagination.
 * Receives data via $viewData array from ContactController.
 */

use App\Utils\Helper; // Use the Helper class
use App\Core\CSRFProtector; // Use the CSRFProtector class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'مدیریت مشتریان';
$contacts = $viewData['contacts'] ?? []; // Should contain 'balance' from repo query
$successMessage = $viewData['success_msg'] ?? null;
$errorMessage = $viewData['error_msg'] ?? null;
$searchTerm = $viewData['search_term'] ?? '';
$pagination = $viewData['pagination'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';

// Optional: Totals passed from controller (if calculated)
// $totalDebtorBalance = $viewData['total_debtor_balance'] ?? null;
// $totalCreditorBalance = $viewData['total_creditor_balance'] ?? null;

// Base URL for this page for search form and pagination links
$pageBaseUrl = $baseUrl . '/app/contacts';
$queryString = !empty($searchTerm) ? '?search=' . urlencode($searchTerm) : '';

?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    <a href="<?php echo $baseUrl; ?>/app/contacts/add" class="btn btn-success btn-sm">
        <i class="fas fa-user-plus me-1"></i> افزودن مخاطب
    </a>
</div>

<?php // --- Display Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo Helper::escapeHtml($successMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo Helper::escapeHtml($errorMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>


<?php // --- Search Form --- ?>
<div class="card shadow-sm mb-3">
    <div class="card-body p-2">
        <form method="GET" action="<?php echo $pageBaseUrl; ?>" class="row g-2 align-items-center">
            <div class="col-md-6 col-lg-5">
                <label for="search" class="visually-hidden">جستجو</label>
                 <input type="text" class="form-control form-control-sm" id="search" name="search" value="<?php echo Helper::escapeHtml($searchTerm); ?>" placeholder="جستجو در نام یا جزئیات مخاطبین...">
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

<?php // --- Optional: Display Total Balances --- ?>
<?php /*
 <?php if ($totalDebtorBalance !== null && $totalCreditorBalance !== null): ?>
    <div class="row mb-3 g-3">
         <div class="col-md-6"><div class="card border-danger"><div class="card-body text-center p-2">... Total Debtors ...</div></div></div>
         <div class="col-md-6"><div class="card border-success"><div class="card-body text-center p-2">... Total Creditors ...</div></div></div>
    </div>
 <?php endif; ?>
*/ ?>


<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
         <h5 class="mb-0">لیست مخاطبین</h5>
        <?php if ($pagination && $pagination['totalRecords'] > 0): ?>
            <small class="text-muted">
                نمایش <?php echo (($pagination['currentPage']-1) * $pagination['limit']) + 1; ?>
                - <?php echo min($pagination['totalRecords'], $pagination['currentPage'] * $pagination['limit']); ?>
                از <?php echo $pagination['totalRecords']; ?>
            </small>
        <?php else: ?>
             <small class="text-muted">مجموع: 0</small>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($contacts)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                             <th class="text-center" style="width: 5%;">#</th>
                             <th>نام / عنوان</th>
                             <th>ماهیت</th>
                             <th class="text-center">مانده حساب<br><small>(ریال)</small></th>
                             <th class="text-center">تشخیص</th>
                             <th class="text-center">سقف اعتبار<br><small>(ریال)</small></th>
                             <th>جزئیات</th>
                             <th class="text-center" style="width: 130px;">عملیات</th> <?php // Slightly wider for 3 buttons ?>
                        </tr>
                    </thead>
                     <tbody>
                         <?php
                            $startRow = (($pagination['currentPage'] ?? 1) - 1) * ($pagination['limit'] ?? 15) + 1;
                         ?>
                         <?php foreach ($contacts as $index => $contact):
                                // Balance should come pre-calculated from controller/repo
                               $balance = (float)($contact['balance'] ?? 0.0);
                               $balance_is_positive = $balance > ($viewData['balanceThreshold'] ?? 0.01); // Customer owes us
                               $balance_is_negative = $balance < -($viewData['balanceThreshold'] ?? 0.01); // We owe customer
                               $bal_class = $balance_is_negative ? 'text-success' : ($balance_is_positive ? 'text-danger' : 'text-secondary');
                               $bal_status = $balance_is_negative ? 'بس' : ($balance_is_positive ? 'بد' : '-');
                          ?>
                            <tr>
                                <td class="text-center small"><?php echo $startRow + $index; ?></td>
                                <td class="fw-bold"><?php echo $contact['name']; // Escaped ?></td>
                                <td class="small"><?php echo $contact['type_farsi']; // Translated ?></td>
                                 <td class="text-center number-fa small fw-bold <?php echo $bal_class; ?>">
                                     <?php echo Helper::formatNumber(abs($balance), 0); // Show absolute value ?>
                                </td>
                                 <td class="text-center small fw-bold <?php echo $bal_class; ?>"><?php echo $bal_status; ?></td>
                                <td class="text-center number-fa small">
                                     <?php echo $contact['credit_limit_formatted']; // Formatted ?>
                                 </td>
                                <td class="small" title="<?php echo $contact['details']; // Escaped, full details in title ?>">
                                     <?php echo mb_substr($contact['details'] ?? '', 0, 40, 'UTF-8') . (mb_strlen($contact['details'] ?? '') > 40 ? '...' : ''); // Show shortened ?>
                                </td>
                                 <td class="text-center text-nowrap">
                                     <?php // Ledger Button ?>
                                     <a href="<?php echo $baseUrl; ?>/app/contacts/ledger/<?php echo (int)$contact['id']; ?>" class="btn btn-sm btn-outline-info btn-action me-1" data-bs-toggle="tooltip" title="کارت حساب"><i class="fas fa-file-alt"></i></a>
                                     <?php // Edit Button ?>
                                    <a href="<?php echo $baseUrl; ?>/app/contacts/edit/<?php echo (int)$contact['id']; ?>" class="btn btn-sm btn-outline-primary btn-action me-1" data-bs-toggle="tooltip" title="ویرایش"><i class="fas fa-pen-to-square"></i></a>
                                     <?php // Delete Form (POST) ?>
                                     <form method="post" action="<?php echo $baseUrl; ?>/app/contacts/delete/<?php echo $contact['id']; ?>" class="d-inline" onsubmit="return confirm('آیا از حذف این مخاطب اطمینان دارید؟');">
                                         <input type="hidden" name="csrf_token" value="<?php echo CSRFProtector::generateToken(); ?>">
                                         <button type="submit" class="btn btn-sm btn-outline-danger btn-action" data-bs-toggle="tooltip" title="حذف مخاطب">
                                             <i class="fas fa-trash"></i>
                                         </button>
                                     </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                     </tbody>
                </table>
            </div>

            <?php // --- Pagination Links --- ?>
             <?php if ($pagination && $pagination['totalPages'] > 1): ?>
                 <div class="card-footer text-center py-2"> <?php // Footer for pagination ?>
                     <?php
                     $baseUrlForPagination = $pageBaseUrl . $queryString;
                     include __DIR__ . '/../partials/pagination.php';
                     ?>
                 </div>
             <?php endif; ?>

        <?php elseif (empty($errorMessage)): ?>
            <p class="text-center text-muted p-4 mb-0"><?php echo empty($searchTerm) ? 'هیچ مخاطبی ثبت نشده است.' : 'مخاطبی با این مشخصات یافت نشد.'; ?></p>
        <?php endif; ?>
    </div> <?php // end card body ?>
</div> <?php // end card ?>

<?php // Tooltip JS ?>