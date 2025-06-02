{{-- File: resources/views/layouts/partials/flash-messages.blade.php --}}

@if ($message = Session::get('success'))
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mx-6 my-4 rounded-md shadow-sm" role="alert">
    <p class="font-bold">موفقیت</p>
    <p>{{ $message }}</p>
</div>
@endif

@if ($message = Session::get('error'))
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mx-6 my-4 rounded-md shadow-sm" role="alert">
    <p class="font-bold">خطا</p>
    <p>{{ $message }}</p>
</div>
@endif

@if ($message = Session::get('warning'))
<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mx-6 my-4 rounded-md shadow-sm" role="alert">
    <p class="font-bold">هشدار</p>
    <p>{{ $message }}</p>
</div>
@endif

@if ($message = Session::get('info'))
<div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mx-6 my-4 rounded-md shadow-sm" role="alert">
    <p class="font-bold">اطلاعات</p>
    <p>{{ $message }}</p>
</div>
@endif

{{-- Display validation errors if any --}}
@if ($errors->any())
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mx-6 my-4 rounded-md shadow-sm" role="alert">
    <p class="font-bold">لطفاً خطاهای زیر را برطرف کنید:</p>
    <ul class="mt-2 list-disc list-inside text-sm">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif