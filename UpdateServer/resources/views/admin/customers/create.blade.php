{{-- File: resources/views/admin/customers/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('افزودن مشتری جدید') }}
    </x-slot>

    <div class="p-6 bg-white rounded-lg shadow border">
        <form action="{{ route('admin.customers.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Name Field --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">نام مشتری <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('name') border-red-500 @enderror">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Email Field --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">ایمیل <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('email') border-red-500 @enderror">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Phone Field --}}
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">تلفن</label>
                    <input type="tel" name="phone" id="phone" value="{{ old('phone') }}"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('phone') border-red-500 @enderror">
                    @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- User Selection Dropdown --}}
                <div>
                    <label for="user_id" class="block text-sm font-medium text-gray-700">اتصال به کاربر (اختیاری)</label>
                    <select name="user_id" id="user_id"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('user_id') border-red-500 @enderror">
                        <option value="">-- انتخاب کاربر --</option>
                        @foreach ($availableUsers as $user)
                            <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                    @error('user_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-gray-500">فقط کاربرانی که قبلاً به مشتری دیگری متصل نشده‌اند نمایش داده می‌شوند.</p>
                </div>

                {{-- Address Field (Example) --}}
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700">آدرس</label>
                    <textarea name="address" id="address" rows="3"
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('address') border-red-500 @enderror">{{ old('address') }}</textarea>
                    @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end items-center">
                <a href="{{ route('admin.customers.index') }}" class="text-sm text-gray-600 hover:text-gray-800 mr-4">
                    انصراف
                </a>
                <button type="submit" class="btn-blue-app">
                    <i class="fas fa-plus mr-1 ml-1"></i>
                    افزودن مشتری
                </button>
            </div>
        </form>
    </div>
</x-app-layout>