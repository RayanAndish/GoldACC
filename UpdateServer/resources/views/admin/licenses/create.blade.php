{{-- File: resources/views/admin/licenses/create.blade.php (Using App Layout & Tailwind) --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('ایجاد لایسنس جدید') }}
    </x-slot>

    <div class="p-6 bg-white rounded-lg shadow border">
         {{-- Display All Validation Errors --}}
         @if ($errors->any())
            <div class="mb-4 p-4 bg-red-100 border border-red-200 text-red-700 rounded-md text-sm">
                <p class="font-semibold">لطفا خطاهای زیر را برطرف کنید:</p>
                <ul class="list-disc list-inside mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        {{-- Display Session Error --}}
        @if(session('error'))
             <div class="mb-4 p-4 bg-red-100 border border-red-200 text-red-700 rounded-md text-sm">
                 {{ session('error') }}
             </div>
        @endif


        {{-- Ensure route 'admin.licenses.store' exists --}}
        <form action="{{ route('admin.licenses.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- System Selection --}}
                <div>
                    <label for="system_id" class="block text-sm font-medium text-gray-700">سیستم مشتری <span class="text-red-500">*</span></label>
                    {{-- Ensure $systems (with customer) is passed from the controller's create method --}}
                    <select name="system_id" id="system_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('system_id') border-red-500 @enderror" required>
                        <option value="">-- انتخاب سیستم --</option>
                        @isset($systems)
                            @foreach($systems as $system)
                                <option value="{{ $system->id }}" {{ old('system_id') == $system->id ? 'selected' : '' }}>
                                    {{ $system->name }} ({{ $system->domain }}) - مشتری: {{ $system->customer->name ?? 'نامشخص' }}
                                </option>
                            @endforeach
                        @else
                             <option value="" disabled>هیچ سیستمی یافت نشد</option>
                        @endisset
                    </select>
                    @error('system_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Hardware ID --}}
                <div>
                    <label for="hardware_id" class="block text-sm font-medium text-gray-700">شناسه سخت‌افزار (Hardware ID) <span class="text-red-500">*</span></label>
                    <input type="text" name="hardware_id" id="hardware_id" value="{{ old('hardware_id') }}" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('hardware_id') border-red-500 @enderror">
                    @error('hardware_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                 {{-- Request Code --}}
                <div>
                    <label for="request_code" class="block text-sm font-medium text-gray-700">کد درخواست فعال‌سازی (Request Code) <span class="text-red-500">*</span></label>
                    <input type="text" name="request_code" id="request_code" value="{{ old('request_code') }}" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('request_code') border-red-500 @enderror">
                    @error('request_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                 {{-- IP Address --}}
                <div>
                    <label for="ip_address" class="block text-sm font-medium text-gray-700">آدرس IP (اختیاری)</label>
                    <input type="text" name="ip_address" id="ip_address" value="{{ old('ip_address') }}" placeholder="e.g., 192.168.1.100"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('ip_address') border-red-500 @enderror">
                    @error('ip_address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- License Type --}}
                <div>
                    <label for="license_type" class="block text-sm font-medium text-gray-700">نوع لایسنس <span class="text-red-500">*</span></label>
                    <input type="text" name="license_type" id="license_type" value="{{ old('license_type', 'standard') }}" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('license_type') border-red-500 @enderror">
                    @error('license_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Expiry Date --}}
                <div>
                    <label for="expires_at" class="block text-sm font-medium text-gray-700">تاریخ انقضا (اختیاری)</label>
                    {{-- Change type to text and add class for datepicker --}}
                    <input type="text" name="expires_at" id="expires_at" value="{{ old('expires_at') }}" placeholder="YYYY/MM/DD"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm jalali-datepicker @error('expires_at') border-red-500 @enderror">
                    @error('expires_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>


                {{-- Features --}}
                <div class="md:col-span-2">
                    <label for="features" class="block text-sm font-medium text-gray-700">ویژگی‌ها (جدا شده با کاما ,)</label>
                    <textarea name="features" id="features" rows="3" placeholder="feature1,feature2,another_feature"
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('features') border-red-500 @enderror">{{ old('features') }}</textarea>
                    @error('features') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

            </div>

            {{-- Form Actions --}}
            <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end items-center">
                 {{-- Ensure route 'admin.licenses.index' exists --}}
                <a href="{{ route('admin.licenses.index') }}" class="text-sm text-gray-600 hover:text-gray-800 mr-4">
                    انصراف
                </a>
                {{-- Use appropriate button class --}}
                <button type="submit" class="btn-blue-app">
                    <i class="fas fa-key mr-1 ml-1"></i>
                    تولید لایسنس
                </button>
            </div>
        </form>
    </div>
</x-app-layout>