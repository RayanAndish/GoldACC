<?php
/**
 * Template: src/views/dashboard/index.php
 * Main application dashboard.
 * Receives data via $viewData array from DashboardController.
 * REVISED: Comprehensive data display, formatting numbers/dates/names to Persian/escaped HTML.
 * Addresses 'محصول نامشخص' and 'مخاطب نامشخص' issues and zeros in summary cards.
 */

 use App\Utils\Helper; // Use the Helper class (assuming latest version is globally active)
 use Morilog\Jalali\Jalalian; // Add Jalalian namespace (if Morilog used directly in view, though Helper abstracts it).

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'داشبورد';
$dashboardData = $viewData['dashboard_data'] ?? []; // Contains all dashboard sections
$dashboardError = $viewData['dashboard_error'] ?? null; // General loading error
$successMessage = $viewData['flashMessage']['text'] ?? null; // Get flash message (using default key)
$successType = $viewData['flashMessage']['type'] ?? 'info'; // Get flash message type
$baseUrl = $viewData['baseUrl'] ?? '';
$user = $viewData['user'] ?? ($viewData['loggedInUser'] ?? null); // Current logged-in user info

// --- Extract specific dashboard data sections with fallbacks for safety ---
// Using safer variable names $d for dashboard_data passed to template (like before in my mockups)
$d = $dashboardData;

$weightInventoryItems = $d['weight_inventory_items'] ?? [];
// Ensure formatting comes from controller if needed, or explicitly call helper here
$total750EquivalentFormatted = $d['total_750_equivalent_formatted'] ?? Helper::formatPersianNumber(0, 3);

$coinInventoryItems = $d['coin_inventory_items'] ?? [];
$totalCoinQuantityFormatted = $d['total_coin_quantity_formatted'] ?? Helper::formatPersianNumber(0, 0);

$bankCashBalanceFormatted = $d['bank_cash_balance_formatted'] ?? Helper::formatRial(0);

$recentSoldItems = $d['recent_sold_items'] ?? [];
$recentTransactions = $d['recent_transactions'] ?? [];
$recentPayments = $d['recent_payments'] ?? [];

$debtorsList = $d['debtors_list'] ?? [];
$creditorsList = $d['creditors_list'] ?? [];
$pendingReceiptSummary = $d['pending_receipt_summary'] ?? [];
$pendingDeliverySummary = $d['pending_delivery_summary'] ?? [];

$totalBuyValueFormatted = $d['total_buy_value_formatted'] ?? Helper::formatRial(0);
$totalSellValueFormatted = $d['total_sell_value_formatted'] ?? Helper::formatRial(0);
$overallProfitLossFormatted = $d['overall_profit_loss_formatted'] ?? Helper::formatRial(0);
$overallProfitLossStatus = $d['overall_profit_loss_status'] ?? 'normal'; // profit, loss, normal

// --- Prepare Data for Charts (Passed directly from controller after processing) --- 
// Ensure your DashboardController formats and passes these ready.
$weightLabels = []; $weightData = []; // To be filled from $d['weight_inventory_items'] in JS for chart.
$weightBackgroundColors = ['#DAA520', '#B8860B', '#FFD700', '#F0E68C', '#EEE8AA', '#BDB76B']; // Gold shades.

// --- Prepare Data for Charts --- 
$monthlyChartData = $dashboardData['monthly_chart_data'] ?? ['labels' => [], 'buy_data' => [], 'sell_data' => []];
?>

