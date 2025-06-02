<?php

namespace App\Utils; // Namespace مطابق با پوشه src/Utils

use PDO; // برای Type Hinting در متدهای دیتابیس (که بهتر است به Repository ها منتقل شوند)
use PDOException; // برای گرفتن خطاهای دیتابیس
use Monolog\Logger; // برای استفاده از لاگر تزریق شده
use Exception; // برای خطاهای عمومی
use NumberFormatter; // برای فرمت اعداد با intl
use DateTime; // برای کار با تاریخ
use App\Core\CSRFProtector; // برای کار با توکن CSRF

/**
 * کلاس Helper برای توابع کمکی عمومی.
 * توابع سراسری از functions.php به متدهای static این کلاس منتقل می شوند.
 * وابستگی ها (مانند Logger) از طریق متدهای static (یا در آینده از طریق DI برای متدهای غیر static) تزریق می شوند.
 */
class Helper {

    // نمونه Logger برای استفاده در متدهای static
    private static ?Logger $logger = null;
    private static ?array $config = null; // برای دسترسی به تنظیمات در متدهای static
    private static ?array $messages = null; // برای دسترسی به آرایه پیام‌ها

    /**
     * متد برای تنظیم لاگر و تنظیمات در کلاس Helper.
     * این متد باید یک بار در ابتدای public/index.php فراخوانی شود.
     *
     * @param Logger $logger نمونه Monolog Logger.
     * @param array $config آرایه کامل تنظیمات برنامه.
     */
    public static function initialize(Logger $logger, array $config): void {
        self::$logger = $logger;
        self::$config = $config;

        // بارگذاری فایل پیام‌ها
        $messagesFilePath = realpath(__DIR__ . '/../../config/messages.php');
        if ($messagesFilePath && file_exists($messagesFilePath)) {
            self::$messages = require $messagesFilePath;
        } else {
            // اگر فایل پیام‌ها پیدا نشد، یک لاگ خطا ثبت کنید
            self::logError('Messages file not found.', ['path' => $messagesFilePath ?? 'config/messages.php']);
            self::$messages = []; // آرایه خالی برای جلوگیری از خطا
        }
    }


    /**
     * ثبت فعالیت در سیستم.
     * منطق لاگ‌برداری از توابع log_activity در functions.php و logger.php به این متد منتقل شده.
     * از لاگر Monolog تزریق شده استفاده می‌کند.
     * ذخیره در دیتابیس نیز در اینجا انجام می‌شود (اگرچه بهتر است به یک Repository اختصاصی منتقل شود).
     *
     * @param PDO|null $db نمونه PDO متصل به دیتابیس (نیاز به تزریق در متد static).
     * @param string $message پیام لاگ.
     * @param string $actionType نوع عملیات (مانند LOGIN, UPDATE_PROFILE, BACKUP).
     * @param string $level سطح لاگ (مانند INFO, WARNING, ERROR).
     * @param array $data داده‌های اضافی.
     */
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

