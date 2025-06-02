<?php // app/Http/Controllers/Auth/AdminLoginController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Http\Requests\Auth\LoginRequest; // Can potentially reuse the user LoginRequest

class AdminLoginController extends Controller
{
    /**
     * Show the admin login form.
     */
    public function showLoginForm(): View
    {
        // Ensure guests of the 'admin' guard can see the form
        // $this->middleware('guest:admin')->except('logout'); // Apply middleware in routes instead
        return view('auth.admin-secure-login'); // We need to create this view
    }

    /**
     * Handle an authentication attempt for admin.
     */
    public function login(LoginRequest $request): RedirectResponse // Use LoginRequest for validation
    {
        // Attempt to log the admin in using the 'admin' guard
        if (Auth::guard('admin')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Redirect to the intended admin dashboard or a default route
            // Make sure 'admin.dashboard' route exists
            return redirect()->intended('/admin/dashboard');
        }

        // If unsuccessful, then redirect back to the login with the form data
        return back()->withErrors([
            'email' => 'اطلاعات کاربری ارائه شده با سوابق ما مطابقت ندارد.',
        ])->onlyInput('email');
    }

     /**
      * Log the admin out of the application.
      */
     public function logout(Request $request): RedirectResponse
     {
         Auth::guard('admin')->logout();

         $request->session()->invalidate();

         $request->session()->regenerateToken();

         return redirect('/'); // Redirect to landing page
     }
}