<?php

namespace App\Controllers;

use App\Core\ViewRenderer;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Repositories\ProductCategoryRepository;
use PDO;
use Monolog\Logger;
use App\Utils\Helper;
use Throwable;

class ProductController extends AbstractController {

    private ProductRepository $productRepository;
    private ProductCategoryRepository $categoryRepository;

    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        $this->productRepository = $this->services['productRepository'] ?? new ProductRepository($this->db, $this->logger);
        $this->categoryRepository = $this->services['productCategoryRepository'] ?? new ProductCategoryRepository($this->db, $this->logger);
    }

    private function getCommonViewData(string $pageTitle = ''): array {
        return [
            'appName'        => $this->config['app']['name'] ?? 'پیش‌فرض',
            'baseUrl'        => $this->config['app']['base_url'] ?? '',
            'currentUri'     => $_SERVER['REQUEST_URI'] ?? '/',
            'isLoggedIn'     => $this->authService->isLoggedIn(),
            'loggedInUser'   => $this->authService->getCurrentUser(),
            'flashMessage'   => $this->getFlashMessage('product_message'),
            'pageTitle'      => $pageTitle,
        ];
    }

    public function index(): void {
        $this->requireLogin();
        $pageTitle = 'لیست محصولات';
        $viewData = $this->getCommonViewData($pageTitle);

        try {
            $viewData['products'] = $this->productRepository->findAll([], true);
            $viewData['success_msg'] = $this->getFlashMessage('product_success')['text'] ?? null;
        } catch (Throwable $e) {
            $this->logger->error("Error fetching products list.", ['exception' => $e]);
            $viewData['error_msg'] = "خطا در بارگذاری لیست محصولات.";
            $viewData['products'] = [];
        }
        $viewData['flashMessage'] = $this->getFlashMessage('product_list_error');

        $this->viewRenderer->render('products/list', $viewData);
    }

    public function showAddForm(): void {
        $this->requireLogin();
        $pageTitle = 'افزودن محصول جدید';
        $viewData = $this->getCommonViewData($pageTitle);

        $viewData['product'] = new Product();
        try {
            $viewData['categories'] = $this->categoryRepository->findAllActives();
        } catch (Throwable $e) {
            $this->logger->error("Error fetching categories for product form.", ['exception' => $e]);
            $viewData['categories'] = [];
            $this->setSessionMessage('خطا در بارگذاری دسته‌بندی‌ها برای فرم محصول.', 'danger', 'form_error');
        }
        
        $viewData['action'] = $this->config['app']['base_url'] . '/app/products/save';
        $viewData['formTitle'] = 'افزودن محصول جدید';
        $viewData['submitButtonText'] = 'ذخیره محصول';
        
        // خواندن خطاها و داده‌های فرم از session flash
        $validationErrorsFlash = $this->getFlashMessage('validation_errors_prod');
        $viewData['errors'] = ($validationErrorsFlash && isset($validationErrorsFlash['text'])) ? unserialize($validationErrorsFlash['text']) : [];
        if (!is_array($viewData['errors'])) $viewData['errors'] = [];

        $formDataFlash = $this->getFlashMessage('form_data_prod');
        $formData = ($formDataFlash && isset($formDataFlash['text'])) ? unserialize($formDataFlash['text']) : [];

        // اگر داده‌های فرم وجود داشت، آنها را به مدل منتقل کن
        if (is_array($formData) && !empty($formData)) {
            $viewData['product']->name = $formData['name'] ?? '';
            $viewData['product']->category_id = isset($formData['category_id']) ? (int)$formData['category_id'] : 0;
            $viewData['product']->product_code = $formData['product_code'] ?? '';
            $viewData['product']->description = $formData['description'] ?? '';
            $viewData['product']->default_carat = isset($formData['default_carat']) && $formData['default_carat'] !== '' ? (float)$formData['default_carat'] : null;
            $viewData['product']->is_active = isset($formData['is_active']);
        }

        $this->viewRenderer->render('products/form', $viewData);
    }

    public function showEditForm(int $id): void {
        $this->requireLogin();
        $pageTitle = 'ویرایش محصول';
        $viewData = $this->getCommonViewData($pageTitle);

        $product = $this->productRepository->findById($id);
        if (!$product) {
            $this->logger->warning("Product not found for editing.", ['id' => $id]);
            $this->setSessionMessage('محصول یافت نشد.', 'danger', 'product_list_error');
            $this->redirect('app/products'); // حذف / از ابتدای مسیر
            return;
        }
        
        try {
            $viewData['categories'] = $this->categoryRepository->findAllActives();
        } catch (Throwable $e) {
             $this->logger->error("Error fetching categories for product edit form.", ['exception' => $e]);
             $viewData['categories'] = [];
             $this->setSessionMessage('خطا در بارگذاری دسته‌بندی‌ها.', 'danger', 'form_error');
        }

        $viewData['product'] = $product;
        $viewData['action'] = $this->config['app']['base_url'] . '/app/products/save';
        $viewData['formTitle'] = "ویرایش محصول: " . Helper::escapeHtml($product->name);
        $viewData['submitButtonText'] = 'به‌روزرسانی محصول';
        
        // خواندن خطاها و داده‌های فرم از session flash
        $validationErrorsFlash = $this->getFlashMessage('validation_errors_prod');
        $viewData['errors'] = ($validationErrorsFlash && isset($validationErrorsFlash['text'])) ? unserialize($validationErrorsFlash['text']) : [];
        if (!is_array($viewData['errors'])) $viewData['errors'] = [];

        $formDataFlash = $this->getFlashMessage('form_data_prod');
        $formData = ($formDataFlash && isset($formDataFlash['text'])) ? unserialize($formDataFlash['text']) : [];

        // اگر داده‌های فرم وجود داشت، آنها را به مدل منتقل کن
        if (is_array($formData) && !empty($formData)) {
            $viewData['product']->name = $formData['name'] ?? $product->name;
            $viewData['product']->category_id = isset($formData['category_id']) ? (int)$formData['category_id'] : $product->category_id;
            $viewData['product']->product_code = $formData['product_code'] ?? $product->product_code;
            $viewData['product']->description = $formData['description'] ?? $product->description;
            $viewData['product']->default_carat = isset($formData['default_carat']) && $formData['default_carat'] !== '' ? (float)$formData['default_carat'] : $product->default_carat;
            $viewData['product']->is_active = isset($formData['is_active']) ? (bool)$formData['is_active'] : $product->is_active;
        }

        $this->viewRenderer->render('products/form', $viewData);
    }

    public function save(?int $routeId = null): void {
        $this->requireLogin();

        // Determine product ID: prioritize route ID, then POST ID
        $productId = $routeId ?? (isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null);

        // Ensure request method is appropriate (POST for create, maybe POST/PUT for update)
        // Note: HTML forms only support GET/POST. PUT/DELETE often handled via _method field.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
            $this->logger->warning('Invalid request method for save action.', ['method' => $_SERVER['REQUEST_METHOD']]);
            $this->redirect('app/products'); // Or show an error
            return;
        }

        $isUpdate = $productId !== null;
        $redirectUrlOnError = 'app/products/' . ($isUpdate ? 'edit/' . $productId : 'add');

        $product = $isUpdate ? $this->productRepository->findById($productId) : new Product();

        if ($isUpdate && !$product) {
            $this->logger->error("Product not found for saving (update).", ['id' => $productId]);
            $this->setSessionMessage('محصول مورد نظر برای بروزرسانی یافت نشد', 'danger', 'form_error'); // Changed message key
            $this->redirect('app/products');
            return;
        }
        if (!$isUpdate) $product = new Product(); // Ensure new product object if not updating
        // Ensure ID is set on the object for the repository save method and validation check
        $product->id = $productId;

        // Populate basic fields
        $product->name = trim($_POST['name'] ?? '');
        $product->category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $product->product_code = trim($_POST['product_code'] ?? null);
        $product->description = trim($_POST['description'] ?? null);
        $product->default_carat = isset($_POST['default_carat']) && $_POST['default_carat'] !== '' ? (float)$_POST['default_carat'] : null;
        $product->is_active = isset($_POST['is_active']);

        // ========== START: Populate potentially missing fields ========== 
        $product->quantity = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (float)$_POST['quantity'] : null;
        $product->weight = isset($_POST['weight']) && $_POST['weight'] !== '' ? (float)$_POST['weight'] : null;
        $product->coin_year = isset($_POST['coin_year']) && $_POST['coin_year'] !== '' ? (int)$_POST['coin_year'] : null;
        // Note: unit_of_measure should also be saved if the field was added to the form
        $product->unit_of_measure = $_POST['unit_of_measure'] ?? 'gram'; // Default to gram if not sent
        // ========== END: Populate potentially missing fields ========== 

        // --- Populate and Validate Capital Fields --- 
        $capitalQuantityRaw = $_POST['capital_quantity'] ?? null;
        $capitalWeightRaw = $_POST['capital_weight_grams'] ?? null;
        $capitalCaratRaw = $_POST['capital_reference_carat'] ?? null;

        $product->capital_quantity = null;
        $product->capital_weight_grams = null;
        $product->capital_reference_carat = null;

        $errors = []; // Initialize errors array here

        if ($capitalQuantityRaw !== null && $capitalQuantityRaw !== '') {
            $cleanedQty = Helper::sanitizeFormattedNumber($capitalQuantityRaw);
            if (is_numeric($cleanedQty) && ($cq = floatval($cleanedQty)) >= 0) {
                $product->capital_quantity = $cq;
            } else {
                $errors['capital_quantity'] = 'مقدار سرمایه تعدادی نامعتبر است (باید عدد غیر منفی باشد).';
            }
        }

        if ($capitalWeightRaw !== null && $capitalWeightRaw !== '') {
            $cleanedWeight = Helper::sanitizeFormattedNumber($capitalWeightRaw);
             if (is_numeric($cleanedWeight) && ($cw = floatval($cleanedWeight)) >= 0) {
                $product->capital_weight_grams = $cw;

                // Validate capital carat only if weight is provided
                if ($capitalCaratRaw !== null && $capitalCaratRaw !== '') {
                    $cleanedCarat = Helper::sanitizeFormattedNumber($capitalCaratRaw);
                    if (is_numeric($cleanedCarat) && ($cc = intval($cleanedCarat)) > 0 && $cc <= 1000) {
                        $product->capital_reference_carat = $cc;
                    } else {
                        $errors['capital_reference_carat'] = 'عیار مبنای سرمایه نامعتبر است (باید عدد بین 1 تا 1000 باشد).';
                    }
                } else {
                     // If weight is set, carat should ideally be set too, defaulting to 750
                     $product->capital_reference_carat = 750; 
                }
            } else {
                $errors['capital_weight_grams'] = 'مقدار سرمایه وزنی نامعتبر است (باید عدد غیر منفی باشد).';
            }
        } else {
            // If weight is empty/null, carat is irrelevant, set to null or default
            $product->capital_reference_carat = 750; // Or null, depending on desired DB state
        }
        // --- End Capital Fields --- 

        $product->tax_enabled = isset($_POST['tax_enabled']) ? 1 : 0;
        $product->tax_rate = isset($_POST['tax_rate']) && $_POST['tax_rate'] !== '' ? (float)$_POST['tax_rate'] : null;
        $product->vat_enabled = isset($_POST['vat_enabled']) ? 1 : 0;
        $product->vat_rate = isset($_POST['vat_rate']) && $_POST['vat_rate'] !== '' ? (float)$_POST['vat_rate'] : null;

        // --- Basic Validation (Continue with existing checks) ---
        if (empty($product->name)) {
            $errors['name'] = 'نام محصول نمی‌تواند خالی باشد.';
        }
        if (empty($product->category_id) || $product->category_id <= 0) {
            $errors['category_id'] = 'لطفاً یک دسته‌بندی معتبر انتخاب کنید.';
        } else {
            // Check if category exists
            $categoryExists = $this->categoryRepository->findById($product->category_id);
            if (!$categoryExists) {
                $errors['category_id'] = 'دسته‌بندی انتخاب شده معتبر نیست.';
            }
        }
        if (!empty($product->product_code) && $this->productRepository->productCodeExists($product->product_code, $product->id)) {
            $errors['product_code'] = 'این کد محصول قبلاً استفاده شده است.';
        }

        if (!empty($errors)) {
            $this->logger->warning("Validation failed for product.", ['errors' => $errors, 'data' => $_POST]);
            $this->setSessionMessage(serialize($errors), 'danger', 'validation_errors_prod');
            $this->setSessionMessage(serialize($_POST), 'info', 'form_data_prod'); 
            $this->setSessionMessage(implode('<br>', array_values($errors)), 'danger', 'default');

            $this->redirect($redirectUrlOnError);
            return;
        }

        try {
            $savedId = $this->productRepository->save($product);
            if ($savedId !== false) {
                $actionWord = $isUpdate ? 'به‌روزرسانی' : 'ایجاد';
                $this->setSessionMessage("محصول با موفقیت {$actionWord} شد.", 'success', 'product_success');
                $this->logger->info("Product {$actionWord} successfully.", ['id' => $savedId]);
                $this->redirect('app/products');
                return;
            } else {
                $this->setSessionMessage('خطا در ذخیره محصول رخ داد (ریپازیتوری false برگرداند).', 'danger', 'form_error');
                $this->logger->error("Failed to save product (repository returned false).", ['data' => $_POST, 'isUpdate' => $isUpdate]);
                $this->setSessionMessage(serialize($_POST), 'info', 'form_data_prod');
                if (!empty($errors)) {
                    $this->setSessionMessage(serialize($errors), 'danger', 'validation_errors_prod');
                    $this->setSessionMessage(implode('<br>', array_values($errors)), 'danger', 'default');
                }
                $this->redirect($redirectUrlOnError);
                return;
            }
        } catch (Throwable $e) {
            $this->logger->error("Failed to save product.", ['data' => $_POST, 'isUpdate' => $isUpdate, 'exception' => $e]);
            $this->setSessionMessage('خطا در ذخیره محصول: ' . $e->getMessage(), 'danger', 'form_error');
            $this->setSessionMessage(serialize($_POST), 'info', 'form_data_prod');
            if (!empty($errors)) {
                $this->setSessionMessage(serialize($errors), 'danger', 'validation_errors_prod');
                $this->setSessionMessage(implode('<br>', array_values($errors)), 'danger', 'default');
            }
            $this->redirect($redirectUrlOnError);
            return;
        }
    }

    public function delete(int $id): void {
        $this->requireLogin();

        try {
            if ($this->productRepository->delete($id)) {
                $this->setSessionMessage('محصول با موفقیت حذف شد.', 'success', 'product_success');
                $this->logger->info("Product deleted successfully.", ['id' => $id]);
            } else {
                $this->setSessionMessage('خطا در حذف محصول یا محصول یافت نشد.', 'danger', 'product_list_error');
                $this->logger->error("Failed to delete product (not found or db error).", ['id' => $id]);
            }
        } catch (Throwable $e) {
             $this->logger->error("Exception during product deletion.", ['id' => $id, 'exception' => $e]);
             $this->setSessionMessage('خطای سیستمی هنگام حذف محصول: ' . $e->getMessage(), 'danger', 'product_list_error');
        }
        $this->redirect('app/products');
    }
}