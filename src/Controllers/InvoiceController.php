<?php
namespace App\Controllers;

use PDO;
use Monolog\Logger;
use Throwable;
use Morilog\Jalali\Jalalian;
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;
use App\Repositories\TransactionRepository;
use App\Repositories\ContactRepository;
use App\Repositories\SettingsRepository;
use App\Utils\Helper;
use App\Core\CSRFProtector;

class InvoiceController extends AbstractController {

    private TransactionRepository $transactionRepository;
    private ContactRepository $contactRepository;
    private SettingsRepository $settingsRepository;

    public function __construct(PDO $db, Logger $logger, array $config, ViewRenderer $viewRenderer, array $services) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services);
        $this->transactionRepository = $services['transactionRepository'];
        $this->contactRepository = $services['contactRepository'];
        $this->settingsRepository = $services['settingsRepository'];
    }

    public function showGeneratorForm(): void {
        $this->requireLogin();
        $pageTitle = "صدور فاکتور معاملات";
        $loadingError = null;
        $transactions = [];
        $selectedContactId = null;
        $selectedTransactionType = null;
        $filters = ['start_date'=>'', 'end_date'=>''];

        try {
            $contacts = $this->contactRepository->getAll();
        } catch (Throwable $e) {
            $this->logger->error("Error fetching contacts for invoice generator.", ['exception' => $e]);
            $loadingError = "خطا در بارگذاری لیست طرف‌های حساب.";
            $contacts = [];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'load_transactions') {
            if (!CSRFProtector::validateToken($_POST['csrf_token'] ?? null)) {
                $this->setFlashMessage('درخواست نامعتبر است.', 'danger');
                $this->redirect('/app/invoice-generator');
                return;
            }
            $selectedContactId = filter_input(INPUT_POST, 'contact_id', FILTER_VALIDATE_INT);
            $selectedTransactionType = filter_input(INPUT_POST, 'transaction_type_filter', FILTER_DEFAULT);
            $filters['start_date'] = trim($_POST['start_date'] ?? '');
            $filters['end_date'] = trim($_POST['end_date'] ?? '');
            
            if (!$selectedContactId || !$selectedTransactionType) {
                $this->setFlashMessage("انتخاب طرف حساب و نوع فاکتور الزامی است.", 'danger');
            } else {
                try {
                    $repoFilters = [
                        'counterparty_contact_id' => $selectedContactId,
                        'transaction_type' => $selectedTransactionType,
                        'start_date_sql' => Helper::parseJalaliDateToSql($filters['start_date']),
                        'end_date_sql' => Helper::parseJalaliDateToSql($filters['end_date'], true),
                    ];
                    $transactionsResult = $this->transactionRepository->getUninvoicedTransactions($repoFilters);
                    if (empty($transactionsResult)) {
                        $this->setFlashMessage("هیچ معامله تکمیل شده‌ای برای این فیلترها یافت نشد.", 'info');
                    } else {
                        foreach($transactionsResult as &$tx) {
                            $tx['date_farsi'] = Helper::formatPersianDate($tx['transaction_date']);
                            $tx['value_formatted'] = Helper::formatRial($tx['total_value_rials']);
                            $tx['product_farsi'] = Helper::escapeHtml($tx['product_name'] ?? '-'); // (اصلاح شده)
                            $tx['display_amount'] = !empty($tx['quantity']) 
                                ? Helper::formatPersianNumber($tx['quantity']) . ' عدد' 
                                : Helper::formatPersianNumber($tx['weight_grams'], 3) . ' گرم';
                            $tx['notes_short'] = Helper::escapeHtml(mb_substr($tx['notes'] ?? '', 0, 40, 'UTF-8')) . (mb_strlen($tx['notes'] ?? '') > 40 ? '…' : '');
                        }
                        unset($tx);
                        $transactions = $transactionsResult;
                    }
                } catch (Throwable $e) {
                    $this->logger->error("Error fetching transactions for invoice.", ['exception' => $e]);
                    $this->setFlashMessage("خطا در بارگذاری معاملات.", 'danger');
                }
            }
        }

        $this->render('invoices/generator', [
            'page_title' => $pageTitle,
            'contacts' => $contacts,
            'transactions' => $transactions,
            'selected_contact_id' => $selectedContactId,
            'selected_transaction_type' => $selectedTransactionType,
            'filters' => $filters,
            'loading_error' => $loadingError,
            'baseUrl' => $this->config['app']['base_url'],
            'form_action_filter' => $this->config['app']['base_url'] . '/app/invoice-generator', // (اصلاح شده)
            'form_action_preview' => $this->config['app']['base_url'] . '/app/invoice-preview', // (اصلاح شده)
        ]);
    }

    /**
     * (بازنویسی کامل) پیش‌نمایش فاکتور را بر اساس آیتم‌های انتخاب شده تولید می‌کند.
     * این نسخه با فایل preview.php شما کاملاً هماهنگ است.
     */
    public function preview(): void {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             $this->redirect('/app/invoice-generator');
             return;
        }
        if (!CSRFProtector::validateToken($_POST['csrf_token'] ?? null)) {
            $this->setSessionMessage('خطای امنیتی: توکن نامعتبر است.', 'danger');
            $this->redirect('/app/invoice-generator');
            return;
        }

         $invoiceData = ['error_msg' => null];
        try {
            $transactionIds = array_map('intval', $_POST['transaction_ids'] ?? []);
            $contactId = (int)($_POST['contact_id_for_invoice'] ?? 0);
            $invoiceType = $_POST['invoice_type'] ?? '';

            if (empty($transactionIds) || empty($contactId) || empty($invoiceType)) {
                throw new \Exception("اطلاعات ارسالی برای صدور فاکتور ناقص است.");
            }

            $systemSettings = $this->settingsRepository->getAllSettingsAsAssoc();
            $contactData = $this->contactRepository->getById($contactId);
            $itemsRaw = $this->transactionRepository->getTransactionsByIds($transactionIds, $contactId, $invoiceType);

            if (!$contactData || empty($itemsRaw)) {
                throw new \Exception("مخاطب یا معاملات انتخاب شده یافت نشدند.");
            }
            
            $ourInfo = [
                'name' => $systemSettings['customer_name'] ?? ($systemSettings['app_name'] ?? 'نام کسب‌وکار شما'),
                'details' => $systemSettings['seller_address'] ?? 'آدرس شما',
            ];
            
            if ($invoiceType === 'sell') {
                $invoiceData['seller_info'] = $ourInfo;
                $invoiceData['buyer_info'] = $contactData;
            } else {
                $invoiceData['seller_info'] = $contactData;
                $invoiceData['buyer_info'] = $ourInfo;
            }

            $invoiceData['type_label'] = ($invoiceType === 'buy') ? 'فاکتور خرید' : 'فاکتور فروش';
            $invoiceData['contact'] = $contactData;

             // (محاسبات نهایی و دقیق)
            $subTotal = 0; // این متغیر جمع "مبلغ نهایی ردیف" خواهد بود (پایه + سود/اجرت)
            $totalGeneralTax = 0;
            $totalVat = 0;
            $processedItems = [];
            foreach ($itemsRaw as $key => $item) {
                $itemBaseValue = (float)($item['total_value_rials'] ?? 0);
                $itemProfitWage = (float)($item['profit_amount_rials'] ?? 0) + (float)($item['fee_amount_rials'] ?? 0) + (float)($item['ajrat_rials'] ?? 0);
                
                $subTotal += ($itemBaseValue + $itemProfitWage);
                $totalGeneralTax += (float)($item['general_tax_rials'] ?? 0);
                $totalVat += (float)($item['vat_rials'] ?? 0);
                
                $processedItems[] = $this->formatInvoiceItemForPreview($item, $key + 1);
            }
            $invoiceData['items'] = $processedItems;
            
            $grandTotal = $subTotal + $totalGeneralTax + $totalVat;

            $invoiceData['summary'] = [
                'sub_total' => $subTotal,
                'total_general_tax' => $totalGeneralTax,
                'total_vat' => $totalVat,
                'grand_total' => $grandTotal,
                'grand_total_words' => Helper::convertNumberToWords((int)$grandTotal) . ' ریال',
            ];

        } catch (Throwable $e) {
            $this->logger->error("Error generating invoice preview.", ['exception' => $e]);
            $invoiceData['error_msg'] = "خطا: " . $e->getMessage();
        }
        
         // (اصلاح شده) ارسال تمام متغیرهای مورد نیاز به view
        $this->render('invoices/preview', [
            'invoice' => $invoiceData,
            'baseUrl' => $this->config['app']['base_url'],
            'appName' => $this->config['app']['name'] ?? 'حسابداری رایان طلا',
            'current_date_farsi' => Jalalian::now()->format('Y/m/d'),
            // این اطلاعات باید از تنظیمات یا یک فایل config خوانده شود
            'seller_info' => [
                'name' => $this->config['app']['seller_name'] ?? 'نام فروشنده شما',
                'address' => $this->config['app']['seller_address'] ?? 'آدرس شما',
                'phone' => $this->config['app']['seller_phone'] ?? 'تلفن شما',
            ],
        ], false);
    }
    /**
     * (بازنویسی کامل) یک آیتم را برای نمایش در جدول پیش‌نمایش فاکتور شما فرمت‌بندی می‌کند.
     * این تابع دقیقاً همان ساختار داده‌ای را تولید می‌کند که فایل preview.php شما انتظار دارد.
     */
    private function formatInvoiceItemForPreview(array $item, int $rowNum): array
    {
        $formatted = [];
        $baseCategory = strtolower($item['base_category']);

        // 1. شرح کامل و چند خطی
        $description = "<strong>" . Helper::escapeHtml($item['product_name']) . "</strong>";
        $details = [];
        switch ($baseCategory) {
            case 'melted':
                if (!empty($item['tag_number'])) $details[] = "انگ: " . Helper::escapeHtml($item['tag_number']);
                if (!empty($item['assay_office_name'])) $details[] = "ری‌گیری: " . Helper::escapeHtml($item['assay_office_name']);
                break;
            case 'manufactured':
                $details[] = "وزن: " . Helper::formatPersianNumber($item['weight_grams'], 3) . " گرم";
                if ($item['has_attachments'] == 1) $details[] = "متعلقات: " . Helper::escapeHtml($item['attachment_type'] ?? 'دارد');
                break;
            case 'coin':
                if ($item['is_bank_coin']) $details[] = "نوع: بانکی";
                if (!empty($item['seal_name'])) $details[] = "وکیوم: " . Helper::escapeHtml($item['seal_name']);
                break;
            case 'bullion':
                if (!empty($item['tag_number'])) $details[] = "شماره: " . Helper::escapeHtml($item['tag_number']);
                if (!empty($item['workshop_name'])) $details[] = "سازنده: " . Helper::escapeHtml($item['workshop_name']);
                break;
            case 'jewelry':
                $details[] = "وزن: " . Helper::formatPersianNumber($item['weight_grams'], 3) . " گرم";
                if (!empty($item['jewelry_type'])) $details[] = "نوع: " . Helper::escapeHtml($item['jewelry_type']);
                if (!empty($item['jewelry_color'])) $details[] = "رنگ: " . Helper::escapeHtml($item['jewelry_color']);
                if (!empty($item['jewelry_quality'])) $details[] = "کیفیت: " . Helper::escapeHtml($item['jewelry_quality']);
                break;
        }
        if (!empty($details)) $description .= "<br><small class='text-muted'>" . implode(' | ', $details) . "</small>";
        $formatted['product_type_farsi'] = $description;


        // 2. مقدار و عیار/سال
        $isCountable = in_array($baseCategory, ['coin', 'manufactured', 'jewelry']);
        $formatted['quantity_formatted'] = $isCountable ? Helper::formatPersianNumber($item['quantity']) : Helper::formatPersianNumber($item['weight_grams'], 3);
        $formatted['carat_formatted'] = ($baseCategory === 'coin') ? Helper::formatPersianNumber($item['coin_year']) : (!empty($item['carat']) ? Helper::formatPersianNumber($item['carat']) : '-');

        // 3. ارزش گذاری (نرخ و قیمت ۱ گرم)
        $isPricedByWeight = in_array($baseCategory, ['melted', 'bullion', 'manufactured']);
        if ($isPricedByWeight) {
            $formatted['rate_display'] = !empty($item['mazaneh_price']) ? Helper::formatRial($item['mazaneh_price']) : Helper::formatRial($item['unit_price_rials']);
            $formatted['rate_note'] = !empty($item['mazaneh_price']) ? '(مظنه)' : '(هر گرم)';
            $formatted['price_per_ref_gram_formatted'] = !empty($item['mazaneh_price']) ? Helper::formatRial((float)$item['mazaneh_price'] / 4.3318) : Helper::formatRial($item['unit_price_rials']);
        } else { // برای سکه و جواهر
            $formatted['rate_display'] = Helper::formatRial($item['unit_price_rials']);
            $formatted['rate_note'] = '(هر عدد)';
            $formatted['price_per_ref_gram_formatted'] = '-';
        }
        // (اضافه شده) ستون جدید "قیمت خالص"
        $formatted['base_value_formatted'] = Helper::formatRial($item['total_value_rials'] ?? 0);
        // 4. سود/اجرت
         // محاسبه سود/اجرت
        $itemProfitWageCommission = (float)($item['profit_amount_rials'] ?? 0) + (float)($item['fee_amount_rials'] ?? 0) + (float)($item['ajrat_rials'] ?? 0);
        $formatted['profit_wage_commission_formatted'] = Helper::formatRial($itemProfitWageCommission);

        // 5. وزن ۷۵۰
        $formatted['calculated_weight_grams'] = ($isPricedByWeight && !empty($item['carat'])) ? Helper::convertGoldToCarat((float)$item['weight_grams'], (int)$item['carat']) : null;
        
        // 6. انتقال مقادیر دیگر
        $formatted['row_num'] = Helper::formatPersianNumber($rowNum);
        $formatted['delivery_status'] = $item['delivery_status'];
        $formatted['total_value_formatted'] = Helper::formatRial($item['total_value_rials']);
        $formatted['quantity'] = $item['quantity']; // برای شرط در view
        $formatted['coin_year'] = $item['coin_year'];

        // محاسبه مبلغ نهایی ردیف
        $itemFinalValue = (float)($item['total_value_rials'] ?? 0) + $itemProfitWageCommission;
        $formatted['final_value_formatted'] = Helper::formatRial($itemFinalValue);

        $formatted['profit_wage_commission_formatted'] = Helper::formatRial($itemProfitWageCommission);
        $formatted['final_value_formatted'] = Helper::formatRial($itemFinalValue);

        return $formatted;
    }
}