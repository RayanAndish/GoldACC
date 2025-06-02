<?php
/**
 * Template: src/views/bank_accounts/list.php
 * Displays the list of Bank Accounts.
 * Receives data via $viewData array from BankAccountController.
 */

use App\Utils\Helper; // Use the Helper class
use App\core\CSRFProtector; // Use the CSRFProtector class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'حساب‌های بانکی';
$accounts = $viewData['accounts'] ?? [];
$successMessage = $viewData['success_msg'] ?? null; // Success message from controller/session
$errorMessage = $viewData['error_msg'] ?? null; // Error message from controller/session
$baseUrl = $viewData['baseUrl'] ?? '';

// Calculate total balance
$totalBalance = 0.0;
foreach ($accounts as $acc) {
    $totalBalance += (float)($acc['current_balance'] ?? 0); // Use the raw balance from DB for calculation
}

?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    <a href="<?php echo $baseUrl; ?>/app/bank-accounts/add" class="btn btn-success btn-sm">
        <i class="fas fa-plus me-1"></i> افزودن حساب جدید
    </a>
</div>

<?php // --- Display Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo Helper::escapeHtml($successMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo Helper::escapeHtml($errorMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>


<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">لیست حساب‌های بانکی</h5>
        <small class="text-muted">مجموع: <?php echo count($accounts); ?> حساب</small>
    </div>
    <div class="card-body p-0 <?php echo empty($accounts) ? 'p-md-4' : 'p-md-0'; ?>">
        <?php if (!empty($accounts)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 5%;">#</th>
                            <th scope="col">نام حساب</th>
                            <th scope="col">نام بانک</th>
                            <th scope="col">شماره حساب / کارت</th>
                            <th scope="col">موجودی فعلی (ریال)</th>
                            <th scope="col">تاریخ ایجاد</th>
                            <th scope="col" class="text-center">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $index => $acc):
                              $currentBal = (float)($acc['current_balance'] ?? 0); // Get raw balance for styling
                         ?>
                            <tr>
                                <td class="fw-bold text-center"><?php echo $index + 1; ?></td>
                                <td><?php echo $acc['account_name']; // Already escaped ?></td>
                                <td><?php echo $acc['bank_name'] ?: '-'; // Already escaped ?></td>
                                <td class="text-center number-fa"><?php echo $acc['account_number'] ?: '-'; // Already escaped ?></td>
                                <td class="fw-bold text-center number-fa <?php echo $currentBal < 0 ? 'text-danger' : ''; ?>">
                                    <?php echo $acc['current_balance_formatted']; // Formatted in controller ?>
                                </td>
                                <td class="text-nowrap small text-center">
                                    <?php echo $acc['created_at_persian'] ?? '-'; // Formatted in controller ?>
                                </td>
                                <td class="text-center text-nowrap">
                                     <?php // Ledger Button ?>
                                     <a href="<?php echo $baseUrl; ?>/app/bank-accounts/ledger/<?php echo (int)$acc['id']; ?>"
                                        class="btn btn-sm btn-outline-info btn-action me-1"
                                        data-bs-toggle="tooltip" title="مشاهده گردش حساب">
                                        <i class="fas fa-list-alt"></i> <?php /* <span class="d-none d-md-inline">گردش</span> */ ?>
                                     </a>
                                     <?php // Edit Button ?>
                                     <a href="<?php echo $baseUrl; ?>/app/bank-accounts/edit/<?php echo (int)$acc['id']; ?>"
                                        class="btn btn-sm btn-outline-primary btn-action me-1"
                                        data-bs-toggle="tooltip" title="ویرایش حساب">
                                       <i class="fas fa-pen-to-square"></i>
                                     </a>
                                     <?php // Delete Form ?>
                                     <form method="post" action="<?php echo $baseUrl; ?>/app/bank-accounts/delete/<?php echo (int)$acc['id']; ?>" class="d-inline" onsubmit="return confirm('آیا از حذف این حساب بانکی اطمینان دارید؟');">
                                        <input type="hidden" name="csrf_token" value="<?php echo CSRFProtector::generateToken(); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-action" data-bs-toggle="tooltip" title="حذف حساب">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                     </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                     <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="4" class="text-end px-3">جمع کل موجودی بانک‌ها:</td>
                            <td class="text-center <?php echo $totalBalance < 0 ? 'text-danger' : ''; ?>">
                                <?php echo Helper::formatRial($totalBalance); ?>
                            </td>
                            <td colspan="2"></td> <?php /* Empty cells for actions/date */?>
                        </tr>
                     </tfoot>
                </table>
            </div>
        <?php elseif (!$errorMessage): ?>
            <p class="text-center text-muted p-4 mb-0">هنوز هیچ حساب بانکی ثبت نشده است.</p>
        <?php endif; ?>
    </div>
</div>

<?php // Tooltip JS (if not global) ?>