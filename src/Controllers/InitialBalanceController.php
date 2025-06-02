<?php
/**
 * Controller: src/Controllers/InitialBalanceController.php
 * Handles initial balance operations
 */

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

class InitialBalanceController
{
    private $initialBalanceRepository;
    private $productRepository;
    private $bankAccountRepository;
    private $initialBalanceService;
    private $inventoryCalculationService;
    private $logger;
    private $config;
    private $viewRenderer;
    private $db;

    public function __construct(
        InitialBalanceRepository $initialBalanceRepository,
        ProductRepository $productRepository,
        BankAccountRepository $bankAccountRepository,
        InitialBalanceService $initialBalanceService,
        InventoryCalculationService $inventoryCalculationService,
        Logger $logger,
        PDO $db = null
    ) {
        global $config;
        $this->config = $config;
        $this->viewRenderer = new ViewRenderer(
            $config['paths']['views'],
            $config['paths']['layouts'],
            $logger
        );
        $this->initialBalanceRepository = $initialBalanceRepository;
        $this->productRepository = $productRepository;
        $this->bankAccountRepository = $bankAccountRepository;
        $this->initialBalanceService = $initialBalanceService;
        $this->inventoryCalculationService = $inventoryCalculationService;
        $this->logger = $logger;
        $this->db = $db;
    }

    /**
     * Check if user is logged in
     */
    private function requireLogin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    /**
     * Render a view with data
     */
    private function render(string $viewName, array $data = []): void
    {
        $this->viewRenderer->render($viewName, $data, true);
    }

    /**
     * Set a flash message
     */
    private function setFlashMessage(string $type, string $message): void
    {
        $_SESSION['flash_messages'][$type] = $message;
    }

    /**
     * Redirect to a URL
     */
    private function redirect(string $url): void
    {
        header('Location: ' . $this->config['app']['base_url'] . $url);
        exit;
    }

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
            
