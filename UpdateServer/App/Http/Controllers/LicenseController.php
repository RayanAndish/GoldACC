@extends('admin.layout') {{-- فرض بر اینکه یک layout اصلی برای ادمین دارید --}}

@section('content')
<div class="container">
    <h1>ایجاد لایسنس جدید</h1>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form action="{{ route('admin.licenses.store') }}" method="POST">
        @csrf

        <div class="mb-3">
            <label for="system_id" class="form-label">سیستم مشتری <span class="text-danger">*</span></label>
            <select class="form-select @error('system_id') is-invalid @enderror" id="system_id" name="system_id" required>
                <option value="">-- انتخاب سیستم --</option>
                @foreach($systems as $system)
                    <option value="{{ $system->id }}" {{ old('system_id') == $system->id ? 'selected' : '' }}>
                        {{ $system->name }} ({{ $system->domain }}) - مشتری: {{ $system->customer->name ?? 'نامشخص' }}
                    </option>
                @endforeach
            </select>
            @error('system_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="hardware_id" class="form-label">شناسه سخت‌افزار (Hardware ID) <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('hardware_id') is-invalid @enderror" id="hardware_id" name="hardware_id" value="{{ old('hardware_id') }}" required>
             @error('hardware_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

         <div class="mb-3">
            <label for="request_code" class="form-label">کد درخواست فعال‌سازی (Request Code) <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('request_code') is-invalid @enderror" id="request_code" name="request_code" value="{{ old('request_code') }}" required>
             @error('request_code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="ip_address" class="form-label">آدرس IP (اختیاری)</label>
            <input type="text" class="form-control @error('ip_address') is-invalid @enderror" id="ip_address" name="ip_address" value="{{ old('ip_address') }}">
            @error('ip_address')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="license_type" class="form-label">نوع لایسنس <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('license_type') is-invalid @enderror" id="license_type" name="license_type" value="{{ old('license_type', 'standard') }}" required>
            @error('license_type')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="expires_at" class="form-label">تاریخ انقضا (اختیاری)</label>
            <input type="date" class="form-control @error('expires_at') is-invalid @enderror" id="expires_at" name="expires_at" value="{{ old('expires_at') }}">
            @error('expires_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="features" class="form-label">ویژگی‌ها (جدا شده با کاما ,)</label>
            <textarea class="form-control @error('features') is-invalid @enderror" id="features" name="features" rows="3">{{ old('features') }}</textarea>
            <div class="form-text">مثال: feature1,feature2,another_feature</div>
            @error('features')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">تولید لایسنس</button>
        <a href="{{ route('admin.licenses.index') }}" class="btn btn-secondary">انصراف</a>
    </form>
</div>
@endsection