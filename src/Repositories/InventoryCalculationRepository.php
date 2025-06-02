<?php

namespace App\Repositories;

use PDO;
use App\Models\InventoryCalculation;
use Monolog\Logger;

class InventoryCalculationRepository
{
    private $db;
    private $logger;

    public function __construct(PDO $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * ایجاد رکورد جدید محاسبات
     */
    public function create(array $data): int
    {
        try {
            $sql = "INSERT INTO inventory_calculations (
                product_id, calculation_date, calculation_type,
                quantity_before, weight_before, quantity_after, weight_after,
                average_purchase_price, total_value, target_capital,
                balance_percentage, balance_status
            ) VALUES (
                :product_id, :calculation_date, :calculation_type,
                :quantity_before, :weight_before, :quantity_after, :weight_after,
                :average_purchase_price, :total_value, :target_capital,
                :balance_percentage, :balance_status
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'product_id' => $data['product_id'],
                'calculation_date' => $data['calculation_date'],
                'calculation_type' => $data['calculation_type'],
                'quantity_before' => $data['quantity_before'],
                'weight_before' => $data['weight_before'],
                'quantity_after' => $data['quantity_after'],
                'weight_after' => $data['weight_after'],
                'average_purchase_price' => $data['average_purchase_price'],
                'total_value' => $data['total_value'],
                'target_capital' => $data['target_capital'],
                'balance_percentage' => $data['balance_percentage'],
                'balance_status' => $data['balance_status']
            ]);

            return $this->db->lastInsertId();
        } catch (\PDOException $e) {
            $this->logger->error("Error creating inventory calculation", ['exception' => $e, 'data' => $data]);
            throw $e;
        }
    }

    /**
     * دریافت آخرین محاسبات یک محصول
     */
    public function getLatestCalculation(int $productId): ?InventoryCalculation
    {
        try {
            $sql = "SELECT * FROM inventory_calculations 
                    WHERE product_id = :product_id 
                    ORDER BY calculation_date DESC, id DESC 
                    LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['product_id' => $productId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? new InventoryCalculation($result) : null;
        } catch (\PDOException $e) {
            $this->logger->error("Error getting latest calculation", ['exception' => $e, 'product_id' => $productId]);
            throw $e;
        }
    }

    /**
     * دریافت محاسبات یک محصول در یک بازه زمانی
     */
    public function getCalculationsByDateRange(int $productId, string $startDate, string $endDate): array
    {
        try {
            $sql = "SELECT * FROM inventory_calculations 
                    WHERE product_id = :product_id 
                    AND calculation_date BETWEEN :start_date AND :end_date 
                    ORDER BY calculation_date ASC, id ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'product_id' => $productId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($row) {
                return new InventoryCalculation($row);
            }, $results);
        } catch (\PDOException $e) {
            $this->logger->error("Error getting calculations by date range", [
                'exception' => $e,
                'product_id' => $productId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            throw $e;
        }
    }

    /**
     * دریافت خلاصه محاسبات برای همه محصولات
     */
    public function getLatestCalculationsForAllProducts(): array
    {
        try {
            $sql = "WITH RankedCalculations AS (
                        SELECT *,
                            ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY calculation_date DESC, id DESC) as rn
                        FROM inventory_calculations
                    )
                    SELECT * FROM RankedCalculations WHERE rn = 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($row) {
                return new InventoryCalculation($row);
            }, $results);
        } catch (\PDOException $e) {
            $this->logger->error("Error getting latest calculations for all products", ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * به‌روزرسانی محاسبات
     */
    public function update(int $id, array $data): bool
    {
        try {
            $sql = "UPDATE inventory_calculations SET 
                    quantity_after = :quantity_after,
                    weight_after = :weight_after,
                    average_purchase_price = :average_purchase_price,
                    total_value = :total_value,
                    target_capital = :target_capital,
                    balance_percentage = :balance_percentage,
                    balance_status = :balance_status,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'id' => $id,
                'quantity_after' => $data['quantity_after'],
                'weight_after' => $data['weight_after'],
                'average_purchase_price' => $data['average_purchase_price'],
                'total_value' => $data['total_value'],
                'target_capital' => $data['target_capital'],
                'balance_percentage' => $data['balance_percentage'],
                'balance_status' => $data['balance_status']
            ]);
        } catch (\PDOException $e) {
            $this->logger->error("Error updating inventory calculation", ['exception' => $e, 'id' => $id, 'data' => $data]);
            throw $e;
        }
    }

    /**
     * حذف محاسبات
     */
    public function delete(int $id): bool
    {
        try {
            $sql = "DELETE FROM inventory_calculations WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute(['id' => $id]);
        } catch (\PDOException $e) {
            $this->logger->error("Error deleting inventory calculation", ['exception' => $e, 'id' => $id]);
            throw $e;
        }
    }
} 