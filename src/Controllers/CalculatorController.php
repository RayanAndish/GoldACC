<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Dependencies (Likely none specific needed for just displaying the calculator page)
// use App\Utils\Helper;

/**
 * CalculatorController displays the gold calculator page.
 * Inherits from AbstractController.
 */
class CalculatorController extends AbstractController {

    // No specific repository dependencies likely needed for this controller.

    /**
     * Constructor. Relies on parent to inject base dependencies.
     *
     * @param PDO $db Database connection.
     * @param Logger $logger Logger instance.
     * @param array $config Application configuration.
     * @param ViewRenderer $viewRenderer View renderer instance.
     * @param array $services Array of application services.
     */
    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services); // Pass all to parent
        $this->logger->debug("CalculatorController initialized.");
    }

    /**
     * Displays the calculator page.
     * Logic might be primarily handled by JavaScript on the client-side.
     * Route: /app/calculator (GET)
     */
    public function index(): void {
        $this->requireLogin(); // Requires login to access the calculator
        // Optional: Add specific permission check if needed

        $pageTitle = "ماشین حساب طلا";
        $this->logger->debug("Rendering calculator page.");

        // Prepare data for the view (might include default values or settings)
        $viewData = [
            'page_title' => $pageTitle,
            // Add any necessary data for the calculator view here
            // e.g., 'default_mazaneh' => $this->settingsRepository->get('default_mazaneh'),
        ];

        // Render the calculator view (assuming view at src/views/calculator/index.php)
        $this->render('calculator/index', $viewData);
    }

    // Potential future methods for handling calculations via API/AJAX if needed
    // public function calculateApi(): void {
    //     $this->requireLogin();
    //     // Get input data from POST/JSON
    //     // Perform calculation (maybe using a dedicated CalculatorService)
    //     // Return JSON response
    // }

}