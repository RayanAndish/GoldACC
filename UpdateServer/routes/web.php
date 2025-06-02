<?php

use Illuminate\Support\Facades\Route;

// ========== Landing Page Controller ==========
use App\Http\Controllers\LandingPageController;

// ========== User Area Controllers ==========
use App\Http\Controllers\ProfileController as UserProfileController;
use App\Http\Controllers\SupportTicketController as UserSupportTicketController;
use App\Http\Controllers\DashboardController as UserDashboardController; // این کنترلر برای داشبورد کاربر عادی است

// ========== Admin Area Controllers ==========
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController; // این برای داشبورد ادمین
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\SystemController as AdminSystemController;
use App\Http\Controllers\Admin\LicenseController as AdminLicenseController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;

// ==========================================
// ========== PUBLIC ROUTES =================
// ==========================================
Route::get('/', [LandingPageController::class, 'index'])->name('landing');

// ==========================================
// ========== USER AUTHENTICATED ROUTES ===== (مسیرهای کاربران عادی)
// ==========================================
Route::middleware(['auth', 'verified'])->group(function () { // 'auth' برای گارد پیش‌فرض کاربران، 'verified' اگر نیاز به تایید ایمیل دارند
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard'); // نام مسیر: 'dashboard' یا 'user.dashboard' اگر ترجیح می‌دهید

    // User Profile
    Route::prefix('profile')->name('profile.')->group(function () { // نام: profile.edit, profile.update, ...
        Route::get('/', [UserProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [UserProfileController::class, 'update'])->name('update');
        Route::delete('/', [UserProfileController::class, 'destroy'])->name('destroy');
    });

    // User Support Tickets
    Route::prefix('tickets')->name('tickets.')->group(function () { // نام: tickets.index, tickets.create, ...
        Route::get('/', [UserSupportTicketController::class, 'index'])->name('index');
        Route::get('/create', [UserSupportTicketController::class, 'create'])->name('create');
        Route::post('/', [UserSupportTicketController::class, 'store'])->name('store');
        Route::get('/{ticket}', [UserSupportTicketController::class, 'show'])->name('show');
    });
});

// ==========================================
// ========== ADMIN ROUTES ==================
// ==========================================
Route::prefix('admin')->name('admin.')->group(function () {
    // Admin Authentication Routes
    Route::middleware('guest:admin')->group(function() {
        Route::get('secure-area', [AdminLoginController::class, 'showLoginForm'])->name('login'); // نام: admin.login
        Route::post('secure-area', [AdminLoginController::class, 'login']); // نام برای POST هم admin.login خواهد بود
    });
    Route::post('logout', [AdminLoginController::class, 'logout'])->name('logout')->middleware('auth:admin'); // نام: admin.logout

    // Admin Protected Routes (All protected routes including dashboard go here)
    Route::middleware(['auth:admin', 'verified:admin.verification.notice'])->group(function () { // 'verified' برای ادمین اگر نیاز است
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard'); // نام: admin.dashboard

        // Admin Support Ticket Management
        Route::prefix('tickets')->name('tickets.')->group(function () { // نام‌ها: admin.tickets.index, ...
            Route::get('/', [AdminSupportTicketController::class, 'index'])->name('index');
            Route::get('/{ticket}', [AdminSupportTicketController::class, 'show'])->name('show');
            Route::put('/{ticket}', [AdminSupportTicketController::class, 'update'])->name('update');
            Route::delete('/{ticket}', [AdminSupportTicketController::class, 'destroy'])->name('destroy');
        });

        // ... بقیه مسیرهای ادمین به همین شکل ...
        // Admin Customer Management
        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/', [AdminCustomerController::class, 'index'])->name('index');
            Route::get('/create', [AdminCustomerController::class, 'create'])->name('create');
            Route::post('/', [AdminCustomerController::class, 'store'])->name('store');
            Route::get('/{customer}', [AdminCustomerController::class, 'show'])->name('show');
            Route::get('/{customer}/edit', [AdminCustomerController::class, 'edit'])->name('edit');
            Route::put('/{customer}', [AdminCustomerController::class, 'update'])->name('update');
            Route::delete('/{customer}', [AdminCustomerController::class, 'destroy'])->name('destroy');
        });

        // Admin System Management
        Route::prefix('systems')->name('systems.')->group(function () {
            Route::get('/', [AdminSystemController::class, 'index'])->name('index');
            Route::get('/create', [AdminSystemController::class, 'create'])->name('create');
            Route::post('/', [AdminSystemController::class, 'store'])->name('store');
            Route::get('/{system}', [AdminSystemController::class, 'show'])->name('show');
            Route::get('/{system}/edit', [AdminSystemController::class, 'edit'])->name('edit');
            Route::put('/{system}', [AdminSystemController::class, 'update'])->name('update');
            Route::delete('/{system}', [AdminSystemController::class, 'destroy'])->name('destroy');
        });

        // Admin License Management
        Route::prefix('licenses')->name('licenses.')->group(function () {
            Route::get('/', [AdminLicenseController::class, 'index'])->name('index');
            Route::get('/create', [AdminLicenseController::class, 'create'])->name('create');
            Route::post('/', [AdminLicenseController::class, 'store'])->name('store');
            Route::get('/{license}', [AdminLicenseController::class, 'show'])->name('show');
            Route::get('/{license}/edit', [AdminLicenseController::class, 'edit'])->name('edit');
            Route::put('/{license}', [AdminLicenseController::class, 'update'])->name('update');
            Route::delete('/{license}', [AdminLicenseController::class, 'destroy'])->name('destroy');
        });
    });
});

// Include Breeze's/Laravel's default auth routes for regular users
// این فایل معمولاً شامل مسیرهای لاگین، ریجستر و ... برای کاربران عادی است.
// اگر از این فایل استفاده می‌کنید، مسیر /dashboard که در بالا برای کاربران عادی تعریف کردیم،
// ممکن است با مسیر /dashboard پیش‌فرض Breeze تداخل پیدا کند یا جایگزین آن شود.
// باید بررسی کنید که auth.php دقیقاً چه مسیرهایی را تعریف می‌کند.
require __DIR__.'/auth.php';