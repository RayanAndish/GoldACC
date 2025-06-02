<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- <title>{{ config('app.name', 'Laravel') }}</title> --}}
        <title>ورود / ثبت‌نام - حسابداری رایان طلا</title> {{-- Custom Title --}}

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Custom Styles for Auth Pages -->
        <style>
             :root {
                --clr-gold: #D4AF37;
                --clr-gold-dark: #B8860B;
                --clr-dark-bg: #111827; /* Tailwind gray-900 */
                --clr-dark-card: #1f2937; /* Tailwind gray-800 */
                --clr-text-light: #f3f4f6; /* Tailwind gray-100 */
                --clr-text-muted: #9ca3af; /* Tailwind gray-400 */
                --clr-input-bg: #374151; /* Tailwind gray-700 */
                --clr-input-border: #4b5563; /* Tailwind gray-600 */
                --clr-input-focus-border: var(--clr-gold);
             }
            html, body {
                font-family: 'Vazirmatn', sans-serif !important;
                background-color: var(--clr-dark-bg);
                color: var(--clr-text-light);
            }
            /* Style the anchor tags specifically for auth pages */
            .auth-link {
                color: var(--clr-gold);
                transition: color 0.2s ease;
                font-size: 0.875rem; /* text-sm */
                text-decoration: none;
            }
            .auth-link:hover {
                 color: #facc15; /* lighter gold on hover */
                 text-decoration: underline;
            }
             /* Basic styling for the card */
            .auth-card {
                 background-color: var(--clr-dark-card);
                 border-top: 4px solid var(--clr-gold);
                 box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            }
            /* Styling inputs and labels within this layout */
             .auth-card label {
                 color: var(--clr-text-muted);
                 font-size: 0.875rem;
                 margin-bottom: 0.25rem;
                 display: block;
             }
             .auth-card input[type="text"],
              .auth-card input[type="email"],
              .auth-card input[type="password"],
              .auth-card select {
                 background-color: var(--clr-input-bg);
                 border: 1px solid var(--clr-input-border);
                 color: #ffffff; /* Changed to white for better contrast */
                 border-radius: 0.375rem; /* rounded-md */
                 padding: 0.6rem 0.75rem;
                 width: 100%;
                 font-size: 0.9rem;
                 transition: border-color 0.2s ease, background-color 0.2s ease;
              }
               /* Explicitly style autofill state */
               .auth-card input:-webkit-autofill,
               .auth-card input:-webkit-autofill:hover,
               .auth-card input:-webkit-autofill:focus,
               .auth-card input:-webkit-autofill:active {
                   -webkit-box-shadow: 0 0 0 30px var(--clr-input-bg) inset !important; /* Force background color */
                   -webkit-text-fill-color: #ffffff !important; /* Force text color */
                   caret-color: #ffffff; /* Ensure cursor color is visible */
               }
               .auth-card input[type=\"text\"]:focus,
               .auth-card input[type=\"email\"]:focus,
               .auth-card input[type=\"password\"]:focus,
               .auth-card select:focus {
                   border-color: var(--clr-input-focus-border);
                   outline: none;
                   box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.3); /* Subtle gold focus ring */
               }
                /* Checkbox styling */
                .auth-card input[type=\"checkbox\"] {
                    border-radius: 0.25rem;
                    border-color: var(--clr-input-border);
                    background-color: var(--clr-input-bg);
                    color: var(--clr-gold); /* Checkmark color */
                    transition: all 0.2s ease;
                }
                 .auth-card input[type=\"checkbox\"]:focus {
                     border-color: var(--clr-input-focus-border);
                     outline: none;
                      box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.3);
                 }
                  .auth-card input[type=\"checkbox\"]:checked {
                       background-color: var(--clr-gold);
                       border-color: var(--clr-gold);
                  }
                   .auth-card .checkbox-label span {
                        color: var(--clr-text-muted);
                   }
                /* Error message styling */
                .auth-card .input-error {
                    color: #f87171; /* Tailwind red-400 */
                    font-size: 0.8rem;
                    margin-top: 0.25rem;
                }
                 /* Primary Button Style */
                 .btn-auth-primary {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0.6rem 1.5rem;
                    background-image: linear-gradient(to bottom, #e0bb50, var(--clr-gold));
                    border: none;
                    border-radius: 0.375rem;
                    font-weight: 600;
                    font-size: 0.9rem;
                    color: var(--clr-dark-bg);
                    text-shadow: none;
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
                    transition: all 0.3s ease;
                    cursor: pointer;
                 }
                  .btn-auth-primary:hover {
                     background-image: linear-gradient(to bottom, var(--clr-gold), var(--clr-gold-dark));
                     color: white;
                     box-shadow: 0 10px 15px rgba(212, 175, 55, 0.25);
                     transform: translateY(-2px);
                  }
                   .btn-auth-primary:disabled {
                       opacity: 0.7;
                       cursor: not-allowed;
                   }

        </style>
    </head>
    <body class="antialiased"> {{-- Base styles are now in <style> --}}
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 px-4">
            <div class="mb-6"> {{-- Added margin bottom --}}
                <a href="/" wire:navigate>
                    {{-- Logo/Site Name --}}
                     <h1 class="text-3xl font-bold text-white text-center">
                         سامانه <span style="color: var(--clr-gold);">رایان طلا</span>
                     </h1>
                </a>
            </div>

            {{-- Card Wrapper --}}
            <div class="auth-card w-full sm:max-w-md px-6 py-8 overflow-hidden sm:rounded-lg">
                {{ $slot }} {{-- This is where login.blade.php content will be injected --}}
            </div>
        </div>
    </body>
</html>