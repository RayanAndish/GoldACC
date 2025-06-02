<?php

namespace App\Controllers;

use App\Repositories\InitialBalanceRepository;
use App\Repositories\ProductRepository;
use App\Repositories\BankAccountRepository;
use App\Services\InitialBalanceService;
use App\Services\InventoryCalculationService;
use App\Utils\Helper;
use App\Core\ViewRenderer;
use Monolog\Logger;
use Throwable;
use PDO;

// FIX: ارث‌بری از AbstractController برای استفاده از معماری اصلی برنامه
class InitialBalanceController extends AbstractController
{
    // FIX: تعریف پراپرتی‌ها برای وابستگی‌های خاص این کنترلر
    private InitialBalanceRepository $initialBalanceRepository;
    private ProductRepository $productRepository;
    private BankAccountRepository $bankAccountRepository;
    private InitialBalanceService $initialBalanceService;
    private InventoryCalculationService $inventoryCalculationService;

    /**
     * FIX: تغییر کامل سازنده برای هماهنگی با معماری اصلی و تزریق وابستگی‌ها
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);

        // دریافت وابستگی‌های مورد نیاز از کانتینر سرویس‌ها
        $this->initialBalanceRepository = $services['initialBalanceRepository'];
        $this->productRepository = $services['productRepository'];
        $this->bankAccountRepository = $services['bankAccountRepository'];
        $this->initialBalanceService = $services['initialBalanceService'];
        $this->inventoryCalculationService = $services['inventoryCalculationService'];
        
        $this->logger->debug("InitialBalanceController initialized correctly.");
    }
    
    // FIX: حذف متدهای تکراری (requireLogin, render, setFlashMessage, redirect)
    // این متدها از کلاس پدر (AbstractController) به ارث می‌رسند.

    /**
     * نمایش لیست موجودی‌های اولیه
     */
    public function index(): void {
        $this->requireLogin();
        
        $pageTitle = "تعریف موجودی اولیه و سرمایه هدف";
        $initialBalances = [];
        $bankAccounts = [];
        $errorMessage = null;
        
        try {
            $initialBalances = $this->initialBalanceRepository->getAllInitialBalances();
            $bankAccounts = $this->bankAccountRepository->getAll();
            
            // این منطق باید در سرویس یا ریپازیتوری انجام شود اما فعلا برای حفظ عملکرد قبلی باقی می‌ماند
            foreach ($initialBalances as &$balance) {
                $performance = $this->initialBalanceService->calculatePerformanceBalance($balance['product_id']);
                $balance['performance_balance'] = $performance;
            }
            unset($balance);
            
        } catch (Throwable $e) {
            $this->logger->error("Error fetching initial balances", ['exception' => $e]);
            $errorMessage = "خطا در دریافت اطلاعات موجودی اولیه.";
        }
        
        // FIX: استفاده از متد render که از کلاس پدر به ارث رسیده
        $this->render('initial_balance/list', [
            'page_title' => $pageTitle,
            'initial_balances' => $initialBalances,
            'bank_accounts' => $bankAccounts,
            'error_message' => $errorMessage
        ]);
    }

    /**
     * نمایش فرم تعریف موجودی اولیه
     */
    public function showForm(): void {
        $this->requireLogin();
        $pageTitle = "ثبت موجودی اولیه و سرمایه هدف جدید";
        $products = [];
        $bankAccounts = [];
        $errorMessage = null;
        
        try {
            $products = $this->productRepository->findAll(['is_active' => true], true);
            $bankAccounts = $this->bankAccountRepository->getAll();
        } catch (Throwable $e) {
            $this->logger->error("Error fetching form data", ['exception' => $e]);
            $errorMessage = "خطا در دریافت اطلاعات.";
        }
        
        // FIX: استفاده از متد render
        $this->render('initial_balance/form', [
            'page_title' => $pageTitle, // pageTitle به page_title استانداردسازی شد
            'products' => $products,
            'bank_accounts' => $bankAccounts,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * ذخیره موجودی اولیه
     */
    public function save(): void {
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/initial-balance');
        }

        try {
            // FIX: منطق اعتبارسنجی و ذخیره باید به InitialBalanceService منتقل شود
            // این کنترلر فقط باید داده‌های POST را به سرویس ارسال کند.
            $this->initialBalanceService->createFromPostData($_POST);

            $this->setSessionMessage('موجودی اولیه با موفقیت ثبت شد.', 'success');
            $this->redirect('/app/initial-balance');

        } catch (Throwable $e) {
            $this->logger->error("Error saving initial balance", ['exception' => $e]);
            $this->setSessionMessage('خطا در ثبت موجودی اولیه: ' . $e->getMessage(), 'danger', 'form_error');
            // TODO: Repopulate form with old data
            $this->redirect('/app/initial-balance/form');
        }
    }
    
    // متدهای edit, update, delete نیز باید به همین شکل اصلاح و به معماری جدید منتقل شوند.
    // فعلا برای رفع خطای اصلی، این متدها کامنت می‌شوند یا خالی می‌مانند.
    public function edit(int $id): void {
        // TODO: Implement logic
        $this->setSessionMessage('این بخش هنوز پیاده سازی نشده است.', 'info');
        $this->redirect('/app/initial-balance');
    }
    
    public function update(int $id): void {
        // TODO: Implement logic
         $this->setSessionMessage('این بخش هنوز پیاده سازی نشده است.', 'info');
         $this->redirect('/app/initial-balance');
    }

    public function delete(int $id): void {
        // TODO: Implement logic
         $this->setSessionMessage('این بخش هنوز پیاده سازی نشده است.', 'info');
         $this->redirect('/app/initial-balance');
    }
}
