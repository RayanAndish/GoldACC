<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
                <div>
            <x-input-label for="name" :value="__('نام و نام خانوادگی')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-1 input-error" />
        </div>

         <!-- Company Name -->
        <div class="mt-4">
            <x-input-label for="company_name" :value="__('نام شرکت')" />
            <x-text-input id="company_name" class="block mt-1 w-full" type="text" name="company_name" :value="old('company_name')" required autofocus autocomplete="organization" />
            <x-input-error :messages="$errors->get('company_name')" class="mt-1 input-error" />
        </div>

        <!-- Organizational Email Address -->
        <div class="mt-4">
            <x-input-label for="organizational_email" :value="__('ایمیل سازمانی')" />
            <x-text-input id="organizational_email" class="block mt-1 w-full" type="email" name="organizational_email" :value="old('organizational_email')" required autofocus autocomplete="email" />
            <x-input-error :messages="$errors->get('organizational_email')" class="mt-1 input-error" />
        </div>

        <!-- Domain Name -->
        <div class="mt-4">
            <x-input-label for="domain_name" :value="__('نام دامنه')" />
            <x-text-input id="domain_name" class="block mt-1 w-full" type="text" name="domain_name" :value="old('domain_name')" placeholder="example.com" required autofocus autocomplete="url" />
            <x-input-error :messages="$errors->get('domain_name')" class="mt-1 input-error" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('آدرس ایمیل')" /> {{-- Translated --}}
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1 input-error" /> {{-- Added input-error class --}}
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('رمز عبور')" /> {{-- Translated --}}
            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1 input-error" /> {{-- Added input-error class --}}
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('تکرار رمز عبور')" /> {{-- Translated --}}
            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 input-error" /> {{-- Added input-error class --}}
        </div>

        <div class="flex items-center justify-end mt-6"> {{-- Increased margin-top --}}
            {{-- Apply custom link style --}}
            <a class="auth-link" href="{{ route('login') }}">
                {{ __('قبلاً ثبت نام کرده‌اید؟') }} {{-- Translated --}}
            </a>

             {{-- Apply custom button style --}}
            <button type="submit" class="btn-auth-primary ms-4">
                {{ __('ثبت نام') }} {{-- Translated --}}
            </button>
        </div>
    </form>
</x-guest-layout>