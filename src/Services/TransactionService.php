<?php
// src/Services/TransactionService.php
namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionItemRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\ContactWeightLedgerRepository;
use App\Repositories\InventoryLedgerRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\CoinInventoryRepository;
use App\Utils\Helper;
use Monolog\Logger;
use PDO;
use Throwable;

class TransactionService
{
    private Logger $logger;
    private PDO $db;
    private TransactionRepository $transactionRepository;
    private TransactionItemRepository $transactionItemRepository;
    private ProductRepository $productRepository;
    private FormulaService $formulaService;
    private ContactWeightLedgerRepository $contactWeightLedgerRepository;
    private InventoryLedgerRepository $inventoryLedgerRepository;
    private InventoryRepository $inventoryRepository;
    private CoinInventoryRepository $coinInventoryRepository;

    public function __construct(
        PDO $db,
        Logger $logger,
        TransactionRepository $transactionRepository,
        TransactionItemRepository $transactionItemRepository,
        ProductRepository $productRepository,
        FormulaService $formulaService,
        ContactWeightLedgerRepository $contactWeightLedgerRepository,
        InventoryLedgerRepository $inventoryLedgerRepository,
        InventoryRepository $inventoryRepository,
        CoinInventoryRepository $coinInventoryRepository
    ) {
        $this->db = $db;
        $this->logger = $logger;
        $this->transactionRepository = $transactionRepository;
        $this->transactionItemRepository = $transactionItemRepository;
        $this->productRepository = $productRepository;
        $this->formulaService = $formulaService;
        $this->contactWeightLedgerRepository = $contactWeightLedgerRepository;
        $this->inventoryLedgerRepository = $inventoryLedgerRepository;
        $this->inventoryRepository = $inventoryRepository;
        $this->coinInventoryRepository = $coinInventoryRepository;
    }

    public function saveTransaction(array $requestData, ?int $id = null): int
    {
        $isEditMode = $id !== null;
        $this->logger->info("Service: Starting save transaction.", ['id' => $id, 'is_edit' => $isEditMode]);

        try {
            $this->validateTransactionData($requestData);
            
            // شروع تراکنش دیتابیس باید اولین کار باشد
            $this->db->beginTransaction();

            if ($isEditMode) {
                $this->contactWeightLedgerRepository->deleteByTransactionId($id);
            }

            $transaction = $this->createTransactionObject($requestData, $id);
            $this->logger->debug("SERVICE: Transaction object created.", ['transaction_id' => $transaction->id]);

            if (empty($requestData['items'])) {
                throw new \Exception("هیچ قلم کالایی برای ذخیره وجود ندارد.");
            }
            
            $productIds = array_unique(array_filter(array_column($requestData['items'], 'product_id')));
            $productsById = $this->productRepository->findByIds($productIds, true);

            $itemsToSave = [];
            $calculatedItemsForSummary = [];
            
            foreach ($requestData['items'] as $itemData) {
                if (empty($itemData['product_id'])) continue;
                $product = $productsById[$itemData['product_id']] ?? null;
                if (!$product) throw new \Exception("محصول با شناسه {$itemData['product_id']} یافت نشد.");

                $finalItemData = $this->recalculateItemOnServer($itemData, $transaction, $product);
                
                $item = new TransactionItem();
                $item->id = !empty($itemData['id']) ? (int)$itemData['id'] : null;
                
                $this->populateItemModel($item, $itemData, $finalItemData, $product);
                
                $itemsToSave[] = $item;
                $calculatedItemsForSummary[] = $finalItemData;
            }

            if (empty($itemsToSave)) {
                throw new \Exception("هیچ قلم کالای معتبری برای ذخیره وجود ندارد.");
            }

            $summary = $this->formulaService->calculateTransactionSummary($calculatedItemsForSummary);
            foreach ($summary as $key => $value) {
                if (property_exists($transaction, $key)) {
                    $transaction->{$key} = $value;
                }
            }
            
            $savedTransactionId = $this->transactionRepository->save($transaction);
            
            if ($isEditMode) {
                $itemIdsToKeep = array_filter(array_column($itemsToSave, 'id'));
                $this->transactionItemRepository->deleteRemovedItems($savedTransactionId, $itemIdsToKeep);
            }

            foreach ($itemsToSave as $item) {
                $item->transaction_id = $savedTransactionId;
                $this->transactionItemRepository->save($item);
            }

            $weightCommitments = $this->calculateWeightCommitments($calculatedItemsForSummary, $productsById);
            foreach ($weightCommitments as $commitment) {
                $this->recordWeightCommitment(
                    $transaction->counterparty_contact_id,
                    $commitment['category_id'],
                    $commitment['weight_change'],
                    $savedTransactionId,
                    $transaction->transaction_type
                );
            }
            
            $this->db->commit();
            return $savedTransactionId;

        } catch (Throwable $e) {
            // فقط در صورتی rollBack کن که تراکنش فعال باشد
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logger->error("Failed to save transaction.", ['exception' => $e]);
            // پرتاب مجدد خطا تا کنترلر آن را مدیریت کند
            throw $e;
        }
    }


