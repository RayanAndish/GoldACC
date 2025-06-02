<?php

namespace App\Controllers; // Namespace مطابق با پوشه src/Controllers

use PDO;
use Monolog\Logger;
use Throwable; // For catching exceptions
use Exception; // For throwing general exceptions
use Morilog\Jalali\Jalalian; // Add Jalalian namespace

// Core & Base
use App\Core\ViewRenderer;
use App\Controllers\AbstractController;

// Dependencies
use App\Repositories\TransactionRepository;
use App\Repositories\ContactRepository; // Needed for contact dropdown and preview info
use App\Repositories\SettingsRepository; // Optional: For default tax rate
use App\Utils\Helper; // Utility functions
use App\core\CSRFProtector; // Added for CSRF token validation

/**
 * InvoiceController handles HTTP requests related to generating invoices.
 * Displays a form to select transactions and shows an invoice preview.
 * Inherits from AbstractController.
 */
class InvoiceController extends AbstractController {

    private TransactionRepository $transactionRepository;
    private ContactRepository $contactRepository;
    // Optional: Inject SettingsRepository if default tax comes from DB settings
    // private SettingsRepository $settingsRepository;

    /**
     * Constructor. Injects dependencies.
     *
     * @param PDO $db
     * @param Logger $logger
     * @param array $config
     * @param ViewRenderer $viewRenderer
     * @param array $services Array of application services.
     * @throws \Exception If required repositories are missing.
     */
    public function __construct(
        PDO $db,
        Logger $logger,
        array $config,
        ViewRenderer $viewRenderer,
        array $services // Receive the $services array
    ) {
        parent::__construct($db, $logger, $config, $viewRenderer, $services); // Pass all to parent

        // Retrieve specific repositories
        if (!isset($this->services['transactionRepository']) || !$this->services['transactionRepository'] instanceof TransactionRepository) {
            throw new \Exception('TransactionRepository not found for InvoiceController.');
        }
        $this->transactionRepository = $this->services['transactionRepository'];

        if (!isset($this->services['contactRepository']) || !$this->services['contactRepository'] instanceof ContactRepository) {
            throw new \Exception('ContactRepository not found for InvoiceController.');
        }
        $this->contactRepository = $this->services['contactRepository'];

        // Optional: Get settings repository
        // if (!isset($this->services['settingsRepository']) || !$this->services['settingsRepository'] instanceof SettingsRepository) {
        //     // Handle missing optional dependency if needed
        // }
        // $this->settingsRepository = $this->services['settingsRepository'] ?? null;


        $this->logger->debug("InvoiceController initialized.");
    }

    /**
     * Displays the invoice generator form.
     * Handles GET requests to show the form and POST requests (via filter submit) to load transactions.
     * Route: /app/invoice-generator (GET, POST)
     */
    public function showGeneratorForm(): void {
        $this->requireLogin();
        // Optional: Permission check

        $pageTitle = "صدور فاکتور معاملات";
        $contacts = [];
        $transactions = [];
        $loadingError = null; // Error loading initial contacts
        $filterError = $this->getFlashMessage('filter_error');
        if (isset($_SESSION['flash_messages']['filter_error'])) {
            unset($_SESSION['flash_messages']['filter_error']);
        }

        // --- Filter Values (from POST or default) ---
        // Read filter values from POST request if submitted
        $isFilterPost = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'load_transactions');
        $selectedContactId = filter_input(INPUT_POST, 'contact_id', FILTER_VALIDATE_INT);
        $selectedType = filter_input(INPUT_POST, 'transaction_type_filter', FILTER_DEFAULT); // 'buy' or 'sell'
        $startDateJalali = trim(filter_input(INPUT_POST, 'start_date', FILTER_DEFAULT) ?? '');
        $endDateJalali = trim(filter_input(INPUT_POST, 'end_date', FILTER_DEFAULT) ?? '');

        // Parse dates
        $startDateSql = Helper::parseJalaliDateToSql($startDateJalali);
        $endDateSql = Helper::parseJalaliDateToSql($endDateJalali);
        if ($endDateSql) { $endDateSql .= ' 23:59:59'; }

