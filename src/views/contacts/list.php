<?php
use App\Utils\Helper;
use App\Core\CSRFProtector;

$pageTitle = $viewData['page_title'] ?? 'مدیریت مشتریان';
$contacts = $viewData['contacts'] ?? [];
$successMessage = $viewData['success_msg'] ?? null;
$errorMessage = $viewData['error_msg'] ?? null;
$searchTerm = $viewData['search_term'] ?? '';
$pagination = $viewData['pagination'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';

$pageBaseUrl = $baseUrl . '/app/contacts';
$queryString = !empty($searchTerm) ? '?search=' . urlencode($searchTerm) : '';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    <a href="<?php echo $baseUrl; ?>/app/contacts/add" class="btn btn-success btn-sm">
        <i class="fas fa-user-plus me-1"></i> افزودن مخاطب
    </a>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo Helper::escapeHtml($successMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo Helper::escapeHtml($errorMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

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

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
         <h5 class="mb-0">لیست مخاطبین</h5>
        <?php if ($pagination && $pagination['totalRecords'] > 0): ?>
            <small class="text-muted">
                نمایش <?php echo Helper::formatPersianNumber($pagination['firstItem']); ?>
                - <?php echo Helper::formatPersianNumber($pagination['lastItem']); ?>
                از <?php echo Helper::formatPersianNumber($pagination['totalRecords']); ?>
            </small>
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
                             <th class="text-center" style="width: 130px;">عملیات</th>
                        </tr>
                    </thead>
                     <tbody>
                         <?php
                            $startRow = (($pagination['currentPage'] ?? 1) - 1) * ($pagination['itemsPerPage'] ?? 15) + 1;
                         ?>
                         <?php foreach ($contacts as $index => $contact):
                               $balance = (float)($contact['balance'] ?? 0.0);
                               
                               // (اصلاح نهایی) منطق دقیق برای تشخیص بدهکار/بستانکار/بی‌حساب
                               if ($balance > 0.01) { // اگر بزرگتر از صفر بود -> بدهکار
                                   $bal_class = 'text-danger';
                                   $bal_status = 'بد';
                               } elseif ($balance < -0.01) { // اگر کوچکتر از صفر بود -> بستانکار
                                   $bal_class = 'text-success';
                                   $bal_status = 'بس';
                               } else { // در غیر این صورت (یعنی صفر یا نزدیک به صفر) -> بی‌حساب
                                   $bal_class = 'text-secondary';
                                   $bal_status = '-';
                               }
                          ?>
                            <tr>
                                <td class="text-center small"><?php echo Helper::formatPersianNumber($startRow + $index); ?></td>
                                <td class="fw-bold"><?php echo Helper::escapeHtml($contact['name']); ?></td>
                                <td class="small"><?php echo $contact['type_farsi']; ?></td>
                                 <td class="text-center number-fa small fw-bold <?php echo $bal_class; ?>">
                                     <?php echo Helper::formatRial(abs($balance)); ?>
                                </td>
                                 <td class="text-center small fw-bold <?php echo $bal_class; ?>"><?php echo $bal_status; ?></td>
                                <td class="text-center number-fa small">
                                     <?php echo $contact['credit_limit_formatted']; ?>
                                 </td>
                                <td class="small" title="<?php echo Helper::escapeHtml($contact['details'] ?? ''); ?>">
                                     <?php echo mb_substr($contact['details'] ?? '', 0, 40, 'UTF-8') . (mb_strlen($contact['details'] ?? '') > 40 ? '...' : ''); ?>
                                </td>
                                 <td class="text-center text-nowrap">
                                     <a href="<?php echo $baseUrl; ?>/app/contacts/ledger/<?php echo (int)$contact['id']; ?>" class="btn btn-sm btn-outline-info btn-action me-1" data-bs-toggle="tooltip" title="کارت حساب"><i class="fas fa-file-alt"></i></a>
                                    <a href="<?php echo $baseUrl; ?>/app/contacts/edit/<?php echo (int)$contact['id']; ?>" class="btn btn-sm btn-outline-primary btn-action me-1" data-bs-toggle="tooltip" title="ویرایش"><i class="fas fa-pen-to-square"></i></a>
                                     <form method="post" action="<?php echo $baseUrl; ?>/app/contacts/delete/<?php echo (int)$contact['id']; ?>" class="d-inline" onsubmit="return confirm('آیا از حذف این مخاطب اطمینان دارید؟');">
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

            <?php if ($pagination && $pagination['totalPages'] > 1): ?>
                 <div class="card-footer text-center py-2">
                     <?php
                     $baseUrlForPagination = $pageBaseUrl . $queryString;
                     include __DIR__ . '/../partials/pagination.php';
                     ?>
                 </div>
             <?php endif; ?>

        <?php elseif (empty($errorMessage)): ?>
            <p class="text-center text-muted p-4 mb-0"><?php echo empty($searchTerm) ? 'هیچ مخاطبی ثبت نشده است.' : 'مخاطبی با این مشخصات یافت نشد.'; ?></p>
        <?php endif; ?>
    </div>
</div>
