<?php

namespace App\Core;

/**
 * کلاس محافظ CSRF برای تأمین امنیت فرم‌ها در برابر حملات Cross-Site Request Forgery
 */
class CSRFProtector {
    /**
     * تولید یک توکن CSRF جدید و ذخیره آن در نشست
     * 
     * @return string توکن تولید شده
     */
    public static function generateToken(): string {
        // شروع نشست اگر شروع نشده باشد
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // تولید توکن تصادفی
        $token = bin2hex(random_bytes(32));
        
        // ذخیره در نشست
        $_SESSION['csrf_token'] = $token;
        
        return $token;
    }
    
    /**
     * بررسی معتبر بودن توکن CSRF ارسال شده
     * 
     * @param string|null $token توکن دریافتی از کاربر
     * @return bool آیا توکن معتبر است
     */
    public static function validateToken(?string $token): bool {
        // شروع نشست اگر شروع نشده باشد
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // بررسی وجود توکن و صحت آن
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        
        // مقایسه توکن‌ها با روش مقایسه زمان-ثابت برای جلوگیری از حملات timing
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * حذف توکن CSRF فعلی (معمولاً پس از پردازش فرم)
     */
    public static function removeToken(): void {
        // شروع نشست اگر شروع نشده باشد
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // حذف توکن از نشست
        unset($_SESSION['csrf_token']);
    }
} 