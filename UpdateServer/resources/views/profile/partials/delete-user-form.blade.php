{{-- File: resources/views/profile/partials/delete-user-form.blade.php --}}
<section class="space-y-6">
    <header>
        <h2 class="text-lg font-semibold text-gray-900">
            {{ __('حذف حساب کاربری') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('پس از حذف حساب شما، تمام منابع و داده‌های آن برای همیشه حذف خواهند شد. قبل از حذف حساب، لطفاً هرگونه داده یا اطلاعاتی که مایل به نگهداری آن هستید را دانلود کنید.') }}
        </p>
    </header>

    {{-- Modal Trigger Button --}}
    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        class="!bg-red-600 hover:!bg-red-700" {{-- Override default danger button style if needed --}}
    >{{ __('حذف حساب کاربری') }}</x-danger-button>

    {{-- Confirmation Modal --}}
    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 bg-white rounded-lg"> {{-- Light background for modal --}}
            @csrf
            @method('delete')

            <h2 class="text-lg font-semibold text-gray-900">
                {{ __('آیا از حذف حساب خود مطمئن هستید؟') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('پس از حذف حساب شما، تمام منابع و داده‌های آن برای همیشه حذف خواهند شد. لطفاً رمز عبور خود را برای تأیید اینکه می‌خواهید حساب خود را برای همیشه حذف کنید، وارد نمایید.') }}
            </p>

            {{-- Password Input --}}
            <div class="mt-6">
                <x-input-label for="password" value="{{ __('رمز عبور') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4 profile-input"
                    placeholder="{{ __('رمز عبور') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            {{-- Modal Action Buttons --}}
            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('انصراف') }}
                </x-secondary-button>

                <x-danger-button class="ms-3 !bg-red-600 hover:!bg-red-700">
                    {{ __('حذف حساب کاربری') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
     {{-- Re-add profile-input style if not global --}}
     {{-- <style> .profile-input { @apply border-gray-300 focus:border-yellow-500 focus:ring-yellow-500 rounded-md shadow-sm; } </style> --}}
</section>