<?php

namespace App\Repositories;

use PDO;
use PDOException;
use Monolog\Logger;
use App\Models\Product;
use App\Models\ProductCategory; // Needed for type hinting and potentially joining

class ProductRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Finds a product by its ID.
     * Optionally joins with product_categories table.
     *
     * @param int $id
     * @param bool $withCategory
     * @return Product|null
     */
    public function findById(int $id, bool $withCategory = false): ?Product {
        $sql = "SELECT p.*";
        if ($withCategory) {
            $sql .= ", pc.name as category_name, pc.code as category_code, pc.base_category as category_base_category"; // Add other category fields as needed
        }
        $sql .= " FROM products p";
        if ($withCategory) {
            $sql .= " LEFT JOIN product_categories pc ON p.category_id = pc.id";
        }
        $sql .= " WHERE p.id = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $product = new Product($data);
                if ($withCategory && $product->category_id !== null) {
                    if (isset($data['category_name']) || isset($data['category_code']) || isset($data['category_base_category'])) {
                         $product->category = new ProductCategory([
                            'id' => $product->category_id,
                            'name' => $data['category_name'] ?? null,
                            'code' => $data['category_code'] ?? null,
                            'base_category' => $data['category_base_category'] ?? null,
                            // Populate other category fields if fetched
                        ]);
                    }
                }
                return $product;
            }
            return null;
        } catch (PDOException $e) {
            $this->logger->error("Error fetching product by ID: {$id}", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Finds a product by its code.
     *
     * @param string $productCode
     * @param bool $withCategory
     * @return Product|null
     */
    public function findByCode(string $productCode, bool $withCategory = false): ?Product {
        $sql = "SELECT p.*";
        if ($withCategory) {
            $sql .= ", pc.name as category_name, pc.code as category_code, pc.base_category as category_base_category";
        }
        $sql .= " FROM products p";
        if ($withCategory) {
            $sql .= " LEFT JOIN product_categories pc ON p.category_id = pc.id";
        }
        $sql .= " WHERE p.product_code = :product_code";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':product_code', $productCode, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) {
                $product = new Product($data);
                if ($withCategory && $product->category_id !== null) {
                     if (isset($data['category_name']) || isset($data['category_code']) || isset($data['category_base_category'])) {
                        $product->category = new ProductCategory([
                           'id' => $product->category_id,
                           'name' => $data['category_name'] ?? null,
                           'code' => $data['category_code'] ?? null,
                           'base_category' => $data['category_base_category'] ?? null,
                       ]);
                    }
                }
                return $product;
            }
            return null;
        } catch (PDOException $e) {
            $this->logger->error("Error fetching product by code: {$productCode}", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Retrieves all products.
     * Can filter by category, active status, and include category details.
     *
     * @param array $filters (e.g., ['category_id' => 1, 'is_active' => true])
     * @param bool $withCategory
     * @return Product[]
     */
    public function findAll(array $filters = [], bool $withCategory = false): array {
        $products = [];
        $sql = "SELECT p.*";
        if ($withCategory) {
            $sql .= ", pc.name as category_name, pc.code as category_code, pc.base_category as category_base_category";
        }
        $sql .= " FROM products p";
        if ($withCategory) {
            $sql .= " LEFT JOIN product_categories pc ON p.category_id = pc.id";
        }

        $whereClauses = [];
        $params = [];

        if (isset($filters['category_id'])) {
            $whereClauses[] = "p.category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }
        if (isset($filters['is_active'])) {
            $whereClauses[] = "p.is_active = :is_active";
            $params[':is_active'] = (bool)$filters['is_active'];
        }
        // Add other filters as needed

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $sql .= " ORDER BY p.name ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $product = new Product($row);
                if ($withCategory && $product->category_id !== null) {
                    if (isset($row['category_name']) || isset($row['category_code']) || isset($row['category_base_category'])) {
                         $product->category = new ProductCategory([
                            'id' => $product->category_id,
                            'name' => $row['category_name'] ?? null,
                            'code' => $row['category_code'] ?? null,
                            'base_category' => $row['category_base_category'] ?? null,
                        ]);
                    }
                }
                $products[] = $product;
            }
        } catch (PDOException $e) {
            $this->logger->error("Error fetching all products", ['filters' => $filters, 'exception' => $e->getMessage()]);
        }
        return $products;
    }

    /**
     * Retrieves all active products with their category information.
     * This is a specialized version of findAll with 'is_active' => true and withCategory => true
     *
     * @return Product[] Array of active products with their category information.
     */
    public function getAllActiveWithCategory(): array {
        return $this->findAll(['is_active' => true], true);
    }

    /**
     * Saves a product (creates if new, updates if exists).
     *
     * @param Product $product
     * @return int|false The ID of the saved product or false on failure.
     */
    public function save(Product $product): int|false {
        $isNew = $product->id === null;

        // Ensure category_id is valid before saving
        if ($isNew || $this->isCategoryModified($product)) { // Check if category_id exists or is being changed
            $categoryRepo = new ProductCategoryRepository($this->db, $this->logger); // Consider injecting this
            if (!$categoryRepo->findById($product->category_id)) {
                $this->logger->error("Invalid category_id: {$product->category_id} for product: {$product->name}");
                return false;
            }
        }

        $sql = $isNew
            ? "INSERT INTO products (name, category_id, product_code, description, default_carat, is_active, capital_quantity, capital_weight_grams, capital_reference_carat, quantity, weight, coin_year, unit_of_measure, tax_enabled, tax_rate, vat_enabled, vat_rate, created_at, updated_at) VALUES (:name, :category_id, :product_code, :description, :default_carat, :is_active, :capital_quantity, :capital_weight_grams, :capital_reference_carat, :quantity, :weight, :coin_year, :unit_of_measure, :tax_enabled, :tax_rate, :vat_enabled, :vat_rate, NOW(), NOW())"
            : "UPDATE products SET name = :name, category_id = :category_id, product_code = :product_code, description = :description, default_carat = :default_carat, is_active = :is_active, capital_quantity = :capital_quantity, capital_weight_grams = :capital_weight_grams, capital_reference_carat = :capital_reference_carat, quantity = :quantity, weight = :weight, coin_year = :coin_year, unit_of_measure = :unit_of_measure, tax_enabled = :tax_enabled, tax_rate = :tax_rate, vat_enabled = :vat_enabled, vat_rate = :vat_rate, updated_at = NOW() WHERE id = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':name', $product->name, PDO::PARAM_STR);
            $stmt->bindValue(':category_id', $product->category_id, PDO::PARAM_INT);
            $stmt->bindValue(':product_code', $product->product_code, PDO::PARAM_STR);
            $stmt->bindValue(':description', $product->description, PDO::PARAM_STR);
            $stmt->bindValue(':default_carat', $product->default_carat, PDO::PARAM_STR); // PDO::PARAM_STR for float/decimal can be safer
            $stmt->bindValue(':is_active', $product->is_active, PDO::PARAM_BOOL);
            $stmt->bindValue(':capital_quantity', $product->capital_quantity, PDO::PARAM_STR); // Use PARAM_STR for nullables or specific types
            $stmt->bindValue(':capital_weight_grams', $product->capital_weight_grams, PDO::PARAM_STR);
            $stmt->bindValue(':capital_reference_carat', $product->capital_reference_carat, PDO::PARAM_INT);
            $stmt->bindValue(':quantity', $product->quantity, PDO::PARAM_STR); // Assuming float, using STR for safety with null
            $stmt->bindValue(':weight', $product->weight, PDO::PARAM_STR); // Assuming float, using STR for safety with null
            $stmt->bindValue(':coin_year', $product->coin_year, PDO::PARAM_INT);
            $stmt->bindValue(':unit_of_measure', $product->unit_of_measure, PDO::PARAM_STR);
            $stmt->bindValue(':tax_enabled', $product->tax_enabled, PDO::PARAM_BOOL);
            $stmt->bindValue(':tax_rate', $product->tax_rate, PDO::PARAM_STR);
            $stmt->bindValue(':vat_enabled', $product->vat_enabled, PDO::PARAM_BOOL);
            $stmt->bindValue(':vat_rate', $product->vat_rate, PDO::PARAM_STR);

            if (!$isNew) {
                $stmt->bindValue(':id', $product->id, PDO::PARAM_INT);
            }

            $success = $stmt->execute();

            if ($success) {
                if ($isNew) {
                    $product->id = (int)$this->db->lastInsertId();
                    $this->logger->info("Product created.", ['id' => $product->id, 'name' => $product->name]);
                } else {
                    $this->logger->info("Product updated.", ['id' => $product->id, 'name' => $product->name]);
                }
                return $product->id;
            }
            $this->logger->error("Failed to save product.", ['name' => $product->name, 'errorInfo' => $stmt->errorInfo()]);
            return false;
        } catch (PDOException $e) {
            $this->logger->error("Database error saving product: {$product->name}", ['exception' => $e->getMessage()]);
            // Check for foreign key constraint violation for category_id
            if (str_contains($e->getMessage(), 'FOREIGN KEY (`category_id`)')) {
                 $this->logger->error("Foreign key constraint violation for category_id: {$product->category_id}");
            }
            return false;
        }
    }

    /**
     * Helper to check if category_id is being modified (for updates).
     * This requires fetching the existing record, so use judiciously or pass original state.
     */
    private function isCategoryModified(Product $product): bool {
        if ($product->id === null) return true; // It's a new product

        $stmt = $this->db->prepare("SELECT category_id FROM products WHERE id = :id");
        $stmt->bindParam(':id', $product->id, PDO::PARAM_INT);
        $stmt->execute();
        $currentCategoryId = $stmt->fetchColumn();
        return $currentCategoryId !== $product->category_id;
    }

    /**
     * Deletes a product by its ID.
     *
     * @param int $id
     * @return bool True on success, false on failure.
     */
    public function delete(int $id): bool {
        try {
            // Consider checking for related records in inventory_ledger or product_initial_balances
            // before deleting to maintain data integrity, or rely on DB foreign key constraints (ON DELETE RESTRICT).
            $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $success = $stmt->execute();

            if ($success && $stmt->rowCount() > 0) {
                $this->logger->info("Product deleted.", ['id' => $id]);
                return true;
            } elseif ($success) {
                $this->logger->warning("Attempted to delete product, but no record found.", ['id' => $id]);
                return false; // Or true if "not found" is acceptable
            }
            $this->logger->error("Failed to delete product.", ['id' => $id, 'errorInfo' => $stmt->errorInfo()]);
            return false;
        } catch (PDOException $e) {
            $this->logger->error("Database error deleting product: {$id}", ['exception' => $e->getMessage()]);
            // Check for foreign key constraint violations if products are referenced elsewhere
             if (str_contains($e->getMessage(), 'FOREIGN KEY constraint failed')) {
                 $this->logger->error("Cannot delete product due to existing references (e.g., in inventory or transactions).", ['id' => $id]);
            }
            return false;
        }
    }

    /**
     * Checks if a product code already exists.
     * Useful for validation before saving.
     *
     * @param string $productCode
     * @param int|null $excludeId To exclude a specific product ID (e.g., when updating)
     * @return bool
     */
    public function productCodeExists(string $productCode, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM products WHERE product_code = :product_code";
        $params = [':product_code' => $productCode];
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logger->error("Error checking if product code exists: {$productCode}", ['exception' => $e->getMessage()]);
            return true; // Fail safe: assume it exists if DB error
        }
    }
}