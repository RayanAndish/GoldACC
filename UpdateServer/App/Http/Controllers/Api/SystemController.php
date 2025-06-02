<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\System;
use Illuminate\Http\Request; // اضافه شد

class SystemController extends Controller
{
    /**
     * Display the specified system.
     * This endpoint should be protected by an admin-specific API authentication middleware.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param int $id The ID of the system.
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, int $id)
    {
        // TODO: Implement Admin API Authentication Middleware.
        // Example: if (!auth('admin_api_guard')->check() || !auth('admin_api_guard')->user()->can('view_systems')) {
        // return response()->json(['error' => 'Unauthorized or Forbidden'], 401);
        // }

        $system = System::with([
                            'customer', // مشتری مربوط به این سیستم
                            'licenses' => function ($query) { // لایسنس‌های این سیستم
                                $query->orderBy('created_at', 'desc');
                            },
                            'encryptionKeysActive' => function ($query) { // فقط کلیدهای فعال
                                $query->where('status', 'active');
                            },
                            'backups' => function ($query) { // 5 بکاپ آخر
                                $query->orderBy('created_at', 'desc')->limit(5);
                            },
                            'logs' => function ($query) { // 10 لاگ آخر سیستم
                                $query->orderBy('created_at', 'desc')->limit(10);
                            }
                        ])
                        ->find($id);

        if (!$system) {
            return response()->json(['error' => 'سامانه یافت نشد.'], 404);
        }

        // شما می‌توانید داده‌ها را قبل از ارسال، به فرمت دلخواه خود تبدیل کنید
        // مثلاً اطلاعات حساس را حذف کنید یا داده‌های بیشتری اضافه نمایید.
        return response()->json($system);
    }

    /**
     * Display a listing of the systems.
     * This endpoint should be protected by an admin-specific API authentication middleware.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // TODO: Implement Admin API Authentication Middleware.
        // Example: if (!auth('admin_api_guard')->check() || !auth('admin_api_guard')->user()->can('list_systems')) {
        // return response()->json(['error' => 'Unauthorized or Forbidden'], 401);
        // }

        // TODO: Add pagination, filtering, and sorting capabilities.
        $systems = System::with('customer:id,name', 'licenses:id,system_id,status,expires_at') // فقط فیلدهای ضروری برای لیست
                         ->orderBy('created_at', 'desc')
                         ->paginate(15); // مثال صفحه‌بندی

        return response()->json($systems);
    }

    // TODO: متدهای store, update, destroy برای مدیریت کامل سیستم‌ها توسط ادمین از طریق API
    // این متدها نیز باید با احراز هویت و اعتبارسنجی مناسب ادمین محافظت شوند.
    // public function store(Request $request) { ... }
    // public function update(Request $request, System $system) { ... } // Route model binding
    // public function destroy(Request $request, System $system) { ... }
}
