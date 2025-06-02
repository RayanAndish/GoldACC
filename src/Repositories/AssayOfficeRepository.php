<?php

namespace App\Repositories; // Namespace مطابق با پوشه src/Repositories

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;

/**
 * کلاس AssayOfficeRepository برای تعامل با جدول پایگاه داده assay_offices.
 */
class AssayOfficeRepository {

    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * دریافت تمام مراکز ری گیری.
     *
     * @return array آرایه‌ای از مراکز.
     * @throws PDOException.
     */
    public function getAll(): array {
        $this->logger->debug("Fetching all assay offices.");
        try {
            $sql = "SELECT id, name, phone, address FROM assay_offices ORDER BY name ASC";
            $stmt = $this->db->query($sql);
            $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->info("Fetched " . count($offices) . " assay offices.");
            return $offices;
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching all assay offices: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * دریافت یک مرکز ری گیری بر اساس ID.
     *
     * @param int $officeId شناسه مرکز.
     * @return array|null آرایه اطلاعات مرکز یا null.
     * @throws PDOException.
     */
    public function getById(int $officeId): ?array {
        $this->logger->debug("Fetching assay office with ID: {$officeId}.");
        try {
            $sql = "SELECT id, name, phone, address FROM assay_offices WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $officeId, PDO::PARAM_INT);
            $stmt->execute();
            $office = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($office) $this->logger->debug("Assay office found.", ['id' => $officeId]);
            else $this->logger->debug("Assay office not found.", ['id' => $officeId]);
            return $office ?: null;
        } catch (PDOException $e) {
            $this->logger->error("Database error fetching assay office by ID {$officeId}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * ذخیره (افزودن یا ویرایش) مرکز ری گیری.
     * منطق از src/action/assay_office_save.php گرفته شده.
     *
     * @param array $officeData آرایه داده‌ها (باید شامل name و اختیاری id, phone, address باشد).
     * @return int شناسه مرکز ذخیره شده.
     * @throws PDOException.
     * @throws Exception در صورت داده نامعتبر یا خطای Unique Constraint.
     */
    public function save(array $officeData): int {
        $officeId = $officeData['id'] ?? null;
        $isEditing = $officeId !== null;
        $this->logger->info(($isEditing ? "Updating" : "Creating") . " assay office.", ['id' => $officeId, 'name' => $officeData['name'] ?? 'N/A']);

        if (empty($officeData['name'])) {
            $this->logger->error("Attempted to save assay office with missing name.", ['data' => $officeData]);
            throw new Exception("نام مرکز ری گیری الزامی است.");
        }

        try {
            if ($isEditing) {
                $sql = "UPDATE assay_offices SET name = :name, phone = :phone, address = :address, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':id', $officeId, PDO::PARAM_INT);
            } else {
                $sql = "INSERT INTO assay_offices (name, phone, address, created_at, updated_at) VALUES (:name, :phone, :address, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                $stmt = $this->db->prepare($sql);
            }
            $stmt->bindValue(':name', $officeData['name'], PDO::PARAM_STR);
            // استفاده از null برای فیلدهای اختیاری اگر خالی هستند
            $stmt->bindValue(':phone', !empty($officeData['phone']) ? $officeData['phone'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':address', !empty($officeData['address']) ? $officeData['address'] : null, PDO::PARAM_STR);
            $stmt->execute();

            if (!$isEditing) {
                $officeId = (int)$this->db->lastInsertId();
                 $this->logger->info("Assay office created successfully with ID: {$officeId}.", ['name' => $officeData['name']]);
            } else {
                 if ($stmt->rowCount() === 0) {
                      $this->logger->warning("Assay office update attempted for ID {$officeId} but no row was affected.");
                 } else {
                      $this->logger->info("Assay office updated successfully.", ['id' => $officeId, 'name' => $officeData['name']]);
                 }
            }

            return (int)$officeId;

        } catch (PDOException $e) {
            $this->logger->error("Database error saving assay office: " . $e->getMessage(), ['exception' => $e, 'id' => $officeId, 'name' => $officeData['name'] ?? 'N/A']);
            if ($e->getCode() === '23000') { // Unique constraint violation (assuming name is unique)
                throw new Exception("مرکز ری گیری با نام '{$officeData['name']}' از قبل موجود است.", 0, $e);
            }
            throw $e;
        } catch (Throwable $e) {
             $this->logger->error("Error saving assay office: " . $e->getMessage(), ['exception' => $e, 'id' => $officeId, 'name' => $officeData['name'] ?? 'N/A']);
             throw $e;
        }
    }

    /**
     * بررسی اینکه آیا یک مرکز ری گیری در جدول معاملات استفاده شده است.
     * منطق از src/action/assay_office_delete.php گرفته شده.
     *
     * @param int $officeId شناسه مرکز.
     * @return int تعداد دفعات استفاده شده در معاملات.
     * @throws PDOException.
     */
    public function countUsageInTransactions(int $officeId): int {
        $this->logger->debug("Checking usage count for assay office ID {$officeId} in transactions.");
        try {
            $sql = "SELECT COUNT(*) FROM transactions WHERE assay_office_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $officeId, PDO::PARAM_INT);
            $stmt->execute();
            $count = (int)$stmt->fetchColumn();
             $this->logger->debug("Assay office ID {$officeId} used {$count} times in transactions.");
            return $count;
        } catch (PDOException $e) {
            $this->logger->error("Database error counting assay office usage in transactions for ID {$officeId}: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * حذف یک مرکز ری گیری بر اساس ID.
     * منطق از src/action/assay_office_delete.php گرفته شده.
     *
     * @param int $officeId شناسه مرکز برای حذف.
     * @return bool True اگر حذف شد، False اگر یافت نشد.
     * @throws PDOException در صورت خطای دیتابیس (شامل Foreign Key).
     * @throws Exception در صورت خطای دیگر.
     */
    public function delete(int $officeId): bool {
        $this->logger->info("Attempting to delete assay office with ID: {$officeId}.");
        // ** نکته: چک کردن وابستگی در countUsageInTransactions() باید قبل از فراخوانی این متد در Service یا Controller انجام شود. **

        try {
            $sql = "DELETE FROM assay_offices WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $officeId, PDO::PARAM_INT);
            $stmt->execute();

            $deletedCount = $stmt->rowCount();
            if ($deletedCount > 0) {
                $this->logger->info("Assay office deleted successfully.", ['id' => $officeId]);
                return true;
            } else {
                $this->logger->warning("Assay office delete attempted for ID {$officeId} but no row was affected (Not found?).");
                return false;
            }
        } catch (PDOException $e) {
            $this->logger->error("Database error deleting assay office with ID {$officeId}: " . $e->getMessage(), ['exception' => $e]);
             // اگر Foreign Key Constraint تعریف شده باشد و چک کردن فراموش شده باشد، اینجا خطا می‌دهد.
             if ($e->getCode() === '23000') { // Foreign key violation
                  throw new Exception("امکان حذف مرکز ری گیری وجود ندارد: در معاملات استفاده شده است.", 0, $e);
             }
            throw $e; // Re-throw other PDOExceptions
        } catch (Throwable $e) {
             $this->logger->error("Error deleting assay office with ID {$officeId}: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
        }
    }

    /**
     * جستجو و صفحه‌بندی مراکز ری‌گیری با امکان جستجو در نام، تلفن و آدرس.
     * @param string $searchTerm عبارت جستجو
     * @param int $limit تعداد رکورد در هر صفحه
     * @param int $offset شروع رکورد
     * @return array ['offices'=>[], 'total'=>int]
     */
    public function searchAndPaginate(string $searchTerm, int $limit, int $offset): array {
        $params = [];
        $where = '';
        if ($searchTerm !== '') {
            $where = "WHERE name LIKE :q1 OR phone LIKE :q2 OR address LIKE :q3";
            $params[':q1'] = '%' . $searchTerm . '%';
            $params[':q2'] = '%' . $searchTerm . '%';
            $params[':q3'] = '%' . $searchTerm . '%';
        }
        // شمارش کل رکوردها
        $sqlCount = "SELECT COUNT(*) FROM assay_offices $where";
        $stmtCount = $this->db->prepare($sqlCount);
        foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
        $stmtCount->execute();
        $total = (int)$stmtCount->fetchColumn();
        // دریافت رکوردهای صفحه جاری
        $sql = "SELECT id, name, phone, address FROM assay_offices $where ORDER BY name ASC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $offices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return ['offices'=>$offices, 'total'=>$total];
    }

    // سایر متدهای مورد نیاز (مثلا getByName)
}