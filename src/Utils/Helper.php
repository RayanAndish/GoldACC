<?php

namespace App\Utils;

use PDO;
use Monolog\Logger;
use Exception;
use DateTime;
use App\Core\CSRFProtector;
use Morilog\Jalali\Jalalian;
use Psr\Log\LogLevel;

/**
 * کلاس Helper برای توابع کمکی عمومی.
 */
class Helper {

    private static ?Logger $logger = null;
    private static ?array $config = null;
    private static ?array $messages = null;

    public static function initialize(Logger $logger, array $config): void {
        self::$logger = $logger;
        self::$config = $config;
        
        if (self::$messages === null) {
            $messagesFile = $config['paths']['src'] . '/messages.php';
            if (file_exists($messagesFile)) {
                self::$messages = require $messagesFile;
            } else {
                self::$messages = [];
                $logger->warning("Messages file not found at: " . $messagesFile);
            }
        }
    }

    public static function escapeHtml(mixed $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function generateCsrfToken(): string {
        return CSRFProtector::generateToken();
    }

    public static function verifyCsrfToken(?string $token): bool {
        return CSRFProtector::validateToken($token);
    }
    
    /**
     * یک تاریخ شمسی (مانند '1403/04/02') را به فرمت SQL (مانند '2024-06-22') تبدیل می‌کند.
     *
     * @param string|null $jalaliDate رشته تاریخ شمسی
     * @param bool $endOfDay اگر true باشد، زمان را به 23:59:59 برای انتهای روز تنظیم می‌کند
     * @return string|null تاریخ میلادی در فرمت SQL یا null اگر ورودی نامعتبر باشد
     */
    public static function parseJalaliDateToSql(?string $jalaliDate, bool $endOfDay = false): ?string {
        if (empty($jalaliDate)) {
            return null;
        }
        try {
            $datePart = preg_replace('#\s.*#', '', $jalaliDate);
            $parts = preg_split('/[-\/]/', $datePart);
            
            if (count($parts) === 3 && ctype_digit($parts[0]) && ctype_digit($parts[1]) && ctype_digit($parts[2])) {
                $gregorian = Jalalian::fromFormat('Y/m/d', implode('/', $parts))->toCarbon();
                if ($endOfDay) {
                    return $gregorian->endOfDay()->toDateTimeString();
                }
                return $gregorian->toDateString();
            }
            return null;
        } catch (\Exception $e) {
            self::$logger?->warning('Failed to parse Jalali date to SQL format.', ['date' => $jalaliDate, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * یک تاریخ و زمان شمسی (مانند '1403/04/02 15:30:00') را به فرمت SQL تبدیل می‌کند.
     *
     * @param string|null $jalaliDatetime رشته تاریخ و زمان شمسی
     * @return string|null تاریخ و زمان میلادی در فرمت SQL یا null اگر ورودی نامعتبر باشد
     */
    public static function parseJalaliDatetimeToSql(?string $jalaliDatetime): ?string {
        if (empty($jalaliDatetime)) {
            return null;
        }
        try {
            // پشتیبانی از فرمت با و بدون ثانیه
            $format = (substr_count($jalaliDatetime, ':') == 2) ? 'Y/m/d H:i:s' : 'Y/m/d H:i';
            return Jalalian::fromFormat($format, $jalaliDatetime)->toCarbon()->toDateTimeString();
        } catch (\Exception $e) {
            self::$logger?->warning('Failed to parse Jalali datetime to SQL format.', ['datetime' => $jalaliDatetime, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * NEW: فرمت‌دهی تاریخ میلادی به شمسی برای نمایش.
     *
     * @param string|null $gregorianDateTime تاریخ میلادی از دیتابیس یا 'now'
     * @param string $format فرمت خروجی
     * @return string تاریخ شمسی فرمت شده یا رشته خالی
     */
    public static function formatPersianDateTime(?string $gregorianDateTime, string $format = 'Y/m/d H:i'): string {
        if (empty($gregorianDateTime)) {
            return '';
        }
        try {
            if (strtolower($gregorianDateTime) === 'now') {
                return Jalalian::now()->format($format);
            }
            $dt = new DateTime($gregorianDateTime);
            return Jalalian::fromDateTime($dt)->format($format);
        } catch (Exception $e) {
            self::$logger?->warning('Failed to format Gregorian datetime to Persian.', ['datetime' => $gregorianDateTime, 'error' => $e->getMessage()]);
            return '';
        }
    }

    public static function logActivity(?PDO $db, string $message, string $actionType = 'GENERAL', string $level = 'INFO', array $data = []): void {
        if (self::$logger) {
            $logLevel = strtolower($level);
            if (!in_array($logLevel, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])) {
                $logLevel = 'info';
            }
            $logData = array_merge([
                 'user_id' => $_SESSION['user_id'] ?? null,
                 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                 'action_type' => strtoupper($actionType),
            ], $data);
            self::$logger->log($logLevel, $message, $logData);
        }
    }

    public static function getMessageText(string $key, ?string $default = null): string {
        if (self::$messages !== null && array_key_exists($key, self::$messages)) {
            return self::$messages[$key];
        }
        self::logWarning('Message key not found.', ['key' => $key]);
        if ((self::$config['app']['debug'] ?? false) && $default === null) {
            return "##{$key}##";
        }
        return $default ?? $key;
    }
    
    private static function logWarning(string $message, array $context = []): void {
        if (self::$logger) {
            self::$logger->warning($message, $context);
        }
    }

    public static function translateDeliveryStatus(?string $status): string {
        if ($status === null || trim($status) === '') return '-';
        $statuses = [
            'completed'         => 'تکمیل شده',
            'pending_delivery'  => 'منتظر تحویل',
            'pending_receipt'   => 'منتظر دریافت',
            'cancelled'         => 'لغو شده'
        ];
        return $statuses[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function getDeliveryStatusClass(string $status): string {
        return match ($status) {
            'completed' => 'bg-success',
            'pending_receipt' => 'bg-info text-dark',
            'pending_delivery' => 'bg-warning text-dark',
            'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
    }
    
    public static function getDeliveryStatusOptions(bool $forFilter = false): array {
        $statuses = [
            'pending_receipt' => 'منتظر دریافت',
            'pending_delivery' => 'منتظر تحویل',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
        ];
        if ($forFilter) {
            return ['' => 'همه وضعیت‌ها'] + $statuses;
        }
        return $statuses;
    }

    public static function sanitizeFormattedNumber(?string $numberStr): ?string {
        if ($numberStr === null) return null;
        $cleaned = trim($numberStr);
        $cleaned = str_replace([',', '٬', '،'], '', $cleaned);
        $cleaned = strtr($cleaned, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٫'=>'.'
        ]);
        if (is_numeric($cleaned)) {
            return $cleaned;
        }
        return '0';
    }
    
    public static function formatRial($amount, bool $withSuffix = true): string {
        $formatted = number_format((float)$amount, 0, '.', ',');
        return $withSuffix ? $formatted . ' ریال' : $formatted;
    }
    
    public static function formatNumber($number, int $decimals = 0, string $decPoint = '.', string $thousandsSep = ','): string
    {
        if ($number === null) {
            return '0';
        }
        return number_format((float) $number, $decimals, $decPoint, $thousandsSep);
    }
    
    public static function generatePaginationData(int $currentPage, int $itemsPerPage, int $totalRecords): array {
        $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $itemsPerPage) : 1;
        $currentPage = max(1, min($currentPage, $totalPages));
        return [
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'itemsPerPage' => $itemsPerPage,
            'offset' => ($currentPage - 1) * $itemsPerPage,
            'hasNextPage' => $currentPage < $totalPages,
            'hasPrevPage' => $currentPage > 1,
            'nextPage' => $currentPage + 1,
            'prevPage' => $currentPage - 1,
            'firstItem' => ($totalRecords > 0) ? (($currentPage - 1) * $itemsPerPage) + 1 : 0,
            'lastItem' => ($totalRecords > 0) ? min($currentPage * $itemsPerPage, $totalRecords) : 0,
        ];
    }
}
