{{-- File: resources/views/admin/systems/create.blade.php (Using App Layout & Tailwind) --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('افزودن سامانه جدید') }}
    </x-slot>

    <div class="p-6 bg-white rounded-lg shadow border">
        {{-- Ensure route 'admin.systems.store' exists --}}
        <form action="{{ route('admin.systems.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Customer Selection --}}
                <div>
                    <label for="customer_id" class="block text-sm font-medium text-gray-700">مشتری <span class="text-red-500">*</span></label>
                    {{-- Ensure $customers is passed from the controller's create method --}}
                    <select name="customer_id" id="customer_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('customer_id') border-red-500 @enderror" required>
                        <option value="">-- انتخاب مشتری --</option>
                        @isset($customers)
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        @else
                             <option value="" disabled>لیست مشتریان بارگذاری نشده است.</option>
                        @endisset
                    </select>
                    @error('customer_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- System Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">نام سامانه <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('name') border-red-500 @enderror">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Domain --}}
                <div>
                    <label for="domain" class="block text-sm font-medium text-gray-700">دامنه <span class="text-red-500">*</span></label>
                    <input type="url" name="domain" id="domain" value="{{ old('domain') }}" required placeholder="https://example.com"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('domain') border-red-500 @enderror">
                    @error('domain') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">وضعیت <span class="text-red-500">*</span></label>
                    <select name="status" id="status" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('status') border-red-500 @enderror" required>
                        <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>فعال</option>
                        <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>غیرفعال</option>
                    </select>
                    @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Current Version --}}
                 <div class="md:col-span-2"> {{-- Span across 2 columns for better layout maybe --}}
                    <label for="current_version" class="block text-sm font-medium text-gray-700">نسخه فعلی</label>
                    <input type="text" name="current_version" id="current_version" value="{{ old('current_version') }}"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('current_version') border-red-500 @enderror">
                    @error('current_version') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- ADD OTHER FIELDS if needed --}}

            </div>

            {{-- Form Actions --}}
            <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end items-center">
                 {{-- Ensure route 'admin.systems.index' exists --}}
                <a href="{{ route('admin.systems.index') }}" class="text-sm text-gray-600 hover:text-gray-800 mr-4">
                    انصراف
                </a>
                <button type="submit" class="btn-blue-app">
                    <i class="fas fa-plus mr-1 ml-1"></i>
                    افزودن سامانه
                </button>
            </div>
        </form>
    </div>
</x-app-layout>