{{-- File: resources/views/admin/systems/index.blade.php (Using App Layout & Tailwind) --}}
<x-app-layout>
    <x-slot name="header">
        {{ __('لیست سامانه‌ها') }}
    </x-slot>

    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
         {{-- Card Header --}}
         <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
             <h4 class="text-lg font-semibold text-gray-700">لیست سامانه‌ها</h4>
             {{-- Ensure 'admin.systems.create' route exists --}}
             <a href="{{ route('admin.systems.create') }}" class="btn-gold-app text-sm">
                 <i class="fas fa-plus mr-1 ml-1"></i> افزودن سامانه جدید
             </a>
         </div>

         {{-- Flash Messages --}}
         @include('layouts.partials.flash-messages')

         <div class="p-6">
            @if ($systems->isEmpty())
                <p class="text-center text-gray-500 py-4">هیچ سامانه‌ای یافت نشد.</p>
            @else
                <div class="overflow-x-auto relative shadow-md sm:rounded-lg border border-gray-200">
                    <table class="w-full text-sm text-right text-gray-600">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                            <tr>
                                <th scope="col" class="py-3 px-6">#</th>
                                <th scope="col" class="py-3 px-6">نام</th>
                                <th scope="col" class="py-3 px-6">دامنه</th>
                                <th scope="col" class="py-3 px-6">وضعیت</th>
                                <th scope="col" class="py-3 px-6">نسخه فعلی</th>
                                <th scope="col" class="py-3 px-6">مشتری</th>
                                <th scope="col" class="py-3 px-6">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Loop through systems passed from controller --}}
                            @foreach($systems as $system)
                            <tr class="bg-white border-b hover:bg-gray-50">
                                {{-- Use loop iteration or system ID --}}
                                <th scope="row" class="py-4 px-6 font-medium text-gray-900 whitespace-nowrap">{{ $system->id }}</th>
                                <td class="py-4 px-6">{{ $system->name }}</td>
                                <td class="py-4 px-6">{{ $system->domain }}</td>
                                <td class="py-4 px-6">
                                    {{-- Simple status display - customize as needed --}}
                                    @if($system->status == 'active')
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">فعال</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">غیرفعال</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6">{{ $system->current_version ?? '-' }}</td>
                                {{-- Eager load customer relationship in controller: $systems = System::with('customer')->... --}}
                                <td class="py-4 px-6">{{ $system->customer->name ?? 'اختصاص نیافته' }}</td>
                                <td class="py-4 px-6 text-center whitespace-nowrap">
                                    {{-- Ensure routes 'admin.systems.edit', 'admin.systems.destroy' exist --}}
                                    <a href="{{ route('admin.systems.edit', $system) }}" class="font-medium text-blue-600 hover:underline text-xs px-1">ویرایش</a>
                                    <form action="{{ route('admin.systems.destroy', $system) }}" method="POST" onsubmit="return confirm('آیا از حذف این سامانه مطمئن هستید؟');" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-medium text-red-600 hover:underline text-xs px-1">حذف</button>
                                    </form>
                                    {{-- Add other actions if needed --}}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Pagination links - ensure they are styled for Tailwind if needed --}}
                <div class="mt-6">
                    {{ $systems->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>