    /**
     * Securely escapes HTML special characters.
     * منطق از escape_html در functions.php منتقل شده.
     *
     * @param string|null $string Input string.
     * @return string Escaped string.
     */
    public static function escapeHtml(?string $string): string {
        if ($string === null) return '';
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

     /**
      * Sanitizes a number string, removing formatting characters and ensuring validity.
      * Logic from sanitize_formatted_number in functions.php.
      *
      * @param string|null $numberStr Input number string (e.g., "1,234.50").
      * @return string Cleaned number string, or an empty string for invalid input. Returns null if input is null.
      */
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

       
     /**
      * Translates gold product type codes to human-readable Persian.
      * Logic from translate_product_type in functions.php.
      *
      * @param string|null $type Product type code.
      * @return string Persian translation or formatted code.
      */
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


     /**
      * Translates contact type codes to human-readable Persian.
      * Logic from get_contact_type_farsi in functions.php.
      *
      * @param string|null $type Contact type code.
      * @return string Persian translation or formatted code.
      */
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

    /**
     * **WARNING: N+1 Query Potential.**
     * Calculates the Rials balance for a specific contact using multiple queries.
     * Should be moved to a dedicated Repository (e.g., ContactRepository or LedgerRepository).
     * Only use for single contact detail views, NOT for lists.
     * Logic from calculate_contact_balance in functions.php.
     *
     * @param PDO $db PDO connection (needs to be passed as it's not a class dependency).
     * @param int $contactId Contact ID.
     * @return float Balance (positive: contact owes us / negative: we owe contact). Returns 0.0 on error.
     */
    public static function calculateContactBalance(?PDO $db, int $contactId): float {
        if (!$db) {
            self::logError("Cannot calculate contact balance: Database connection not provided.", ['contact_id' => $contactId]);
            return 0.0; // Return 0 or throw Exception? Returning 0 for now.
        }
        $balance = 0.0;
        try {
            // + Sales to contact
            $sql_sell = "SELECT SUM(total_value_rials) FROM transactions WHERE counterparty_contact_id = :id AND transaction_type = 'sell'";
            $stmt_sell = $db->prepare($sql_sell); $stmt_sell->bindValue(':id', $contactId, PDO::PARAM_INT); $stmt_sell->execute();
            $balance += (float)($stmt_sell->fetchColumn() ?: 0.0);

            // - Buys from contact
            $sql_buy = "SELECT SUM(total_value_rials) FROM transactions WHERE counterparty_contact_id = :id AND transaction_type = 'buy'";
            $stmt_buy = $db->prepare($sql_buy); $stmt_buy->bindValue(':id', $contactId, PDO::PARAM_INT); $stmt_buy->execute();
            $balance -= (float)($stmt_buy->fetchColumn() ?: 0.0);

            // + Payments We made to contact
            $sql_paid_to = "SELECT SUM(amount_rials) FROM payments WHERE receiving_contact_id = :id";
            $stmt_paid_to = $db->prepare($sql_paid_to); $stmt_paid_to->bindValue(':id', $contactId, PDO::PARAM_INT); $stmt_paid_to->execute();
            $balance += (float)($stmt_paid_to->fetchColumn() ?: 0.0);

            // - Payments He made to us
            $sql_paid_by = "SELECT SUM(amount_rials) FROM payments WHERE paying_contact_id = :id";
            $stmt_paid_by = $db->prepare($sql_paid_by); $stmt_paid_by->bindValue(':id', $contactId, PDO::PARAM_INT); $stmt_paid_by->execute();
            $balance -= (float)($stmt_paid_by->fetchColumn() ?: 0.0);

        } catch (PDOException $e) {
             self::logError("Database error calculating balance for contact ID {$contactId}: " . $e->getMessage(), ['exception' => $e]);
             return 0.0; // Return 0 on DB error
        } catch (Throwable $e) { // Catch any other errors
             self::logError("Unexpected error calculating balance for contact ID {$contactId}: " . $e->getMessage(), ['exception' => $e]);
             return 0.0;
        }
        return round($balance, 2);
    }

    /**
     * Converts a number to its Persian word representation.
     * Logic from convertNumberToWords in functions.php.
     *
     * @param int $number The number to convert.
     * @return string The word representation.
     */
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

     /**
      * Translates delivery status codes to human-readable Persian.
      * Logic from translate_delivery_status in functions.php.
      *
      * @param string|null $status Delivery status code.
      * @return string Persian translation or formatted code.
      */
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

     // --- Helpers for PHP < 8.0 ---
     // این توابع در PHP 8.1 و بالاتر داخلی هستند و نیازی به Polyfill ندارند
     // اما برای سازگاری با نسخه های کمی پایین تر PHP 8 می توان نگه داشت
     // بهتر است فقط برای PHP 8.0 و پایین تر Polyfill کنید.
     // با توجه به اینکه از 8.3 استفاده می کنید، نیازی به اینها نیست.
     /*
     public static function strStartsWith(string $haystack, string $needle): bool {
         return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
     }
     public static function strEndsWith(string $haystack, string $needle): bool {
          return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
     }
     */

     // --- توابع لاگینگ داخلی برای استفاده در خود کلاس Helper ---
     // اینها برای لاگ کردن خطاهای مربوط به توابع کمکی (مثل خطای تبدیل تاریخ یا فرمت عدد) استفاده می شوند.
     private static function logError(string $message, array $context = []): void {
         if (self::$logger) {
             self::$logger->error($message, $context);
         } else {
             error_log("Helper Error (Monolog not ready): " . $message);
         }
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

    // نکته: تابع check_permission() شامل منطق دسترسی بر اساس نقش کاربر است که بهتر است
    // به یک کلاس SecurityService یا AuthService اختصاصی منتقل شود.
    // تابع get_active_license() نیز تعامل مستقیم با دیتابیس دارد و باید به LicenseRepository منتقل شود.
    // تابع BASE_PATH() تکراری با ثابت ROOT_PATH در public/index.php است و باید حذف شود.
    // تابع load_view() تکراری با ViewRenderer است و باید حذف شود.

   /**
    * تولید داده‌های صفحه‌بندی برای نمایش در لیست‌ها
    * @param int $currentPage صفحه فعلی
    * @param int $totalPages تعداد کل صفحات
    * @param int $totalRecords تعداد کل رکوردها
    * @param int $itemsPerPage تعداد آیتم در هر صفحه
    * @return array
    */
   public static function generatePaginationData(int $currentPage, int $totalPages, int $totalRecords, int $itemsPerPage): array {
       return [
           'current_page' => $currentPage,
           'total_pages' => $totalPages,
           'total_records' => $totalRecords,
           'items_per_page' => $itemsPerPage,
           'has_prev' => $currentPage > 1,
           'has_next' => $currentPage < $totalPages,
           'prev_page' => ($currentPage > 1) ? $currentPage - 1 : null,
           'next_page' => ($currentPage < $totalPages) ? $currentPage + 1 : null,
       ];
   }

   /**
    * بازگرداندن وضعیت‌های مجاز تحویل برای فیلتر لیست معاملات
    * @return array
    */
   public static function getDeliveryStatusOptions(): array {
       return [
           'pending_receipt' => 'در انتظار دریافت',
           'pending_delivery' => 'در انتظار تحویل',
           'completed' => 'تکمیل شده',
           'cancelled' => 'لغو شده',
       ];
   }

   /**
    * بازگرداندن کلاس CSS مناسب برای وضعیت تحویل معامله
    * @param string|null $status
    * @return string
    */
   public static function getDeliveryStatusClass(?string $status): string {
       return match ($status) {
           'pending_receipt'  => 'badge bg-warning',
           'pending_delivery' => 'badge bg-info',
           'completed'        => 'badge bg-success',
           'cancelled'        => 'badge bg-danger',
           default            => 'badge bg-secondary',
       };
   }

   /**
    * ترجمه وضعیت لایسنس به فارسی
    * @param string|null $status
    * @return string
    */
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

   /**
    * بازگرداندن کلاس CSS مناسب برای وضعیت لایسنس
    * @param string|null $status
    * @return string
    */
   public static function getLicenseStatusClass(?string $status): string
   {
       $map = [
           'active'   => 'success',
           'expired'  => 'danger',
           'pending'  => 'warning',
           'revoked'  => 'secondary',
           'unknown'  => 'secondary',
       ];
       $status = strtolower((string)$status);
       return $map[$status] ?? 'secondary';
   }

   /**
    * تولید توکن CSRF جدید
     * 
     * @return string توکن تولید شده
     */
    public static function generateCsrfToken(): string {
        return CSRFProtector::generateToken();
    }
    
    /**
     * بررسی معتبر بودن توکن CSRF
     * 
     * @param string|null $token توکن دریافتی از کاربر
     * @return bool آیا توکن معتبر است
     */
    public static function verifyCsrfToken(?string $token): bool {
        if ($token === null) {
            return false;
        }
        return CSRFProtector::validateToken($token);
    }
    
    /**
     * تولید مجدد توکن CSRF
     * 
     * @return string توکن جدید
     */
    public static function regenerateCsrfToken(): string {
        CSRFProtector::removeToken();
        return CSRFProtector::generateToken();
    }

    /**
     * Retrieves message text by key from the loaded messages array.
     *
     * @param string $key The message key.
     * @param string|null $default Default value if key not found.
     * @return string The message text or default value.
     */
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

    /**
     * Recursively sanitize all numeric fields in an array (form data).
     * Only fields with keys matching numeric field patterns will be sanitized.
     *
     * @param array $data Input array (e.g. $_POST)
     * @param array|null $numericKeys Optional: list of numeric field names (if null, auto-detect by key name)
     * @return array Sanitized array
     */
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

    /**
     * فرمت مبلغ ریالی با جداکننده هزارگان و پسوند ریال
     * @param float|int|string $amount
     * @param bool $withSuffix
     * @return string
     */
    public static function formatRial($amount, $withSuffix = true)
    {
        $formatted = number_format((float)$amount, 0, '.', ',');
        return $withSuffix ? $formatted . ' ریال' : $formatted;
    }

    /**
     * فرمت کردن اعداد با تعداد رقم اعشار مشخص
     *
     * @param mixed $number عدد ورودی
     * @param int $decimals تعداد ارقام اعشار
     * @param string $decPoint نماد اعشار
     * @param string $thousandsSep نماد جداکننده هزارگان
     * @return string عدد فرمت شده
     */
    public static function formatNumber($number, int $decimals = 0, string $decPoint = '.', string $thousandsSep = ','): string
    {
        if ($number === null) {
            return '0';
        }
        
        // تبدیل به float برای اطمینان از عدد بودن
        $number = (float) $number;
        
        return number_format($number, $decimals, $decPoint, $thousandsSep);
    }
}