    /**
     * (بازنویسی کامل) این تابع مدل TransactionItem را از داده‌های نهایی (ترکیب ورودی فرم و محاسبات سرور) پر می‌کند.
     * این تابع تمام انواع کالا را به صورت جامع مدیریت می‌کند.
     */
  /**
     * (نسخه نهایی) مدل TransactionItem را از داده‌های فرم و محاسبات پر می‌کند.
     */
  /**
     * (نسخه نهایی) مدل TransactionItem را از داده‌های فرم و محاسبات پر می‌کند.
     */
    private function populateItemModel(TransactionItem &$item, array $formData, array $calculatedData, Product $product): void
    {
        $baseCategory = strtolower($product->category->base_category);
        
        // مقادیر عمومی
        $item->product_id = $product->id;
        $item->description = $formData['item_description'] ?? null;
        $item->unit_price_rials = (float)($calculatedData['item_unit_price_' . $baseCategory] ?? 0.0);
        $item->total_value_rials = (float)($calculatedData['item_total_price_' . $baseCategory] ?? 0.0);
        // (اصلاح کلیدی) ذخیره درصد و مبلغ سود
        $item->profit_percent = (float)($formData['item_profit_percent_' . $baseCategory] ?? 0.0);
        $item->profit_amount_rials = (float)($calculatedData['item_profit_amount_' . $baseCategory] ?? 0.0);
        // (اصلاح کلیدی) ذخیره درصد و مبلغ کارمزد
        $item->fee_percent = (float)($formData['item_fee_percent_' . $baseCategory] ?? 0.0);
        $item->fee_amount_rials = (float)($calculatedData['item_fee_amount_' . $baseCategory] ?? 0.0);
        $item->weight_grams = (float)($formData['item_weight_scale_' . $baseCategory] ?? $formData['item_weight_carat_jewelry'] ?? 0.0);
        $item->carat = (int)($formData['item_carat_' . $baseCategory] ?? 0);
        // (جدید) ذخیره مقادیر نهایی مالیات محاسبه شده برای هر آیتم
        $item->general_tax_rials = (float)($calculatedData['item_general_tax_' . $baseCategory] ?? 0.0);
        $item->vat_rials = (float)($calculatedData['item_vat_' . $baseCategory] ?? 0.0);

        // مقادیر اختصاصی
        switch ($baseCategory) {
            case 'melted':
                $item->tag_number = $formData['item_tag_number_melted'] ?? null;
                $item->tag_type = $formData['item_tag_type_melted'] ?? null;
                $assayOfficeId = $formData['item_assay_office_melted'] ?? null;
                $item->assay_office_id = !empty($assayOfficeId) && $assayOfficeId > 0 ? (int)$assayOfficeId : null;
                break;
            case 'manufactured':
                $item->quantity = (int)($formData['item_quantity_manufactured'] ?? 0);
                $item->workshop_name = $formData['item_workshop_manufactured'] ?? null;
                $item->stone_weight_grams = (float)($formData['item_attachment_weight_manufactured'] ?? 0.0);
                $item->manufactured_item_type = $formData['item_type_manufactured'] ?? null;
                $item->has_attachments = ($formData['item_has_attachments_manufactured'] ?? 'No') === 'Yes' ? 1 : 0;
                $item->attachment_type = $formData['item_attachment_type_manufactured'] ?? null;
                $item->ajrat_percent = (float)($calculatedData['item_manufacturing_fee_rate_manufactured'] ?? 0.0);
                $item->ajrat_rials = (float)($calculatedData['item_manufacturing_fee_amount_manufactured'] ?? 0.0);
                break;
            case 'coin':
                $item->quantity = (int)($formData['item_quantity_coin'] ?? 0);
                $item->coin_year = (int)($formData['item_coin_year_coin'] ?? 0);
                $item->is_bank_coin = ($formData['item_type_coin'] ?? 'misc') === 'bank';
                $item->seal_name = $formData['item_vacuum_name_coin'] ?? null;
                break;
            case 'bullion':
                $item->tag_number = $formData['item_bullion_number_bullion'] ?? null;
                $item->workshop_name = $formData['item_manufacturer_bullion'] ?? null;
                break;
            case 'jewelry':
                $item->quantity = (int)($formData['item_quantity_jewelry'] ?? 0);
                $item->jewelry_type = $formData['item_type_jewelry'] ?? null;
                $item->jewelry_color = $formData['item_color_jewelry'] ?? null;
                $item->jewelry_quality = $formData['item_quality_grade_jewelry'] ?? null;
                break;
        }
    }
    