        // --- Fetch Contacts (Always needed for dropdown) ---
        try {
            $contacts = $this->contactRepository->getAll(); // Assume repo method exists
        } catch (Throwable $e) {
            $this->logger->error("Error fetching contacts for invoice generator.", ['exception' => $e]);
            $loadingError = "خطا در بارگذاری لیست طرف‌های حساب.";
            $contacts = [];
        }

        // --- Fetch Transactions (Only if filters are submitted via POST) ---
        if ($isFilterPost) {
            $this->logger->debug("Loading transactions based on filter.", ['contact_id' => $selectedContactId, 'type' => $selectedType, 'start' => $startDateSql, 'end' => $endDateSql]);
            // Validate filters
            if (!$selectedContactId) {
                 $filterError = ['text' => "لطفا طرف حساب را انتخاب کنید."];
            } elseif (!in_array($selectedType, ['buy', 'sell'])) {
                 $filterError = ['text' => "لطفا نوع فاکتور (خرید یا فروش) را انتخاب کنید."];
            }

            if (!$filterError) { // Proceed if filters are valid
                try {
                    $filters = [
                        'counterparty_contact_id' => $selectedContactId,
                        'transaction_type' => $selectedType,
                        'start_date_sql' => $startDateSql,
                        'end_date_sql' => $endDateSql,
                        // Add filter for uninvoiced transactions if status exists
                        // 'invoice_status' => 'pending',
                    ];
                    // Assume repo method exists
                    $transactionsResult = $this->transactionRepository->getFilteredTransactionsForInvoice($filters);

                    if (empty($transactionsResult)) {
                        $this->setSessionMessage("هیچ معامله قابل فاکتوری برای این فیلترها یافت نشد.", 'info', 'filter_info'); // Use specific key
                    } else {
                        // Format for display in selection list
                        foreach($transactionsResult as &$tx) { $this->formatTransactionForGeneratorList($tx); } unset($tx);
                        $transactions = $transactionsResult;
                    }
                } catch (Throwable $e) {
                    $this->logger->error("Error fetching transactions for invoice generator.", ['filters' => $filters, 'exception' => $e]);
                    $filterError = ['text' => "خطا در بارگذاری معاملات: " . Helper::escapeHtml($e->getMessage())];
                }
            }
             // If filter error occurred, set flash message for display
             if ($filterError) {
                 $this->setSessionMessage($filterError['text'], 'danger', 'filter_error');
             }
        } // End POST filter processing

