{{-- File: resources/views/admin/licenses/show.blade.php (Using App Layout & Tailwind) --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('جزئیات لایسنس #') }}{{ $license->id }}
    </x-slot>

    <div class="space-y-6">

        {{-- Main Info Card --}}
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
             <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
                 <h4 class="text-lg font-semibold text-gray-700">اطلاعات اصلی</h4>
                 <div>
                    {{-- Ensure routes exist --}}
                    <a href="{{ route('admin.licenses.edit', $license->id) }}" class="btn-blue-app text-xs mr-2">
                         <i class="fas fa-edit mr-1 ml-1"></i>ویرایش
                     </a>
                    <a href="{{ route('admin.licenses.index') }}" class="text-sm text-gray-600 hover:text-gray-800">
                         بازگشت به لیست
                     </a>
                </div>
             </div>
             <div class="p-6">
                 <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                     <div class="col-span-1 font-medium text-gray-600">شناسه لایسنس:</div>
                     <dd class="md:col-span-2 text-gray-800">{{ $license->id }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">کلید نمایشی:</div>
                     <dd class="md:col-span-2 font-mono text-xs break-all text-gray-800">{{ $license->license_key_display ?? 'N/A' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">وضعیت:</div>
                     <dd class="md:col-span-2">
                          @php
                            $statusClass = match($license->status) {
                                'active' => 'bg-green-100 text-green-800',
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'expired' => 'bg-gray-100 text-gray-800',
                                default => 'bg-red-100 text-red-800',
                            };
                          @endphp
                         <span class="px-2 py-0.5 rounded text-xs font-medium {{ $statusClass }}">
                             {{ ucfirst($license->status) }}
                         </span>
                     </dd>

                     <div class="col-span-1 font-medium text-gray-600">نوع لایسنس:</div>
                     <dd class="md:col-span-2 text-gray-800">{{ $license->license_type ?? 'standard' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">تاریخ انقضا:</div>
                     <dd class="md:col-span-2 text-gray-800">{{ $license->jalali_expires_at ?? 'بدون انقضا' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">تاریخ فعال‌سازی:</div>
                     <dd class="md:col-span-2 text-gray-800">{{ $license->jalali_activated_at ?? 'هنوز فعال نشده' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">تاریخ ایجاد:</div>
                     <dd class="md:col-span-2 text-gray-800">{{ $license->jalali_created_at ?? '-' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">آخرین به‌روزرسانی:</div>
                     <dd class="md:col-span-2 text-gray-800">{{ $license->jalali_updated_at ?? '-' }}</dd>
                 </dl>
            </div>
        </div>

        {{-- System & Customer Info Card --}}
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
             <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                 <h4 class="text-base font-semibold text-gray-700">اطلاعات سیستم و مشتری</h4>
            </div>
             <div class="p-6">
                @if($license->system)
                    {{-- Eager load system.customer in controller --}}
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                        <div class="col-span-1 font-medium text-gray-600">نام سیستم:</div>
                        <dd class="md:col-span-2 text-gray-800">
                            <a href="{{ route('admin.systems.edit', $license->system_id) }}" class="text-blue-600 hover:underline">{{ $license->system->name }}</a>
                        </dd>

                        <div class="col-span-1 font-medium text-gray-600">دامنه سیستم:</div>
                        <dd class="md:col-span-2 text-gray-800">{{ $license->system->domain }}</dd>

                        @if($license->system->customer)
                            <div class="col-span-1 font-medium text-gray-600">نام مشتری:</div>
                            <dd class="md:col-span-2 text-gray-800">
                                <a href="{{ route('admin.customers.edit', $license->system->customer_id) }}" class="text-blue-600 hover:underline">{{ $license->system->customer->name }}</a>
                            </dd>
                            <div class="col-span-1 font-medium text-gray-600">ایمیل مشتری:</div>
                            <dd class="md:col-span-2 text-gray-800">{{ $license->system->customer->email }}</dd>
                        @else
                            <div class="col-span-1 font-medium text-gray-600">مشتری:</div>
                            <dd class="md:col-span-2 text-gray-400 italic">مشتری مرتبط یافت نشد</dd>
                        @endif
                    </dl>
                @else
                    <p class="text-sm text-gray-500 italic">سیستم مرتبط با این لایسنس یافت نشد (ممکن است حذف شده باشد).</p>
                @endif
            </div>
        </div>

         {{-- Technical Info & Features Card --}}
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
             <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                 <h4 class="text-base font-semibold text-gray-700">اطلاعات فنی و ویژگی‌ها</h4>
            </div>
             <div class="p-6">
                 <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                     <div class="col-span-1 font-medium text-gray-600">هش کلید لایسنس:</div>
                     <dd class="md:col-span-2 font-mono text-xs break-all text-gray-800">{{ $license->license_key_hash ?? 'N/A' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">Salt:</div>
                     <dd class="md:col-span-2 font-mono text-xs break-all text-gray-800">{{ $license->salt ?? 'N/A' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">هش شناسه سخت‌افزار:</div>
                     <dd class="md:col-span-2 font-mono text-xs break-all text-gray-800">{{ $license->hardware_id_hash ?? 'N/A' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">هش کد درخواست:</div>
                     <dd class="md:col-span-2 font-mono text-xs break-all text-gray-800">{{ $license->request_code_hash ?? 'N/A' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">هش IP:</div>
                     <dd class="md:col-span-2 font-mono text-xs break-all text-gray-800">{{ $license->ip_hash ?? 'ثبت نشده' }}</dd>

                     <div class="col-span-1 font-medium text-gray-600">ویژگی‌ها:</div>
                     <dd class="md:col-span-2">
                        @if(!empty($license->features))
                            <div class="flex flex-wrap gap-1">
                                @foreach($license->features as $feature)
                                    {{-- Use a consistent badge style --}}
                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">{{ $feature }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-gray-400 italic">بدون ویژگی خاص</span>
                        @endif
                    </dd>
                 </dl>
            </div>
        </div>

        {{-- Delete Button Area --}}
         <div class="mt-6 flex justify-end">
              <form action="{{ route('admin.licenses.destroy', $license->id) }}" method="POST" onsubmit="return confirm('آیا از حذف این لایسنس اطمینان دارید؟ این عمل غیرقابل بازگشت است.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger-app text-sm"> {{-- Use a red button class --}}
                    <i class="fas fa-trash mr-1 ml-1"></i>
                    حذف لایسنس
                </button>
            </form>
        </div>

    </div>
</x-app-layout>