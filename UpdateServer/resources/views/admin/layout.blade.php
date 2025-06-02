<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title', 'داشبورد') | پنل مدیریت رایان طلا</title>

    {{-- Load Bootstrap 5 RTL CSS from CDN --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet" integrity="sha384-nU14brUcp6StFntEOOEBvcJm4huWjB0OcIeQ3flBFEmJuskTbNlnNTMcKzuN内یسمü+ODXn3FsL" crossorigin="anonymous">

    {{-- Load Vazirmatn Font from Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">

    {{-- Custom Styles --}}
    <style>
        /* Apply Vazirmatn font */
        body {
            font-family: 'Vazirmatn', sans-serif !important;
            /* Ensure sticky footer works with Bootstrap */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f8f9fa; /* Light background */
        }
        main {
            flex-grow: 1; /* Allow main content to grow */
        }
        .footer {
            background-color: #343a40; /* bg-dark equivalent */
            color: rgba(255, 255, 255, 0.5); /* text-white-50 */
        }
        .footer a.text-warning {
            color: #ffc107 !important; /* text-warning */
        }
         /* Adjustments for RTL */
        .ms-auto { margin-right: auto !important; }
        .me-auto { margin-left: auto !important; }
        .ms-1 { margin-right: 0.25rem !important; }
        .me-1 { margin-left: 0.25rem !important; }
        .ms-2 { margin-right: 0.5rem !important; }
        .me-2 { margin-left: 0.5rem !important; }
        /* Add other RTL adjustments if needed */

        /* Custom colors if needed */
        .navbar-dark .navbar-brand {
             color: #daa520; /* Gold color */
             font-weight: bold;
        }
        .tech-info {
            font-size: 0.8em;
        }
         /* Ensure code blocks are styled nicely */
        code {
             background-color: #e9ecef;
             padding: 0.2em 0.4em;
             border-radius: 3px;
             color: #212529;
             font-size: 0.85em;
             user-select: all; /* Allow easy copying */
        }

    </style>

    @stack('styles')
</head>
<body>

    {{-- Navigation Bar - Using Bootstrap classes --}}
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
      <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('admin.dashboard') }}">پنل مدیریت رایان طلا</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.dashboard*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">داشبورد</a></li>
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.customers*') ? 'active' : '' }}" href="{{ route('admin.customers.index') }}">مشتریان</a></li>
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.systems*') ? 'active' : '' }}" href="{{ route('admin.systems.index') }}">سامانه‌ها</a></li>
            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.licenses*') ? 'active' : '' }}" href="{{ route('admin.licenses.index') }}">لایسنس‌ها</a></li>
          </ul>
          <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
            <li class="nav-item">
                <form action="{{ route('admin.logout') }}" method="GET" id="logout-form" style="display: none;">
                    @csrf
                </form>
                 <a class="nav-link" href="{{ route('admin.logout') }}"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                     خروج
                 </a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    {{-- Main Content Area --}}
    <main class="py-4">
        <div class="container"> {{-- Bootstrap container --}}
            {{-- Display Success/Error Messages Here --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
             @if(session('generated_license_key'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <p class="mb-1"><strong>کلید لایسنس تولید شده:</strong></p>
                    <code>{{ session('generated_license_key') }}</code>
                    <p class="mt-1 mb-0"><small>این کلید را کپی کرده و به مشتری بدهید.</small></p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if(session('error'))
                 <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h5 class="alert-heading">خطا در ورودی!</h5>
                     <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Page specific content will be yielded here --}}
            @yield('content')
        </div>
    </main>

    {{-- Footer - Using Bootstrap classes --}}
    <footer class="footer mt-auto py-3 bg-dark text-white-50 border-top border-secondary-subtle shadow-sm">
        <div class="container text-center">
            <span>
                <small>
                    © {{ date('Y') }} {{ config('app.name', 'پنل مدیریت رایان طلا') }} | تمامی حقوق محفوظ است.
                    <span class="mx-2 d-none d-md-inline">|</span><br class="d-md-none">
                     <a href="https://www.rar-co.ir" target="_blank" class="text-decoration-none text-warning fw-bold">شرکت رایان اندیش رشد</a>
                </small>
                <small class="tech-info d-block mt-1 text-white-50">
                     <span class="me-2">IP: {{ request()->ip() == '::1' ? '127.0.0.1' : request()->ip() }}</span>
                     <span class="mx-1">|</span> <span>مرورگر: <span id="footerBrowserInfo">...</span></span>
                </small>
            </span>
        </div>
    </footer>

    {{-- Load Bootstrap 5 JS Bundle from CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    {{-- Footer specific JS (Browser detect) - Tooltip should be handled by Bootstrap Bundle --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- Browser Detection ---
            try {
                const userAgent = navigator.userAgent || navigator.vendor || window.opera;
                let browserInfo = "ناشناخته";
                if (/chrome/i.test(userAgent) && !/edg/i.test(userAgent)) browserInfo = "گوگل کروم";
                else if (/firefox/i.test(userAgent)) browserInfo = "فایرفاکس";
                else if (/safari/i.test(userAgent) && !/chrome/i.test(userAgent)) browserInfo = "سافاری";
                else if (/msie|trident/i.test(userAgent)) browserInfo = "اینترنت اکسپلورر";
                else if (/edg/i.test(userAgent)) browserInfo = "مایکروسافت اج (Chromium)";
                else if (/edge/i.test(userAgent)) browserInfo = "مایکروسافت اج (EdgeHTML)";
                const browserInfoSpan = document.getElementById("footerBrowserInfo");
                if(browserInfoSpan) browserInfoSpan.textContent = browserInfo;
            } catch(e) { /* Silenced */ }

            // Mobile menu toggle for Bootstrap Navbar (Optional, if using the exact navbar markup)
            // Bootstrap handles this automatically via data-bs-toggle etc.

            // Bootstrap Tooltip Initialization (Should be automatic with bundle, but check if needed)
             var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
             var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
               return new bootstrap.Tooltip(tooltipTriggerEl)
             })

        }); // End DOMContentLoaded
    </script>

    @stack('scripts') {{-- Allow pages to push additional scripts --}}

</body>
</html>