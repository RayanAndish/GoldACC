<?php
use App\Utils\Helper;
use Morilog\Jalali\Jalalian;

$invoice = $viewData['invoice'] ?? [];
$pageTitle = 'فاکتور';
$invoiceContact = $invoice['contact'] ?? null;
$invoiceItems = $invoice['items'] ?? [];
$invoiceSummary = $invoice['summary'] ?? [];
$errorMessage = $invoice['error_msg'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';
$appName = $viewData['appName'] ?? 'حسابداری رایان طلا';
$currentDateFarsi = $viewData['current_date_farsi'] ?? Jalalian::now()->format('Y/m/d');

// اطلاعات خریدار و فروشنده از کنترلر ارسال شده
$buyerInfo = $invoice['buyer_info'] ?? [];
$sellerInfo = $invoice['seller_info'] ?? [];
$invoiceTypeLabel = $invoice['type_label'] ?? 'فاکتور';

// استخراج مقادیر از خلاصه مالی
$subTotal = (float)($invoiceSummary['sub_total'] ?? 0.0);
$totalGeneralTax = (float)($invoiceSummary['total_general_tax'] ?? 0.0);
$totalVat = (float)($invoiceSummary['total_vat'] ?? 0.0);
$grandTotal = (float)($invoiceSummary['grand_total'] ?? 0.0);
$grandTotalWords = $invoiceSummary['grand_total_words'] ?? '';
$itemCount = (int)($invoiceSummary['item_count'] ?? count($invoiceItems));

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo Helper::escapeHtml($invoiceTypeLabel . ' - ' . ($invoiceContact['name'] ?? '')); ?></title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/bootstrap.rtl.min.css">
    <style>
        body { background-color: #eee; font-family: 'Vazirmatn', sans-serif !important; }
        .invoice-container { max-width: 100%; margin: 2rem auto; background: #fff; border: 1px solid #ddd; }
        .table > :not(caption) > * > * { padding: 0.6rem; vertical-align: middle; }
        .table th { font-weight: 600; }
        .table tfoot td { border-width: 0; }
        .signature-area { margin-top: 5rem; }
        
        /* --- استایل‌های نهایی و کامل برای چاپ --- */
        @media print {
            /* --- تنظیمات کلی صفحه --- */
            body {
                background-color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print { display: none !important; }
            .invoice-container {
                width: 95%;
                max-width: 100%;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }

            /* --- کاهش فاصله‌های اضافی برای جا شدن در یک صفحه --- */
            .row, p, h2, h4, h6, section {
                margin-bottom: 0.5rem !important; /* کاهش فاصله پایین تمام بخش‌ها */
            }
            header.row {
                margin-bottom: 1rem !important;
            }
            
            /* --- تنظیمات جدول --- */
            .table {
                margin-bottom: 0.5rem !important;
            }
            .table > :not(caption) > * > * {
                padding: 0.3rem 0.4rem; /* کاهش پدینگ داخلی سلول‌ها */
            }
            .table-responsive {
                overflow-x: hidden !important; /* حذف اسکرول افقی در چاپ */
            }

            /* --- تنظیمات اندازه فونت برای خوانایی و بهینه‌سازی فضا --- */
            body, .table {
                font-size: 9pt !important;
            }
            h2 { font-size: 13pt !important; }
            h4, h6 { font-size: 11pt !important; }
            
            /* (اصلاح کلیدی) تنظیم اندازه فونت خلاصه مالی */
            .summary-table td {
                font-size: 10pt !important;
            }
            /* (اصلاح کلیدی) کوچک کردن "مبلغ نهایی قابل پرداخت" در چاپ */
            .summary-table .grand-total-row td {
                font-size: 12pt !important;
            }

            /* --- تنظیمات پایانی --- */
            .signature-area { margin-top: 1rem !important; }
            footer { margin-top: 1.5rem !important; }
        }
    </style>
</head>
<body>
    <div class="invoice-container p-2 p-md-2">
        <?php if ($errorMessage): ?>
            <!-- ... -->
        <?php else: ?>
            <div class="text-center mb-4 no-print">
                 <button class="btn btn-success" onclick="window.print();"><i class="fas fa-print me-1"></i> چاپ</button>
                 <a href="javascript:history.back()" class="btn btn-secondary ms-2">بازگشت</a>
             </div>
             
             <div class="text-center">
                <h2><?php echo Helper::escapeHtml($invoiceTypeLabel); ?></h2>
             </div>
             
             <header class="row mb-1 mt-1">
                 <div class="col-6">
                     <p><strong>شماره فاکتور:</strong> <?php echo Helper::escapeHtml('RGI-' . date('Ymd') . $invoiceContact['id']); ?></p>
                     <p><strong>تاریخ صدور:</strong> <?php echo $currentDateFarsi; ?></p>
                 </div>
                 <div class="col-6 text-end">
                     <h4><?php echo Helper::escapeHtml($appName); ?></h4>
                     <p class="small text-muted"><?php // اطلاعات آدرس و تلفن مالک سامانه ?></p>
                 </div>
             </header>

                <!-- (اصلاح نهایی) بخش اطلاعات خریدار و فروشنده -->
                <section class="row mb-1">
                 <div class="col-6">
                     <h6>مشخصات فروشنده:</h6>
                     <p><strong>نام:</strong> <?php echo Helper::escapeHtml($sellerInfo['name'] ?? ($sellerInfo['customer_name'] ?? '')); ?></p>
                     <p class="small text-muted">
                        <?php 
                        // نمایش جزئیات طرف حساب یا آدرس مالک سامانه
                        $sellerDetails = $sellerInfo['details'] ?? ($sellerInfo['seller_address'] ?? '');
                        echo nl2br(Helper::escapeHtml($sellerDetails));
                        ?>
                     </p>
                 </div>
                 <div class="col-6">
                     <h6>مشخصات خریدار:</h6>
                     <p><strong>نام:</strong> <?php echo Helper::escapeHtml($buyerInfo['name'] ?? ($buyerInfo['customer_name'] ?? '')); ?></p>
                      <p class="small text-muted">
                        <?php
                        $buyerDetails = $buyerInfo['details'] ?? ($buyerInfo['seller_address'] ?? '');
                        echo nl2br(Helper::escapeHtml($buyerDetails));
                        ?>
                     </p>
                 </div>
                </section>

                 <?php // --- Invoice Items Table --- ?>
                 <?php if (!empty($invoiceItems)): ?>
                <div class="table-responsive">
                 <table class="table table-bordered table-sm align-middle text-center">
                     <thead class="table-light">
                         <tr>
                             <th rowspan="2">ردیف</th>
                             <th rowspan="2">شرح کالا</th>
                             <th rowspan="2" class="align-middle" style="width: 4%;">مقدار</th>
                             <th rowspan="2" class="align-middle" style="width: 3%;">عیار/سال</th>
                             <th colspan="3" class="align-middle" style="width: 25%;">ارزش گذاری (ریال)</th>
                             <th rowspan="2">قیمت خالص</th>
                             <th rowspan="2">سود/اجرت</th>
                             <th rowspan="2" class="align-middle" style="width: 6%;">وضعیت تحویل</th>
                             <th rowspan="2">مبلغ نهایی (ریال)</th>
                         </tr>
                         <tr>
                             <th>وزن ۷۵۰ (گرم)</th>
                             <th>نرخ واحد/مظنه</th>
                             <th>قیمت ۱گرم (مبنا)</th>
                        </tr>
                     </thead>
                     <tbody>
                        <?php foreach ($invoiceItems as $item): ?>
                        <tr>
                            <td><?php echo $item['row_num']; ?></td>
                            <td class="text-start"><?php echo $item['product_type_farsi']; ?></td>
                            <td><?php echo $item['quantity_formatted']; ?></td>
                            <td><?php echo $item['carat_formatted']; ?></td>
                            <td><?php echo isset($item['calculated_weight_grams']) ? Helper::formatPersianNumber($item['calculated_weight_grams'], 3) : '-'; ?></td>
                            <td class="text-end"><?php echo $item['rate_display']; ?><br><small class="text-muted"><?php echo $item['rate_note']; ?></small></td>
                            <td class="text-end"><?php echo $item['price_per_ref_gram_formatted']; ?></td>
                            <td class="text-end"><?php echo $item['base_value_formatted']; ?></td>
                            <td class="text-end"><?php echo $item['profit_wage_commission_formatted']; ?></td>
                            <td><?php echo Helper::translateDeliveryStatus($item['delivery_status']); ?></td>
                            <td class="text-end fw-bold"><?php echo $item['final_value_formatted']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                     </tbody>
                 </table>
                </div>
                <div class="row justify-content-end mt-1">
                 <div class="col-md-6">
                     <table class="table table-sm summary-table"> <?php // افزودن کلاس ?>
                         <tbody>
                             <tr>
                                 <td class="fw-bold">جمع کل (ریال):</td>
                                 <td class="text-end"><?php echo Helper::formatRial($subTotal); ?></td>
                             </tr>
                             <?php if ($totalGeneralTax > 0): ?>
                             <tr>
                                 <td>جمع مالیات عمومی:</td>
                                 <td class="text-end"><?php echo Helper::formatRial($totalGeneralTax); ?></td>
                             </tr>
                             <?php endif; ?>
                             <?php if ($totalVat > 0): ?>
                             <tr>
                                 <td>جمع مالیات بر ارزش افزوده:</td>
                                 <td class="text-end"><?php echo Helper::formatRial($totalVat); ?></td>
                             </tr>
                             <?php endif; ?>
                              <tr class="table-light grand-total-row"> <?php // افزودن کلاس ?>
                                 <td class="fw-bold fs-5">مبلغ نهایی قابل پرداخت (ریال):</td>
                                 <td class="text-end fw-bold fs-5"><?php echo Helper::formatRial($grandTotal); ?></td>
                             </tr>
                         </tbody>
                     </table>
                     <p class="small text-muted mt-2">به حروف: <?php echo Helper::escapeHtml($grandTotalWords); ?></p>
                 </div>
             </div>
            <?php endif; // end of invoiceItems check ?>

            <footer class="mt-2 pt-2">
                 <div class="row signature-area">
                    <div class="col-6 text-center border-top pt-1"><p>امضاء فروشنده</p></div>
                    <div class="col-6 text-center border-top pt-1"><p>امضاء خریدار</p></div>
                 </div>
                 <div class="text-center text-muted small mt-0">
                     <p>فروش آغاز یک تعهد است ، تعهد ما ضمانت اصالت ، کیفیت و قیمت رقابتی است </p>
                 </div>
            </footer>
        <?php endif; ?>
    </div>

    <?php // Bootstrap JS (needed for tooltips if used in future) ?>
    <script src="<?php echo $baseUrl; ?>/js/bootstrap.bundle.min.js"></script>
</body>
</html>