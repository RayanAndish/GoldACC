{{-- File: resources/views/admin/licenses/edit.blade.php (Using App Layout & Tailwind) --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('ویرایش لایسنس #') }}{{ $license->id }}
    </x-slot>

    <div class="space-y-6"> {{-- Add spacing between sections --}}

        {{-- Display Session Error --}}
        @if(session('error'))
             <div class="p-4 bg-red-100 border border-red-200 text-red-700 rounded-md text-sm">
                 {{ session('error') }}
             </div>
        @endif

        {{-- Non-Editable Info Card --}}
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
             <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h4 class="text-base font-semibold text-gray-700">اطلاعات ثابت</h4>
            </div>
            <div class="p-6">
                 <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                     <div class="col-span-1 font-medium text-gray-600">کلید نمایشی:</div>
                     <dd class="md:col-span-2 font-mono text-xs break-all text-gray-800">{{ $license->license_key_display ?? 'N/A' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">هش شناسه سخت‌افزار:</div>
                     <dd class="md:col-span-2 font-mono text-xs break-all text-gray-800">{{ $license->hardware_id_hash ?? 'N/A' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">هش کد درخواست:</div>
                     <dd class="md:col-span-2 font-mono text-xs break-all text-gray-800">{{ $license->request_code_hash ?? 'N/A' }}</dd>
                 </dl>
            </div>
        </div>

        {{-- Editable Info Form Card --}}
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
             <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h4 class="text-base font-semibold text-gray-700">ویرایش اطلاعات</h4>
            </div>
            <form action="{{ route('admin.licenses.update', $license->id) }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {{-- System Selection --}}
                    <div>
                        <label for="system_id" class="block text-sm font-medium text-gray-700">سیستم مشتری <span class="text-red-500">*</span></label>
                        {{-- Ensure $systems (with customer) is passed from the controller's edit method --}}
                        <select name="system_id" id="system_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('system_id') border-red-500 @enderror" required>
                            <option value="">-- انتخاب سیستم --</option>
                            @isset($systems)
                                @foreach($systems as $system)
                                    <option value="{{ $system->id }}" {{ old('system_id', $license->system_id) == $system->id ? 'selected' : '' }}>
                                        {{ $system->name }} ({{ $system->domain }}) - مشتری: {{ $system->customer->name ?? 'نامشخص' }}
                                    </option>
                                @endforeach
                             @else
                                 <option value="" disabled>لیست سامانه‌ها بارگذاری نشده است.</option>
                            @endisset
                        </select>
                        @error('system_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                     {{-- IP Address --}}
                    <div>
                        <label for="ip_address" class="block text-sm font-medium text-gray-700">آدرس IP (اختیاری)</label>
                        <input type="text" name="ip_address" id="ip_address" value="{{ old('ip_address') }}" placeholder="برای ثبت IP جدید، اینجا وارد کنید"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('ip_address') border-red-500 @enderror">
                         @if($license->ip_hash)
                         <p class="mt-1 text-xs text-gray-500">هش IP فعلی: <code class="text-xs">{{ $license->ip_hash }}</code></p>
                         @endif
                        @error('ip_address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- License Type --}}
                    <div>
                        <label for="license_type" class="block text-sm font-medium text-gray-700">نوع لایسنس <span class="text-red-500">*</span></label>
                        <input type="text" name="license_type" id="license_type" value="{{ old('license_type', $license->license_type) }}" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('license_type') border-red-500 @enderror">
                        @error('license_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Status --}}
                     <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">وضعیت <span class="text-red-500">*</span></label>
                        <select name="status" id="status" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('status') border-red-500 @enderror" required>
                            <option value="pending" {{ old('status', $license->status) == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="active" {{ old('status', $license->status) == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="expired" {{ old('status', $license->status) == 'expired' ? 'selected' : '' }}>Expired</option>
                            <option value="revoked" {{ old('status', $license->status) == 'revoked' ? 'selected' : '' }}>Revoked</option>
                           {{-- Add other statuses if needed --}}
                        </select>
                        @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Expiry Date --}}
                     <div>
                        <label for="expires_at" class="block text-sm font-medium text-gray-700">تاریخ انقضا (اختیاری)</label>
                        {{-- Change type, add class, use Jalali accessor for initial value --}}
                        <input type="text" name="expires_at" id="expires_at" value="{{ old('expires_at', $license->jalali_expires_at) }}" placeholder="YYYY/MM/DD"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm jalali-datepicker @error('expires_at') border-red-500 @enderror">
                        @error('expires_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Features --}}
                    <div class="md:col-span-2">
                        <label for="features" class="block text-sm font-medium text-gray-700">ویژگی‌ها (جدا شده با کاما ,)</label>
                        <textarea name="features" id="features" rows="3" placeholder="feature1,feature2,another_feature"
                                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('features') border-red-500 @enderror">{{ old('features', is_array($license->features) ? implode(',', $license->features) : $license->features) }}</textarea>
                        @error('features') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                </div>

                {{-- Form Actions --}}
                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end items-center gap-x-3">
                    {{-- Ensure routes exist --}}
                    <a href="{{ route('admin.licenses.show', $license->id) }}" class="text-sm text-cyan-600 hover:text-cyan-800">مشاهده جزئیات</a>
                    <a href="{{ route('admin.licenses.index') }}" class="text-sm text-gray-600 hover:text-gray-800">انصراف</a>
                    {{-- Use appropriate button class --}}
                    <button type="submit" class="btn-blue-app">
                        <i class="fas fa-save mr-1 ml-1"></i>
                        ذخیره تغییرات
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>