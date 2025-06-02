<?php

namespace App\Services;

use Monolog\Logger;
use Exception;
use Throwable;

// Required dependencies for data collection
use App\Repositories\ActivityLogRepository;
use App\Repositories\UserRepository;
use App\Services\ApiClient;
use App\Services\LicenseService;
use App\Services\SecurityService;
use App\Services\UpdateService; // To get version info

/**
 * MonitoringService collects system health and status information and sends it
 * periodically to a central monitoring server via the ApiClient.
 */
class MonitoringService {

    // Injected dependencies
    private ApiClient $apiClient;
    private Logger $logger;
    private array $config;
    private ActivityLogRepository $activityLogRepository;
    private UserRepository $userRepository;
    private LicenseService $licenseService;
    private SecurityService $securityService;
    private UpdateService $updateService;

    /**
     * Constructor.
     *
     * @param ApiClient $apiClient API client instance.
     * @param Logger $logger Logger instance.
     * @param array $config Application configuration.
     * @param ActivityLogRepository $activityLogRepository Activity log repository.
     * @param UserRepository $userRepository User repository.
     * @param LicenseService $licenseService License service.
     * @param SecurityService $securityService Security service.
     * @param UpdateService $updateService Update service.
     */
    public function __construct(
        ApiClient $apiClient,
        Logger $logger,
        array $config,
        ActivityLogRepository $activityLogRepository,
        UserRepository $userRepository,
        LicenseService $licenseService,
        SecurityService $securityService,
        UpdateService $updateService // Inject UpdateService
    ) {
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        $this->config = $config;
        $this->activityLogRepository = $activityLogRepository;
        $this->userRepository = $userRepository;
        $this->licenseService = $licenseService;
        $this->securityService = $securityService;
        $this->updateService = $updateService; // Store injected service
        $this->logger->debug("MonitoringService initialized.");
    }

    /**
     * Collects various monitoring data points from the application and server environment.
     * Handles errors gracefully for individual data points.
     *
     * @return array An array containing the collected monitoring data.
     */
    private function collectData(): array {
        $this->logger->debug("Collecting monitoring data.");
        $data = [
            'report_format_version' => '1.0', // Version of this report structure
            'timestamp_utc' => gmdate('Y-m-d H:i:s'), // Use UTC for consistency
            'app_name' => $this->config['app']['name'] ?? 'UnknownApp',
            'app_env' => $this->config['app']['env'] ?? 'unknown',
            'domain' => $_SERVER['SERVER_NAME'] ?? php_uname('n'), // Use server name or hostname
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'N/A', // Best guess for server IP
            'php_version' => PHP_VERSION,
            'os_family' => PHP_OS_FAMILY, // Windows, Linux, Darwin, etc.
            // Add more static environment info if needed
        ];

        // Collect data points individually, catching errors for each
        $data['hardware_id'] = $this->tryCollect([$this->securityService, 'generateHardwareId'], 'hardware_id');
        $data = array_merge($data, $this->tryCollectLicenseStatus());
        $data['user_count'] = $this->tryCollect([$this->userRepository, 'countAll'], 'user_count');
        $data['recent_errors_count'] = $this->tryCollect([$this->activityLogRepository, 'countErrorsLast24Hours'], 'recent_errors_count');
        $data['current_version'] = $this->tryCollect([$this->updateService, 'getCurrentVersion'], 'current_version');

        // --- Optional Advanced Metrics (might require more permissions/libraries) ---
        // Disk Space
        $data['disk_free_space_gb'] = $this->tryCollect(function() {
            $path = $this->config['paths']['root'] ?? '/'; // Check root path
            $bytes = @disk_free_space($path); // Use @ to suppress errors
            return $bytes !== false ? round($bytes / (1024**3), 2) : null;
        }, 'disk_free_space');
        $data['disk_total_space_gb'] = $this->tryCollect(function() {
             $path = $this->config['paths']['root'] ?? '/';
             $bytes = @disk_total_space($path);
             return $bytes !== false ? round($bytes / (1024**3), 2) : null;
        }, 'disk_total_space');

        // Memory Usage (Approximate PHP usage)
        $data['memory_usage_mb'] = $this->tryCollect(function() {
            return round(memory_get_usage(true) / (1024**2), 2); // Real memory usage
        }, 'memory_usage');
        $data['memory_peak_usage_mb'] = $this->tryCollect(function() {
             return round(memory_get_peak_usage(true) / (1024**2), 2);
        }, 'memory_peak_usage');

        // --- End Optional Metrics ---


        // Filter out null values before returning? Optional.
        // $data = array_filter($data, fn($value) => $value !== null);

        $this->logger->debug("Monitoring data collection finished.", ['collected_keys' => array_keys($data)]);
        return $data;
    }

