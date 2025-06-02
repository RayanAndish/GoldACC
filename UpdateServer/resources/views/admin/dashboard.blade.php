{{-- File: UpdateServer/resources/views/admin/dashboard.blade.php --}}
<x-app-layout> {{-- Use the main application layout --}}
    <x-slot name="header">
        {{ __('داشبورد مدیریت') }}
    </x-slot>

    {{-- Main Content Area --}}
    <div class="space-y-6">

        {{-- Welcome/Overview (Optional) --}}
        {{-- <div class="bg-white rounded-lg shadow-lg border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800">خوش آمدید، {{ Auth::guard('admin')->user()->name }}!</h3>
            <p class="text-gray-600 mt-1">اینجا می‌توانید بخش‌های مختلف سیستم را مدیریت کنید.</p>
        </div> --}}

        {{-- Management Cards Row --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            {{-- Customers Card --}}
            <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden flex flex-col">
                <div class="p-6">
                    <h4 class="text-lg font-semibold text-gray-800 mb-2">مدیریت مشتریان</h4>
                    <p class="text-sm text-gray-600 mb-4">مشاهده، ایجاد و ویرایش اطلاعات مشتریان ثبت شده در سیستم.</p>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 mt-auto text-sm flex justify-between items-center">
                     <a href="{{ route('admin.customers.index') }}" class="btn-gold-app !py-1.5 !px-3 !text-xs">
                         <i class="fas fa-list mr-1 ml-1"></i> لیست مشتریان
                     </a>
                    <a href="{{ route('admin.customers.create') }}" class="btn-blue-app !py-1.5 !px-3 !text-xs">
                        <i class="fas fa-plus mr-1 ml-1"></i>افزودن مشتری
                    </a>
                </div>
            </div>

            {{-- Systems Card --}}
            <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden flex flex-col">
                 <div class="p-6">
                    <h4 class="text-lg font-semibold text-gray-800 mb-2">مدیریت سامانه‌ها</h4>
                    <p class="text-sm text-gray-600 mb-4">مشاهده، ایجاد و ویرایش سامانه‌های ثبت شده برای مشتریان.</p>
                 </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 mt-auto text-sm flex justify-between items-center">
                    <a href="{{ route('admin.systems.index') }}" class="btn-gold-app !py-1.5 !px-3 !text-xs">
                        <i class="fas fa-desktop mr-1 ml-1"></i> لیست سامانه‌ها
                    </a>
                    <a href="{{ route('admin.systems.create') }}" class="btn-blue-app !py-1.5 !px-3 !text-xs">
                         <i class="fas fa-plus mr-1 ml-1"></i> افزودن سامانه
                     </a>
                </div>
            </div>

            {{-- Licenses Card --}}
            <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden flex flex-col">
                <div class="p-6">
                    <h4 class="text-lg font-semibold text-gray-800 mb-2">مدیریت لایسنس‌ها</h4>
                    <p class="text-sm text-gray-600 mb-4">مشاهده، ایجاد و مدیریت لایسنس‌های نرم‌افزار برای سامانه‌ها.</p>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 mt-auto text-sm flex justify-between items-center">
                     <a href="{{ route('admin.licenses.index') }}" class="btn-gold-app !py-1.5 !px-3 !text-xs">
                         <i class="fas fa-key mr-1 ml-1"></i> لیست لایسنس‌ها
                     </a>
                     {{-- Use gold button for creating licenses --}}
                    <a href="{{ route('admin.licenses.create') }}" class="btn-blue-app !py-1.5 !px-3 !text-xs">
                         <i class="fas fa-plus mr-1 ml-1"></i> ایجاد لایسنس
                     </a>
                </div>
            </div>

        </div> {{-- End Grid --}}

        {{-- Add other sections or stats here if needed --}}

    </div> {{-- End Main Content Area --}}
</x-app-layout>