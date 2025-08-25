<?php
/**
 * Template: src/views/dashboard/index.php
 * Main application dashboard.
 * Receives data via $viewData array from DashboardController.
 */

 use App\Utils\Helper; // Use the Helper class
 use Morilog\Jalali\Jalalian; // Add Jalalian namespace

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'داشبورد';
$dashboardData = $viewData['dashboard_data'] ?? []; // Contains all dashboard sections
$dashboardError = $viewData['dashboard_error'] ?? null; // General loading error
$successMessage = $viewData['flashMessage']['text'] ?? null; // Get flash message (using default key)
$successType = $viewData['flashMessage']['type'] ?? 'info'; // Get flash message type
$baseUrl = $viewData['baseUrl'] ?? '';
$user = $viewData['user'] ?? ($viewData['loggedInUser'] ?? null); // اصلاح برای گرفتن user از کنترلر جدید

// --- Extract specific dashboard data sections with fallbacks ---
$weightInventoryItems = $dashboardData['weight_inventory_items'] ?? [];
$total750Equivalent = $dashboardData['total_750_equivalent'] ?? 0; // مقدار خام
// $total750EquivalentFormatted = $dashboardData['total_750_equivalent_formatted'] ?? Helper::formatNumber(0, 3); // حذف شد
$coinInventoryItems = $dashboardData['coin_inventory_items'] ?? [];
$bankCashBalance = $dashboardData['bank_cash_balance'] ?? 0; // مقدار خام
// $bankCashBalanceFormatted = $dashboardData['bank_cash_balance_formatted'] ?? Helper::formatRial(0); // حذف شد

$recentSoldItems = $dashboardData['recent_sold_items'] ?? [];
$debtorsList = $dashboardData['debtors_list'] ?? [];
$creditorsList = $dashboardData['creditors_list'] ?? [];
$pendingReceiptSummary = $dashboardData['pending_receipt_summary'] ?? [];
$pendingDeliverySummary = $dashboardData['pending_delivery_summary'] ?? [];
$recentTransactions = $dashboardData['recent_transactions'] ?? [];
$recentPayments = $dashboardData['recent_payments'] ?? ($viewData['recent_payments'] ?? []); // اطمینان از وجود recent_payments

// --- Prepare Data for Charts --- 

// 1. Weight Inventory Doughnut Chart Data (Using Real Data)
$weightLabels = [];
$weightData = [];
$weightBackgroundColors = ['#DAA520', '#B8860B', '#FFD700', '#F0E68C', '#EEE8AA', '#BDB76B']; // Gold shades
if (!empty($weightInventoryItems)) {
    foreach ($weightInventoryItems as $index => $item) {
        $weightLabels[] = 'عیار ' . ($item['carat'] ?? '?'); // نمایش مستقیم عیار
        $weightData[] = abs($item['total_weight_grams'] ?? 0);
    }
}

// 2. Monthly Transactions Line Chart Data (Placeholder - Needs data from Controller)
// These variables should be populated in the Controller and passed via $viewData
$monthlyChartData = $dashboardData['monthly_chart_data'] ?? ($viewData['monthly_chart_data'] ?? ['labels' => [], 'data' => []]); // اطمینان از وجود

?>

