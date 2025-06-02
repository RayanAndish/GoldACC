<?php
// src/views/product_categories/list.php
use App\Utils\Helper;

// --- Extract data from $viewData ---
$pageTitle = $viewData['pageTitle'] ?? 'دسته‌بندی محصولات';
$categories = $viewData['categories'] ?? [];

$action = $action ?? ($viewData['action'] ?? '');
$isEditMode = $isEditMode ?? ($viewData['isEditMode'] ?? false);
$category = $category ?? ($viewData['category'] ?? null);
$listPageUrl = $listPageUrl ?? ($viewData['listPageUrl'] ?? '');
$submitButtonText = $submitButtonText ?? ($viewData['submitButtonText'] ?? 'ذخیره');
$errors = $errors ?? ($viewData['errors'] ?? []);

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

<form action="<?= Helper::escapeHtml($action) ?>" method="POST" class="needs-validation" novalidate>
    <?php if ($isEditMode): ?>
        <input type="hidden" name="id" value="<?= Helper::escapeHtml($category->id) ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label for="name" class="form-label">نام دسته‌بندی<span class="text-danger">*</span></label>
        <input type="text"
               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
               id="name" name="name"
               value="<?= Helper::escapeHtml($category->name) ?>" required maxlength="100">
        <div class="invalid-feedback"><?= Helper::escapeHtml($errors['name'] ?? 'لطفا نام دسته‌بندی را وارد کنید.') ?></div>
    </div>

    <div class="mb-3">
        <label for="code" class="form-label">کد دسته‌بندی <small>(اختیاری)</small></label>
        <input type="text"
               class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>"
               id="code" name="code"
               value="<?= Helper::escapeHtml($category->code ?? '') ?>" maxlength="50">
        <div class="form-text small text-muted">کد یکتا برای شناسایی (مثال: GOLD_COIN, SILVER_BAR).</div>
        <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?= Helper::escapeHtml($errors['code']) ?></div><?php endif; ?>
    </div>

    <div class="mb-3">
        <label for="base_category" class="form-label">نوع پایه دسته‌بندی<span class="text-danger">*</span></label>
        <select name="base_category" id="base_category" class="form-select <?= isset($errors['base_category']) ? 'is-invalid' : '' ?>" required>
            <option value="">انتخاب کنید...</option>
            <option value="MELTED" <?= ($category->base_category === 'MELTED') ? 'selected' : '' ?>>آبشده</option>
            <option value="MANUFACTURED" <?= ($category->base_category === 'MANUFACTURED') ? 'selected' : '' ?>>ساخته شده</option>
            <option value="COIN" <?= ($category->base_category === 'COIN') ? 'selected' : '' ?>>سکه</option>
            <option value="BULLION" <?= ($category->base_category === 'BULLION') ? 'selected' : '' ?>>شمش</option>
            <option value="JEWELRY" <?= ($category->base_category === 'JEWELRY') ? 'selected' : '' ?>>جواهر</option>
        </select>
        <div class="invalid-feedback"><?= Helper::escapeHtml($errors['base_category'] ?? 'لطفا نوع پایه دسته‌بندی را انتخاب کنید.') ?></div>
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">توضیحات <small>(اختیاری)</small></label>
        <textarea class="form-control" id="description" name="description" rows="3"><?= Helper::escapeHtml($category->description ?? '') ?></textarea>
    </div>

    <div class="mb-3">
        <label for="unit_of_measure" class="form-label">واحد اندازه‌گیری پیش‌فرض <small>(اختیاری)</small></label>
        <input type="text" class="form-control" id="unit_of_measure" name="unit_of_measure" value="<?= Helper::escapeHtml($category->unit_of_measure ?? '') ?>" maxlength="50">
        <div class="form-text small text-muted">مثال: عدد، گرم، مثقال، قیراط.</div>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= ($category->is_active) ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_active">
            دسته‌بندی فعال باشد
        </label>
    </div>
    <input type="hidden" name="csrf_token" value="<?= Helper::generateCsrfToken() ?>">
    <hr class="my-4">

    <div class="d-flex justify-content-between align-items-center">
        <a href="/public/app/product-categories" class="btn btn-outline-secondary px-4">انصراف</a>
        <button type="submit" class="btn btn-primary px-5">
            <i class="fas fa-save me-1"></i> <?= Helper::escapeHtml($submitButtonText) ?>
        </button>
    </div>
</form>