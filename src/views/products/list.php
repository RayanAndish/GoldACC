<?php
// src/views/products/list.php
use App\Utils\Helper;

// --- Extract data from $viewData ---
$pageTitle = $viewData['pageTitle'] ?? 'لیست محصولات';
$products = $viewData['products'] ?? [];
$flashMessage = $viewData['flashMessage'] ?? null; // Get the whole flash message array
$successMessage = ($flashMessage && $flashMessage['type'] === 'success') ? $flashMessage['text'] : ($viewData['success_msg'] ?? null);
$errorMessage = $viewData['error_msg'] ?? (($flashMessage && $flashMessage['type'] === 'danger') ? $flashMessage['text'] : null);
$otherMessage = ($flashMessage && !in_array($flashMessage['type'], ['success', 'danger'])) ? $flashMessage['text'] : null;
$otherMessageType = ($flashMessage && isset($flashMessage['type'])) ? $flashMessage['type'] : 'info';

$baseUrl = $viewData['baseUrl'] ?? '';
$pageBaseUrl = $baseUrl . '/app/products';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?= Helper::escapeHtml($pageTitle) ?></h1>
    <a href="<?= Helper::escapeHtml($pageBaseUrl) ?>/add" class="btn btn-success btn-sm">
        <i class="fas fa-plus-circle me-1"></i> افزودن محصول
    </a>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= Helper::escapeHtml($successMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= Helper::escapeHtml($errorMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($otherMessage): ?>
    <div class="alert alert-<?= Helper::escapeHtml($otherMessageType) ?> alert-dismissible fade show"><?= Helper::escapeHtml($otherMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
         <h5 class="mb-0">لیست محصولات</h5>
         <small class="text-muted">مجموع: <?= count($products) ?></small>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($products)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 5%;">#</th>
                            <th>نام محصول</th>
                            <th>کد</th>
                            <th>دسته‌بندی</th>
                            <th class="text-center">عیار پیش‌فرض</th>
                            <th class="text-center">وضعیت</th>
                            <th class="text-center" style="width: 120px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $index => $product): ?>
                            <?php if ($product instanceof \App\Models\Product): // Ensure it's a Product object ?>
                            <tr>
                                <td class="text-center small"><?= $index + 1 ?></td>
                                <td class="fw-bold"><?= Helper::escapeHtml($product->name) ?></td>
                                <td class="small"><?= Helper::escapeHtml($product->product_code ?? '-') ?></td>
                                <td class="small"><?= Helper::escapeHtml($product->category instanceof \App\Models\ProductCategory ? $product->category->name : 'نامشخص') ?></td>
                                <td class="text-center small"><?= $product->default_carat !== null ? Helper::escapeHtml((string)$product->default_carat) : '-' ?></td>
                                <td class="text-center">
                                    <?php if ($product->is_active): ?>
                                        <span class="badge bg-success">فعال</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-nowrap">
                                    <a href="<?= Helper::escapeHtml($pageBaseUrl) ?>/edit/<?= (int)$product->id ?>" class="btn btn-sm btn-outline-primary btn-action me-1" data-bs-toggle="tooltip" title="ویرایش"><i class="fas fa-pen-to-square"></i></a>
                                    <form action="<?= Helper::escapeHtml($pageBaseUrl) ?>/delete/<?= (int)$product->id ?>" method="POST" class="d-inline" onsubmit="return confirm('آیا از حذف محصول «' + <?= htmlspecialchars(json_encode($product->name), ENT_QUOTES, 'UTF-8') ?> + '» اطمینان دارید؟');">
                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-action" data-bs-toggle="tooltip" title="حذف"><i class="fas fa-trash-can"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center text-muted p-4 mb-0">هیچ محصولی ثبت نشده است.</p>
        <?php endif; ?>
    </div>
</div>