<x-app-layout>
    <x-slot name="header">
        {{ __('جزئیات درخواست:') }} #{{ $ticket->id }} - {{ $ticket->subject }}
    </x-slot>

    <div class="space-y-6">

        {{-- Flash Messages --}}
        <div class="px-6 pt-6"> {{ Display messages at the top }}
             @include('layouts.partials.flash-messages')
         </div>

        {{-- Ticket Details & History Card --}}
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden mx-6"> {{ Add horizontal margin }}
             {{-- Card Header --}}
             <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
                 <h4 class="text-lg font-semibold text-gray-700">موضوع: {{ $ticket->subject }}</h4>
                 <div class="text-sm text-gray-600 flex flex-wrap items-center gap-x-4 gap-y-1">
                     <span>وضعیت: <span class="font-semibold">
                         @php
                             $statusText = $ticket->translated_status ?? ucfirst($ticket->status);
                             $statusClass = match($ticket->status) {
                                 'open' => 'text-blue-700', 'in_progress' => 'text-yellow-700', 'answered' => 'text-purple-700',
                                 'closed' => 'text-gray-700', 'resolved' => 'text-green-700', default => 'text-gray-700',
                             };
                         @endphp
                         <span class="{{ $statusClass }}">{{ $statusText }}</span>
                     </span></span>
                    <span>اولویت: <span class="font-semibold">
                         @php
                             $priorityText = $ticket->translated_priority ?? ucfirst($ticket->priority);
                             $priorityClass = match($ticket->priority) {
                                'low' => 'text-gray-700', 'medium' => 'text-orange-700', 'high' => 'text-red-700', 'critical' => 'text-purple-700', default => 'text-gray-700',
                             };
                         @endphp
                         <span class="{{ $priorityClass }}">{{ $priorityText }}</span>
                    </span></span>
                 </div>
             </div>

             {{-- Message History --}}
             <div class="p-6 space-y-6">
                 {{-- Original User Message --}}
                 <div class="flex space-x-3 space-x-reverse">
                     <div class="flex-shrink-0">
                          <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-gray-200 text-gray-600" title="{{ $ticket->user->name ?? 'کاربر' }}">
                             <i class="fas fa-user"></i>
                         </span>
                     </div>
                     <div class="flex-1 bg-gray-100 rounded-lg p-4 border border-gray-200">
                         <div class="flex items-center justify-between mb-2">
                             <h5 class="text-sm font-semibold text-gray-800">شما</h5>
                             <span class="text-xs text-gray-500" title="{{ $ticket->created_at?->format('Y/m/d H:i:s') ?? '' }}">
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
                         $isFromAdmin = $reply->admin_id !== null;
                         $bubbleClasses = $isFromAdmin ? 'bg-yellow-50 border-yellow-200 max-w-xl ml-auto' : 'bg-gray-100 border-gray-200 max-w-xl';
                         $nameClasses = $isFromAdmin ? 'text-sm font-semibold text-yellow-800' : 'text-sm font-semibold text-gray-800';
                         $timeClasses = $isFromAdmin ? 'text-xs text-yellow-600' : 'text-xs text-gray-500';
                         $avatarWrapperClasses = $isFromAdmin ? 'flex-shrink-0 order-last ml-3' : 'flex-shrink-0 mr-3';
                         $avatarClasses = $isFromAdmin ? 'inline-flex items-center justify-center h-10 w-10 rounded-full bg-yellow-100 text-yellow-700' : 'inline-flex items-center justify-center h-10 w-10 rounded-full bg-gray-200 text-gray-600';
                         $avatarIcon = $isFromAdmin ? 'fas fa-user-shield' : 'fas fa-user';
                         $senderName = $isFromAdmin ? ($reply->admin->name ?? 'پشتیبانی') : 'شما'; // Use 'شما' for user
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
                                 <span class="{{ $timeClasses }}" title="{{ $reply->created_at?->format('Y/m/d H:i:s') ?? '' }}">
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

        {{-- Optional: User Reply Form or Close Ticket Button --}}
        {{-- Example: Close Ticket Button --}}
        {{--
        @if(!in_array($ticket->status, ['closed', 'resolved']))
            <div class="mx-6 mb-6 flex justify-end">
                <form action="{{-- route('tickets.close', $ticket->id) --}}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn-danger-app text-sm" onclick="return confirm('آیا از بستن این درخواست مطمئن هستید؟');">
                        بستن درخواست
                    </button>
                </form>
            </div>
        @endif
        --}}

        <div class="px-6 pb-6 text-left"> {{ Link back to list }}
             <a href="{{ route('tickets.index') }}" class="text-sm text-gray-600 hover:text-gray-800">
                 &larr; بازگشت به لیست درخواست‌ها
             </a>
        </div>

    </div>
</x-app-layout>
