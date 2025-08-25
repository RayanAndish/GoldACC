<?php
namespace App\Repositories;

use PDO;
use Monolog\Logger;
use App\Utils\Helper;
use Throwable;

class PhysicalSettlementRepository {
    private PDO $db;
    private Logger $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function createSettlement(array $data): int {
        $sql = "INSERT INTO physical_settlements (contact_id, direction, notes) VALUES (:contact_id, :direction, :notes)";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':contact_id' => $data['contact_id'],
                ':direction' => $data['direction'],
                ':notes' => $data['notes'],
            ]);
            return (int)$this->db->lastInsertId();
        } catch (Throwable $e) {
            $this->logger->error("DB error creating settlement.", ['exception' => $e]);
            throw $e;
        }
    }

    public function createSettlementItem(array $data): bool {
        // کوئری SQL از ستون `weight_scale` استفاده می‌کند
        $sql = "INSERT INTO physical_settlement_items (settlement_id, product_id, weight_scale, carat, weight_750) 
                VALUES (:settlement_id, :product_id, :weight_scale, :carat, :weight_750)";
        try {
            $stmt = $this->db->prepare($sql);

            // **اصلاح کلیدی:** کلیدهای آرایه execute باید دقیقاً با نام placeholder ها در کوئری مطابقت داشته باشد
            return $stmt->execute([
                ':settlement_id' => $data['settlement_id'],
                ':product_id'    => $data['product_id'],
                ':weight_scale'  => $data['weight'], // کلید 'weight' از کنترلر به placeholder ':weight_scale' متصل می‌شود
                ':carat'         => $data['carat'],
                ':weight_750'    => $data['weight_750'],
            ]);
        } catch (Throwable $e) {
            $this->logger->error("DB error creating settlement item.", ['exception' => $e]);
            throw $e;
        }
    }
}