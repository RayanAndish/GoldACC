<?php

namespace App\Controllers;

use App\Core\Database; // اختیاری
use App\Core\ViewRenderer; // اختیاری
use Monolog\Logger; // اختیاری
use PDO; // اختیاری
use Throwable; // برای گرفتن Exception ها

// استفاده از Repository ها برای واکشی داده های داشبورد
use App\Repositories\InventoryRepository; // برای موجودی وزنی
use App\Repositories\CoinInventoryRepository; // برای موجودی سکه
use App\Repositories\BankAccountRepository; // برای موجودی نقدی بانک
use App\Repositories\ContactRepository; // برای بدهکاران/طلبکاران
use App\Repositories\TransactionRepository; // برای آخرین معاملات و خلاصه تعهدات
use App\Repositories\PaymentRepository; // برای آخرین پرداخت ها

// use App\Utils\Helper; // حذف شد
use App\Services\LicenseService; // استفاده از LicenseService
use App\Services\AuthService;

/**
 * کلاس DashboardController برای نمایش صفحه داشبورد اصلی.
 * اطلاعات خلاصه و آماری را از Repository های مختلف واکشی می کند.
 * از AbstractController ارث می برد.
 */
class DashboardController extends AbstractController {

    // وابستگی به Repository های مختلف برای واکشی داده های داشبورد
    protected InventoryRepository $inventoryRepository;
    protected CoinInventoryRepository $coinInventoryRepository;
    protected BankAccountRepository $bankAccountRepository; // اگر نیاز به اطلاعات حساب ها باشد
    protected ContactRepository $contactRepository; // اگر نیاز به اطلاعات مخاطبین باشد
    protected TransactionRepository $transactionRepository; // اگر نیاز به اطلاعات معاملات باشد
    protected PaymentRepository $paymentRepository; // اگر نیاز به اطلاعات پرداخت ها باشد
    protected LicenseService $licenseService;
    protected AuthService $authService;


