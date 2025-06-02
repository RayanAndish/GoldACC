<?php

namespace App\Controllers;

use App\Services\EmailService;
use App\Services\Logger;
use Exception;

class ApiController
{
    /**
     * ارسال اطلاعات فعال‌سازی به پشتیبانی
     */
    public function sendActivationInfo()
    {
        try {
            // دریافت داده‌های JSON از درخواست
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (!$data) {
                throw new Exception('داده‌های ارسالی نامعتبر است.');
            }

            // بررسی وجود فیلدهای ضروری
            $requiredFields = ['domain', 'hardware_id', 'request_code'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("فیلد {$field} الزامی است.");
                }
            }

            // استفاده از ایمیل پشتیبانی پیش‌فرض از تنظیمات
            $supportEmail = $this->config['app']['support_email'] ?? 'accsupport@rar-co.ir';

            // ارسال ایمیل با استفاده از EmailService
            $emailService = new EmailService($this->logger);
            $emailService->sendActivationInfo(
                $supportEmail,
                $data['domain'],
                $data['hardware_id'],
                $data['request_code']
            );

            $this->logger->info('اطلاعات فعال‌سازی با موفقیت به پشتیبانی ارسال شد.', [
                'domain' => $data['domain'],
                'hardware_id' => $data['hardware_id']
            ]);

            return $this->jsonResponse(['success' => true, 'message' => 'اطلاعات با موفقیت به پشتیبانی ارسال شد.']);

        } catch (Exception $e) {
            $this->logger->error('خطا در ارسال اطلاعات فعال‌سازی به پشتیبانی', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'success' => false,
                'message' => 'خطا در ارسال اطلاعات به پشتیبانی: ' . $e->getMessage()
            ], 500);
        }
    }
} 