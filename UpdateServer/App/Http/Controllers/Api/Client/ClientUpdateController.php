<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator; // For request validation
// use App\Models\SoftwareVersion; // مثال: مدلی برای نگهداری اطلاعات نسخه‌ها
// use App\Models\ClientSystem; // مثال: مدلی برای سیستم‌های کلاینت

class ClientUpdateController extends Controller
{
    /**
     * کلاینت نسخه فعلی خود و سایر اطلاعات سیستم را گزارش می‌دهد.
     * این اطلاعات می‌تواند برای نمایش در پنل ادمین و تصمیم‌گیری برای آپدیت استفاده شود.
     */
    // این متد در فایل مسیرها به ClientSystemMonitorController@reportVersionInfo اشاره داشت،
    // اما اینجا آن را به ClientUpdateController منتقل می‌کنیم برای سازماندهی بهتر.
    // اگر می‌خواهید در ClientSystemMonitorController بماند، این متد را به آنجا منتقل کنید.
    // مسیر در api.php: Route::post('system/report-version', [ClientSystemMonitorController::class, 'reportVersionInfo'])
    // اگر به این کنترلر منتقل می‌شود، مسیر هم باید اصلاح شود:
    // Route::post('system/report-version', [ClientUpdateController::class, 'reportCurrentVersion'])->name('system.report_version');
    public function reportCurrentVersion(Request $request)
    {
        $systemId = $request->attributes->get('authenticated_system_id'); // از میدل‌ور ApiSecurity
        if (!$systemId) {
            Log::warning('ClientUpdateController.reportCurrentVersion: Missing authenticated_system_id');
            return response()->json(['success' => false, 'message' => 'System authentication failed.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'client_version' => 'required|string|max:50',
            'os_name' => 'nullable|string|max:100',
            'os_version' => 'nullable|string|max:50',
            // سایر اطلاعات سیستمی که مایل به دریافت آن هستید
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid input.', 'errors' => $validator->errors()], 422);
        }

        try {
            Log::info('Client reported version info', [
                'system_id' => $systemId,
                'client_version' => $request->input('client_version'),
                'os_name' => $request->input('os_name'),
                'os_version' => $request->input('os_version'),
            ]);

            // TODO: منطق ذخیره اطلاعات نسخه کلاینت در دیتابیس
            // مثال:
            // $clientSystem = ClientSystem::find($systemId);
            // if ($clientSystem) {
            //     $clientSystem->client_version = $request->input('client_version');
            //     $clientSystem->os_name = $request->input('os_name');
            //     $clientSystem->os_version = $request->input('os_version');
            //     $clientSystem->last_reported_at = now();
            //     $clientSystem->save();
            // }

            return response()->json(['success' => true, 'message' => 'Version information received.']);

        } catch (\Exception $e) {
            Log::error('ClientUpdateController.reportCurrentVersion: Exception', [
                'system_id' => $systemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'An internal server error occurred.'], 500);
        }
    }

    /**
     * کلاینت برای بررسی وجود نسخه جدیدتر، درخواست ارسال می‌کند.
     * سرور باید آخرین نسخه موجود و اطلاعات آن را برگرداند.
     */
    public function checkForUpdate(Request $request)
    {
        $systemId = $request->attributes->get('authenticated_system_id');
        if (!$systemId) {
            Log::warning('ClientUpdateController.checkForUpdate: Missing authenticated_system_id');
            return response()->json(['success' => false, 'message' => 'System authentication failed.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_version' => 'required|string|max:50', // نسخه فعلی کلاینت
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid input.', 'errors' => $validator->errors()], 422);
        }

        $clientCurrentVersion = $request->input('current_version');

        try {
            Log::info('Client checking for update', ['system_id' => $systemId, 'current_version' => $clientCurrentVersion]);

            // TODO: منطق بررسی آخرین نسخه در دیتابیس و مقایسه با نسخه کلاینت
            // $latestVersion = SoftwareVersion::orderBy('release_date', 'desc')->first();
            $latestVersion = null; // مقدار نمونه
            // مثال ساده برای نمونه (شما باید از دیتابیس بخوانید)
            $mockLatestVersion = '1.2.0';
            $mockReleaseNotes = 'Bug fixes and performance improvements.';
            $mockDownloadUrl = 'https://example.com/updates/app-1.2.0.zip'; // یا یک مکانیزم دانلود دیگر

            // if (!$latestVersion) {
            //     return response()->json(['success' => true, 'update_available' => false, 'message' => 'No version information found on server.']);
            // }

            // if (version_compare($clientCurrentVersion, $latestVersion->version_number, '<')) {
            if (version_compare($clientCurrentVersion, $mockLatestVersion, '<')) { // استفاده از نسخه نمونه
                return response()->json([
                    'success' => true,
                    'update_available' => true,
                    // 'latest_version' => $latestVersion->version_number,
                    // 'release_date' => $latestVersion->release_date->toDateString(),
                    // 'release_notes' => $latestVersion->release_notes,
                    // 'download_url' => $latestVersion->download_url, // یا اطلاعات دیگر برای دانلود
                    'latest_version' => $mockLatestVersion,
                    'release_notes' => $mockReleaseNotes,
                    'download_url' => $mockDownloadUrl,
                ]);
            } else {
                return response()->json(['success' => true, 'update_available' => false, 'message' => 'You are using the latest version.']);
            }

        } catch (\Exception $e) {
            Log::error('ClientUpdateController.checkForUpdate: Exception', [
                'system_id' => $systemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'An internal server error occurred while checking for updates.'], 500);
        }
    }

    /**
     * (اختیاری) مسیری برای دانلود مستقیم فایل آپدیت.
     * این مسیر ممکن است نیاز به محافظت بیشتری داشته باشد (مثلاً توکن یکبار مصرف).
     */
    public function downloadUpdate(Request $request, $version)
    {
        $systemId = $request->attributes->get('authenticated_system_id');
        if (!$systemId) {
            Log::warning('ClientUpdateController.downloadUpdate: Missing authenticated_system_id');
            return response()->json(['success' => false, 'message' => 'System authentication failed.'], 401);
        }

        Log::info('Client requesting update download', ['system_id' => $systemId, 'version' => $version]);

        // TODO: منطق پیدا کردن فایل آپدیت بر اساس نسخه و ارسال آن به کلاینت
        // $filePath = storage_path('app/updates/app-' . $version . '.zip');
        // if (file_exists($filePath)) {
        //     return response()->download($filePath);
        // } else {
        //     return response()->json(['success' => false, 'message' => 'Update file not found.'], 404);
        // }

        return response()->json(['success' => false, 'message' => 'Download functionality not implemented yet.'], 501);
    }
} 