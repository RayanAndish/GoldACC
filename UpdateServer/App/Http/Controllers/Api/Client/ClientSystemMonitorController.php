<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// use App\Models\System; // گرفته شده از request attribute
use App\Models\Log; // استفاده از مدل Log شما
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\Version; // برای getVersionInfo

class ClientSystemMonitorController extends Controller
{
    public function recordHeartbeat(Request $request)
    {
        $authenticatedSystem = $request->attributes->get('authenticated_system');
        if (!$authenticatedSystem) {
            return response()->json(['error' => 'System not authenticated or not found.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'current_version' => 'required|string|max:50',
            'os_info'         => 'nullable|string|max:255',
            'php_version'     => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $authenticatedSystem->last_heartbeat_at = Carbon::now();
            $authenticatedSystem->current_version = $request->input('current_version');
            $authenticatedSystem->os_info = $request->input('os_info');
            $authenticatedSystem->php_version = $request->input('php_version');
            $authenticatedSystem->save();

            return response()->json([
                'message' => 'Heartbeat recorded successfully for ' . $authenticatedSystem->name,
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error recording heartbeat for system ID ' . $authenticatedSystem->id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Could not record heartbeat due to a server error.'], 500);
        }
    }

    public function logError(Request $request)
    {
        $authenticatedSystem = $request->attributes->get('authenticated_system');
        if (!$authenticatedSystem) {
            return response()->json(['error' => 'System not authenticated or not found.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'type'    => ['required', 'string', Rule::in(['critical', 'error', 'warning', 'info', 'debug'])], // استفاده از فیلد type اگر level را اضافه نکرده‌اید
            'message' => 'required|string|max:10000', // افزایش طول پیام
            'context' => 'nullable|array',
            'file_path'    => 'nullable|string|max:500', // تغییر نام از file
            'line_number'  => 'nullable|integer',    // تغییر نام از line
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            Log::create([ // استفاده از مدل Log
                'system_id'  => $authenticatedSystem->id,
                'type'       => $request->input('type'), // یا level اگر آن ستون را دارید
                'message'    => $request->input('message'),
                'context'    => $request->input('context', []),
                'file_path'  => $request->input('file_path'), // استفاده از نام ستون جدید
                'line_number'=> $request->input('line_number'), // استفاده از نام ستون جدید
                'ip_address' => $request->ip(), // ستون جدید
            ]);

            return response()->json(['message' => 'Error logged successfully.']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error logging client error for system ID ' . $authenticatedSystem->id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Could not log error due to a server error.'], 500);
        }
    }

    public function getVersionInfo(Request $request)
    {
        $authenticatedSystem = $request->attributes->get('authenticated_system');
        if (!$authenticatedSystem) {
            return response()->json(['error' => 'System not authenticated or not found.'], 403);
        }

        $latestVersionModel = Version::orderBy('release_date', 'desc')->first();
        $latestStableVersion = $latestVersionModel ? $latestVersionModel->version_code : 'N/A'; // از جدول versions

        return response()->json([
            'latest_stable_version' => $latestStableVersion,
            'client_current_version' => $authenticatedSystem->current_version ?? 'N/A',
            'update_available' => $latestStableVersion !== 'N/A' && version_compare($latestStableVersion, ($authenticatedSystem->current_version ?? '0.0.0'), '>'),
            'update_details' => $latestVersionModel ? [
                'version_code' => $latestVersionModel->version_code,
                'description'  => $latestVersionModel->description,
                'file_path'    => $latestVersionModel->file_path, // کلاینت می‌تواند از این برای دانلود استفاده کند
                'release_date' => $latestVersionModel->release_date ? $latestVersionModel->release_date->toIso8601String() : null,
            ] : null,
        ]);
    }
}