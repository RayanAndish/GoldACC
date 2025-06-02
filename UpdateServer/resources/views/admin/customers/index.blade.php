{{-- File: resources/views/admin/customers/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('لیست مشتریان') }}
    </x-slot>

    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
         {{-- Card Header --}}
         <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
             <h4 class="text-lg font-semibold text-gray-700">لیست مشتریان</h4>
             <a href="{{ route('admin.customers.create') }}" class="btn-gold-app text-sm">
                 <i class="fas fa-plus mr-1 ml-1"></i> افزودن مشتری جدید
             </a>
         </div>

         {{-- Flash Messages --}}
         @include('layouts.partials.flash-messages')

         <div class="p-6">
            @if ($customers->isEmpty())
                <p class="text-center text-gray-500 py-4">هیچ مشتری یافت نشد.</p>
            @else
                <div class="overflow-x-auto relative shadow-md sm:rounded-lg border border-gray-200">
                    <table class="w-full text-sm text-right text-gray-600">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                            <tr>
                                <th scope="col" class="py-3 px-6">#</th>
                                <th scope="col" class="py-3 px-6">نام مشتری</th>
                                <th scope="col" class="py-3 px-6">ایمیل</th>
                                <th scope="col" class="py-3 px-6">تلفن</th>
                                <th scope="col" class="py-3 px-6">کاربر متصل</th> {{-- New Column --}}
                                <th scope="col" class="py-3 px-6">تاریخ ثبت</th>
                                <th scope="col" class="py-3 px-6">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $customer)
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <th scope="row" class="py-4 px-6 font-medium text-gray-900 whitespace-nowrap">{{ $customer->id }}</th>
                                    <td class="py-4 px-6">{{ $customer->name }}</td>
                                    <td class="py-4 px-6">{{ $customer->email }}</td>
                                    <td class="py-4 px-6">{{ $customer->phone ?? '-' }}</td>
                                    {{-- Display Linked User --}}
                                    <td class="py-4 px-6">
                                        @if ($customer->user)
                                            <span class="flex items-center" title="{{ $customer->user->email }}">
                                                <i class="fas fa-user text-gray-400 mr-1 ml-1 text-xs"></i>
                                                {{ $customer->user->name }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-xs italic">--</span>
                                        @endif
                                    </td>
                                    <td class="py-4 px-6 text-gray-500 whitespace-nowrap">{{ $customer->created_at->format('Y/m/d') }}</td>
                                    <td class="py-4 px-6 text-center whitespace-nowrap">
                                        {{-- Edit Button --}}
                                        <a href="{{ route('admin.customers.edit', $customer) }}" class="font-medium text-blue-600 hover:underline text-xs px-1">ویرایش</a>
                                        {{-- Delete Button/Form --}}
                                        <form action="{{ route('admin.customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('آیا از حذف این مشتری مطمئن هستید؟');" class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-medium text-red-600 hover:underline text-xs px-1">حذف</button>
                                        </form>
                                         {{-- Potentially add a 'Show' link later --}}
                                         {{-- <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium text-green-600 hover:underline text-xs px-1">جزئیات</a> --}}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">
                    {{ $customers->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>