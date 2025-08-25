<?php
/**
 * Service: src/Services/InitialBalanceService.php
 * Handles initial balance business logic
 */

namespace App\Services;

use App\Repositories\InitialBalanceRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\TransactionRepository;
use App\Utils\Helper;
use Monolog\Logger;
use Throwable;

class InitialBalanceService
{
    private $initialBalanceRepository;
    private $inventoryRepository;
    private $transactionRepository;
    private $logger;

    public function __construct(
        InitialBalanceRepository $initialBalanceRepository,
        InventoryRepository $inventoryRepository,
        TransactionRepository $transactionRepository,
        Logger $logger
    ) {
        $this->initialBalanceRepository = $initialBalanceRepository;
        $this->inventoryRepository = $inventoryRepository;
        $this->transactionRepository = $transactionRepository;
        $this->logger = $logger;
    }

    /**
     * ایجاد موجودی اولیه جدید
     */
    public function createInitialBalance(array $data): int
    {
        try {
            // دریافت اطلاعات محصول
            $product = $this->inventoryRepository->getProductDetails($data['product_id']);
            if (!$product) {
                throw new \Exception("محصول مورد نظر یافت نشد.");
            }

            // محاسبه قیمت واحد برای محصولات غیر سکه و جواهر
            if ($product['type'] !== 'coin' && $product['type'] !== 'jewelry' && !empty($data['market_price'])) {
                $data['average_purchase_price_per_unit'] = round($data['market_price'] / 4.3318);
            }

            // محاسبه ارزش کل بر اساس نوع محصول
            if (!isset($data['total_purchase_value']) || !$data['total_purchase_value']) {
                if ($product['type'] === 'coin' && isset($data['quantity'])) {
                    $data['total_purchase_value'] = $data['quantity'] * $data['average_purchase_price_per_unit'];
                } else if (isset($data['weight_750'])) {
                    $data['total_purchase_value'] = $data['weight_750'] * $data['average_purchase_price_per_unit'];
                }
            }

            // ذخیره موجودی اولیه
            $initialBalanceId = $this->initialBalanceRepository->createInitialBalance($data);

            // CRITICAL FIX: Record this initial balance in the inventory ledger
            $this->inventoryRepository->addInitialBalanceToLedger($data, $initialBalanceId);

            // ثبت در لاگ
            $this->logger->info("Initial balance created and recorded in ledger", [
                'initial_balance_id' => $initialBalanceId,
                'product_id' => $data['product_id'],
                'product_type' => $product['type'],
                'weight_750' => $data['weight_750'] ?? null,
                'quantity' => $data['quantity'] ?? null
            ]);

            return $initialBalanceId;

        } catch (Throwable $e) {
            $this->logger->error("Error creating initial balance", [
                'exception' => $e,
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * بروزرسانی موجودی اولیه
     */
    public function updateInitialBalance(int $id, array $data): void
    {
        try {
            // محاسبه ارزش کل اگر وارد نشده باشد
            if (!isset($data['total_purchase_value']) || !$data['total_purchase_value']) {
                if ($data['quantity']) {
                    $data['total_purchase_value'] = $data['quantity'] * $data['average_purchase_price_per_unit'];
                } else if ($data['weight_grams']) {
                    $data['total_purchase_value'] = $data['weight_grams'] * $data['average_purchase_price_per_unit'];
                }
            }

            // بروزرسانی موجودی اولیه
            $this->initialBalanceRepository->updateInitialBalance($id, $data);

            // ثبت در لاگ
            $this->logger->info("Initial balance updated", [
                'initial_balance_id' => $id,
                'product_id' => $data['product_id']
            ]);

        } catch (Throwable $e) {
            $this->logger->error("Error updating initial balance", [
                'exception' => $e,
                'id' => $id,
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * حذف موجودی اولیه
     */
    public function deleteInitialBalance(int $id): void
    {
        try {
            // حذف موجودی اولیه
            $this->initialBalanceRepository->deleteInitialBalance($id);

            // ثبت در لاگ
            $this->logger->info("Initial balance deleted", [
                'initial_balance_id' => $id
            ]);

        } catch (Throwable $e) {
            $this->logger->error("Error deleting initial balance", [
                'exception' => $e,
                'id' => $id
            ]);
            throw $e;
        }
    }

    /**
     * ثبت در کاردکس
     */
    public function recordInInventoryLedger(int $initialBalanceId): void
    {
        try {
            // دریافت اطلاعات موجودی اولیه
            $initialBalance = $this->initialBalanceRepository->getInitialBalanceById($initialBalanceId);
            if (!$initialBalance) {
                throw new \Exception("موجودی اولیه مورد نظر یافت نشد.");
            }

            // ثبت در کاردکس
            $this->inventoryRepository->recordInitialBalance($initialBalance);

            // ثبت در لاگ
            $this->logger->info("Initial balance recorded in inventory ledger", [
                'initial_balance_id' => $initialBalanceId,
                'product_id' => $initialBalance['product_id']
            ]);

        } catch (Throwable $e) {
            $this->logger->error("Error recording initial balance in inventory ledger", [
                'exception' => $e,
                'initial_balance_id' => $initialBalanceId
            ]);
            throw $e;
        }
    }

    /**
     * بروزرسانی کاردکس
     */
    public function updateInventoryLedger(int $initialBalanceId): void
    {
        try {
            // دریافت اطلاعات موجودی اولیه
            $initialBalance = $this->initialBalanceRepository->getInitialBalanceById($initialBalanceId);
            if (!$initialBalance) {
                throw new \Exception("موجودی اولیه مورد نظر یافت نشد.");
            }

            // بروزرسانی کاردکس
            $this->inventoryRepository->updateInitialBalance($initialBalance);

            // ثبت در لاگ
            $this->logger->info("Initial balance updated in inventory ledger", [
                'initial_balance_id' => $initialBalanceId,
                'product_id' => $initialBalance['product_id']
            ]);

        } catch (Throwable $e) {
            $this->logger->error("Error updating initial balance in inventory ledger", [
                'exception' => $e,
                'initial_balance_id' => $initialBalanceId
            ]);
            throw $e;
        }
    }

    /**
     * محاسبه تراز عملکرد
     */
    public function calculatePerformanceBalance(int $productId): array
    {
        try {
            // دریافت موجودی اولیه
            $initialBalance = $this->initialBalanceRepository->getInitialBalanceByProductId($productId);
            if (!$initialBalance) {
                return [
                    'performance_balance' => 0,
                    'performance_percentage' => 0
                ];
            }

            // محاسبه تراز عملکرد
            $performance = $this->inventoryRepository->calculatePerformanceBalance($productId);

            return [
                'performance_balance' => $performance['performance_balance'],
                'performance_percentage' => $performance['performance_percentage']
            ];

        } catch (Throwable $e) {
            $this->logger->error("Error calculating performance balance", [
                'exception' => $e,
                'product_id' => $productId
            ]);
            throw $e;
        }
    }

    /**
     * ذخیره محاسبات موجودی
     */
    public function saveCalculations(array $data): void
    {
        try {
            // دریافت اطلاعات محصول
            $product = $this->inventoryRepository->getProductDetails($data['product_id']);
            if (!$product) {
                throw new \Exception("محصول مورد نظر یافت نشد.");
            }

            // محاسبه درصد تراز
            $balancePercentage = 0;
            if ($data['target_capital'] > 0) {
                $balancePercentage = ($data['total_value'] / $data['target_capital']) * 100;
            }

            // تعیین وضعیت تراز
            $balanceStatus = 'normal';
            if ($balancePercentage < 95) {
                $balanceStatus = 'shortage';
            } else if ($balancePercentage > 105) {
                $balanceStatus = 'excess';
            }

            // ذخیره محاسبات
            $calculationData = [
                'product_id' => $data['product_id'],
                'calculation_date' => $data['calculation_date'],
                'calculation_type' => $data['calculation_type'],
                'quantity_before' => $data['quantity_before'] ?? 0,
                'weight_before' => $data['weight_before'] ?? 0,
                'quantity_after' => $data['quantity_after'] ?? 0,
                'weight_after' => $data['weight_after'] ?? 0,
                'average_purchase_price' => $data['average_purchase_price'],
                'total_value' => $data['total_value'],
                'target_capital' => $data['target_capital'],
                'balance_percentage' => $balancePercentage,
                'balance_status' => $balanceStatus
            ];

            $this->inventoryRepository->saveInventoryCalculation($calculationData);

            // ثبت در لاگ
            $this->logger->info("Inventory calculations saved", [
                'product_id' => $data['product_id'],
                'calculation_type' => $data['calculation_type'],
                'balance_status' => $balanceStatus
            ]);

        } catch (Throwable $e) {
            $this->logger->error("Error saving inventory calculations", [
                'exception' => $e,
                'data' => $data
            ]);
            throw $e;
        }
    }
} 