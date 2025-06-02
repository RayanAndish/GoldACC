<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\License;
use App\Models\System;
use App\Models\EncryptionKey;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Exception;
use Illuminate\Validation\ValidationException;
use Firebase\JWT\JWT;

class HandshakeController extends Controller
{
    private function normalizeDomain(string $domain): string {
        $domain = preg_replace('/^https?:\\/\\//i', '', $domain);
        $domain = preg_replace('/^www\\./i', '', $domain);
        return strtolower(trim($domain));
    }

    protected $challengeExpiryMinutes = 5;

    public function initiate(Request $request)
    {
        $validatedData = $request->validate([
            'domain' => 'required|string|max:255',
            'ip' => 'required|ip',
            'ray_id' => 'sometimes|string|max:255|nullable',
            'hardware_id' => 'required|string|max:255',
            'client_nonce' => 'required|string|size:32',
            'server_nonce' => 'required|string|size:32',
            'challenge' => 'required|string|size:128'
        ]);

        Log::info('Handshake initiated by client.', [
            'domain' => $validatedData['domain'],
            'ip' => $validatedData['ip'],
            'hardware_id' => $validatedData['hardware_id'],
            'ray_id' => $validatedData['ray_id'] ?? null
        ]);

        try {
            // 1. بازیابی اطلاعات از کش
            $challengeData = Cache::get("activation_challenge_{$validatedData['client_nonce']}");
            
            if (!$challengeData) {
                Log::warning('Challenge not found or expired.', [
                    'client_nonce' => $validatedData['client_nonce']
                ]);
                return response()->json(['error' => 'Challenge expired or invalid'], 400);
            }

            // 2. اعتبارسنجی challenge
            $expectedChallenge = $this->generateChallenge($validatedData['client_nonce'], $validatedData['server_nonce']);
            if (!hash_equals($expectedChallenge, $validatedData['challenge'])) {
                Log::warning('Invalid challenge.', [
                    'client_nonce' => $validatedData['client_nonce'],
                    'server_nonce' => $validatedData['server_nonce']
                ]);
                return response()->json(['error' => 'Invalid challenge'], 403);
            }

            // 3. اعتبارسنجی hardware_id
            if ($challengeData['hardware_id'] !== $validatedData['hardware_id']) {
                Log::warning('Hardware ID mismatch.', [
                    'expected' => $challengeData['hardware_id'],
                    'received' => $validatedData['hardware_id']
                ]);
                return response()->json(['error' => 'Hardware ID mismatch'], 403);
            }

            // 4. اعتبارسنجی domain
            $clientDomain = $this->normalizeDomain($validatedData['domain']);
            $storedDomain = $this->normalizeDomain($challengeData['domain']);
            if ($clientDomain !== $storedDomain) {
                Log::warning('Domain mismatch.', [
                    'expected' => $storedDomain,
                    'received' => $clientDomain
                ]);
                return response()->json(['error' => 'Domain mismatch'], 403);
            }

            // 5. حذف challenge از کش
            Cache::forget("activation_challenge_{$validatedData['client_nonce']}");

            // 6. تولید API keys و handshake string
            $apiKey = Str::random(40);
            $apiSecret = Str::random(60);
            $hmacSalt = Str::random(32);
            $handshakeString = $apiKey . $apiSecret . $hmacSalt;

            // 7. ذخیره در دیتابیس
            try {
                $system = System::create([
                    'hardware_id' => $validatedData['hardware_id'],
                    'domain' => $validatedData['domain'],
                    'ip_address' => $validatedData['ip'],
                    'status' => 'pending'
                ]);

                $encryptionKey = EncryptionKey::create([
                    'system_id' => $system->id,
                    'key_value' => $handshakeString,
                    'status' => 'active'
                ]);

                Log::info('System and encryption key created successfully.', [
                    'system_id' => $system->id
                ]);

            } catch (\Exception $e) {
                Log::error('Error creating system or encryption key.', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => 'Internal server error during system setup'], 500);
            }

            // 8. آماده‌سازی پاسخ
            $mapping = [
                'api_key' => [0, strlen($apiKey) - 1],
                'api_secret' => [strlen($apiKey), strlen($apiKey) + strlen($apiSecret) - 1],
                'hmac_salt' => [strlen($apiKey) + strlen($apiSecret), strlen($handshakeString) - 1],
            ];

            Log::info('Handshake successful.', [
                'system_id' => $system->id,
                'domain' => $validatedData['domain']
            ]);

            return response()->json([
                'message' => 'Handshake successful. System registered.',
                'system_id' => $system->id,
                'handshake_string' => $handshakeString,
                'handshake_map' => base64_encode(json_encode($mapping)),
                'expires_in' => config('api.security.handshake_token_ttl', 24) * 3600
            ]);

        } catch (\Exception $e) {
            Log::error('Error in handshake process.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error during handshake'], 500);
        }
    }

    public function initiateActivation(Request $request)
    {
        try {
            // 1. اعتبارسنجی داده‌های ورودی
            $validatedData = $request->validate([
                'domain' => 'required|string|max:255',
                'ip' => 'required|ip',
                'ray_id' => 'required|string|max:255'
            ]);

            // 2. Rate Limiting - محدودیت 5 درخواست در دقیقه
            $key = 'activation_requests_' . $validatedData['ip'];
            $maxAttempts = 5;
            $decayMinutes = 1;

            if (Cache::get($key, 0) >= $maxAttempts) {
                Log::warning('Rate limit exceeded for IP.', [
                    'ip' => $validatedData['ip'],
                    'domain' => $validatedData['domain']
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Too many requests. Please try again later.'
                ], 429);
            }

            // افزایش شمارنده درخواست‌ها
            Cache::add($key, 1, $decayMinutes * 60);
            Cache::increment($key);

            // 3. بررسی وجود دامنه در جدول systems
            $system = System::where('domain', $validatedData['domain'])->first();
            if (!$system) {
                Log::warning('Domain not found in systems table.', [
                    'domain' => $validatedData['domain']
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Domain not registered. Please contact support.'
                ], 403);
            }

            // 4. بررسی IP برای جلوگیری از DDOS
            $suspiciousIPs = Cache::get('suspicious_ips', []);
            if (in_array($validatedData['ip'], $suspiciousIPs)) {
                Log::warning('Suspicious IP detected.', [
                    'ip' => $validatedData['ip'],
                    'domain' => $validatedData['domain']
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied.'
                ], 403);
            }

            // 5. بررسی تعداد درخواست‌های ناموفق
            $failedAttempts = Cache::get('failed_attempts_' . $validatedData['ip'], 0);
            if ($failedAttempts >= 3) {
                $suspiciousIPs[] = $validatedData['ip'];
                Cache::put('suspicious_ips', $suspiciousIPs, now()->addHours(24));
                Log::warning('IP blocked due to multiple failed attempts.', [
                    'ip' => $validatedData['ip']
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied.'
                ], 403);
            }

            Log::info('License activation initiated.', [
                'domain' => $validatedData['domain'],
                'ip' => $validatedData['ip'],
                'ray_id' => $validatedData['ray_id']
            ]);

            // 6. تولید salt ها و nonce ها
            $clientNonceSalt = Str::random(32);
            $serverNonceSalt = Str::random(32);
            $requestCodeSalt = Str::random(32);

            // 7. تولید server_nonce
            $serverNonce = hash('sha256', $validatedData['domain'] . $serverNonceSalt);

            // 8. ذخیره اطلاعات در کش برای استفاده در مرحله بعدی
            $challengeData = [
                'domain' => $validatedData['domain'],
                'ip' => $validatedData['ip'],
                'customer_id' => $system->customer_id,
                'client_nonce_salt' => $clientNonceSalt,
                'server_nonce_salt' => $serverNonceSalt,
                'request_code_salt' => $requestCodeSalt,
                'created_at' => now()
            ];

            Cache::put(
                "activation_challenge_{$validatedData['ray_id']}", 
                $challengeData, 
                now()->addMinutes($this->challengeExpiryMinutes)
            );

            // 9. ارسال پاسخ به کلاینت
            return response()->json([
                'status' => 'success',
                'message' => 'Activation initiated successfully',
                'salt' => $clientNonceSalt,
                'server_nonce' => $serverNonce
            ]);

        } catch (ValidationException $e) {
            Log::warning('Validation failed in activation initiation.', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input data',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in activation initiation.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 500);
        }
    }

    public function completeActivation(Request $request)
    {
        try {
            // 1. اعتبارسنجی داده‌های ورودی
            $validatedData = $request->validate([
                'hardware_id' => 'required|string|max:64',
                'domain' => 'required|string|max:255',
                'ip' => 'required|ip',
                'ray_id' => 'required|string|max:255',
                'server_nonce' => 'required|string|size:64'
            ]);

            Log::info('Completing license activation.', [
                'domain' => $validatedData['domain'],
                'hardware_id' => $validatedData['hardware_id'],
                'ray_id' => $validatedData['ray_id']
            ]);

            // 2. بازیابی اطلاعات از کش
            $challengeData = Cache::get("activation_challenge_{$validatedData['ray_id']}");
            
            if (!$challengeData) {
                Log::warning('Challenge not found or expired.', [
                    'ray_id' => $validatedData['ray_id']
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Challenge expired or invalid'
                ], 400);
            }

            Log::info('Challenge data retrieved from cache.', [
                'ray_id' => $validatedData['ray_id'],
                'domain' => $challengeData['domain'],
                'customer_id' => $challengeData['customer_id']
            ]);

            // 3. اعتبارسنجی domain
            if ($validatedData['domain'] !== $challengeData['domain']) {
                Log::warning('Domain mismatch.', [
                    'expected' => $challengeData['domain'],
                    'received' => $validatedData['domain']
                ]);
                return response()->json(['error' => 'Domain mismatch'], 403);
            }

            // 4. تولید salt های جدید
            $hardwareIdSalt = Str::random(32);
            $activationSalt = Str::random(32);

            Log::info('Generated new salts.', [
                'hardware_id_salt' => $hardwareIdSalt,
                'activation_salt' => $activationSalt
            ]);

            // 5. بررسی وجود سیستم
            $system = System::where('domain', $validatedData['domain'])->first();

            if ($system) {
                Log::info('Updating existing system.', [
                    'system_id' => $system->id,
                    'domain' => $validatedData['domain']
                ]);
                // به‌روزرسانی سیستم موجود
                $system->update([
                    'hardware_id' => $validatedData['hardware_id'],
                    'customer_id' => $challengeData['customer_id'],
                    'name' => 'سیستم جدید',
                    'ip_address' => $validatedData['ip'],
                    'status' => 'pending',
                    'client_nonce' => $challengeData['client_nonce_salt'],
                    'server_nonce' => $validatedData['server_nonce'],
                    'client_nonce_salt' => $challengeData['client_nonce_salt'],
                    'server_nonce_salt' => $challengeData['server_nonce_salt'],
                    'request_code_salt' => $challengeData['request_code_salt'],
                    'hardware_id_salt' => $hardwareIdSalt,
                    'activation_salt' => $activationSalt
                ]);
            } else {
                Log::info('Creating new system.', [
                    'domain' => $validatedData['domain']
                ]);
                // ایجاد سیستم جدید
                $system = System::create([
                    'domain' => $validatedData['domain'],
                    'hardware_id' => $validatedData['hardware_id'],
                    'customer_id' => $challengeData['customer_id'],
                    'name' => 'سیستم جدید',
                    'ip_address' => $validatedData['ip'],
                    'status' => 'pending',
                    'client_nonce' => $challengeData['client_nonce_salt'],
                    'server_nonce' => $validatedData['server_nonce'],
                    'client_nonce_salt' => $challengeData['client_nonce_salt'],
                    'server_nonce_salt' => $challengeData['server_nonce_salt'],
                    'request_code_salt' => $challengeData['request_code_salt'],
                    'hardware_id_salt' => $hardwareIdSalt,
                    'activation_salt' => $activationSalt
                ]);
            }

            // 6. تولید request_code
            $requestCode = hash_hmac(
                'sha256',
                $validatedData['hardware_id'] . $validatedData['domain'] . $validatedData['server_nonce'],
                $challengeData['request_code_salt']
            );

            Log::info('Generated request code.', [
                'system_id' => $system->id,
                'request_code' => $requestCode
            ]);

            // 7. حذف challenge از کش
            Cache::forget("activation_challenge_{$validatedData['ray_id']}");
            Log::info('Challenge removed from cache.', [
                'ray_id' => $validatedData['ray_id']
            ]);

            // 8. ارسال پاسخ به کلاینت
            return response()->json([
                'status' => 'success',
                'message' => 'System registered successfully',
                'request_code' => $requestCode,
                'system_id' => $system->id
            ]);

        } catch (ValidationException $e) {
            Log::warning('Validation failed in activation completion.', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input data',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in activation completion.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 500);
        }
    }

    private function generateChallenge($clientNonce, $serverNonce)
    {
        return hash_hmac(
            'sha3-512',
            $clientNonce . $serverNonce,
            config('license.secret')
        );
    }

    private function validateRequestCode(License $license, string $requestCode): bool
    {
        // بررسی اعتبار request_code با استفاده از اطلاعات ذخیره شده
        $expectedCode = hash_hmac('sha256',
            $license->hardware_id . $license->domain . $license->client_nonce . $license->server_nonce,
            $license->salt
        );

        return hash_equals($expectedCode, $requestCode);
    }

    private function generateActivationToken(License $license): string
    {
        // تولید توکن فعال‌سازی با استفاده از اطلاعات لایسنس
        $payload = [
            'license_id' => $license->id,
            'hardware_id' => $license->hardware_id,
            'domain' => $license->domain,
            'expires_at' => $license->expires_at,
            'iat' => time(),
            'nbf' => time()
        ];

        return JWT::encode($payload, config('app.key'), 'HS256');
    }
}