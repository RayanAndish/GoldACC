<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions
use Exception;
use Morilog\Jalali\Jalalian; // Add Jalalian namespace

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController; // Use the base controller

// Dependencies
use App\Repositories\ActivityLogRepository;
use App\Utils\Helper; // Utility functions

/**
 * ActivityLogsController handles displaying system activity logs.
 * Includes searching and pagination functionality.
 * Inherits from AbstractController.
 */
class ActivityLogsController extends AbstractController {

    private ActivityLogRepository $activityLogRepository;

    /**
     * Constructor.
     * Injects dependencies from the Front Controller / DI Container.
     *
     * @param PDO $db Database connection.
     * @param Logger $logger Logger instance.
     * @param array $config Application configuration.
     * @param ViewRenderer $viewRenderer View renderer instance.
     * @param array $services Array of application services.
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services // Receive the $services array
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services); // Pass all to parent

        // Retrieve specific repository from the services array stored by the parent
        if (!isset($this->services['activityLogRepository']) || !$this->services['activityLogRepository'] instanceof ActivityLogRepository) {
            throw new \Exception('ActivityLogRepository not found in services array for ActivityLogsController.');
        }
        $this->activityLogRepository = $this->services['activityLogRepository'];
        $this->logger->debug("ActivityLogsController initialized.");
    }

    /**
     * Displays the list of system activity logs.
     * Handles search and pagination.
     * Route: /app/activity-logs (GET)
     */
    public function index(): void {
        // Access Control: Ensure user is logged in and is an admin
        $this->requireLogin();
        $this->requireAdmin(); // Use the helper from AbstractController

        $pageTitle = "گزارش فعالیت‌های سیستم";
        $logs = [];
        $paginationData = []; // Holds pagination calculation results
        $errorMessage = $this->getFlashMessage('log_error'); // Check for flash errors first

        // --- Search and Pagination Logic ---
        $itemsPerPage = (int)($this->config['app']['items_per_page'] ?? 15);
        $currentPage = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $searchTerm = trim(filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? ''); // Basic filter, consider FILTER_SANITIZE_SPECIAL_CHARS if needed
        $filterType = trim(filter_input(INPUT_GET, 'type', FILTER_DEFAULT) ?? ''); // اضافه کردن فیلتر نوع لاگ

        try {
            // Use Repository to fetch data with filtering and pagination
            // Assumes ActivityLogRepository has these methods implemented correctly
            // (including necessary JOINs with users table if username is displayed directly)

            $totalRecords = $this->activityLogRepository->countFiltered($searchTerm, $filterType);
            $totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $itemsPerPage) : 1;
            $currentPage = max(1, min($currentPage, $totalPages)); // Ensure current page is within bounds
            $offset = ($currentPage - 1) * $itemsPerPage;

            $logs = $this->activityLogRepository->getFilteredAndPaginated($searchTerm, $filterType, $itemsPerPage, $offset);

            // Prepare data for display (Escaping, Date Formatting, JSON Decoding)
            foreach ($logs as &$log) { // Use reference to modify array directly
                 $log['username'] = isset($log['username']) ? Helper::escapeHtml($log['username']) : '[سیستم/نامشخص]'; // Handle null usernames
                 $log['action_type'] = Helper::escapeHtml($log['action_type'] ?? 'N/A');
                 $log['ip_address'] = Helper::escapeHtml($log['ip_address'] ?? 'N/A');
                 $log['ray_id'] = isset($log['ray_id']) ? Helper::escapeHtml($log['ray_id']) : '';
                 $log['level_name'] = Helper::escapeHtml($log['level_name'] ?? 'N/A'); // Assuming level_name exists

                 // Decode JSON action_details and display it readably
                 $details = json_decode($log['action_details'] ?? '{}', true);
                 if (json_last_error() === JSON_ERROR_NONE && is_array($details)) {
                     // Display as pretty-printed JSON (ensure it's escaped for HTML)
                     $log['action_details_display'] = '<pre>' . Helper::escapeHtml(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
                     // Or format into a table/list for better readability if structure is known
                 } else {
                     $log['action_details_display'] = Helper::escapeHtml($log['action_details'] ?? ''); // Display raw string if not valid JSON
                 }

                 $log['created_at_persian'] = $log['created_at'] ? Jalalian::fromFormat('Y-m-d H:i:s', $log['created_at'])->format('Y/m/d H:i:s') : '-';
            }
            unset($log); // Unset reference

            // Pagination data for the view helper
            $paginationData = Helper::generatePaginationData($currentPage, $totalPages, $totalRecords, $itemsPerPage);

            $this->logger->debug("Activity logs fetched successfully.", ['count' => count($logs), 'total' => $totalRecords, 'page' => $currentPage]);

        } catch (Throwable $e) {
            $this->logger->error("Error fetching activity logs.", ['exception' => $e]);
            $errorMessage = "خطا در بارگذاری گزارش فعالیت‌ها.";
            // Append detailed error message only in debug mode
            if ($this->config['app']['debug']) {
                 $errorMessage .= " جزئیات: " . Helper::escapeHtml($e->getMessage());
            }
            $logs = []; // Ensure logs array is empty on error
            $paginationData = Helper::generatePaginationData(1, 1, 0, $itemsPerPage); // Default pagination
        }

        // Render the view (assuming view at src/views/activity_logs/list.php)
        $this->render('activity_logs/list', [
            'page_title' => $pageTitle,
            'logs'       => $logs,
            'error_msg'  => $errorMessage, // Display error if any
            'search_term'=> Helper::escapeHtml($searchTerm), // Escape search term for display in form
            'filter_type'=> Helper::escapeHtml($filterType), // اضافه کردن فیلتر نوع به ویو
            'pagination' => $paginationData // Pass pagination data to view
        ]);
    }

    // Other potential methods related to activity logs (e.g., view details, clear logs)
    // public function view(int $logId): void { /* ... */ }
    // public function clear(): void { /* Requires POST and careful permission checks */ }

} // End ActivityLogsController class