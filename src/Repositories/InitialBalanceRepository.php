<?php
/**
 * Repository: src/Repositories/InitialBalanceRepository.php
 * Handles initial balance database operations
 */

namespace App\Repositories;

use PDO;
use Throwable;

class InitialBalanceRepository
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * دریافت تمام موجودی‌های اولیه
     */
    public function getAllInitialBalances(): array
    {
        try {
            $sql = "SELECT ib.*, p.name as product_name, 
                    c.name as category_name, c.base_category as product_group
                    FROM product_initial_balances ib
                    JOIN products p ON ib.product_id = p.id
                    JOIN product_categories c ON p.category_id = c.id
                    ORDER BY ib.balance_date DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            throw new \Exception("Error fetching initial balances: " . $e->getMessage());
        }
    }

    /**
     * دریافت موجودی اولیه با شناسه
     */
    public function getInitialBalanceById(int $id): ?array
    {
        try {
            $sql = "SELECT ib.*, p.name as product_name, 
                    c.name as category_name, c.base_category as product_group
                    FROM product_initial_balances ib
                    JOIN products p ON ib.product_id = p.id
                    JOIN product_categories c ON p.category_id = c.id
                    WHERE ib.id = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Throwable $e) {
            throw new \Exception("Error fetching initial balance: " . $e->getMessage());
        }
    }

    /**
     * دریافت موجودی اولیه با شناسه محصول
     */
    public function getInitialBalanceByProductId(int $productId): ?array
    {
        try {
            $sql = "SELECT ib.*, p.name as product_name, 
                    c.name as category_name, c.base_category as product_group
                    FROM product_initial_balances ib
                    JOIN products p ON ib.product_id = p.id
                    JOIN product_categories c ON p.category_id = c.id
                    WHERE ib.product_id = :product_id
                    ORDER BY ib.balance_date DESC
                    LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Throwable $e) {
            throw new \Exception("Error fetching initial balance: " . $e->getMessage());
        }
    }

    /**
     * ایجاد موجودی اولیه جدید
     */
    public function createInitialBalance(array $data): int
    {
        try {
            $sql = "INSERT INTO product_initial_balances (
                        product_id, balance_date, quantity, weight_grams, carat,
                        average_purchase_price_per_unit, total_purchase_value, notes,
                        created_by_user_id, created_at, updated_at
                    ) VALUES (
                        :product_id, :balance_date, :quantity, :weight_grams, :carat,
                        :average_purchase_price_per_unit, :total_purchase_value, :notes,
                        :created_by_user_id, NOW(), NOW()
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':product_id', $data['product_id'], PDO::PARAM_INT);
            $stmt->bindValue(':balance_date', $data['balance_date']);
            $stmt->bindValue(':quantity', $data['quantity'] ?? null);
            $stmt->bindValue(':weight_grams', $data['weight_grams'] ?? null);
            $stmt->bindValue(':carat', $data['carat'] ?? null);
            $stmt->bindValue(':average_purchase_price_per_unit', $data['average_purchase_price_per_unit']);
            $stmt->bindValue(':total_purchase_value', $data['total_purchase_value']);
            $stmt->bindValue(':notes', $data['notes'] ?? null);
            $stmt->bindValue(':created_by_user_id', $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
            
            $stmt->execute();
            
            return (int) $this->db->lastInsertId();
        } catch (Throwable $e) {
            throw new \Exception("Error creating initial balance: " . $e->getMessage());
        }
    }

    /**
     * بروزرسانی موجودی اولیه
     */
    public function updateInitialBalance(int $id, array $data): void
    {
        try {
            $sql = "UPDATE product_initial_balances SET
                        product_id = :product_id,
                        balance_date = :balance_date,
                        quantity = :quantity,
                        weight_grams = :weight_grams,
                        carat = :carat,
                        average_purchase_price_per_unit = :average_purchase_price_per_unit,
                        total_purchase_value = :total_purchase_value,
                        notes = :notes,
                        updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':product_id', $data['product_id'], PDO::PARAM_INT);
            $stmt->bindValue(':balance_date', $data['balance_date']);
            $stmt->bindValue(':quantity', $data['quantity'] ?? null);
            $stmt->bindValue(':weight_grams', $data['weight_grams'] ?? null);
            $stmt->bindValue(':carat', $data['carat'] ?? null);
            $stmt->bindValue(':average_purchase_price_per_unit', $data['average_purchase_price_per_unit']);
            $stmt->bindValue(':total_purchase_value', $data['total_purchase_value']);
            $stmt->bindValue(':notes', $data['notes'] ?? null);
            
            $stmt->execute();
        } catch (Throwable $e) {
            throw new \Exception("Error updating initial balance: " . $e->getMessage());
        }
    }

    /**
     * حذف موجودی اولیه
     */
    public function deleteInitialBalance(int $id): void
    {
        try {
            $sql = "DELETE FROM product_initial_balances WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            throw new \Exception("Error deleting initial balance: " . $e->getMessage());
        }
    }
} 