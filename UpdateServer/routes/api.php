<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\Api\HandshakeController;
use App\Http\Controllers\Api\Client\ClientLicenseController;
use App\Http\Controllers\Api\Client\ClientSystemMonitorController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\Client\ClientUpdateController;      // جدید
use App\Http\Controllers\Api\Client\ClientBackupController;       // جدید

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/handshake', [HandshakeController::class, 'initiate'])->name('api.public.handshake');

// مسیرهای جدید برای فرآیند فعال‌سازی لایسنس
Route::post('/license/initiate-activation', [HandshakeController::class, 'initiateActivation'])->name('api.public.license.initiate');
Route::post('/license/complete-activation', [HandshakeController::class, 'completeActivation'])->name('api.public.license.complete');

Route::middleware([\App\Http\Middleware\ApiSecurity::class]) // استفاده از نام کلاس برای گروه
->name('api.client.')
->group(function () {
        // License
        Route::post('license/activate', [ClientLicenseController::class, 'activate'])->name('license.activate');
        Route::get('license/status', [ClientLicenseController::class, 'getStatus'])->name('license.status');

        // System Monitoring & Version Reporting
        Route::post('system/heartbeat', [ClientSystemMonitorController::class, 'recordHeartbeat'])->name('system.heartbeat');
        Route::post('system/report-version', [ClientSystemMonitorController::class, 'reportVersionInfo'])->name('system.report_version'); // جدید
        Route::post('system/log-error', [ClientSystemMonitorController::class, 'logCriticalError'])->name('system.log_error');       // جدید

        // Updates
        Route::get('updates/check', [ClientUpdateController::class, 'checkForUpdate'])->name('updates.check');                   // جدید
        // Route::get('updates/download/{version}', [ClientUpdateController::class, 'downloadUpdate'])->name('updates.download'); // جدید - اختیاری

        // Backup Management (Key Management by Server)
        Route::post('backup/request-encryption-key', [ClientBackupController::class, 'requestEncryptionKey'])->name('backup.request_encryption_key'); // جدید
        Route::post('backup/report-status', [ClientBackupController::class, 'reportBackupStatus'])->name('backup.report_status');             // جدید
        Route::post('backup/request-decryption-key', [ClientBackupController::class, 'requestDecryptionKey'])->name('backup.request_decryption_key'); // جدید

        // Sync
        Route::post('sync/system-data', [SyncController::class, 'syncClientSystemData'])->name('sync.system_data');
    });