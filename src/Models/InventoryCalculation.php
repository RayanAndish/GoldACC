<?php

namespace App\Models;

class InventoryCalculation
{
    public ?int $id = null;
    public ?int $product_id = null;
    public ?string $calculation_date = null;
    public ?string $calculation_type = null;
    public ?int $quantity_before = null;
    public ?float $weight_before = null;
    public ?int $quantity_after = null;
    public ?float $weight_after = null;
    public ?float $average_purchase_price = null;
    public ?float $total_value = null;
    public ?float $target_capital = null;
    public ?float $balance_percentage = null;
    public ?string $balance_status = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    // Optional: To hold related objects if joined
    public ?Product $product = null;

    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->id = isset($data['id']) ? (int)$data['id'] : null;
            $this->product_id = isset($data['product_id']) ? (int)$data['product_id'] : null;
            $this->calculation_date = $data['calculation_date'] ?? null;
            $this->calculation_type = $data['calculation_type'] ?? null;
            $this->quantity_before = isset($data['quantity_before']) ? (int)$data['quantity_before'] : null;
            $this->weight_before = isset($data['weight_before']) ? (float)$data['weight_before'] : null;
            $this->quantity_after = isset($data['quantity_after']) ? (int)$data['quantity_after'] : null;
            $this->weight_after = isset($data['weight_after']) ? (float)$data['weight_after'] : null;
            $this->average_purchase_price = isset($data['average_purchase_price']) ? (float)$data['average_purchase_price'] : null;
            $this->total_value = isset($data['total_value']) ? (float)$data['total_value'] : null;
            $this->target_capital = isset($data['target_capital']) ? (float)$data['target_capital'] : null;
            $this->balance_percentage = isset($data['balance_percentage']) ? (float)$data['balance_percentage'] : null;
            $this->balance_status = $data['balance_status'] ?? null;
            $this->created_at = $data['created_at'] ?? null;
            $this->updated_at = $data['updated_at'] ?? null;

            // Example of hydrating related objects if their data is passed
            if ($this->product_id !== null && isset($data['product_name'])) {
                $this->product = new Product($data);
            }
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'calculation_date' => $this->calculation_date,
            'calculation_type' => $this->calculation_type,
            'quantity_before' => $this->quantity_before,
            'weight_before' => $this->weight_before,
            'quantity_after' => $this->quantity_after,
            'weight_after' => $this->weight_after,
            'average_purchase_price' => $this->average_purchase_price,
            'total_value' => $this->total_value,
            'target_capital' => $this->target_capital,
            'balance_percentage' => $this->balance_percentage,
            'balance_status' => $this->balance_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'product' => $this->product ? $this->product->toArray() : null
        ];
    }

    /**
     * محاسبه درصد تراز
     */
    public function calculateBalancePercentage(): float
    {
        if ($this->target_capital <= 0) {
            return 0;
        }
        return ($this->total_value / $this->target_capital) * 100;
    }

    /**
     * تعیین وضعیت تراز
     */
    public function determineBalanceStatus(): string
    {
        $percentage = $this->calculateBalancePercentage();
        
        if ($percentage < 95) {
            return 'shortage';
        } elseif ($percentage > 105) {
            return 'excess';
        }
        return 'normal';
    }

    /**
     * محاسبه تغییرات
     */
    public function calculateChanges(): array
    {
        return [
            'quantity_change' => $this->quantity_after - $this->quantity_before,
            'weight_change' => $this->weight_after - $this->weight_before
        ];
    }
} 