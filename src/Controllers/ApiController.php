<?php
// src/Controllers/ApiController.php
namespace App\Controllers;

use App\Core\ViewRenderer;
use App\Models\Product; // Make sure Product is imported
use App\Repositories\ProductRepository; // Make sure ProductRepository is imported
use App\Services\TransactionService; // Make sure TransactionService is imported
use App\Services\EmailService; // Ensure EmailService is imported if used
use App\Services\FormulaService; // Ensure FormulaService is imported if used
use App\Utils\Helper; // Import Helper for sanitization
use Monolog\Logger;
use PDO;
use Throwable;
use Exception; // Explicitly import Exception

class ApiController extends AbstractController
{
    private TransactionService $transactionService;
    private ProductRepository $productRepository;
    private ?EmailService $emailService; // Assuming it's still optional here
    private FormulaService $formulaService; // Ensure this is also assigned properly

    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);

        // --- Services Initialization (CRITICAL FIX: Added productRepository) ---
        if (!isset($services['transactionService']) || !$services['transactionService'] instanceof TransactionService) {
            throw new Exception('TransactionService not found or invalid for ApiController.');
        }
        $this->transactionService = $services['transactionService'];

        if (!isset($services['productRepository']) || !$services['productRepository'] instanceof ProductRepository) {
            throw new Exception('ProductRepository not found or invalid for ApiController.');
        }
        $this->productRepository = $services['productRepository']; // FIX: This was missing

        if (!isset($services['formulaService']) || !$services['formulaService'] instanceof FormulaService) {
            throw new Exception('FormulaService not found or invalid for ApiController.');
        }
        $this->formulaService = $services['formulaService']; // Ensure FormulaService is also assigned to property

        // EmailService is optional
        $this->emailService = $services['emailService'] ?? null; 
        // --- End Services Initialization ---
        
        $this->logger->debug("ApiController initialized correctly.");
    }

    /**
     * A generic calculate method. If it's used, ensure its logic is implemented.
     * Route: /api/calculate (POST)
     */
    public function calculate(): void
    {
        // This method's logic should be specific to what /api/calculate does.
        // If it's for the separate calculator module, its implementation would be different.
        // For now, if you are not actively using this specific route for transactions,
        // it can remain as a basic placeholder or be fleshed out later.
        
        // This is generic placeholder response.
        $this->jsonResponse(['success' => false, 'message' => 'Generic calculate endpoint not implemented.'], 405);
        return;
    }

    /**
     * Returns all calculable values for a transaction item row.
     * Route: /api/calculate-item (POST)
     */
    public function calculateItem(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
            return;
        }

        try {
            $jsonInput = file_get_contents('php://input');
            $inputValues = json_decode($jsonInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON payload.');
            }

            $productId = $inputValues['product_id'] ?? null;
            if (!$productId) {
                $this->jsonResponse(['success' => false, 'message' => 'Product ID is required for item calculation.'], 400);
                return;
            }

            // Using findByIdWithCategory to ensure product category data is available.
            $product = $this->productRepository->findByIdWithCategory($productId);
            if (!$product) {
                $this->jsonResponse(['success' => false, 'message' => 'Product not found for item calculation.'], 404);
                return;
            }

            // Sanitize mazaneh_price before passing it to the Transaction model.
            $cleanedMazanehPrice = Helper::sanitizeFormattedNumber($inputValues['mazaneh_price'] ?? 0);
            $mockTransaction = new \App\Models\Transaction(['mazaneh_price' => $cleanedMazanehPrice]);


            $calculatedItemData = $this->transactionService->recalculateItemOnServer(
                $inputValues, // Renamed from requestData to inputValues for consistency
                $mockTransaction,
                $product
            );

            $this->jsonResponse(['success' => true, 'data' => $calculatedItemData], 200);

        } catch (Throwable $e) {
            $this->logger->error("API item calculation error.", ['exception' => $e, 'request_data' => $inputValues]);
            $errorMessage = 'Calculation error: ' . $e->getMessage();
            if ($this->config['app']['debug']) {
                $errorMessage .= " (Detail: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . ")";
            }
            $this->jsonResponse(['success' => false, 'message' => $errorMessage], 500);
        }
    }

    /**
     * Sends activation info to support.
     */
    public function sendActivationInfo(): void
    {
        if (!$this->emailService) {
            $this->jsonResponse(['success' => false, 'message' => 'Email service is not configured.'], 501);
            return;
        }

        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (!$data) {
                throw new Exception('داده‌های ارسالی نامعتبر است.');
            }

            $requiredFields = ['domain', 'hardware_id', 'request_code'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("فیلد {$field} الزامی است.");
                }
            }
            
            // Assuming sendActivationInfo method exists in EmailService and accepts an array.
            $this->emailService->sendActivationInfo($data);

            $this->logger->info('اطلاعات فعال‌سازی با موفقیت به پشتیبانی ارسال شد.', [
                'domain' => $data['domain'],
                'hardware_id' => '...' . substr($data['hardware_id'], -12) // Log partial for security
            ]);
            
            $this->jsonResponse(['success' => true, 'message' => 'اطلاعات با موفقیت به پشتیبانی ارسال شد.']);

        } catch (Exception $e) {
            $this->logger->error('خطا در ارسال اطلاعات فعال‌سازی به پشتیبانی', [
                'error' => $e->getMessage(),
            ]);

            $this->jsonResponse([
                'success' => false,
                'message' => 'خطا در ارسال اطلاعات به پشتیبانی: ' . $e->getMessage()
            ], 500);
        }
    }
}