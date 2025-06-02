<?php

namespace App\Services; // Namespace مطابق با پوشه src/Services

use PDO; // For transaction management if handled here
use PDOException; // Specific DB exceptions
use Monolog\Logger;
use Exception; // General exceptions
use Throwable; // Catch errors and exceptions
use App\Repositories\TransactionRepository; // Assumes this repository exists
use App\Repositories\InventoryRepository; // Assumes this repository exists
use App\Repositories\CoinInventoryRepository; // Assumes this repository exists

/**
 * DeliveryService handles the business logic for completing the delivery or receipt
 * status of gold transactions. It updates the transaction status and adjusts
 * the corresponding inventory records atomically.
 */
class DeliveryService {

    // Dependencies injected via constructor
    private TransactionRepository $transactionRepository;
    private InventoryRepository $inventoryRepository;
    private CoinInventoryRepository $coinInventoryRepository;
    private Logger $logger;
    private PDO $db; // PDO instance for managing transactions at the service level

    /**
     * Constructor.
     *
     * @param TransactionRepository $transactionRepository Transaction repository instance.
     * @param InventoryRepository $inventoryRepository Weight-based inventory repository instance.
     * @param CoinInventoryRepository $coinInventoryRepository Coin inventory repository instance.
     * @param Logger $logger Logger instance.
     * @param PDO $db PDO instance for transaction management.
     */
    public function __construct(
        TransactionRepository $transactionRepository,
        InventoryRepository $inventoryRepository,
        CoinInventoryRepository $coinInventoryRepository,
        Logger $logger,
        PDO $db // Inject PDO for service-level transaction control
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->inventoryRepository = $inventoryRepository;
        $this->coinInventoryRepository = $coinInventoryRepository;
        $this->logger = $logger;
        $this->db = $db; // Store PDO instance
        $this->logger->debug("DeliveryService initialized.");
    }


