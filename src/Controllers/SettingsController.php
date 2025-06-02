<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Dependencies
use App\Repositories\SettingsRepository;
// use App\Services\SettingsService; // If complex logic needed
use App\Utils\Helper;

/**
 * SettingsController manages system-wide application settings.
 * Displays the settings form and handles saving settings. Access restricted to Admins.
 * Inherits from AbstractController.
 */
class SettingsController extends AbstractController {

    private SettingsRepository $settingsRepository;
    // private ?SettingsService $settingsService; // Optional

    /**
     * Constructor. Injects dependencies.
     *
     * @param PDO $db
     * @param Logger $logger
     * @param array $config Application config (might hold some defaults).
     * @param ViewRenderer $viewRenderer
     * @param array $services Array of application services.
     * @throws \Exception If SettingsRepository is missing.
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services // Receive the $services array
        // SettingsService $settingsService = null // Optional
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services); // Pass all to parent

        // Retrieve specific repository
        if (!isset($this->services['settingsRepository']) || !$this->services['settingsRepository'] instanceof SettingsRepository) {
            throw new \Exception('SettingsRepository not found for SettingsController.');
        }
        $this->settingsRepository = $this->services['settingsRepository'];
        // $this->settingsService = $settingsService;

        $this->logger->debug("SettingsController initialized.");
    }

    /**
     * Displays the system settings page/form.
     * Fetches current settings from the database.
     * Route: /app/settings (GET)
     */
    public function index(): void {
        $this->requireLogin();
        $this->requireAdmin(); // Only admins can access settings

        $pageTitle = "تنظیمات سیستم";
        $settingsData = []; // Associative array [key => value]
        $loadingError = null;
        $formError = $this->getFlashMessage('settings_form_error'); // Get error from save attempt
        $successMessage = $this->getFlashMessage('settings_success'); // Get success message

        try {
            // Fetch all settings from DB using Repository
            // Assumes getAllSettingsAsAssoc() returns [key => value] array
            $settingsData = $this->settingsRepository->getAllSettingsAsAssoc();
            $this->logger->debug("System settings fetched successfully.", ['count' => count($settingsData)]);

            // You might merge/override with config defaults here if needed
            // Example: $settingsData['app_name'] = $settingsData['app_name'] ?? $this->config['app']['name'];

        } catch (Throwable $e) {
            $this->logger->error("Error fetching system settings.", ['exception' => $e]);
            $loadingError = "خطا در بارگذاری تنظیمات از پایگاه داده.";
            $settingsData = []; // Empty array on error
        }

        // Render the settings view (assuming view at src/views/system/settings.php)
        $this->render('system/settings', [
            'page_title'     => $pageTitle,
            'form_action'    => $this->config['app']['base_url'] . '/app/settings/save',
            'settings'       => $settingsData, // Pass fetched settings to the view
            'error_message'  => $formError ? $formError['text'] : null, // Display validation errors if any
            'success_message'=> $successMessage ? $successMessage['text'] : null, // Display success message
            'loading_error'  => $loadingError,
            // Pass known setting keys/types to view for structured form generation if needed
            // 'setting_definitions' => $this->getSettingDefinitions(),
        ]);
    }

    /**
     * Processes the request to save system settings.
     * Route: /app/settings/save (POST)
     */
    public function save(): void {
         $this->requireLogin();
         $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/settings');
        }
        // TODO: Add CSRF token validation

        $this->logger->info("Processing settings save request.");

        // --- Input Extraction & Validation ---
        $submittedSettings = $_POST;
        unset($submittedSettings['csrf_token'], $submittedSettings['submit_action']);

        $settingsToSave = [];
        foreach ($submittedSettings as $key => $value) {
            if ($value !== null && $value !== '') {
                $settingsToSave[$key] = $value;
            }
        }

        if (empty($settingsToSave)) {
            $this->logger->warning("No valid settings found in POST data to save.");
            $this->setSessionMessage("هیچ تنظیماتی برای ذخیره ارسال نشد.", 'danger', 'settings_form_error');
            $this->redirect('/app/settings');
        }

        try {
            $this->settingsRepository->saveSettings($settingsToSave);
            $this->logger->info("System settings updated successfully.", ['keys_updated' => array_keys($settingsToSave)]);
            \App\Utils\Helper::logActivity($this->db, "System settings updated.", 'SUCCESS');
            $this->setSessionMessage('تنظیمات با موفقیت ذخیره شدند.', 'success', 'settings_success');
            $this->redirect('/app/settings');
        } catch (Throwable $e) {
            $this->logger->error("Error saving system settings.", ['exception' => $e]);
            $errorMessage = 'خطا در ذخیره تنظیمات سیستم.';
            if ($this->config['app']['debug']) { $errorMessage .= " جزئیات: " . \App\Utils\Helper::escapeHtml($e->getMessage()); }
            $this->setSessionMessage($errorMessage, 'danger', 'settings_form_error');
            $this->redirect('/app/settings');
        }
    }

} // End SettingsController class
