{{-- File: resources/views/profile/partials/update-profile-information-form.blade.php --}}
<section>
    <header>
        <h2 class="text-lg font-semibold text-gray-900">
            {{ __('اطلاعات پروفایل') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("نام و اطلاعات ایمیل حساب کاربری خود را به‌روز کنید.") }}
             {{-- Add note about company info if needed --}}
             {{-- <br> {{ __("اطلاعات شرکت، ایمیل سازمانی و دامنه در این بخش قابل ویرایش نیست.") }} --}}
        </p>
    </header>

    {{-- Form for email verification link --}}
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    {{-- Main profile update form --}}
    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        {{-- Name --}}
        <div>
            <x-input-label for="name" :value="__('نام و نام خانوادگی')" class="text-gray-700" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full profile-input" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        {{-- Email --}}
        <div>
            <x-input-label for="email" :value="__('ایمیل (شخصی/ورود)')" class="text-gray-700"/>
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full profile-input" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            {{-- Email Verification Status --}}
            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-orange-700">
                        {{ __('آدرس ایمیل شما تایید نشده است.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('برای ارسال مجدد ایمیل تایید اینجا کلیک کنید.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('لینک تایید جدیدی به آدرس ایمیل شما ارسال شد.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

         {{-- Display Company Info (Readonly for now) --}}
         @if($user->company_name || $user->organizational_email || $user->domain_name)
             <div class="mt-6 pt-4 border-t border-gray-200 space-y-4">
                 <h3 class="text-md font-semibold text-gray-800">اطلاعات شرکت (ثبت شده)</h3>
                 @if($user->company_name)
                 <div>
                     <span class="block text-sm font-medium text-gray-500">نام شرکت:</span>
                     <p class="text-sm text-gray-700">{{ $user->company_name }}</p>
                 </div>
                 @endif
                 @if($user->organizational_email)
                  <div>
                     <span class="block text-sm font-medium text-gray-500">ایمیل سازمانی:</span>
                     <p class="text-sm text-gray-700">{{ $user->organizational_email }}</p>
                 </div>
                 @endif
                 @if($user->domain_name)
                  <div>
                     <span class="block text-sm font-medium text-gray-500">نام دامنه:</span>
                     <p class="text-sm text-gray-700">{{ $user->domain_name }}</p>
                 </div>
                 @endif
                  <p class="text-xs text-gray-500">این اطلاعات توسط شما در زمان ثبت نام وارد شده و در حال حاضر قابل ویرایش از این بخش نیست.</p>
             </div>
         @endif


        {{-- Save Button --}}
        <div class="flex items-center gap-4">
            {{-- Use a button style consistent with the theme --}}
            <button type="submit" class="btn-blue-app text-sm">
                 {{ __('ذخیره تغییرات') }}
            </button>

            {{-- Saved message --}}
            <x-action-message class="me-3" on="profile-updated">
                {{ __('ذخیره شد.') }}
            </x-action-message>
        </div>
    </form>

    {{-- Custom style for profile inputs if needed, or apply directly --}}
    <style>
        .profile-input {
             @apply border-gray-300 focus:border-yellow-500 focus:ring-yellow-500 rounded-md shadow-sm;
        }
         /* Style for the 'Saved.' message if needed */
         /* [x-data][x-show][x-transition] { color: green; font-weight: bold; } */
    </style>
</section>