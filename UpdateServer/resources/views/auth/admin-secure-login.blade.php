{{-- File: resources/views/auth/admin-login.blade.php --}}
    {{-- This view is similar to login.blade.php but targets admin login route --}}
    <x-guest-layout> {{-- Use the same guest layout --}}
        <div class="text-center mb-4 text-lg font-semibold text-gray-200">
             ورود مدیران سیستم
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4 text-sm text-green-400" :status="session('status')" />

        <form method="POST" action="{{ route('admin.login') }}">
            @csrf

            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('ایمیل')" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                 {{-- Display general error first --}}
                 @error('email')
                      <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                 @enderror
            </div>

            <!-- Password -->
            <div class="mt-4">
                 <x-input-label for="password" :value="__('رمز عبور')" />
                 <x-text-input id="password" class="block mt-1 w-full"
                                type="password"
                                name="password"
                                required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-1 input-error" />
            </div>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember_me" class="inline-flex items-center checkbox-label">
                    <input id="remember_me" type="checkbox" name="remember">
                    <span class="ms-2 text-sm">{{ __('مرا به خاطر بسپار') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-6">
                {{-- Optional: Add forgot password link for admins if needed --}}
                {{-- @if (Route::has('admin.password.request'))
                    <a class="auth-link" href="{{ route('admin.password.request') }}">
                        {{ __('رمز عبور خود را فراموش کرده‌اید؟') }}
                    </a>
                @endif --}}

                <button type="submit" class="btn-auth-primary">
                    {{ __('ورود') }}
                </button>
            </div>
        </form>
    </x-guest-layout>