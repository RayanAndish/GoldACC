<?php
namespace App\Services;

use PDO;
use Monolog\Logger;
use Exception;
use Throwable;
use App\Repositories\TransactionRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\CoinInventoryRepository;
use App\Repositories\TransactionItemRepository; // جدید
use App\Repositories\ProductRepository; // جدید
use App\Repositories\ContactWeightLedgerRepository; // جدید
use App\Utils\Helper; // جدید

class DeliveryService {

    private TransactionRepository $transactionRepository;
    private InventoryRepository $inventoryRepository;
    private CoinInventoryRepository $coinInventoryRepository;
    private TransactionItemRepository $transactionItemRepository; // جدید
    private ProductRepository $productRepository; // جدید
    private ContactWeightLedgerRepository $contactWeightLedgerRepository; // جدید
    private Logger $logger;
    private PDO $db;

    public function __construct(
        TransactionRepository $transactionRepository,
        InventoryRepository $inventoryRepository,
        CoinInventoryRepository $coinInventoryRepository,
        TransactionItemRepository $transactionItemRepository, // جدید
        ProductRepository $productRepository, // جدید
        ContactWeightLedgerRepository $contactWeightLedgerRepository, // جدید
        Logger $logger,
        PDO $db
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->inventoryRepository = $inventoryRepository;
        $this->coinInventoryRepository = $coinInventoryRepository;
        $this->transactionItemRepository = $transactionItemRepository; // جدید
        $this->productRepository = $productRepository; // جدید
        $this->contactWeightLedgerRepository = $contactWeightLedgerRepository; // جدید
        $this->logger = $logger;
        $this->db = $db;
        $this->logger->debug("DeliveryService initialized.");
    }

    public function completeDelivery(int $transactionId, string $actionType): bool {
        $this->logger->info("Attempting delivery completion.", ['transaction_id' => $transactionId, 'action' => $actionType]);

        if (!in_array($actionType, ['receipt', 'delivery'])) {
             throw new Exception('عملیات نامعتبر: نوع عملیات (' . htmlspecialchars($actionType) . ') شناخته شده نیست.');
        }
        
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }

