<x-app-layout>
    {{-- Set the header content for the layout (uses light background, dark+bold text) --}}
    <x-slot name="header">
        {{ __('پیشخوان کاربری') }}
    </x-slot>

    {{-- Main content area (dark background provided by app.blade.php body) --}}
    {{-- Set default text color for this scope to dark, suitable for light cards --}}
    <div class="text-gray-800">

        {{-- Welcome Message - Ensured light text on dark gradient --}}
        <div class="mb-6 p-4 bg-gradient-to-r from-gray-700 to-gray-800 rounded-lg shadow text-center border border-gray-600">
            {{-- Explicitly setting text color --}}
            <h3 class="text-xl font-semibold text-gray-100"> {{-- Increased size, ensured color --}}
                سلام <span class="font-bold" style="color: var(--clr-gold);">{{ Auth::user()->name }}</span>، عزیز ! به سامانه رایان طلا خوش آمدید
            </h3>
        </div>

        {{-- Display General Error Messages from Controller --}}
        @if (!empty($errorMessage))
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg shadow">
                <p>{!! nl2br(e($errorMessage)) !!}</p>
            </div>
        @endif

        {{-- Global Version Information Card --}}
        <div class="mb-6 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-700 flex items-center">
                    <i class="fas fa-cloud-download-alt mr-2 ml-1 text-yellow-500 fa-fw"></i>
                    آخرین نسخه نرم‌افزار
                </h4>
            </div>
            <div class="p-5">
                <div class="space-y-3 text-base text-gray-700">
                    <div class="flex justify-between items-center">
                        <strong class="font-medium text-gray-500">آخرین نسخه پایدار:</strong>
                        <span class="font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-600 text-sm">{{ $latest_stable_version ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <strong class="font-medium text-gray-500">تاریخ انتشار:</strong>
                        <span class="font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-600 text-sm">{{ $latest_version_release_date ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Systems Loop --}}
        @if (!empty($systemsData))
            <h3 class="text-xl font-semibold text-gray-700 mb-4 mt-8">سامانه‌های شما:</h3>
            <div class="grid grid-cols-1 lg:grid-cols-1 gap-6 mb-6"> {{-- Changed to 1 column for better layout per system --}}
                @foreach ($systemsData as $data)
                    @php
                        $system = $data['system'];
                        $activeLicense = $data['activeLicense'];
                        $clientVersion = $data['client_version'];
                    @endphp
                    <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                        {{-- System Header --}}
                        <div class="px-5 py-3 bg-gray-100 border-b border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-700 flex items-center justify-between">
                                <span>
                                    <i class="fas fa-desktop mr-2 ml-1 text-yellow-500 fa-fw"></i>
                                    {{ $system->name ?? 'سامانه بدون نام' }} ({{ $system->domain ?? 'N/A' }})
                                </span>
                                <span class="text-sm font-normal px-2 py-0.5 rounded bg-blue-100 text-blue-700">
                                   ID: {{ $system->id }}
                                </span>
                            </h4>
                        </div>

                        {{-- System & License Details --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-0"> {{-- Nested grid for side-by-side cards --}}
                            {{-- License Information Section for this System --}}
                            <div class="p-5 border-b md:border-b-0 md:border-r border-gray-200">
                                <h5 class="text-md font-semibold text-gray-600 mb-3 flex items-center">
                                    <i class="fas fa-check-circle mr-2 ml-1 text-gray-400 fa-fw"></i>
                                    اطلاعات لایسنس
                                </h5>
                                <div class="space-y-3 text-sm text-gray-700">
                                    @if($activeLicense)
                                        <div class="flex justify-between items-center">
                                            <strong class="font-medium text-gray-500">وضعیت:</strong>
                                            @php
                                                $licenseStatusText = match(strtolower($activeLicense->status ?? 'unknown')) {
                                                    'active' => 'فعال',
                                                    'expired' => 'منقضی شده',
                                                    'suspended' => 'معلق شده',
                                                    'pending' => 'در انتظار تایید',
                                                    'revoked' => 'باطل شده',
                                                    default => 'نامشخص',
                                                };
                                                $licenseStatusClass = match(strtolower($activeLicense->status ?? 'unknown')) {
                                                    'active' => 'bg-green-100 text-green-700',
                                                    'expired' => 'bg-red-100 text-red-700',
                                                    'suspended' => 'bg-yellow-100 text-yellow-700',
                                                    'pending' => 'bg-blue-100 text-blue-700',
                                                    'revoked' => 'bg-red-200 text-red-800',
                                                    default => 'bg-gray-100 text-gray-700',
                                                };
                                            @endphp
                                            <span class="px-2 py-0.5 rounded text-xs font-bold {{ $licenseStatusClass }}">
                                                {{ $licenseStatusText }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <strong class="font-medium text-gray-500">کلید لایسنس:</strong>
                                            <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded text-gray-600">
                                                @if(isset($activeLicense->license_key_display) && $activeLicense->license_key_display !== 'N/A' && strlen($activeLicense->license_key_display) > 10)
                                                    {{ substr($activeLicense->license_key_display, 0, 5) }}<span class="text-gray-400">...</span>{{ substr($activeLicense->license_key_display, -5) }}
                                                @elseif(isset($activeLicense->license_key_display))
                                                    {{ $activeLicense->license_key_display }}
                                                @else
                                                    N/A
                                                @endif
                                            </span>
                                        </div>
                                        @if(isset($activeLicense->jalali_expires_at) && $activeLicense->jalali_expires_at)
                                        <div class="flex justify-between items-center">
                                            <strong class="font-medium text-gray-500">تاریخ انقضا:</strong>
                                            <span>{{ $activeLicense->jalali_expires_at }}</span>
                                        </div>
                                        @else
                                        <div class="flex justify-between items-center">
                                            <strong class="font-medium text-gray-500">تاریخ انقضا:</strong>
                                            <span>بدون انقضا</span>
                                        </div>
                                        @endif
                                    @else
                                        <p class="text-gray-500 text-xs italic">هنوز لایسنس فعالی برای این سامانه ثبت نشده است.</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Version Information Section for this System --}}
                            <div class="p-5">
                                <h5 class="text-md font-semibold text-gray-600 mb-3 flex items-center">
                                    <i class="fas fa-cubes mr-2 ml-1 text-gray-400 fa-fw"></i>
                                    اطلاعات نسخه سامانه
                                </h5>
                                <div class="space-y-3 text-sm text-gray-700">
                                    <div class="flex justify-between items-center">
                                        <strong class="font-medium text-gray-500">نسخه سامانه شما:</strong>
                                        <span class="font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-600 text-xs">{{ $clientVersion }}</span>
                                    </div>

                                    @if ($clientVersion !== 'N/A' && ($latest_stable_version ?? 'N/A') !== 'N/A' && version_compare($latest_stable_version, $clientVersion, '>'))
                                        <div class="mt-3 pt-3 border-t border-gray-200 text-center">
                                            <p class="text-orange-600 mb-2 text-xs font-semibold">
                                                <i class="fas fa-bell mr-1 animate-pulse"></i> به‌روزرسانی نسخه {{ $latest_stable_version }} برای این سامانه در دسترس است.
                                            </p>
                                            {{-- لینک دانلود یا مشاهده جزئیات آپدیت برای این سیستم خاص می‌تواند اینجا قرار گیرد --}}
                                            {{-- <a href="#" class="btn-gold-app text-xs !py-1 !px-3">
                                                مشاهده جزئیات
                                            </a> --}}
                                        </div>
                                    @elseif ($clientVersion !== 'N/A' && ($latest_stable_version ?? 'N/A') !== 'N/A' && version_compare($latest_stable_version, $clientVersion, '<='))
                                        <div class="mt-3 pt-3 border-t border-gray-200 text-center">
                                            <p class="text-green-600 text-xs font-semibold">
                                                <i class="fas fa-check-circle mr-1"></i> این سامانه به آخرین نسخه به‌روز می‌باشد.
                                            </p>
                                        </div>
                                    @else
                                        <div class="mt-3 pt-3 border-t border-gray-200 text-center">
                                            <p class="text-gray-500 text-xs">اطلاعات مربوط به نسخه این سامانه برای مقایسه در دسترس نیست.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div> {{-- End Nested Grid --}}
                    </div>
                @endforeach
            </div>
        @elseif(empty($errorMessage)) {{-- فقط اگر خطای کلی هم نداشتیم این پیام را نشان بده --}}
            <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden p-6 text-center">
                <i class="fas fa-info-circle text-4xl text-blue-500 mb-3"></i>
                <p class="text-gray-600">در حال حاضر هیچ سامانه‌ای برای شما ثبت نشده است.</p>
                <p class="text-sm text-gray-500 mt-2">در صورت نیاز به ثبت سامانه جدید، با پشتیبانی تماس بگیرید.</p>
            </div>
        @endif

        {{-- Support Request Section - Light Background with Gold Accent --}}
        <div class="mt-8 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
             {{-- Card Header --}}
             <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                 <h4 class="text-lg font-semibold text-gray-700 flex items-center"> {{-- text-lg --}}
                    <i class="fas fa-headset mr-2 ml-1 text-yellow-500 fa-fw"></i>
                     پشتیبانی و درخواست‌ها
                 </h4>
            </div>
             {{-- Card Body --}}
             <div class="p-5">
                 {{-- Increased font size --}}
                <p class="text-base text-gray-600 mb-5 leading-relaxed"> {{-- text-base --}}
                    در صورت نیاز به راهنمایی، گزارش خطا یا درخواست توسعه ویژگی‌های جدید، می‌توانید از طریق لینک زیر یک درخواست جدید ثبت نمایید. کارشناسان ما در اسرع وقت پاسخگو خواهند بود.
                </p>
                <div class="text-center">
                    {{-- Corrected route name --}}
                    <a href="{{ route('tickets.create') }}" class="btn-blue-app text-base">
                        <i class="fas fa-plus-circle mr-1"></i> ثبت درخواست جدید
                    </a>
                </div>
                 {{-- Placeholder for listing recent tickets --}}
                 {{--
                 <div class=\"mt-8 pt-4 border-t border-gray-200\">
                     <h5 class=\"text-lg font-semibold mb-3 text-gray-700\">درخواست‌های اخیر شما:</h5>
                     <div class=\"text-base text-gray-500 bg-gray-50 p-4 rounded border border-gray-200\">
                         <p>هنوز درخواستی ثبت نشده است.</p>
                     </div>
                 </div>
                 --}}
            </div>
        </div>

    </div> {{-- End main content wrapper --}}
</x-app-layout>