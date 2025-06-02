<?php // app/Http/Middleware/RedirectIfAuthenticated.php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Import Log facade
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;
        Log::debug('[RedirectIfAuthenticated] Handling request for: ' . $request->path() . ' with guards: ' . implode(', ', $guards));

        foreach ($guards as $guard) {
            $guardToCheck = $guard ?? 'web'; // Use 'web' if guard is null
            $isChecked = Auth::guard($guardToCheck)->check();
            Log::debug("[RedirectIfAuthenticated] Checking guard: '{$guardToCheck}'. Is authenticated: " . ($isChecked ? 'YES' : 'NO'));

            if ($isChecked) {
                $redirectTo = match ($guardToCheck) { // Use guardToCheck here
                    'admin' => route('admin.dashboard'),
                    default => RouteServiceProvider::HOME, // Usually '/dashboard'
                };
                Log::warning("[RedirectIfAuthenticated] Authenticated user detected on guest route. Guard: '{$guardToCheck}'. Redirecting to: {$redirectTo}");
                return redirect($redirectTo);
            }
        }

        Log::debug('[RedirectIfAuthenticated] No authenticated guard matched. Passing request.');
        return $next($request);
    }
}