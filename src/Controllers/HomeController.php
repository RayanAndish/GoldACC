<?php

namespace App\Controllers; // Namespace صحیح

use PDO;
use Monolog\Logger;
use App\Core\ViewRenderer;
use App\Controllers\AbstractController; // ارث‌بری از کلاس پایه
use App\Utils\Helper; // اگر Helper لازم است
use Throwable;
use Exception;
use Morilog\Jalali\Jalalian; // Add Jalalian namespace
// احتمالاً نیاز به Repository خاصی برای صفحات عمومی نیست

/**
 * HomeController handles requests for public-facing pages like landing and about.
 */
class HomeController extends AbstractController {

    // اگر وابستگی‌های خاصی نیاز دارد، اینجا تعریف و در سازنده مقداردهی کنید
    // private SomeRepository $someRepository;

    /**
     * Constructor. Relies on parent to inject base dependencies.
     *
     * @param PDO $db
     * @param Logger $logger
     * @param array $config
     * @param ViewRenderer $viewRenderer
     * @param array $services Array of application services.
     */
    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services); // فراخوانی سازنده والد
        $this->logger->debug("HomeController initialized.");
        // $this->someRepository = $services['someRepository']; // Example if needed
    }

    /**
     * Displays the landing page.
     * Route: / or /landing (GET)
     */
    public function landing(): void {
        $this->logger->info("Rendering landing page.");

        // Check if user is already logged in, if so, redirect to dashboard
        if ($this->isLoggedIn()) {
            $this->redirect('/app/dashboard'); // Redirect logged-in users away from landing
            // exit; // Redirect includes exit
        }

        // Flash message might exist (e.g., from license check failure before login attempt)
        $flashLicenseMessage = $this->getFlashMessage('license_message');

        // Render the landing page view
        // View file should be located at src/views/home/landing.php
        $this->render('home/landing', [
            'page_title' => 'خوش آمدید',
             // Pass flash message specifically if needed by the view template
             'flash_license_message' => $flashLicenseMessage
            // Pass any other specific data needed by the landing page
        ], false); // Render landing page without the main app layout (header/footer)
    }

    /**
     * Displays the About page.
     * Route: /about (GET)
     */
    public function about(): void {
        $this->logger->info("Rendering about page.");

        // This method needs to fetch the data required by the about view template.
        // The logic previously inside templates/about.php should be moved here.

        $systemInfo = [];
        $operationalInfo = [];
        $companyInfo = [ // This can be hardcoded or come from config
            'name' => 'شرکت رایان اندیش رشد',
            'email' => 'info@rar-co.ir', // Corrected email
            'phone' => '86051183-021',
            'copyright' => 'مالکیت این سامانه [...] بر عهده استفاده کننده غیرمجاز می‌باشد.' // Shortened
        ];
        $errorMessage = null; // Initialize error message

        try {
            // Fetch System Info (License, Users, DB Size)
            // --- License ---
            $license = $this->licenseService->getActiveLicenseInfo(); // Use service
             $systemInfo['license_key_display'] = $license ? substr($license['license_key'] ?? '', 0, 5) . '...' . substr($license['license_key'] ?? '', -5) : 'N/A';
             $systemInfo['license_status_farsi'] = $license ? Helper::translateLicenseStatus($license['status'] ?? 'unknown') : 'نامشخص';
             $systemInfo['license_status_class'] = $license ? Helper::getLicenseStatusClass($license['status'] ?? 'unknown') : 'secondary';
             $systemInfo['license_expiry_gregorian'] = $license['expires_at'] ?? null;
             $systemInfo['license_expiry_farsi'] = $license['expires_at'] ? Jalalian::fromFormat('Y-m-d H:i:s', $license['expires_at'])->format('Y/m/d') : '-';
             $systemInfo['install_date_gregorian'] = $license['created_at'] ?? null;
             $systemInfo['install_date_from_license'] = $license['created_at'] ? Jalalian::fromFormat('Y-m-d H:i:s', $license['created_at'])->format('Y/m/d') : '-';
            
             error_log('EXPIRES_AT: ' . ($license['expires_at'] ?? 'NULL'));
             error_log('CREATED_AT: ' . ($license['created_at'] ?? 'NULL'));
             error_log('INSTALL_DATE: ' . ($installDateSetting ?? 'NULL'));

             // --- Active Users ---
             // Use UserRepository injected via $this->services if needed, or directly if already stored
             $userRepo = $this->services['userRepository'];
             $systemInfo['active_users'] = $userRepo->countActiveUsers(); // Assume this method exists

             // --- DB Size ---
             // Use DatabaseService if available, otherwise basic query
             $dbService = $this->services['databaseService'] ?? null;
             if ($dbService) {
                  // $dbSizeInfo = $dbService->getDatabaseSizeInfo(); // Assume method exists
                  // $systemInfo['total_db_size_formatted'] = $dbSizeInfo['total_formatted'];
                  // $systemInfo['table_details'] = $dbSizeInfo['tables'];
                  // Placeholder:
                   $systemInfo['total_db_size_formatted'] = '15 MB';
                   $systemInfo['table_details'] = [['name'=>'users', 'size'=>'1 MB'],['name'=>'transactions','size'=>'10 MB']];
             } else {
                  $systemInfo['total_db_size_formatted'] = 'N/A (Service Unavailable)';
                  $systemInfo['table_details'] = [];
             }


            // Fetch Operational Info (Settings)
            // Use SettingsRepository injected via $this->services
             $settingsRepo = $this->services['settingsRepository'];
             $operationalInfo['domain'] = $settingsRepo->getSetting('app_domain') ?: ($_SERVER['SERVER_NAME'] ?? 'N/A');
             $operationalInfo['email'] = $settingsRepo->getSetting('admin_email') ?: 'تعریف نشده';
             $operationalInfo['customer_name'] = $settingsRepo->getSetting('customer_name') ?: 'تعریف نشده';
             $installDateSetting = $settingsRepo->getSetting('install_date');
             $operationalInfo['install_date_gregorian'] = $installDateSetting;
             $operationalInfo['install_date_farsi'] = $installDateSetting ? Jalalian::fromFormat('Y-m-d H:i:s', $installDateSetting)->format('Y/m/d') : '-';


        } catch (Throwable $e) {
            $this->logger->error("Error fetching data for About page.", ['exception' => $e]);
            $errorMessage = "خطا در خواندن اطلاعات سیستم.";
            // Set default/empty values on error? Handled by array initialization above.
        }


        // Render the about page view
        // View file should be located at src/views/home/about.php
        $this->render('home/about', [
            'page_title' => 'درباره سامانه',
            'system_info' => $systemInfo,
            'operational_info' => $operationalInfo,
            'company_info' => $companyInfo,
            'error_msg' => $errorMessage
        ], true); // Render 'about' page WITH the main layout
    }

} // End HomeController