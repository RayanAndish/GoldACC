<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\System; // برای دریافت لیست سیستم‌ها در فرم
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // برای لاگ‌گیری
use Illuminate\Support\Str; // برای تولید رشته‌های تصادفی
use Illuminate\Validation\Rule; // برای قوانین اعتبارسنجی پیشرفته (مانند in)
use Exception; // برای مدیریت خطا
use Morilog\Jalali\Jalalian;

class LicenseController extends Controller
{
    public function index()
    {
        Log::info('Viewing license list.');
        $licenses = License::with('system.customer') // Eager load relationships for efficiency
                           ->latest() // Order by creation date, newest first
                           ->paginate(15); // Paginate results

        return view('admin.licenses.index', compact('licenses'));
    }

    /**
     * Show the form for creating a new license.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        Log::info('Viewing create license form.');
        // Fetch systems with their customer names to populate the dropdown
        $systems = System::with('customer:id,name') // Only select necessary customer columns
                         ->orderBy('name')
                         ->get(['id', 'name', 'domain', 'customer_id']);

        return view('admin.licenses.create', compact('systems'));
    }

    /**
     * Store a newly created license in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        Log::info('Attempting to store a new license.');

        // Validate the incoming request data
        $validatedData = $request->validate([
            'system_id' => 'required|exists:systems,id',
            'hardware_id' => 'required|string|max:255',
            'request_code' => 'required|string|max:255',
            'ip_address' => 'nullable|ip', // Optional IP address
            'license_type' => 'required|string|max:50',
            'expires_at' => 'nullable|string', // Basic Jalali format validation YYYY/MM/DD
            'features' => 'nullable|string', // Comma-separated string initially
        ]);
                // Convert Jalali expires_at to Gregorian Carbon object
                $gregorianExpiresAt = null;
                if (!empty($validatedData['expires_at'])) {
                    try {
                        $gregorianExpiresAt = Jalalian::fromFormat('Y/m/d', $validatedData['expires_at'])->toCarbon();
                        // Optional: Add date validation logic here if needed (e.g., must be after today)
                        if ($gregorianExpiresAt->isPast() && !$gregorianExpiresAt->isToday()) {
                             throw new Exception('تاریخ انقضا نمی‌تواند مربوط به گذشته باشد.');
                         }
                    } catch (\Exception $e) {
                        // Use Laravel's validation exception for consistency
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'expires_at' => 'فرمت تاریخ انقضا نامعتبر است یا تاریخ گذشته است. از فرمت YYYY/MM/DD استفاده کنید.',
                        ]);
                    }
                }

        try {
            // 1. Generate UNIQUE Salt
            $salt = bin2hex(random_bytes(16));

            // 2. Generate License Key (Plain Text) - Ensure it's unique enough
            $license_key_plain = 'GLIC-' . strtoupper(Str::random(32)); // 32 bytes = 64 hex chars

            // 3. Get Hashing and Display Config
            $iterations = config('license.iterations', 10000);
            $display_key_prefix_length = config('license.display_key_prefix_length', 8);

            // 4. Hash parameters using the UNIQUE $salt
            // Ensure required fields are present before hashing
            $hashed_hardware_id = hash_pbkdf2('sha256', $validatedData['hardware_id'], $salt, $iterations);
            $hashed_license_key = hash_pbkdf2('sha256', $license_key_plain, $salt, $iterations);
            $hashed_request_code = hash_pbkdf2('sha256', $validatedData['request_code'], $salt, $iterations);
            $hashed_ip = !empty($validatedData['ip_address']) ? hash_pbkdf2('sha256', $validatedData['ip_address'], $salt, $iterations) : null;

            // 5. Prepare features array
            $features_array = !empty($validatedData['features']) ? array_filter(array_map('trim', explode(',', $validatedData['features']))) : [];

            // 6. Prepare display key
            $license_key_display = substr($license_key_plain, 0, $display_key_prefix_length) . '...';

            // 7. Create License record in the database
            $license = License::create([
                'system_id' => $validatedData['system_id'],
                'license_key_hash' => $hashed_license_key,
                'license_key_display' => $license_key_display,
                'salt' => $salt,
                'hardware_id_hash' => $hashed_hardware_id,
                'license_type' => $validatedData['license_type'],
                'features' => $features_array ?: null, // Store as JSON (thanks to $casts in Model)
                'request_code_hash' => $hashed_request_code,
                'ip_hash' => $hashed_ip,
                'status' => 'pending', // Initial status
                'expires_at' => $gregorianExpiresAt,
                'activated_at' => null, // Not activated yet
            ]);

            Log::info('License generated successfully.', ['license_id' => $license->id, 'system_id' => $license->system_id]);

            // Redirect to the index page with success message and the generated key
            return redirect()->route('admin.licenses.index')
                   ->with('success', 'لایسنس با موفقیت تولید شد.')
                   ->with('generated_license_key', $license_key_plain); // Pass plain key to view via session flash

        } catch (Exception $e) {
            Log::error('License generation failed.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $validatedData ?? $request->all(), // Log validated data if available
            ]);

            // Redirect back to the form with error message and input data
            return back()->withInput()
                   ->with('error', 'خطا در تولید لایسنس: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified license details.
     *
     * @param  \App\Models\License  $license
     * @return \Illuminate\View\View
     */
    public function show(License $license)
    {
        Log::info('Viewing license details.', ['license_id' => $license->id]);
        // Eager load related data if not already loaded by route model binding (optional but good practice)
        $license->loadMissing('system.customer');

        return view('admin.licenses.show', compact('license'));
    }

