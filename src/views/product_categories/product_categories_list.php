<?php
// src/views/product_categories/list.php
use App\Utils\Helper;

// --- Extract data from $viewData ---
$pageTitle = $viewData['pageTitle'] ?? 'دسته‌بندی محصولات';
$categories = $viewData['categories'] ?? [];

// --- Handle Flash Messages ---
$flashMessage = $viewData['flashMessage'] ?? null;
$successMessage = ($flashMessage && isset($flashMessage['type']) && $flashMessage['type'] === 'success') ? ($flashMessage['text'] ?? null) : ($viewData['success_msg'] ?? null);
$errorMessage = $viewData['error_msg'] ?? (($flashMessage && isset($flashMessage['type']) && $flashMessage['type'] === 'danger') ? ($flashMessage['text'] ?? null) : null);
$otherMessage = ($flashMessage && isset($flashMessage['type']) && !in_array($flashMessage['type'], ['success', 'danger'])) ? ($flashMessage['text'] ?? null) : null;
$otherMessageType = ($flashMessage && isset($flashMessage['type'])) ? $flashMessage['type'] : 'info';
// --- End Flash Messages ---

$baseUrl = $viewData['baseUrl'] ?? '';
$currentUri = $viewData['currentUri'] ?? '';

$pageBaseUrl = $baseUrl . '/app/product-categories';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?= Helper::escapeHtml($pageTitle) ?></h1>
    <a href="<?= Helper::escapeHtml($pageBaseUrl) ?>/add" class="btn btn-success btn-sm">
        <i class="fas fa-plus-circle me-1"></i> افزودن دسته‌بندی
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
         <h5 class="mb-0">لیست دسته‌بندی‌ها</h5>
         <small class="text-muted">مجموع: <?= count($categories) ?></small>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($categories)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                             <th class="text-center" style="width: 5%;">#</th>
                             <th>نام دسته‌بندی</th>
                             <th>کد</th>
                             <th>واحد پیش‌فرض</th>
                             <th>نیازمندی‌ها</th>
                             <th class="text-center">وضعیت</th>
                             <th class="text-center" style="width: 120px;">عملیات</th>
                        </tr>
                    </thead>
                     <tbody>
                         <?php foreach ($categories as $index => $category): ?>
                            <?php if ($category instanceof \App\Models\ProductCategory): // Ensure it's a Category object ?>
                            <tr>
                                <td class="text-center small"><?= $index + 1 ?></td>
                                <td class="fw-bold"><?= Helper::escapeHtml($category->name) ?></td>
                                <td class="small"><?= Helper::escapeHtml($category->code ?? '-') ?></td>
                                <td class="small"><?= Helper::escapeHtml($category->unit_of_measure ?? '-') ?></td>
                                <td class="small">
                                    <?php
                                    $requirements = [];
                                    if ($category->requires_carat) $requirements[] = 'عیار';
                                    if ($category->requires_weight) $requirements[] = 'وزن';
                                    if ($category->requires_quantity) $requirements[] = 'تعداد';
                                    if ($category->requires_coin_year) $requirements[] = 'سال سکه';
                                    echo !empty($requirements) ? Helper::escapeHtml(implode('، ', $requirements)) : '-';
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($category->is_active): ?>
                                        <span class="badge bg-success">فعال</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                                 <td class="text-center text-nowrap">
                                    <a href="<?= Helper::escapeHtml($pageBaseUrl) ?>/edit/<?= (int)$category->id ?>" class="btn btn-sm btn-outline-primary btn-action me-1" data-bs-toggle="tooltip" title="ویرایش"><i class="fas fa-pen-to-square"></i></a>
                                    <form action="<?= Helper::escapeHtml($pageBaseUrl) ?>/delete/<?= (int)$category->id ?>" method="POST" class="d-inline" onsubmit="return confirm('آیا از حذف دسته‌بندی «' + <?= htmlspecialchars(json_encode($category->name), ENT_QUOTES, 'UTF-8') ?> + '» اطمینان دارید؟ (اگر محصولی به آن اختصاص داشته باشد حذف نمی‌شود)');">
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
            <p class="text-center text-muted p-4 mb-0">هیچ دسته‌بندی ثبت نشده است.</p>
        <?php endif; ?>
    </div>
</div>