    /**
     * Helper function to safely call a data collection method and handle exceptions.
     *
     * @param callable $callable The function or method to call.
     * @param string $metricName A descriptive name for the metric being collected (for logging).
     * @return mixed The result of the callable, or a default error value (e.g., 'Error', null) on failure.
     */
    private function tryCollect(callable $callable, string $metricName, mixed $errorValue = 'Error'): mixed {
        try {
            return $callable();
        } catch (Throwable $e) {
            $this->logger->error("Error collecting monitoring metric.", ['metric' => $metricName, 'exception' => $e]);
            return $errorValue;
        }
    }

    /**
     * Specific helper to collect license status safely.
     *
     * @return array License status data or error indicators.
     */
    private function tryCollectLicenseStatus(): array {
         $result = [
             'license_valid' => false,
             'license_message' => 'Error collecting status',
             'license_type' => null,
             'license_expiry' => null,
             'license_key_prefix' => null
         ];
         try {
             // Use the existing checkLicense method
             $licenseStatus = $this->licenseService->checkLicense();
             $result['license_valid'] = $licenseStatus['valid'];
             $result['license_message'] = $licenseStatus['message'];
             if ($licenseStatus['valid'] && isset($licenseStatus['license_info'])) {
                  $info = $licenseStatus['license_info'];
                  $result['license_type'] = $info['license_type'] ?? null;
                  $result['license_expiry'] = $info['expires_at'] ?? null;
                  $result['license_key_prefix'] = isset($info['license_key']) ? substr($info['license_key'], 0, 5) . '...' : null;
             }
         } catch (Throwable $e) {
              $this->logger->error("Error collecting license status for monitoring.", ['exception' => $e]);
              // Keep default error values in $result
         }
         return $result;
    }

    /**
     * Collects monitoring data and sends it to the central API server.
     * Intended to be called periodically (e.g., by a cron job or scheduled task).
     *
     * @return array The response from the central monitoring API server.
     * @throws Exception If data collection or sending fails critically.
     */
    public function sendReport(): array {
        $this->logger->info("Attempting to send monitoring report to central server.");
        try {
            // 1. Collect the data
            $monitoringData = $this->collectData();

            // 2. Send data via ApiClient
            // Assumes ApiClient has a method like `sendMonitoringReport` or a generic `post` method
            // The endpoint on the central server might be '/api/monitor/report'
            $endpoint = '/api/monitor/report'; // Define your central server endpoint
            // $apiResponse = $this->apiClient->sendMonitoringReport($monitoringData); // If specific method exists
            $apiResponse = $this->apiClient->sendRequest($endpoint, $monitoringData); // Using generic sendRequest

            // 3. Process API response
            // Assuming API returns ['success' => bool, 'message' => string]
            if (!isset($apiResponse['success'])) {
                 $this->logger->error("Invalid response structure received from monitoring API.", ['api_response' => $apiResponse]);
                 // Don't throw exception? Log and return the response.
                 // throw new Exception("Invalid response from monitoring server.");
                 return ['success' => false, 'message' => 'Invalid response from server', 'api_response' => $apiResponse];
            }

            if ($apiResponse['success']) {
                 $this->logger->info("Monitoring report sent successfully to central server.");
                 return ['success' => true, 'message' => 'Report sent successfully.'];
            } else {
                 $this->logger->warning("Central server failed to process monitoring report.", ['message' => $apiResponse['message'] ?? 'Unknown server error', 'api_response' => $apiResponse]);
                  return ['success' => false, 'message' => $apiResponse['message'] ?? 'Server processing error'];
            }

        } catch (Throwable $e) {
            // Catch errors from collectData or apiClient->sendRequest
            $this->logger->error("Failed to send monitoring report.", ['exception' => $e]);
            // Rethrow to indicate failure to the caller
            throw new Exception("Failed to send monitoring report: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Handles receiving a monitoring report (SERVER-SIDE IMPLEMENTATION).
     * This method belongs on the CENTRAL monitoring server, not the client application.
     * It acts as the API endpoint logic.
     *
     * @param array $reportData The monitoring data received via POST request.
     * @return array Response array ['success' => bool, 'message' => string].
     */
    public function receiveReport(array $reportData): array {
         // Log a critical error if this is somehow executed on the client system
         $this->logger->critical("CRITICAL: MonitoringService::receiveReport executed on client system! This method is for the central server only.");
         // Return an error response appropriate for an API endpoint
         http_response_code(501); // Not Implemented (on client)
         return ['success' => false, 'message' => 'Endpoint not implemented on this system.'];

         /* --- CENTRAL SERVER LOGIC EXAMPLE ---
         $this->logger->info("Received monitoring report.", ['domain' => $reportData['domain'] ?? 'unknown']);
         // 1. Validate the received data ($reportData)
         // 2. Store the data in the central monitoring database
         // 3. Perform analysis or trigger alerts based on the data
         // 4. Return a success response
         return ['success' => true, 'message' => 'Report received and processed.'];
         */
    }

} // End MonitoringService class