<?php
namespace App\Controllers;

use PDO;
use Monolog\Logger;
use Throwable;
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;
use App\Repositories\ContactRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PhysicalSettlementRepository;
use App\Repositories\ContactWeightLedgerRepository;
use App\Utils\Helper;
use App\Core\CSRFProtector;

class SettlementController extends AbstractController {

    private ContactRepository $contactRepository;
    private ProductRepository $productRepository;
    private PhysicalSettlementRepository $settlementRepository;
    private ContactWeightLedgerRepository $contactWeightLedgerRepository;

    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        $this->contactRepository = $services['contactRepository'];
        $this->productRepository = $services['productRepository'];
        $this->settlementRepository = $services['settlementRepository'];
        $this->contactWeightLedgerRepository = $services['contactWeightLedgerRepository'];
    }

    public function showForm(): void {
        $this->requireLogin();
        $contacts = [];
        $products = [];
        $errorMessage = $this->getFlashMessage('settlement_error');
        $successMessage = $this->getFlashMessage('settlement_success');

        try {
            $contacts = $this->contactRepository->getAll();
            $products = $this->productRepository->findAll(['unit_of_measure' => 'gram']);
        } catch (Throwable $e) {
            $this->logger->error("Failed to fetch prerequisites for settlement form.", ['exception' => $e]);
            $this->setFlashMessage("خطا در بارگذاری پیش‌نیازهای فرم.", "danger");
            $errorMessage = ['text' => "خطا در بارگذاری پیش‌نیازهای فرم."];
        }

        $this->render('transactions/settlements', [
            'page_title' => 'ثبت تسویه حساب فیزیکی',
            'contacts' => $contacts,
            'products' => $products,
            'baseUrl' => $this->config['app']['base_url'],
            'error_message' => $errorMessage['text'] ?? null,
            'success_message' => $successMessage['text'] ?? null,
        ]);
    }

    public function save(): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/settlements/add');
            return;
        }
        if (!CSRFProtector::validateToken($_POST['csrf_token'] ?? null)) {
            $this->setFlashMessage('خطای امنیتی.', 'danger');
            $this->redirect('/app/settlements/add');
            return;
        }

        $this->db->beginTransaction();
        try {
            $contactId = filter_input(INPUT_POST, 'contact_id', FILTER_VALIDATE_INT);
            $direction = $_POST['direction'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $items = $_POST['items'] ?? [];

            if (!$contactId || !in_array($direction, ['inflow', 'outflow']) || empty($items)) {
                throw new \Exception("اطلاعات ارسالی ناقص است. لطفاً تمام فیلدهای ستاره‌دار را پر کنید.");
            }

            $settlementId = $this->settlementRepository->createSettlement([
                'contact_id' => $contactId,
                'direction' => $direction,
                'notes' => $notes,
            ]);

            $totalWeight750 = 0;
            $primaryCategoryId = null;

            foreach ($items as $item) {
                if (empty($item['weight']) || empty($item['carat']) || empty($item['product_id']) || 
                    !is_numeric($item['weight']) || !is_numeric($item['carat'])) {
                    continue;
                }

                $weight750_item = Helper::convertGoldToCarat((float)$item['weight'], (int)$item['carat']);
                $totalWeight750 += $weight750_item;

                $product = $this->productRepository->findByIdWithCategory((int)$item['product_id']);
                if (!$product) {
                    continue;
                }

                if ($primaryCategoryId === null) {
                    $primaryCategoryId = $product->category->id;
                }

                $itemData = [
                    'settlement_id' => $settlementId,
                    'product_id'    => (int)$item['product_id'],
                    'weight'        => (float)$item['weight'],
                    'carat'         => (int)$item['carat'],
                    'weight_750'    => $weight750_item,
                ];
                $this->settlementRepository->createSettlementItem($itemData);
            }

             if ($primaryCategoryId) {
                $lastBalance = $this->contactWeightLedgerRepository->getLastBalance($contactId, $primaryCategoryId);

                // ===================== اصلاح کلیدی منطق =====================
                // inflow: ما طلا دریافت می‌کنیم -> مشتری از ما طلبکار (بستانکار) می‌شود -> مانده باید مثبت شود
                // outflow: ما طلا تحویل می‌دهیم -> مشتری به ما بدهکار می‌شود -> مانده باید منفی شود
                $change = ($direction === 'inflow') ? $totalWeight750 : -$totalWeight750;
                // ==========================================================

                $newBalance = $lastBalance + $change;

                $this->contactWeightLedgerRepository->recordEntry([
                    'contact_id' => $contactId,
                    'product_category_id' => $primaryCategoryId,
                    'event_type' => 'SETTLEMENT',
                    'change_weight_grams' => $change,
                    'balance_after_grams' => $newBalance,
                    'related_settlement_id' => $settlementId,
                    'notes' => "تسویه فیزیکی شماره {$settlementId}",
                ]);
            }

            $this.db->commit();
            $this.setFlashMessage("تسویه حساب فیزیکی با موفقیت ثبت شد.", "success");
            $this.redirect('/app/contacts/ledger/' . $contactId); // Redirect to the unified ledger
        } catch (\Exception $e) {
            $this.db->rollBack();
            $this.logger->error("Failed to save settlement.", ['exception' => $e]);
            $this.setFlashMessage("خطا در ذخیره‌سازی تسویه: " . $e->getMessage(), "danger");
            $this.redirect('/app/settlements/add');
        }
    }
}
