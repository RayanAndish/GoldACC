<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>کارتابل - حسابداری رایان طلا</title>
        <link rel="stylesheet" href="{{ asset('vendor/jalalidatepicker/jalalidatepicker.min.css') }}">
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
         {{-- Font Awesome --}}
         <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Custom App Styles -->
        <!-- Custom App Styles -->
        <style>
            :root {
                --clr-gold:rgb(253, 198, 48);
                --clr-gold-dark:rgb(143, 104, 6);
                --clr-dark-bg: #111827; /* gray-900 - Body Background */
                --clr-text-dark: #1f2937; /* gray-800 */
                --clr-text-medium: #4b5563; /* gray-600 */
                --clr-text-light: #f3f4f6; /* gray-100 */
                --clr-text-muted-dark: #6b7280; /* gray-500 */
                --clr-border-light: #e5e7eb; /* gray-200 */
                --clr-border-medium: #d1d5db; /* gray-300 */
            }
            html, body {
                 font-family: 'Vazirmatn', sans-serif !important;
                 background-color: var(--clr-dark-bg);
            }
             /* Basic link styling */
            main a:not(.button-link):not(.card-link):not(.nav-link) {
                color:rgb(83, 1, 1);
                text-decoration: none; transition: color 0.2s ease;
            }
             main a:not(.button-link):not(.card-link):not(.nav-link):hover {
                 color:rgb(83, 1, 1); text-decoration: underline;
            }
             /* Helper for card links */
             .card-link {
                 color: var(--clr-gold); font-weight: 500; text-decoration: none; transition: color 0.2s ease;
             }
              .card-link:hover {
                   color: var(--clr-gold-dark); text-decoration: underline;
              }

        </style>
    </head>
    <body class="antialiased">
        <div class="min-h-screen">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow-sm border-b border-gray-200 print:hidden">
                    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                        {{-- Header text darker and bolder --}}
                        <h2 class="font-bold text-xl text-gray-900 leading-tight"> {{-- <<< Changed to font-bold, text-gray-900 --}}
                             {{ $header }}
                        </h2>
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="py-8">
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                     {{-- The $slot will contain dashboard.blade.php content, which will define its own cards --}}
                     {{ $slot }}
                </div>
            </main>

            {{-- Optional Footer --}}
            {{-- <footer class="py-4 text-center text-xs text-gray-500 print:hidden">
                © {{ date('Y') }} سامانه رایان طلا | توسعه توسط <a href="https://www.rar-co.ir" target="_blank" class="hover:text-yellow-400 font-semibold">شرکت رایان اندیش رشد</a>
            </footer> --}}
        </div>
        {{-- Add JS for Jalali Datepicker before closing body --}}
        <script src="{{ asset('vendor/jalalidatepicker/jalalidatepicker.min.js') }}"></script>
        <script>
            // Initialize datepicker on elements with the specific class
            jalaliDatepicker.startWatch({
                selector: '.jalali-datepicker',
                format: 'YYYY/MM/DD', // Match the format you expect/display
                time: false // Set to true if you need time as well
            });
        </script>
    </body>
</html>
