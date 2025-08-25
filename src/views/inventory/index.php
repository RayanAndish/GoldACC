<?php
/**
 * Template: src/views/inventory/index.php
 * Displays detailed inventory information including weight-based, coins, and commitments.
 * Receives data via $viewData array from InventoryController.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'جزئیات موجودی و تعهدات';
$inventoryData = $viewData['inventory_data'] ?? [];
$errorMessage = $viewData['error_msg'] ?? null; // General loading error
$baseUrl = $viewData['baseUrl'] ?? '';
$latestMazanehPrice = $viewData['latestMazanehPrice'] ?? null;
$latestCoinPrices = $viewData['latestCoinPrices'] ?? [];
$csrfToken = $viewData['csrf_token'] ?? '';

// Extract specific sections for easier access
$weightInventory = $inventoryData['weight_inventory'] ?? [];
$coinInventory = $inventoryData['coin_inventory'] ?? [];
$pendingReceiptDetails = $inventoryData['pending_receipt_details'] ?? [];
$pendingDeliveryDetails = $inventoryData['pending_delivery_details'] ?? [];
$capitalPerformanceData = $inventoryData['capital_performance'] ?? [];
$summary = $inventoryData['summary'] ?? [
    'total_750_equivalent_formatted' => Helper::formatPersianNumber(0, 3), // Use Persian formatter
    'total_weight_value_formatted' => Helper::formatRial(0),
    'total_coin_value_formatted' => Helper::formatRial(0),
    'overall_total_value_formatted' => Helper::formatRial(0),
    'value_method_is_real' => false,
    'price_750_for_estimation' => null
];

$overallAvgBuyPrice1g = $inventoryData['overall_avg_buy_price_1g'] ?? 0;

// بررسی اگر هیچ داده‌ای برای تراز عملکرد وجود ندارد
$hasCapitalPerformanceData = !empty($capitalPerformanceData);
?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<?php // Display potential errors ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo Helper::escapeHtml($errorMessage); ?>
    </div>
<?php endif; ?>

<?php // --- Summary Section --- ?>
<div class="row mb-4 g-3">
    <div class="col-md-6 col-lg-3">
        <div class="card text-center h-100 shadow-sm border-secondary">
            <div class="card-body d-flex flex-column justify-content-center">
                <h6 class="card-title text-muted small">جمع معادل ۷۵۰</h6>
                <p class="card-text fs-4 fw-bold mb-0 number-fa"><?php echo $summary['total_750_equivalent_formatted']; ?> <small>گرم</small></p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card text-center h-100 shadow-sm border-secondary">
             <div class="card-body d-flex flex-column justify-content-center">
                <h6 class="card-title text-muted small">جمع ارزش وزنی <small>(واقعی)</small></h6>
                <p class="card-text fs-4 fw-bold mb-0 number-fa"><?php echo $summary['total_weight_value_formatted']; ?> <small>ریال</small></p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card text-center h-100 shadow-sm border-secondary">
            <div class="card-body d-flex flex-column justify-content-center">
                <h6 class="card-title text-muted small">جمع ارزش سکه <small>(تخمینی)</small></h6>
                <p class="card-text fs-4 fw-bold mb-0 number-fa"><?php echo $summary['total_coin_value_formatted']; ?> <small>ریال</small></p>
            </div>
        </div>
    </div>
     <div class="col-md-6 col-lg-3">
        <div class="card text-center h-100 shadow-sm border-success">
            <div class="card-body d-flex flex-column justify-content-center">
                <h6 class="card-title text-muted small">جمع کل ارزش انبار <small>(بخش وزنی واقعی)</small></h6>
                <p class="card-text fs-4 fw-bold mb-0 number-fa"><?php echo $summary['overall_total_value_formatted']; ?> <small>ریال</small></p>
            </div>
         </div>
    </div>
</div> <?php // End Summary Row ?>

<?php // --- Capital Performance Table --- (اصلاح شده) ?>
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-balance-scale me-2"></i>تراز عملکرد موجودی (نسبت به سرمایه هدف)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm table-bordered text-center align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>نام محصول</th> <?php // تغییر از دسته‌بندی به نام محصول ?>
                        <th>موجودی هدف</th>
                        <th>موجودی فعلی</th>
                        <th>تراز</th>
                        <th style="width:10%;">درصد</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($capitalPerformanceData)): ?>
                        <?php foreach($capitalPerformanceData as $item): ?>
                        <?php
                            $statusClass = $item['status'] === 'shortage' ? 'table-danger' : 
                                        ($item['status'] === 'excess' ? 'table-warning' : 'table-success');
                            $statusIcon = $item['status'] === 'shortage' ? 'fa-arrow-down text-danger' : 
                                        ($item['status'] === 'excess' ? 'fa-arrow-up text-warning' : 'fa-check text-success');
                            $statusText = $item['status'] === 'shortage' ? 'کمبود' : 
                                        ($item['status'] === 'excess' ? 'مازاد' : 'نرمال');
                        ?>
                        <tr class="<?php echo $statusClass; ?>">
                            <td><?php echo Helper::escapeHtml($item['product_name'] ?? ''); ?></td>
                            <td class="number-fa"><?php echo $item['target_formatted'] ?? '-'; ?></td>
                            <td class="number-fa"><?php echo $item['current_formatted'] ?? '-'; ?></td>
                            <td class="number-fa fw-bold"><?php echo $item['balance_formatted'] ?? '-'; ?></td>
                            <td>
                                <div class="progress" style="height: 22px;">
                                    <?php 
                                        // محدود کردن درصد به حداکثر 200% برای نمایش در نوار پیشرفت
                                        $progressPercent = min((float)($item['balance_percent'] ?? 0), 200);
                                        $progressClass = $item['status'] === 'shortage' ? 'bg-danger' : 
                                                        ($item['status'] === 'excess' ? 'bg-warning' : 'bg-success');
                                    ?>
                                    <div class="progress-bar <?php echo $progressClass; ?>" role="progressbar" 
                                        style="width: <?php echo $progressPercent; ?>%;" 
                                        aria-valuenow="<?php echo $progressPercent; ?>" aria-valuemin="0" aria-valuemax="200">
                                        <?php echo Helper::formatPersianNumber($item['balance_percent'] ?? 0, 0); ?>%
                                    </div>
                                </div>
                            </td>
                            <td><i class="fas <?php echo $statusIcon; ?> me-1"></i> <?php echo $statusText; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center p-3">
                                <p>هیچ محصولی با موجودی هدف تعریف شده وجود ندارد.</p>
                                <p class="small">برای مشاهده این بخش، مقادیر «سرمایه تعدادی» یا «سرمایه وزنی» را در تعریف محصولات تنظیم کنید.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php // --- Weight Inventory Table --- ?>
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
         <h5 class="mb-0"><i class="fas fa-weight-hanging me-2"></i>موجودی وزنی</h5>
         <?php if (!$summary['value_method_is_real'] && !empty($summary['price_750_for_estimation'])): ?>
             <small class="text-muted">ارزش‌ها بر اساس قیمت ۱ گرم ۷۵۰: <?php echo Helper::formatRial($summary['price_750_for_estimation']); ?> محاسبه شده.</small>
         <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($weightInventory)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm table-bordered text-center align-middle mb-0 small">
                     <thead class="table-light">
                        <tr>
                            <th style="width:15%;">عیار</th>
                            <th style="width:20%;">وزن واقعی<br><small>(گرم)</small></th>
                            <th style="width:20%;">معادل ۷۵۰<br><small>(گرم)</small></th>
                             <?php if ($summary['value_method_is_real']): ?>
                                 <th style="width:20%;">میانگین خرید<br><small>(ریال/گرم)</small></th>
                             <?php endif; ?>
                             <th style="width: <?php echo $summary['value_method_is_real'] ? '25%' : '40%'; ?>;">ارزش کل<br><small>(ریال)</small></th>
                         </tr>
                    </thead>
                    <tbody>
                       <?php foreach ($weightInventory as $item): ?>
                            <tr class="<?php echo ((float)($item['total_weight_grams'] ?? 0) < 0) ? 'table-danger' : ''; ?>">
                                <td class="fw-bold number-fa"><?php echo Helper::formatPersianNumber($item['carat'] ?? '-', 0); ?></td>
                                <td class="number-fa"><?php echo Helper::formatPersianNumber($item['total_weight_grams'] ?? '-', 3); ?></td>
                                <td class="number-fa"><?php echo Helper::formatPersianNumber($item['equivalent_750'] ?? '-', 3); ?></td>
                                 <?php if ($summary['value_method_is_real']): ?>
                                    <td class="number-fa small">
                                         <?php echo Helper::formatRial($item['avg_buy_price'] ?? 0); ?>
                                     </td>
                                 <?php endif; ?>
                                <td class="number-fa text-end fw-bold">
                                    <?php echo Helper::formatRial($item['value_display_raw'] ?? 0); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                     <tfoot class="table-light fw-bold">
                         <tr>
                             <th>جمع کل</th>
                             <th class="number-fa"><?php echo Helper::formatPersianNumber(array_sum(array_column($weightInventory, 'total_weight_grams')), 3); ?> <small>گرم</small></th>
                             <th class="number-fa"><?php echo $summary['total_750_equivalent_formatted']; ?> <small>گرم</small></th>
                             <th colspan="<?php echo $summary['value_method_is_real'] ? 2 : 1; ?>" class="number-fa text-end">
                                <?php echo $summary['total_weight_value_formatted']; ?>
                             </th>
                         </tr>
                         <tr>
                             <th colspan="2">میانگین وزنی خرید هر گرم طلا</th>
                             <th colspan="<?php echo $summary['value_method_is_real'] ? 3 : 2; ?>" class="number-fa fw-bold text-primary">
                                 <?php echo ($overallAvgBuyPrice1g > 0) ? Helper::formatRial($overallAvgBuyPrice1g) . ' <small>(هر گرم)</small>' : '-'; ?>
                             </th>
                         </tr>
                         <tr>
                             <th colspan="2">قیمت مظنه فعلی (API)</th>
                             <th colspan="<?php echo $summary['value_method_is_real'] ? 3 : 2; ?>" class="number-fa fw-bold text-info">
                                 <?php echo isset($latestMazanehPrice) && $latestMazanehPrice > 0 ? Helper::formatRial($latestMazanehPrice) . ' <small>(هر گرم)</small>' : '-'; ?>
                             </th>
                         </tr>
                         <tr>
                             <th colspan="2">سود/زیان فعلی هر گرم</th>
                             <?php 
                                 $profitLoss = (isset($latestMazanehPrice) && $latestMazanehPrice > 0 && $overallAvgBuyPrice1g > 0) ? $latestMazanehPrice - $overallAvgBuyPrice1g : null;
                                 $profitLossClass = ($profitLoss > 0) ? 'text-success' : (($profitLoss < 0) ? 'text-danger' : '');
                             ?>
                             <th colspan="<?php echo $summary['value_method_is_real'] ? 3 : 2; ?>" class="number-fa fw-bold <?php echo $profitLossClass; ?>">
                                 <?php 
                                     if ($profitLoss !== null) {
                                         echo Helper::formatRial($profitLoss) . ' <small>(هر گرم)</small>';
                                         echo $profitLoss > 0 ? ' (سود)' : ($profitLoss < 0 ? ' (زیان)' : '');
                                     } else {
                                         echo '-';
                                     }
                                 ?>
                             </th>
                         </tr>
                     </tfoot>
                </table>
            </div>
        <?php elseif (!$errorMessage): // Show only if no general error ?>
            <p class="text-center text-muted p-3 mb-0">موجودی وزنی ثبت نشده است.</p>
        <?php endif; ?>
    </div>
</div>

<?php // --- Coin Inventory Table --- ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-coins me-2"></i>موجودی سکه</h5>
    </div>
    <div class="card-body p-0">
         <?php if (!empty($coinInventory)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm table-bordered text-center align-middle mb-0">
                     <thead class="table-light">
                        <tr>
                            <th>نوع سکه</th>
                            <th>تعداد</th>
                            <th>آخرین قیمت واحد<br><small>(ریال)</small></th>
                            <th>ارزش کل<br><small>(تخمینی - ریال)</small></th>
                        </tr>
                    </thead>
                     <tbody>
                         <?php foreach($coinInventory as $item): ?>
                         <?php
                         $coinKey = $item['coin_type'] ?? '';
                         $latestPrice = $latestCoinPrices[$coinKey] ?? null;
                         $quantity = (int)($item['quantity'] ?? 0);
                         $latestPriceFormatted = $latestPrice ? Helper::formatRial($latestPrice) : '-';
                         $estimatedValue = $latestPrice ? $latestPrice * $quantity : 0;
                         $estimatedValueFormatted = $latestPrice ? Helper::formatRial($estimatedValue) : '-';
                         ?>
                         <tr class="<?php echo ($quantity < 0) ? 'table-danger' : ''; ?>">
                             <td class="fw-bold"><?php echo $item['type_farsi'] ?? '-'; ?></td>
                             <td class="number-fa"><?php echo $item['quantity_formatted'] ?? '-'; ?> <small>عدد</small></td>
                             <td class="number-fa text-center"><?php echo $latestPriceFormatted; ?></td>
                             <td class="number-fa text-end fw-bold"><?php echo $estimatedValueFormatted; ?></td>
                         </tr>
                         <?php endforeach; ?>
                     </tbody>
                      <tfoot class="table-light fw-bold">
                          <tr>
                              <td colspan="3" class="text-start ps-2">مجموع ارزش تخمینی سکه‌ها:</td>
                              <td colspan="1" class="text-end number-fa"><?php echo $summary['total_coin_value_formatted']; ?></td>
                          </tr>
                      </tfoot>
                </table>
                 <p class="text-muted small mt-2 px-3">
                     * ارزش تخمینی سکه‌ها بر اساس آخرین قیمت API برای هر نوع سکه محاسبه شده است.
                 </p>
            </div>
         <?php elseif (!$errorMessage): ?>
             <p class="text-center text-muted p-3 mb-0">موجودی سکه ثبت نشده است.</p>
         <?php endif; ?>
    </div>
</div>

 <?php // --- Pending Receipts Table --- ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="fas fa-download me-1"></i> موارد منتظر دریافت <small>(خریداری شده، تحویل نگرفته‌ایم)</small></h5>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($pendingReceiptDetails)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>تاریخ</th><th>نوع</th><th>مقدار</th><th>عیار/سال</th><th>طرف حساب</th><th>معامله#</th><th class="text-center">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pendingReceiptDetails as $item): ?>
                        <tr>
                            <td class="text-nowrap"><?php echo Helper::formatPersianDate($item['transaction_date'] ?? ''); ?></td>
                            <td><?php echo Helper::escapeHtml($item['product_name'] ?? '-'); ?></td>
                            <td class="number-fa text-nowrap">
                                <?php if (($item['unit_of_measure'] ?? 'gram') === 'count'): ?>
                                    <?php echo Helper::formatPersianNumber($item['quantity'] ?? 0, 0); ?> عدد
                                <?php else: ?>
                                    <?php echo Helper::formatPersianNumber($item['weight_grams'] ?? 0, 3); ?> گرم
                                <?php endif; ?>
                            </td>
                            <td class="number-fa">
                                <?php if (($item['unit_of_measure'] ?? 'gram') === 'count'): ?>
                                    <?php echo Helper::escapeHtml($item['coin_year'] ?? '-'); ?>
                                <?php else: ?>
                                    <?php echo Helper::escapeHtml($item['carat'] ?? '-'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo Helper::escapeHtml($item['counterparty_name'] ?? '-'); ?></td>
                            <td><a href="<?php echo $baseUrl; ?>/app/transactions/edit/<?php echo (int)($item['transaction_id'] ?? 0); ?>" target="_blank">#<?php echo (int)($item['transaction_id'] ?? 0); ?></a></td>
                            <td class="text-center">
                                <form action="<?php echo $baseUrl; ?>/app/transactions/complete-delivery/<?php echo (int)($item['transaction_id'] ?? 0); ?>/receipt" method="POST" class="d-inline" onsubmit="return confirm('آیا دریافت کالای معامله #<?php echo (int)($item['transaction_id'] ?? 0); ?> را تایید می‌کنید؟ موجودی به‌روز خواهد شد.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <button type="submit" class="btn btn-sm btn-success py-0 px-2" data-bs-toggle="tooltip" title="تایید دریافت و افزودن به موجودی">
                                        تایید دریافت
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!$errorMessage): ?>
            <p class="text-center text-muted p-3 mb-0">موردی برای دریافت وجود ندارد.</p>
        <?php endif; ?>
    </div>
</div>

<?php // --- Pending Deliveries Table --- ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-warning">
        <h5 class="mb-0"><i class="fas fa-upload me-1"></i> موارد منتظر تحویل <small>(فروخته شده، تحویل نداده‌ایم)</small></h5>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($pendingDeliveryDetails)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>تاریخ</th><th>نوع</th><th>مقدار</th><th>عیار/سال</th><th>طرف حساب</th><th>معامله#</th><th class="text-center">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pendingDeliveryDetails as $item): ?>
                        <tr>
                            <td class="text-nowrap"><?php echo Helper::formatPersianDate($item['transaction_date'] ?? ''); ?></td>
                            <td><?php echo Helper::escapeHtml($item['product_name'] ?? '-'); ?></td>
                            <td class="number-fa text-nowrap">
                                <?php if (($item['unit_of_measure'] ?? 'gram') === 'count'): ?>
                                    <?php echo Helper::formatPersianNumber($item['quantity'] ?? 0, 0); ?> عدد
                                <?php else: ?>
                                    <?php echo Helper::formatPersianNumber($item['weight_grams'] ?? 0, 3); ?> گرم
                                <?php endif; ?>
                            </td>
                            <td class="number-fa">
                                <?php if (($item['unit_of_measure'] ?? 'gram') === 'count'): ?>
                                    <?php echo Helper::escapeHtml($item['coin_year'] ?? '-'); ?>
                                <?php else: ?>
                                    <?php echo Helper::escapeHtml($item['carat'] ?? '-'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo Helper::escapeHtml($item['counterparty_name'] ?? '-'); ?></td>
                            <td><a href="<?php echo $baseUrl; ?>/app/transactions/edit/<?php echo (int)($item['transaction_id'] ?? 0); ?>" target="_blank">#<?php echo (int)($item['transaction_id'] ?? 0); ?></a></td>
                            <td class="text-center">
                                <form action="<?php echo $baseUrl; ?>/app/transactions/complete-delivery/<?php echo (int)($item['transaction_id'] ?? 0); ?>/delivery" method="POST" class="d-inline" onsubmit="return confirm('آیا تحویل کالای معامله #<?php echo (int)($item['transaction_id'] ?? 0); ?> را تایید می‌کنید؟');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning py-0 px-2 text-dark" data-bs-toggle="tooltip" title="تایید تحویل">
                                        تایید تحویل
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!$errorMessage): ?>
            <p class="text-center text-muted p-3 mb-0">موردی برای تحویل وجود ندارد.</p>
        <?php endif; ?>
    </div>
</div>

<!-- اضافه کردن اسکریپت JS برای ابزارهای راهنما (tooltip) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // فعال‌سازی tooltip های بوت‌استرپ
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // اگر پیام‌های جدید داریم، آن‌ها را نمایش می‌دهیم
    if (typeof window.Messages !== 'undefined' && typeof window.showMessage === 'function') {
        const urlParams = new URLSearchParams(window.location.search);
        const messageType = urlParams.get('message');
        const messageAction = urlParams.get('action');
        
        if (messageType && window.Messages[messageType]) {
            window.showMessage(window.Messages[messageType], messageAction === 'error' ? 'error' : 'success');
        }
    }
});
</script>