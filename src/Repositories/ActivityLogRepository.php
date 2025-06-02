<?php

namespace App\Repositories;

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;
use Throwable;

/**
 * کلاس ActivityLogRepository برای تعامل با جدول activity_logs.
 * مسئولیت واکشی، فیلتر، صفحه بندی و ذخیره لاگ ها را بر عهده دارد.
 */
class ActivityLogRepository {

    private PDO $db;
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param PDO $db نمونه PDO متصل به دیتابیس.
     * @param Logger $logger نمونه Monolog Logger.
     */
    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * واکشی لاگ های فعالیت با فیلتر جستجو و صفحه بندی.
     * منطق از src/controllers/activity_logs.php گرفته شده.
     *
     * @param string $searchTerm عبارت جستجو.
     * @param string $filterType نوع لاگ برای فیلتر کردن (اختیاری).
     * @param int $limit حداکثر تعداد رکوردها.
     * @param int $offset تعداد رکوردهایی که باید رد شوند.
     * @return array آرایه‌ای از رکوردهای لاگ فعالیت.
     * @throws PDOException.
     */
    public function getFilteredAndPaginated(string $searchTerm, string $filterType, int $limit, int $offset): array {
        $this->logger->debug("Fetching activity logs with search '{$searchTerm}', type '{$filterType}', limit {$limit}, offset {$offset}.");
        try {
            $query = "SELECT al.*, u.username
                      FROM activity_logs al
                      LEFT JOIN users u ON al.user_id = u.id";

            $params = [];
            $whereClauses = [];

            if (!empty($searchTerm)) {
                $whereClauses[] = "(al.action_details LIKE :search1 OR al.action_type LIKE :search2 OR al.ip_address LIKE :search3 OR al.ray_id LIKE :search4 OR u.username LIKE :search5)";
                $params[':search1'] = "%{$searchTerm}%";
                $params[':search2'] = "%{$searchTerm}%";
                $params[':search3'] = "%{$searchTerm}%";
                $params[':search4'] = "%{$searchTerm}%";
                $params[':search5'] = "%{$searchTerm}%";
            }

            if (!empty($filterType)) {
                $whereClauses[] = "al.action_type = :filter_type";
                $params[':filter_type'] = $filterType;
            }

            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(' AND ', $whereClauses);
            }

            $query .= " ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $stmt = $this->db->prepare($query);

            // Bind کردن پارامترها (به صورت دستی برای کنترل نوع)
            foreach ($params as $key => $value) {
                $type = PDO::PARAM_STR;
                if ($key === ':limit' || $key === ':offset') {
                    $type = PDO::PARAM_INT;
                }
                $stmt->bindValue($key, $value, $type);
            }

            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logger->info("Fetched " . count($logs) . " activity log records.");
            return $logs;

        } catch (PDOException $e) {
            $this->logger->error("Database error fetching activity logs: " . $e->getMessage(), ['exception' => $e, 'search' => $searchTerm, 'limit' => $limit, 'offset' => $offset]);
            throw $e; // Re-throw the PDOException
        } catch (Throwable $e) { // گرفتن خطاهای عمومی دیگر
            $this->logger->error("Error fetching activity logs: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * شمارش کل رکوردهای لاگ فعالیت با فیلتر جستجو.
     * منطق از src/controllers/activity_logs.php گرفته شده.
     *
     * @param string $searchTerm عبارت جستجو.
     * @param string $filterType نوع لاگ برای فیلتر کردن (اختیاری).
     * @return int تعداد کل رکوردها.
     * @throws PDOException.
     */
    public function countFiltered(string $searchTerm, string $filterType): int {
        $this->logger->debug("Counting activity logs with search '{$searchTerm}' and type '{$filterType}'.");
        try {
            $count_query = "SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id";

            $params = [];
            $whereClauses = [];

            if (!empty($searchTerm)) {
                $whereClauses[] = "(al.action_details LIKE :search1 OR al.action_type LIKE :search2 OR al.ip_address LIKE :search3 OR al.ray_id LIKE :search4 OR u.username LIKE :search5)";
                $params[':search1'] = "%{$searchTerm}%";
                $params[':search2'] = "%{$searchTerm}%";
                $params[':search3'] = "%{$searchTerm}%";
                $params[':search4'] = "%{$searchTerm}%";
                $params[':search5'] = "%{$searchTerm}%";
            }

            if (!empty($filterType)) {
                $whereClauses[] = "al.action_type = :filter_type";
                $params[':filter_type'] = $filterType;
            }

            if (!empty($whereClauses)) {
                $count_query .= " WHERE " . implode(' AND ', $whereClauses);
            }

            $count_stmt = $this->db->prepare($count_query);
             // Bind کردن پارامتر جستجو (اگر وجود دارد)
             if (!empty($searchTerm)) {
                 $count_stmt->bindValue(':search1', $params[':search1'], PDO::PARAM_STR);
                 $count_stmt->bindValue(':search2', $params[':search2'], PDO::PARAM_STR);
                 $count_stmt->bindValue(':search3', $params[':search3'], PDO::PARAM_STR);
                 $count_stmt->bindValue(':search4', $params[':search4'], PDO::PARAM_STR);
                 $count_stmt->bindValue(':search5', $params[':search5'], PDO::PARAM_STR);
             }
             // Bind کردن پارامتر نوع لاگ (اگر وجود دارد)
             if (!empty($filterType)) {
                 $count_stmt->bindValue(':filter_type', $params[':filter_type'], PDO::PARAM_STR);
             }
             $count_stmt->execute();
            $count = (int)$count_stmt->fetchColumn();

            $this->logger->info("Counted {$count} activity log records.");
            return $count;

        } catch (PDOException $e) {
            $this->logger->error("Database error counting activity logs: " . $e->getMessage(), ['exception' => $e, 'search' => $searchTerm]);
            throw $e;
        } catch (Throwable $e) {
             $this->logger->error("Error counting activity logs: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
        }
    }

    /**
     * ذخیره یک رکورد لاگ فعالیت در دیتابیس.
     * این منطق از تابع سراسری log_activity در functions.php/logger.php گرفته شده.
     * این متد توسط Helper::logActivity($db, ...) فراخوانی می شود.
     *
     * @param array $logData آرایه داده‌های لاگ (شامل user_id, username, action_type, action_details, ip_address, ray_id).
     * @throws PDOException.
     */
    public function save(array $logData): void {
         $this->logger->debug("Saving activity log record to database.", ['data' => $logData]);
         try {
             $sql = "INSERT INTO activity_logs (user_id, username, action_type, action_details, ip_address, ray_id, created_at) VALUES (:user_id, :username, :action_type, :action_details, :ip_address, :ray_id, NOW())";
             $stmt = $this->db->prepare($sql);
             // Bind values (با در نظر گرفتن nullable بودن user_id و سایر فیلدها)
             $stmt->bindValue(':user_id', $logData['user_id'] ?? null, PDO::PARAM_INT);
             $stmt->bindValue(':username', $logData['username'] ?? 'guest', PDO::PARAM_STR);
             $stmt->bindValue(':action_type', $logData['action_type'] ?? 'UNKNOWN', PDO::PARAM_STR);
             $stmt->bindValue(':action_details', $logData['action_details'] ?? null, PDO::PARAM_STR); // فرض بر این است که details قبلا JSON شده است
             $stmt->bindValue(':ip_address', $logData['ip_address'] ?? 'unknown', PDO::PARAM_STR);
             $stmt->bindValue(':ray_id', $logData['ray_id'] ?? null, PDO::PARAM_STR);
             $stmt->execute();

             // لاگ موفقیت (اختیاری در سطح Repository)
             // $this->logger->debug("Activity log record saved successfully.");

         } catch (PDOException $e) {
              $this->logger->error("Database error saving activity log: " . $e->getMessage(), ['exception' => $e, 'log_data' => $logData]);
              // این یک خطای مهم است اما نباید کل برنامه را متوقف کند.
              // فقط لاگ می کنیم و اجازه می دهیم خطا به ErrorHandler سراسری برسد تا ثبت شود.
              // پرتاب دوباره (throw $e;) برای اطمینان از ثبت در ErrorHandler
              throw $e;
         } catch (Throwable $e) {
              $this->logger->error("Error saving activity log: " . $e->getMessage(), ['exception' => $e, 'log_data' => $logData]);
              throw $e;
         }
    }

    /**
     * شمارش تعداد رکوردهای لاگ با نوع 'ERROR' در 24 ساعت گذشته.
     * نیاز برای MonitoringService.
     *
     * @return int تعداد خطاها.
     * @throws PDOException.
     */
    public function countErrorsLast24Hours(): int {
         $this->logger->debug("Counting error logs in the last 24 hours.");
         try {
             $sql = "SELECT COUNT(*) FROM activity_logs WHERE action_type = 'ERROR' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
             $stmt = $this->db->query($sql);
             $count = (int)$stmt->fetchColumn();
             $this->logger->debug("Error logs in last 24h: {$count}.");
             return $count;
         } catch (PDOException $e) {
             $this->logger->error("Database error counting error logs last 24h: " . $e->getMessage(), ['exception' => $e]);
             throw $e;
         } catch (Throwable $e) {
              $this->logger->error("Error counting error logs last 24h: " . $e->getMessage(), ['exception' => $e]);
              throw $e;
         }
    }

    // در آینده متدهای دیگری برای حذف لاگ های قدیمی، گزارش گیری خاص و ...
}