    private function createTransactionObject(array $postData, ?int $id): Transaction
    {
        $transaction = new Transaction();
        $transaction->id = $id;
        $transaction->transaction_type = $postData['transaction_type'];
        $transaction->transaction_date = Helper::parseJalaliDatetimeToSql($postData['transaction_date']);
        $transaction->counterparty_contact_id = (int)($postData['counterparty_contact_id'] ?? 0);
        $transaction->mazaneh_price = (float)Helper::sanitizeFormattedNumber($postData['mazaneh_price'] ?? '0');
        $transaction->delivery_status = $postData['delivery_status'];
        $transaction->notes = trim($postData['notes'] ?? '');
        $transaction->created_by_user_id = $_SESSION['user_id'] ?? null;
        $transaction->updated_by_user_id = $_SESSION['user_id'] ?? null;
        return $transaction;
    }

    public function recalculateItemOnServer(array $itemData, Transaction $transaction, Product $product): array
    {
        $formulaGroup = strtolower($product->category->base_category);
        // (اصلاح شده) آماده‌سازی داده‌ها برای موتور محاسباتی
        $values = array_merge($itemData, [
            'mazaneh_price' => $transaction->mazaneh_price,
            'product_group' => $formulaGroup,
            // ارسال فیلدهای لازم برای فرمول‌های مالیات شما
            'product_tax_enabled' => ($product->general_tax_base_type !== 'NONE'),
            'product_tax_rate' => (float)($product->tax_rate ?? 0.0),
            'product_vat_enabled' => ($product->vat_base_type !== 'NONE'),
            'product_vat_rate' => (float)($product->vat_rate ?? 0.0),
        ]);

              // پاک‌سازی ورودی‌ها برای جلوگیری از خطا
        foreach ($values as $key => &$value) {
            if (is_string($value)) {
                $numericValue = str_replace(',', '', $value);
                if (is_numeric($numericValue)) {
                    $value = (float)$numericValue;
                }
            }
        }
        unset($value);

        // 2. انجام محاسبات اصلی آیتم توسط سرویس فرمول
        $calculatedData = $this->formulaService->calculateAllForItem($values, $formulaGroup);
        
        // 3. (اصلاح شده) محاسبه مستقیم مالیات پس از محاسبات اصلی
        $item_profit = (float)($calculatedData['item_profit_amount_' . $formulaGroup] ?? 0.0);
        $item_wage = (float)($calculatedData['item_manufacturing_fee_amount_manufactured'] ?? 0.0);

        // محاسبه مالیات عمومی
        $general_tax_base = 0.0;
        if ($product->general_tax_base_type === 'WAGE_PROFIT') {
            $general_tax_base = $item_profit + $item_wage;
        } elseif ($product->general_tax_base_type === 'PROFIT_ONLY') {
            $general_tax_base = $item_profit;
        }
        $calculatedData['item_general_tax_' . $formulaGroup] = round($general_tax_base * ((float)($product->tax_rate ?? 0.0) / 100.0));

        // محاسبه ارزش افزوده
        $vat_base = 0.0;
        if ($product->vat_base_type === 'WAGE_PROFIT') {
            $vat_base = $item_profit + $item_wage;
        } elseif ($product->vat_base_type === 'PROFIT_ONLY') {
            $vat_base = $item_profit;
        }
        $calculatedData['item_vat_' . $formulaGroup] = round($vat_base * ((float)($product->vat_rate ?? 0.0) / 100.0));
        
        // 4. ادغام نتایج نهایی و بازگرداندن آن‌ها
        return array_merge($values, $calculatedData);
    }


