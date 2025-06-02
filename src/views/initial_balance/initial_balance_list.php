<?php
/**
 * Template: src/views/initial_balance/list.php
 * List of initial balances
 */

use App\Utils\Helper;
use App\Core\CSRFProtector;

// Extract data from $viewData
$pageTitle = $viewData['pageTitle'] ?? $viewData['page_title'] ?? 'تعریف موجودی اولیه و سرمایه هدف';
$initialBalances = $viewData['initial_balances'] ?? [];
$successMessage = $viewData['success_message'] ?? null;
$errorMessage = $viewData['error_message'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    <a href="<?php echo $baseUrl; ?>/app/initial-balance/form" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i> ثبت موجودی اولیه و سرمایه هدف جدید
    </a>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo Helper::escapeHtml($successMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo Helper::escapeHtml($errorMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($initialBalances)): ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-1"></i> هیچ موجودی اولیه‌ای تعریف نشده است.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>نام محصول</th>
                            <th>مقدار موجودی</th>
                            <th>قیمت واحد (ریال)</th>
                            <th>ارزش کل (ریال)</th>
                            <th>سرمایه هدف (ریال)</th>
                            <th>عملکرد (ریال)</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($initialBalances as $balance): ?>
                            <tr>
                                <td><?php echo Helper::escapeHtml($balance['product_name']); ?></td>
                                <td><?php echo Helper::formatNumber($balance['quantity'], 3); ?></td>
                                <td><?php echo Helper::formatNumber($balance['unit_price'], 0); ?></td>
                                <td><?php echo Helper::formatNumber($balance['total_value'], 0); ?></td>
                                <td><?php echo Helper::formatNumber($balance['target_capital'], 0); ?></td>
                                <td>
                                    <?php 
                                    $performance = $balance['performance_balance'] ?? 0;
                                    $performanceClass = $performance >= 0 ? 'text-success' : 'text-danger';
                                    echo '<span class="' . $performanceClass . '">' . Helper::formatNumber($performance, 0) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo $baseUrl; ?>/app/initial-balance/edit/<?php echo $balance['id']; ?>" 
                                           class="btn btn-outline-primary" title="ویرایش">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $balance['id']; ?>)" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal حذف -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تایید حذف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                آیا از حذف این موجودی اولیه اطمینان دارید؟
            </div>
            <div class="modal-footer">
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo CSRFProtector::generateToken(); ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-danger">حذف</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    const form = document.getElementById('deleteForm');
    form.action = '<?php echo $baseUrl; ?>/app/initial-balance/delete/' + id;
    modal.show();
}
</script> 