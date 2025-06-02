<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SmsLog;
use App\Models\System;
use App\Models\SmsSetting;

class SmsController extends Controller
{
    public function send(Request $request)
    {
        $systemId = $request->input('system_id');
        $customerId = $request->input('customer_id');
        $toNumber = $request->input('to_number');
        $message = $request->input('message');

        // دریافت تنظیمات پیامک فعال
        $smsSetting = SmsSetting::where('system_id', $systemId)->where('is_active', true)->first();
        if (!$smsSetting) {
            return response()->json(['error' => 'تنظیمات پیامک فعال یافت نشد.'], 404);
        }

        // اینجا باید کد ارسال پیامک به سرویس‌دهنده (مثلاً Kavenegar) را قرار دهید
        // فرض: ارسال موفق و response فرضی
        $response = 'ارسال موفق (شبیه‌سازی شده)';

        // ثبت لاگ پیامک
        $smsLog = SmsLog::create([
            'system_id' => $systemId,
            'customer_id' => $customerId,
            'to_number' => $toNumber,
            'message' => $message,
            'status' => 'success',
            'response' => $response,
            'sent_at' => now(),
        ]);

        return response()->json(['success' => true, 'sms_log_id' => $smsLog->id]);
    }
}