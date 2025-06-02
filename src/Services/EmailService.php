<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Monolog\Logger;

class EmailService {
    private PHPMailer $mailer;
    private Logger $logger;
    private array $config;

    public function __construct(Logger $logger, array $config) {
        $this->logger = $logger;
        $this->config = $config;
        $this->initializeMailer();
    }

    private function initializeMailer(): void {
        $this->mailer = new PHPMailer(true);

        try {
            // تنظیمات SMTP هاست
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['mail']['host'] ?? 'localhost';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['mail']['username'] ?? '';
            $this->mailer->Password = $this->config['mail']['password'] ?? '';
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $this->config['mail']['port'] ?? 587;
            $this->mailer->CharSet = 'UTF-8';

            // تنظیم فرستنده پیش‌فرض
            $this->mailer->setFrom(
                $this->config['mail']['from_address'] ?? 'noreply@' . $_SERVER['HTTP_HOST'],
                $this->config['mail']['from_name'] ?? 'سیستم فعال‌سازی'
            );
        } catch (Exception $e) {
            $this->logger->error('خطا در تنظیمات SMTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * ارسال ایمیل اطلاعات فعال‌سازی به پشتیبانی
     */
    public function sendActivationInfo(array $activationData): bool {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($this->config['mail']['support_email'] ?? 'support@' . $_SERVER['HTTP_HOST']);
            $this->mailer->Subject = 'اطلاعات فعال‌سازی سامانه - ' . $activationData['domain'];

            // ایجاد متن ایمیل با فرمت استاندارد
            $body = $this->generateActivationEmailBody($activationData);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            $this->mailer->send();
            $this->logger->info('ایمیل اطلاعات فعال‌سازی با موفقیت ارسال شد', [
                'domain' => $activationData['domain'],
                'hardware_id' => substr($activationData['hardware_id'], 0, 10) . '...'
            ]);
            return true;

        } catch (Exception $e) {
            $this->logger->error('خطا در ارسال ایمیل اطلاعات فعال‌سازی', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'activation_data' => array_merge(
                    $activationData,
                    ['hardware_id' => substr($activationData['hardware_id'], 0, 10) . '...']
                )
            ]);
            return false;
        }
    }

    /**
     * تولید متن ایمیل با فرمت استاندارد
     */
    private function generateActivationEmailBody(array $data): string {
        $html = '<html dir="rtl" lang="fa"><body style="font-family: Tahoma, Arial, sans-serif;">';
        $html .= '<h2 style="color: #17a2b8;">اطلاعات فعال‌سازی سامانه</h2>';
        $html .= '<div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">';
        
        // اطلاعات اصلی
        $html .= '<h3 style="color: #343a40; margin-bottom: 15px;">اطلاعات سیستم</h3>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><td style="padding: 8px; border: 1px solid #dee2e6;"><strong>دامنه/آدرس:</strong></td>';
        $html .= '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($data['domain']) . '</td></tr>';
        
        $html .= '<tr><td style="padding: 8px; border: 1px solid #dee2e6;"><strong>شناسه سخت‌افزاری:</strong></td>';
        $html .= '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($data['hardware_id']) . '</td></tr>';
        
        $html .= '<tr><td style="padding: 8px; border: 1px solid #dee2e6;"><strong>کد درخواست:</strong></td>';
        $html .= '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($data['request_code']) . '</td></tr>';
        
        // اطلاعات اضافی
        if (!empty($data['additional_info'])) {
            $html .= '<tr><td style="padding: 8px; border: 1px solid #dee2e6;"><strong>اطلاعات تکمیلی:</strong></td>';
            $html .= '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($data['additional_info']) . '</td></tr>';
        }
        
        $html .= '</table>';
        
        // اطلاعات زمان
        $html .= '<div style="margin-top: 20px; color: #6c757d; font-size: 0.9em;">';
        $html .= '<p>زمان درخواست: ' . date('Y-m-d H:i:s') . '</p>';
        $html .= '</div>';
        
        // راهنمای پشتیبانی
        $html .= '<div style="margin-top: 20px; padding: 15px; background-color: #e9ecef; border-radius: 5px;">';
        $html .= '<h4 style="color: #495057; margin-bottom: 10px;">راهنمای پشتیبانی</h4>';
        $html .= '<ol style="margin: 0; padding-right: 20px;">';
        $html .= '<li>اطلاعات ارسالی را بررسی کنید</li>';
        $html .= '<li>در صورت صحت اطلاعات، کلید لایسنس مناسب را تولید کنید</li>';
        $html .= '<li>کلید لایسنس را به کاربر ارسال کنید</li>';
        $html .= '</ol>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '<div style="color: #6c757d; font-size: 0.8em; text-align: center; margin-top: 20px;">';
        $html .= '<p>این ایمیل به صورت خودکار توسط سیستم فعال‌سازی ارسال شده است.</p>';
        $html .= '</div>';
        $html .= '</body></html>';

        return $html;
    }
} 