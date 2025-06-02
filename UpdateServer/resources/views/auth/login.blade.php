<x-guest-layout>
    <!-- Session Status -->
    {{-- Add text-sm and appropriate color for status messages --}}
    <x-auth-session-status class="mb-4 text-sm text-green-400" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            {{-- Label will inherit base style from guest.blade.php --}}
            <x-input-label for="email" :value="__('ایمیل')" />
            {{-- Input will inherit base style from guest.blade.php --}}
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            {{-- Error message styling applied via guest.blade.php --}}
            <x-input-error :messages="$errors->get('email')" class="mt-1 input-error" /> {{-- Added input-error class --}}
        </div>

        <!-- Password -->
        <div class="mt-4">
             <x-input-label for="password" :value="__('رمز عبور')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-1 input-error" /> {{-- Added input-error class --}}
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            {{-- Checkbox styling applied via guest.blade.php --}}
            <label for="remember_me" class="inline-flex items-center checkbox-label">
                <input id="remember_me" type="checkbox" name="remember"> {{-- Removed default breeze classes, style comes from guest layout --}}
                <span class="ms-2 text-sm">{{ __('مرا به خاطر بسپار') }}</span> {{-- Span color inherited from guest layout --}}
            </label>
        </div>

        <div class="flex items-center justify-between mt-6"> {{-- Use justify-between --}}
            @if (Route::has('password.request'))
                {{-- Apply custom link style --}}
                <a class="auth-link" href="{{ route('password.request') }}">
                    {{ __('رمز عبور خود را فراموش کرده‌اید؟') }}
                </a>
            @else
                <span></span> {{-- Placeholder to keep button on the right if no forgot link --}}
            @endif

            {{-- Apply custom button style defined in guest.blade.php --}}
            <button type="submit" class="btn-auth-primary"> {{-- Removed ms-3, spacing handled by justify-between --}}
                {{ __('ورود') }}
            </button>
        </div>

        {{-- Link to Register Page --}}
         <div class="text-center mt-8 border-t border-gray-700 pt-4"> {{-- Added separator --}}
             <span class="text-sm text-gray-400">هنوز حساب کاربری ندارید؟ </span>
             <a href="{{ route('register') }}" class="auth-link font-semibold">
                 ثبت نام کنید
             </a>
         </div>
    </form>
</x-guest-layout>