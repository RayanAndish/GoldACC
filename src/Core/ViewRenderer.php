<?php

namespace App\Core; // Namespace مطابق با پوشه src/Core

use Exception; // For throwing exceptions
use Monolog\Logger; // Optional: If logging is needed within the renderer

/**
 * ViewRenderer class handles rendering PHP view files and managing layouts (header/footer).
 * It finds and includes the specified view file, making the provided data available to it.
 */
class ViewRenderer {

    private string $viewsPath;
    private string $layoutsPath; // Path to the directory containing layout files (header.php, footer.php)
    private ?Logger $logger; // Optional logger instance

    /**
     * Constructor.
     * Sets the base paths for view files and layout files.
     *
     * @param string $viewsPath Absolute path to the views directory (e.g., .../src/views).
     * @param string $layoutsPath Absolute path to the layouts directory (e.g., .../src/views/layouts).
     * @param Logger|null $logger Optional logger instance.
     */
    public function __construct(string $viewsPath, string $layoutsPath, ?Logger $logger = null) {
        // Ensure paths are valid directories (optional but recommended)
        if (!is_dir($viewsPath)) {
             throw new Exception("Invalid views path provided to ViewRenderer: " . htmlspecialchars($viewsPath));
        }
        if (!is_dir($layoutsPath)) {
             throw new Exception("Invalid layouts path provided to ViewRenderer: " . htmlspecialchars($layoutsPath));
        }

        $this->viewsPath = rtrim($viewsPath, '/\\');
        $this->layoutsPath = rtrim($layoutsPath, '/\\');
        $this->logger = $logger;
        $this->logger?->debug("ViewRenderer initialized.", ['views' => $this->viewsPath, 'layouts' => $this->layoutsPath]);
    }

    /**
     * Renders a specified view file.
     * Includes the view file and makes the provided data available to it within the $viewData variable.
     * Optionally includes header and footer layout files.
     *
     * **Important:** View files should access passed data using `$viewData['variableName']`.
     * Example: `echo $viewData['page_title'];` instead of `echo $page_title;`
     *
     * @param string $viewName View file name relative to the views path (e.g., 'users/list', 'auth/login'). No '.php' extension needed.
     * @param array $data Associative array of data to be made available to the view.
     * @param bool $withLayout Whether to include the header and footer layout files.
     * @param string $layoutName Name of the layout to use (currently only supports 'default' via header/footer). Future use.
     *
     * @throws Exception If the specified view file does not exist.
     *                   Exceptions occurring *inside* the view file will be caught by the global ErrorHandler.
     */
    public function render(string $viewName, array $data = [], bool $withLayout = true, string $layoutName = 'default'): void {
        // Construct the full path to the view file
        $viewFilePath = $this->viewsPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $viewName) . '.php';

        // Check if the view file exists
        if (!file_exists($viewFilePath)) {
            $this->logger?->error("View file not found.", ['path' => $viewFilePath]);
            throw new Exception("View file not found: " . htmlspecialchars($viewFilePath));
        }

        // Make data available to the view inside a single variable ($viewData)
        // This avoids polluting the local scope and potential variable name collisions (safer than extract()).
        $viewData = $data;

        // لاگ کردن داده‌های ارسالی به view
        if (isset($viewData['error_message'])) {
            $this->logger?->debug("View data contains error_message", [
                'error_message' => $viewData['error_message'],
                'is_array' => is_array($viewData['error_message']),
                'has_text' => is_array($viewData['error_message']) && isset($viewData['error_message']['text']),
                'text_value' => is_array($viewData['error_message']) && isset($viewData['error_message']['text']) ? $viewData['error_message']['text'] : null
            ]);
        } else {
            $this->logger?->debug("View data does not contain error_message");
        }

        // --- Output Buffering Note ---
        // This renderer assumes output buffering is handled by the caller (e.g., the Front Controller).
        // It directly includes files, which writes to the active output buffer.

        // Include header if layout is enabled
        if ($withLayout) {
            $headerPath = $this->layoutsPath . DIRECTORY_SEPARATOR . 'header.php'; // Assuming default header name
            if (file_exists($headerPath)) {
                // Include the header file. $viewData is available inside header.php
                 $this->logger?->debug("Including layout header.", ['path' => $headerPath]);
                include $headerPath;
            } else {
                 // Log a warning if header is expected but not found. Don't halt execution.
                 $this->logger?->warning("Layout header file not found, but layout was requested.", ['path' => $headerPath]);
                 error_log("Warning: Layout header file not found: " . $headerPath); // Log to PHP error log if logger not available
            }
        }

        // Include the main view file
        // $viewData is available inside the included file.
         $this->logger?->debug("Including main view file.", ['path' => $viewFilePath]);
        include $viewFilePath;

        // Include footer if layout is enabled
        if ($withLayout) {
            $footerPath = $this->layoutsPath . DIRECTORY_SEPARATOR . 'footer.php'; // Assuming default footer name
            if (file_exists($footerPath)) {
                // Include the footer file. $viewData is available inside footer.php
                 $this->logger?->debug("Including layout footer.", ['path' => $footerPath]);
                include $footerPath;
            } else {
                // Log a warning if footer is expected but not found.
                $this->logger?->warning("Layout footer file not found, but layout was requested.", ['path' => $footerPath]);
                error_log("Warning: Layout footer file not found: " . $footerPath);
            }
        }
    }

    /**
     * Renders a partial view (a smaller view fragment without layout).
     * Useful for including sub-components within a main view or for AJAX responses.
     * Data is made available via $viewData['variableName'].
     *
     * @param string $partialName Partial view file name relative to views path.
     * @param array $data Data for the partial view.
     * @throws Exception If the partial view file does not exist.
     */
    public function renderPartial(string $partialName, array $data = []): void {
        // Construct the full path to the partial view file
        $partialPath = $this->viewsPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $partialName) . '.php';

        // Check if the partial view file exists
        if (!file_exists($partialPath)) {
            $this->logger?->error("Partial view file not found.", ['path' => $partialPath]);
            throw new Exception("Partial view file not found: " . htmlspecialchars($partialPath));
        }

        // Make data available
        $viewData = $data;

        // Include the partial view file
        $this->logger?->debug("Including partial view file.", ['path' => $partialPath]);
        include $partialPath;
    }

    // Note: No need for explicit getData() helper if views use $viewData['key'] syntax.

} // End ViewRenderer class