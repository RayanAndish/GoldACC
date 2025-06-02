<?php
/**
 * Template: src/views/auth/register.php
 * Registration Form.
 * Receives data via $viewData array from AuthController::handleRegistration.
 */

use App\Utils\Helper;

// Extract data
$pageTitle = $viewData['page_title'] ?? 'ثبت نام';
$appName = $viewData['appName'] ?? 'حسابداری رایان طلا';
$baseUrl = $viewData['baseUrl'] ?? '/';
$error = $viewData['error'] ?? null;
$errors = $viewData['errors'] ?? []; // Field-specific errors
$oldInput = $viewData['oldInput'] ?? [];

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Helper::escapeHtml($pageTitle); ?> | <?php echo Helper::escapeHtml($appName); ?></title>
    <base href="<?php echo Helper::escapeHtml(rtrim($baseUrl, '/') . '/'); ?>/">
    <link rel="stylesheet" href="css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="css/all.min.css"/>
    <link rel="stylesheet" href="css/style.css">

    <?php // Reuse login styles for consistency (or create specific register styles) ?>
    <style>
        /* Basic styles - Copy/Adapt from login.php for consistent look */
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
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px 0; /* Add padding for scroll */
            color: #e0e1dd;
        }
        .register-container { width: 100%; max-width: 500px; padding: 20px; }
        .card { background-color: rgba(27, 38, 59, 0.8); border: 1px solid rgba(76, 205, 196, 0.2); border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4); backdrop-filter: blur(8px); }
        .card-header { background-color: transparent; border-bottom: 1px solid rgba(76, 205, 196, 0.2); text-align: center; padding: 1.5rem 1rem; color: #a0d2db; }
        .card-header h3 { color: #e0e1dd; font-weight: 400; margin-bottom: 0; }
        .card-body { padding: 2rem 2.5rem; }
        .form-label { color: #b0bec5; font-size: 0.85rem; font-weight: 500; margin-bottom: 0.5rem; display: block; }
        .form-control { font-family: inherit; background-color: rgba(13, 27, 42, 0.5); border: 1px solid transparent; border-bottom: 1px solid rgba(76, 205, 196, 0.3); border-radius: 0.375rem; color: #fff; padding: 0.85rem 1rem; font-size: 1rem; transition: border-color 0.2s ease, background-color 0.2s ease; }
        .form-control:focus { background-color: rgba(13, 27, 42, 0.7); border-color: rgba(76, 205, 196, 0.7); box-shadow: 0 0 0 0.2rem rgba(76, 205, 196, 0.15); color: #fff; }
        .form-control::placeholder { color: #78909c; opacity: 1; }
        .btn-register { background-color: #4ecdc4; border: none; color: #ffffff; font-weight: 500; padding: 0.85rem 1.75rem; border-radius: 0.375rem; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(76, 205, 196, 0.2); text-transform: uppercase; letter-spacing: 1px; font-size: 0.9rem; font-family: inherit; }
        .btn-register:hover { background-color: #3caea3; box-shadow: 0 6px 20px rgba(76, 205, 196, 0.3); transform: translateY(-1px); color: #fff; }
        .alert { font-size: 0.875rem; border-radius: 0.375rem; padding: 0.8rem 1.25rem; border: none; }
        .alert-danger { background-color: rgba(255, 107, 107, 0.15); color: #ffced1; }
        .alert-success { background-color: rgba(76, 205, 196, 0.15); color: #c4f0ed; }
        .invalid-feedback { color: #ffced1; font-size: 0.8rem; padding-top: 0.25rem; }
        .login-link { text-align: center; margin-top: 1.5rem; font-size: 0.9rem; } 
        .login-link a { color: #a0d2db; text-decoration: none; } 
        .login-link a:hover { color: #c4f0ed; } 
    </style>
</head>
<body>
    <div class="register-container">
        <div class="card">
            <div class="card-header">
                <h3><?php echo Helper::escapeHtml($pageTitle); ?></h3>
            </div>
            <div class="card-body">

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo Helper::escapeHtml($error); ?>
                    </div>
                <?php endif; ?>

                <form action="<?php echo $baseUrl; ?>/register" method="POST" id="registerForm" class="needs-validation" novalidate>
                    <?php // TODO: Add CSRF token ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">نام کاربری</label>
                        <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                               id="username" name="username" value="<?php echo Helper::escapeHtml($oldInput['username'] ?? ''); ?>" required>
                        <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback"><?php echo Helper::escapeHtml($errors['username']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">آدرس ایمیل</label>
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                               id="email" name="email" value="<?php echo Helper::escapeHtml($oldInput['email'] ?? ''); ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?php echo Helper::escapeHtml($errors['email']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">رمز عبور</label>
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                               id="password" name="password" required>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?php echo Helper::escapeHtml($errors['password']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirm" class="form-label">تکرار رمز عبور</label>
                        <input type="password" class="form-control <?php echo isset($errors['password_confirm']) ? 'is-invalid' : ''; ?>"
                               id="password_confirm" name="password_confirm" required>
                        <?php if (isset($errors['password_confirm'])): ?>
                            <div class="invalid-feedback"><?php echo Helper::escapeHtml($errors['password_confirm']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-register">
                           <i class="fas fa-user-plus me-1"></i> ثبت نام
                        </button>
                    </div>
                </form>

                <div class="login-link mt-4 pt-2 text-center border-top border-secondary border-opacity-25"> <?php /* Added spacing, centering, and a subtle top border */ ?>
                    <p class="mb-2 text-white-50 small">قبلاً ثبت نام کرده‌اید؟</p>
                    <a href="<?php echo $baseUrl; ?>/login" class="btn btn-outline-light btn-sm px-3">
                        <i class="fas fa-sign-in-alt me-1"></i> وارد شوید
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        // Optional: Add client-side validation if needed, although server-side is primary
    </script>
</body>
</html> 