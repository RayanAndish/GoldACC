<?php
// src/Repositories/ProductRepository.php
namespace App\Repositories;

use PDO;
use PDOException;
use Monolog\Logger;
use App\Models\Product;
use App\Models\ProductCategory;

class ProductRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function findById(int $id, bool $withCategory = false): ?Product {
        $sql = "SELECT p.*";
        $sql .= " FROM products p";
        $sql .= " WHERE p.id = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data ? new Product($data) : null;
        } catch (PDOException $e) {
            $this->logger->error("Error fetching product by ID: {$id}", ['exception' => $e->getMessage()]);
            return null;
        }
    }
    
    public function findByIds(array $ids, bool $withCategory = false): array
    {
        if (empty($ids)) { return []; }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $sql = "SELECT p.*";
        if ($withCategory) {
             $sql .= ", pc.name as category_name, pc.code as category_code, pc.base_category, pc.unit_of_measure as category_unit_of_measure";
        }
        $sql .= " FROM products p";
        if ($withCategory) {
            $sql .= " LEFT JOIN product_categories pc ON p.category_id = pc.id";
        }
        $sql .= " WHERE p.id IN ({$placeholders})";

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($ids as $k => $id) { $stmt->bindValue(($k + 1), $id, PDO::PARAM_INT); }
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $products = [];
            foreach ($results as $data) {
                $product = new Product($data);
                if ($withCategory && isset($data['category_id'])) {
                    $product->category = new ProductCategory([
                        'id' => $data['category_id'],
                        'name' => $data['category_name'],
                        'code' => $data['category_code'] ?? null,
                        'base_category' => $data['base_category'],
                        'unit_of_measure' => $data['category_unit_of_measure'] ?? null
                    ]);
                }
                $products[$product->id] = $product;
            }
            return $products;
        } catch (PDOException $e) {
            $this->logger->error("Error fetching products by IDs.", ['ids' => $ids, 'exception' => $e->getMessage()]);
            throw $e;
        }
    }

    public function findByIdWithCategory(int $id): ?Product
    {
        $this->logger->debug("ProductRepository::findByIdWithCategory called for ID: {$id}");

        $sql = "SELECT p.*, pc.name as category_name, pc.code as category_code, pc.base_category, pc.unit_of_measure as category_unit_of_measure";
        $sql .= " FROM products p JOIN product_categories pc ON p.category_id = pc.id WHERE p.id = :id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->logger->debug("ProductRepository::findByIdWithCategory - Fetched Raw Data from DB:", [
                'product_id' => $id,
                'fetched_data' => $data
            ]);

            if (!$data) return null;

            $product = new Product($data);
            $product->category = new ProductCategory([
                'id' => $data['category_id'],
                'name' => $data['category_name'],
                'code' => $data['category_code'] ?? null,
                'base_category' => $data['base_category'],
                'unit_of_measure' => $data['category_unit_of_measure'] ?? null,
            ]);
            return $product;
        } catch (Throwable $e) {
            $this->logger->error("Error fetching product with category by ID.", ['id' => $id, 'exception' => $e]);
            throw $e;
        }
    }

    public function findAll(array $filters = [], bool $withCategory = false): array {
        $products = [];
        $sql = "SELECT p.*";
        if ($withCategory) {
            $sql .= ", pc.name as category_name, pc.code as category_code, pc.base_category, pc.unit_of_measure as category_unit_of_measure";
        }
        $sql .= " FROM products p";
        if ($withCategory) {
            $sql .= " LEFT JOIN product_categories pc ON p.category_id = pc.id";
        }

        $whereClauses = [];
        $params = [];
        if (isset($filters['is_active'])) {
            $whereClauses[] = "p.is_active = :is_active";
            $params[':is_active'] = (bool)$filters['is_active'];
        }
        if (!empty($whereClauses)) { $sql .= " WHERE " . implode(" AND ", $whereClauses); }
        $sql .= " ORDER BY p.name ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $product = new Product($row);
                if ($withCategory && isset($row['category_id'])) {
                    $product->category = new ProductCategory([
                        'id' => $row['category_id'],
                        'name' => $row['category_name'],
                        'code' => $row['category_code'] ?? null,
                        'base_category' => $row['base_category'],
                        'unit_of_measure' => $row['category_unit_of_measure'] ?? null,
                    ]);
                }
                $products[] = $product;
            }
        } catch (PDOException $e) {
            $this->logger->error("Error fetching all products", ['filters' => $filters, 'exception' => $e->getMessage()]);
            throw $e;
        }
        return $products;
    }

    public function getAllActiveWithCategory(): array {
        return $this->findAll(['is_active' => true], true);
    }

    public function save(Product $product): int|false {
        $isNew = $product->id === null;

        $sql = $isNew
            ? "INSERT INTO products (name, category_id, product_code, unit_of_measure, description, default_carat, is_active, capital_quantity, capital_weight_grams, capital_reference_carat, vat_base_type, general_tax_base_type, tax_rate, vat_rate, created_at, updated_at) VALUES (:name, :category_id, :product_code, :unit_of_measure, :description, :default_carat, :is_active, :capital_quantity, :capital_weight_grams, :capital_reference_carat, :vat_base_type, :general_tax_base_type, :tax_rate, :vat_rate, NOW(), NOW())"
            : "UPDATE products SET name = :name, category_id = :category_id, product_code = :product_code, unit_of_measure = :unit_of_measure, description = :description, default_carat = :default_carat, is_active = :is_active, capital_quantity = :capital_quantity, capital_weight_grams = :capital_weight_grams, capital_reference_carat = :capital_reference_carat, vat_base_type = :vat_base_type, general_tax_base_type = :general_tax_base_type, tax_rate = :tax_rate, vat_rate = :vat_rate, updated_at = NOW() WHERE id = :id";

        try {
            $stmt = $this->db->prepare($sql);
            
            $stmt->bindValue(':name', $product->name, PDO::PARAM_STR);
            $stmt->bindValue(':category_id', $product->category_id, PDO::PARAM_INT);
            $stmt->bindValue(':product_code', $product->product_code, PDO::PARAM_STR);
            $stmt->bindValue(':unit_of_measure', $product->unit_of_measure, PDO::PARAM_STR);
            $stmt->bindValue(':description', $product->description, PDO::PARAM_STR);
            $stmt->bindValue(':default_carat', $product->default_carat, PDO::PARAM_STR);
            $stmt->bindValue(':is_active', $product->is_active, PDO::PARAM_BOOL);
            $stmt->bindValue(':capital_quantity', $product->capital_quantity, PDO::PARAM_STR);
            $stmt->bindValue(':capital_weight_grams', $product->capital_weight_grams, PDO::PARAM_STR);
            $stmt->bindValue(':capital_reference_carat', $product->capital_reference_carat, PDO::PARAM_INT);
            
            $stmt->bindValue(':vat_base_type', $product->vat_base_type, PDO::PARAM_STR);
            $stmt->bindValue(':general_tax_base_type', $product->general_tax_base_type, PDO::PARAM_STR);
            $stmt->bindValue(':tax_rate', $product->tax_rate, PDO::PARAM_STR);
            $stmt->bindValue(':vat_rate', $product->vat_rate, PDO::PARAM_STR);

            if (!$isNew) { $stmt->bindValue(':id', $product->id, PDO::PARAM_INT); }
            $success = $stmt->execute();

            if ($success) {
                if ($isNew) { $product->id = (int)$this->db->lastInsertId(); }
                $this->logger->info("Product saved successfully.", ['id' => $product->id, 'name' => $product->name]);
                return $product->id;
            }
            
            $this->logger->error("Failed to save product.", ['name' => $product->name, 'errorInfo' => $stmt->errorInfo()]);
            return false;

        } catch (PDOException $e) {
            $this->logger->error("Database error saving product: {$product->name}", ['exception' => $e->getMessage()]);
            throw $e;
        }
    }

    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $success = $stmt->execute();

            if ($success && $stmt->rowCount() > 0) {
                $this->logger->info("Product deleted.", ['id' => $id]);
                return true;
            }
            $this->logger->warning("Attempted to delete product, but no record found.", ['id' => $id]);
            return false;
        } catch (PDOException $e) {
            $this->logger->error("Database error deleting product: {$id}", ['exception' => $e->getMessage()]);
            throw $e;
        }
    }

    public function productCodeExists(string $productCode, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM products WHERE product_code = :product_code";
        $params = [':product_code' => $productCode];
        if ($excludeId !== null) { $sql .= " AND id != :exclude_id"; $params[':exclude_id'] = $excludeId; }
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logger->error("Error checking if product code exists: {$productCode}", ['exception' => $e->getMessage()]);
            throw $e;
        }
    }

     /**
     * (جدید) تمام محصولات را به همراه اطلاعات دسته‌بندی آن‌ها واکشی می‌کند.
     * @param array $conditions شرایط جستجو (مثلاً ['has_capital_target' => true])
     * @return array آرایه‌ای از آبجکت‌های Product
     */
    public function findAllWithCategory(array $conditions = []): array
    {
        $this->logger->debug("Fetching all products with categories.", ['conditions' => $conditions]);
        $sql = "SELECT p.*, pc.name as category_name, pc.code as category_code, pc.base_category
                FROM products p
                LEFT JOIN product_categories pc ON p.category_id = pc.id";
        
        $where = [];
        if (!empty($conditions['has_capital_target'])) {
            $where[] = "(p.capital_quantity IS NOT NULL AND p.capital_quantity > 0) OR (p.capital_weight_grams IS NOT NULL AND p.capital_weight_grams > 0)";
        }
        if (!empty($conditions['is_active'])) {
            $where[] = "p.is_active = 1";
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY p.name ASC";

        try {
            $stmt = $this->db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $products = [];
            foreach ($results as $row) {
                $product = new Product($row);
                if (isset($row['category_id'])) {
                    $product->category = new ProductCategory([
                        'id' => $row['category_id'],
                        'name' => $row['category_name'],
                        'code' => $row['category_code'],
                        'base_category' => $row['base_category']
                    ]);
                }
                $products[] = $product;
            }
            return $products;
        } catch (Throwable $e) {
            $this->logger->error("Error fetching products with categories.", ['exception' => $e]);
            return [];
        }
    }
}