<?php
/**
 * Template: src/views/assays/list.php
 * Displays the list of Assay Offices with search and pagination.
 * Receives data via $viewData array from AssayOfficeController.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData (provided by Controller) ---
$pageTitle = $viewData['page_title'] ?? 'مراکز ری‌گیری';
$assayOffices = $viewData['assay_offices'] ?? [];
$successMessage = $viewData['success_msg'] ?? null; // Success message from controller/session
$errorMessage = $viewData['error_msg'] ?? null; // Error message from controller/session
$searchTerm = $viewData['search_term'] ?? ''; // Current search term
$pagination = $viewData['pagination'] ?? null; // Pagination data array
$baseUrl = $viewData['baseUrl'] ?? ''; // Base URL for constructing links

// Define base URL for this page (including potential search term)
$pageBaseUrl = $baseUrl . '/app/assay-offices';
$queryString = !empty($searchTerm) ? '?search=' . urlencode($searchTerm) : '';

?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    <a href="<?php echo $baseUrl; ?>/app/assay-offices/add" class="btn btn-success btn-sm">
        <i class="fas fa-plus me-1"></i> افزودن مرکز جدید
    </a>
</div>

<?php // --- Display Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo Helper::escapeHtml($successMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo Helper::escapeHtml($errorMessage); // Escape simple error messages ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>


<?php // --- Search Form --- ?>
<div class="card shadow-sm mb-3">
    <div class="card-body p-2">
        <form method="GET" action="<?php echo $pageBaseUrl; ?>" class="row g-2 align-items-center">
            <?php /* No hidden 'page' input needed with proper routing */ ?>
            <div class="col-md-5 col-lg-4">
                 <label for="search" class="visually-hidden">جستجو</label>
                 <input type="text" class="form-control form-control-sm" id="search" name="search" value="<?php echo Helper::escapeHtml($searchTerm); ?>" placeholder="جستجو در نام مرکز...">
            </div>
            <div class="col-auto">
                 <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
            </div>
            <?php if (!empty($searchTerm)): ?>
                 <div class="col-auto">
                     <a href="<?php echo $pageBaseUrl; ?>" class="btn btn-sm btn-outline-secondary" title="پاک کردن جستجو">
                         <i class="fas fa-times"></i>
                     </a>
                 </div>
             <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">لیست مراکز ثبت شده</h5>
        <?php if ($pagination && $pagination['totalRecords'] > 0): ?>
            <small class="text-muted">
                نمایش <?php echo (($pagination['currentPage']-1) * $pagination['limit']) + 1; ?>
                تا <?php echo min($pagination['totalRecords'], $pagination['currentPage'] * $pagination['limit']); ?>
                از <?php echo $pagination['totalRecords']; ?> مورد
            </small>
        <?php else: ?>
             <small class="text-muted">مجموع: 0 مورد</small>
        <?php endif; ?>
    </div>
    <div class="card-body p-0 <?php echo empty($assayOffices) ? 'p-md-4' : 'p-md-0'; ?>">
        <?php if (!empty($assayOffices)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                         <tr>
                            <th scope="col" style="width: 5%;">#</th>
                            <th scope="col">نام مرکز</th>
                            <th scope="col">تلفن</th>
                            <th scope="col">آدرس</th>
                            <th scope="col">تاریخ ثبت</th>
                            <th scope="col" class="text-center">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php // Row number calculation needs pagination info
                            $startRow = (($pagination['currentPage'] ?? 1) - 1) * ($pagination['limit'] ?? 15) + 1;
                        ?>
                        <?php foreach ($assayOffices as $index => $office): ?>
                            <tr>
                                <td class="text-center small"><?php echo $startRow + $index; ?></td>
                                <td><?php echo $office['name']; // Already escaped in controller ?></td>
                                <td class="number-fa text-center"><?php echo $office['phone'] ?: '-'; // Already escaped ?></td>
                                <td class="small"><?php echo nl2br($office['address'] ?: '-'); // Use nl2br for addresses, already escaped ?></td>
                                <td class="text-nowrap small"><?php echo $office['created_at_persian'] ?? '-'; // Formatted in controller ?></td>
                                <td class="text-center text-nowrap">
                                     <?php // Construct URLs using base URL and route paths ?>
                                     <a href="<?php echo $baseUrl; ?>/app/assay-offices/edit/<?php echo (int)$office['id']; ?>"
                                        class="btn btn-sm btn-outline-primary btn-action me-1" data-bs-toggle="tooltip" title="ویرایش">
                                         <i class="fas fa-pen-to-square"></i>
                                     </a>
                                     <?php // Delete form should use POST method for safety ?>
                                     <form action="<?php echo $baseUrl; ?>/app/assay-offices/delete/<?php echo (int)$office['id']; ?>" method="POST" class="d-inline" onsubmit="return confirm('آیا از حذف مرکز ' + <?php echo json_encode($office['name']); ?> + ' مطمئن هستید؟');">
                                         <?php // TODO: Add CSRF token input here ?>
                                         <button type="submit" class="btn btn-sm btn-outline-danger btn-action" data-bs-toggle="tooltip" title="حذف">
                                             <i class="fas fa-trash-can"></i>
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
                <?php 
                $baseUrlForPagination = $pageBaseUrl;
                include __DIR__ . '/../partials/pagination.php';
                ?>
            <?php endif; ?>
            <?php // --- End Pagination --- ?>

        <?php elseif (empty($errorMessage)): // Show message only if no error occurred ?>
            <p class="text-center text-muted p-4 mb-0">
                 <?php echo empty($searchTerm) ? 'هنوز مرکزی ثبت نشده است.' : 'موردی با این مشخصات یافت نشد.'; ?>
            </p>
        <?php endif; ?>
    </div> <?php // end card body ?>
</div> <?php // end card ?>

<?php
// Activate Bootstrap Tooltips if not done globally in layout footer
// Needs Bootstrap JS loaded
?>
<script>
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  })
</script>