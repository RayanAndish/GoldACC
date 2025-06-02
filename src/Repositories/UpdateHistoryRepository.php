<?php
namespace App\Repositories;

use PDO;
use PDOException;
use Monolog\Logger;

class UpdateHistoryRepository {
    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * ثبت یک رکورد جدید در تاریخچه به‌روزرسانی
     */
    public function add(string $version, string $status, string $log = null): bool {
        try {
            $sql = "INSERT INTO update_history (version, update_time, status, log) VALUES (:version, NOW(), :status, :log)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':version', $version);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':log', $log);
            return $stmt->execute();
        } catch (PDOException $e) {
            $this->logger->error("Failed to insert update history record: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت لیست تاریخچه به‌روزرسانی با صفحه‌بندی
     */
    public function getList(int $limit, int $offset): array {
        $sql = "SELECT * FROM update_history ORDER BY update_time DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * شمارش کل رکوردها
     */
    public function countAll(): int {
        $sql = "SELECT COUNT(*) FROM update_history";
        return (int)$this->db->query($sql)->fetchColumn();
    }

    /**
     * دریافت یک گزارش خاص بر اساس id
     */
    public function getById(int $id): ?array {
        $sql = "SELECT * FROM update_history WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
} 