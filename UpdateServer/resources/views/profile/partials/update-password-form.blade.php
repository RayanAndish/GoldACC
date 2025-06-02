{{-- File: resources/views/profile/partials/update-password-form.blade.php --}}
<section>
    <header>
        <h2 class="text-lg font-semibold text-gray-900">
            {{ __('به‌روزرسانی رمز عبور') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('اطمینان حاصل کنید که حساب شما از یک رمز عبور طولانی و تصادفی برای امنیت بیشتر استفاده می‌کند.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        {{-- Current Password --}}
        <div>
            <x-input-label for="update_password_current_password" :value="__('رمز عبور فعلی')" class="text-gray-700"/>
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full profile-input" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        {{-- New Password --}}
        <div>
            <x-input-label for="update_password_password" :value="__('رمز عبور جدید')" class="text-gray-700"/>
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full profile-input" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        {{-- Confirm Password --}}
        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('تکرار رمز عبور جدید')" class="text-gray-700"/>
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full profile-input" autocomplete="new-password"/>
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        {{-- Save Button --}}
        <div class="flex items-center gap-4">
             <button type="submit" class="btn-blue-app text-sm">
                 {{ __('ذخیره') }}
             </button>

            <x-action-message class="me-3" on="password-updated">
                {{ __('ذخیره شد.') }}
            </x-action-message>
        </div>
    </form>
     {{-- Re-add profile-input style if not global --}}
     {{-- <style> .profile-input { @apply border-gray-300 focus:border-yellow-500 focus:ring-yellow-500 rounded-md shadow-sm; } </style> --}}
</section>