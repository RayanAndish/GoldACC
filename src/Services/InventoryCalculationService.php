<?php

namespace App\Services;

use App\Repositories\InventoryCalculationRepository;
use App\Repositories\ProductRepository;
use App\Utils\Helper;
use Monolog\Logger;

class InventoryCalculationService
{
    private $calculationRepository;
    private $productRepository;
    private $logger;

    public function __construct(
        InventoryCalculationRepository $calculationRepository,
        ProductRepository $productRepository,
        Logger $logger
    ) {
        $this->calculationRepository = $calculationRepository;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * ذخیره محاسبات موجودی
     */
    public function saveCalculations(array $data): int
    {
        try {
            return $this->calculationRepository->create($data);
        } catch (\Throwable $e) {
            $this->logger->error("Error saving inventory calculations", ['exception' => $e, 'data' => $data]);
            throw $e;
        }
    }

    /**
     * محاسبه موجودی فعلی محصول
     */
    public function calculateCurrentInventory(int $productId): array
    {
        try {
            $product = $this->productRepository->findById($productId);
            $latestCalculation = $this->calculationRepository->getLatestCalculation($productId);
            
            return [
                'product_id' => $productId,
                'product_name' => $product->name,
                'quantity' => $latestCalculation->quantity_after ?? 0,
                'weight' => $latestCalculation->weight_after ?? 0,
                'average_price' => $latestCalculation->average_purchase_price ?? 0,
                'total_value' => $latestCalculation->total_value ?? 0,
                'target_capital' => $latestCalculation->target_capital ?? 0,
                'balance_percentage' => $latestCalculation->balance_percentage ?? 0,
                'balance_status' => $latestCalculation->balance_status ?? 'normal'
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Error calculating current inventory", ['exception' => $e, 'product_id' => $productId]);
            throw $e;
        }
    }

    /**
     * محاسبه تراز عملکرد
     */
    public function calculatePerformanceBalance(array $currentData, array $targetData): array
    {
        $currentQuantity = $currentData['quantity'] ?? 0;
        $currentWeight = $currentData['weight'] ?? 0;
        $currentValue = $currentData['total_value'] ?? 0;
        
        $targetQuantity = $targetData['quantity'] ?? 0;
        $targetWeight = $targetData['weight'] ?? 0;
        $targetValue = $targetData['target_capital'] ?? 0;

        // محاسبه درصد تراز برای هر معیار
        $quantityPercentage = $this->calculatePercentage($currentQuantity, $targetQuantity);
        $weightPercentage = $this->calculatePercentage($currentWeight, $targetWeight);
        $valuePercentage = $this->calculatePercentage($currentValue, $targetValue);

        return [
            'quantity_balance' => [
                'current' => $currentQuantity,
                'target' => $targetQuantity,
                'difference' => $currentQuantity - $targetQuantity,
                'percentage' => $quantityPercentage,
                'status' => $this->determineBalanceStatus($quantityPercentage)
            ],
            'weight_balance' => [
                'current' => $currentWeight,
                'target' => $targetWeight,
                'difference' => $currentWeight - $targetWeight,
                'percentage' => $weightPercentage,
                'status' => $this->determineBalanceStatus($weightPercentage)
            ],
            'value_balance' => [
                'current' => $currentValue,
                'target' => $targetValue,
                'difference' => $currentValue - $targetValue,
                'percentage' => $valuePercentage,
                'status' => $this->determineBalanceStatus($valuePercentage)
            ]
        ];
    }

    /**
     * محاسبه درصد
     */
    private function calculatePercentage(float $current, float $target): float
    {
        if ($target <= 0) {
            return 0;
        }
        return ($current / $target) * 100;
    }

    /**
     * تعیین وضعیت تراز
     */
    private function determineBalanceStatus(float $percentage): string
    {
        if ($percentage < 95) {
            return 'shortage';
        } elseif ($percentage > 105) {
            return 'excess';
        }
        return 'normal';
    }

    /**
     * به‌روزرسانی محاسبات موجودی
     */
    public function updateCalculations(int $productId): void
    {
        try {
            // دریافت آخرین محاسبات
            $latestCalculation = $this->calculationRepository->getLatestCalculation($productId);
            
            // محاسبه موجودی فعلی
            $currentInventory = $this->calculateCurrentInventory($productId);
            
            // ایجاد رکورد جدید محاسبات
            $calculationData = [
                'product_id' => $productId,
                'calculation_date' => date('Y-m-d'),
                'calculation_type' => 'daily_balance',
                'quantity_before' => $latestCalculation->quantity_after ?? 0,
                'weight_before' => $latestCalculation->weight_after ?? 0,
                'quantity_after' => $currentInventory['quantity'],
                'weight_after' => $currentInventory['weight'],
                'average_purchase_price' => $currentInventory['average_price'],
                'total_value' => $currentInventory['total_value'],
                'target_capital' => $currentInventory['target_capital'],
                'balance_percentage' => $currentInventory['balance_percentage'],
                'balance_status' => $currentInventory['balance_status']
            ];
            
            $this->saveCalculations($calculationData);
            
        } catch (\Throwable $e) {
            $this->logger->error("Error updating inventory calculations", ['exception' => $e, 'product_id' => $productId]);
            throw $e;
        }
    }
} 