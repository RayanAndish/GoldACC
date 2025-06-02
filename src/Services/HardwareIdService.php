<?php

namespace App\Services;

class HardwareIdService {
    private static ?string $cachedHardwareId = null;

    public static function getHardwareId(): string {
        if (self::$cachedHardwareId === null) {
            $dataPoints = [
                'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? '',
                'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? '',
                'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? '',
                'SERVER_ADDR' => $_SERVER['SERVER_ADDR'] ?? '',
                'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? '',
                'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? '',
                'SERVER_PROTOCOL' => $_SERVER['SERVER_PROTOCOL'] ?? '',
                'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'HTTP_ACCEPT_LANGUAGE' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
                'HTTP_ACCEPT_ENCODING' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
                'HTTP_ACCEPT' => $_SERVER['HTTP_ACCEPT'] ?? '',
                'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? ''
            ];

            // مرتب‌سازی داده‌ها برای اطمینان از یکسان بودن ترتیب
            ksort($dataPoints);
            self::$cachedHardwareId = hash('sha256', json_encode($dataPoints));
        }

        return self::$cachedHardwareId;
    }
} 