        try {
            // **اصلاح ۱: واکشی معامله به همراه تمام آیتم‌هایش**
            $transaction = $this->transactionRepository->findByIdWithItems($transactionId);

            if (!$transaction || empty($transaction['items'])) {
                 if ($this->db->inTransaction()) $this->db->rollBack();
                 throw new Exception('معامله با شناسه ' . $transactionId . ' یا آیتم‌های آن یافت نشد.');
            }

            // 3. Validate Transaction Status and Type Consistency
            $expectedStatus = ($actionType === 'receipt') ? 'pending_receipt' : 'pending_delivery';
            if (($transaction['delivery_status'] ?? null) !== $expectedStatus) {
                 if ($this->db->inTransaction()) $this->db->rollBack();
                 $this->logger->error("Transaction delivery status mismatch.", [
                     'transaction_id' => $transactionId,
                     'current_status' => $transaction['delivery_status'] ?? 'N/A',
                     'expected_status' => $expectedStatus
                 ]);
                 throw new Exception('وضعیت فعلی معامله (' . ($transaction['delivery_status'] ?? 'نامشخص') . ') اجازه این عملیات را نمی‌دهد.');
            }
            $transactionType = $transaction['transaction_type'] ?? null;
            if (($actionType === 'receipt' && $transactionType !== 'buy') || ($actionType === 'delivery' && $transactionType !== 'sell')) {
                 if ($this->db->inTransaction()) $this->db->rollBack();
                 $this->logger->error("Transaction type does not match action type.", [
                    'transaction_id' => $transactionId,
                    'transaction_type' => $transactionType,
                    'action_type' => $actionType
                 ]);
                 throw new Exception('نوع عملیات (' . $actionType . ') با نوع معامله (' . $transactionType . ') همخوانی ندارد.');
            }


    // 4. به‌روزرسانی وضعیت کلی معامله
            // شما از متد updateDeliveryStatus استفاده کرده بودید که به نظر می‌رسد دیگر لازم نیست
            // ما از متد save استاندارد استفاده می‌کنیم
            $txObject = new \App\Models\Transaction($transaction);
            $txObject->delivery_status = 'completed';
            $txObject->delivery_date = date('Y-m-d H:i:s');
            $this->transactionRepository->save($txObject);
            $this->logger->info("Transaction status updated.", ['transaction_id' => $transactionId, 'new_status' => 'completed']);
            
            // **اصلاح ۲: انتقال منطق به‌روزرسانی موجودی به داخل حلقه روی آیتم‌ها**
            foreach($transaction['items'] as $item) {
                $product = $this->productRepository->findByIdWithCategory((int)$item['product_id']);
                if(!$product) {
                    $this->logger->warning("Product not found for transaction item, skipping inventory update.", ['item_id' => $item['id']]);
                    continue;
                }

                $isWeightBased = in_array($product->category->base_category, ['MELTED', 'MANUFACTURED', 'BULLION', 'JEWELRY']);
                $isCoinBased = $product->category->base_category === 'COIN';

                if ($isWeightBased) {
                     $weightChange = (float)(($transactionType == 'buy') ? +$item['weight_grams'] : -$item['weight_grams']);
                     $this->inventoryRepository->updateInventoryByCarat((int)$item['carat'], $weightChange, 0);

                     // **منطق جدید: ثبت در کاردکس وزنی مخاطب**
                     $weight750 = Helper::convertGoldToCarat((float)$item['weight_grams'], (int)$item['carat']);
                     if ($weight750 > 0) {
                        $this->recordWeightLedgerEntry(
                            $transaction['counterparty_contact_id'],
                            $product->category_id,
                            $weight750,
                            $transactionId,
                            $transactionType
                        );
                     }
                }
                
                if ($isCoinBased) {
                     $quantityChange = (int)(($transactionType == 'buy') ? +$item['quantity'] : -$item['quantity']);
                     $this->coinInventoryRepository->updateCoinInventoryQuantity($product->product_code, $quantityChange);
                }
            }

            // 6. تایید نهایی تراکنش دیتابیس
            if ($this->db->inTransaction()) {
                $this->db->commit();
                $this->logger->info("Delivery completion transaction committed successfully.", ['transaction_id' => $transactionId]);
            }
            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                 $this->db->rollBack();
            }
            $this->logger->error("Error during delivery completion.", [
                'transaction_id' => $transactionId,
                'exception_message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * (جدید) یک رکورد در کاردکس وزنی مخاطب ثبت می‌کند.
     */
    private function recordWeightLedgerEntry(int $contactId, int $categoryId, float $weightChange, int $transactionId, string $transactionType): void
    {
        $lastBalance = $this->contactWeightLedgerRepository->getLastBalance($contactId, $categoryId);
        
        // فروش: او به ما طلا بدهکار می‌شود (بدهی‌اش مثبت)
        // خرید: او از ما طلا طلبکار می‌شود (بدهی‌اش منفی)
        $change = ($transactionType === 'sell') ? $weightChange : -$weightChange;
        $newBalance = $lastBalance + $change;
        
        $this->contactWeightLedgerRepository->recordEntry([
            'contact_id' => $contactId,
            'product_category_id' => $categoryId,
            'event_type' => 'TRANSACTION',
            'change_weight_grams' => $change,
            'balance_after_grams' => $newBalance,
            'related_transaction_id' => $transactionId,
            'notes' => "تکمیل معامله #" . $transactionId
        ]);
        $this->logger->info("Contact weight ledger updated.", [
            'contact' => $contactId, 'category' => $categoryId, 'change' => $change, 'new_balance' => $newBalance
        ]);
    }
}