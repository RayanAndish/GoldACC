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
        $this->productRepository = $services['productRepository'];
        $this->categoryRepository = $services['productCategoryRepository'];
    }

    public function index(): void {
        $this->requireLogin();
        $pageTitle = 'لیست محصولات';

        try {
            $products = $this->productRepository->findAll([], true);
            $successMsg = $this->getFlashMessage('product_success')['text'] ?? null;
        } catch (Throwable $e) {
            $this->logger->error("Error fetching products list.", ['exception' => $e]);
            $this->setSessionMessage("خطا در بارگذاری لیست محصولات.", 'danger', 'product_list_error');
            $products = [];
        }
        
        $this->render('products/list', [
            'page_title' => $pageTitle,
            'products' => $products,
            'success_msg' => $successMsg,
            'error_msg' => $this->getFlashMessage('product_list_error')['text'] ?? null
        ]);
    }

    public function showAddForm(): void {
        $this->showForm(null);
    }

    public function showEditForm(int $id): void {
        $this->showForm($id);
    }

    private function showForm(?int $id): void
    {
        $this->requireLogin();
        $isEditMode = $id !== null;
        $pageTitle = $isEditMode ? 'ویرایش محصول' : 'افزودن محصول جدید';
        
        $product = $isEditMode ? $this->productRepository->findById($id, true) : new Product();

        if ($isEditMode && !$product) {
            $this->setSessionMessage('محصول یافت نشد.', 'danger', 'product_list_error');
            $this->redirect('/app/products');
            return;
        }

        try {
            $categories = $this->categoryRepository->findAllActives();
        } catch (Throwable $e) {
            $this->logger->error("Error fetching categories for product form.", ['exception' => $e]);
            $this->setSessionMessage('خطا در بارگذاری دسته‌بندی‌ها.', 'danger');
            $categories = [];
        }

        // Repopulate form from session flash data if validation failed
        $oldData = $this->getFlashMessage('form_data_prod')['text'] ?? [];
        if(!empty($oldData)) {
            $product = new Product($oldData);
        }

        $this->render('products/form', [
            'page_title' => $pageTitle,
            'product' => $product,
            'categories' => $categories,
            'errors' => $this->getFlashMessage('validation_errors_prod')['text'] ?? [],
        ]);
    }

    public function save(?int $id = null): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/products');
            return;
        }
        
        $isEditMode = $id !== null;
        $postData = $_POST;
        
        // --- Validation ---
        $errors = $this->validateProductData($postData, $id);
        if (!empty($errors)) {
            $this->setSessionMessage($errors, 'danger', 'validation_errors_prod');
            $this->setSessionMessage($postData, 'info', 'form_data_prod'); // Repopulate form
            $redirectUrl = '/app/products/' . ($isEditMode ? 'edit/' . $id : 'add');
            $this->redirect($redirectUrl);
            return;
        }

        // --- Populate Model ---
        $product = $isEditMode ? $this->productRepository->findById($id) : new Product();
        if ($isEditMode && !$product) {
            $this->setSessionMessage('محصول برای ویرایش یافت نشد.', 'danger');
            $this->redirect('/app/products');
            return;
        }

        $product->name = trim($postData['name']);
        $product->category_id = (int)$postData['category_id'];
        $product->product_code = trim($postData['product_code']) ?: null;
        $product->unit_of_measure = trim($postData['unit_of_measure']);
        $product->description = trim($postData['description']) ?: null;
        $product->default_carat = !empty($postData['default_carat']) ? (float)$postData['default_carat'] : null;
        $product->is_active = isset($postData['is_active']);
        
        // Populate capital fields
        $product->capital_quantity = !empty($postData['capital_quantity']) ? (float)Helper::sanitizeFormattedNumber($postData['capital_quantity']) : null;
        $product->capital_weight_grams = !empty($postData['capital_weight_grams']) ? (float)Helper::sanitizeFormattedNumber($postData['capital_weight_grams']) : null;
        $product->capital_reference_carat = !empty($postData['capital_reference_carat']) ? (int)$postData['capital_reference_carat'] : 750;

        // **Populate new tax fields**
        $product->vat_base_type = $postData['vat_base_type'] ?? 'NONE';
        $product->general_tax_base_type = $postData['general_tax_base_type'] ?? 'NONE';
        $product->tax_rate = !empty($postData['tax_rate']) ? (float)$postData['tax_rate'] : null;
        $product->vat_rate = !empty($postData['vat_rate']) ? (float)$postData['vat_rate'] : null;

        // --- Save to Database ---
        try {
            $savedId = $this->productRepository->save($product);
            if ($savedId) {
                $actionWord = $isEditMode ? 'به‌روزرسانی' : 'ایجاد';
                $this->setSessionMessage("محصول با موفقیت {$actionWord} شد.", 'success', 'product_success');
                $this->redirect('/app/products');
            } else {
                throw new \Exception("ریپازیتوری در ذخیره محصول ناموفق بود.");
            }
        } catch (Throwable $e) {
            $this->logger->error("Failed to save product.", ['data' => $postData, 'exception' => $e]);
            $this->setSessionMessage('خطا در ذخیره محصول: ' . $e->getMessage(), 'danger');
            $this->setSessionMessage($postData, 'info', 'form_data_prod');
            $redirectUrl = '/app/products/' . ($isEditMode ? 'edit/' . $id : 'add');
            $this->redirect($redirectUrl);
        }
    }

    private function validateProductData(array $postData, ?int $excludeId): array
    {
        $errors = [];
        if (empty(trim($postData['name']))) {
            $errors['name'] = 'نام محصول نمی‌تواند خالی باشد.';
        }
        if (empty($postData['category_id'])) {
            $errors['category_id'] = 'لطفاً یک دسته‌بندی انتخاب کنید.';
        } elseif (!$this->categoryRepository->findById((int)$postData['category_id'])) {
            $errors['category_id'] = 'دسته‌بندی انتخاب شده معتبر نیست.';
        }
        if (!empty(trim($postData['product_code'])) && $this->productRepository->productCodeExists(trim($postData['product_code']), $excludeId)) {
            $errors['product_code'] = 'این کد محصول قبلاً استفاده شده است.';
        }
        if (!in_array($postData['unit_of_measure'], ['gram', 'count'])) {
            $errors['unit_of_measure'] = 'واحد سنجش نامعتبر است.';
        }
        if (!in_array($postData['vat_base_type'], ['NONE', 'WAGE_PROFIT', 'PROFIT_ONLY'])) {
             $errors['vat_base_type'] = 'پایه محاسبه ارزش افزوده نامعتبر است.';
        }
        if (!in_array($postData['general_tax_base_type'], ['NONE', 'WAGE_PROFIT', 'PROFIT_ONLY'])) {
             $errors['general_tax_base_type'] = 'پایه محاسبه مالیات عمومی نامعتبر است.';
        }
        // Add more validation for numeric fields if needed
        return $errors;
    }

    public function delete(int $id): void {
        $this->requireLogin();
        try {
            // **Add Dependency Check**: Check if the product is used in any transaction items.
            // This requires a new method in a relevant repository, e.g., TransactionItemRepository.
            // For now, we rely on the database's foreign key constraint to prevent deletion.
            
            if ($this->productRepository->delete($id)) {
                $this->setSessionMessage('محصول با موفقیت حذف شد.', 'success', 'product_success');
            } else {
                $this->setSessionMessage('خطا در حذف محصول یا محصول یافت نشد.', 'danger', 'product_list_error');
            }
        } catch (Throwable $e) {
             $this->logger->error("Exception during product deletion.", ['id' => $id, 'exception' => $e]);
             // Check for foreign key constraint violation error
             if (str_contains($e->getMessage(), 'constraint violation')) {
                 $this->setSessionMessage('امکان حذف این محصول وجود ندارد زیرا در معاملات ثبت شده استفاده شده است.', 'warning', 'product_list_error');
             } else {
                 $this->setSessionMessage('خطای سیستمی هنگام حذف محصول.', 'danger', 'product_list_error');
             }
        }
        $this->redirect('/app/products');
    }
}