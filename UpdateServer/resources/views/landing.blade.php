<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <title>سامانه حسابداری رایان طلا - مدیریت هوشمند معاملات طلا</title>

    {{-- Load CSS and JS via Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Define Gold Color Palette in Head (Optional but convenient) --}}
    <style>
        :root {
            --clr-gold: #D4AF37; /* Slightly brighter, classic gold */
            --clr-gold-dark: #B8860B; /* DarkGoldenrod */
            --clr-dark-bg: #1f2937; /* Tailwind gray-800 */
            --clr-dark-card: #374151; /* Tailwind gray-700 */
            --clr-text-light: #f3f4f6; /* Tailwind gray-100 */
            --clr-text-muted: #9ca3af; /* Tailwind gray-400 */
        }
        /* Apply base font using Tailwind config */
        body {
            font-family: 'Vazirmatn', sans-serif !important; /* Ensure Vazirmatn */
            background-color: #f9fafb; /* Slightly off-white bg */
        }
        /* Custom Hero Background */
        .hero-section {
         background-color: var(--clr-dark-bg);
         background-image: linear-gradient(rgba(31, 41, 55, 0.85), rgba(55, 65, 81, 0.95)), /* Darker gradient */
                           url('{{ asset('images/goldback.jpg') }}');
         background-repeat: no-repeat;
         background-position: center center;
         background-size: cover;
         color: var(--clr-text-light);
         padding: 7rem 1rem; /* More padding */
         text-align: center;
         border-bottom: 5px solid var(--clr-gold); /* Gold accent border */
        }
        /* Add subtle gold shadow/glow to Hero H1 */
        .hero-section h1 {
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.6), 0 0 15px rgba(212, 175, 55, 0.2); /* Dark shadow + subtle gold glow */
        }
        .hero-section p {
             color: var(--clr-text-light);
             opacity: 0.9;
        }
         /* Outline Button */
         .btn-outline-gold {
            border: 2px solid var(--clr-gold);
            color: var(--clr-gold);
             padding: 0.7rem 1.8rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.3s ease;
         }
          .btn-outline-gold:hover {
            background-color: rgba(212, 175, 55, 0.1); /* Light gold background on hover */
            color: var(--clr-gold-dark);
             border-color: var(--clr-gold-dark);
             transform: translateY(-2px);
         }
          /* Key Features Section - Icons and Cards */
         #key-features .feature-card {
            background-color: white;
            border-radius: 0.5rem; /* rounded-lg */
            padding: 1.5rem; /* p-6 */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); /* subtle shadow */
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb; /* gray-200 border */
         }
         #key-features .feature-card:hover {
             transform: translateY(-5px);
             box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08); /* enhanced shadow */
             border-color: rgba(212, 175, 55, 0.3); /* subtle gold border */
         }
         /* Enhanced Feature icon styling */
         .feature-icon {
             display: inline-flex; /* Use flex to center */
             align-items: center;
             justify-content: center;
             width: 64px; /* w-16 */
             height: 64px; /* h-16 */
             margin-bottom: 1.5rem; /* mb-6 */
             border-radius: 50%; /* rounded-full */
             background-color: rgba(212, 175, 55, 0.1); /* Light gold background */
             transition: all 0.3s ease;
         }
         .feature-icon i {
            font-size: 1.875rem; /* text-3xl */
            color: var(--clr-gold-dark);
            margin: 0; /* Reset margin */
         }
         #key-features .feature-card:hover .feature-icon {
             background-color: var(--clr-gold);
             transform: rotate(-15deg) scale(1.1);
         }
          #key-features .feature-card:hover .feature-icon i {
             color: white;
          }
          /* Detailed Features Section - Dark Theme */
         #features {
             background-color: var(--clr-dark-card);
             color: var(--clr-text-muted);
         }
          #features h2 {
             color: white;
              text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5); /* Add shadow */
          }
          #features h4 {
             color: var(--clr-text-light);
          }
          #features p {
              color: var(--clr-text-muted);
              font-size: 0.875rem; /* text-sm */
          }
          /* Feature item icon in dark section */
          #features .feature-item-icon i {
             color: var(--clr-gold);
             font-size: 1.125rem; /* text-lg */
             transition: color 0.3s ease;
          }
           #features .feature-item:hover .feature-item-icon i {
              color: #e0bb50; /* Lighter gold on hover */
           }
         /* Call to Action Section */
         #cta-section {
             background-color: #f3f4f6; /* gray-100 */
             border-top: 1px solid #e5e7eb; /* gray-200 */
         }
          /* Footer Adjustments */
         .landing-footer {
            background-color: var(--clr-dark-bg);
            color: var(--clr-text-muted);
            font-size: 0.875rem; /* text-sm */
            padding-top: 1.25rem; /* py-5 */
            padding-bottom: 1.25rem;
         }
         .landing-footer a {
             color: var(--clr-gold);
             transition: color 0.2s ease;
         }
         .landing-footer a:hover {
             color: #e0bb50; /* Lighter gold */
         }
         /* Navbar adjustment */
         .navbar-landing {
            background-color: rgba(255, 255, 255, 0.98); /* Less transparent white */
            backdrop-filter: blur(8px); /* More blur */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); /* Subtle shadow */
         }
    </style>
     {{-- Font Awesome for Icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="bg-gray-100 text-gray-800">

{{-- Hero Section - Dark Theme --}}
    <section class="hero-section text-white py-16 md:py-24">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-5 leading-tight"> <!-- Increased size -->
                سامانه حسابداری <span style="color: var(--clr-gold);">رایان طلا</span>
            </h1>
            <p class="text-lg md:text-xl text-gray-200 max-w-3xl mx-auto mb-10"> <!-- Lighter text, more margin -->
                یک سامانه حسابداری معاملات طلای آبشده، شمش، ساخته شده و مستعمل با ویژگی‌های منحصربفرد که هرنوع نیاز شما در حیطه امنیت، نگهداری اطلاعات و مدیریت معاملات را برآورده کرده و گزارش‌های عملکردی بسیار کاربردی ارائه می‌دهد.
            </p>
            <div class="space-x-4 space-x-reverse">
                <a href="{{ route('register') }}" class="btn-gold-app">همین حالا ثبت نام کنید</a>
                <a href="#key-features" class="btn-gold-app">ویژگی‌های کلیدی</a>
            </div>
        </div>
    </section>

    {{-- Key Selling Points Section --}}
    <section id="key-features" class="py-16 bg-white"> <!-- Increased padding -->
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
             <h2 class="text-3xl md:text-4xl font-bold text-center text-gray-800 mb-16">چرا رایان طلا؟</h2> <!-- Bolder, more margin -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
                {{-- Card 1 --}}
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-puzzle-piece"></i></div>
                    <h3 class="text-xl font-semibold mb-3">توسعه پذیری</h3>
                    <p class="text-gray-600 text-sm">امکان توسعه سامانه بر اساس نیازمندی‌های خاص کسب و کار شما.</p>
                </div>
                 {{-- Card 2 --}}
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-sync-alt"></i></div>
                    <h3 class="text-xl font-semibold mb-3">به‌روزرسانی رایگان</h3>
                    <p class="text-gray-600 text-sm">دریافت به‌روزرسانی‌های جدید سامانه به مدت یکسال به صورت رایگان.</p>
                </div>
                 {{-- Card 3 --}}
                 <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-infinity"></i></div>
                    <h3 class="text-xl font-semibold mb-3">استفاده مادام‌العمر</h3>
                    <p class="text-gray-600 text-sm">با یکبار خرید، برای همیشه از امکانات سامانه استفاده کنید.</p>
                </div>
            </div>
        </div>
    </section>


    {{-- Detailed Features Section - Dark Theme --}}
    <section id="features" class="py-16">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl md:text-4xl font-bold text-center text-white mb-16">امکانات و ویژگی‌ها</h2> <!-- Bolder, more margin -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12"> <!-- Increased gap -->
                {{-- Feature Item Template --}}
                <div class="feature-item flex items-start space-x-4 space-x-reverse"> <!-- Increased spacing -->
                     <div class="feature-item-icon flex-shrink-0 mt-1 text-lg"> <!-- Adjusted size/margin -->
                         <i class="fas fa-dollar-sign fa-fw"></i>
                     </div>
                    <div>
                        <h4 class="text-lg font-semibold text-white mb-1">قیمت لحظه‌ای</h4>
                        <p>دریافت قیمت لحظه‌ای انواع محصولات طلا.</p>
                    </div>
                </div>
                <div class="feature-item flex items-start space-x-4 space-x-reverse">
                     <div class="feature-item-icon flex-shrink-0 mt-1 text-lg">
                         <i class="fas fa-calculator fa-fw"></i>
                     </div>
                    <div>
                        <h4 class="text-lg font-semibold text-white mb-1">محاسبات بلادرنگ</h4>
                         <p>محاسبات قیمت بر اساس مظنه، تعداد، گرم و تبدیل عیار.</p>
                    </div>
                </div>
                 <div class="feature-item flex items-start space-x-4 space-x-reverse">
                     <div class="feature-item-icon flex-shrink-0 mt-1 text-lg">
                         <i class="fas fa-magic fa-fw"></i>
                     </div>
                    <div>
                        <h4 class="text-lg font-semibold text-white mb-1">ماشین حساب طلا</h4>
                        <p>پیشنهاد قیمت خرید و فروش و محاسبات پیشرفته.</p>
                    </div>
                </div>
                <div class="feature-item flex items-start space-x-4 space-x-reverse">
                    <div class="feature-item-icon flex-shrink-0 mt-1 text-lg">
                        <i class="fas fa-users fa-fw"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-white mb-1">مدیریت مشتریان</h4>
                        <p>تعریف انواع مشتری (همکار، خریدار، واسطه...).</p>
                    </div>
                </div>
                 <div class="feature-item flex items-start space-x-4 space-x-reverse">
                    <div class="feature-item-icon flex-shrink-0 mt-1 text-lg">
                        <i class="fas fa-credit-card fa-fw"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-white mb-1">روش‌های پرداخت/دریافت</h4>
                        <p>تهاتر، نقدی، بانکی، چک، واسطه و ارتباط با معامله.</p>
                    </div>
                </div>
                 <div class="feature-item flex items-start space-x-4 space-x-reverse">
                     <div class="feature-item-icon flex-shrink-0 mt-1 text-lg">
                         <i class="fas fa-balance-scale fa-fw"></i>
                     </div>
                    <div>
                        <h4 class="text-lg font-semibold text-white mb-1">مدیریت ری‌گیری</h4>
                        <p>تعریف و دریافت اطلاعات مراکز ری‌گیری.</p>
                    </div>
                </div>
                 <div class="feature-item flex items-start space-x-4 space-x-reverse">
                     <div class="feature-item-icon flex-shrink-0 mt-1 text-lg">
                         <i class="fas fa-university fa-fw"></i>
                     </div>
                    <div>
                        <h4 class="text-lg font-semibold text-white mb-1">حساب‌های بانکی و واسط</h4>
                        <p>تعریف انواع حساب برای مدیریت مالی دقیق.</p>
                    </div>
                </div>
                 <div class="feature-item flex items-start space-x-4 space-x-reverse">
                    <div class="feature-item-icon flex-shrink-0 mt-1 text-lg">
                        <i class="fas fa-exchange-alt fa-fw"></i>
                     </div>
                    <div>
                        <h4 class="text-lg font-semibold text-white mb-1">انواع معامله</h4>
                        <p>معامله به صورت تهاتر، نقدی، غیرنقدی.</p>
                    </div>
                </div>
                 <div class="feature-item flex items-start space-x-4 space-x-reverse">
                     <div class="feature-item-icon flex-shrink-0 mt-1 text-lg">
                         <i class="fas fa-shopping-basket fa-fw"></i>
                     </div>
                    <div>
                        <h4 class="text-lg font-semibold text-white mb-1">تفکیک سبد</h4>
                        <p>تفکیک سبد معامله از سبد پرداخت/دریافت.</p>
                    </div>
                </div>
                {{-- More Features ... --}}
                 <div class="feature-item flex items-start space-x-4 space-x-reverse md:col-span-2 lg:col-span-3 justify-center pt-4"> <!-- Added padding top -->
                     <div class="feature-item-icon flex-shrink-0 mt-1 text-lg">
                         <i class="fas fa-ellipsis-h fa-fw"></i>
                     </div>
                    <div>
                        <h4 class="text-lg font-semibold text-white mb-1">و امکانات بی‌شمار دیگر...</h4>
                    </div>
                </div>
            </div>
        </div>
    </section>


    {{-- Call to Action Section - Light Theme --}}
    <section id="cta-section">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
            {{-- Adjusted font size for responsiveness --}}
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900">برای تحولی بزرگ آماده اید؟</h2>
            <p class="text-lg text-gray-600 mt-4 mb-10 max-w-2xl mx-auto">به جمع کاربران حسابداری رایان طلا بپیوندید و معاملات طلای خود را هوشمند کنید.</p>

            {{-- Aparat Video Embed Placeholder --}}
            {{-- Use max-w- to control width, mx-auto to center --}}
            <div class="max-w-3xl mx-auto mb-12 shadow-xl rounded-lg overflow-hidden border border-gray-200">
                {{-- Tailwind CSS Aspect Ratio Plugin --}}
                {{-- Make sure @tailwindcss/aspect-ratio is installed and configured --}}
                <div class="bg-black" style="height: 400px;">
                    <iframe class="w-full h-full"
                            src="https://www.aparat.com/video/video/embed/videohash/csdxv2m/vt/frame?autoplay=0&titleShow=1" 
                            title="معرفی سامانه حسابداری رایان طلا" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen>
                    </iframe>
                </div>
            </div>
             {{-- End Video Embed Placeholder --}}

            <a href="{{ route('register') }}" class="btn-gold-app">همین حالا ثبت نام کنید</a>
        </div>
    </section>

    {{-- Footer for Landing Page --}}
    <footer class="landing-footer">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
             © {{ date('Y') }} سامانه حسابداری رایان طلا | توسعه توسط
            <a href="https://www.rar-co.ir" target="_blank">شرکت رایان اندیش رشد</a>
        </div>
    </footer>

     {{-- Vite handles JS loading --}}
</body>
</html>