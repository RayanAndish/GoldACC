<?php

namespace App\Controllers;

use Throwable;
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;
use App\Utils\Helper;
use App\Models\ProductCategory;
use App\Repositories\ProductCategoryRepository;
use PDO;
use Monolog\Logger;

class ProductCategoryController extends AbstractController {

    private ProductCategoryRepository $categoryRepository;

    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        // اطمینان از اینکه categoryRepository از services گرفته می‌شود اگر آنجا تعریف شده
        if (isset($this->services['productCategoryRepository']) && $this->services['productCategoryRepository'] instanceof ProductCategoryRepository) {
            $this->categoryRepository = $this->services['productCategoryRepository'];
        } else {
            // Fallback or throw exception if not found in services and is critical
            $this->categoryRepository = new ProductCategoryRepository($this->db, $this->logger);
            $this->logger->info("ProductCategoryRepository instantiated directly in ProductCategoryController as it was not found in services.");
        }
    }

    private function getCommonViewData(string $pageTitle = ''): array {
        // این متد می‌تواند در AbstractController هم باشد
        $commonData = [
            'appName'        => $this->config['app']['name'] ?? 'پیش‌فرض',
            'baseUrl'        => $this->config['app']['base_url'] ?? '',
            'currentUri'     => $_SERVER['REQUEST_URI'] ?? '/',
            'isLoggedIn'     => $this->authService->isLoggedIn(),
            'loggedInUser'   => $this->authService->getCurrentUser(),
            'flashMessage'   => $this->getFlashMessage('category_message'),
            'pageTitle'      => $pageTitle,
        ];
        return $commonData;
    }

    public function index(): void {
        $this->requireLogin();
        $pageTitle = 'دسته‌بندی محصولات';
        $viewData = $this->getCommonViewData($pageTitle);

        try {
            $viewData['categories'] = $this->categoryRepository->findAll();
            $viewData['success_msg'] = $this->getFlashMessage('category_success')['text'] ?? null;
        } catch (Throwable $e) {
            $this->logger->error("Error fetching product categories list.", ['exception' => $e]);
            $viewData['error_msg'] = "خطا در بارگذاری لیست دسته‌بندی‌ها.";
            $viewData['categories'] = [];
        }
        $viewData['flashMessage'] = $this->getFlashMessage('category_list_error');

        $this->viewRenderer->render('product_categories/list', $viewData);
    }

    public function showAddForm(): void {
        $this->requireLogin();
        $pageTitle = 'افزودن دسته‌بندی محصول';
        $viewData = $this->getCommonViewData($pageTitle);

        $viewData['category'] = new ProductCategory();
        $viewData['action'] = $this->config['app']['base_url'] . '/app/product-categories/save';
        $viewData['formTitle'] = 'افزودن دسته‌بندی جدید';
        $viewData['submitButtonText'] = 'ذخیره';
        
        // خواندن خطاها و داده‌های فرم از session flash
        $validationErrorsFlash = $this->getFlashMessage('validation_errors_cat');
        $viewData['errors'] = ($validationErrorsFlash && isset($validationErrorsFlash['text'])) ? unserialize($validationErrorsFlash['text']) : [];
        if (!is_array($viewData['errors'])) $viewData['errors'] = [];

        $formDataFlash = $this->getFlashMessage('form_data_cat');
        $formData = ($formDataFlash && isset($formDataFlash['text'])) ? unserialize($formDataFlash['text']) : [];

        // اگر داده‌های فرم وجود داشت، آنها را به مدل منتقل کن
        if (is_array($formData) && !empty($formData)) {
            $viewData['category']->name = $formData['name'] ?? '';
            $viewData['category']->code = $formData['code'] ?? '';
            $viewData['category']->description = $formData['description'] ?? '';
            $viewData['category']->unit_of_measure = $formData['unit_of_measure'] ?? '';
            $viewData['category']->base_category = $formData['base_category'] ?? '';
            $viewData['category']->is_active = isset($formData['is_active']);
        }

        $this->viewRenderer->render('product_categories/form', $viewData);
    }

    public function showEditForm(int $id): void {
        $this->requireLogin();
        $pageTitle = 'ویرایش دسته‌بندی محصول';
        $viewData = $this->getCommonViewData($pageTitle);

        $category = $this->categoryRepository->findById($id);
        if (!$category) {
            $this->logger->warning("Product category not found for editing.", ['id' => $id]);
            $this->setSessionMessage('دسته بندی یافت نشد', 'danger', 'category_list_error');
            $this->redirect('app/product-categories'); // حذف / از ابتدای مسیر
            return;
        }

        $viewData['category'] = $category;
        $viewData['action'] = $this->config['app']['base_url'] . '/app/product-categories/save';
        $viewData['formTitle'] = "ویرایش دسته‌بندی: " . Helper::escapeHtml($category->name);
        $viewData['submitButtonText'] = 'به‌روزرسانی';
        
        // خواندن خطاها و داده‌های فرم از session flash
        $validationErrorsFlash = $this->getFlashMessage('validation_errors_cat');
        $viewData['errors'] = ($validationErrorsFlash && isset($validationErrorsFlash['text'])) ? unserialize($validationErrorsFlash['text']) : [];
        if (!is_array($viewData['errors'])) $viewData['errors'] = [];

        $formDataFlash = $this->getFlashMessage('form_data_cat');
        $formData = ($formDataFlash && isset($formDataFlash['text'])) ? unserialize($formDataFlash['text']) : [];

        // اگر داده‌های فرم وجود داشت، آنها را به مدل منتقل کن
        if (is_array($formData) && !empty($formData)) {
            $viewData['category']->name = $formData['name'] ?? $category->name;
            $viewData['category']->code = $formData['code'] ?? $category->code;
            $viewData['category']->description = $formData['description'] ?? $category->description;
            $viewData['category']->unit_of_measure = $formData['unit_of_measure'] ?? $category->unit_of_measure;
            $viewData['category']->base_category = $formData['base_category'] ?? $category->base_category;
            $viewData['category']->is_active = isset($formData['is_active']);
        }

        $this->viewRenderer->render('product_categories/form', $viewData);
    }

    public function save(): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('app/product-categories'); // حذف / از ابتدای مسیر
            return;
        }

        $categoryId = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $isNew = $categoryId === null;
        $redirectUrlOnError = 'app/product-categories/' . ($isNew ? 'add' : 'edit/' . $categoryId); // حذف / از ابتدای مسیر

        $category = $isNew ? new ProductCategory() : $this->categoryRepository->findById($categoryId);
        if (!$isNew && !$category) {
            $this->logger->error("Product category not found for saving (update).", ['id' => $categoryId]);
            $this->setSessionMessage('دسته بندی مورد نظر برای بروزرسانی یافت نشد.', 'danger', 'form_error');
            $this->redirect('app/product-categories'); // حذف / از ابتدای مسیر
            return;
        }
        if ($isNew) $category = new ProductCategory();

        // Populate category object
        $category->id = $categoryId; // Set ID for edit case
        $category->name = trim($_POST['name'] ?? '');
        $category->code = trim($_POST['code'] ?? null);
        $category->description = trim($_POST['description'] ?? null);
        $category->unit_of_measure = trim($_POST['unit_of_measure'] ?? null);
        $category->base_category = trim($_POST['base_category'] ?? null);
        $category->is_active = isset($_POST['is_active']);


        // --- Validation ---
        $errors = [];
        if (empty($category->name)) {
            $errors['name'] = 'نام دسته‌بندی نمی‌تواند خالی باشد.';
        }
        if (!empty($category->code) && $this->categoryRepository->codeExists($category->code, $category->id)) {
             $errors['code'] = 'این کد دسته‌بندی قبلاً استفاده شده است.';
        }
        // Add more validation as needed

        if (!empty($errors)) {
            $this->logger->warning("Validation failed for category.", ['errors' => $errors, 'data' => $_POST]);
            $this->setSessionMessage(serialize($errors), 'danger', 'validation_errors_cat');
            $this->setSessionMessage(serialize($_POST), 'info', 'form_data_cat');
            $this->setSessionMessage(implode('<br>', array_values($errors)), 'danger', 'default');

            $this->redirect($redirectUrlOnError);
            return;
        }

        try {
            $savedId = $this->categoryRepository->save($category);
            $actionWord = $isNew ? 'ایجاد' : 'به‌روزرسانی';
            $this->setSessionMessage("دسته‌بندی با موفقیت {$actionWord} شد.", 'success', 'category_success');
            $this->logger->info("Product category {$actionWord} successfully.", ['id' => $savedId]);
            $this->redirect('app/product-categories'); // حذف / از ابتدای مسیر
        } catch (Throwable $e) {
            $this->logger->error("Failed to save product category.", ['data' => $_POST, 'isNew' => $isNew, 'exception' => $e]);
            $this->setSessionMessage('خطا در ذخیره دسته‌بندی: ' . $e->getMessage(), 'danger', 'form_error');
            $this->setSessionMessage(serialize($_POST), 'info', 'form_data_cat');
            $this->redirect($redirectUrlOnError);
        }
    }

    public function delete(int $id): void {
        $this->requireLogin();

        $productRepo = new \App\Repositories\ProductRepository($this->db, $this->logger);
        try {
            $productsInCategory = $productRepo->findAll(['category_id' => $id]);

            if (!empty($productsInCategory)) {
                $this->setSessionMessage('امکان حذف این دسته‌بندی وجود ندارد زیرا محصولاتی به آن اختصاص داده شده‌اند.', 'warning', 'category_list_error');
                $this->logger->warning("Attempted to delete category with associated products.", ['id' => $id, 'count' => count($productsInCategory)]);
            } else {
                if ($this->categoryRepository->delete($id)) {
                    $this->setSessionMessage('دسته‌بندی با موفقیت حذف شد.', 'success', 'category_success');
                    $this->logger->info("Product category deleted successfully.", ['id' => $id]);
                } else {
                    $this->setSessionMessage('خطا در حذف دسته‌بندی. ممکن است دسته بندی یافت نشود.', 'danger', 'category_list_error');
                    $this->logger->error("Failed to delete product category (not found or db error).", ['id' => $id]);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error("Exception during category deletion.", ['id' => $id, 'exception' => $e]);
            $this->setSessionMessage('خطای سیستمی هنگام حذف دسته‌بندی: ' . $e->getMessage(), 'danger', 'category_list_error');
        }
        $this->redirect('app/product-categories'); // حذف / از ابتدای مسیر
    }
}