<?php // Add Chart.js and AOS CSS - Keep existing structure of loading here. ?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/aos.css">
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/style.css">
<?php // Custom Dashboard Styles ?>
<style>
    /* Your existing CSS here, ensures custom styles like stat-card, card-header-dashboard etc. are kept. */
    body { background-color: #f8f9fa; }
    .stat-card {
        background: linear-gradient(135deg, #495057 0%, #343a40 100%);
        color: #fff;
        border-radius: 0.5rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.15); }
    .stat-card .stat-icon {
        font-size: 2rem;
        color: rgba(255, 215, 0, 0.8);
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
        direction: ltr; /* Keep numbers LTR for display in value tags */
        text-align: right;
    }
    .stat-card .stat-unit { font-size: 0.8rem; font-weight: normal; margin-left: 4px; opacity: 0.8; }

    .card { border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-radius: 0.5rem; }
    .card-header { background-color: #fff; border-bottom: 1px solid #e9ecef; font-weight: 600; color: #343a40; padding: 0.8rem 1.25rem; }
    .list-group-item { border-color: rgba(0,0,0,0.05); }
    .badge { font-size: 0.75em; padding: 0.4em 0.6em; }
    /* Ensure .number-fa class from global style is working as expected here too for specific elements outside stat-card */
    .number-fa { font-family: 'Vazirmatn', Tahoma, Arial, sans-serif !important; direction: ltr; } /* Add for robustness in case */

    /* Chart specific styles - adjust paths if you don't load from root /js folder*/
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
        $date = new DateTime(); // Create DateTime object (ensure correct timezone setting for server in config.php/index.php).
        $formatter = new IntlDateFormatter( // Requires PHP intl extension.
            'fa_IR', // Persian (Farsi) locale for Iran.
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'Asia/Tehran', // Explicitly set timezone.
            IntlDateFormatter::TRADITIONAL, // Use traditional calendars if possible (like Persian Calendar for fa_IR).
            'EEEE d MMMM y' // Format: Full weekday, day, full month name, year (e.g., جمعه ۱۰ مرداد ۱۴۰۴).
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
                <span><?php echo $total750EquivalentFormatted; ?></span><span class="stat-unit">گرم</span>
            </div>
            <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/app/inventory" class="text-decoration-none text-light small d-block mt-2">
                 جزئیات موجودی <i class="fas fa-arrow-left fa-xs me-1"></i>
            </a>
        </div>
    </div>
    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-card shadow-sm">
            <div class="stat-icon"><i class="fas fa-university"></i></div>
            <span class="stat-label">موجودی کل نقدی</span>
            <div class="stat-value">
                <span><?php echo $bankCashBalanceFormatted; ?></span><span class="stat-unit">ریال</span>
            </div>
            <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/app/bank-accounts" class="text-decoration-none text-light small d-block mt-2">
                 جزئیات حساب‌ها <i class="fas fa-arrow-left fa-xs me-1"></i>
            </a>
        </div>
    </div>
    <?php // Total Coins Count Card - using direct processed data ?>
     <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
        <div class="stat-card shadow-sm">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <span class="stat-label">تعداد کل سکه</span>
            <div class="stat-value">
                <span><?php echo $totalCoinQuantityFormatted; ?></span><span class="stat-unit">عدد</span>
            </div>
            <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/app/inventory" class="text-decoration-none text-light small d-block mt-2">
                 جزئیات موجودی <i class="fas fa-arrow-left fa-xs me-1"></i>
            </a>
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
                <?php if (!empty($weightInventoryItems)): ?>
                    <canvas id="weightInventoryChart"></canvas>
                <?php else: ?>
                    <p class="small text-muted my-5">موجودی وزنی (تکمیل شده) برای نمایش در نمودار یافت نشد.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php // --- Monthly Transactions Line Chart --- ?>
        <div class="card mb-4" data-aos="fade-up" data-aos-delay="200">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-line me-2 text-primary"></i>روند خرید و فروش (ماهانه)</span>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($monthlyChartData['labels']) && array_sum($monthlyChartData['buy_data']) + array_sum($monthlyChartData['sell_data']) > 0): ?>
                    <canvas id="monthlyTransactionsChart"></canvas>
                <?php else: ?>
                    <p class="small text-muted my-5 text-center">تراکنش تکمیل شده‌ای در ماه‌های اخیر برای نمایش در نمودار یافت نشد.</p>
                <?php endif; ?>
            </div>
        </div>
         <?php // --- Commitments Card --- (خلاصه تعهدات) ?>
        <div class="card" data-aos="fade-up" data-aos-delay="300">
            <div class="card-header d-flex justify-content-between align-items-center">
                 <span><i class="fas fa-handshake me-2 text-info"></i>خلاصه تعهدات معلق</span>
                 <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/app/transactions?status=pending" class="btn btn-sm btn-outline-secondary py-1 px-2">مشاهده همه</a>
            </div>
            <div class="card-body p-3">
                <div class="row">
                    <?php // Pending Receipts (باید دریافت کنیم) ?>
                    <div class="col-md-6 border-end-md mb-3 mb-md-0 pe-md-3">
                        <h6 class="small fw-bold mb-2"><i class="fas fa-arrow-down text-success me-1"></i>باید دریافت کنیم<small>(از دیگران)</small></h6>
                        <?php if (!empty($pendingReceiptSummary)): ?>
                            <ul class="list-unstyled small mb-0">
                                <?php foreach ($pendingReceiptSummary as $item): ?>
                                    <li class="d-flex justify-content-between mb-1 pb-1 border-bottom">
                                        <span class="text-truncate" title="<?php echo htmlspecialchars($item['item_name'] ?? 'نامشخص', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['item_name'] ?? 'نامشخص', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <strong class="text-nowrap text-end ps-1">
                                            <?php if (!empty($item['total_weight_750']) && $item['total_weight_750'] > 0): ?>
                                                <span><?php echo Helper::formatPersianNumber($item['total_weight_750'] ?? 0, 3); ?></span><small class="ms-1">گرم</small>
                                            <?php elseif (!empty($item['total_quantity'])): ?>
                                                <span><?php echo Helper::formatPersianNumber($item['total_quantity'] ?? 0, 0); ?></span><small class="ms-1">عدد</small>
                                            <?php endif; ?>
                                        </strong>
                                     </li>
                                 <?php endforeach; ?>
                            </ul>
                         <?php else: ?> <p class="small text-muted mb-0">موردی یافت نشد.</p> <?php endif; ?>
                     </div>
                     <?php // Pending Deliveries (باید تحویل دهیم) ?>
                    <div class="col-md-6 ps-md-3">
                        <h6 class="small fw-bold mb-2"><i class="fas fa-arrow-up text-warning me-1"></i>باید تحویل دهیم<small>(به دیگران)</small></h6>
                         <?php if (!empty($pendingDeliverySummary)): ?>
                            <ul class="list-unstyled small mb-0">
                                <?php foreach ($pendingDeliverySummary as $item): ?>
                                    <li class="d-flex justify-content-between mb-1 pb-1 border-bottom">
                                        <span class="text-truncate" title="<?php echo htmlspecialchars($item['item_name'] ?? 'نامشخص', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['item_name'] ?? 'نامشخص', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <strong class="text-nowrap text-end ps-1">
                                            <?php if (!empty($item['total_weight_750']) && $item['total_weight_750'] > 0): ?>
                                                <span><?php echo Helper::formatPersianNumber($item['total_weight_750'] ?? 0, 3); ?></span><small class="ms-1">گرم</small>
                                            <?php elseif (!empty($item['total_quantity'])): ?>
                                                <span><?php echo Helper::formatPersianNumber($item['total_quantity'] ?? 0, 0); ?></span><small class="ms-1">عدد</small>
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
                                <td class="py-2 coin-type-display" data-coin-type="<?php echo htmlspecialchars($item['coin_type'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"> <i class="fas fa-circle fa-xs me-2" style="color: <?php echo $weightBackgroundColors[$index % count($weightBackgroundColors)] ?? '#6c757d'; ?>"></i> <?php echo htmlspecialchars($item['type_farsi'] ?? ($item['coin_type'] ?? 'نامشخص'), ENT_QUOTES, 'UTF-8'); ?></td>
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

         <?php // --- Recent Transactions Card --- (آخرین معاملات (خرید و فروش)) ?>
          <div class="card mb-4" data-aos="fade-up" data-aos-delay="150">
             <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-history me-2 text-success"></i>آخرین معاملات</span>
                <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/app/transactions" class="btn btn-sm btn-outline-secondary py-1 px-2">مشاهده همه</a>
             </div>
            <div class="card-body p-0">
                 <?php if (!empty($recentTransactions)): ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($recentTransactions as $tx): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-1 py-2 px-3">
                                <div class="text-truncate">
                                    <span class="badge bg-<?php echo ($tx['transaction_type'] ?? '') === 'buy' ? 'success' : 'danger'; ?> me-1">
                                        <?php echo htmlspecialchars(($tx['transaction_type'] === 'buy' ? 'خرید' : 'فروش'), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <?php // نمایش نام محصول نمونه و "و موارد دیگر" اگر بیش از یک آیتم وجود داشته باشد ?>
                                    <strong><?php echo htmlspecialchars($tx['product_name'] ?? 'کالا', ENT_QUOTES, 'UTF-8'); ?> و موارد دیگر</strong>

                                    <?php if (!empty($tx['counterparty_name'])): ?>
                                        <br><small class="text-muted"><i class="fas fa-user fa-xs me-1"></i><?php echo htmlspecialchars($tx['counterparty_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-nowrap text-end">
                                    <span class="fw-bold"><?php echo Helper::formatRial($tx['final_payable_amount_rials'] ?? 0); ?></span>
                                    <small class="text-muted d-block"><?php echo Helper::formatPersianDate($tx['transaction_date'] ?? ''); ?></small>
                                </div>
                            </li>
                         <?php endforeach; ?>
                     </ul>
                 <?php else: ?>
                    <p class="small text-muted text-center p-3 my-3">هیچ معامله اخیری یافت نشد.</p>
                <?php endif; ?>
             </div>
        </div>

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
                                    <span class="badge bg-<?php echo ($payment['direction'] ?? '') === 'inflow' ? 'success' : (($payment['direction'] ?? '') === 'outflow' ? 'danger' : 'secondary'); ?> me-1 payment-direction-display" data-direction="<?php echo htmlspecialchars($payment['direction'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(($payment['direction_farsi'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if (!empty($payment['contact_name'])): ?>
                                        <strong class="text-dark me-1"><?php echo htmlspecialchars($payment['contact_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">مخاطب نامشخص</span>
                                    <?php endif; ?>
                                    <?php if (!empty($payment['description'])): ?>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars(mb_substr($payment['description'], 0, 30) . (mb_strlen($payment['description']) > 30 ? '...' : ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-nowrap text-end">
                                    <span class="fw-bold"><?php echo ($payment['amount_rials_formatted'] ?? Helper::formatRial(0)); ?></span>
                                    <small class="text-muted d-block date-display"><?php echo ($payment['payment_date_jalali'] ?? Helper::formatPersianDate('now')); ?></small>
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
                                        <strong class="text-nowrap text-danger text-end ps-1"><?php echo ($contact['balance_formatted'] ?? Helper::formatRial(0)); ?></strong>
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
                                    <li class="d-group-item d-flex justify-content-between mb-1 pb-1 border-bottom">
                                         <span class="text-truncate" title="<?php echo htmlspecialchars($contact['name'] ?? 'ناشناس', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact['name'] ?? 'ناشناس', ENT_QUOTES, 'UTF-8'); ?></span>
                                         <strong class="text-nowrap text-success text-end ps-1"><?php echo ($contact['balance_formatted'] ?? Helper::formatRial(0)); ?></strong>
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

<?php // --- JavaScript for Charts, AOS, and Dynamic Content (Ensure correct paths and global availability of Helper) --- ?>
<!-- Chart.js - For Chart visuals -->
<script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/js/chart.min.js"></script>
<!-- AOS - Animate On Scroll Library -->
<script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/js/aos.js"></script>
<!-- Your custom messages.js -->
<script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/js/messages.js"></script>


<script>
// Dashboard specific JavaScript for charts and other interactive elements.
// Ensure global 'Helper' is available if used within this script, or pass necessary functions.
document.addEventListener('DOMContentLoaded', function() {
    if (typeof AOS !== 'undefined') {
        AOS.init({ duration: 600, once: true });
    } else {
        console.warn('AOS library not found for dashboard animations.');
    }

    // Function to safely format Persian numbers in JS (mimics PHP Helper::formatPersianNumber)
    function formatPersianNumberJS(number, decimals = 0) {
        if (number === null || number === undefined || number === '') { return '-'; }
        number = parseFloat(number);
        if (isNaN(number)) { return '-'; }

        let formatted = number.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
            useGrouping: true // To ensure thousand separators.
        });
        
        const englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', ','];
        const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '/', '٬'];

        formatted = formatted.replace(/-/g, '_MINUS_').split('').map(char => { // Handle negative sign
             const index = englishDigits.indexOf(char);
             return index !== -1 ? persianDigits[index] : char;
        }).join('').replace(/_MINUS_/g, '-'); // Re-insert negative sign.

        return formatted;
    }

  // --- Chart Initializations ---
    const weightInventoryDataFromPHP = <?php echo json_encode($weightInventoryItems ?? []); ?>;
    const ctxWeight = document.getElementById('weightInventoryChart');
    
    if (ctxWeight && typeof Chart !== 'undefined' && weightInventoryDataFromPHP.length > 0) {
        const weightLabels = [];
        const weightData = [];
        const weightBackgroundColors = ['#DAA520', '#B8860B', '#FFD700', '#F0E68C', '#EEE8AA', '#BDB76B'];

        weightInventoryDataFromPHP.forEach((item, index) => {
            weightLabels.push('عیار ' + (item['carat'] || '?'));
            weightData.push(parseFloat(item['total_weight_grams'] || 0));
        });

        new Chart(ctxWeight, {
            type: 'doughnut',
            data: {
                labels: weightLabels,
                datasets: [{
                    label: 'موجودی وزنی',
                    data: weightData,
                    backgroundColor: weightBackgroundColors,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }


 // (اصلاح شده) Monthly Transactions Line Chart
    const ctxMonthly = document.getElementById('monthlyTransactionsChart');
    if (ctxMonthly && typeof Chart !== 'undefined' && <?php echo !empty($monthlyChartData['labels']) ? 'true' : 'false'; ?>) {
        new Chart(ctxMonthly, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthlyChartData['labels']); ?>,
                datasets: [
                    {
                        label: 'مجموع فروش',
                        data: <?php echo json_encode($monthlyChartData['sell_data']); ?>,
                        borderColor: 'rgb(220, 53, 69)', // Red for Sell
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.1,
                        fill: true
                    },
                    {
                        label: 'مجموع خرید',
                        data: <?php echo json_encode($monthlyChartData['buy_data']); ?>,
                        borderColor: 'rgb(25, 135, 84)', // Green for Buy
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        tension: 0.1,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
        // **کد جدید برای فعال‌سازی دستی منو**
    var definitionsDropdown = document.getElementById('definitionsDropdown');
    if (definitionsDropdown) {
        console.log('Dropdown element found. Initializing manually.');
        // ایجاد یک نمونه جدید از Dropdown بوت‌استرپ برای عنصر منو
        var bsDropdown = new bootstrap.Dropdown(definitionsDropdown);
    } else {
        console.error('Dropdown element with ID "definitionsDropdown" not found!');
    }
});
</script>