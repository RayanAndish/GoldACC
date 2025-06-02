<?php
/**
 * Template: src/views/auth/login.php
 * Displays the user login form.
 * Receives data via $viewData array from AuthController.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'ورود به سیستم';
$appName = $viewData['appName'] ?? 'حسابداری رایان طلا'; // Get app name from common view data
$errorMessage = $viewData['error'] ?? null; // Get specific error message passed by controller
$successMessage = $viewData['success'] ?? null; // Get potential success message (e.g., after logout)
$baseUrl = $viewData['baseUrl'] ?? '/'; // Get base URL

// Note: Login page usually doesn't use the main application layout (header/footer)
// The necessary HTML structure is included directly here.

// --- Get IP and Ray ID (Optional, for display/logging, already logged by controller/service) ---
// $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
// $ray_id = $_SESSION['ray_id'] ?? uniqid('ray-'); // Generate if not in session (though session might not be reliable before login)

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Helper::escapeHtml($pageTitle); ?> | <?php echo Helper::escapeHtml($appName); ?></title>
    <?php // Base URL for relative paths ?>
    <base href="<?php echo Helper::escapeHtml(rtrim($baseUrl, '/') . '/'); ?>/">

    <?php // --- CSS Includes --- ?>
    <link rel="stylesheet" href="css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="css/all.min.css"/>
    <link rel="stylesheet" href="css/style.css">

    <?php // --- Modern Deep Ocean Theme Styles --- ?>
    <style>
        /* Load Vazirmatn font */
        @font-face {
        font-family: 'Vazirmatn';
        /* FIX: Changed font path to local */
        src: url('../fonts/Vazirmatn-RD[wght].woff2') format('woff2 supports variations'),
            url('../fonts/Vazirmatn-RD[wght].woff2') format('woff2-variations');
        font-weight: 100 900;
        font-style: normal;
        font-display: swap;
        }
        html, body { height: 100%; }
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif !important;
            background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 100%); /* Deep blue gradient */
            display: flex;
            align-items: center; /* Vertically center */
            justify-content: center; /* Horizontally center */
            min-height: 100vh;
            margin: 0;
            color: #e0e1dd; /* Off-white text */
        }
        .login-container {
            width: 100%;
            max-width: 450px; /* Increased max width */
            padding: 20px;
        }
        .card {
            background-color: rgba(27, 38, 59, 0.8); /* Darker, less transparent blue */
            border: 1px solid rgba(76, 205, 196, 0.2); /* Subtle teal border */
            border-radius: 0.75rem; /* Slightly larger radius */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
        }
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(76, 205, 196, 0.2);
            text-align: center;
            padding: 2rem 1rem 1.5rem; /* More top padding */
            color: #a0d2db; /* Lighter teal/cyan color */
        }
        .card-header .logo-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            display: block;
            color: #6fffe9; /* Bright cyan accent */
        }
        .card-header h3 {
            color: #e0e1dd;
            font-weight: 400;
            margin-bottom: 0;
        }
        .card-body {
            padding: 2rem 2.5rem 2.5rem; /* More padding */
        }
        .form-label {
             color: #b0bec5; /* Lighter grey label */
             font-size: 0.85rem;
             font-weight: 500;
             margin-bottom: 0.5rem; /* Added space below label */
             display: block; /* Ensure label takes full width */
        }
        .form-control {
            font-family: inherit;
            background-color: rgba(13, 27, 42, 0.5); /* Darker input background */
            border: 1px solid transparent; /* Make border transparent initially */
            border-bottom: 1px solid rgba(76, 205, 196, 0.3); /* Teal bottom border */
            border-radius: 0.375rem; /* Restore some radius */
            color: #fff;
            padding: 0.85rem 1rem; /* Increased padding */
            font-size: 1rem;
            transition: border-color 0.2s ease, background-color 0.2s ease;
        }
        .form-control:focus {
            background-color: rgba(13, 27, 42, 0.7);
            border-color: rgba(76, 205, 196, 0.7); /* Brighter teal border on focus */
            box-shadow: 0 0 0 0.2rem rgba(76, 205, 196, 0.15); /* Subtle focus glow */
            color: #fff;
        }
        .form-control::placeholder {
            color: #78909c; /* Slightly lighter placeholder */
            opacity: 1;
        }
        .btn-login {
            background-color: #4ecdc4; /* Solid vibrant teal */
            border: none;
            color: #ffffff;
            font-weight: 500;
            padding: 0.85rem 1.75rem;
            border-radius: 0.375rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 205, 196, 0.2);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
            font-family: inherit; /* Ensure font is inherited */
        }
        .btn-login:hover {
            background-color: #3caea3; /* Slightly darker teal on hover */
            box-shadow: 0 6px 20px rgba(76, 205, 196, 0.3);
            transform: translateY(-1px);
            color: #fff;
        }
        .alert {
            font-size: 0.875rem;
            border-radius: 0.375rem;
            padding: 0.8rem 1.25rem;
            border: none;
        }
        .alert-danger {
             background-color: rgba(255, 107, 107, 0.15);
             color: #ffced1;
        }
         .alert-success {
             background-color: rgba(76, 205, 196, 0.15);
             color: #c4f0ed;
         }
        .card-footer {
            background-color: transparent;
            text-align: center;
            padding: 1.5rem 1rem;
            font-size: 0.8rem;
            color: #78909c; /* Muted text */
            border-top: 1px solid rgba(76, 205, 196, 0.1);
        }
        .invalid-feedback {
            color: #ffced1; /* Light red for errors */
            font-size: 0.8rem;
            padding-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <?php // TODO: Replace with logo if available ?>
                <i class="fas fa-shield-alt logo-icon"></i> <?php /* Changed icon to shield */ ?>
                <h3><?php echo Helper::escapeHtml($appName); ?></h3> <?php /* Changed to h3 */ ?>
            </div>
            <div class="card-body">
                <?php // Display Success or Error messages passed from controller ?>
                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success d-flex align-items-center small" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <div><?php echo Helper::escapeHtml($successMessage); ?></div>
                    </div>
                <?php endif; ?>
                 <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger d-flex align-items-center small" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div><?php echo Helper::escapeHtml($errorMessage); ?></div>
                    </div>
                <?php endif; ?>

                <?php // Login form pointing to the correct controller action ?>
                <form action="<?php echo $baseUrl; ?>/login" method="POST" id="loginForm" class="needs-validation" novalidate>
                     <?php // TODO: Add CSRF token input field here. ?>

                    <div class="mb-3"> <?php /* Adjusted margin */ ?>
                        <label for="username" class="form-label">نام کاربری</label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="نام کاربری" required autofocus>
                        <div class="invalid-feedback">لطفا نام کاربری را وارد کنید.</div>
                    </div>
                    <div class="mb-4"> <?php /* Adjusted margin */ ?>
                        <label for="password" class="form-label">رمز عبور</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="رمز عبور" required>
                        <div class="invalid-feedback">لطفا رمز عبور را وارد کنید.</div>
                    </div>
                    <div class="d-grid mt-4 pt-2"> <?php /* Added top margin */ ?>
                        <button type="submit" class="btn btn-login">
                            <i class="fas fa-sign-in-alt me-1"></i> ورود
                        </button>
                    </div>
                     <?php /* Optional: Add "Forgot Password?" link here */ ?>
                     <?php /* <div class="text-center mt-3 pt-1 small"><a href="#" class="text-decoration-none" style="color: #90a4ae;">رمز عبور خود را فراموش کرده‌اید؟</a></div> */ ?>
                </form>
            </div>
            <div class="card-footer">
                 © <?php echo date('Y'); ?> <?php echo Helper::escapeHtml($appName); ?>. تمامی حقوق محفوظ است.
            </div>
        </div>
    </div>

    <?php // --- JS Includes --- ?>
    <script src="js/bootstrap.bundle.min.js"></script>
    <?php // Bootstrap Form Validation Script ?>
    <script>
        (() => {
          'use strict'
          const forms = document.querySelectorAll('.needs-validation')
          Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
              if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
              }
              form.classList.add('was-validated')
            }, false)
          })
        })()
    </script>
</body>
</html>