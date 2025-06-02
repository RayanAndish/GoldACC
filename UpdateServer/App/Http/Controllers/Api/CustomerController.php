<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    // TODO: این مسیر باید با میدل‌ور احراز هویت ادمین API محافظت شود.
    public function show(Request $request, $id)
    {
        // if (!auth('admin_api_guard')->check()) {
        //     return response()->json(['error' => 'Unauthorized'], 401);
        // }

        $customer = Customer::with(['systems', 'user'])->find($id); // اضافه کردن user relationship
        if (!$customer) {
            return response()->json(['error' => 'مشتری یافت نشد.'], 404);
        }
        return response()->json($customer);
    }

    // TODO: متدهای index, store, update, destroy برای مدیریت مشتریان توسط ادمین از طریق API
}