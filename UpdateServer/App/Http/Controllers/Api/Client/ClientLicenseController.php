<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// use App\Models\System; // گرفته شده از request attribute
use App\Models\License;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ClientLicenseController extends Controller
{
    public function getStatus(Request $request)
    {
        $authenticatedSystem = $request->attributes->get('authenticated_system');
        if (!$authenticatedSystem) {
            return response()->json(['error' => 'System not authenticated or not found.'], 403);
        }

        $license = License::where('system_id', $authenticatedSystem->id)->first();

        if (!$license) {
            return response()->json([
                'status' => 'unlicensed',
                'message' => 'No license is currently associated with this system.',
                'is_active' => false,
            ], 200); // تغییر به 200 با وضعیت unlicensed
        }

        // بررسی وضعیت لایسنس بر اساس فیلد status و تاریخ انقضا
        $isActive = false;
        $statusMessage = $license->status;

        if ($license->status === 'active') {
            if ($license->expires_at && Carbon::parse($license->expires_at)->isPast()) {
                $statusMessage = 'expired';
                $isActive = false;
            } else {
                $isActive = true;
            }
        } else {
            $isActive = false; // for pending, inactive, revoked, etc.
        }


        return response()->json([
            'license_key_display' => $license->license_key_display,
            'status'      => $statusMessage,
            'is_active'   => $isActive,
            'license_type'=> $license->license_type,
            'features'    => $license->features ? json_decode($license->features, true) : [],
            'expires_at'  => $license->expires_at ? Carbon::parse($license->expires_at)->toIso8601String() : null,
            'activated_at'=> $license->activated_at ? Carbon::parse($license->activated_at)->toIso8601String() : null,
        ]);
    }

    public function activate(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('License activation request received.', [
            'request_data' => $request->all(),
            'system_id' => $request->attributes->get('authenticated_system')?->id
        ]);

        $authenticatedSystem = $request->attributes->get('authenticated_system');
        if (!$authenticatedSystem) {
            \Illuminate\Support\Facades\Log::error('System not authenticated in license activation.');
            return response()->json(['error' => 'System not authenticated or not found.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'license_key_display' => 'required_without:license_key|string|max:50',
            'license_key' => 'required_without:license_key_display|string|max:50',
            'request_code' => 'required|string|max:64',
            'hardware_id' => 'required|string|max:64',
            'domain' => 'required|string|max:255',
            'server_nonce' => 'required|string|size:32'
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::warning('License activation validation failed.', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // بررسی اعتبار کد درخواست
            $requestCode = $request->input('request_code');
            $hardwareId = $request->input('hardware_id');
            $domain = $request->input('domain');
            $serverNonce = $request->input('server_nonce');

            // بررسی زمان اعتبار کد درخواست (5 دقیقه)
            $requestCodeTime = $authenticatedSystem->request_code_created_at;
            if (!$requestCodeTime || now()->diffInSeconds($requestCodeTime) > 300) {
                \Illuminate\Support\Facades\Log::warning('Request code expired.', [
                    'system_id' => $authenticatedSystem->id,
                    'request_code_time' => $requestCodeTime
                ]);
                return response()->json(['error' => 'Challenge expired'], 400);
            }

            // بررسی اعتبار کد درخواست
            $expectedCode = hash_hmac('sha256',
                $hardwareId . $domain . $authenticatedSystem->client_nonce . $serverNonce,
                $authenticatedSystem->salt
            );

            if (!hash_equals($expectedCode, $requestCode)) {
                \Illuminate\Support\Facades\Log::warning('Invalid request code.', [
                    'system_id' => $authenticatedSystem->id
                ]);
                return response()->json(['error' => 'Invalid request code'], 400);
            }

            $providedLicenseKeyDisplay = $request->input('license_key_display') ?? $request->input('license_key');
            \Illuminate\Support\Facades\Log::info('Looking up license.', [
                'license_key_display' => $providedLicenseKeyDisplay
            ]);

            $license = License::where('license_key_display', $providedLicenseKeyDisplay)->first();

            if (!$license) {
                \Illuminate\Support\Facades\Log::warning('License not found.', [
                    'license_key_display' => $providedLicenseKeyDisplay
                ]);
                return response()->json(['error' => 'Invalid license key provided.'], 400);
            }

            \Illuminate\Support\Facades\Log::info('License found.', [
                'license_id' => $license->id,
                'system_id' => $license->system_id,
                'status' => $license->status,
                'authenticated_system_id' => $authenticatedSystem->id,
                'hardware_id_hash_exists' => !empty($license->hardware_id_hash),
                'salt_exists' => !empty($license->salt),
                'request_code_hash_exists' => !empty($license->request_code_hash),
                'license_key_display' => $license->license_key_display
            ]);

            // بررسی اینکه لایسنس برای این سیستم است یا هنوز به هیچ سیستمی اختصاص داده نشده
            if ($license->system_id !== null && (string)$license->system_id !== (string)$authenticatedSystem->id) {
                \Illuminate\Support\Facades\Log::warning('License belongs to different system.', [
                    'license_system_id' => $license->system_id,
                    'request_system_id' => $authenticatedSystem->id,
                    'license_status' => $license->status,
                    'license_id' => $license->id
                ]);
                return response()->json(['error' => 'License key is associated with a different system.'], 409);
            }
            
            // اگر لایسنس pending است، آن را فعال کن
            if ($license->status === 'pending') {
                \Illuminate\Support\Facades\Log::info('Activating license.', [
                    'license_id' => $license->id,
                    'system_id' => $authenticatedSystem->id
                ]);

                $license->status = 'active';
                $license->system_id = $authenticatedSystem->id;
                $license->activated_at = Carbon::now();
                $license->save();

                \Illuminate\Support\Facades\Log::info('License activated successfully.', [
                    'license_id' => $license->id,
                    'system_id' => $authenticatedSystem->id
                ]);

                return response()->json([
                    'message' => 'License activated successfully for ' . $authenticatedSystem->name,
                    'license_status' => [
                        'license_key_display' => $license->license_key_display,
                        'status'      => 'active',
                        'is_active'   => true,
                        'expires_at'  => $license->expires_at ? Carbon::parse($license->expires_at)->toIso8601String() : null,
                        'activated_at'=> $license->activated_at->toIso8601String(),
                    ]
                ]);
            } else if ($license->system_id === $authenticatedSystem->id && $license->status === 'active') {
                \Illuminate\Support\Facades\Log::info('License already active.', [
                    'license_id' => $license->id,
                    'system_id' => $authenticatedSystem->id
                ]);
                return response()->json(['message' => 'License is already active for this system.'], 200);
            } else {
                \Illuminate\Support\Facades\Log::warning('License cannot be activated.', [
                    'license_id' => $license->id,
                    'system_id' => $license->system_id,
                    'status' => $license->status,
                    'authenticated_system_id' => $authenticatedSystem->id
                ]);
                return response()->json(['error' => 'License cannot be activated for this system or is not in a pending state for it.'], 400);
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error activating license.', [
                'system_id' => $authenticatedSystem->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Could not activate license due to a server error.'], 500);
        }
    }

    public function initiateActivation(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('License activation initiated.', [
            'domain' => $request->input('domain'),
            'ip' => $request->ip(),
            'ray_id' => $request->input('ray_id')
        ]);

        try {
            $validator = Validator::make($request->all(), [
                'domain' => 'required|string|max:255',
                'ray_id' => 'required|string|max:64'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'Invalid input data'], 422);
            }

            $domain = $request->input('domain');
            $rayId = $request->input('ray_id');
            $ip = $request->ip();

            // تولید salt‌های جدید
            $hardwareIdSalt = bin2hex(random_bytes(16));
            $activationSalt = bin2hex(random_bytes(16));

            // تولید nonce
            $serverNonce = bin2hex(random_bytes(16));

            // ذخیره اطلاعات در کش با زمان 5 دقیقه
            $cacheKey = 'activation_challenge_' . $rayId;
            $challengeData = [
                'domain' => $domain,
                'ip' => $ip,
                'hardware_id_salt' => $hardwareIdSalt,
                'activation_salt' => $activationSalt,
                'server_nonce' => $serverNonce,
                'created_at' => now()->timestamp
            ];

            \Cache::put($cacheKey, $challengeData, 300); // 5 minutes

            \Illuminate\Support\Facades\Log::info('Generated new salts.', [
                'hardware_id_salt' => $hardwareIdSalt,
                'activation_salt' => $activationSalt
            ]);

            return response()->json([
                'status' => 'success',
                'hardware_id_salt' => $hardwareIdSalt,
                'activation_salt' => $activationSalt,
                'server_nonce' => $serverNonce
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in initiateActivation.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function completeActivation(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Completing license activation.', [
            'domain' => $request->input('domain'),
            'hardware_id' => $request->input('hardware_id'),
            'ray_id' => $request->input('ray_id')
        ]);

        try {
            $validator = Validator::make($request->all(), [
                'domain' => 'required|string|max:255',
                'hardware_id' => 'required|string|max:64',
                'server_nonce' => 'required|string|size:32',
                'request_code' => 'required|string|max:64',
                'ray_id' => 'required|string|max:64',
                'license_key' => 'required|string|max:50'
            ]);

            if ($validator->fails()) {
                \Illuminate\Support\Facades\Log::warning('Validation failed in completeActivation.', [
                    'errors' => $validator->errors()->toArray()
                ]);
                return response()->json(['error' => 'Invalid input data'], 422);
            }

            $domain = $request->input('domain');
            $hardwareId = $request->input('hardware_id');
            $serverNonce = $request->input('server_nonce');
            $requestCode = $request->input('request_code');
            $rayId = $request->input('ray_id');
            $licenseKey = $request->input('license_key');

            // دریافت اطلاعات چالش از کش
            $cacheKey = 'activation_challenge_' . $rayId;
            $challengeData = \Cache::get($cacheKey);

            if (!$challengeData) {
                \Illuminate\Support\Facades\Log::warning('Challenge not found or expired.', [
                    'ray_id' => $rayId
                ]);
                return response()->json(['error' => 'Challenge not found or expired'], 400);
            }

            // بررسی زمان اعتبار چالش (5 دقیقه)
            if (now()->timestamp - $challengeData['created_at'] > 300) {
                \Illuminate\Support\Facades\Log::warning('Challenge expired.', [
                    'ray_id' => $rayId,
                    'created_at' => $challengeData['created_at']
                ]);
                return response()->json(['error' => 'Challenge expired'], 400);
            }

            // بررسی تطابق اطلاعات
            if ($challengeData['domain'] !== $domain) {
                \Illuminate\Support\Facades\Log::warning('Domain mismatch.', [
                    'challenge_domain' => $challengeData['domain'],
                    'request_domain' => $domain
                ]);
                return response()->json(['error' => 'Domain mismatch'], 400);
            }

            // بررسی اعتبار کد درخواست
            $expectedCode = hash_hmac('sha256',
                $hardwareId . $domain . $challengeData['server_nonce'] . $serverNonce,
                $challengeData['activation_salt']
            );

            if (!hash_equals($expectedCode, $requestCode)) {
                \Illuminate\Support\Facades\Log::warning('Invalid request code.', [
                    'ray_id' => $rayId
                ]);
                return response()->json(['error' => 'Invalid request code'], 400);
            }

            // بررسی اعتبار لایسنس
            $license = License::where('license_key', $licenseKey)
                ->where('status', 'active')
                ->first();

            if (!$license) {
                \Illuminate\Support\Facades\Log::warning('Invalid or inactive license.', [
                    'license_key' => substr($licenseKey, 0, 8) . '...'
                ]);
                return response()->json(['error' => 'Invalid or inactive license'], 400);
            }

            // بررسی تکراری نبودن لایسنس
            $existingActivation = \App\Models\LicenseActivation::where('license_id', $license->id)
                ->where('status', 'active')
                ->first();

            if ($existingActivation) {
                \Illuminate\Support\Facades\Log::warning('License already activated.', [
                    'license_id' => $license->id
                ]);
                return response()->json(['error' => 'License already activated'], 400);
            }

            // پیدا کردن یا ایجاد سیستم
            $system = \App\Models\System::firstOrCreate(
                ['hardware_id' => $hardwareId],
                [
                    'domain' => $domain,
                    'name' => 'System ' . substr($hardwareId, 0, 8),
                    'status' => 'active'
                ]
            );

            // فعال‌سازی لایسنس
            \DB::transaction(function () use ($system, $license) {
                // ایجاد رکورد فعال‌سازی
                \App\Models\LicenseActivation::create([
                    'license_id' => $license->id,
                    'system_id' => $system->id,
                    'activated_at' => now(),
                    'status' => 'active'
                ]);

                // به‌روزرسانی وضعیت سیستم
                $system->update([
                    'license_id' => $license->id,
                    'activation_status' => 'active',
                    'activated_at' => now()
                ]);

                // به‌روزرسانی وضعیت لایسنس
                $license->update([
                    'activation_count' => \DB::raw('activation_count + 1'),
                    'last_activated_at' => now(),
                    'activated_at' => now(),
                    'status' => 'active',
                    'is_active' => true
                ]);
            });

            // حذف چالش از کش
            \Cache::forget($cacheKey);

            \Illuminate\Support\Facades\Log::info('License activated successfully.', [
                'license_id' => $license->id,
                'system_id' => $system->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'License activated successfully',
                'data' => [
                    'activation_date' => now()->format('Y-m-d H:i:s'),
                    'expiry_date' => $license->expiry_date,
                    'features' => $license->features
                ]
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in completeActivation.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}