        // --- Render View ---
        $this->render('invoices/generator', [
            'page_title' => $pageTitle,
            'form_action_filter' => $this->config['app']['base_url'] . '/app/invoice-generator',
            'form_action_preview' => $this->config['app']['base_url'] . '/app/invoice-preview',
            'contacts' => $contacts,
            'transactions' => $transactions,
            'selected_contact_id' => $selectedContactId,
            'selected_transaction_type' => $selectedType, // مقدار انتخاب شده را به فرم برگردان
            'filters' => ['start_date' => $startDateJalali, 'end_date' => $endDateJalali],
            'flashMessage' => $filterError ? ['text' => $filterError['text'], 'type' => 'danger'] : null, // فقط یک پیام خطا
            'loading_error' => $loadingError,
            'info_msg' => ($this->getFlashMessage('filter_info'))['text'] ?? null,
            'valid_transaction_types' => ['buy', 'sell'],
        ]);
    }

    /** Helper to format transaction for generator selection list */
    private function formatTransactionForGeneratorList(array &$tx): void {
        $tx['type_farsi'] = ($tx['transaction_type'] === 'buy') ? 'خرید' : 'فروش';
        $tx['product_farsi'] = Helper::translateProductType($tx['gold_product_type'] ?? '');
        $tx['date_farsi'] = $tx['transaction_date'] ? Jalalian::fromFormat('Y-m-d H:i:s', $tx['transaction_date'])->format('Y/m/d H:i') : '-';
        $tx['display_amount'] = '';
        if (!empty($tx['quantity']) && $tx['quantity'] > 0) $tx['display_amount'] = Helper::formatNumber($tx['quantity'], 0) . " عدد";
        elseif (!empty($tx['gold_weight_grams']) && $tx['gold_weight_grams'] > 0) $tx['display_amount'] = Helper::formatNumber($tx['gold_weight_grams'], 3) . " گرم";
        $tx['value_formatted'] = Helper::formatRial($tx['total_value_rials'] ?? 0);
        $tx['notes_short'] = Helper::escapeHtml(mb_substr($tx['notes'] ?? '', 0, 40, 'UTF-8')) . (mb_strlen($tx['notes'] ?? '') > 40 ? '…' : '');
    }


    /**
     * Generates and displays the invoice preview page based on selected transactions.
     * Route: /app/invoice-preview (POST)
     */
    public function preview(): void {
        $this->requireLogin();
        // Optional: Permission check

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             $this->redirect('/app/invoice-generator'); // Redirect back if accessed directly
        }

        // CSRF token validation
        if (!CSRFProtector::validateToken($_POST['csrf_token'] ?? null)) {
            $this->setSessionMessage('خطای امنیتی: توکن نامعتبر است.', 'danger', 'invoice_error');
            $this->redirect('/app/invoice-generator');
        }

        $pageTitle = "پیش‌نمایش فاکتور";
        $invoiceData = [ // Initialize data structure
            'contact' => null,
            'items' => [],
            'summary' => ['sub_total' => 0.0, 'tax_rate_percent' => 0.0, 'tax_amount' => 0.0, 'grand_total' => 0.0, 'grand_total_words' => '', 'item_count' => 0],
            'type_label' => 'فاکتور',
            'invoice_number' => 'پیش‌نمایش-' . time(), // Generate temporary number
            'issue_date_farsi' => Jalalian::fromFormat('Y-m-d', date('Y-m-d'))->format('Y/m/d'), // Today's date
            'error_msg' => null
        ];

        // --- Input Extraction and Validation ---
        $transactionIdsRaw = $_POST['transaction_ids'] ?? [];
        $contactId = filter_input(INPUT_POST, 'contact_id_for_invoice', FILTER_VALIDATE_INT);
        $invoiceType = $_POST['invoice_type'] ?? null; // 'buy' or 'sell' from hidden field
        $applyTax = isset($_POST['apply_tax']) && $_POST['apply_tax'] == '1';
        $taxRatePercentStr = $_POST['tax_rate_percent'] ?? '0'; // Assume default is 0 if not provided

        $transactionIds = [];
        if (is_array($transactionIdsRaw)) {
            $transactionIds = array_filter($transactionIdsRaw, fn($id) => is_numeric($id) && $id > 0);
            $transactionIds = array_map('intval', $transactionIds);
        }

        $taxRatePercent = 0.0;
        if ($applyTax) {
            $cleanedTax = Helper::sanitizeFormattedNumber($taxRatePercentStr);
            if ($cleanedTax !== '' && $cleanedTax !== null && is_numeric($cleanedTax) && ($rate = floatval($cleanedTax)) >= 0) {
                 $taxRatePercent = $rate;
            } else {
                 $invoiceData['error_msg'] = ($invoiceData['error_msg'] ? $invoiceData['error_msg'].'<br>':'') . 'درصد مالیات نامعتبر بود، ۰٪ در نظر گرفته شد.';
            }
        }

        if (empty($transactionIds)) { $invoiceData['error_msg'] = ($invoiceData['error_msg'] ? $invoiceData['error_msg'].'<br>':'') . "هیچ معامله معتبری انتخاب نشده."; }
        elseif (!$contactId) { $invoiceData['error_msg'] = ($invoiceData['error_msg'] ? $invoiceData['error_msg'].'<br>':'') . "مخاطب نامعتبر است."; }
        elseif (!in_array($invoiceType, ['buy', 'sell'])) { $invoiceData['error_msg'] = ($invoiceData['error_msg'] ? $invoiceData['error_msg'].'<br>':'') . "نوع فاکتور نامعتبر است."; }

        // --- Fetch Data and Calculate if Input is Valid ---
        if ($invoiceData['error_msg'] === null) {
            try {
                // Fetch Contact Info
                $contactData = $this->contactRepository->getById($contactId);
                if(!$contactData) throw new Exception("مخاطب (#{$contactId}) یافت نشد.");
                $invoiceData['contact'] = [ // Select and escape needed fields
                    'id' => $contactData['id'],
                    'name' => Helper::escapeHtml($contactData['name']),
                    'details' => Helper::escapeHtml($contactData['details'] ?? '')
                ];
                $invoiceData['type_label'] = ($invoiceType === 'buy') ? 'فاکتور خرید' : 'فاکتور فروش';

                // Fetch Invoice Items (Transactions) - Ensure they match contact and type
                // Assume repo method validates this or returns only matching items
                $itemsRaw = $this->transactionRepository->getTransactionsByIds($transactionIds, $contactId, $invoiceType);
                if (count($itemsRaw) !== count($transactionIds)) {
                     $this->logger->warning("Mismatch between requested transaction IDs and fetched items for invoice.", ['requested' => $transactionIds, 'fetched_count' => count($itemsRaw)]);
                     // Potentially throw error or just use fetched items
                }
                if (empty($itemsRaw)) throw new Exception("هیچ آیتم معتبری یافت نشد.");

                // --- Calculate Summary and Format Items ---
                $subTotal = 0; $rowNum = 1; $processedItems = [];
                foreach ($itemsRaw as $item) {
                    $itemValue = (float)($item['total_value_rials'] ?? 0);
                    $subTotal += $itemValue;
                    $formattedItem = $this->formatInvoiceItem($item, $rowNum++);
                    $processedItems[] = $formattedItem;
                }
                $invoiceData['items'] = $processedItems;

                $subTotal = round($subTotal);
                $taxAmount = round($subTotal * ($taxRatePercent / 100));
                $grandTotal = $subTotal + $taxAmount;
                $grandTotalWords = Helper::convertNumberToWords((int)$grandTotal) . " ریال";

                $invoiceData['summary'] = [
                    'sub_total' => $subTotal, 'tax_rate_percent' => $taxRatePercent,
                    'tax_amount' => $taxAmount, 'grand_total' => $grandTotal,
                    'grand_total_words' => $grandTotalWords, 'item_count' => count($processedItems)
                ];

                 $this->logger->debug("Invoice preview data prepared.", ['contact_id' => $contactId, 'item_count' => count($processedItems)]);

            } catch (Throwable $e) {
                 $this->logger->error("Error preparing invoice preview.", ['exception' => $e, 'contact_id' => $contactId, 'tx_ids' => $transactionIds]);
                 $invoiceData['error_msg'] = ($invoiceData['error_msg'] ? $invoiceData['error_msg'].'<br>':'') . 'خطا در آماده‌سازی اطلاعات فاکتور: ' . Helper::escapeHtml($e->getMessage());
                 // Reset data on error
                 $invoiceData['contact'] = null; $invoiceData['items'] = []; $invoiceData['summary'] = ['sub_total' => 0.0, /*...*/];
            }
        }

        // --- Render Preview View ---
        // Render without the main application layout (header/footer)
        $this->render('invoices/preview', [
            'page_title' => $pageTitle,
            'invoice'    => $invoiceData, // Pass the consolidated data structure
        ], false); // false = without layout
    }

     /** Helper function to format a single transaction item for invoice display */
     private function formatInvoiceItem(array $item, int $rowNum): array {
         $formatted = [];
         $formatted['row_num'] = $rowNum;
         $formatted['product_type_farsi'] = Helper::translateProductType($item['gold_product_type'] ?? '');
         $formatted['description'] = $formatted['product_type_farsi']; // Start with type

         $formatted['quantity'] = $item['quantity'] ?? null;
         $formatted['quantity_formatted'] = Helper::formatNumber($item['quantity'] ?? 0, 0);
         $formatted['weight'] = $item['gold_weight_grams'] ?? null;
         $formatted['weight_formatted'] = Helper::formatNumber($item['gold_weight_grams'] ?? 0, 3);
         $formatted['carat'] = $item['gold_carat'] ?? null;
         $formatted['carat_formatted'] = Helper::escapeHtml($item['gold_carat'] ?? '-');
         $formatted['reference_carat'] = $item['reference_carat'] ?? null;
         $formatted['coin_year'] = $item['coin_year'] ?? null;
         $formatted['melted_tag_number'] = $item['melted_tag_number'] ?? null;
         $formatted['assay_office_name'] = $item['assay_office_name'] ?? null;
         $formatted['other_coin_description'] = $item['other_coin_description'] ?? null;
         $formatted['final_notes'] = $item['notes'] ?? null;
         $formatted['delivery_status'] = $item['delivery_status'] ?? null;
         $formatted['price_per_reference_gram'] = $item['price_per_reference_gram'] ?? null;
         $formatted['price_per_ref_gram_formatted'] = isset($item['price_per_reference_gram']) ? Helper::formatRial($item['price_per_reference_gram']) : '-';
         $formatted['calculated_weight_grams'] = $item['calculated_weight_grams'] ?? null;
         $formatted['gold_product_type'] = $item['gold_product_type'] ?? null;

         // محاسبه وزن معادل ۷۵۰
         if (($item['gold_weight_grams'] ?? 0) > 0 && ($item['gold_carat'] ?? 0) > 0) {
             $formatted['calculated_weight_grams'] = round(($item['gold_weight_grams'] * $item['gold_carat']) / 750, 3);
         } else {
             $formatted['calculated_weight_grams'] = null;
         }

         // Determine Rate and Unit Price columns based on product type
         $formatted['rate_display'] = '-';
         $formatted['rate_note'] = '';
         $formatted['unit_price_1g_display'] = '-'; // Price per 1 gram (750 or other ref)

         if (!empty($item['quantity']) && !empty($item['unit_price'])) { // Coin or Item with Quantity/Unit Price
             $formatted['rate_display'] = Helper::formatRial($item['unit_price']);
             $formatted['rate_note'] = '(هر عدد)';
             // Add year/description for specific coins
              if (($item['gold_product_type'] ?? '') === 'coin_emami' && !empty($item['coin_year'])) {
                   $formatted['description'] .= ' (' . Helper::escapeHtml($item['coin_year']) . ')';
              } elseif (($item['gold_product_type'] ?? '') === 'other_coin') {
                   $noteParts = explode("\n", $item['notes'] ?? '', 2);
                   if (str_starts_with($noteParts[0], 'نوع سکه:')) {
                       $formatted['description'] .= ' (' . Helper::escapeHtml(trim(substr($noteParts[0], strlen('نوع سکه:')))) . ')';
                   }
              }
         } elseif (!empty($item['gold_weight_grams'])) { // Weight based product
              if (!empty($item['mazaneh_price']) && $item['mazaneh_price'] > 0) {
                   $formatted['rate_display'] = Helper::formatRial($item['mazaneh_price']);
                   $formatted['rate_note'] = '(مظنه)';
                   // Calculate price per gram 750 from mazaneh
                    $pricePerGram750 = $item['mazaneh_price'] / 4.3318;
                   $formatted['unit_price_1g_display'] = Helper::formatRial($pricePerGram750 * (750 / 750)); // Assuming display as 750 equivalent price

              } elseif (!empty($item['price_per_reference_gram']) && $item['price_per_reference_gram'] > 0 && !empty($item['reference_carat'])) {
                    $refCarat = (int)($item['reference_carat']);
                    $formatted['rate_display'] = Helper::formatRial($item['price_per_reference_gram']);
                    $formatted['rate_note'] = '(۱ گرم @' . $refCarat . ')';
                    // Calculate price per gram 750 from reference price
                    $pricePerGram750 = $item['price_per_reference_gram'] * (750 / $refCarat);
                    $formatted['unit_price_1g_display'] = Helper::formatRial($pricePerGram750);
              }
         }

         $formatted['total_value_formatted'] = Helper::formatRial($item['total_value_rials'] ?? 0);
         $formatted['status_placeholder'] = '-'; // Placeholder for status if needed later
         $formatted['details'] = ''; // Add specific details like tag number if needed
          if (($item['gold_product_type'] ?? '') === 'melted' && !empty($item['melted_tag_number'])) {
              $formatted['details'] = 'ش‌ا: ' . Helper::escapeHtml($item['melted_tag_number']);
          }

         return $formatted;
     }


} // End InvoiceController class
