<?php
// src/Controllers/InventoryController.php
namespace App\Controllers;

use PDO;
use Monolog\Logger;
use Throwable;

use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

use App\Repositories\InventoryRepository;
use App\Repositories\CoinInventoryRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\TransactionItemRepository; // Also make sure TransactionItemRepository is imported if used.
use App\Repositories\ProductRepository; // ProductRepository for capital calculations (get product data)
use App\Repositories\ProductCategoryRepository; // For getting category names and details
use App\Repositories\InventoryLedgerRepository; // New dependency to get current balance accurately

use App\Services\GoldPriceService;
use App\Services\InventoryCalculationService; // For new calculation services if integrated.

use App\Utils\Helper;
use App\Core\CSRFProtector;

/**
 * InventoryController displays the detailed inventory status page.
 */
class InventoryController extends AbstractController {

    private InventoryRepository $inventoryRepository;
    private CoinInventoryRepository $coinInventoryRepository;
    private TransactionRepository $transactionRepository;
    private TransactionItemRepository $transactionItemRepository;
    private GoldPriceService $goldPriceService;
    private ProductRepository $productRepository; // Added for capital performance
    private ProductCategoryRepository $productCategoryRepository; // Added for capital performance (category name)
    private InventoryLedgerRepository $inventoryLedgerRepository; // Added for current balance calculation


    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);

        $this->logger->debug("InventoryController constructor called.");

        $requiredServices = [ // Map of service_key => class_name
            'inventoryRepository' => InventoryRepository::class,
            'coinInventoryRepository' => CoinInventoryRepository::class,
            'transactionRepository' => TransactionRepository::class,
            'transactionItemRepository' => TransactionItemRepository::class, // Need to make sure this is available.
            'goldPriceService' => GoldPriceService::class,
            'productRepository' => ProductRepository::class, // Add these missing
            'productCategoryRepository' => ProductCategoryRepository::class, // Add these missing
            'inventoryLedgerRepository' => InventoryLedgerRepository::class, // Added for correct current balance.
        ];
        
        foreach ($requiredServices as $propName => $className) {
            if (!isset($services[$propName]) || !$services[$propName] instanceof $className) {
                // If this is a problem for DI, make sure main index.php initializes all these service in the $services array properly.
                throw new \Exception("Required service '{$propName}' ({$className}) not found or invalid in InventoryController.");
            }
            $this->$propName = $services[$propName];
            $this->logger->debug("Service injected: {$propName}");
        }

        $this->logger->debug("InventoryController initialized.");
    }

    public function index(): void {
        $this->requireLogin();

        $pageTitle = "جزئیات موجودی و تعهدات";
        $inventoryData = [
            'weight_inventory'           => [],
            'coin_inventory'             => [],
            'pending_receipt_details'    => [],
            'pending_delivery_details'   => [],
            'summary'                    => [
                'total_750_equivalent_formatted' => Helper::formatNumber(0, 4),
                'total_weight_value_formatted'   => Helper::formatRial(0),
                'total_coin_value_formatted'     => Helper::formatRial(0),
                'overall_total_value_formatted'  => Helper::formatRial(0),
                'value_method_is_real'           => false,
                'price_750_for_estimation'       => null
            ],
            'capital_performance'        => [],
            'overall_avg_buy_price_1g'   => 0, // Reset here
        ];
        $fetchErrors = [];

        $csrfToken = CSRFProtector::generateToken();
        
        // --- Fetch External Prices ---
        $latestPrice750 = null;
        try {
             $latestPrice750 = $this->goldPriceService->fetchLatestPricePer750Gram();
        } catch (\Throwable $e) {
             $this->logger->warning("Failed to fetch latest 750 price for inventory estimation.", ['exception' => $e]);
        }
        $latestMazanehPrice = $this->goldPriceService->fetchLatestGoldPrice();
        $latestCoinPrices = $this->goldPriceService->fetchLatestCoinPrices();

  // 1. Weight Inventory
        try {
            $items = $this->inventoryRepository->getAllInventorySummary();
            $total750 = 0; $totalWeightValueRaw = 0; $hasRealValue = false; $totalWeightForAvg = 0; $totalRealValue = 0;

            foreach ($items as &$item) {
                $weight = (float)($item['total_weight_grams'] ?? 0);
                $carat = (int)($item['carat'] ?? 0);
                $valueRaw = (float)($item['total_value_rials'] ?? 0);

                $item['equivalent_750'] = ($weight * $carat) / 750;
                $total750 += $item['equivalent_750'];
                
                // ارزش واقعی موجودی (بر اساس میانگین قیمت خرید)
                $itemValueDisplay = $valueRaw;
                $totalWeightValueRaw += $itemValueDisplay;
                $hasRealValue = true; // با منطق جدید، ارزش همیشه واقعی است

                // برای محاسبه میانگین کل
                $totalWeightForAvg += $weight;
                $totalRealValue += $valueRaw;

                // فرمت‌دهی برای نمایش
                $item['value_display_raw'] = $itemValueDisplay;
                $item['avg_buy_price_per_gram_formatted'] = ($weight > 0) ? Helper::formatRial($valueRaw / $weight) : '-';
            }
            unset($item);

            $overallAvgBuyPrice1g = ($totalWeightForAvg > 0) ? $totalRealValue / $totalWeightForAvg : 0;

            $inventoryData['weight_inventory'] = $items;
            $inventoryData['overall_avg_buy_price_1g'] = $overallAvgBuyPrice1g;
            $inventoryData['summary']['total_750_equivalent_formatted'] = Helper::formatPersianNumber($total750, 4);
            $inventoryData['summary']['total_weight_value_formatted'] = Helper::formatRial($totalWeightValueRaw);
            $inventoryData['summary']['value_method_is_real'] = $hasRealValue;
            
        } catch (Throwable $e) { $this->handleDataFetchError($e, $fetchErrors, "موجودی وزنی"); }


        // 2. Coin Inventory
        try {
            $items = $this->coinInventoryRepository->getAllCoinInventory();
            $totalCoinValueApi = 0; 
            foreach ($items as &$item) {
                $this->formatCoinItemForView($item, $latestCoinPrices, $totalCoinValueApi);
            } unset($item);
            $inventoryData['coin_inventory'] = $items;
            $inventoryData['summary']['total_coin_value_formatted'] = Helper::formatRial($totalCoinValueApi);
            $inventoryData['summary']['_api_coin_value_raw'] = $totalCoinValueApi;
            $this->logger->debug("Coin inventory processed for inventory page.", ['count' => count($items), 'total_api_value' => $totalCoinValueApi]);
        } catch (Throwable $e) { $this->handleDataFetchError($e, $fetchErrors, "موجودی سکه"); }

        // 3. Overall Total Value
        $rawWeightValueTotal = array_sum(array_column($inventoryData['weight_inventory'], 'value_display_raw'));
        $rawCoinValueTotal = $inventoryData['summary']['_api_coin_value_raw'] ?? 0;
        $overallTotal = $rawWeightValueTotal + $rawCoinValueTotal;
        $inventoryData['summary']['overall_total_value_formatted'] = Helper::formatRial($overallTotal);

     // 4. (اصلاح شده) سیستم تراز عملکرد (بر اساس هر محصول)
        try {
            $productsWithCapitalTarget = $this->productRepository->findAllWithCategory(['has_capital_target' => true]);
            
            $capitalPerformanceData = [];
            foreach ($productsWithCapitalTarget as $product) {
                $currentBalance = $this->inventoryLedgerRepository->getProductCurrentBalance($product->id);

                $item = [
                    'product_name' => $product->name, // استفاده از نام محصول
                ];

                $target = 0.0;
                $current = 0.0;
                $displayUnit = '';
                
                if ($product->unit_of_measure === 'count' && !empty($product->capital_quantity)) {
                    $target = (float)$product->capital_quantity;
                    $current = (float)($currentBalance['quantity'] ?? 0);
                    $displayUnit = ' عدد';
                } elseif ($product->unit_of_measure === 'gram' && !empty($product->capital_weight_grams)) {
                    $target = (float)$product->capital_weight_grams;
                    $current = (float)($currentBalance['weight_grams'] ?? 0.0);
                    $displayUnit = ' گرم';
                } else {
                    continue; 
                }

                $item['target_formatted'] = Helper::formatPersianNumber($target, $product->unit_of_measure === 'gram' ? 3 : 0) . $displayUnit;
                $item['current_formatted'] = Helper::formatPersianNumber($current, $product->unit_of_measure === 'gram' ? 3 : 0) . $displayUnit;
                $balance = $current - $target;
                $item['balance_formatted'] = Helper::formatPersianNumber($balance, $product->unit_of_measure === 'gram' ? 3 : 0) . $displayUnit;
                
                $item['balance_percent'] = ($target > 0) ? round(($current / $target) * 100, 1) : 100.0;

                $item['status'] = 'normal';
                if ($item['balance_percent'] < 95) $item['status'] = 'shortage';
                elseif ($item['balance_percent'] > 105) $item['status'] = 'excess';
                
                $capitalPerformanceData[] = $item;
            }
            $inventoryData['capital_performance'] = $capitalPerformanceData;
            
        } catch (Throwable $e) { 
            $this->handleDataFetchError($e, $fetchErrors, "تراز عملکرد سرمایه"); 
        }

        // 5. Pending Transactions Summary
        try {
             $inventoryData['pending_receipt_details'] = $this->transactionRepository->getPendingItemsDetails('pending_receipt');
             $inventoryData['pending_delivery_details'] = $this->transactionRepository->getPendingItemsDetails('pending_delivery');
             $this->logger->debug("Pending summary fetched successfully for inventory page.");

        } catch (Throwable $e) { $this->handleDataFetchError($e, $fetchErrors, "تعهدات معلق"); }

        // FIFO Inventory Calculation (DISABLED per client feedback)
        $this->logger->warning("FIFO calculation is currently disabled on the inventory page to prevent errors. Enable manually if required.");


        $errorMessage = !empty($fetchErrors) ? "خطا در بارگذاری برخی بخش‌های موجودی:<br>" . implode("<br>", $fetchErrors) : null;
        if ($errorMessage) {
             $this->setSessionMessage($errorMessage, 'warning', 'inventory_error');
        }

        $fieldsData = $this->loadDynamicFields();
        $formulasData = $this->loadDynamicFormulas();

        $viewData = [
            'page_title' => $pageTitle,
            'inventory_data' => $inventoryData,
            'csrf_token' => $csrfToken,
            'latestMazanehPrice' => $latestMazanehPrice,
            'latestCoinPrices' => $latestCoinPrices,
            'baseUrl' => $this->getBaseUrl(),
            'error_msg' => !empty($fetchErrors) ? implode("<br>", $fetchErrors) : null,
            'global_json_strings_for_footer' => [ // Used for displaying fields/formulas in debug or for client-side evaluation setup.
                'fields' => json_encode($fieldsData),
                'formulas' => json_encode($formulasData)
            ],
            // Ensure capitalPerformanceData is directly accessible at top-level of viewData (already there due to $inventoryData key)
        ];

        $this->render('inventory/index', $viewData, true); // true implies render with layout.
    }
    
    /** Loads dynamic fields from fields.json */
    private function loadDynamicFields(): array {
        try {
            $fieldsPath = __DIR__ . '/../../config/fields.json';
            if (!file_exists($fieldsPath)) { $this->logger->error("Fields file not found: {$fieldsPath}"); return ['fields' => []]; }
            $fieldsContent = file_get_contents($fieldsPath);
            return json_decode($fieldsContent, true) ?: ['fields' => []];
        } catch (\Throwable $e) { $this->logger->error("Error loading fields: " . $e->getMessage()); return ['fields' => []]; }
    }
    
    /** Loads dynamic formulas from formulas.json */
    private function loadDynamicFormulas(): array {
        try {
            $formulasPath = __DIR__ . '/../../config/formulas.json';
            if (!file_exists($formulasPath)) { $this->logger->error("Formulas file not found: {$formulasPath}"); return ['formulas' => []]; }
            $formulasContent = file_get_contents($formulasPath);
            return json_decode($formulasContent, true) ?: ['formulas' => []];
        } catch (\Throwable $e) { $this->logger->error("Error loading formulas: " . $e->getMessage()); return ['formulas' => []]; }
    }

    private function handleDataFetchError(Throwable $e, array &$errors, string $sectionName): void {
         $this->logger->error("Error fetching {$sectionName} for inventory page.", ['exception' => $e]);
         $errorText = "خطا در بارگذاری {$sectionName}.";
         if ($this->config['app']['debug']) { $errorText .= " (" . Helper::escapeHtml($e->getMessage()) . ")";}
         $errors[] = $errorText;
    }

     private function formatCoinItemForView(array &$item, array $latestCoinPrices, float &$totalValueApi): void {
         $quantity = (int)($item['quantity'] ?? 0);
         $item['type_farsi'] = Helper::translateProductType($item['coin_type'] ?? '');
         $item['quantity_formatted'] = Helper::formatNumber($quantity, 0);
         $coinKey = $item['coin_type'] ?? '';
         $latestPrice = $latestCoinPrices[$coinKey] ?? null;
         $item['latest_unit_price_formatted'] = $latestPrice ? Helper::formatRial($latestPrice) : '-';
         $estimatedValue = 0.0;
         if ($quantity > 0 && $latestPrice !== null && $latestPrice > 0) {
             $estimatedValue = $quantity * $latestPrice;
             $totalValueApi += $estimatedValue;
             $item['estimated_value_formatted'] = Helper::formatRial($estimatedValue);
         } else { $item['estimated_value_formatted'] = '-'; }
     }

      private function formatPendingItemForView(array &$item): void {
           $item['transaction_type_farsi'] = match ($item['transaction_type'] ?? '') { 'buy' => 'خرید', 'sell' => 'فروش', default => '؟' };
           $item['counterparty_name'] = Helper::escapeHtml($item['counterparty_name'] ?? '[نامشخص]');
           $item['transaction_date_persian'] = $item['transaction_date'] ?? '';
           $productCategoryCode = $item['product_category_code'] ?? '';
           if (is_numeric($productCategoryCode)) {
               $numericProductCodeMap = [
                   '1' => 'new_jewelry', '2' => 'used_jewelry', '3' => 'melted', '4' => 'coin_emami',
                   '5' => 'coin_bahar_azadi_new', '6' => 'coin_bahar_azadi_old', '7' => 'coin_half',
                   '8' => 'coin_quarter', '9' => 'coin_gerami', '10' => 'bullion', '11' => 'other_coin'
               ];
               $productCategoryCode = $numericProductCodeMap[$productCategoryCode] ?? "unknown_type_{$productCategoryCode}";
           }
           $item['product_type_farsi'] = Helper::translateProductType($productCategoryCode);
           $quantity = (int)($item['quantity'] ?? 0);
           $weight = (float)($item['weight_grams'] ?? 0);
           $carat = Helper::escapeHtml($item['carat'] ?? '-');
           $coinYear = Helper::escapeHtml($item['coin_year'] ?? '-');

           $item['product_name'] = Helper::escapeHtml($item['product_name'] ?? '[کالا نامشخص]');
           $productUnitOfMeasure = $item['product_unit_of_measure'] ?? 'gram';

           if ($productUnitOfMeasure === 'count') {
               $item['display_quantity_or_weight'] = Helper::formatNumber($quantity, 0);
               $item['display_spec'] = $coinYear;
           } elseif ($productUnitOfMeasure === 'gram') {
               $item['display_quantity_or_weight'] = Helper::formatNumber($weight, 4);
               $item['display_spec'] = $carat;
           } else {
               $item['display_quantity_or_weight'] = '-'; $item['display_spec'] = '-';
           }
           if (isset($item['delivery_status'])) {
               if ($item['delivery_status'] === 'pending_delivery') $item['transaction_type'] = 'sell';
               elseif ($item['delivery_status'] === 'pending_receipt') $item['transaction_type'] = 'buy';
           }
      }

    private function getBaseUrl(): string { return rtrim($this->config['app']['base_url'] ?? '', '/'); }
}