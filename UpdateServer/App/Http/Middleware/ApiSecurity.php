<?php
// UpdateServer/App/Http/Middleware/ApiSecurity.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\EncryptionKey;
use App\Models\System; // اضافه کردن use برای مدل System
use Symfony\Component\HttpFoundation\Response;

class ApiSecurity
{
    protected int $timestampTolerance = 900; // 15 دقیقه
    protected string $nonceCachePrefix = 'api_nonce_';
    protected int $apiKeyLength = 40;
    protected int $apiSecretLength = 60;
    protected int $hmacSaltLength = 32;

    public function handle(Request $request, Closure $next): Response
    {
        Log::debug('API Security: Middleware handle method reached.', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'headers_subset' => [
                'x-api-key' => substr($request->header('X-API-KEY') ?? '', 0, 10) . '...',
                'x-system-id' => $request->header('X-SYSTEM-ID'),
                'x-timestamp' => $request->header('X-TIMESTAMP'),
                'x-nonce' => $request->header('X-NONCE'),
                'x-signature' => substr($request->header('X-SIGNATURE') ?? '', 0, 10) . '...',
            ]
        ]);

        $apiKeyFromHeader = $request->header('X-API-KEY');
        $signatureFromHeader = $request->header('X-SIGNATURE');
        $timestampFromHeader = $request->header('X-TIMESTAMP');
        $nonceFromHeader = $request->header('X-NONCE');
        $systemIdFromHeader = $request->header('X-SYSTEM-ID');

        // 1. بررسی وجود هدرهای لازم
        if (!$apiKeyFromHeader || !$signatureFromHeader || !$timestampFromHeader || !$nonceFromHeader || !$systemIdFromHeader) {
            Log::warning('API Security: Missing required headers.', [
                'missing_X-API-KEY' => !$apiKeyFromHeader,
                'missing_X-SIGNATURE' => !$signatureFromHeader,
                'missing_X-TIMESTAMP' => !$timestampFromHeader,
                'missing_X-NONCE' => !$nonceFromHeader,
                'missing_X-SYSTEM-ID' => !$systemIdFromHeader,
            ]);
            return response()->json(['error' => 'Missing required security headers.'], 400);
        }

        // 2. بررسی Timestamp
        $currentTimestamp = time();
        Log::debug('API Security: Timestamp Check Details', [
            'received_ts_header' => $timestampFromHeader,
            'server_ts_current' => $currentTimestamp,
            'difference_abs' => abs($currentTimestamp - (int)$timestampFromHeader),
            'tolerance' => $this->timestampTolerance,
            'is_within_tolerance' => (abs($currentTimestamp - (int)$timestampFromHeader) <= $this->timestampTolerance),
            'system_id' => $systemIdFromHeader,
        ]);
        if (!is_numeric($timestampFromHeader) || abs($currentTimestamp - (int)$timestampFromHeader) > $this->timestampTolerance) {
            Log::warning('API Security: Timestamp validation failed.', [
                'received_ts' => $timestampFromHeader,
                'server_ts' => $currentTimestamp,
                'tolerance' => $this->timestampTolerance,
                'system_id' => $systemIdFromHeader,
                'is_numeric' => is_numeric($timestampFromHeader)
            ]);
            return response()->json(['error' => 'Request timestamp out of tolerance or invalid.'], 401);
        }

        // 3. بررسی Nonce
        $nonceCacheKey = $this->nonceCachePrefix . $systemIdFromHeader . '_' . $nonceFromHeader; // اضافه کردن system_id به کلید برای اطمینان بیشتر
        if (!Cache::add($nonceCacheKey, true, $this->timestampTolerance + 120)) { // افزایش زمان کش به tolerance + 2 دقیقه
            Log::warning('API Security: Duplicate nonce detected (replay attack?).', [
                'nonce' => $nonceFromHeader,
                'system_id' => $systemIdFromHeader,
                'cache_key_tried' => $nonceCacheKey
            ]);
            return response()->json(['error' => 'Duplicate request detected (nonce reuse).'], 401);
        }

        // 4. پیدا کردن رکورد EncryptionKey بر اساس system_id
        // **مهم: Eager load کردن رابطه system برای جلوگیری از کوئری N+1 و اطمینان از دسترسی به آبجکت System**
        $encryptionKeyRecord = EncryptionKey::with('system')
                                        ->where('system_id', $systemIdFromHeader)
                                        ->first();

        if (!$encryptionKeyRecord) {
            Log::warning('API Security: EncryptionKey record NOT FOUND for system_id.', [
                'system_id_searched' => $systemIdFromHeader,
            ]);
            // این پیام خطا با پیامی که کلاینت دریافت می‌کند مطابقت دارد
            return response()->json(['error' => 'System not authenticated or not found.'], 403);
        }
        Log::debug('API Security: EncryptionKey record found.', [
            'encryption_key_id' => $encryptionKeyRecord->id,
            'key_system_id' => $encryptionKeyRecord->system_id,
            'key_value_present' => !empty($encryptionKeyRecord->key_value),
            'system_relation_loaded_on_key' => $encryptionKeyRecord->relationLoaded('system'),
            'related_system_object_from_key' => $encryptionKeyRecord->system ? "ID: {$encryptionKeyRecord->system->id}, Name: {$encryptionKeyRecord->system->name}" : "null"
        ]);

