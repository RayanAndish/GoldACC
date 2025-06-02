<?php // app/Http/Middleware/Authenticate.php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Import Auth
use Illuminate\Support\Facades\Log; // Keep for potential debugging

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            // Check if the request is for an admin route
            if ($request->is('admin/*') || $request->routeIs('admin.*')) {
                Log::warning('[Authenticate Middleware] Unauthenticated admin access attempt. Redirecting to admin.login.', ['path' => $request->path()]);
                return route('admin.login'); // Redirect to admin login page
            }
            
            Log::warning('[Authenticate Middleware] Unauthenticated web access attempt. Redirecting to login.', ['path' => $request->path()]);
            return route('login'); // Default to user login page
        }
        return null;
    }
}