    /**
     * Constructor.
     * وابستگی ها از Front Controller تزریق می شوند.
     * نیاز به تمام Repository هایی دارد که اطلاعاتشان در داشبورد نمایش داده می شود.
     *
     * @param PDO $db
     * @param Logger $logger
     * @param array $config
     * @param ViewRenderer $viewRenderer
     * @param array $services
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services // فقط یک آرایه
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        $required = [
            'inventoryRepository' => InventoryRepository::class,
            'coinInventoryRepository' => CoinInventoryRepository::class,
            'bankAccountRepository' => BankAccountRepository::class,
            'contactRepository' => ContactRepository::class,
            'transactionRepository' => TransactionRepository::class,
            'paymentRepository' => PaymentRepository::class,
            'licenseService' => LicenseService::class,
            'authService' => AuthService::class,
        ];
        foreach ($required as $prop => $class) {
            if (!isset($services[$prop]) || !$services[$prop] instanceof $class) {
                throw new \Exception("{$class} not found or invalid for DashboardController.");
            }
            $this->$prop = $services[$prop];
        }
    }

    /**
     * نمایش صفحه داشبورد.
     * منطق از src/controllers/dashboard.php گرفته شده.
     * مسیر: /app/dashboard (GET) یا / (GET) اگر روت اصلی باشد.
     */
    public function index(): void {
        // بررسی لاگین بودن کاربر و معتبر بودن لایسنس
        $this->requireLogin();
        // $this->requireLicense(); // غیرفعال کردن چک لایسنس

        $user = $this->authService->getCurrentUser();

        $pageTitle = "داشبورد";
        $dashboardData = []; // آرایه برای نگهداری تمام داده های واکشی شده برای ویو
        $dashboardError = null; // پیام خطای کلی برای داشبورد

        try {
            // 1. موجودی وزنی و معادل 750 (با استفاده از InventoryRepository)
            // InventoryRepository نیاز به متدی برای واکشی موجودی وزنی دارد.
            $weightInventoryItems = $this->inventoryRepository->getAllInventorySummary();

            // محاسبه معادل 750 در Controller (چون منطق نمایش است)
            $total750Equivalent = 0;
            foreach ($weightInventoryItems as &$item) {
                // اطمینان از وجود و عددی بودن مقادیر قبل از محاسبه
                 if (isset($item['total_weight_grams'], $item['carat']) && is_numeric($item['total_weight_grams']) && is_numeric($item['carat'])) {
                      $item['equivalent_750'] = ((float)$item['total_weight_grams'] * (int)$item['carat']) / 750;
                      $total750Equivalent += $item['equivalent_750'];
                    } else {
                      // لاگ هشدار اگر داده ناقص یا نامعتبر بود
                     $this->logger->warning("Invalid or missing data for weight inventory item.", ['item' => $item]);
                     $item['equivalent_750'] = 0; // پیش فرض
                    }
            }
            unset($item);
            $dashboardData['weight_inventory_items'] = $weightInventoryItems;
            $dashboardData['total_750_equivalent'] = $total750Equivalent;

            // 2. موجودی سکه (با استفاده از CoinInventoryRepository)
            // CoinInventoryRepository نیاز به متدی برای واکشی موجودی سکه دارد.
            $coinInventoryItems = $this->coinInventoryRepository->getAllCoinInventory(); // فرض کنیم متد getAllCoinInventory در Repository وجود دارد.

            // ترجمه نوع سکه و فرمت دهی برای نمایش در ویو
            foreach ($coinInventoryItems as &$ci) {
            }
            unset($ci);
            $dashboardData['coin_inventory_items'] = $coinInventoryItems;

            // 3. موجودی نقدی بانک (با استفاده از BankAccountRepository)
            // BankAccountRepository نیاز به متدی برای محاسبه مجموع موجودی فعلی دارد.
            $bankCashBalance = $this->bankAccountRepository->getTotalCurrentBalance(); // فرض کنیم متد getTotalCurrentBalance در Repository وجود دارد.
            $dashboardData['bank_cash_balance'] = (float)$bankCashBalance;

            // 4. بدهکاران / طلبکاران (با استفاده از ContactRepository)
            // ContactRepository نیاز به متدی برای واکشی لیست بدهکاران و طلبکاران با مانده دارد.
            // این نیاز به کوئری JOIN و UNION پیچیده‌ای دارد که قبلاً در کد قدیمی بود.
            // یک متد اختصاصی مانند getDebtorsAndCreditors($threshold = 0.01) در ContactRepository پیاده سازی کنید.
            $balanceThreshold = $this->config['app']['balance_threshold'] ?? 0.01; // آستانه مانده از config
            $debtorsAndCreditors = $this->contactRepository->getDebtorsAndCreditors($balanceThreshold); // فرض کنیم این متد وجود دارد و لیست مرتب شده برگرداند.

            // فرمت دهی مانده ها برای نمایش در ویو
            $dashboardData['debtors_list'] = $debtorsAndCreditors['debtors'];
            $dashboardData['creditors_list'] = $debtorsAndCreditors['creditors'];


            // 5. آخرین فروش‌ها (با استفاده از TransactionRepository)
            // TransactionRepository نیاز به متدی برای واکشی آخرین معاملات از نوع 'sell' دارد.
            $recentSales = $this->transactionRepository->getLatestTransactions('sell', 5); // فرض کنیم getLatestTransactions(type, limit) وجود دارد.

            // پردازش و فرمت دهی برای نمایش در ویو
            foreach ($recentSales as &$tx) {
            }
            unset($tx);
            $dashboardData['recent_sold_items'] = $recentSales;


            // 6. آخرین معاملات (ترکیب خرید و فروش) (با استفاده از TransactionRepository)
            // TransactionRepository نیاز به متدی برای واکشی آخرین معاملات (بدون فیلتر نوع) دارد.
            $recentTransactions = $this->transactionRepository->getLatestTransactions(null, 5); // فرض کنیم null برای همه انواع است.

            // پردازش و فرمت دهی برای نمایش
            foreach ($recentTransactions as &$tx) {
            }
            unset($tx);
            $dashboardData['recent_transactions'] = $recentTransactions;


            // 7. آخرین پرداخت‌ها (با استفاده از PaymentRepository)
            // PaymentRepository نیاز به متدی برای واکشی آخرین پرداخت ها دارد.
            $recentPayments = $this->paymentRepository->getLatestPayments(5); // فرض کنیم getLatestPayments(limit) وجود دارد.

            // پردازش و فرمت دهی برای نمایش
            foreach ($recentPayments as &$p) {
            }
            unset($p);
            $dashboardData['recent_payments'] = $recentPayments;


            // 8. خلاصه تعهدات تحویل (با استفاده از TransactionRepository)
            // TransactionRepository نیاز به متدهایی برای خلاصه کردن معاملات pending_receipt و pending_delivery دارد.
            // مثلا getPendingReceiptSummary() و getPendingDeliverySummary()
            $dashboardData['pending_receipt_summary'] = $this->transactionRepository->getPendingSummary('pending_receipt'); // فرض کنیم متد وجود دارد
            $dashboardData['pending_delivery_summary'] = $this->transactionRepository->getPendingSummary('pending_delivery'); // فرض کنیم متد وجود دارد

            // پردازش برای نمایش (ترجمه نوع محصول، فرمت دهی وزن/تعداد)
            foreach ($dashboardData['pending_receipt_summary'] as &$item) {
            } unset($item);
            foreach ($dashboardData['pending_delivery_summary'] as &$item) {
            } unset($item);


        } catch (Throwable $e) {
            // گرفتن هرگونه Exception از Repository ها (که قبلاً لاگ شده‌اند)
            $this->logger->error("Exception during dashboard data fetching.", ['exception' => $e]);
            $dashboardError = "خطا در بارگذاری اطلاعات داشبورد.";
            if ($this->config['app']['debug']) {
                $dashboardError .= " Detail: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
            // در صورت خطا، لیست ها و مقادیر آماری خالی یا صفر خواهند ماند (مقادیر اولیه).
        }

        // رندر View داشبورد
        // فرض می‌کنیم View در src/views/dashboard/index.php قرار دارد.
        $this->render('dashboard/index', [
            'page_title' => $pageTitle,
            'dashboard_data' => $dashboardData, // ارسال تمام داده های واکشی شده و پردازش شده
            'dashboard_error' => $dashboardError,
            'user' => $user,
             // پیام های Flash Message در Layout مدیریت می شوند.
        ]);
    }

    // سایر متدهای Controller مربوط به داشبورد (اگر وجود دارند)
}