    /**
     * Show the form for editing the specified license.
     *
     * @param  \App\Models\License  $license
     * @return \Illuminate\View\View
     */
    public function edit(License $license)
    {
        Log::info('Viewing edit license form.', ['license_id' => $license->id]);
        $license->loadMissing('system.customer'); // Load relation if needed

        // Fetch systems for the dropdown
        $systems = System::with('customer:id,name')
                         ->orderBy('name')
                         ->get(['id', 'name', 'domain', 'customer_id']);

        return view('admin.licenses.edit', compact('license', 'systems'));
    }

    /**
     * Update the specified license in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\License  $license
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, License $license)
    {
        Log::info('Attempting to update license.', ['license_id' => $license->id]);

        // Validate the incoming request data
        $validatedData = $request->validate([
            'system_id' => 'required|exists:systems,id',
            // Hardware ID and Request Code are generally not editable after creation
            'ip_address' => 'nullable|ip',
            'license_type' => 'required|string|max:50',
            'expires_at' => 'nullable|string|', // Basic Jalali format validation YYYY/MM/DD
            'features' => 'nullable|string',
            'status' => ['required', 'string', Rule::in(['pending', 'active', 'expired', 'revoked'])], // Use Rule::in for status validation
        ]);
                // Convert Jalali expires_at to Gregorian Carbon object
                $gregorianExpiresAt = null;
                if (!empty($validatedData['expires_at'])) {
                    try {
                        $gregorianExpiresAt = Jalalian::fromFormat('Y/m/d', $validatedData['expires_at'])->toCarbon();
                        // Optional: Add date validation logic here
                        if ($gregorianExpiresAt->isPast() && !$gregorianExpiresAt->isToday()) {
                             throw new Exception('تاریخ انقضا نمی‌تواند مربوط به گذشته باشد.');
                         }
                    } catch (\Exception $e) {
                         throw \Illuminate\Validation\ValidationException::withMessages([
                             'expires_at' => 'فرمت تاریخ انقضا نامعتبر است یا تاریخ گذشته است. از فرمت YYYY/MM/DD استفاده کنید.',
                         ]);
                    }
                } else {
                     // If the input is empty, explicitly set it to null for update
                     $gregorianExpiresAt = null;
                }
        
        try {
            // Prepare data for update
            $updateData = [
                 'system_id' => $validatedData['system_id'],
                 'license_type' => $validatedData['license_type'],
                 'expires_at' => $gregorianExpiresAt,
                 'status' => $validatedData['status'],
                 // Recalculate activated_at if status changes to 'active' and it's null? Or handle in API activation?
                 // Keep it simple here: activated_at is only set by the API check.
             ];

            // Hash IP only if provided and different from current (or current is null)
            // Note: We need the original salt of this license
            if (!empty($validatedData['ip_address']) && $license->salt) {
                 $iterations = config('license.iterations', 10000);
                 $new_ip_hash = hash_pbkdf2('sha256', $validatedData['ip_address'], $license->salt, $iterations);
                 // Only update if the hash is different or was previously null
                 if ($new_ip_hash !== $license->ip_hash) {
                    $updateData['ip_hash'] = $new_ip_hash;
                    Log::debug('IP hash updated for license.', ['license_id' => $license->id]);
                 }
            } elseif (empty($validatedData['ip_address']) && $license->ip_hash !== null) {
                 // Clear IP hash if input is empty and it was previously set
                 $updateData['ip_hash'] = null;
                 Log::debug('IP hash cleared for license.', ['license_id' => $license->id]);
            }

            // Prepare features array
            $features_array = !empty($validatedData['features']) ? array_filter(array_map('trim', explode(',', $validatedData['features']))) : [];
            $updateData['features'] = $features_array ?: null; // Store as JSON

            // Perform the update
            $license->update($updateData);

            Log::info('License updated successfully.', ['license_id' => $license->id]);

            return redirect()->route('admin.licenses.index')
                   ->with('success', 'لایسنس با موفقیت به‌روزرسانی شد.');

        } catch (Exception $e) {
            Log::error('License update failed.', [
                'license_id' => $license->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $validatedData ?? $request->all(),
            ]);

            return back()->withInput()
                   ->with('error', 'خطا در به‌روزرسانی لایسنس: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified license from storage.
     *
     * @param  \App\Models\License  $license
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(License $license)
    {
        Log::warning('Attempting to delete license.', ['license_id' => $license->id]);

        try {
            $license->delete();
            Log::info('License deleted successfully.', ['license_id' => $license->id]);
            return redirect()->route('admin.licenses.index')
                   ->with('success', 'لایسنس با موفقیت حذف شد.');
        } catch (Exception $e) {
            Log::error('License deletion failed.', [
                'license_id' => $license->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'خطا در حذف لایسنس: ' . $e->getMessage());
        }
    }
}