{{-- File: resources/views/profile/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('پروفایل کاربری') }}
    </x-slot>

    {{-- Removed the outer py-12 div, padding is handled by main in app layout --}}
    <div class="space-y-6"> {{-- Add space between cards --}}

        {{-- Update Profile Information Card --}}
        <div class="p-4 sm:p-8 bg-white shadow-lg sm:rounded-lg border border-gray-200">
            <div class="max-w-xl"> {{-- Limit width of the form inside card --}}
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        {{-- Update Password Card --}}
        <div class="p-4 sm:p-8 bg-white shadow-lg sm:rounded-lg border border-gray-200">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        {{-- Delete Account Card --}}
        <div class="p-4 sm:p-8 bg-white shadow-lg sm:rounded-lg border border-gray-200">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>