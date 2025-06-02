<?php

namespace App\Utils;

use PDO;
use PDOException;
use Monolog\Logger;
use Exception;
use NumberFormatter;
use DateTime;
use App\Core\CSRFProtector;
use Morilog\Jalali\Jalalian; // FIX: Add use statement for Jalalian

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
        
        // Load messages from file if not already loaded
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
     * FIX: اضافه کردن متد parseJalaliDateToSql
     * این متد یک تاریخ شمسی (مانند '1403/04/02') را به فرمت SQL (مانند '2024-06-22') تبدیل می‌کند.
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
            // جدا کردن بخش تاریخ در صورتی که زمان هم وجود داشته باشد
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
     * FIX: اضافه کردن متد parseJalaliDatetimeToSql
     * این متد یک تاریخ و زمان شمسی (مانند '1403/04/02 15:30:00') را به فرمت SQL تبدیل می‌کند.
     *
     * @param string|null $jalaliDatetime رشته تاریخ و زمان شمسی
     * @return string|null تاریخ و زمان میلادی در فرمت SQL یا null اگر ورودی نامعتبر باشد
     */
    public static function parseJalaliDatetimeToSql(?string $jalaliDatetime): ?string {
        if (empty($jalaliDatetime)) {
            return null;
        }
        try {
            return Jalalian::fromFormat('Y/m/d H:i:s', $jalaliDatetime)->toCarbon()->toDateTimeString();
        } catch (\Exception $e) {
            self::$logger?->warning('Failed to parse Jalali datetime to SQL format.', ['datetime' => $jalaliDatetime, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public static function logActivity(?PDO $db, string $message, string $actionType = 'GENERAL', string $level = 'INFO', array $data = []): void {
        // استفاده از لاگر Monolog تزریق شده
        if (self::$logger) {
            // تعیین سطح لاگ Monolog بر اساس پارامتر $level
            $logLevel = LogLevel::INFO; // پیش‌فرض
            switch (strtoupper($level)) {
                case 'ERROR':
                case 'FATAL':
                    $logLevel = LogLevel::ERROR;
                    break;
                case 'WARNING':
                    $logLevel = LogLevel::WARNING;
                    break;
                case 'DEBUG':
                    $logLevel = LogLevel::DEBUG;
                    break;
                case 'CRITICAL':
                    $logLevel = LogLevel::CRITICAL;
                    break;
                case 'ALERT':
                    $logLevel = LogLevel::ALERT;
                     break;
                case 'EMERGENCY':
                    $logLevel = LogLevel::EMERGENCY;
                     break;
                default:
                    $logLevel = LogLevel::INFO;
            }

            // اضافه کردن اطلاعات کاربر و IP به داده‌های لاگ
            $logData = array_merge([
                 'user_id' => $_SESSION['user_id'] ?? null,
                 'username' => $_SESSION['username'] ?? 'guest',
                 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                 'ray_id' => $_SESSION['ray_id'] ?? uniqid('Ray-', true),
                 'action_type' => strtoupper($actionType), // ذخیره نوع عملیات
                 'level' => strtoupper($level), // ذخیره سطح لاگ
            ], $data);

            self::$logger->log($logLevel, $message, $logData);

        } else {
            // Fallback به error_log پیش فرض PHP اگر لاگر Monolog تنظیم نشده باشد.
            $logMessage = sprintf(
                "[%s] [%s] [%s] [User: %s] [IP: %s] %s %s\n",
                date('Y-m-d H:i:s'),
                strtoupper($level),
                strtoupper($actionType),
                $_SESSION['username'] ?? 'guest',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $message,
                json_encode($data, JSON_UNESCAPED_UNICODE) // استفاده از json_encode
            );
            error_log($logMessage);
        }

        // ذخیره در جدول activity_logs (هنوز به Repository منتقل نشده)
        // این بخش باید به یک ActivityLogRepository اختصاصی منتقل شود.
        if ($db) {
            try {
                $user_id = $data['user_id'] ?? $_SESSION['user_id'] ?? null;
                $username = $data['username'] ?? $_SESSION['username'] ?? 'guest';
                $ip_address = $data['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                if ($ip_address === '::1') {
                    $ip_address = '127.0.0.1'; // تبدیل IPv6 لوکال به IPv4
                }
                $ray_id = $data['ray_id'] ?? $_SESSION['ray_id'] ?? uniqid('Ray-', true);

                // فقط داده های اضافی (نه اطلاعات اصلی لاگ) را به عنوان جزئیات ذخیره کن
                $detailsToStore = array_diff_key($data, array_flip(['user_id', 'username', 'ip', 'ray_id', 'action_type', 'level']));
                $action_details = !empty($detailsToStore) ? json_encode($detailsToStore, JSON_UNESCAPED_UNICODE) : null;

                // حذف level_name از کوئری INSERT
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, username, action_type, action_details, ip_address, ray_id) VALUES (:user_id, :username, :action_type, :action_details, :ip_address, :ray_id)");
                $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindValue(':username', $username, PDO::PARAM_STR);
                $stmt->bindValue(':action_type', strtoupper($actionType), PDO::PARAM_STR); // ذخیره نوع عملیات
                $stmt->bindValue(':action_details', $action_details, PDO::PARAM_STR);
                $stmt->bindValue(':ip_address', $ip_address, PDO::PARAM_STR);
                $stmt->bindValue(':ray_id', $ray_id, PDO::PARAM_STR);
                $stmt->execute();
            } catch (PDOException $e) {
                // اگر در ذخیره لاگ در دیتابیس خطا رخ داد، حداقل در فایل لاگ Monolog ثبت شود.
                 if (self::$logger) {
                     self::$logger->error("Database error saving activity log: " . $e->getMessage(), ['exception' => $e, 'log_message' => $message, 'log_type' => $level, 'log_data' => $data]);
                 } else {
                      error_log("Database error saving activity log (Monolog not ready): " . $e->getMessage());
                 }
            }
        } else {
             // اگر اتصال دیتابیس در متد logActivity ارسال نشده بود
             if (self::$logger) {
                 self::$logger->warning("Activity log attempted but database connection not provided.", ['message' => $message, 'type' => $level, 'data' => $data]);
             } else {
                  error_log("Activity log attempted but database connection not provided and Monolog not ready.");
             }
        }
    }

    // ... سایر متدهای کلاس Helper ...
    public static function getMessageText(string $key, ?string $default = null): string {
        // Check if messages array is loaded and key exists
        if (self::$messages !== null && array_key_exists($key, self::$messages)) {
            return self::$messages[$key];
        }

        // Log warning if message key is not found
        self::logWarning('Message key not found.', ['key' => $key]);

        // Return key wrapped in ## if debug is on, otherwise return default or key
        if ((self::$config['app']['debug'] ?? false) && $default === null) {
            return "##{$key}##";
        }

        return $default ?? $key; // Return default or the key itself as fallback
    }
    
    private static function logWarning(string $message, array $context = []): void {
        if (self::$logger) {
            self::$logger->warning($message, $context);
        } else {
            error_log("Helper Warning (Monolog not ready): " . $message);
        }
    }

     private static function logInfo(string $message, array $context = []): void {
         if (self::$logger) {
             self::$logger->info($message, $context);
         } else {
             error_log("Helper Info (Monolog not ready): " . $message);
         }
     }

    public static function translateProductType(?string $type): string {
        if ($type === null || trim($type) === '') return '-';
        $types = [
            'melted'                 => 'آبشده',
            'used_jewelry'           => 'طلای دست دوم / متفرقه',
            'new_jewelry'            => 'طلای نو / ساخته شده',
            'coin_bahar_azadi_new'   => 'سکه بهار جدید',
            'coin_bahar_azadi_old'   => 'سکه بهار قدیم',
            'coin_emami'             => 'سکه امامی',
            'coin_half'              => 'نیم سکه',
            'coin_quarter'           => 'ربع سکه',
            'coin_gerami'            => 'سکه گرمی',
            'other_coin'             => 'سایر سکه ها',
            'bullion'                => 'شمش / طلای خام'
        ];
        // Return translation or a formatted version of the type code
        return $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
     }

    public static function getContactTypeFarsi(?string $type): string {
        if ($type === null || trim($type) === '') return Helper::getMessageText('unknown', '-'); // Use getMessageText
        $types = [
            'debtor'           => 'مشتری',
            'creditor_account' => 'تأمین کننده',
            'counterparty'     => 'همکار صنفی',
            'mixed'            => 'حساب واسط',
            'other'            => 'متفرقه'
        ];
        return $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
    
    public static function translateLicenseStatus(?string $status): string
    {
        $map = [
            'active'   => 'فعال',
            'expired'  => 'منقضی',
            'pending'  => 'در انتظار',
            'revoked'  => 'لغو شده',
            'unknown'  => 'نامشخص',
        ];
        $status = strtolower((string)$status);
        return $map[$status] ?? 'نامشخص';
    }
 
    
    public static function getLicenseStatusClass(string $status): string {
        return match ($status) {
            'active' => 'success',
            'expired', 'revoked' => 'danger',
            'suspended' => 'warning',
            default => 'secondary',
        };
    }

    public static function translateDeliveryStatus(?string $status): string {
        if ($status === null || trim($status) === '') return '-';
        $statuses = [
            'completed'         => 'تکمیل شده',
            'pending_delivery'  => 'منتظر تحویل',
            'pending_receipt'   => 'منتظر دریافت',
           'cancelled'         => 'لغو شده'
        ];
        return $statuses[$status] ?? ucfirst(str_replace('_', ' ', $status)); // Fallback
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
    public static function getDeliveryStatusOptions(): array {
        return [
            'pending_receipt' => 'در انتظار دریافت',
            'pending_delivery' => 'در انتظار تحویل',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
        ];
    }

    public static function sanitizeNumbersRecursive(array $data, ?array $numericKeys = null): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::sanitizeNumbersRecursive($value, $numericKeys);
            } else {
                // اگر لیست فیلدهای عددی داده شده بود فقط روی آن‌ها عمل کن، وگرنه بر اساس نام کلید تشخیص بده
                $isNumericField = false;
                if ($numericKeys !== null) {
                    $isNumericField = in_array($key, $numericKeys, true);
                } else {
                    // تشخیص بر اساس نام کلید (مثلاً شامل price, amount, weight, quantity, carat, rate, percent)
                    $isNumericField = preg_match('/(price|amount|weight|quantity|carat|rate|percent|tax|fee|profit|value|year|usd|rial)/i', $key);
                }
                if ($isNumericField) {
                    $result[$key] = self::sanitizeFormattedNumber($value);
                } else {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }

    public static function convertNumberToWords(int $number): string {
        $words = [
            0 => 'صفر', 1 => 'یک', 2 => 'دو', 3 => 'سه', 4 => 'چهار', 5 => 'پنج', 6 => 'شش', 7 => 'هفت', 8 => 'هشت', 9 => 'نه',
            10 => 'ده', 11 => 'یازده', 12 => 'دوازده', 13 => 'سیزده', 14 => 'چهارده', 15 => 'پانزده', 16 => 'شانزده', 17 => 'هفده', 18 => 'هجده', 19 => 'نوزده',
            20 => 'بیست', 30 => 'سی', 40 => 'چهل', 50 => 'پنجاه', 60 => 'شصت', 70 => 'هفتاد', 80 => 'هشتاد', 90 => 'نود',
            100 => 'صد', 200 => 'دویست', 300 => 'سیصد', 400 => 'چهارصد', 500 => 'پانصد', 600 => 'ششصد', 700 => 'هفتصد', 800 => 'هشتصد', 900 => 'نهصد'
        ];
        if ($number < 0) return 'منفی ' . self::convertNumberToWords(-$number);
        if ($number < 20) return $words[$number];
          if ($number < 100) {
              $tens = floor($number / 10) * 10;
              $units = $number % 10;
              return $words[$tens] . ($units ? ' و ' . $words[$units] : '');
          }
          if ($number < 1000) {
              $hundreds = floor($number / 100) * 100;
              $remainder = $number % 100;
            return $words[$hundreds] . ($remainder ? ' و ' . self::convertNumberToWords($remainder) : '');
          }
          if ($number < 1000000) {
              $thousands = floor($number / 1000);
              $remainder = $number % 1000;
            return self::convertNumberToWords($thousands) . ' هزار' . ($remainder ? ' و ' . self::convertNumberToWords($remainder) : '');
          }
          if ($number < 1000000000) {
              $millions = floor($number / 1000000);
              $remainder = $number % 1000000;
            return self::convertNumberToWords($millions) . ' میلیون' . ($remainder ? ' و ' . self::convertNumberToWords($remainder) : '');
          }
          if ($number < 1000000000000) {
              $billions = floor($number / 1000000000);
              $remainder = $number % 1000000000;
            return self::convertNumberToWords($billions) . ' میلیارد' . ($remainder ? ' و ' . self::convertNumberToWords($remainder) : '');
          }
          return 'عدد بزرگتر از ۱۰۰۰ میلیارد است';
     }
    
     public static function sanitizeFormattedNumber(?string $numberStr): ?string {
        if ($numberStr === null) return null;

        // 1. Trim whitespace
        $cleaned = trim($numberStr);

        // 2. Remove commas (thousands separators)
        $cleaned = str_replace(',', '', $cleaned);

        // 3. Convert Persian/Arabic digits and decimal separators to English
        $cleaned = strtr($cleaned, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٫'=>'.','،'=>'.' // Persian and Arabic decimal/thousands separators
        ]);

        // 4. Remove any characters that are NOT digits, decimal point, or negative sign
        $cleaned = preg_replace('/[^\d.-]/', '', $cleaned);

        // 5. Validate the resulting string as a float.
        // filter_var with FILTER_VALIDATE_FLOAT is good, but can return the float value directly.
        // We want the string representation after cleaning.
        // is_numeric handles float/int representations correctly.
        if (is_numeric($cleaned)) {
            // Handle edge cases like "-" or "." only, which is_numeric considers true but are invalid numbers.
            if ($cleaned === '-' || $cleaned === '.' || $cleaned === '+') return ''; // Treat as invalid
            // Ensure only one decimal point and negative sign at the beginning.
            if (substr_count($cleaned, '.') > 1 || (strpos($cleaned, '-') > 0 && $cleaned[0] !== '-')) {
                self::logWarning("Sanitization resulted in invalid number format after cleaning: " . $numberStr . " -> " . $cleaned);
                return '';
            }
            return $cleaned;
        } elseif ($cleaned === '') {
             return ''; // Input was empty or only contained removed characters
        }

        // If we reach here, the cleaned string is not numeric.
        self::logWarning("Input not numeric after sanitization: " . $numberStr . " -> " . $cleaned);
        return ''; // Invalid input
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
        
        // تبدیل به float برای اطمینان از عدد بودن
        $number = (float) $number;
        
        return number_format($number, $decimals, $decPoint, $thousandsSep);
    }
    
    public static function generatePaginationData(int $currentPage, int $totalPages, int $totalRecords, int $itemsPerPage, int $linksToShow = 5): array {
        $pagination = [
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'itemsPerPage' => $itemsPerPage,
            'hasNextPage' => $currentPage < $totalPages,
            'hasPrevPage' => $currentPage > 1,
            'nextPage' => $currentPage + 1,
            'prevPage' => $currentPage - 1,
            'pages' => [],
            'firstItem' => ($totalRecords > 0) ? (($currentPage - 1) * $itemsPerPage) + 1 : 0,
            'lastItem' => ($totalRecords > 0) ? min($currentPage * $itemsPerPage, $totalRecords) : 0,
        ];

        if ($totalPages <= 1) {
            return $pagination;
        }

        $start = max(1, $currentPage - (int)floor(($linksToShow - 1) / 2));
        $end = min($totalPages, $currentPage + (int)ceil(($linksToShow - 1) / 2));

        if ($end - $start + 1 < $linksToShow) {
            if ($start === 1) {
                $end = min($totalPages, $start + $linksToShow - 1);
            } elseif ($end === $totalPages) {
                $start = max(1, $end - $linksToShow + 1);
            }
        }

        if ($start > 1) {
            $pagination['pages'][] = ['num' => 1, 'isCurrent' => false, 'isEllipsis' => false];
            if ($start > 2) {
                $pagination['pages'][] = ['num' => '...', 'isCurrent' => false, 'isEllipsis' => true];
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $pagination['pages'][] = ['num' => $i, 'isCurrent' => $i === $currentPage, 'isEllipsis' => false];
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $pagination['pages'][] = ['num' => '...', 'isCurrent' => false, 'isEllipsis' => true];
            }
            $pagination['pages'][] = ['num' => $totalPages, 'isCurrent' => false, 'isEllipsis' => false];
        }

        return $pagination;
    }
}