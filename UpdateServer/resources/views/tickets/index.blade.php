<x-app-layout>
    <x-slot name="header">
        {{ __('درخواست‌های پشتیبانی من') }}
    </x-slot>

    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
         {{-- Card Header --}}
         <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
             <h4 class="text-lg font-semibold text-gray-700">لیست درخواست‌ها</h4>
             <a href="{{ route('tickets.create') }}" class="btn-gold-app text-sm">
                 <i class="fas fa-plus mr-1 ml-1"></i> ثبت درخواست جدید
             </a>
         </div>

         {{-- Flash Messages --}}
         <div class="p-6 pb-0">
             @include('layouts.partials.flash-messages')
         </div>

         <div class="p-6">
            @if (isset($tickets) && !$tickets->isEmpty())
                <div class="overflow-x-auto relative shadow-md sm:rounded-lg border border-gray-200">
                    <table class="w-full text-sm text-right text-gray-600">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                            <tr>
                                <th scope="col" class="py-3 px-6">#</th>
                                <th scope="col" class="py-3 px-6">موضوع</th>
                                <th scope="col" class="py-3 px-6">وضعیت</th>
                                <th scope="col" class="py-3 px-6">اولویت</th>
                                <th scope="col" class="py-3 px-6">تاریخ ثبت</th>
                                <th scope="col" class="py-3 px-6">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tickets as $ticket)
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <th scope="row" class="py-4 px-6 font-medium text-gray-900 whitespace-nowrap">{{ $ticket->id }}</th>
                                <td class="py-4 px-6">{{ $ticket->subject }}</td>
                                <td class="py-4 px-6">
                                    @php
                                        $statusClass = match($ticket->status) {
                                            'open' => 'bg-blue-100 text-blue-800',
                                            'in_progress' => 'bg-yellow-100 text-yellow-800',
                                            'answered' => 'bg-purple-100 text-purple-800',
                                            'closed' => 'bg-gray-100 text-gray-800',
                                            'resolved' => 'bg-green-100 text-green-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 rounded text-xs font-medium {{ $statusClass }}">
                                        {{ $ticket->translated_status ?? ucfirst($ticket->status) }} {{-- Assuming translated_status accessor exists --}}
                                    </span>
                                </td>
                                 <td class="py-4 px-6">
                                      @php
                                        $priorityClass = match($ticket->priority) {
                                            'low' => 'bg-gray-100 text-gray-600',
                                            'medium' => 'bg-orange-100 text-orange-700',
                                            'high' => 'bg-red-100 text-red-700',
                                            'critical' => 'bg-purple-100 text-purple-800',
                                            default => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 rounded text-xs font-medium {{ $priorityClass }}">
                                        {{ $ticket->translated_priority ?? ucfirst($ticket->priority) }} {{-- Assuming translated_priority accessor exists --}}
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-gray-500 whitespace-nowrap">
                                     {{ $ticket->jalali_created_at ?? '-'}} {{-- Use Jalali Accessor --}}
                                 </td>
                                <td class="py-4 px-6 text-center whitespace-nowrap">
                                    <a href="{{ route('tickets.show', $ticket->id) }}" class="font-medium text-cyan-600 hover:underline text-xs px-1" title="مشاهده جزئیات"><i class="fas fa-eye"></i></a>
                                    {{-- Add other user actions if needed (e.g., close) --}}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-gray-500">شما هنوز درخواستی ثبت نکرده‌اید.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Pagination links --}}
                <div class="mt-6">
                    {{ $tickets->links() }}
                </div>
            @else
                 <p class="text-center text-gray-500 py-4">شما هنوز درخواستی ثبت نکرده‌اید.</p>
            @endif
        </div>
    </div>
</x-app-layout>