    /**
     * Completes the delivery or receipt process for a specific transaction.
     * Updates transaction status and adjusts inventory within a database transaction.
     *
     * @param int $transactionId The ID of the transaction to complete.
     * @param string $actionType The type of action: 'receipt' (for buys) or 'delivery' (for sells).
     * @return bool True if the operation was successful.
     * @throws Exception If validation fails, transaction status is incorrect, or data is missing.
     * @throws PDOException If a database error occurs during the transaction.
     */
    public function completeDelivery(int $transactionId, string $actionType): bool {
        $this->logger->info("Attempting delivery completion.", ['transaction_id' => $transactionId, 'action' => $actionType]);

        // 1. Validate Action Type
        if (!in_array($actionType, ['receipt', 'delivery'])) {
             $this->logger->error("Invalid action type provided.", ['transaction_id' => $transactionId, 'action_type' => $actionType]);
             throw new Exception('عملیات نامعتبر: نوع عملیات (' . htmlspecialchars($actionType) . ') شناخته شده نیست.');
        }

        // --- Start Database Transaction ---
        // Managing the transaction here ensures atomicity across transaction update and inventory update.
        if ($this->db->inTransaction()) {
             // Avoid nested transactions if already started by controller, though PDO might handle it.
             $this->logger->warning("completeDelivery called while already in a transaction.", ['transaction_id' => $transactionId]);
        } else {
            $this->db->beginTransaction();
            $this->logger->debug("Database transaction started.", ['transaction_id' => $transactionId]);
        }
        // --- End Database Transaction Start ---


        try {
            // 2. Fetch Transaction with Lock
            // Assumes TransactionRepository::getByIdWithLock selects FOR UPDATE to prevent race conditions
            $transaction = $this->transactionRepository->getByIdWithLock($transactionId);

            if (!$transaction) {
                 if ($this->db->inTransaction()) $this->db->rollBack(); // Rollback if we started it
                 $this->logger->error("Transaction not found.", ['transaction_id' => $transactionId]);
                 throw new Exception('معامله با شناسه ' . $transactionId . ' یافت نشد.');
            }
            $this->logger->debug("Transaction record fetched and locked.", ['transaction_id' => $transactionId]);


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


            // 4. Update Transaction Status and Delivery Date
            $deliveryTimestamp = date('Y-m-d H:i:s');
            $newStatus = 'completed';
            // Assumes TransactionRepository::updateDeliveryStatus handles the update based on current expected status
            $isTransactionUpdated = $this->transactionRepository->updateDeliveryStatus($transactionId, $newStatus, $deliveryTimestamp, $expectedStatus);

            if (!$isTransactionUpdated) {
                 // This could happen if the status was changed by another process between the lock and update
                 if ($this->db->inTransaction()) $this->db->rollBack();
                 $this->logger->error("Failed to update transaction status. Possible concurrency issue or status change.", ['transaction_id' => $transactionId, 'expected_status' => $expectedStatus]);
                 throw new Exception('خطا در به‌روزرسانی وضعیت معامله. ممکن است وضعیت آن توسط پردازش دیگری تغییر کرده باشد.');
            }
            $this->logger->info("Transaction status updated.", ['transaction_id' => $transactionId, 'new_status' => $newStatus]);


            // 5. Update Corresponding Inventory
            $productType = $transaction['gold_product_type'] ?? null;
            $inventoryUpdated = false; // Flag to track if any inventory update was attempted

            $isWeightBased = in_array($productType, ['melted', 'used_jewelry', 'new_jewelry', 'bullion']);
            $isCoinBased = $productType && str_starts_with($productType, 'coin_');

            if ($isWeightBased) {
                // Validate required fields for weight update
                if (!isset($transaction['gold_carat'], $transaction['gold_weight_grams']) || !is_numeric($transaction['gold_weight_grams'])) {
                    if ($this->db->inTransaction()) $this->db->rollBack();
                     $this->logger->error("Missing or invalid data for weight inventory update.", ['transaction_id' => $transactionId, 'data' => $transaction]);
                     throw new Exception("اطلاعات عیار یا وزن معامله برای به‌روزرسانی موجودی نامعتبر یا ناقص است.");
                }

                // Calculate changes (Buy increases inventory, Sell decreases)
                 $weightChange = (float)(($transactionType == 'buy') ? +$transaction['gold_weight_grams'] : -$transaction['gold_weight_grams']);
                 // Value change might also be needed for average cost calculation (optional)
                 // $valueChange = (float)(($transactionType == 'buy') ? +$transaction['total_value_rials'] : -$transaction['total_value_rials']);
                 $carat = $transaction['gold_carat'];

                 $this->logger->debug("Updating weight inventory.", ['transaction_id' => $transactionId, 'carat' => $carat, 'weight_change' => $weightChange]);
                 // Assumes InventoryRepository::updateInventoryByCarat handles the DB update
                 $isWeightInventoryUpdated = $this->inventoryRepository->updateInventoryByCarat($carat, $weightChange, 0);

                 if (!$isWeightInventoryUpdated) {
                     // Repository should ideally throw an exception on failure
                      if ($this->db->inTransaction()) $this->db->rollBack();
                      $this->logger->critical("InventoryRepository::updateInventoryByCarat failed unexpectedly.", ['transaction_id' => $transactionId]);
                      throw new Exception("خطای بحرانی در به‌روزرسانی موجودی وزنی رخ داد.");
                 }
                 $inventoryUpdated = true;
                 $this->logger->debug("Weight inventory update successful.", ['transaction_id' => $transactionId]);

            } elseif ($isCoinBased) {
                 // Validate required fields for coin update
                 if (!isset($transaction['quantity']) || !is_numeric($transaction['quantity'])) {
                      if ($this->db->inTransaction()) $this->db->rollBack();
                      $this->logger->error("Missing or invalid data for coin inventory update.", ['transaction_id' => $transactionId, 'data' => $transaction]);
                      throw new Exception("اطلاعات تعداد معامله برای به‌روزرسانی موجودی سکه نامعتبر یا ناقص است.");
                 }
                 $quantityChange = (int)(($transactionType == 'buy') ? +$transaction['quantity'] : -$transaction['quantity']);
                 $coinType = $productType; // e.g., 'coin_azadi', 'coin_emami'

                 $this->logger->debug("Updating coin inventory.", ['transaction_id' => $transactionId, 'coin_type' => $coinType, 'quantity_change' => $quantityChange]);
                 // Assumes CoinInventoryRepository::updateCoinInventoryByType handles the DB update
                 $isCoinInventoryUpdated = $this->coinInventoryRepository->updateCoinInventoryByType($coinType, $quantityChange);

                 if (!$isCoinInventoryUpdated) {
                     // Repository should ideally throw an exception on failure
                      if ($this->db->inTransaction()) $this->db->rollBack();
                      $this->logger->critical("CoinInventoryRepository::updateCoinInventoryByType failed unexpectedly.", ['transaction_id' => $transactionId]);
                      throw new Exception("خطای بحرانی در به‌روزرسانی موجودی سکه رخ داد.");
                 }
                 $inventoryUpdated = true;
                 $this->logger->debug("Coin inventory update successful.", ['transaction_id' => $transactionId]);

            } else {
                 // Product type doesn't require inventory update (e.g., 'miscellaneous')
                 $this->logger->info("Transaction product type does not require inventory update.", ['transaction_id' => $transactionId, 'product_type' => $productType]);
                 $inventoryUpdated = true; // Mark as 'updated' (meaning no update was needed/failed) to allow commit
            }


            // 6. Commit Transaction
            if ($inventoryUpdated) { // Only commit if all necessary steps (including inventory) were successful or not needed
                if ($this->db->inTransaction()) {
                    $this->db->commit();
                    $this->logger->info("Delivery completion transaction committed successfully.", ['transaction_id' => $transactionId]);
                }
                return true; // Operation successful
            } else {
                 // This case should theoretically not be reached if exceptions are thrown on failure
                 if ($this->db->inTransaction()) $this->db->rollBack();
                 $this->logger->error("Inventory update failed or was skipped unexpectedly, rolling back.", ['transaction_id' => $transactionId]);
                 throw new Exception("خطا در به‌روزرسانی موجودی، عملیات لغو شد.");
            }

        } catch (Throwable $e) { // Catch PDOException or any other Exception/Error
            // Rollback transaction if active and we started it (or if any error occurred)
            if ($this->db->inTransaction()) {
                 $this->logger->warning("Rolling back transaction due to error.", ['transaction_id' => $transactionId, 'exception_message' => $e->getMessage()]);
                 $this->db->rollBack();
            }
            // Log the error details
            $this->logger->error("Error during delivery completion.", [
                'transaction_id' => $transactionId,
                'action' => $actionType,
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
                // Avoid logging full exception object if too verbose
            ]);
            // Rethrow the exception to be handled by the controller / global error handler
            // Keep the original exception type if possible (PDOException vs Exception)
            throw $e;
        }
    }

    // Other delivery/receipt related methods can be added here.

} // End DeliveryService class