        if (empty($encryptionKeyRecord->key_value)) {
            Log::error('API Security: EncryptionKey record found, but its key_value is EMPTY.', [
                'system_id' => $systemIdFromHeader,
                'encryption_key_id' => $encryptionKeyRecord->id,
            ]);
             // این پیام خطا نیز با پیام کلاینت مطابقت دارد
            return response()->json(['error' => 'System not authenticated or not found.'], 403);
        }

        // 5. استخراج کلیدها از key_value
        $handshakeString = $encryptionKeyRecord->key_value;
        $expectedTotalLength = $this->apiKeyLength + $this->apiSecretLength + $this->hmacSaltLength;
        if (strlen($handshakeString) !== $expectedTotalLength) {
             Log::error('API Security: Stored key_value length mismatch for system.', [
                 'system_id' => $systemIdFromHeader, 'expected' => $expectedTotalLength, 'actual' => strlen($handshakeString),
             ]);
             return response()->json(['error' => 'Internal server configuration error regarding credential length.'], 500);
        }
        $apiKeyFromStorage = substr($handshakeString, 0, $this->apiKeyLength);
        $apiSecret = substr($handshakeString, $this->apiKeyLength, $this->apiSecretLength);
        $hmacSalt = substr($handshakeString, $this->apiKeyLength + $this->apiSecretLength);

        // 6. بررسی تطابق API Key
         if (!hash_equals($apiKeyFromStorage, $apiKeyFromHeader)) {
             Log::warning('API Security: API Key mismatch.', [
                 'system_id' => $systemIdFromHeader,
                 'header_key_prefix' => substr($apiKeyFromHeader, 0, 5),
                 'storage_key_should_start_with' => substr($apiKeyFromStorage, 0, 5),
             ]);
             return response()->json(['error' => 'Invalid credentials (API Key mismatch).'], 401);
         }

        // 7. محاسبه Signature مورد انتظار
        $requestBody = $request->getContent();
        $dataToSign = $requestBody . $nonceFromHeader . $timestampFromHeader;
        $keyForHmac = $apiSecret . $hmacSalt;
        $expectedSignature = hash_hmac('sha3-512', $dataToSign, $keyForHmac);

        // 8. مقایسه Signature ها
        if (!hash_equals($expectedSignature, $signatureFromHeader)) {
            Log::warning('API Security: Signature validation failed.', [
                'system_id' => $systemIdFromHeader,
                'expected_sig_prefix' => substr($expectedSignature, 0, 10),
                'received_sig_prefix' => substr($signatureFromHeader, 0, 10),
                'data_signed_preview' => substr($dataToSign, 0, 100) . '...'
            ]);
            return response()->json(['error' => 'Invalid request signature.'], 401);
        }

        // --- شروع بخش اضافه کردن authenticated_system ---
        // در این نقطه، $encryptionKeyRecord معتبر است و رابطه 'system' باید با Eager Loading لود شده باشد.
        $relatedSystem = $encryptionKeyRecord->system;

        if ($relatedSystem instanceof System) { // بررسی اینکه آیا واقعاً یک آبجکت از نوع System است
            $request->attributes->add(['authenticated_system' => $relatedSystem]);
            // همچنین ID را برای دسترسی آسان‌تر اضافه می‌کنیم، گرچه آبجکت کامل بهتر است
            $request->attributes->add(['authenticated_system_id' => $relatedSystem->id]);

            Log::info('API Security: Request validated AND Authenticated System object ADDED to request attributes successfully.', [
                'system_id_validated' => $systemIdFromHeader, // ID از هدر
                'system_id_added_from_object' => $relatedSystem->id, // ID از آبجکت اضافه شده
                'system_name_added' => $relatedSystem->name ?? 'N/A'
            ]);
        } else {
            Log::error('API Security: Request validated BUT Authenticated System object COULD NOT BE ADDED. "system" relationship on EncryptionKey is invalid or missing.', [
                'system_id_validated' => $systemIdFromHeader,
                'encryption_key_id' => $encryptionKeyRecord->id,
                'foreign_system_id_on_key' => $encryptionKeyRecord->system_id, // ID که کلید به آن اشاره می‌کند
                'system_relation_loaded' => $encryptionKeyRecord->relationLoaded('system'),
                'related_system_is_null_or_wrong_type' => is_null($relatedSystem) ? 'Null' : 'Wrong Type: ' . get_class($relatedSystem)
            ]);
            // با اینکه درخواست امنیتی معتبر است، چون نتوانستیم آبجکت سیستم را به کنترلر پاس دهیم،
            // کنترلر احتمالاً به خطا خواهد خورد. اینجا یک خطای 500 برمی‌گردانیم تا نشان دهیم
            // یک مشکل داخلی در تنظیمات سرور (رابطه بین جداول) وجود دارد.
            return response()->json(['error' => 'Internal server error: Could not associate validated request with a system entity.'], 500);
        }
        // --- پایان بخش اضافه کردن authenticated_system ---

        return $next($request);
    }
}