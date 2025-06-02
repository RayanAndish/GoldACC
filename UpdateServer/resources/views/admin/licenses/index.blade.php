{{-- File: resources/views/admin/licenses/index.blade.php (Using App Layout & Tailwind) --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('لیست لایسنس‌ها') }}
    </x-slot>

    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
         {{-- Card Header --}}
         <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
             <h4 class="text-lg font-semibold text-gray-700">لیست لایسنس‌ها</h4>
             {{-- Ensure 'admin.licenses.create' route exists --}}
             <a href="{{ route('admin.licenses.create') }}" class="btn-gold-app text-sm">
                 <i class="fas fa-plus mr-1 ml-1"></i> افزودن لایسنس جدید
             </a>
         </div>

         {{-- Flash Messages - Using the standard partial --}}
         <div class="p-6 space-y-3"> 
            @include('layouts.partials.flash-messages')

            {{-- Specific message for generated license key --}}
            @if(session('generated_license_key'))
                <div class="p-4 mb-4 text-sm text-blue-700 bg-blue-100 rounded-lg border border-blue-200" role="alert">
                    <p class="font-semibold">کلید لایسنس تولید شده:</p>
                    <p class="mt-1 font-mono text-xs break-all">{{ session('generated_license_key') }}</p>
                    <p class="mt-1 text-xs">این کلید را کپی کرده و به مشتری بدهید.</p>
                </div>
            @endif
         </div>

         <div class="p-6 pt-0">
            {{-- Check if $licenses variable is passed and not empty --}}
            @if (isset($licenses) && !$licenses->isEmpty())
                <div class="overflow-x-auto relative shadow-md sm:rounded-lg border border-gray-200">
                    <table class="w-full text-sm text-right text-gray-600">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                            <tr>
                                <th scope="col" class="py-3 px-6">#</th>
                                <th scope="col" class="py-3 px-6">کلید نمایشی</th>
                                <th scope="col" class="py-3 px-6">سیستم (دامنه)</th>
                                <th scope="col" class="py-3 px-6">مشتری</th>
                                <th scope="col" class="py-3 px-6">وضعیت</th>
                                <th scope="col" class="py-3 px-6">نوع</th>
                                <th scope="col" class="py-3 px-6">تاریخ انقضا</th>
                                <th scope="col" class="py-3 px-6">تاریخ ایجاد</th>
                                <th scope="col" class="py-3 px-6">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($licenses as $license)
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <th scope="row" class="py-4 px-6 font-medium text-gray-900 whitespace-nowrap">{{ $license->id }}</th>
                                <td class="py-4 px-6 font-mono text-xs">{{ $license->license_key_display ?? 'N/A' }}</td>
                                <td class="py-4 px-6">
                                    @if($license->system)
                                        {{-- Ensure 'admin.systems.edit' route exists --}}
                                        <a href="{{ route('admin.systems.edit', $license->system_id) }}" class="text-blue-600 hover:underline" title="{{ $license->system->domain }}">
                                            {{ $license->system->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-400 text-xs italic">سیستم حذف شده</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6">
                                    @if($license->system && $license->system->customer)
                                         {{-- Ensure 'admin.customers.edit' route exists --}}
                                        <a href="{{ route('admin.customers.edit', $license->system->customer_id) }}" class="text-blue-600 hover:underline">
                                            {{ $license->system->customer->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-400 text-xs italic">نامشخص</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6">
                                    @php
                                        $statusClass = match($license->status) {
                                            'active' => 'bg-green-100 text-green-800',
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'expired' => 'bg-gray-100 text-gray-800',
                                            default => 'bg-red-100 text-red-800', // danger for revoked or other statuses
                                        };
                                        $isPastExpiry = $license->expires_at && $license->expires_at->isPast() && $license->status != 'expired';
                                    @endphp
                                    <span class="px-2 py-0.5 rounded text-xs font-medium {{ $statusClass }}">
                                        {{ ucfirst($license->status) }}
                                    </span>
                                     @if($isPastExpiry)
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 ml-1">منقضی شده</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6">{{ $license->license_type ?? 'standard' }}</td>
                                <td class="py-4 px-6 text-gray-500 whitespace-nowrap">{{ $license->jalali_expires_at ?? 'بدون انقضا' }}</td>
                                <td class="py-4 px-6 text-gray-500 whitespace-nowrap">{{ $license->jalali_created_at ?? '-'}}</td>
                                <td class="py-4 px-6 text-center whitespace-nowrap">
                                    {{-- Ensure routes exist --}}
                                    <a href="{{ route('admin.licenses.show', $license->id) }}" class="font-medium text-cyan-600 hover:underline text-xs px-1" title="مشاهده جزئیات"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('admin.licenses.edit', $license->id) }}" class="font-medium text-blue-600 hover:underline text-xs px-1" title="ویرایش"><i class="fas fa-edit"></i></a>
                                    <form action="{{ route('admin.licenses.destroy', $license->id) }}" method="POST" onsubmit="return confirm('آیا از حذف این لایسنس اطمینان دارید؟');" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-medium text-red-600 hover:underline text-xs px-1" title="حذف"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-gray-500">هیچ لایسنسی یافت نشد.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Pagination links --}}
                <div class="mt-6">
                    {{ $licenses->links() }} {{-- Ensure pagination is styled for Tailwind --}}
                </div>
            @else
                 {{-- This case might not be needed if using @forelse, but kept for safety --}}
                <p class="text-center text-gray-500 py-4">هیچ لایسنسی یافت نشد.</p>
            @endif
        </div>
    </div>
    {{-- Include Font Awesome if needed globally in app.blade.php --}}
</x-app-layout>