<?php // Add Chart.js and AOS CSS ?>
<link rel="stylesheet" href="css/aos.css" />
<link rel="stylesheet" href="css/style.css">
<?php // Custom Dashboard Styles ?>
<style>
    body { background-color: #f8f9fa; } /* Light background for dashboard */
    .stat-card {
        background: linear-gradient(135deg, #495057 0%, #343a40 100%); /* Dark Gray Gradient */
        color: #fff;
        border-radius: 0.5rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem; /* Consistent margin */
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.15); }
    .stat-card .stat-icon {
        font-size: 2rem;
        color: rgba(255, 215, 0, 0.8); /* Gold icon */
        opacity: 0.8;
        margin-bottom: 0.5rem;
    }
    .stat-card .stat-label {
        font-size: 0.9rem;
        color: #ced4da;
        margin-bottom: 0.25rem;
        display: block;
    }
    .stat-card .stat-value {
        font-size: 1.75rem;
        font-weight: 600;
        color: #fff;
        direction: ltr; /* Keep numbers LTR */
        text-align: right;
    }
    .stat-card .stat-unit { font-size: 0.8rem; font-weight: normal; margin-left: 4px; opacity: 0.8; }

    .card { border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-radius: 0.5rem; }
    .card-header { background-color: #fff; border-bottom: 1px solid #e9ecef; font-weight: 600; color: #343a40; padding: 0.8rem 1.25rem; }
    .list-group-item { border-color: rgba(0,0,0,0.05); }
    .table { font-size: 0.9rem; }
    .badge { font-size: 0.75em; padding: 0.4em 0.6em; }

    /* Chart specific styles */
    #weightInventoryChart, #monthlyTransactionsChart { max-height: 280px; width: 100% !important; }
</style>

<?php // --- Display Potential Welcome/Error Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-<?php echo htmlspecialchars($successType, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show shadow-sm border-0">
        <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($dashboardError): ?>
    <div class="alert alert-danger shadow-sm border-0"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($dashboardError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php // --- Welcome Message --- ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2 class="m-0 fw-normal">سلام, <?php echo htmlspecialchars($user['name'] ?? ($user['username'] ?? 'کاربر'), ENT_QUOTES, 'UTF-8'); ?> عزیز!</h2>
        <span class="text-muted small"><i class="far fa-calendar-alt me-1"></i>
        <?php 
        $date = new DateTime();
        $formatter = new IntlDateFormatter(
            'fa_IR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            null,
            IntlDateFormatter::TRADITIONAL,
            'EEEE d MMMM y'
        );
        echo $formatter->format($date);
        ?>
        </span>
</div>

<?php // --- Top Row: Key Stat Cards --- ?>
<div class="row mb-4">
    <div class="col-md-6 col-lg-4" data-aos="fade-up">
        <div class="stat-card shadow-sm">
            <div class="stat-icon"><i class="fas fa-balance-scale-right"></i></div>
            <span class="stat-label">موجودی طلای معادل ۷۵۰</span>
            <div class="stat-value">
                <span><?php echo Helper::formatPersianNumber($total750Equivalent, 3); ?></span><span class="stat-unit">گرم</span>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-card shadow-sm">
            <div class="stat-icon"><i class="fas fa-university"></i></div>
            <span class="stat-label">موجودی کل نقدی</span>
            <div class="stat-value">
                <span><?php echo Helper::formatPersianNumber($bankCashBalance, 0); ?></span><span class="stat-unit">ریال</span>
            </div>
        </div>
    </div>
    <?php // Optional: Add more stat cards here, e.g., total coins, pending value ?>
     <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
        <?php // Example: Total Coins Count ?>
        <div class="stat-card shadow-sm">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <span class="stat-label">تعداد کل سکه</span>
            <?php $totalCoins = 0; foreach($coinInventoryItems as $ci) { $totalCoins += (int)($ci['quantity'] ?? 0); } ?>
            <div class="stat-value">
                <span><?php echo Helper::formatPersianNumber($totalCoins, 0); ?></span><span class="stat-unit">عدد</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <?php // --- Column 1 (Right - Larger) --- ?>
    <div class="col-lg-7 order-lg-1">

        <?php // --- Weight Inventory Doughnut Chart --- ?>
        <div class="card mb-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-pie me-2 text-warning"></i>تفکیک موجودی وزنی (بر اساس عیار)</span>
                <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/app/inventory" class="btn btn-sm btn-outline-secondary py-1 px-2">جزئیات موجودی</a>
            </div>
            <div class="card-body p-3 text-center">
                <?php if (!empty($weightData)): ?>
                    <canvas id="weightInventoryChart"></canvas>
                <?php else: ?>
                    <p class="small text-muted my-5">داده‌ای برای نمایش نمودار موجودی وزنی یافت نشد.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php // --- Monthly Transactions Line Chart --- ?>
        <div class="card mb-4" data-aos="fade-up" data-aos-delay="200">
            <div class="card-header d-flex justify-content-between align-items-center">
                 <span><i class="fas fa-chart-line me-2 text-primary"></i>روند تراکنش‌ها (ماهانه)</span>
                 <?php /* Optional link: <a href="..." class="btn btn-sm btn-outline-secondary py-1 px-2">مشاهده همه</a> */ ?>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($monthlyChartData['labels']) && !empty($monthlyChartData['data'])): ?>
                    <canvas id="monthlyTransactionsChart"></canvas>
                <?php else: ?>
                    <p class="small text-muted my-5 text-center">داده‌ای برای نمایش نمودار روند تراکنش‌ها یافت نشد. <br><small>(نیاز به پیاده‌سازی در کنترلر)</small></p>
                <?php endif; ?>
            </div>
        </div>

         <?php // --- Commitments Card --- ?>
        <div class="card" data-aos="fade-up" data-aos-delay="300">
            <div class="card-header d-flex justify-content-between align-items-center">
                 <span><i class="fas fa-handshake me-2 text-info"></i>خلاصه تعهدات معلق</span>
                 <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/app/transactions?status=pending" class="btn btn-sm btn-outline-secondary py-1 px-2">مشاهده همه</a>
            </div>
            <div class="card-body p-3">
                <div class="row">
                    <?php // Pending Receipts ?>
                    <div class="col-md-6 border-end-md mb-3 mb-md-0 pe-md-3">
                        <h6 class="small fw-bold mb-2"><i class="fas fa-arrow-down text-success me-1"></i>باید دریافت کنیم<small>(از دیگران)</small></h6>
                        <?php if (!empty($pendingReceiptSummary)): ?>
                            <ul class="list-unstyled small mb-0">
                                <?php foreach ($pendingReceiptSummary as $item): ?>
                                    <li class="d-flex justify-content-between mb-1 pb-1 border-bottom">
                                        <span class="text-truncate product-type-display" data-product-type="<?php echo htmlspecialchars($item['product_category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['product_category'] ?? 'نامشخص', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <strong class="text-nowrap text-end ps-1">
                                            <?php if (!empty($item['total_weight_750']) && $item['total_weight_750'] > 0): ?>
                                                <span><?php echo Helper::formatPersianNumber($item['total_weight_750'], 3); ?></span><small class="ms-1">گرم</small>
                                            <?php elseif (!empty($item['total_quantity'])): ?>
                                                <span><?php echo Helper::formatPersianNumber($item['total_quantity'], 0); ?></span><small class="ms-1">عدد</small>
                                            <?php endif; ?>
                                        </strong>
                                     </li>
                                 <?php endforeach; ?>
                            </ul>
                         <?php else: ?> <p class="small text-muted mb-0">موردی یافت نشد.</p> <?php endif; ?>
                     </div>
                     <?php // Pending Deliveries ?>
                    <div class="col-md-6 ps-md-3">
                        <h6 class="small fw-bold mb-2"><i class="fas fa-arrow-up text-warning me-1"></i>باید تحویل دهیم<small>(به دیگران)</small></h6>
                         <?php if (!empty($pendingDeliverySummary)): ?>
                            <ul class="list-unstyled small mb-0">
                                <?php foreach ($pendingDeliverySummary as $item): ?>
                                    <li class="d-flex justify-content-between mb-1 pb-1 border-bottom">
                                        <span class="text-truncate product-type-display" data-product-type="<?php echo htmlspecialchars($item['product_category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['product_category'] ?? 'نامشخص', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <strong class="text-nowrap text-end ps-1">
                                            <?php if (!empty($item['total_weight_750']) && $item['total_weight_750'] > 0): ?>
                                                <span><?php echo Helper::formatPersianNumber($item['total_weight_750'], 3); ?></span><small class="ms-1">گرم</small>
                                            <?php elseif (!empty($item['total_quantity'])): ?>
                                                <span><?php echo Helper::formatPersianNumber($item['total_quantity'], 0); ?></span><small class="ms-1">عدد</small>
                                            <?php endif; ?>
                                        </strong>
                                     </li>
                                <?php endforeach; ?>
                            </ul>
                         <?php else: ?> <p class="small text-muted mb-0">موردی یافت نشد.</p> <?php endif; ?>
                    </div>
                </div>
            </div>
        </div> <?php // End Commitments Card ?>

    </div> <?php // End Col 1 ?>


     <?php // --- Column 2 (Left - Smaller) --- ?>
    <div class="col-lg-5 order-lg-2">

        <?php // --- Coin Inventory Card --- ?>
        <div class="card mb-4" data-aos="fade-up" data-aos-delay="50">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-coins me-2 text-warning"></i>موجودی سکه</span>
                <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/app/inventory" class="btn btn-sm btn-outline-secondary py-1 px-2">جزئیات موجودی</a>
            </div>
            <div class="card-body p-3">
                 <?php if (!empty($coinInventoryItems)): ?>
                    <table class="table table-sm table-hover small mb-0">
                        <tbody>
                            <?php foreach ($coinInventoryItems as $index => $item): ?>
                                <tr>
                                <td class="py-2 coin-type-display" data-coin-type="<?php echo htmlspecialchars($item['coin_type'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"> <i class="fas fa-circle fa-xs me-2" style="color: <?php echo $weightBackgroundColors[$index % count($weightBackgroundColors)] ?? '#6c757d'; ?>"></i> <?php echo htmlspecialchars($item['coin_type'] ?? '', ENT_QUOTES, 'UTF-8'); // نمایش کد نوع سکه ?></td>
                                    <td class="text-end fw-bold py-2"><?php echo Helper::formatPersianNumber($item['quantity'] ?? 0, 0); ?> <small>عدد</small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="small text-muted text-center my-3">موجودی سکه ثبت نشده است.</p>
                <?php endif; ?>
            </div>
        </div>

         <?php // --- Recent Transactions Card --- ?>
        <div class="card mb-4" data-aos="fade-up" data-aos-delay="150">
             <div class="card-header d-flex justify-content-between align-items-center">
             <span><i class="fas fa-history me-2 text-success"></i>آخرین معاملات (خرید و فروش)</span>
                <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/app/transactions" class="btn btn-sm btn-outline-secondary py-1 px-2">مشاهده همه</a>
             </div>
            <div class="card-body p-0">
                 <?php if (!empty($recentTransactions)): ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($recentTransactions as $tx): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-1 py-2 px-3">
                                <div class="text-truncate">
                                    <span class="badge bg-<?php echo ($tx['transaction_type'] ?? '') === 'buy' ? 'success' : (($tx['transaction_type'] ?? '') === 'sell' ? 'danger' : 'secondary'); ?> me-1"><?php echo htmlspecialchars(($tx['transaction_type'] ?? '') === 'buy' ? 'خرید' : 'فروش', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <strong><?php echo htmlspecialchars($tx['product_name'] ?? 'محصول نامشخص', ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php if (!empty($tx['counterparty_name'])): ?>
                                        <br><small class="text-muted"><i class="fas fa-user fa-xs me-1"></i><?php echo htmlspecialchars($tx['counterparty_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-nowrap text-end">
                                    <span class="fw-bold"><?php echo Helper::formatPersianNumber($tx['final_payable_amount_rials'] ?? 0, 0); ?></span>
                                    <small class="text-muted d-block date-display"><?php echo Helper::formatPersianDate($tx['transaction_date'] ?? ''); ?></small>
                                </div>
                            </li>
                         <?php endforeach; ?>
                     </ul>
                     <?php else: ?>
                    <p class="small text-muted text-center p-3 my-3">هیچ معامله اخیری یافت نشد.</p>
                <?php endif; ?>
             </div>
        </div>

        <?php // --- Debtors/Creditors Card --- ?>
        <?php // --- Recent Payments Card --- ?>
         <div class="card mb-4" data-aos="fade-up" data-aos-delay="250">
             <div class="card-header d-flex justify-content-between align-items-center">
             <span><i class="fas fa-money-bill-wave me-2 text-primary"></i>آخرین پرداخت‌ها</span>
                 <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/app/payments" class="btn btn-sm btn-outline-secondary py-1 px-2">مشاهده همه</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recentPayments)): ?>
                     <ul class="list-group list-group-flush small">
                        <?php foreach ($recentPayments as $payment): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-1 py-2 px-3">
                                <div class="text-truncate">
                                    <span class="badge bg-<?php echo ($payment['direction'] ?? '') === 'in' ? 'success' : (($payment['direction'] ?? '') === 'out' ? 'danger' : 'secondary'); ?> me-1 payment-direction-display" data-direction="<?php echo htmlspecialchars($payment['direction'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($payment['direction'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if (!empty($payment['contact_name'])): ?>
                                        <strong class="text-primary"><?php echo htmlspecialchars($payment['contact_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">مخاطب نامشخص</span>
                                    <?php endif; ?>
                                    <?php if (!empty($payment['description'])): ?>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars(mb_substr($payment['description'], 0, 30) . (mb_strlen($payment['description']) > 30 ? '...' : ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-nowrap text-end">
                                    <strong class="fw-bold"><?php echo Helper::formatPersianNumber($payment['amount_rials'] ?? 0, 0); ?></strong>
                                    <small class="text-muted d-block date-display"><?php echo Helper::formatPersianDate($payment['payment_date'] ?? ''); ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="small text-muted text-center p-3 my-3">هیچ پرداخت اخیری یافت نشد.</p>
                <?php endif; ?>
            </div>
        </div>


        <?php // --- Debtors and Creditors Cards --- ?>
        <div class="card mb-4" data-aos="fade-up" data-aos-delay="350">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-users me-2 text-danger"></i>بدهکاران و طلبکاران</span>
                 <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/app/contacts" class="btn btn-sm btn-outline-secondary py-1 px-2">مشاهده همه</a>
             </div>
             <div class="card-body p-3">
                <div class="row">
                <div class="col-md-6 border-end-md mb-3 mb-md-0 pe-md-3">
                        <h6 class="small fw-bold text-danger mb-2"><i class="fas fa-arrow-left me-1"></i>بدهکاران <small>(به ما)</small></h6>
                        <?php if (!empty($debtorsList)): ?>
                            <ul class="list-unstyled small mb-0">
                            <?php foreach ($debtorsList as $contact): ?>
                                    <li class="d-flex justify-content-between mb-1 pb-1 border-bottom">
                                        <span class="text-truncate" title="<?php echo htmlspecialchars($contact['name'] ?? 'ناشناس', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact['name'] ?? 'ناشناس', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <strong class="text-nowrap text-danger text-end ps-1"><?php echo Helper::formatPersianNumber($contact['balance'] ?? 0, 0); ?></strong>
                                    </li>
                                    <?php endforeach; ?>
                            </ul>
                                    <?php else: ?> <p class="small text-muted mb-0">موردی یافت نشد.</p> <?php endif; ?>
                     </div>
                            <div class="col-md-6 ps-md-3">
                        <h6 class="small fw-bold text-success mb-2"><i class="fas fa-arrow-right me-1"></i>طلبکاران <small>(از ما)</small></h6>
                          <?php if (!empty($creditorsList)): ?>
                             <ul class="list-unstyled small mb-0">
                             <?php foreach ($creditorsList as $contact): ?>
                                    <li class="d-flex justify-content-between mb-1 pb-1 border-bottom">
                                         <span class="text-truncate" title="<?php echo htmlspecialchars($contact['name'] ?? 'ناشناس', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact['name'] ?? 'ناشناس', ENT_QUOTES, 'UTF-8'); ?></span>
                                         <strong class="text-nowrap text-success text-end ps-1"><?php echo Helper::formatPersianNumber($contact['balance'] ?? 0, 0); ?></strong>
                                     </li>
                                     <?php endforeach; ?>
                              </ul>
                              <?php else: ?> <p class="small text-muted mb-0">موردی یافت نشد.</p> <?php endif; ?>
                        </div>
                     </div>
                 </div>
             </div>

    </div> <?php // End Col 2 ?>

    </div> <?php // End Main Row ?>

    <?php // --- JavaScript for Charts, AutoNumeric, and Dynamic Content --- ?>
<script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/js/chart.min.js"></script>
<script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/js/aos.js"></script>
<script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/js/messages.js"></script>

<script>
function initializeDashboard() {
    if (typeof AOS !== 'undefined') {
        AOS.init({ duration: 600, once: true });
    } else {
        console.error('AOS library is not loaded when initializeDashboard was called.');
    }

    // Translate product types, coin types, payment directions using MESSAGES
    if (typeof MESSAGES !== 'undefined') {
        // Product Types
        document.querySelectorAll('.product-type-display').forEach(el => {
            const productTypeKey = el.dataset.productType;
            if (productTypeKey && MESSAGES.product_types && MESSAGES.product_types[productTypeKey]) {
                el.textContent = MESSAGES.product_types[productTypeKey];
                if (el.title) {
                     el.title = MESSAGES.product_types[productTypeKey];
                }
            }
        });

        // Coin Types
        document.querySelectorAll('.coin-type-display').forEach(el => {
            const coinTypeKey = el.dataset.coinType;
            if (coinTypeKey && MESSAGES.coin_types && MESSAGES.coin_types[coinTypeKey]) { 
                const iconElement = el.querySelector('i');
                const iconColor = iconElement ? iconElement.style.color : '#6c757d';
                el.innerHTML = `<i class="fas fa-circle fa-xs me-2" style="color: ${iconColor}"></i> ${MESSAGES.coin_types[coinTypeKey]}`;
            }
        });

        // Payment Directions
        document.querySelectorAll('.payment-direction-display').forEach(el => {
            const directionKey = el.dataset.direction;
            if (directionKey && MESSAGES.payment_directions && MESSAGES.payment_directions[directionKey]) { 
                el.textContent = MESSAGES.payment_directions[directionKey];
            }
        });
    } else {
        console.error('MESSAGES object is not available for translations when initializeDashboard was called.');
    }

    // --- Chart Initializations ---
    const ctxWeight = document.getElementById('weightInventoryChart');
    if (ctxWeight && typeof Chart !== 'undefined' && <?php echo !empty($weightData) ? 'true' : 'false'; ?>) {
        new Chart(ctxWeight, {
                type: 'doughnut',
                data: {
                labels: <?php echo json_encode($weightLabels); ?>,
                    datasets: [{
                    label: 'موجودی وزنی',
                    data: <?php echo json_encode($weightData); ?>,
                    backgroundColor: <?php echo json_encode($weightBackgroundColors); ?>,
                    hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { family: 'Vazirmatn FD' } } } }
            }
        });
    }

    const ctxMonthly = document.getElementById('monthlyTransactionsChart');
    if (ctxMonthly && typeof Chart !== 'undefined' && <?php echo (!empty($monthlyChartData['labels']) && !empty($monthlyChartData['data'])) ? 'true' : 'false'; ?>) {
        new Chart(ctxMonthly, {
                    type: 'line',
                    data: {
                labels: <?php echo json_encode($monthlyChartData['labels']); ?>,
                        datasets: [{
                    label: 'تراکنش‌ها',
                    data: <?php echo json_encode($monthlyChartData['data']); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: {
                        responsive: true,
                        maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { font: { family: 'Vazirmatn FD' } } },
                    x: { ticks: { font: { family: 'Vazirmatn FD' } } }
                },
                plugins: { legend: { display: true, labels: { font: { family: 'Vazirmatn FD' } } } }
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', initializeDashboard);
</script>