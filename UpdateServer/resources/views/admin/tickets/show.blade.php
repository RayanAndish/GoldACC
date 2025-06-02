{{-- File: resources/views/admin/tickets/show.blade.php (Reviewed) --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('جزئیات درخواست پشتیبانی') }} #{{ $ticket->id }} - {{ $ticket->subject }}
    </x-slot>

    <div class="space-y-6">

        {{-- Success/Error Messages --}}
        @include('layouts.partials.flash-messages')

        {{-- Ticket Details & History Card --}}
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
             {{-- Card Header --}}
             <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
                 <h4 class="text-lg font-semibold text-gray-700">موضوع: {{ $ticket->subject }}</h4>
                 <div class="text-sm text-gray-600 flex flex-wrap items-center gap-x-4 gap-y-1">
                     <span>مشتری: <span class="font-medium text-gray-800">{{ $ticket->user->name ?? 'ناشناس' }}</span></span>
                     <span>وضعیت: <span class="font-semibold">
                        @switch($ticket->status)
                            @case('open') <span class="text-blue-700">باز</span> @break
                            @case('in_progress') <span class="text-yellow-700">در حال بررسی</span> @break
                            @case('answered') <span class="text-purple-700">پاسخ داده شده</span> @break
                            @case('closed') <span class="text-gray-700">بسته شده</span> @break
                            @case('resolved') <span class="text-green-700">حل شده</span> @break
                            @default {{ ucfirst($ticket->status) }}
                        @endswitch
                    </span></span>
                    <span>اولویت: <span class="font-semibold">
                        @switch($ticket->priority)
                            @case('low') <span class="text-gray-700">پایین</span> @break
                            @case('medium') <span class="text-orange-700">متوسط</span> @break
                            @case('high') <span class="text-red-700">بالا</span> @break
                            @case('critical') <span class="text-purple-700">بحرانی</span> @break
                            @default {{ ucfirst($ticket->priority) }}
                        @endswitch
                    </span></span>
                 </div>
             </div>

             {{-- Message History --}}
             <div class="p-6 space-y-6">
                 {{-- Original User Message --}}
                 <div class="flex space-x-3 space-x-reverse">
                     <div class="flex-shrink-0">
                          <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-gray-200 text-gray-600" title="{{ $ticket->user->name ?? 'کاربر' }}">
                             <i class="fas fa-user"></i> {{-- Make sure Font Awesome is included in your project --}}
                         </span>
                     </div>
                     <div class="flex-1 bg-gray-100 rounded-lg p-4 border border-gray-200">
                         <div class="flex items-center justify-between mb-2">
                             <h5 class="text-sm font-semibold text-gray-800">{{ $ticket->user->name ?? 'کاربر' }}</h5>
                             <span class="text-xs text-gray-500" title="{{ $ticket->created_at->format('Y/m/d H:i:s') ?? '' }}">
                                 {{-- Use Jalali Accessor --}}
                                 {{ $ticket->jalali_created_at ?? '-' }}
                             </span>
                         </div>
                         <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $ticket->message }}</p>
                     </div>
                 </div>

                 {{-- Replies --}}
                 @forelse ($ticket->replies as $reply)
                     {{-- Determine alignment and styling based on sender --}}
                     @php
                         $isFromAdmin = $reply->admin_id !== null; // Check if admin_id is set
                         $bubbleClasses = $isFromAdmin ? 'bg-yellow-50 border-yellow-200 max-w-xl ml-auto' : 'bg-gray-100 border-gray-200 max-w-xl';
                         $nameClasses = $isFromAdmin ? 'text-sm font-semibold text-yellow-800' : 'text-sm font-semibold text-gray-800';
                         $timeClasses = $isFromAdmin ? 'text-xs text-yellow-600' : 'text-xs text-gray-500';
                         $avatarWrapperClasses = $isFromAdmin ? 'flex-shrink-0 order-last ml-3' : 'flex-shrink-0 mr-3';
                         $avatarClasses = $isFromAdmin ? 'inline-flex items-center justify-center h-10 w-10 rounded-full bg-yellow-100 text-yellow-700' : 'inline-flex items-center justify-center h-10 w-10 rounded-full bg-gray-200 text-gray-600';
                         $avatarIcon = $isFromAdmin ? 'fas fa-user-shield' : 'fas fa-user';
                         $senderName = $isFromAdmin ? ($reply->admin->name ?? 'ادمین') : ($reply->user->name ?? 'کاربر');
                         $containerClasses = $isFromAdmin ? 'flex justify-end' : 'flex';
                     @endphp

                     <div class="{{ $containerClasses }}">
                         {{-- Avatar (order changes based on sender) --}}
                         @if (!$isFromAdmin)
                         <div class="{{ $avatarWrapperClasses }}">
                              <span class="{{ $avatarClasses }}" title="{{ $senderName }}">
                                 <i class="{{ $avatarIcon }}"></i>
                             </span>
                         </div>
                         @endif

                         {{-- Message Bubble --}}
                         <div class="flex-1 {{ $bubbleClasses }} rounded-lg p-4 border">
                             <div class="flex items-center justify-between mb-2">
                                 <h5 class="{{ $nameClasses }}">{{ $senderName }}</h5>
                                 <span class="{{ $timeClasses }}" title="{{ $reply->created_at->format('Y/m/d H:i:s') ?? '' }}">
                                     {{-- Use Jalali Accessor for reply --}}
                                     {{ $reply->jalali_created_at ?? '-' }}
                                 </span>
                             </div>
                             <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $reply->message }}</p>
                         </div>

                         {{-- Avatar (order changes based on sender) --}}
                          @if ($isFromAdmin)
                         <div class="{{ $avatarWrapperClasses }}">
                              <span class="{{ $avatarClasses }}" title="{{ $senderName }}">
                                 <i class="{{ $avatarIcon }}"></i>
                             </span>
                         </div>
                         @endif
                     </div>
                 @empty
                     <p class="text-center text-sm text-gray-500 py-4">هنوز پاسخی برای این درخواست ثبت نشده است.</p>
                 @endforelse

             </div> {{-- End Message History --}}
        </div> {{-- End Ticket Details Card --}}


        {{-- Admin Action Card (Reply / Status Change) --}}
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden p-6">
            <h4 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">اقدام / پاسخ ادمین</h4>

            <form action="{{ route('admin.tickets.update', $ticket) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- Admin Reply Textarea --}}
                <div class="mb-4">
                    <label for="admin_reply" class="block text-sm font-medium text-gray-700 mb-1">ثبت پاسخ جدید:</label>
                    <textarea name="admin_reply" id="admin_reply" rows="5"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm @error('admin_reply') border-red-500 @enderror"
                              placeholder="پاسخ خود را اینجا بنویسید...">{{ old('admin_reply') }}</textarea>
                     @error('admin_reply')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                     @enderror
                </div>

                 {{-- Status Change --}}
                <div class="mb-6">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">تغییر وضعیت تیکت:</label>
                    <select name="status" id="status" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm">
                        {{-- Keep values in English, display text in Farsi --}}
                        <option value="open" {{ old('status', $ticket->status) == 'open' ? 'selected' : '' }}>باز</option>
                        <option value="in_progress" {{ old('status', $ticket->status) == 'in_progress' ? 'selected' : '' }}>در حال بررسی</option>
                        <option value="answered" {{ old('status', $ticket->status) == 'answered' ? 'selected' : '' }}>پاسخ داده شده</option>
                        <option value="closed" {{ old('status', $ticket->status) == 'closed' ? 'selected' : '' }}>بسته شده</option>
                        <option value="resolved" {{ old('status', $ticket->status) == 'resolved' ? 'selected' : '' }}>حل شده</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                 <div class="flex justify-end items-center">
                     <a href="{{ route('admin.tickets.index') }}" class="text-sm text-gray-600 hover:text-gray-800 ml-4">
                         بازگشت به لیست
                     </a>
                     <button type="submit" class="btn-blue-app text-sm">
                         <i class="fas fa-save mr-1 ml-1"></i>
                         ثبت پاسخ و ذخیره وضعیت
                     </button>
                 </div>
            </form>
        </div> {{-- End Admin Action Card --}}

    </div> {{-- End Main Content Wrapper --}}

     {{-- Add this script if you haven't included Font Awesome globally --}}
    {{-- @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/js/all.min.js"></script>
    @endpush --}}

</x-app-layout>