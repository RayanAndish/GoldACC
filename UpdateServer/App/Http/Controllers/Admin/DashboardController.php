<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\System;
use App\Models\Backup;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $customerCount = Customer::count();
        $systemCount = System::count();
        $backupCount = Backup::count();

        return view('admin.dashboard', compact('customerCount', 'systemCount', 'backupCount'));
    }
}