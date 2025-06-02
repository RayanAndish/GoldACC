<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\System;
use App\Models\License;
use Illuminate\Support\Facades\Http; // برای ارسال درخواست HTTP
use Illuminate\Support\Facades\Log; // برای لاگ کردن خطاها
use Morilog\Jalali\Jalalian; // اضافه کردن این use
use Carbon\Carbon; // اضافه کردن این use (اگر برای پارس کردن تاریخ میلادی لازم باشد)


class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $customer = $user->customer; // با فرض اینکه رابطه customer در User model تعریف شده
        $systemsData = [];
        $latestVersionInfo = null;
        $errorMessage = null;

        if ($customer) {
            // واکشی تمام سیستم های مشتری به همراه لایسنس های فعال آنها
            // استفاده از with برای eager loading جهت بهینگی
            $systems = $customer->systems()->with(['licenses' => function ($query) {
                $query->where('status', 'active')->latest('activated_at')->limit(1);
            }])->get();

            foreach ($systems as $system) {
                $activeLicense = $system->licenses->first(); // چون با limit(1) واکشی کردیم، اولین آیتم همان لایسنس فعال است
                $systemsData[] = [
                    'system' => $system,
                    'activeLicense' => $activeLicense,
                    'client_version' => $system->current_version ?? 'N/A', // نسخه کلاینت از خود سیستم
                ];
            }
             Log::info('Dashboard data for user: ' . $user->id, ['customer_id' => $customer->id, 'systems_count' => count($systemsData)]);

        } else {
            Log::warning('No customer found for user: ' . $user->id);
            $errorMessage = 'اطلاعات مشتری برای این کاربر یافت نشد.';
        }

        // خواندن اطلاعات آخرین نسخه
        $manifestUrl = config('app.latest_version_manifest_url');
        $latestStableVersion = 'N/A'; // مقدار اولیه
        $latestVersionReleaseDateFormatted = 'N/A'; // مقدار اولیه

        if ($manifestUrl) {
            try {
                $response = Http::timeout(5)->get($manifestUrl);
                if ($response->successful()) {
                    $latestVersionInfo = $response->json();
                    if (!isset($latestVersionInfo['latest']['version'])) {
                         Log::error('latest.json is missing latest.version key.', ['url' => $manifestUrl, 'content' => $response->body()]);
                         $latestVersionInfo = null; // اطمینان از اینکه اطلاعات ناقص استفاده نشود
                    } else {
                        Log::info('Successfully fetched latest version manifest.', ['url' => $manifestUrl, 'data' => $latestVersionInfo]);
                        
                        // <<--- شروع تغییر برای تاریخ --- >>
                        $latestStableVersion = $latestVersionInfo['latest']['version'] ?? 'N/A';
                        $latestVersionReleaseDateRaw = $latestVersionInfo['latest']['release_date'] ?? null;

                        if ($latestVersionReleaseDateRaw) {
                            try {
                                $carbonDate = Carbon::parse($latestVersionReleaseDateRaw);
                                $latestVersionReleaseDateFormatted = Jalalian::fromCarbon($carbonDate)->format('Y/m/d');
                            } catch (\Exception $e) {
                                Log::error('Failed to parse latest version release date from manifest.', ['date_string' => $latestVersionReleaseDateRaw, 'error' => $e->getMessage()]);
                                // $latestVersionReleaseDateFormatted همچنان 'N/A' باقی می‌ماند
                            }
                        }
                        // <<--- پایان تغییر برای تاریخ --- >>
                    }
                } else {
                    Log::error('Failed to fetch latest version manifest.', [
                        'url' => $manifestUrl,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    $errorMessage = $errorMessage ? $errorMessage . ' و خطای دریافت اطلاعات آخرین نسخه.' : 'خطا در دریافت اطلاعات آخرین نسخه.';
                }
            } catch (\Exception $e) {
                Log::error('Exception while fetching latest version manifest.', [
                    'url' => $manifestUrl,
                    'error' => $e->getMessage()
                ]);
                 $errorMessage = $errorMessage ? $errorMessage . ' و خطای ارتباط برای دریافت آخرین نسخه.' : 'خطای ارتباط برای دریافت آخرین نسخه.';
            }
        } else {
            Log::warning('latest_version_manifest_url is not configured in config/app.php.');
            // $errorMessage = $errorMessage ? $errorMessage . ' و URL مانیفست نسخه تنظیم نشده است.' : 'URL مانیفست نسخه تنظیم نشده است.';
            // بهتر است این مورد را به کاربر نمایش ندهیم، فقط لاگ شود.
        }

        return view('dashboard', [ // یا مسیر view داشبورد کاربر شما
            'systemsData' => $systemsData,
            'latest_stable_version' => $latestStableVersion,
            'latest_version_release_date' => $latestVersionReleaseDateFormatted,
            'current_version_changelog' => $latestVersionInfo['current']['changelog'] ?? 'N/A', // اگر می خواهید changelog نسخه فعلی نصب شده را هم نمایش دهید (نیاز به منطق بیشتری دارد)
            'errorMessage' => $errorMessage,
        ]);
    }
}