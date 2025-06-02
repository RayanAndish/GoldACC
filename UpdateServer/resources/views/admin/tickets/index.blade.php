{{-- File: resources/views/admin/tickets/index.blade.php (Slightly Refactored) --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('مدیریت درخواست‌های پشتیبانی') }}
    </x-slot>

    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
         {{-- Card Header --}}
         <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
             <h4 class="text-lg font-semibold text-gray-700">
                 لیست تمام درخواست‌ها
             </h4>
         </div>

         {{-- Flash Messages --}}
         <div class="p-6 pb-0">
             @include('layouts.partials.flash-messages')
         </div>

         <div class="p-6">
            <div class="overflow-x-auto relative shadow-md sm:rounded-lg border border-gray-200">
                <table class="w-full text-sm text-right text-gray-600">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                        <tr>
                            <th scope="col" class="py-3 px-6">#</th>
                            <th scope="col" class="py-3 px-6">موضوع</th>
                            <th scope="col" class="py-3 px-6">مشتری</th>
                            <th scope="col" class="py-3 px-6">وضعیت</th>
                            <th scope="col" class="py-3 px-6">اولویت</th>
                            <th scope="col" class="py-3 px-6">تاریخ ثبت</th>
                            <th scope="col" class="py-3 px-6">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Use @forelse for cleaner empty state handling --}}
                        @forelse ($tickets as $ticket)
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <th scope="row" class="py-4 px-6 font-medium text-gray-900 whitespace-nowrap">{{ $ticket->id }}</th>
                                <td class="py-4 px-6">{{ $ticket->subject }}</td>
                                <td class="py-4 px-6">{{ $ticket->user->name ?? 'کاربر حذف شده' }}</td>
                                <td class="py-4 px-6">
                                     {{-- Keep @switch for background colors --}}
                                     <span class="px-2 py-0.5 rounded text-xs font-bold whitespace-nowrap
                                        @switch($ticket->status)
                                            @case('open') bg-blue-100 text-blue-800 @break @case('in_progress') bg-yellow-100 text-yellow-800 @break
                                            @case('answered') bg-purple-100 text-purple-800 @break @case('closed') bg-gray-100 text-gray-800 @break
                                            @case('resolved') bg-green-100 text-green-800 @break @default bg-gray-100 text-gray-800
                                        @endswitch
                                    ">
                                         {{-- Use accessor for translated text if available --}}
                                         {{ $ticket->translated_status ?? ucfirst($ticket->status) }}
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                     {{-- Keep @switch for background colors --}}
                                    <span class="px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap
                                        @switch($ticket->priority)
                                            @case('low') bg-gray-100 text-gray-600 @break @case('medium') bg-orange-100 text-orange-700 @break
                                            @case('high') bg-red-100 text-red-700 @break @case('critical') bg-purple-100 text-purple-800 @break
                                            @default bg-gray-100 text-gray-600
                                        @endswitch
                                    ">
                                         {{-- Use accessor for translated text if available --}}
                                         {{ $ticket->translated_priority ?? ucfirst($ticket->priority) }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-gray-500 whitespace-nowrap">
                                    {{ $ticket->jalali_created_at ?? '-' }} {{-- Already correct in your code --}}
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <a href="{{ route('admin.tickets.show', $ticket) }}" class="font-medium text-blue-600 hover:underline text-xs whitespace-nowrap">مشاهده/پاسخ</a>
                                    {{-- Delete form commented out --}}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-gray-500">هیچ درخواستی یافت نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{-- Pagination links --}}
            @if ($tickets->hasPages()) {{-- Check if pagination is needed --}}
                <div class="mt-6">
                    {{ $tickets->links() }}
                </div>
            @endif
         </div>
    </div>
</x-app-layout>