            // محاسبه تراز عملکرد برای هر محصول
            foreach ($initialBalances as &$balance) {
                $performance = $this->initialBalanceService->calculatePerformanceBalance($balance['product_id']);
                $balance['performance_balance'] = $performance;
            }
            unset($balance);
            
        } catch (Throwable $e) {
            $this->logger->error("Error fetching initial balances", ['exception' => $e]);
            $errorMessage = "خطا در دریافت اطلاعات موجودی اولیه.";
        }
        
        $this->render('initial_balance/list', [
            'page_title' => $pageTitle,
            'pageTitle' => $pageTitle,
            'initial_balances' => $initialBalances,
            'bank_accounts' => $bankAccounts,
            'error_message' => $errorMessage,
            'baseUrl' => $this->config['app']['base_url'],
            'fields' => json_decode($this->config['app']['global_json_strings']['fields'] ?? '{}', true),
            'formulas' => json_decode($this->config['app']['global_json_strings']['formulas'] ?? '{}', true),
            'appName' => $this->config['app']['name'] ?? 'حسابداری رایان طلا',
            'isLoggedIn' => isset($_SESSION['user_id']),
            'loggedInUser' => $_SESSION['user'] ?? null,
            'currentUri' => $_SERVER['REQUEST_URI'] ?? '/',
            'flashMessage' => $_SESSION['flash_messages'] ?? null
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
        $this->viewRenderer->render('initial_balance/form', [
            'pageTitle' => $pageTitle,
            'page_title' => $pageTitle,
            'products' => $products,
            'bank_accounts' => $bankAccounts,
            'error_message' => $errorMessage,
            'baseUrl' => $this->config['app']['base_url'],
            'fields' => json_decode($this->config['app']['global_json_strings']['fields'] ?? '{}', true),
            'formulas' => json_decode($this->config['app']['global_json_strings']['formulas'] ?? '{}', true),
            'appName' => $this->config['app']['name'] ?? 'حسابداری رایان طلا',
            'isLoggedIn' => isset($_SESSION['user_id']),
            'loggedInUser' => $_SESSION['user'] ?? null,
            'currentUri' => $_SERVER['REQUEST_URI'] ?? '/',
            'flashMessage' => $_SESSION['flash_messages'] ?? null
        ]);
    }

    /**
     * ذخیره موجودی اولیه
     */
    public function save(): void {
        $this->requireLogin();
        
        try {
            // دریافت و اعتبارسنجی داده‌ها
            $data = $this->validateAndSanitizeInput();
            
            // شروع تراکنش
            if ($this->db) {
                $this->db->beginTransaction();
            }
            
            try {
                // ذخیره موجودی اولیه محصول
                if ($data['product_id']) {
                    // ایجاد موجودی اولیه
                    $initialBalanceId = $this->initialBalanceService->createInitialBalance($data);
                    
                    // ثبت در دفتر موجودی
                    $this->initialBalanceService->recordInInventoryLedger($initialBalanceId);
                    
                    // ذخیره محاسبات در جدول جدید
                    $calculationData = [
                        'product_id' => $data['product_id'],
                        'calculation_date' => $data['balance_date'],
                        'calculation_type' => 'initial_balance',
                        'quantity_before' => 0,
                        'weight_before' => 0,
                        'quantity_after' => $data['quantity'] ?? 0,
                        'weight_after' => $data['weight_grams'] ?? 0,
                        'average_purchase_price' => $data['average_purchase_price_per_unit'],
                        'total_value' => $data['total_purchase_value'],
                        'target_capital' => $data['target_capital'],
                        'balance_percentage' => $this->calculateBalancePercentage($data['total_purchase_value'], $data['target_capital']),
                        'balance_status' => $this->determineBalanceStatus($data['total_purchase_value'], $data['target_capital'])
                    ];
                    
                    $this->initialBalanceService->saveCalculations($calculationData);
                }
                
                // ذخیره موجودی اولیه حساب‌های بانکی
                if (!empty($data['bank_initial_balance'])) {
                    foreach ($data['bank_initial_balance'] as $bankId => $balance) {
                        $this->bankAccountRepository->updateInitialBalance($bankId, [
                            'initial_balance' => $balance,
                            'target_capital' => $data['bank_target_capital'][$bankId] ?? 0
                        ]);
                    }
                }
                
                // تایید تراکنش
                if ($this->db && $this->db->inTransaction()) {
                    $this->db->commit();
                }
                
                $this->setFlashMessage('success', 'موجودی اولیه با موفقیت ثبت شد.');
                $this->redirect('/app/initial-balance');
                
            } catch (Throwable $e) {
                // برگشت تراکنش در صورت خطا
                if ($this->db && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
            
        } catch (Throwable $e) {
            $this->logger->error("Error saving initial balance", ['exception' => $e]);
            $this->setFlashMessage('error', 'خطا در ثبت موجودی اولیه: ' . $e->getMessage());
            $this->redirect('/app/initial-balance/form');
        }
    }

    /**
     * محاسبه درصد تراز
     */
    private function calculateBalancePercentage(float $currentValue, float $targetValue): float {
        if ($targetValue <= 0) {
            return 0;
        }
        return ($currentValue / $targetValue) * 100;
    }

    /**
     * تعیین وضعیت تراز
     */
    private function determineBalanceStatus(float $currentValue, float $targetValue): string {
        $percentage = $this->calculateBalancePercentage($currentValue, $targetValue);
        
        if ($percentage < 95) {
            return 'shortage';
        } elseif ($percentage > 105) {
            return 'excess';
        } else {
            return 'normal';
        }
    }

    /**
     * اعتبارسنجی و پاکسازی داده‌های ورودی
     */
    private function validateAndSanitizeInput(): array {
        $data = [
            'product_id' => filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT),
            'balance_date' => filter_input(INPUT_POST, 'balance_date', FILTER_SANITIZE_STRING),
            'market_price' => filter_input(INPUT_POST, 'market_price', FILTER_SANITIZE_STRING),
            'scale_weight' => filter_input(INPUT_POST, 'scale_weight', FILTER_SANITIZE_STRING),
            'weight_750' => filter_input(INPUT_POST, 'weight_750', FILTER_SANITIZE_STRING),
            'quantity' => filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_STRING),
            'carat' => filter_input(INPUT_POST, 'carat', FILTER_VALIDATE_INT),
            'average_purchase_price_per_unit' => filter_input(INPUT_POST, 'average_purchase_price_per_unit', FILTER_SANITIZE_STRING),
            'total_purchase_value' => filter_input(INPUT_POST, 'total_purchase_value', FILTER_SANITIZE_STRING),
            'target_capital' => filter_input(INPUT_POST, 'target_capital', FILTER_SANITIZE_STRING),
            'notes' => filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING),
            'bank_initial_balance' => filter_input(INPUT_POST, 'bank_initial_balance', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY),
            'bank_target_capital' => filter_input(INPUT_POST, 'bank_target_capital', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY)
        ];

        // اعتبارسنجی داده‌های اجباری
        if (!$data['product_id'] && empty($data['bank_initial_balance'])) {
            throw new \Exception("لطفاً حداقل یک محصول یا حساب بانکی را انتخاب کنید.");
        }
        
        if ($data['product_id']) {
            // دریافت اطلاعات محصول
            $product = $this->productRepository->findById($data['product_id']);
            if (!$product) {
                throw new \Exception("محصول انتخاب شده معتبر نیست.");
            }

            // بررسی فیلدهای اجباری بر اساس نوع محصول
            if ($product->type === 'coin') {
                if (empty($data['quantity'])) {
                    throw new \Exception("لطفاً تعداد سکه را وارد کنید.");
                }
            } else {
                if (empty($data['scale_weight'])) {
                    throw new \Exception("لطفاً وزن ترازو را وارد کنید.");
                }
                if (empty($data['weight_750'])) {
                    throw new \Exception("لطفاً وزن 750 را وارد کنید.");
                }
                if ($product->type !== 'jewelry' && empty($data['market_price'])) {
                    throw new \Exception("لطفاً قیمت مظنه را وارد کنید.");
                }
            }

            if (!$data['balance_date']) {
                throw new \Exception("لطفاً تاریخ موجودی اولیه را وارد کنید.");
            }
            if (!$data['average_purchase_price_per_unit']) {
                throw new \Exception("لطفاً قیمت خرید واحد را وارد کنید.");
            }
            if (!$data['total_purchase_value']) {
                throw new \Exception("لطفاً ارزش کل را وارد کنید.");
            }
            if (!$data['target_capital']) {
                throw new \Exception("لطفاً سرمایه هدف را وارد کنید.");
            }
        }

        // تبدیل تاریخ شمسی به میلادی
        if ($data['balance_date']) {
            $data['balance_date'] = Helper::jalaliToGregorian($data['balance_date']);
        }

        // تبدیل اعداد فارسی به انگلیسی و حذف کاما
        $numericFields = [
            'market_price',
            'scale_weight',
            'weight_750',
            'quantity',
            'average_purchase_price_per_unit',
            'total_purchase_value',
            'target_capital'
        ];

        foreach ($numericFields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = Helper::persianToEnglishNumbers($data[$field]);
                $data[$field] = str_replace(',', '', $data[$field]);
            }
        }

        // تبدیل اعداد در آرایه‌های بانکی
        if (!empty($data['bank_initial_balance'])) {
            foreach ($data['bank_initial_balance'] as &$balance) {
                $balance = Helper::persianToEnglishNumbers($balance);
                $balance = str_replace(',', '', $balance);
            }
            unset($balance);
        }

        if (!empty($data['bank_target_capital'])) {
            foreach ($data['bank_target_capital'] as &$target) {
                $target = Helper::persianToEnglishNumbers($target);
                $target = str_replace(',', '', $target);
            }
            unset($target);
        }

        return $data;
    }
} 