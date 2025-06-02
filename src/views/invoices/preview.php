<?php
/**
 * Template: src/views/invoices/preview.php
 * Displays a printable invoice preview.
 * Receives data via $viewData array from InvoiceController::preview.
 * Relies on CSS classes defined in style.css (@media print) for formatting.
 */

use App\Utils\Helper; // Use the Helper class
use Morilog\Jalali\Jalalian; // Add Jalalian namespace

// --- Extract data from $viewData ---
$invoice = $viewData['invoice'] ?? [];
$pageTitle = $invoice['page_title'] ?? 'پیش‌نمایش فاکتور';
$invoiceContact = $invoice['contact'] ?? null; // Customer/Supplier info
$invoiceItems = $invoice['items'] ?? []; // Array of transaction items for the invoice
$invoiceSummary = $invoice['summary'] ?? []; // Totals, tax, words
$invoiceTypeLabel = $invoice['type_label'] ?? 'فاکتور معاملات';
$errorMessage = $invoice['error_msg'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';
$appName = $viewData['appName'] ?? 'حسابداری رایان طلا';
$currentDateFarsi = $viewData['current_date_farsi'] ?? Jalalian::fromFormat('Y-m-d', date('Y-m-d'))->format('Y/m/d');

// Seller info (should come from settings/config via controller)
$sellerInfo = $viewData['seller_info'] ?? [
    'name' => Helper::escapeHtml($appName),
    'address' => 'آدرس شما...',
    'phone' => 'تلفن شما...',
    'logo_path' => 'images/logo.png', // Relative to public path
    'registration_code' => '',
    'postal_code' => ''
];

// Ensure summary keys exist
$subTotal = (float)($invoiceSummary['sub_total'] ?? 0.0);
$taxRatePercent = (float)($invoiceSummary['tax_rate_percent'] ?? 0.0);
$taxAmount = (float)($invoiceSummary['tax_amount'] ?? 0.0);
$grandTotal = (float)($invoiceSummary['grand_total'] ?? $subTotal);
$grandTotalWords = $invoiceSummary['grand_total_words'] ?? Helper::convertNumberToWords($grandTotal) . ' ریال';
$itemCount = (int)($invoiceSummary['item_count'] ?? count($invoiceItems));

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Helper::escapeHtml($pageTitle . ' - ' . ($invoiceContact['name'] ?? '')); ?></title>
    <base href="<?php echo Helper::escapeHtml(rtrim($baseUrl, '/') . '/'); ?>/">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css"> <?php // Include main style for consistency? ?>
</head>
<body id="app-body" class="bg-light invoice-preview"> <?php // Use a light background for preview contrast ?>

    <?php // --- Main Invoice Container --- ?>
    <div class="container my-4"> <?php // Standard container for centering/padding ?>
        <div class="invoice-container bg-white p-4 p-md-5 shadow-sm border"> <?php // Apply class for print styles ?>

             <?php // --- Error Handling --- ?>
            <?php if ($errorMessage || !$invoiceContact): ?>
                 <div class="alert alert-danger no-print">
                     <?php echo Helper::escapeHtml($errorMessage ?: 'اطلاعات لازم برای نمایش فاکتور یافت نشد.'); ?>
                 </div>
                 <div class="text-center no-print mt-3">
                     <button onclick="window.close();" class="btn btn-secondary btn-sm">بستن</button>
                     <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm ms-2">بازگشت</a>
                 </div>
            <?php else: // ---- START INVOICE CONTENT ---- ?>

                 <?php // --- Action Buttons (No Print) --- ?>
                 <div class="text-center mb-4 no-print">
                     <button class="btn btn-success btn-sm" onclick="window.print();"><i class="fas fa-print me-1"></i> چاپ</button>
                     <button class="btn btn-secondary btn-sm ms-2" onclick="window.close();"><i class="fas fa-times me-1"></i> بستن</button>
                     <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm ms-2"><i class="fas fa-arrow-left me-1"></i>بازگشت</a>
                 </div>

                <?php // --- Invoice Header --- ?>
                <div class="row mb-4 align-items-start invoice-header">
                    <div class="col-7 invoice-details">
                        <h2 class="mb-2 fw-bold"><?php echo Helper::escapeHtml($invoiceTypeLabel); ?></h2>
                        <p><strong>شماره فاکتور:</strong> <span class="number-fa"><?php echo Helper::escapeHtml('INV-' . date('ymd') . $invoiceContact['id']); ?></span></p>
                        <p><strong>تاریخ صدور:</strong> <span class="number-fa"><?php echo Helper::escapeHtml($currentDateFarsi); ?></span></p>
                    </div>
                    <div class="col-5 text-start invoice-details"> <?php // Align left in RTL for logo/seller info ?>
                         <?php if (!empty($sellerInfo['logo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($sellerInfo['logo_path'], '/'))): ?>
                             <img src="<?php echo Helper::escapeHtml($sellerInfo['logo_path']); ?>" alt="لوگو" style="max-height: 60px; margin-bottom: 10px;">
                         <?php else: ?>
                            <h6 class="mb-1 fw-bold"><?php echo $sellerInfo['name']; ?></h6>
                         <?php endif; ?>
                        <p class="small mb-1"><?php echo nl2br(Helper::escapeHtml($sellerInfo['address'])); ?></p>
                        <p class="small mb-0">تلفن: <span class="number-fa"><?php echo Helper::escapeHtml($sellerInfo['phone']); ?></span></p>
                        <?php if (!empty($sellerInfo['registration_code'])): ?><p class="small mb-0"><?php echo Helper::escapeHtml($sellerInfo['registration_code']); ?></p><?php endif; ?>
                        <?php if (!empty($sellerInfo['postal_code'])): ?><p class="small mb-0">کدپستی: <span class="number-fa"><?php echo Helper::escapeHtml($sellerInfo['postal_code']); ?></span></p><?php endif; ?>
                    </div>
                </div> <?php // End Header ?>

                <?php // --- Contact Info --- ?>
                <div class="invoice-details border rounded p-3 mb-4 bg-light">
                    <h6 class="mb-2">مشخصات <?php echo (stripos($invoiceTypeLabel,'خرید') !== false) ? 'فروشنده' : 'خریدار'; ?>:</h6>
                    <p class="mb-1"><strong>نام:</strong> <?php echo $invoiceContact['name']; // Already escaped ?></p>
                    <?php if (!empty($invoiceContact['details'])): ?>
                        <div class="row gx-3 small">
                             <?php // Try to parse details (basic line splitting) ?>
                             <?php $lines = preg_split('/\r\n|\r|\n/', trim($invoiceContact['details'])); $lineCount = count($lines); ?>
                             <?php foreach($lines as $i => $line): if(empty(trim($line))) continue; $parts=explode(':', $line, 2); ?>
                                 <div class="<?php echo ($lineCount > 1 && $i < 2) ? 'col-sm-6' : 'col-sm-12'; ?> mb-1"> <?php // Two columns for first 2 lines if more than 1 line ?>
                                      <?php if(isset($parts[1])): // Key: Value format ?>
                                         <strong><?php echo Helper::escapeHtml(trim($parts[0]));?>:</strong> <span class="number-fa"><?php echo Helper::escapeHtml(trim($parts[1]));?></span>
                                      <?php else: // Simple line ?>
                                          <?php echo Helper::escapeHtml(trim($parts[0])); ?>
                                      <?php endif; ?>
                                 </div>
                             <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div> <?php // End Contact Info ?>

                 <?php // --- Invoice Items Table --- ?>
                 <?php if (!empty($invoiceItems)): ?>
                    <div class="table-responsive">
                         <table class="invoice-items-table table table-bordered table-sm align-middle text-center mb-0">
                             <thead class="table-light">
                                 <tr>
                                    <th rowspan="2" class="align-middle">ر</th>
                                    <th rowspan="2" class="align-middle">شرح کالا / خدمات</th>
                                    <th rowspan="2" class="align-middle">مقدار</th>
                                    <th rowspan="2" class="align-middle">عیار/<br>سال</th>
                                    <th colspan="3">ارزش گذاری (ریال)</th>
                                    <th rowspan="2" class="align-middle">وضعیت تحویل</th>
                                    <th rowspan="2" class="align-middle">مبلغ کل<br>(ریال)</th>
                                 </tr>
                                <tr>
                                     <th>وزن ۷۵۰<br><small>(گرم)</small></th>
                                    <th>نرخ واحد/<br>مظنه</th>
                                    <th>قیمت ۱گرم<br><small>(عیار مبنا)</small></th>
                                 </tr>
                             </thead>
                             <tbody>
                                <?php // Item rows generated by controller/helper previously ?>
                                <?php foreach ($invoiceItems as $item): ?>
                                <tr class="item">
                                    <td class="num small"><?php echo $item['row_num']; ?></td>
                                    <td class="desc small text-start"> <?php // Align description left ?>
                                        <strong><?php echo Helper::escapeHtml($item['product_type_farsi'] ?? '-'); ?></strong>
                                         <div class="item-details text-muted">
                                              <?php if(isset($item['gold_weight_grams']) && $item['gold_weight_grams'] > 0):?><span>وزن: <span class="number-fa"><?php echo $item['weight_formatted'];?> گ</span> / عیار: <span class="number-fa"><?php echo $item['carat_formatted'];?></span></span><?php endif;?>
                                              <?php if(isset($item['quantity']) && $item['quantity'] > 0):?><span>تعداد: <span class="number-fa"><?php echo $item['quantity_formatted'];?> ع</span></span><?php endif;?>
                                              <?php if(!empty($item['coin_year'])):?><span> / سال: <span class="number-fa"><?php echo Helper::escapeHtml($item['coin_year']);?></span></span><?php endif;?>
                                              <?php if(!empty($item['melted_tag_number'])):?><span> / انگ: <?php echo Helper::escapeHtml($item['melted_tag_number']);?></span><?php endif;?>
                                              <?php if(!empty($item['assay_office_name'])):?><span> (ری‌گیری: <?php echo Helper::escapeHtml($item['assay_office_name']);?>)</span><?php endif;?>
                                              <?php if(!empty($item['other_coin_description'])):?><span> / <?php echo Helper::escapeHtml($item['other_coin_description']);?></span><?php endif;?>
                                              <?php if(!empty($item['final_notes'])):?><br><i><?php echo Helper::escapeHtml($item['final_notes']);?></i><?php endif; ?>
                                         </div>
                                    </td>
                                    <td class="num number-fa"><?php echo $item['quantity'] ? $item['quantity_formatted'] : $item['weight_formatted']; ?></td>
                                    <td class="num number-fa"><?php echo $item['quantity'] ? ($item['coin_year'] ?: '-') : $item['carat_formatted']; ?></td>
                                    <td class="num number-fa"><?php echo ($item['gold_product_type'] === 'melted' || $item['gold_product_type'] === 'used_jewelry' || $item['gold_product_type'] === 'new_jewelry' || $item['gold_product_type'] === 'bullion') ? (isset($item['calculated_weight_grams']) ? Helper::formatNumber($item['calculated_weight_grams'], 3) : '-') : '-';?></td>
                                    <td class="money number-fa text-end"><?php echo $item['rate_display'] ?? '-'; ?> <small class="text-muted"><?php echo $item['rate_note'] ?? '';?></small></td>
                                    <td class="money number-fa text-end"><?php echo $item['price_per_ref_gram_formatted'] ?? '-';?></td>
                                    <td class="small"><?php echo Helper::translateDeliveryStatus($item['delivery_status'] ?? null); ?></td>
                                    <td class="money number-fa text-end fw-bold"><?php echo $item['total_value_formatted'] ?? '-'; ?></td>
                                </tr>
                                 <?php endforeach; ?>
                                 <?php // Add empty rows if needed for fixed height invoice (handled by @media print styles better) ?>
                             </tbody>
                             <tfoot class="border-top">
                                <tr><td colspan="8" class="fw-bold border-0 text-start pt-2">جمع کل (ریال):</td><td class="money number-fa text-end fw-bold border-0 pt-2"><?php echo Helper::formatNumber($subTotal, 0); ?></td></tr>
                                 <?php if ($taxAmount >= 0): // Show tax row ?>
                                 <tr class="subtotal"><td colspan="8" class="fw-bold border-0 text-start pt-1">مالیات بر ارزش افزوده (<?php echo Helper::formatNumber($taxRatePercent, 2); ?>٪):</td><td class="money number-fa text-end fw-bold border-0 pt-1"><?php echo Helper::formatNumber($taxAmount, 0); ?></td></tr>
                                 <?php endif; ?>
                                 <tr class="grand-total"><td colspan="8" class="fw-bold border-0 text-start pt-2">مبلغ نهایی قابل پرداخت (ریال):</td><td class="money number-fa text-end fw-bold border-0 fs-5 pt-2"><?php echo Helper::formatNumber($grandTotal, 0); ?></td></tr>
                                 <tr><td colspan="9" class="border-0 small text-muted text-start pt-1 pb-0"> مبلغ به حروف: <?php echo Helper::escapeHtml($grandTotalWords); ?></td></tr>
                            </tfoot>
                         </table>
                    </div> <?php // end table-responsive ?>
                 <?php else: ?>
                     <div class="alert alert-warning">هیچ آیتمی برای نمایش در فاکتور انتخاب نشده است.</div>
                 <?php endif; ?>


                <?php // --- Signature Area --- ?>
                <div class="row signature-area mt-5">
                     <div class="col-6 text-center signature"><p>امضاء فروشنده</p></div>
                     <div class="col-6 text-center signature"><p>امضاء خریدار</p></div>
                 </div>

                 <?php // --- Invoice Footer --- ?>
                  <div class="row invoice-footer mt-4 pt-3 text-muted small">
                      <div class="col-sm-8 text-end">
                          [متن ثابت توضیحات پایانی فاکتور...]
                       </div>
                       <div class="col-sm-4 text-start">
                          <?php echo $sellerInfo['name']; ?> © <?php echo Helper::formatNumber(date('Y'), 0, '.', ''); ?>
                        </div>
                  </div>

            <?php endif; // ---- END INVOICE CONTENT ---- ?>
        </div> <?php // End Invoice Container ?>
    </div> <?php // End Bootstrap Container ?>

    <?php // Bootstrap JS (needed for tooltips if used in future) ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>