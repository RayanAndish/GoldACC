<?php

namespace App\Controllers;

use App\Services\EmailService;
use Exception;
use PDO;
use Monolog\Logger;
use App\Core\ViewRenderer;

// FIX: ارث‌بری از AbstractController برای دسترسی به وابستگی‌ها و متدهای کمکی
class ApiController extends AbstractController
{
    private EmailService $emailService;

    /**
     * FIX: اضافه کردن سازنده (Constructor) برای تزریق وابستگی‌ها
     * این متد وابستگی‌های مورد نیاز را از کانتینر سرویس‌ها دریافت می‌کند.
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services
    ) {
        // فراخوانی سازنده والد برای مقداردهی اولیه تمام وابستگی‌های اصلی
        parent::__construct($db, $logger, $config, $viewRenderer, $services);

        // FIX: دریافت EmailService از کانتینر سرویس‌ها
        if (!isset($services['emailService']) || !$services['emailService'] instanceof EmailService) {
            // اگر سرویس مورد نیاز یافت نشد، یک استثنا ایجاد می‌کنیم تا از خطاهای بعدی جلوگیری شود
            throw new Exception('EmailService not found or invalid for ApiController.');
        }
        $this->emailService = $services['emailService'];
        $this->logger->debug("ApiController initialized correctly.");
    }


    /**
     * ارسال اطلاعات فعال‌سازی به پشتیبانی
     */
    public function sendActivationInfo()
    {
        try {
            // دریافت داده‌های JSON از بدنه درخواست
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (!$data) {
                throw new Exception('داده‌های ارسالی نامعتبر است.');
            }

            // اعتبارسنجی فیلدهای ضروری
            $requiredFields = ['domain', 'hardware_id', 'request_code'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("فیلد {$field} الزامی است.");
                }
            }
            
            // FIX: دسترسی صحیح به تنظیمات از طریق پراپرتی $this->config
            // این پراپرتی در AbstractController مقداردهی شده است.
            $supportEmail = $this->config['app']['support_email'] ?? 'accsupport@rar-co.ir';

            // FIX: استفاده از سرویس ایمیل که در سازنده تزریق شده است
            // متد sendActivationInfo در EmailService باید فقط آرایه داده‌ها را دریافت کند.
            $this->emailService->sendActivationInfo($data);

            $this->logger->info('اطلاعات فعال‌سازی با موفقیت به پشتیبانی ارسال شد.', [
                'domain' => $data['domain'],
                'hardware_id' => '...' . substr($data['hardware_id'], -12) // لاگ کردن بخش کوچکی از شناسه برای امنیت
            ]);
            
            // FIX: استفاده از متد jsonResponse که از AbstractController به ارث رسیده است
            $this->jsonResponse(['success' => true, 'message' => 'اطلاعات با موفقیت به پشتیبانی ارسال شد.']);

        } catch (Exception $e) {
            $this->logger->error('خطا در ارسال اطلاعات فعال‌سازی به پشتیبانی', [
                'error' => $e->getMessage(),
            ]);

            // FIX: استفاده از متد jsonResponse برای ارسال خطای استاندارد
            $this->jsonResponse([
                'success' => false,
                'message' => 'خطا در ارسال اطلاعات به پشتیبانی: ' . $e->getMessage()
            ], 500); // ارسال کد وضعیت 500 برای خطای سرور
        }
    }
}
