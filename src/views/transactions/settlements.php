<?php
// src/views/transactions/settlements.php
use App\Utils\Helper;
use App\Core\CSRFProtector;

$pageTitle = $viewData['page_title'] ?? 'تسویه حساب فیزیکی';
$contacts = $viewData['contacts'] ?? [];
$products = $viewData['products'] ?? []; // دریافت لیست محصولات
$baseUrl = $viewData['baseUrl'] ?? '';
$errorMessage = $viewData['error_message'] ?? null;
$successMessage = $viewData['success_message'] ?? null;

// ساخت یک HTML template برای ردیف جدید کالا برای استفاده در جاوا اسکریپت
$productOptionsHtml = '';
foreach ($products as $product) {
    // به جای $product['id'] از $product->id استفاده می‌کنیم
    $productOptionsHtml .= '<option value="' . (int)$product->id . '">' . Helper::escapeHtml($product->name) . '</option>';
}
?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo Helper::escapeHtml($successMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo Helper::escapeHtml($errorMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form id="settlement-form" method="POST" action="<?php echo $baseUrl; ?>/app/settlements/save">
            <input type="hidden" name="csrf_token" value="<?php echo CSRFProtector::generateToken(); ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="contact_id" class="form-label">طرف حساب*</label>
                    <select id="contact_id" name="contact_id" class="form-select" required>
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach($contacts as $contact): ?>
                        <option value="<?php echo $contact['id']; ?>"><?php echo Helper::escapeHtml($contact['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="direction" class="form-label">جهت تسویه*</label>
                    <select id="direction" name="direction" class="form-select" required>
                        <option value="outflow">تحویل به مشتری (کاهش بستانکاری او)</option>
                        <option value="inflow">دریافت از مشتری (کاهش بدهکاری او)</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="notes" class="form-label">یادداشت</label>
                    <input type="text" id="notes" name="notes" class="form-control">
                </div>
            </div>
            
            <hr class="my-4">
            <h5 class="mb-3">اقلام تسویه</h5>
            <div id="items-container">
                <div class="row g-3 align-items-center mb-3 item-row border-bottom pb-3">
                    <div class="col-md-4">
                        <label class="form-label">نوع کالا*</label>
                        <select name="items[0][product_id]" class="form-select" required>
                            <option value="">-- انتخاب کالا --</option>
                            <?php // **اصلاح حلقه دوم:** دسترسی به صورت آبجکت ?>
                            <?php foreach($products as $product): ?>
                            <option value="<?php echo $product->id; ?>"><?php echo Helper::escapeHtml($product->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">وزن ترازو*</label>
                        <input type="text" name="items[0][weight]" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">عیار*</label>
                        <input type="text" name="items[0][carat]" class="form-control" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-danger remove-item-btn" disabled>حذف</button>
                    </div>
                </div>
            </div>
            
            <button type="button" id="add-item-btn" class="btn btn-sm btn-outline-secondary mt-2">
                <i class="fas fa-plus me-1"></i>افزودن ردیف
            </button>
            
            <hr class="my-4">
            <div class="text-end">
                <button type="submit" class="btn btn-primary px-5">ثبت تسویه</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('items-container');
    const addItemBtn = document.getElementById('add-item-btn');
    let itemIndex = 1;

    // تابع برای مدیریت دکمه‌های حذف
    function updateRemoveButtons() {
        const rows = container.querySelectorAll('.item-row');
        rows.forEach((row, index) => {
            const removeBtn = row.querySelector('.remove-item-btn');
            removeBtn.disabled = (rows.length === 1); // دکمه حذف برای ردیف اول غیرفعال است
        });
    }

    // رویداد کلیک روی دکمه افزودن ردیف
    addItemBtn.addEventListener('click', function() {
        const newRow = document.createElement('div');
        newRow.className = 'row g-3 align-items-center mb-3 item-row border-bottom pb-3';
        newRow.innerHTML = `
            <div class="col-md-4">
                <label class="form-label">نوع کالا*</label>
                <select name="items[${itemIndex}][product_id]" class="form-select" required>
                    <option value="">-- انتخاب کالا --</option>
                    <?php echo $productOptionsHtml; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">وزن ترازو*</label>
                <input type="text" name="items[${itemIndex}][weight]" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">عیار*</label>
                <input type="text" name="items[${itemIndex}][carat]" class="form-control" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-danger remove-item-btn">حذف</button>
            </div>
        `;
        container.appendChild(newRow);
        itemIndex++;
        updateRemoveButtons();
    });

    // رویداد کلیک روی دکمه‌های حذف (با event delegation)
    container.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-item-btn')) {
            e.target.closest('.item-row').remove();
            updateRemoveButtons();
        }
    });

    // مقداردهی اولیه
    updateRemoveButtons();
});
</script>