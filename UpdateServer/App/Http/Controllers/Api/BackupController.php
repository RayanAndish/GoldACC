<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backup;
use App\Models\System; // اضافه شد

class BackupController extends Controller
{
    /**
     * Store a newly created backup record in storage.
     * Called by the authenticated client system.
     * Protected by 'api.security' middleware.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $authenticatedSystem = $request->attributes->get('authenticated_system');
        if (!$authenticatedSystem) {
            return response()->json(['error' => 'System not authenticated or not found.'], 403);
        }

        $validated = $request->validate([
            'file_path' => 'required|string|max:255',
            'status'    => 'sometimes|string|max:50', // e.g., 'client_upload_complete', 'client_upload_failed'
            'file_size' => 'nullable|integer|min:0', // اندازه فایل بکاپ به بایت
            'backup_type' => 'nullable|string|max:50' // e.g., 'full', 'database_only', 'files_only'
        ]);

        try {
            $backup = Backup::create([
                'system_id'   => $authenticatedSystem->id, // از سیستم احراز هویت شده
                'file_path'   => $validated['file_path'],
                'status'      => $validated['status'] ?? 'client_reported', // وضعیت اولیه
                'file_size'   => $validated['file_size'] ?? null,
                'backup_type' => $validated['backup_type'] ?? null,
                // 'created_at' توسط Eloquent
            ]);

            return response()->json(['success' => true, 'message' => 'Backup information successfully recorded.', 'backup_id' => $backup->id], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error storing backup for system ID ' . $authenticatedSystem->id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Could not record backup information due to a server error.'], 500);
        }
    }

    /**
     * Display a listing of the backups for a specific system.
     * Can be called by the authenticated client system (for its own backups)
     * OR by an admin (for any system's backups).
     * Access control needs to be handled by middleware or within the method.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param int $systemId The ID of the system whose backups are to be listed.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, int $systemId)
    {
        $authenticatedSystem = $request->attributes->get('authenticated_system'); // برای کلاینت

        // TODO: Implement Admin API Authentication if called by admin
        // $isAdmin = auth('admin_api_guard')->check();

        // بررسی مجوز:
        // ۱. اگر کلاینت است، باید فقط بکاپ‌های خودش را ببیند.
        if ($authenticatedSystem) {
            if ($authenticatedSystem->id != $systemId) {
                return response()->json(['error' => 'Forbidden. Client can only access its own backups.'], 403);
            }
        }
        // ۲. اگر ادمین نیست (و کلاینت هم نیست یا برای سیستم دیگری درخواست داده)، خطا بده.
        // else if (!$isAdmin) { // اگر گارد ادمین هم چک می‌کنید
        //     return response()->json(['error' => 'Unauthorized to view backups for this system.'], 401);
        // }
        // ۳. اگر ادمین است، می‌تواند بکاپ‌های هر سیستمی را ببیند (بدون نیاز به شرط اضافه).

        // اگر سیستم مورد نظر وجود ندارد
        if (!System::where('id', $systemId)->exists()) {
            return response()->json(['error' => 'System not found.'], 404);
        }

        $backups = Backup::where('system_id', $systemId)
                         ->orderBy('created_at', 'desc')
                         ->paginate(15); // مثال صفحه‌بندی

        return response()->json($backups);
    }

    // TODO: متدهای show, update (مثلاً برای تغییر وضعیت بکاپ توسط ادمین), destroy
    // این متدها نیز باید با احراز هویت و مجوزدهی مناسب محافظت شوند.
    // public function show(Request $request, Backup $backup) { ... } // Route model binding
    // public function update(Request $request, Backup $backup) { ... }
    // public function destroy(Request $request, Backup $backup) { ... }
}