 public function completeDelivery(int $transactionId, string $action): bool
    {
        $this->db->beginTransaction();
        try {
            $transaction = $this->transactionRepository->findById($transactionId);
            if (!$transaction) throw new \Exception("معامله مورد نظر یافت نشد.");
            if (is_array($transaction)) $transaction = new Transaction($transaction);

            if (($action === 'receipt' && $transaction->delivery_status !== 'pending_receipt') ||
                ($action === 'delivery' && $transaction->delivery_status !== 'pending_delivery')) {
                throw new \Exception("این معامله در وضعیت صحیحی برای این عملیات نیست.");
            }

            $transaction->delivery_status = 'completed';
            $transaction->delivery_date = date('Y-m-d H:i:s');
            $this->transactionRepository->save($transaction);

            $transactionItems = $this->transactionItemRepository->findByTransactionId($transactionId);
            if (empty($transactionItems)) throw new \Exception("آیتمی برای این معامله یافت نشد.");

            foreach ($transactionItems as $itemData) {
                $product = $this->productRepository->findByIdWithCategory((int)$itemData['product_id']);
                if (!$product) continue;

                $isWeightBased = ($product->unit_of_measure === 'gram');
                $isCoin = ($product->category->base_category === 'COIN');
                
                $change_quantity = 0;
                $change_weight = 0.0;
                $event_type = '';

                if ($action === 'receipt' && $transaction->transaction_type === 'buy') {
                    $event_type = 'PURCHASE';
                    if ($isCoin) $change_quantity = (int)$itemData['quantity'];
                    if ($isWeightBased) $change_weight = (float)$itemData['weight_grams'];
                } elseif ($action === 'delivery' && $transaction->transaction_type === 'sell') {
                    $event_type = 'SALE';
                    if ($isCoin) $change_quantity = -(int)$itemData['quantity'];
                    if ($isWeightBased) $change_weight = -(float)$itemData['weight_grams'];
                } else {
                    continue;
                }
                
                if ($change_quantity !== 0 || $change_weight !== 0.0) {
                    $this->inventoryLedgerRepository->recordChange([
                        'product_id' => $product->id,
                        'transaction_item_id' => $itemData['id'],
                        'change_quantity' => $change_quantity,
                        'change_weight_grams' => $change_weight,
                        'event_type' => $event_type,
                        'event_date' => $transaction->delivery_date,
                        'notes' => "تکمیل معامله #" . $transactionId
                    ]);
                }

                // (اصلاح شده) به‌روزرسانی موجودی فیزیکی
                if ($isWeightBased && isset($itemData['carat'])) {
                    $this->inventoryRepository->updateInventoryByCarat((int)$itemData['carat'], $change_weight, 0);
                } elseif ($isCoin) {
                    // **اصلاح کلیدی: دسترسی به پراپرتی آبجکت به جای کلید آرایه**
                    $this->coinInventoryRepository->updateCoinInventoryQuantity($product->product_code, $change_quantity);
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->logger->error("Failed to complete delivery action.", ['transaction_id' => $transactionId, 'action' => $action, 'exception' => $e]);
            throw $e;
        }
    }
    
    private function validateTransactionData(array $postData): void
    {
        if (empty($postData['transaction_type'])) throw new \Exception("نوع معامله الزامی است.");
        if (empty($postData['counterparty_contact_id'])) throw new \Exception("انتخاب طرف حساب الزامی است.");
        if (empty($postData['transaction_date'])) throw new \Exception("تاریخ معامله الزامی است.");
    }

   /**
     * (جدید) تعهدات وزنی را برای ثبت در کاردکس وزنی محاسبه می‌کند.
     */
    private function calculateWeightCommitments(array $calculatedItems, array $productsById): array
    {
        $commitments = [];
        foreach ($calculatedItems as $item) {
            $product = $productsById[$item['product_id']] ?? null;
            if (!$product || $product->unit_of_measure !== 'gram') continue;

            $baseCategory = strtolower($product->category->base_category);
            // وزن معادل ۷۵۰ را از داده‌های محاسبه شده استخراج می‌کند
            $weight750 = (float)($item['item_weight_750_' . $baseCategory] ?? 0.0);

            if ($weight750 > 0) {
                $catId = $product->category_id;
                if (!isset($commitments[$catId])) {
                    $commitments[$catId] = ['category_id' => $catId, 'weight_change' => 0.0];
                }
                $commitments[$catId]['weight_change'] += $weight750;
            }
        }
        return array_values($commitments);
    }
    
    /**
     * (جدید) یک رکورد جدید در کاردکس بدهی/بستانکاری وزنی طرف حساب ثبت می‌کند.
     */
    private function recordWeightCommitment(int $contactId, int $categoryId, float $weightChange, int $transactionId, string $transactionType)
    {
        if (abs($weightChange) < 0.001) return;
        
        $lastBalance = $this->contactWeightLedgerRepository->getLastBalance($contactId, $categoryId);
        
        // منطق بدهکار/بستانکار کردن وزنی
        // فروش: طرف حساب به ما طلا بدهکار می‌شود (بدهی او مثبت می‌شود)
        // خرید: طرف حساب از ما طلا طلبکار می‌شود (بدهی او منفی می‌شود)
        $change = ($transactionType === 'sell') ? $weightChange : -$weightChange;
        $newBalance = $lastBalance + $change;
        
        $this->contactWeightLedgerRepository->recordEntry([
            'contact_id' => $contactId,
            'product_category_id' => $categoryId,
            'event_type' => 'TRANSACTION',
            'change_weight_grams' => $change,
            'balance_after_grams' => $newBalance,
            'related_transaction_id' => $transactionId,
            'notes' => "بابت معامله شماره {$transactionId}"
        ]);
    }
}