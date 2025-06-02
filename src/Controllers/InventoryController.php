<?php

namespace App\Controllers;

use PDO;
use Monolog\Logger;
use Throwable;

use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

use App\Repositories\InventoryRepository;
use App\Repositories\CoinInventoryRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\TransactionItemRepository;

use App\Utils\Helper;
use App\Core\CSRFProtector;

use App\Services\GoldPriceService;

/**
 * InventoryController displays the detailed inventory status page.
 */
class InventoryController extends AbstractController {

    private InventoryRepository $inventoryRepository;
    private CoinInventoryRepository $coinInventoryRepository;
    private TransactionRepository $transactionRepository;
    private TransactionItemRepository $transactionItemRepository;
    private GoldPriceService $goldPriceService;

    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);

        $required = [
            'inventoryRepository' => InventoryRepository::class,
            'coinInventoryRepository' => CoinInventoryRepository::class,
            'transactionRepository' => TransactionRepository::class,
            'transactionItemRepository' => TransactionItemRepository::class,
            'goldPriceService' => GoldPriceService::class,
        ];
        foreach ($required as $prop => $class) {
            if (!isset($this->services[$prop]) || !$this->services[$prop] instanceof $class) {
                throw new \Exception("Required service '{$prop}' ({$class}) not found for InventoryController.");
            }
            $this->$prop = $services[$prop];
        }

        $this->logger->debug("InventoryController initialized.");
    }

    public function index(): void {
        $this->requireLogin();
        // Optional: Permission check

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
            'capital_performance'        => []
        ];
        $fetchErrors = [];

        // تولید توکن CSRF برای فرم‌ها
        $csrfToken = CSRFProtector::generateToken();
        
        // --- Fetch External Prices ---
        $latestPrice750 = null;
        try {
             // Attempt to fetch latest 750 price for estimation if needed
             // Assuming this method exists and fetches a relevant market price for 750 gold per gram
             $latestPrice750 = $this->goldPriceService->fetchLatestPricePer750Gram(); // Corrected service call
        } catch (\Throwable $e) {
             $this->logger->warning("Failed to fetch latest 750 price for inventory estimation.", ['exception' => $e]);
             // Continue without price estimation if failed
        }
        $latestMazanehPrice = $this->goldPriceService->fetchLatestGoldPrice(); // This is likely per Mesghal or Gram of a specific carat
        $latestCoinPrices = $this->goldPriceService->fetchLatestCoinPrices();

        // 1. Weight Inventory
        try {
            // Assuming getAllInventorySummary fetches sums by carat and includes an average buy price/value
            $items = $this->inventoryRepository->getAllInventorySummary();
            $total750 = 0; $totalWeightValueRaw = 0; $hasRealValue = false; $totalWeightActual = 0;

            foreach ($items as &$item) {
                $weight = (float)($item['total_weight_grams'] ?? 0);
                $carat = (float)($item['carat'] ?? 0); // Use float for carat for precision
                $valueRaw = (float)($item['total_value_rials'] ?? 0); // Actual value from DB
                $itemValueDisplay = 0.0;
                $valueNote = '';

                $item['equivalent_750'] = 0.0;
                if ($weight > 0 && $carat > 0) {
                    $item['equivalent_750'] = ($weight * $carat) / 750;
                    $total750 += $item['equivalent_750']; // Add to overall total
                }

                // Check if *any* item has a real value
                if ($valueRaw !== null && $valueRaw != 0) {
                     $hasRealValue = true;
                }
                // Decide which value to display and sum for the total weight value
                 if ($valueRaw !== null && $valueRaw != 0) {
                     $itemValueDisplay = $valueRaw;
                     $valueNote = ''; // No note if real
                 } elseif ($latestPrice750 !== null && $latestPrice750 > 0 && $item['equivalent_750'] > 0) {
                     $itemValueDisplay = $item['equivalent_750'] * $latestPrice750;
                     $valueNote = ' (تخمینی)';
                 } else {
                      $itemValueDisplay = 0;
                      if ($item['equivalent_750'] > 0) $valueNote = ' (قیمت نامشخص)'; // Indicate why it's zero if weight exists
                      else $valueNote = ''; // No note if zero weight
                 }

                $totalWeightValueRaw += $itemValueDisplay; // Sum the value decided for display/summary
                $totalWeightActual += $weight; // Sum actual weight for avg calculation

                // Format for display
                $item['total_weight_grams_formatted'] = Helper::formatNumber($weight, 4);
                $item['carat_formatted'] = Helper::formatNumber($carat, 2); // Format carat too
                $item['equivalent_750_formatted'] = Helper::formatNumber($item['equivalent_750'], 4);
                $item['value_display_formatted'] = Helper::formatRial($itemValueDisplay) . $valueNote;
                $item['value_display_raw'] = $itemValueDisplay; // Store for summing in the footer


                // Calculate Avg Buy Price per Actual Gram only if using real value and weight > 0
                $item['avg_buy_price_per_gram_formatted'] = '-';
                if ($hasRealValue && $weight > 0 && $valueRaw > 0) {
                     $avgBuy = $valueRaw / $weight;
                     $item['avg_buy_price_per_gram_formatted'] = Helper::formatRial($avgBuy);
                }
            } unset($item);

            // Calculate overall average buy price if real values exist and total weight > 0
            // Calculate overall average buy price from weighted sum of real values / total actual weight
            $totalRealValue = 0;
             $totalWeightForAvg = 0;
             foreach ($items as $item) {
                 if (isset($item['total_value_rials']) && $item['total_value_rials'] !== null && (float)$item['total_value_rials'] != 0 && (float)($item['total_weight_grams'] ?? 0) > 0) {
                     $totalRealValue += (float)$item['total_value_rials'];
                     $totalWeightForAvg += (float)($item['total_weight_grams'] ?? 0);
                 }
             }
            $overallAvgBuyPrice1g = ($totalWeightForAvg > 0) ? $totalRealValue / $totalWeightForAvg : 0;


            $inventoryData['weight_inventory'] = $items;
            $inventoryData['overall_avg_buy_price_1g'] = $overallAvgBuyPrice1g;
            $inventoryData['summary']['total_750_equivalent_formatted'] = Helper::formatNumber($total750, 4);
            $inventoryData['summary']['total_weight_value_formatted'] = Helper::formatRial($totalWeightValueRaw); // Use the summed display value
            $inventoryData['summary']['value_method_is_real'] = $hasRealValue;
            $inventoryData['summary']['price_750_for_estimation'] = $latestPrice750;
            $this->logger->debug("Weight inventory processed for inventory page.");

        } catch (Throwable $e) { $this->handleDataFetchError($e, $fetchErrors, "موجودی وزنی"); }

        // 2. Coin Inventory
        try {
            // Assuming getAllCoinInventory fetches sums by coin type and maybe year
            $items = $this->coinInventoryRepository->getAllCoinInventory();
            $totalCoinValueApi = 0; // Total value based on API prices
            foreach ($items as &$item) {
                // Pass API prices array and total by reference
                $this->formatCoinItemForView($item, $latestCoinPrices, $totalCoinValueApi);
            } unset($item);
            $inventoryData['coin_inventory'] = $items;
            $inventoryData['summary']['total_coin_value_formatted'] = Helper::formatRial($totalCoinValueApi); // Use API based total
            $inventoryData['summary']['_api_coin_value_raw'] = $totalCoinValueApi; // Store raw for overall total
            $this->logger->debug("Coin inventory processed for inventory page.", ['count' => count($items), 'total_api_value' => $totalCoinValueApi]);
        } catch (Throwable $e) { $this->handleDataFetchError($e, $fetchErrors, "موجودی سکه"); }

        // 3. Overall Total Value
        // Sum the raw values determined for display (real or estimated weight value + API coin value)
        $rawWeightValueTotal = array_sum(array_column($inventoryData['weight_inventory'], 'value_display_raw')); // Use summed display value
        $rawCoinValueTotal = $inventoryData['summary']['_api_coin_value_raw'] ?? 0;
        $overallTotal = $rawWeightValueTotal + $rawCoinValueTotal;
        $inventoryData['summary']['overall_total_value_formatted'] = Helper::formatRial($overallTotal);

        // 4. سیستم تراز عملکرد - بررسی موجودی فعلی نسبت به موجودی هدف
        try {
            // دریافت محصولات با سرمایه‌گذاری هدف تنظیم شده 
            $stmt = $this->db->prepare("
                SELECT 
                    p.id, p.name, p.category_id, pc.name AS category_name, p.unit_of_measure,
                    p.capital_quantity, p.capital_weight_grams, p.capital_reference_carat,
                    (SELECT SUM(il.change_quantity) FROM inventory_ledger il WHERE il.product_id = p.id) AS current_quantity,
                    (SELECT SUM(il.change_weight_grams) FROM inventory_ledger il WHERE il.product_id = p.id) AS current_weight
                FROM 
                    products p
                    JOIN product_categories pc ON p.category_id = pc.id
                WHERE 
                    (p.capital_quantity IS NOT NULL AND p.capital_quantity > 0) 
                    OR (p.capital_weight_grams IS NOT NULL AND p.capital_weight_grams > 0)
                ORDER BY 
                    pc.name, p.name
            ");
            $stmt->execute();
            $targetProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // تنظیم اطلاعات تراز برای نمایش
            $capitalPerformanceData = [];
            foreach ($targetProducts as $product) {
                $item = [];
                $item['id'] = $product['id'];
                $item['name'] = $product['name'];
                $item['category_name'] = $product['category_name'];
                
                // بررسی نوع سنجش و محاسبه تراز
                if ($product['unit_of_measure'] === 'count') {
                    // محصولات تعدادی مانند سکه
                    $targetQty = (float)($product['capital_quantity'] ?? 0);
                    $currentQty = (float)($product['current_quantity'] ?? 0);
                    $balanceQty = $currentQty - $targetQty;
                    
                    $item['target_formatted'] = Helper::formatNumber($targetQty, 0) . ' عدد';
                    $item['current_formatted'] = Helper::formatNumber($currentQty, 0) . ' عدد';
                    $item['balance_formatted'] = Helper::formatNumber($balanceQty, 0) . ' عدد';
                    // محاسبه درصد تراز: نسبت موجودی فعلی به موجودی هدف
                    if ($targetQty > 0) {
                        $item['balance_percent'] = round(($currentQty / $targetQty) * 100, 1);
                    } elseif ($currentQty > 0) {
                        // اگر هدف صفر باشد ولی موجودی داشته باشیم، درصد را 100% در نظر می‌گیریم
                        $item['balance_percent'] = 100;
                    } else {
                        // اگر هر دو صفر باشند، درصد را 100% در نظر می‌گیریم (تراز کامل)
                        $item['balance_percent'] = 100;
                    }
                    $item['is_count'] = true;
                } else {
                    // محصولات وزنی
                    $targetWeight = (float)($product['capital_weight_grams'] ?? 0);
                    $currentWeight = (float)($product['current_weight'] ?? 0);
                    $balanceWeight = $currentWeight - $targetWeight;
                    
                    $item['target_formatted'] = Helper::formatNumber($targetWeight, 2) . ' گرم';
                    $item['current_formatted'] = Helper::formatNumber($currentWeight, 2) . ' گرم';
                    $item['balance_formatted'] = Helper::formatNumber($balanceWeight, 2) . ' گرم';
                    // محاسبه درصد تراز: نسبت موجودی فعلی به موجودی هدف
                    if ($targetWeight > 0) {
                        $item['balance_percent'] = round(($currentWeight / $targetWeight) * 100, 1);
                    } elseif ($currentWeight > 0) {
                        // اگر هدف صفر باشد ولی موجودی داشته باشیم، درصد را 100% در نظر می‌گیریم
                        $item['balance_percent'] = 100;
                    } else {
                        // اگر هر دو صفر باشند، درصد را 100% در نظر می‌گیریم (تراز کامل)
                        $item['balance_percent'] = 100;
                    }
                    $item['is_count'] = false;
                }
                
                // مشخص کردن وضعیت (کمبود یا مازاد)
                $item['status'] = 'normal';
                // اگر هم هدف و هم موجودی فعلی صفر باشد، وضعیت نرمال است
                if ($item['balance_percent'] < 95 && (($item['is_count'] && (float)$product['capital_quantity'] > 0) || 
                     (!$item['is_count'] && (float)$product['capital_weight_grams'] > 0))) {
                    $item['status'] = 'shortage';
                } elseif ($item['balance_percent'] > 105) {
                    $item['status'] = 'excess';
                }
                
                $capitalPerformanceData[] = $item;
            }
            $inventoryData['capital_performance'] = $capitalPerformanceData;
            
        } catch (Throwable $e) { 
            $this->handleDataFetchError($e, $fetchErrors, "تراز عملکرد سرمایه"); 
        }

        // 5. Pending Transactions (Displaying Pending Transaction Items)
        try {
             // Assuming findPendingItemsWithProductDetails fetches transaction items with pending status,
             // including linked product details (name, category code, unit of measure) and parent transaction info (date, counterparty)
             // This method needs to be implemented in TransactionItemRepository or TransactionRepository
             $pendingReceiptItems = $this->transactionItemRepository->findPendingItemsWithProductDetails('pending_receipt');
             $pendingDeliveryItems = $this->transactionItemRepository->findPendingItemsWithProductDetails('pending_delivery');

             foreach ($pendingReceiptItems as &$item) { $this->formatPendingItemForView($item); } unset($item);
             foreach ($pendingDeliveryItems as &$item) { $this->formatPendingItemForView($item); } unset($item);

             // Assign the formatted arrays to the main data structure for the view
             $inventoryData['pending_receipt_details'] = $pendingReceiptItems;
             $inventoryData['pending_delivery_details'] = $pendingDeliveryItems;
             $this->logger->debug("Pending items fetched.", ['receipt_count' => count($pendingReceiptItems), 'delivery_count' => count($pendingDeliveryItems)]);


        } catch (Throwable $e) { $this->handleDataFetchError($e, $fetchErrors, "تعهدات معلق"); }

        // 5. FIFO Inventory Calculation (Optional, keeping existing code structure)
         try {
             // Assuming getAll method in TransactionRepository provides data sufficient for FIFO calculation
             // and the static method in InventoryRepository can process it to get product-level FIFO.
             $allTransactions = $this->transactionRepository->getAll();

             $fifoMelted = \App\Repositories\InventoryRepository::calculateFIFOStockAndAvgPrice($allTransactions, 'melted', 'weight');
             $inventoryData['fifo_melted_stock'] = $fifoMelted['stock'];
             $inventoryData['fifo_melted_avg_price'] = $fifoMelted['avg_price'];

             $fifoEmami = \App\Repositories\InventoryRepository::calculateFIFOStockAndAvgPrice($allTransactions, 'coin_emami', 'quantity');
             $inventoryData['fifo_coin_emami_stock'] = $fifoEmami['stock'];
             $inventoryData['fifo_coin_emami_avg_price'] = $fifoEmami['avg_price'];

             $fifoBaharNew = \App\Repositories\InventoryRepository::calculateFIFOStockAndAvgPrice($allTransactions, 'coin_bahar_azadi_new', 'quantity');
             $inventoryData['fifo_coin_bahar_azadi_new_stock'] = $fifoBaharNew['stock'];
             $inventoryData['fifo_coin_bahar_azadi_new_avg_price'] = $fifoBaharNew['avg_price'];

             $fifoHalf = \App\Repositories\InventoryRepository::calculateFIFOStockAndAvgPrice($allTransactions, 'coin_half', 'quantity');
             $inventoryData['fifo_coin_half_stock'] = $fifoHalf['stock'];
             $inventoryData['fifo_coin_half_avg_price'] = $fifoHalf['avg_price'];

             $fifoQuarter = \App\Repositories\InventoryRepository::calculateFIFOStockAndAvgPrice($allTransactions, 'coin_quarter', 'quantity');
             $inventoryData['fifo_coin_quarter_stock'] = $fifoQuarter['stock'];
             $inventoryData['fifo_coin_quarter_avg_price'] = $fifoQuarter['avg_price'];

             $fifoGerami = \App\Repositories\InventoryRepository::calculateFIFOStockAndAvgPrice($allTransactions, 'coin_gerami', 'quantity');
             $inventoryData['fifo_coin_gerami_stock'] = $fifoGerami['stock'];
             $inventoryData['fifo_coin_gerami_avg_price'] = $fifoGerami['avg_price'];

             $this->logger->debug("FIFO calculation completed.");


         } catch (\Throwable $e) {
             $this->logger->error('FIFO calculation error: ' . $e->getMessage());
             // Set default empty values on error
             $inventoryData['fifo_melted_stock'] = $inventoryData['fifo_melted_avg_price'] = 0;
             $inventoryData['fifo_coin_emami_stock'] = $inventoryData['fifo_coin_emami_avg_price'] = 0;
             $inventoryData['fifo_coin_bahar_azadi_new_stock'] = $inventoryData['fifo_coin_bahar_azadi_new_avg_price'] = 0;
             $inventoryData['fifo_coin_half_stock'] = $inventoryData['fifo_coin_half_avg_price'] = 0;
             $inventoryData['fifo_coin_quarter_stock'] = $inventoryData['fifo_coin_quarter_avg_price'] = 0;
             $inventoryData['fifo_coin_gerami_stock'] = $inventoryData['fifo_coin_gerami_avg_price'] = 0;

             $fetchErrors[] = "خطا در محاسبه موجودی بر اساس FIFO.";
         }


        // Set overall error message if any fetch failed
        $errorMessage = !empty($fetchErrors) ? "خطا در بارگذاری برخی بخش‌های موجودی:<br>" . implode("<br>", $fetchErrors) : null;
        if ($errorMessage) {
             // اصلاح فراخوانی متد setSessionMessage با پارامترهای صحیح
             $this->setSessionMessage($errorMessage, 'warning', 'inventory_error');
        }

        // افزودن فیلدهای پویا و فرمول‌ها به ویو
        $fieldsData = $this->loadDynamicFields();
        $formulasData = $this->loadDynamicFormulas();

        // ارسال به ویو
        $viewData = [
            'page_title' => $pageTitle,
            'inventory_data' => $inventoryData,
            'csrf_token' => $csrfToken,
            'latestMazanehPrice' => $latestMazanehPrice,
            'latestCoinPrices' => $latestCoinPrices,
            'baseUrl' => $this->getBaseUrl(),
            'error_msg' => !empty($fetchErrors) ? implode("<br>", $fetchErrors) : null,
            'global_json_strings_for_footer' => [
                'fields' => json_encode($fieldsData),
                'formulas' => json_encode($formulasData)
            ],
            'capitalPerformanceData' => $inventoryData['capital_performance'] ?? [],
            'fields' => $fieldsData['fields'] ?? [],
            'formulas' => $formulasData['formulas'] ?? []
        ];

        $this->render('inventory/index', $viewData, true);
    }
    
    /**
     * بارگذاری فیلدهای پویا از فایل fields.json
     */
    private function loadDynamicFields(): array {
        try {
            $fieldsPath = __DIR__ . '/../../config/fields.json';
            if (!file_exists($fieldsPath)) {
                $this->logger->error("Fields file not found: {$fieldsPath}");
                return ['fields' => []];
            }
            $fieldsContent = file_get_contents($fieldsPath);
            return json_decode($fieldsContent, true) ?: ['fields' => []];
        } catch (\Throwable $e) {
            $this->logger->error("Error loading fields: " . $e->getMessage());
            return ['fields' => []];
        }
    }
    
    /**
     * بارگذاری فرمول‌های پویا از فایل formulas.json
     */
    private function loadDynamicFormulas(): array {
        try {
            $formulasPath = __DIR__ . '/../../config/formulas.json';
            if (!file_exists($formulasPath)) {
                $this->logger->error("Formulas file not found: {$formulasPath}");
                return ['formulas' => []];
            }
            $formulasContent = file_get_contents($formulasPath);
            return json_decode($formulasContent, true) ?: ['formulas' => []];
        } catch (\Throwable $e) {
            $this->logger->error("Error loading formulas: " . $e->getMessage());
            return ['formulas' => []];
        }
    }

    /** Helper to handle data fetching errors */
    private function handleDataFetchError(Throwable $e, array &$errors, string $sectionName): void {
         $this->logger->error("Error fetching {$sectionName} for inventory page.", ['exception' => $e]);
         $errorText = "خطا در بارگذاری {$sectionName}.";
         if ($this->config['app']['debug']) { $errorText .= " (" . Helper::escapeHtml($e->getMessage()) . ")";}
         $errors[] = $errorText;
    }

     /** Helper function to format coin inventory item and calculate total value */
     private function formatCoinItemForView(array &$item, array $latestCoinPrices, float &$totalValueApi): void {
         $quantity = (int)($item['quantity'] ?? 0);
         // Assuming 'coin_type' is a column in the coin inventory summary or can be derived from product_id/category
         $item['type_farsi'] = Helper::translateProductType($item['coin_type'] ?? ''); // Uses coin_type column
         $item['quantity_formatted'] = Helper::formatNumber($quantity, 0);

         // Use the fetched API price based on coin_type
         $coinKey = $item['coin_type'] ?? ''; // Assuming coin_type matches API keys
         $latestPrice = $latestCoinPrices[$coinKey] ?? null;

         $item['latest_unit_price_formatted'] = $latestPrice ? Helper::formatRial($latestPrice) : '-';

         // Calculate Estimated Value using API price
         $estimatedValue = 0.0;
         if ($quantity > 0 && $latestPrice !== null && $latestPrice > 0) {
             $estimatedValue = $quantity * $latestPrice;
             $totalValueApi += $estimatedValue; // Add to grand total API value
             $item['estimated_value_formatted'] = Helper::formatRial($estimatedValue);
         } else {
             $item['estimated_value_formatted'] = '-';
         }
     }

      /** Helper function to format pending transaction items */
      // This function now formats data for TRANSACTION ITEMS with pending status,
      // assuming the repository provides necessary joins to transaction and product tables.
      private function formatPendingItemForView(array &$item): void {
           // $item is now an array representing a transaction_item row, joined with transaction and product info
           // Required joins: transaction (for transaction_date, counterparty_contact_id, transaction_type),
           // product (for name, category_id, unit_of_measure), product_category (for code, requires_...)
           // counterparty contact (for name)

           $item['transaction_type_farsi'] = match ($item['transaction_type'] ?? '') {
                'buy' => 'خرید', 'sell' => 'فروش', default => '؟'
            };
           $item['counterparty_name'] = Helper::escapeHtml($item['counterparty_name'] ?? '[نامشخص]');
           
           // به جای استفاده از تابع formatSqlDatetimeToPersian، مستقیماً فرمت تاریخ را تنظیم می‌کنیم
           // این فرمت در ویو با استفاده از jalaali نمایش داده خواهد شد
           $item['transaction_date_persian'] = $item['transaction_date'] ?? '';

           // Determine display quantity/weight and spec based on product category code (from item's product join)
           // Use product_category_code if available from join, fallback to product_code if not, or generic
           $productCode = $item['product_code'] ?? ''; // From product join
           $productCategoryCode = $item['product_category_code'] ?? ''; // From category join
           $productName = $item['product_name'] ?? '[کالا نامشخص]'; // From product join
           $productUnitOfMeasure = $item['product_unit_of_measure'] ?? 'gram'; // From product join

           // Use item-level quantity/weight/carat/coin_year columns from the transaction_items table
           $quantity = (int)($item['quantity'] ?? 0);
           $weight = (float)($item['weight_grams'] ?? 0);
           $carat = Helper::escapeHtml($item['carat'] ?? '-'); // فقط از فیلد carat استفاده می‌کنیم
           $coinYear = Helper::escapeHtml($item['coin_year'] ?? '-');

           $item['product_name'] = Helper::escapeHtml($productName);
           
           // اطمینان می‌دهیم که کد دسته‌بندی محصول کد متنی باشد نه عددی
           // اگر کد عددی باشد، باید آن را بر اساس کد حقیقی ترجمه کنیم
           if (is_numeric($productCategoryCode)) {
               // نگاشت کدهای عددی به کدهای متنی بر اساس کد پروژه
               $numericProductCodeMap = [
                   '1' => 'new_jewelry',        // طلای نو / ساخته شده
                   '2' => 'used_jewelry',       // طلای دست دوم / متفرقه
                   '3' => 'melted',             // آبشده
                   '4' => 'coin_emami',         // سکه امامی
                   '5' => 'coin_bahar_azadi_new', // سکه بهار آزادی جدید
                   '6' => 'coin_bahar_azadi_old', // سکه بهار آزادی قدیم
                   '7' => 'coin_half',          // نیم سکه
                   '8' => 'coin_quarter',       // ربع سکه
                   '9' => 'coin_gerami',        // سکه گرمی
                   '10' => 'bullion',           // شمش طلا
                   '11' => 'other_coin'         // سایر سکه‌ها
               ];
               $productCategoryCode = $numericProductCodeMap[$productCategoryCode] ?? "unknown_type_{$productCategoryCode}";
           }
           
           // حالا که مطمئن هستیم کد به صورت متنی است، ترجمه می‌کنیم
           $item['product_type_farsi'] = Helper::translateProductType($productCategoryCode);

           if ($productUnitOfMeasure === 'count') {
               $item['display_quantity_or_weight'] = Helper::formatNumber($quantity, 0);
               $item['display_spec'] = $coinYear; // Year is spec for coins
               // Set others to null/empty string for consistency if needed in view checks
               $item['weight_formatted'] = '-';
               $item['carat_formatted'] = '-';
               $item['quantity_formatted'] = $item['display_quantity_or_weight']; // Keep original too for potential other uses
           } elseif ($productUnitOfMeasure === 'gram') { // Assume weight-based gold if not count
               $item['display_quantity_or_weight'] = Helper::formatNumber($weight, 4);
               $item['display_spec'] = $carat; // Carat is spec for weight-based
               // Set others to null/empty string
               $item['quantity_formatted'] = '-';
               $item['weight_formatted'] = $item['display_quantity_or_weight']; // Keep original too
               $item['carat_formatted'] = $carat;
           } else { // Unknown unit
               $item['display_quantity_or_weight'] = '-';
               $item['display_spec'] = '-';
               $item['quantity_formatted'] = '-';
               $item['weight_formatted'] = '-';
               $item['carat_formatted'] = '-';
           }
           // Add transaction ID for linking
           // اطمینان از وجود transaction_id برای استفاده در لینک‌ها
           $item['transaction_id'] = $item['transaction_id'] ?? null;
           
           // همه معاملات منتظر تحویل از نوع "فروش" هستند
           if (isset($item['delivery_status']) && $item['delivery_status'] === 'pending_delivery') {
               $item['transaction_type'] = 'sell';
           }
           
           // همه معاملات منتظر دریافت از نوع "خرید" هستند
           if (isset($item['delivery_status']) && $item['delivery_status'] === 'pending_receipt') {
               $item['transaction_type'] = 'buy';
           }
      }

      /** Helper to fetch latest 750 price (already exists in GoldPriceService) */
     // private function fetchLatest750Price(): ?float { /* This method is now redundant, use GoldPriceService */ }

     // Ensure findPendingItemsWithProductDetails is added/updated in TransactionItemRepository

    /**
     * دریافت URL پایه سایت از تنظیمات
     * @return string
     */
    private function getBaseUrl(): string
    {
        return rtrim($this->config['app']['base_url'] ?? '', '/');
    }
}
