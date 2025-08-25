<?php
// src/Controllers/DashboardController.php
namespace App\Controllers;

use PDO;
use Monolog\Logger;
use Throwable;
use Exception; // Explicitly use Exception.

use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Repositories
use App\Repositories\InventoryRepository;
use App\Repositories\CoinInventoryRepository;
use App\Repositories\BankAccountRepository;
use App\Repositories\ContactRepository; // Required for Dashboard display.
use App\Repositories\TransactionRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository; // For user counts if desired on dashboard.

// Services
use App\Services\LicenseService;
use App\Services\AuthService;
use App\Utils\Helper; // Utility functions (e.g., for formatting numbers).

/**
 * Class DashboardController for displaying the main dashboard page.
 * Fetches summary and statistical data from various repositories.
 * REVISED: Added comprehensive data processing and formatting for dashboard display elements.
 */
class DashboardController extends AbstractController {

    private InventoryRepository $inventoryRepository;
    private CoinInventoryRepository $coinInventoryRepository;
    private BankAccountRepository $bankAccountRepository;
    private ContactRepository $contactRepository;
    private TransactionRepository $transactionRepository;
    private PaymentRepository $paymentRepository;
    protected LicenseService $licenseService;
    protected AuthService $authService;
    protected ?UserRepository $userRepository = null; // Optional.

