<?php

namespace App\Repositories;

use PDO;
use PDOException;
use Monolog\Logger;
use App\Models\ProductCategory;
use Exception;

class ProductCategoryRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Finds a product category by its ID.
     *
     * @param int $id
     * @return ProductCategory|null
     */
    public function findById(int $id): ?ProductCategory {
        try {
            $stmt = $this->db->prepare("SELECT * FROM product_categories WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            return $data ? new ProductCategory($data) : null;
        } catch (PDOException $e) {
            $this->logger->error("Error fetching product category by ID: {$id}", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Finds a product category by its code.
     *
     * @param string $code
     * @return ProductCategory|null
     */
    public function findByCode(string $code): ?ProductCategory {
        try {
            $stmt = $this->db->prepare("SELECT * FROM product_categories WHERE code = :code");
            $stmt->bindParam(':code', $code, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            return $data ? new ProductCategory($data) : null;
        } catch (PDOException $e) {
            $this->logger->error("Error fetching product category by code: {$code}", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Retrieves all product categories.
     *
     * @return ProductCategory[]
     */
    public function findAll(): array {
        $categories = [];
        try {
            $stmt = $this->db->query("SELECT * FROM product_categories ORDER BY name ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $categories[] = new ProductCategory($row);
            }
        } catch (PDOException $e) {
            $this->logger->error("Error fetching all product categories", ['exception' => $e->getMessage()]);
        }
        return $categories;
    }

    /**
     * Checks if a category code already exists in the database,
     * optionally excluding a specific category ID (for updates).
     *
     * @param string $code The category code to check.
     * @param int|null $excludeId The ID of the category to exclude from the check (null for new categories).
     * @return bool True if the code exists, false otherwise.
     * @throws Exception if a database error occurs.
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        if (empty(trim($code))) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM product_categories WHERE code = :code";
        $params = [':code' => $code];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $count = (int)$stmt->fetchColumn();
            return $count > 0;
        } catch (\PDOException $e) {
            $this->logger->error("Database error checking if category code exists.", ['code' => $code, 'exclude_id' => $excludeId, 'error' => $e->getMessage()]);
            throw new Exception("خطا در بررسی وجود کد دسته‌بندی: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Saves a product category (creates if new, updates if exists).
     *
     * @param ProductCategory $category
     * @return int|false The ID of the saved category or false on failure.
     */
    public function save(ProductCategory $category): int|false {
        $isNew = empty($category->id);
    
        $sql = $isNew
            ? "INSERT INTO product_categories (name, code, base_category, description, unit_of_measure, is_active, created_at, updated_at) VALUES (:name, :code, :base_category, :description, :unit_of_measure, :is_active, NOW(), NOW())"
            : "UPDATE product_categories SET name = :name, code = :code, base_category = :base_category, description = :description, unit_of_measure = :unit_of_measure, is_active = :is_active, updated_at = NOW() WHERE id = :id";
    
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':name', $category->name, PDO::PARAM_STR);
            $stmt->bindValue(':code', $category->code, PDO::PARAM_STR);
            $stmt->bindValue(':base_category', $category->base_category, PDO::PARAM_STR);
            $stmt->bindValue(':description', $category->description, PDO::PARAM_STR);
            $stmt->bindValue(':unit_of_measure', $category->unit_of_measure, PDO::PARAM_STR);
            $stmt->bindValue(':is_active', $category->is_active ? 1 : 0, PDO::PARAM_INT);
    
            if (!$isNew) {
                $stmt->bindValue(':id', $category->id, PDO::PARAM_INT);
            }
    
            $success = $stmt->execute();
    
            if ($success) {
                if ($isNew) {
                    $category->id = (int)$this->db->lastInsertId();
                    $this->logger->info("Product category created.", ['id' => $category->id, 'name' => $category->name]);
                } else {
                    $this->logger->info("Product category updated.", ['id' => $category->id, 'name' => $category->name]);
                }
                return $category->id;
            }
            $this->logger->error("Failed to save product category.", ['name' => $category->name, 'errorInfo' => $stmt->errorInfo()]);
            return false;
        } catch (PDOException $e) {
            $this->logger->error("Database error saving product category: {$category->name}", ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Deletes a product category by its ID.
     *
     * @param int $id
     * @return bool True on success, false on failure.
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM product_categories WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $success = $stmt->execute();
            if ($success && $stmt->rowCount() > 0) {
                 $this->logger->info("Product category deleted.", ['id' => $id]);
                return true;
            } elseif ($success) {
                $this->logger->warning("Attempted to delete product category, but no record found or no rows affected.", ['id' => $id]);
                return false;
            }
            $this->logger->error("Failed to delete product category.", ['id' => $id, 'errorInfo' => $stmt->errorInfo()]);
            return false;
        } catch (PDOException $e) {
            $this->logger->error("Database error deleting product category: {$id}", ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Fetches all active product categories.
     *
     * @return ProductCategory[] An array of ProductCategory objects.
     * @throws Exception if a database error occurs.
     */
    public function findAllActives(): array
    {
        $sql = "SELECT * FROM product_categories WHERE is_active = 1 ORDER BY name ASC";
        try {
            $stmt = $this->db->query($sql);
            $categoriesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $categories = [];
            foreach ($categoriesData as $data) {
                $categories[] = new ProductCategory($data);
            }
            return $categories;
        } catch (\PDOException $e) {
            $this->logger->error("Database error fetching all active product categories.", ['error' => $e->getMessage()]);
            throw new Exception("خطا در واکشی دسته‌بندی‌های فعال محصولات: " . $e->getMessage(), 0, $e);
        }
    }

}