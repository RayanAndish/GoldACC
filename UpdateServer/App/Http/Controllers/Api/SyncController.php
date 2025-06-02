<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\License;
use App\Models\User;    // اگر قرار است کاربران یک سیستم خاص همگام شوند
use App\Models\Log;     // اگر قرار است لاگ‌های یک سیستم خاص همگام شوند
use App\Models\Setting; // اگر قرار است تنظیمات یک سیستم خاص همگام شوند
use App\Models\System;  // برای پیدا کردن سیستمی که درخواست را ارسال کرده

class SyncController extends Controller
{
    /**
     * توسط یک سامانه مشتری خاص برای ارسال داده‌هایش به سرور فراخوانی می‌شود.
     * میدل‌ور ApiSecurity قبلاً هویت سیستم را تأیید کرده است.
     */
    public function syncClientSystemData(Request $request)
    {
        // ۱. پیدا کردن سیستمی که درخواست را ارسال کرده است.
        // میدل‌ور ApiSecurity باید api_key را اعتبارسنجی کرده باشد.
        // شما می‌توانید System را از request دریافت کنید اگر میدل‌ور آن را اضافه کرده،
        // یا با استفاده از api_key از هدر، دوباره آن را query کنید.
        $apiKey = $request->header('X-API-KEY');
        $currentSystem = System::where('api_key', $apiKey)->first();

        if (!$currentSystem) {
            // این اتفاق نباید بیفتد اگر میدل‌ور به درستی کار کند
            return response()->json(['error' => 'System not authenticated'], 401);
        }

        // ۲. دریافت داده‌های ارسالی از کلاینت
        $clientData = $request->validate([
            'licenses' => 'sometimes|array',
            'users'    => 'sometimes|array',
            'logs'     => 'sometimes|array',
            'settings' => 'sometimes|array',
            // سایر داده‌های مورد انتظار از کلاینت
        ]);

        // ۳. پردازش و ذخیره داده‌های دریافت شده برای $currentSystem
        // مثال:
        if (isset($clientData['logs'])) {
            foreach ($clientData['logs'] as $logEntry) {
                // ذخیره لاگ با انتساب به $currentSystem->id
                // Log::create(['system_id' => $currentSystem->id, 'message' => $logEntry['message'], ...]);
            }
        }
        // ... پردازش سایر داده‌ها ...

        return response()->json([
            'message' => 'Data for system ' . $currentSystem->name . ' synced successfully.',
            'status_update' => ['new_version' => '1.2.3', 'message_from_server' => 'Update available!'] // مثال پاسخ
        ]);
    }

    /**
     * توسط ادمین/سیستم مرکزی برای دریافت خلاصه‌ای از وضعیت تمام سیستم‌ها فراخوانی می‌شود.
     * میدل‌ور ApiSecurity باید کلید API ادمین را تأیید کرده باشد.
     */
    public function getAllSystemsSummaryForAdmin(Request $request)
    {
        // در اینجا، ApiSecurity باید تأیید کرده باشد که درخواست از یک ادمین مجاز است.
        // (نیاز به منطقی در ApiSecurity برای تشخیص کلیدهای ادمین از کلیدهای مشتری)

        $summaryData = [
            'total_systems' => System::count(),
            'active_licenses' => License::where('is_active', true)->count(), // مثال
            // ... سایر داده‌های آماری و خلاصه ...
        ];

        return response()->json($summaryData);
    }
}