    /**
     * Constructor. Injects dependencies.
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services // Receives the full services array.
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        
        // --- CRITICAL FIX: Ensure all required services are explicitly pulled from $services and assigned. ---
        // This makes it clear which service goes to which property and allows specific error messages.
        // If a service is truly optional for the Dashboard and its absence should not crash, assign null or check its existence later.
        try {
            $this->inventoryRepository = $services['inventoryRepository'] ?? throw new Exception('InventoryRepository is missing from services container.');
            $this->coinInventoryRepository = $services['coinInventoryRepository'] ?? throw new Exception('CoinInventoryRepository is missing from services container.');
            $this->bankAccountRepository = $services['bankAccountRepository'] ?? throw new Exception('BankAccountRepository is missing from services container.');
            $this->contactRepository = $services['contactRepository'] ?? throw new Exception('ContactRepository is missing from services container.');
            $this->transactionRepository = $services['transactionRepository'] ?? throw new Exception('TransactionRepository is missing from services container.');
            $this->paymentRepository = $services['paymentRepository'] ?? throw new Exception('PaymentRepository is missing from services container.');
            $this->licenseService = $services['licenseService'] ?? throw new Exception('LicenseService is missing from services container.');
            $this->authService = $services['authService'] ?? throw new Exception('AuthService is missing from services container.');
            
            $this->userRepository = $services['userRepository'] ?? null; // Assuming UserRepository might be optional for dashboard.

        } catch (Throwable $e) {
            $this->logger->critical("FATAL ERROR: Failed to inject required services into DashboardController.", ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            // Re-throw with a user-friendly message for front-controller ErrorHandler.
            throw new Exception("Initialization Error: Missing critical dashboard components. Please check system configuration. (Detail: {$e->getMessage()})", 0, $e); 
        }

        $this->logger->debug("DashboardController initialized successfully.");
    }

    /**
     * Displays the dashboard page.
     * Route: /app/dashboard (GET) or / (GET) if it's the main route.
     */
    public function index(): void {
        $this->requireLogin();
        // $this->requireLicense(); // Re-enable this line if license check is explicitly required on Dashboard.

        $user = $this->authService->getCurrentUser(); // Current logged-in user.

        $pageTitle = "داشبورد";
        $dashboardData = []; // Array to hold all fetched and processed data for the view.
        $dashboardError = null; // General error message for the dashboard itself.

        try {
            // --- General Dashboard Metrics ---
            $bankCashBalance = $this->bankAccountRepository->getTotalCurrentBalance();
            $dashboardData['bank_cash_balance_formatted'] = Helper::formatRial($bankCashBalance);
            // These TransactionRepository methods require `getOverallTransactionSummary` with correct aggregation (SUM).
            // Example: Sum final_payable_amount_rials from `transactions` table.
            $totalBuyValue = $this->transactionRepository->getOverallTransactionSummary('buy')['total_value_rials'] ?? 0.0;
            $totalSellValue = $this->transactionRepository->getOverallTransactionSummary('sell')['total_value_rials'] ?? 0.0;

            //$dashboardData['total_bank_balance_formatted'] = Helper::formatRial($totalBankBalance);
            $dashboardData['total_buy_value_formatted'] = Helper::formatRial($totalBuyValue);
            $dashboardData['total_sell_value_formatted'] = Helper::formatRial($totalSellValue);
            
            // --- Calculated Total Profit/Loss (Simple approach, refine with FIFO later) ---
            $dashboardData['overall_profit_loss'] = (float)$totalSellValue - (float)$totalBuyValue; // Raw value.
            $dashboardData['overall_profit_loss_formatted'] = Helper::formatRial(abs($dashboardData['overall_profit_loss']));
            $dashboardData['overall_profit_loss_status'] = ($dashboardData['overall_profit_loss'] >= 0) ? 'profit' : 'loss';
            
            // --- Other Dashboard Summary Cards Data ---
            // Total Coin Quantity: Assume CoinInventoryRepository can give sum of quantities.
            $totalCoinQty = 0; // Default
            $coinInventoryRaw = $this->coinInventoryRepository->getAllCoinInventory();
            foreach ($coinInventoryRaw as $coinItem) {
                $totalCoinQty += (int)($coinItem['quantity'] ?? 0);
            }
            $dashboardData['total_coin_quantity_formatted'] = Helper::formatPersianNumber($totalCoinQty, 0);


            // Total 750 Equivalent Gold in Grams (for summary card).
            // Uses InventoryRepository::getAllInventorySummary for detailed items by carat.
      // --- (اصلاح شده) محاسبه موجودی وزنی و ارسال به ویو ---
            $weightInventoryItems = $this->inventoryRepository->getAllInventorySummary();
            $total750EquivalentGrams = 0.0;
            foreach ($weightInventoryItems as $item) {
                $total750EquivalentGrams += Helper::convertGoldToCarat((float)($item['total_weight_grams'] ?? 0), (int)($item['carat'] ?? 0), 750);
            }
            $dashboardData['total_750_equivalent_formatted'] = Helper::formatPersianNumber($total750EquivalentGrams, 3);
            $dashboardData['weight_inventory_items'] = $weightInventoryItems; // **این خط باگ را برطرف می‌کند**


            // --- Section: Last Transactions (Recent Transactions Card) ---
            $dashboardData['recent_transactions'] = [];
            $latestTransactions = $this->transactionRepository->getLatestTransactions(null, 5); // null for all types (buy/sell).
            foreach ($latestTransactions as $tx) {
                // Prepare display data for the table/list card.
                $tx['display_type'] = ($tx['transaction_type'] === 'buy' ? 'خرید' : 'فروش');
                $tx['display_amount'] = Helper::formatRial($tx['final_payable_amount_rials'] ?? 0);
                $tx['display_date'] = Helper::formatPersianDate($tx['transaction_date'] ?? date('Y-m-d')); // Format only date
                $tx['display_contact'] = Helper::escapeHtml($tx['counterparty_name'] ?? 'طرف حساب نامشخص'); // Assumes name comes from JOIN.
                // Assuming `product_name` for transaction is fetched/available, or simplify display.
                // $tx['display_product_name'] = Helper::escapeHtml($tx['product_name'] ?? 'محصول نامشخص'); // Needs to be joined in TransactionRepository
                // For simplicity on dashboard:
                $tx['display_product_info'] = Helper::escapeHtml($tx['product_name'] ?? 'محصول نامشخص'); // Product name not always on root tx record.
                $dashboardData['recent_transactions'][] = $tx;
            }

            // --- Section: Last Payments (Recent Payments Card) ---
            $dashboardData['recent_payments'] = [];
            $latestPayments = $this->paymentRepository->getLatestPayments(5);
            foreach ($latestPayments as $p) {
                // PaymentRepository::getLatestPayments should fetch and format payment_date_jalali, amount_rials_formatted, and set direction_farsi/contact_name.
                $p['display_amount'] = $p['amount_rials_formatted'] ?? Helper::formatRial($p['amount_rials'] ?? 0);
                $p['display_date'] = $p['payment_date_jalali'] ?? Helper::formatPersianDate($p['payment_date'] ?? date('Y-m-d'));
                $p['display_type'] = $p['direction_farsi'] ?? ($p['direction'] === 'inflow' ? 'دریافت' : 'پرداخت'); // Fallback if repo not formatting
                $p['display_contact'] = Helper::escapeHtml($p['paying_contact_name'] ?? $p['receiving_contact_name'] ?? 'نامشخص');
                $dashboardData['recent_payments'][] = $p;
            }

            // --- Section: Debtors & Creditors List (Top X) ---
            $dashboardData['debtors_list'] = [];
            $dashboardData['creditors_list'] = [];
            // ContactRepository::getDebtorsAndCreditors should format the balance for display.
            if (!empty($debtorsAndCreditors['debtors'])) {
                foreach($debtorsAndCreditors['debtors'] as &$debtor) {
                    $debtor['balance_formatted'] = Helper::formatRial(abs((float)$debtor['balance'] ?? 0));
                    $dashboardData['debtors_list'][] = $debtor;
                }
                unset($debtor);
            }
            if (!empty($debtorsAndCreditors['creditors'])) {
                foreach($debtorsAndCreditors['creditors'] as &$creditor) {
                    $creditor['balance_formatted'] = Helper::formatRial(abs((float)$creditor['balance'] ?? 0));
                    $dashboardData['creditors_list'][] = $creditor;
                }
                unset($creditor);
            }


            // --- Section: Pending Commitments Summary ---
            $dashboardData['pending_receipt_summary'] = $this->transactionRepository->getPendingSummary('pending_receipt');
            $dashboardData['pending_delivery_summary'] = $this->transactionRepository->getPendingSummary('pending_delivery');
            // Format data in a loop here (or in repo method formatters if general use cases).
            foreach ($dashboardData['pending_receipt_summary'] as &$item) {
                $item['formatted_weight_750'] = Helper::formatPersianNumber($item['total_weight_750'] ?? 0, 3) . ' گرم';
                $item['formatted_quantity'] = Helper::formatPersianNumber($item['total_quantity'] ?? 0, 0) . ' عدد';
                $item['display_value'] = ($item['total_weight_750'] > 0) ? $item['formatted_weight_750'] : $item['formatted_quantity'];
            } unset($item);
            foreach ($dashboardData['pending_delivery_summary'] as &$item) {
                $item['formatted_weight_750'] = Helper::formatPersianNumber($item['total_weight_750'] ?? 0, 3) . ' گرم';
                $item['formatted_quantity'] = Helper::formatPersianNumber($item['total_quantity'] ?? 0, 0) . ' عدد';
                $item['display_value'] = ($item['total_weight_750'] > 0) ? $item['formatted_weight_750'] : $item['formatted_quantity'];
            } unset($item);


            // --- User statistics (if UserRepository is enabled/exists) ---
            if ($this->userRepository) {
                // $dashboardData['user_counts'] = $this->userRepository->getUserCounts();
            }


        } catch (Throwable $e) {
            $this->logger->error("Exception during dashboard data fetching and processing.", ['exception' => $e, 'trace' => $e->getTraceAsString()]);
            $dashboardError = "خطا در بارگذاری اطلاعات داشبورد.";
            if ($this->config['app']['debug']) {
                $dashboardError .= " جزئیات: " . Helper::escapeHtml($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
            // --- CRITICAL: Reset Dashboard Data on error to prevent view crashes if partially filled. ---
            $dashboardData = [
                'total_bank_balance_formatted' => Helper::formatRial(0),
                'total_buy_value_formatted' => Helper::formatRial(0),
                'total_sell_value_formatted' => Helper::formatRial(0),
                'overall_profit_loss_formatted' => Helper::formatRial(0),
                'overall_profit_loss_status' => 'normal',
                'total_coin_quantity_formatted' => Helper::formatPersianNumber(0, 0),
                'total_750_equivalent_formatted' => Helper::formatPersianNumber(0, 3),
                'weight_inventory_items' => [],
                'coin_inventory_items' => [],
                'recent_transactions' => [],
                'recent_payments' => [],
                'recent_sold_items' => [],
                'debtors_list' => [],
                'creditors_list' => [],
                'pending_receipt_summary' => [],
                'pending_delivery_summary' => [],
            ];
        }

        // Render Dashboard View. (Assume view is at src/views/dashboard/index.php).
        $this->render('dashboard/index', [
            'page_title' => $pageTitle,
            'dashboard_data' => $dashboardData, // Send all fetched and processed data.
            'dashboard_error' => $dashboardError,
            'user' => $user, // Current logged-in user.
            'base_url' => $this->config['app']['base_url'],
            'csrf_token' => Helper::generateCsrfToken(), // For any forms on dashboard.
        ]);
    }

}