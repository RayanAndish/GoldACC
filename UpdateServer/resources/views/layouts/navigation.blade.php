{{-- File: UpdateServer/resources/views/layouts/navigation.blade.php (Corrected Again) --}}
<nav x-data="{ open: false }" class="bg-white/95 backdrop-blur-sm border-b border-gray-200 sticky top-0 z-40 print:hidden">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    @auth('admin')
                        <a href="{{ route('admin.dashboard') }}">
                            <h1 class="text-xl font-bold text-gray-800">
                                <span style="color: var(--clr-gold);">رایان</span> طلا <span class="text-sm font-normal text-gray-500">(مدیریت)</span>
                            </h1>
                        </a>
                    @else
                        <a href="{{ Auth::check() ? route('dashboard') : route('landing') }}">
                             <h1 class="text-xl font-bold text-gray-800">
                                 <span style="color: var(--clr-gold);">رایان</span> طلا
                             </h1>
                        </a>
                    @endauth
                    {{-- Ticket links REMOVED from here --}}
                </div>

                <!-- Navigation Links (Desktop) -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex space-x-reverse">
                    @auth('web')
                        {{-- User Links --}}
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" class="nav-link-light-base" active-class="nav-link-light-active" inactive-class="nav-link-light-inactive">{{ __('پیشخوان') }}</x-nav-link>
                        <x-nav-link :href="route('tickets.index')" :active="request()->routeIs('tickets.*')" class="nav-link-light-base" active-class="nav-link-light-active" inactive-class="nav-link-light-inactive">{{ __('درخواست‌های من') }}</x-nav-link>
                    @endauth

                    @auth('admin')
                        {{-- Admin Links --}}
                        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" class="nav-link-light-base" active-class="nav-link-light-active" inactive-class="nav-link-light-inactive">{{ __('داشبورد مدیریت') }}</x-nav-link>
                        <x-nav-link :href="route('admin.customers.index')" :active="request()->routeIs('admin.customers.*')" class="nav-link-light-base" active-class="nav-link-light-active" inactive-class="nav-link-light-inactive">{{ __('مشتریان') }}</x-nav-link>
                        <x-nav-link :href="route('admin.systems.index')" :active="request()->routeIs('admin.systems.*')" class="nav-link-light-base" active-class="nav-link-light-active" inactive-class="nav-link-light-inactive">{{ __('سامانه‌ها') }}</x-nav-link>
                        <x-nav-link :href="route('admin.licenses.index')" :active="request()->routeIs('admin.licenses.*')" class="nav-link-light-base" active-class="nav-link-light-active" inactive-class="nav-link-light-inactive">{{ __('لایسنس‌ها') }}</x-nav-link>
                        {{-- Ticket link MOVED here --}}
                        <x-nav-link :href="route('admin.tickets.index')" :active="request()->routeIs('admin.tickets.*')" class="nav-link-light-base" active-class="nav-link-light-active" inactive-class="nav-link-light-inactive">{{ __('درخواست‌ها') }}</x-nav-link>
                    @endauth
                </div>
            </div>

            <!-- Settings Dropdown -->
            @if(Auth::guard('web')->check() || Auth::guard('admin')->check())
                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    {{-- Dropdown Content (Trigger, Content with profile/logout) --}}
                    <x-dropdown align="left" width="48">
                         <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-600 bg-white hover:text-gray-800 focus:outline-none focus:bg-gray-50 focus:text-gray-800 transition ease-in-out duration-150">
                                @auth('web') <div>{{ Auth::user()->name }}</div> @endauth
                                @auth('admin') <div>{{ Auth::guard('admin')->user()->name }} (مدیر)</div> @endauth
                                <div class="ms-1"><svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg></div>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                             <div class="py-1 rounded-md bg-white ring-1 ring-black ring-opacity-5 shadow-lg border border-gray-200">
                                @auth('web')<x-dropdown-link :href="route('profile.edit')" class="dropdown-link-light-style">{{ __('پروفایل کاربری') }}</x-dropdown-link>@endauth
                                @auth('web')<form method="POST" action="{{ route('logout') }}"> @csrf<x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();" class="dropdown-link-light-style">{{ __('خروج از حساب') }}</x-dropdown-link></form>@endauth
                                @auth('admin')<form method="POST" action="{{ route('admin.logout') }}"> @csrf<x-dropdown-link :href="route('admin.logout')" onclick="event.preventDefault(); this.closest('form').submit();" class="dropdown-link-light-style">{{ __('خروج مدیر') }}</x-dropdown-link></form>@endauth
                             </div>
                         </x-slot>
                     </x-dropdown>
                 </div>
            @else
                <div class="hidden sm:flex sm:items-center sm:ms-6 space-x-4 space-x-reverse">
                     <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">ورود کاربر</a>
                     <a href="{{ route('register') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">ثبت نام</a>
                </div>
            @endif

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                 @if(Auth::guard('web')->check() || Auth::guard('admin')->check())
                    <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-700 transition duration-150 ease-in-out">
                        {{-- SVG Icon --}}
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /><path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                 @endif
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    @if(Auth::guard('web')->check() || Auth::guard('admin')->check())
        <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
            <div class="pt-2 pb-3 space-y-1">
                @auth('web')
                    {{-- User Links --}}
                    <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">{{ __('پیشخوان') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('tickets.index')" :active="request()->routeIs('tickets.*')">{{ __('درخواست‌های من') }}</x-responsive-nav-link>
                @endauth
                @auth('admin')
                    {{-- Admin Links --}}
                    <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">{{ __('داشبورد مدیریت') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.customers.index')" :active="request()->routeIs('admin.customers.*')">{{ __('مشتریان') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.systems.index')" :active="request()->routeIs('admin.systems.*')">{{ __('سامانه‌ها') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.licenses.index')" :active="request()->routeIs('admin.licenses.*')">{{ __('لایسنس‌ها') }}</x-responsive-nav-link>
                    {{-- Ticket link MOVED here --}}
                    <x-responsive-nav-link :href="route('admin.tickets.index')" :active="request()->routeIs('admin.tickets.*')">{{ __('درخواست‌ها') }}</x-responsive-nav-link>
                @endauth
            </div>
            <!-- Responsive Settings Options -->
            <div class="pt-4 pb-1 border-t border-gray-200">
                <div class="px-4">
                    @auth('web')<div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div><div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>@endauth
                    @auth('admin')<div class="font-medium text-base text-gray-800">{{ Auth::guard('admin')->user()->name }} (مدیر)</div><div class="font-medium text-sm text-gray-500">{{ Auth::guard('admin')->user()->email }}</div>@endauth
                </div>
                <div class="mt-3 space-y-1">
                    @auth('web')<x-responsive-nav-link :href="route('profile.edit')">{{ __('پروفایل کاربری') }}</x-responsive-nav-link>@endauth
                    @auth('web')<form method="POST" action="{{ route('logout') }}">@csrf<x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault();this.closest('form').submit();">{{ __('خروج از حساب') }}</x-responsive-nav-link></form>@endauth
                    @auth('admin')<form method="POST" action="{{ route('admin.logout') }}">@csrf<x-responsive-nav-link :href="route('admin.logout')" onclick="event.preventDefault();this.closest('form').submit();">{{ __('خروج مدیر') }}</x-responsive-nav-link></form>@endauth
                </div>
            </div>
        </div>
    @endif
</nav>
    {{-- Remove style tag if styles are correctly in app.css --}}
    <style>
        /* Base styles for desktop nav links (light theme) */
        .nav-link-light-base {
            display: inline-flex;
            align-items: center;
            padding-top: 1px;
            padding-bottom: 1px;
            border-bottom-width: 2px;
            font-size: 0.9rem; /* text-sm */
            font-weight: 500; /* font-medium */
            line-height: 1.25rem;
            transition: border-color 0.15s ease-in-out, color 0.15s ease-in-out;
        }
        .nav-link-light-inactive {
            border-color: transparent;
            color: var(--clr-text-medium); /* gray-600 */
        }
        .nav-link-light-inactive:hover, .nav-link-light-inactive:focus {
            border-color: var(--clr-border-medium); /* gray-300 */
            color: var(--clr-text-dark); /* gray-800 */
        }
        .nav-link-light-active {
            border-color: var(--clr-gold);
            color: var(--clr-text-dark); /* gray-800 */
        }
         /* Style for dropdown links (light theme) */
        .dropdown-link-light-style {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            text-align: right; /* for RTL */
            font-size: 0.875rem; /* text-sm */
            line-height: 1.25rem;
            color: var(--clr-text-dark);
            transition: background-color 0.15s ease-in-out;
        }
        .dropdown-link-light-style:hover, .dropdown-link-light-style:focus {
            background-color: #f3f4f6; /* gray-100 */
        }
        /* Base styles for responsive nav links (light theme) */
        .responsive-nav-link-light-base {
            display: block;
            padding-left: 1rem; /* pl-3 */
            padding-right: 1rem; /* pr-4 */
            padding-top: 0.5rem; /* py-2 */
            padding-bottom: 0.5rem;
            border-right-width: 4px; /* Use border-right for RTL */
            font-size: 0.9rem; /* text-base */
            font-weight: 500; /* font-medium */
            transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, color 0.15s ease-in-out;
        }
        .responsive-nav-link-light-inactive {
             border-color: transparent;
             color: var(--clr-text-medium);
        }
        .responsive-nav-link-light-inactive:hover, .responsive-nav-link-light-inactive:focus {
            color: var(--clr-text-dark);
            background-color: #f9fafb; /* gray-50 */
            border-color: var(--clr-border-medium); /* gray-300 */
        }
        .responsive-nav-link-light-active {
            border-color: var(--clr-gold);
            background-color: rgba(212, 175, 55, 0.05); /* Very subtle gold bg */
             color: var(--clr-gold-dark);
        }
        .responsive-nav-link-light-active-sub { /* For profile/logout links when active (usually not needed) */
              border-color: var(--clr-gold);
              color: var(--clr-gold-dark);
         }
    </style>
</nav>