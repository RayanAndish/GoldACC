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
        if (empty(trim($jalaliDatetime))) {
            return null;
        }
        try {
            // **FINAL FIX:** Handle both date and datetime formats robustly
            $normalized = str_replace('-', '/', trim($jalaliDatetime));
            
            // Check if time part exists
            if (strpos($normalized, ' ') !== false) {
                // It's a datetime
                $format = (substr_count($normalized, ':') == 2) ? 'Y/m/d H:i:s' : 'Y/m/d H:i';
                return Jalalian::fromFormat($format, $normalized)->toCarbon()->toDateTimeString();
            } else {
                // It's only a date, convert it and append start of day time
                return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon()->startOfDay()->toDateTimeString();
            }
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
        if (strtolower(trim($gregorianDateTime)) === 'now') {
            $persianDate = Jalalian::now()->format($format);
        } else {
            $dt = new DateTime($gregorianDateTime);
            $persianDate = Jalalian::fromDateTime($dt)->format($format);
        }

        // تبدیل اعداد به فارسی
        return self::formatPersianNumber($persianDate);

    } catch (Exception $e) {
        self::$logger?->warning('Failed to format Gregorian datetime to Persian.', [
            'datetime' => $gregorianDateTime,
            'error'    => $e->getMessage()
        ]);
        return '';
    }
}

    /**
     * Converts a standard MySQL datetime string to a Persian (Jalali) date string.
     *
     * @param string|null $dateString The input date string (e.g., '2023-10-27 15:04:00').
     * @param bool $includeTime Whether to include the time in the output.
     * @return string The formatted Persian date string, or an empty string if input is invalid.
     */
    public static function formatPersianDate(?string $dateString, bool $includeTime = false): string
    {
        if (empty($dateString)) {
            return '';
        }
        try {
            $jalalian = Jalalian::fromDateTime($dateString);
            $format = 'Y/m/d';
            if ($includeTime) {
                $format .= ' H:i';
            }
            return $jalalian->format($format);
        } catch (\Exception $e) {
            // Log or handle error if date format is invalid
            return ''; // Return empty for invalid dates
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

    /**
     * Sanitize formatted numeric string (e.g., "1,234,567.89") to float.
     */
    public static function sanitizeFormattedNumber(float|int|string|null $input): float|null
    {
        if ($input === null || $input === '') {
            return null;
        }
        // Remove both English and Persian thousands separators and potential decimal (like /) from Persian.
        $input = str_replace([',', '٬', '/'], '', (string)$input); 
        return is_numeric($input) ? (float)$input : null;
    }
    
    /**
     * Formats a large number (rials amount) into Persian thousand-separated format, no decimals.
     * Uses the enhanced formatPersianNumber.
     */
    public static function formatRial(float|int|string|null $amount): string
    {
        return self::formatPersianNumber($amount, 0); // Rials usually have no decimal places.
    }

    public static function formatNumber(float|int|string|null $number, int $decimals = 0): string
    {
        return self::formatPersianNumber($number, $decimals);
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

    
    /**
     * **NEW Method:** Converts a gold weight from one carat to another equivalent carat.
     * Essential for inventory tracking based on a reference carat (e.g., 750).
     * Formula: equivalent_weight = (weight * fromCarat) / toCarat
     * @param float $weight The weight in grams in the 'fromCarat'.
     * @param int $fromCarat The original carat of the gold.
     * @param int $toCarat The target carat (e.g., 750 for pure gold equivalent).
     * @return float The equivalent weight in the 'toCarat'. Returns 0.0 if toCarat is 0.
     */
    public static function convertGoldToCarat(float $weight, int $fromCarat, int $toCarat = 750): float
    {
        if ($toCarat <= 0) { // Prevent division by zero
            return 0.0;
        }
        if ($weight < 0) { // Don't allow negative weights in calculation.
             $weight = 0.0;
        }
        if ($fromCarat < 0) { // Treat invalid carat as zero, which makes output 0.
             $fromCarat = 0;
        }
        // Calculation should handle up to 4 decimal places for precision in weights.
        return round(($weight * $fromCarat) / $toCarat, 4);
    }

    /**
     * Formats a number into Persian numerals with group separators.
     *
     * @param float|int|string|null $number The number to format.
     * @param int $decimals The number of decimal points.
     * @return string The formatted Persian number.
     */
    /**
     * Converts a numeric value to Persian digits and applies thousand separators.
     * Optionally formats with specific decimal places.
     * @param float|int|string|null $number The input number.
     * @param int $decimals The number of decimal places to format.
     * @return string The formatted number in Persian digits. Returns '-' if input is null or empty.
     */
    public static function formatPersianNumber(float|int|string|null $number, int $decimals = 0): string
    {
        if ($number === null || $number === '') {
            return '-';
        }

        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', ','];
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٫', '٬']; 
        // '٫' برای اعشار فارسی و '٬' برای جداکننده هزار

        // اگر ورودی واقعاً عددی است
        if (is_numeric($number)) {
            $formatted = number_format((float)$number, $decimals, '.', ',');
            return str_replace($englishDigits, $persianDigits, $formatted);
        }

        // اگر رشته است (مثل تاریخ)
        return str_replace($englishDigits, $persianDigits, (string)$number);
    }
    /**
     * (جدید) نوع مخاطب را از انگلیسی به فارسی ترجمه می‌کند.
     */
    public static function getContactTypeFarsi(string $type): string
    {
        return match ($type) {
            'debtor' => 'بدهکار',
            'creditor_account' => 'بستانکار',
            'counterparty' => 'طرف حساب',
            'mixed' => 'ترکیبی',
            'other' => 'سایر',
            default => 'نامشخص',
        };
    }
     /**
     * (جدید) یک عدد را به معادل حروفی آن به زبان فارسی تبدیل می‌کند.
     * @param int $number عدد صحیح برای تبدیل
     * @return string رشته حروف فارسی
     */
    public static function convertNumberToWords(int $number): string
    {
        if ($number == 0) {
            return 'صفر';
        }

        $words = [
            'negative' => 'منفی ',
            'zero' => 'صفر',
            'one' => 'یک',
            'two' => 'دو',
            'three' => 'سه',
            'four' => 'چهار',
            'five' => 'پنج',
            'six' => 'شش',
            'seven' => 'هفت',
            'eight' => 'هشت',
            'nine' => 'نه',
            'ten' => 'ده',
            'eleven' => 'یازده',
            'twelve' => 'دوازده',
            'thirteen' => 'سیزده',
            'fourteen' => 'چهارده',
            'fifteen' => 'پانزده',
            'sixteen' => 'شانزده',
            'seventeen' => 'هفده',
            'eighteen' => 'هجده',
            'nineteen' => 'نوزده',
            'twenty' => 'بیست',
            'thirty' => 'سی',
            'forty' => 'چهل',
            'fifty' => 'پنجاه',
            'sixty' => 'شصت',
            'seventy' => 'هفتاد',
            'eighty' => 'هشتاد',
            'ninety' => 'نود',
            'hundred' => 'صد',
            'two_hundred' => 'دویست',
            'three_hundred' => 'سیصد',
            'four_hundred' => 'چهارصد',
            'five_hundred' => 'پانصد',
            'six_hundred' => 'ششصد',
            'seven_hundred' => 'هفتصد',
            'eight_hundred' => 'هشتصد',
            'nine_hundred' => 'نهصد',
            'thousand' => 'هزار',
            'million' => 'میلیون',
            'billion' => 'میلیارد',
            'trillion' => 'تریلیون',
            'quadrillion' => 'کوادریلیون',
            'quintillion' => 'کوینتیلیون',
            'separator' => ' و '
        ];

        if ($number < 0) {
            return $words['negative'] . self::convertNumberToWords(abs($number));
        }

        $string = '';

        if ($number < 20) {
            switch ($number) {
                case 1: $string = $words['one']; break;
                case 2: $string = $words['two']; break;
                case 3: $string = $words['three']; break;
                case 4: $string = $words['four']; break;
                case 5: $string = $words['five']; break;
                case 6: $string = $words['six']; break;
                case 7: $string = $words['seven']; break;
                case 8: $string = $words['eight']; break;
                case 9: $string = $words['nine']; break;
                case 10: $string = $words['ten']; break;
                case 11: $string = $words['eleven']; break;
                case 12: $string = $words['twelve']; break;
                case 13: $string = $words['thirteen']; break;
                case 14: $string = $words['fourteen']; break;
                case 15: $string = $words['fifteen']; break;
                case 16: $string = $words['sixteen']; break;
                case 17: $string = $words['seventeen']; break;
                case 18: $string = $words['eighteen']; break;
                case 19: $string = $words['nineteen']; break;
            }
        } elseif ($number < 100) {
            $tens = ((int)($number / 10)) * 10;
            $units = $number % 10;
            switch ($tens) {
                case 20: $string = $words['twenty']; break;
                case 30: $string = $words['thirty']; break;
                case 40: $string = $words['forty']; break;
                case 50: $string = $words['fifty']; break;
                case 60: $string = $words['sixty']; break;
                case 70: $string = $words['seventy']; break;
                case 80: $string = $words['eighty']; break;
                case 90: $string = $words['ninety']; break;
            }
            if ($units > 0) {
                $string .= $words['separator'] . self::convertNumberToWords($units);
            }
        } elseif ($number < 1000) {
            $hundreds = floor($number / 100) * 100;
            $remainder = $number % 100;
             switch ($hundreds) {
                case 100: $string = $words['hundred']; break;
                case 200: $string = $words['two_hundred']; break;
                case 300: $string = $words['three_hundred']; break;
                case 400: $string = $words['four_hundred']; break;
                case 500: $string = $words['five_hundred']; break;
                case 600: $string = $words['six_hundred']; break;
                case 700: $string = $words['seven_hundred']; break;
                case 800: $string = $words['eight_hundred']; break;
                case 900: $string = $words['nine_hundred']; break;
            }
            if ($remainder > 0) {
                $string .= $words['separator'] . self::convertNumberToWords($remainder);
            }
        } else {
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int)($number / $baseUnit);
            $remainder = $number % $baseUnit;
            
            $unitWord = '';
            switch ($baseUnit) {
                case 1000: $unitWord = $words['thousand']; break;
                case 1000000: $unitWord = $words['million']; break;
                case 1000000000: $unitWord = $words['billion']; break;
                case 1000000000000: $unitWord = $words['trillion']; break;
                case 1000000000000000: $unitWord = $words['quadrillion']; break;
                case 1000000000000000000: $unitWord = $words['quintillion']; break;
            }

            $string = self::convertNumberToWords($numBaseUnits) . ' ' . $unitWord;
            if ($remainder > 0) {
                $string .= $words['separator'] . self::convertNumberToWords($remainder);
            }
        }

        return $string;
    }
       /**
     * (جدید) کد نوع محصول (base_category) را به نام فارسی آن ترجمه می‌کند.
     * @param string|null $type کد نوع محصول مانند 'melted', 'manufactured'
     * @return string نام فارسی
     */
    public static function translateProductType(?string $type): string
    {
        if ($type === null) {
            return 'نامشخص';
        }

        // اطمینان از اینکه ورودی حروف کوچک است
        $type = strtolower($type);

        return match ($type) {
            'melted' => 'آبشده',
            'manufactured' => 'ساخته شده',
            'coin' => 'سکه',
            'bullion' => 'شمش',
            'jewelry' => 'جواهر',
            // افزودن سایر موارد در صورت نیاز
            default => 'کالای متفرقه',
        };
    }
}