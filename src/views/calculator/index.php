<?php
/**
 * Template: src/views/calculator/index.php (یا calculator_page.php)
 * Displays the Gold Calculator interface.
 * Receives data via $viewData array from CalculatorController.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'ماشین حساب طلا';
$commonCarats = $viewData['common_carats'] ?? [750, 740, 705]; // Default common carats
$baseUrl = $viewData['baseUrl'] ?? ''; // Base URL if needed for assets

?>

<h1 class="mb-4"><?php echo Helper::escapeHtml($pageTitle); ?></h1>

<div class="alert alert-info small" role="alert">
    <i class="fas fa-info-circle me-1"></i>
     این ماشین حساب برای محاسبه سریع ارزش طلای آبشده یا متفرقه بر اساس قیمت مظنه روز و اعمال هزینه‌های مختلف مفید است. نتایج صرفاً جهت راهنمایی هستند و نباید مبنای قطعی معاملات قرار گیرند.
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">ورود اطلاعات محاسبه</h5>
    </div>
    <div class="card-body">
        <form id="calculator-form" class="needs-validation" novalidate onsubmit="return false;"> <?php /* Prevent actual form submission */ ?>
            <div class="row g-3">
                <?php // Mazaneh Price ?>
                <div class="col-md-6 col-lg-4">
                    <label for="calc_mazaneh" class="form-label">قیمت مظنه <small>(ریال)</small><span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text"
                               class="form-control format-number-js"
                               id="calc_mazaneh"
                               required
                               placeholder="مثال: 150,000,000"
                               inputmode="numeric"> <?php /* Hint numeric keyboard */ ?>
                         <span class="input-group-text">ریال</span>
                     </div>
                     <div class="invalid-feedback">لطفا قیمت مظنه معتبر (عدد مثبت) وارد کنید.</div>
                </div>

                <?php // Weight ?>
                <div class="col-md-6 col-lg-4">
                    <label for="calc_weight" class="form-label">وزن کل <small>(گرم)</small><span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text"
                               class="form-control format-number-js"
                               id="calc_weight"
                               required
                               placeholder="مثال: 12.345"
                               inputmode="decimal"> <?php /* Hint decimal keyboard */ ?>
                        <span class="input-group-text">گرم</span>
                    </div>
                     <div class="invalid-feedback">لطفا وزن معتبر (عدد مثبت) وارد کنید.</div>
                </div>

                <?php // Real Carat ?>
                <div class="col-md-6 col-lg-4">
                    <label for="calc_carat" class="form-label">عیار واقعی<span class="text-danger">*</span></label>
                    <div class="input-group">
                         <input type="text"
                                class="form-control format-number-js" <?php /* JS formatting might help */ ?>
                                id="calc_carat"
                                required
                                placeholder="مثال: 935 یا 750"
                                inputmode="numeric"
                                min="1" max="1000"> <?php /* Basic HTML5 validation */ ?>
                        <span class="input-group-text">از ۱۰۰۰</span>
                    </div>
                     <div class="invalid-feedback">لطفا عیار معتبر (بین ۱ تا ۱۰۰۰) وارد کنید.</div>
                </div>

                <?php // Percentages and Costs ?>
                <div class="col-sm-6 col-lg-3 mt-lg-3"> <?php /* Adjust mt for spacing */ ?>
                    <label for="calc_profit_percent" class="form-label">سود <small>(%)</small></label>
                    <div class="input-group">
                         <input type="text" class="form-control format-number-js" id="calc_profit_percent" value="0" inputmode="decimal">
                        <span class="input-group-text">%</span>
                     </div>
                </div>
                <div class="col-sm-6 col-lg-3 mt-lg-3">
                     <label for="calc_commission_percent" class="form-label">کارمزد <small>(%)</small></label>
                      <div class="input-group">
                          <input type="text" class="form-control format-number-js" id="calc_commission_percent" value="0" inputmode="decimal">
                         <span class="input-group-text">%</span>
                      </div>
                </div>
                 <div class="col-sm-6 col-lg-3 mt-lg-3">
                     <label for="calc_tax_percent" class="form-label">مالیات <small>(%)</small></label>
                      <div class="input-group">
                          <input type="text" class="form-control format-number-js" id="calc_tax_percent" value="0" inputmode="decimal">
                         <span class="input-group-text">%</span>
                      </div>
                 </div>
                 <div class="col-sm-6 col-lg-3 mt-lg-3">
                    <label for="calc_extra_costs" class="form-label">سایر هزینه‌ها <small>(ریال)</small></label>
                     <div class="input-group">
                         <input type="text" class="form-control format-number-js" id="calc_extra_costs" value="0" inputmode="numeric">
                        <span class="input-group-text">ریال</span>
                     </div>
                </div>
            </div>
             <div class="mt-3 text-end">
                 <button type="button" class="btn btn-sm btn-outline-secondary" id="reset-calculator">
                     <i class="fas fa-undo me-1"></i> پاک کردن مقادیر
                 </button>
            </div>
        </form>
    </div>
</div>

<hr class="my-4"> <?php /* Reduced margin */ ?>

<div class="card shadow-sm">
    <div class="card-header bg-light"> <?php /* Lighter header for results */ ?>
        <h5 class="mb-0"><i class="fas fa-calculator me-1"></i> نتایج محاسبه</h5>
    </div>
    <div class="card-body" id="calculator-results" style="min-height: 350px;">
        <div class="text-center text-muted my-5" id="results-placeholder">
            <i class="fas fa-info-circle fa-2x mb-2"></i><br>
            لطفاً مقادیر مظنه، وزن و عیار را برای مشاهده نتایج وارد کنید.
        </div>
        <div id="results-content" class="d-none"> <?php /* Hide initially */ ?>
            <div class="row gy-4">
                <?php // Base calculations ?>
                <div class="col-lg-6">
                    <h6 class="text-muted border-bottom pb-2 mb-3">محاسبات پایه</h6>
                    <dl class="row dl-horizontal small mb-0">
                         <dt class="col-sm-5 text-muted">قیمت ۱ گرم عیار <span id="res_real_carat_label" class="fw-bold text-dark">---</span></dt>
                         <dd class="col-sm-7 fw-bold text-end number-fa" id="res_price_per_real_gram">---</dd>

                         <dt class="col-sm-5 text-muted">ارزش پایه طلا</dt>
                         <dd class="col-sm-7 fw-bold text-end number-fa" id="res_base_gold_value">---</dd>

                         <dt class="col-sm-5 text-muted">مبلغ سود <small>(<span id="disp_profit_percent">0</span>%)</small></dt>
                         <dd class="col-sm-7 text-end number-fa" id="res_profit_amount">---</dd>

                         <dt class="col-sm-5 text-muted">مبلغ کارمزد <small>(<span id="disp_commission_percent">0</span>%)</small></dt>
                         <dd class="col-sm-7 text-end number-fa" id="res_commission_amount">---</dd>

                         <dt class="col-sm-5 text-muted">مبلغ مالیات <small>(<span id="disp_tax_percent">0</span>%)</small></dt>
                         <dd class="col-sm-7 text-end number-fa" id="res_tax_amount">---</dd>

                         <dt class="col-sm-5 text-muted">سایر هزینه‌ها</dt>
                         <dd class="col-sm-7 text-end number-fa" id="res_extra_costs_disp">---</dd>
                    </dl>
                </div>

                <?php // Carat table ?>
                <div class="col-lg-6">
                    <h6 class="text-muted border-bottom pb-2 mb-3">جزئیات بر اساس عیار مبنا</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped text-center small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>عیار مبنا</th>
                                    <th>وزن معادل<br><small>(گرم)</small></th>
                                    <th>قیمت هر گرم<br><small>(ریال)</small></th>
                                    <th>ارزش کل<br><small>(ریال)</small></th>
                                </tr>
                            </thead>
                            <tbody id="results-by-carat">
                                <?php foreach($commonCarats as $ref_carat): ?>
                                    <tr data-ref-carat="<?php echo (int)$ref_carat; ?>">
                                        <td class="fw-bold"><?php echo (int)$ref_carat; ?></td>
                                        <td class="res-equiv-weight number-fa">---</td>
                                        <td class="res-price-per-gram number-fa">---</td>
                                        <td class="res-total-value number-fa">---</td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php // Real carat row ?>
                                 <tr id="real-carat-result-row" class="table-info">
                                     <td class="fw-bold align-middle">
                                         <span id="res_real_carat_label_table">---</span>
                                         <br><small>(عیار واقعی)</small>
                                    </td>
                                     <td class="res-equiv-weight number-fa">---</td>
                                     <td class="res-price-per-gram number-fa">---</td>
                                     <td class="res-total-value number-fa">---</td>
                                 </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php // Final price suggestions ?>
            <hr class="my-4">
            <div class="row text-center">
                 <div class="col-md-6 mb-3 mb-md-0">
                     <div class="card h-100 border-success"> <?php /* Use card for better structure */ ?>
                         <div class="card-body">
                             <h5 class="text-success mb-2"><i class="fas fa-arrow-down me-1"></i> پیشنهاد قیمت کل فروشنده به شما (خرید شما)</h5>
                             <p class="display-6 fw-bold text-success mt-3 mb-1 number-fa" id="buy-suggestion-final">0</p>
                             <span class="text-success">ریال</span>
                             <p class="text-muted small mt-2 mb-0">
                                 برای <span id="disp_weight_buy" class="fw-bold number-fa">0</span> گرم طلا عیار <span id="disp_carat_buy" class="fw-bold number-fa">0</span>
                                 <br>(ارزش پایه <strong class="text-dark">منهای</strong> هزینه ها)
                             </p>
                         </div>
                     </div>
                 </div>
                 <div class="col-md-6">
                     <div class="card h-100 border-danger"> <?php /* Use card */ ?>
                          <div class="card-body">
                            <h5 class="text-danger mb-2"><i class="fas fa-arrow-up me-1"></i> پیشنهاد قیمت کل شما به خریدار (فروش شما)</h5>
                            <p class="display-6 fw-bold text-danger mt-3 mb-1 number-fa" id="sell-suggestion-final">0</p>
                             <span class="text-danger">ریال</span>
                            <p class="text-muted small mt-2 mb-0">
                                برای <span id="disp_weight" class="fw-bold number-fa">0</span> گرم طلا عیار <span id="disp_carat" class="fw-bold number-fa">0</span>
                                <br>(ارزش پایه <strong class="text-dark">بعلاوه</strong> سود و هزینه ها)
                            </p>
                         </div>
                     </div>
                </div>
            </div>
        </div> <?php // end #results-content ?>
    </div> <?php // end card-body ?>
</div> <?php // end results card ?>

<?php // --- Javascript for Calculator Logic and Number Formatting --- ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Get Elements ---
    const form = document.getElementById('calculator-form');
    const resultsContainer = document.getElementById('calculator-results');
    const resultsPlaceholder = document.getElementById('results-placeholder');
    const resultsContent = document.getElementById('results-content');
    if (!form || !resultsContainer || !resultsPlaceholder || !resultsContent) {
        console.error("Calculator elements not found!");
        return;
    }

    const inputs = {
        mazaneh: document.getElementById('calc_mazaneh'),
        weight: document.getElementById('calc_weight'),
        carat: document.getElementById('calc_carat'),
        profit: document.getElementById('calc_profit_percent'),
        commission: document.getElementById('calc_commission_percent'),
        tax: document.getElementById('calc_tax_percent'),
        extraCosts: document.getElementById('calc_extra_costs'),
    };

    const outputs = {
        realCaratLabel: document.getElementById('res_real_carat_label'),
        pricePerRealGram: document.getElementById('res_price_per_real_gram'),
        baseGoldValue: document.getElementById('res_base_gold_value'),
        profitAmount: document.getElementById('res_profit_amount'),
        commissionAmount: document.getElementById('res_commission_amount'),
        taxAmount: document.getElementById('res_tax_amount'),
        extraCostsDisp: document.getElementById('res_extra_costs_disp'),
        resultsByCaratBody: document.getElementById('results-by-carat'),
        realCaratRow: document.getElementById('real-carat-result-row'),
        realCaratLabelTable: document.getElementById('res_real_carat_label_table'),
        sellSuggestion: document.getElementById('sell-suggestion-final'),
        buySuggestion: document.getElementById('buy-suggestion-final'),
        dispWeight: document.getElementById('disp_weight'),
        dispCarat: document.getElementById('disp_carat'),
        dispWeightBuy: document.getElementById('disp_weight_buy'),
        dispCaratBuy: document.getElementById('disp_carat_buy'),
        dispProfitPercent: document.getElementById('disp_profit_percent'),
        dispCommissionPercent: document.getElementById('disp_commission_percent'),
        dispTaxPercent: document.getElementById('disp_tax_percent'),
    };

    const resetButton = document.getElementById('reset-calculator');

    // --- Helper Functions ---
    let lastValidValues = {};
    function sanitizeNumber(value) {
        if (typeof value !== 'string') value = String(value || '');
        const persianDigits = [/۰/g, /۱/g, /۲/g, /۳/g, /۴/g, /۵/g, /۶/g, /۷/g, /۸/g, /۹/g];
        const arabicDigits = [/٠/g, /١/g, /٢/g, /٣/g, /٤/g, /٥/g, /٦/g, /٧/g, /٨/g, /٩/g];
        for (let i = 0; i < 10; i++) {
            value = value.replace(persianDigits[i], i).replace(arabicDigits[i], i);
        }
        // تبدیل ویرگول فارسی و نقطه فارسی به نقطه انگلیسی
        value = value.replace(/[،٫]/g, '.');
        // حذف جداکننده هزارگان (کاما)
        value = value.replace(/,/g, '');
        // فقط اعداد و یک نقطه اعشار مجاز است
        return value;
    }

    function formatInputNumber(inputElement) {
        let value = inputElement.value;
        // تبدیل ویرگول و نقطه فارسی به نقطه انگلیسی
        value = value.replace(/[،٫]/g, '.');
        inputElement.value = value;
        lastValidValues[inputElement.id] = value;
    }

    function formatRial(number) {
        if (isNaN(number) || number === null) return '---';
        try {
             return new Intl.NumberFormat('fa-IR').format(Math.round(number));
        } catch(e) { return Math.round(number).toLocaleString(); }
    }

    function formatNumber(number, decimals = 3) {
        if (isNaN(number) || number === null) return '---';
         try {
             return new Intl.NumberFormat('fa-IR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(number);
         } catch(e){ return Number(number).toFixed(decimals); }
    }

    // --- Calculation Logic ---
    function calculate() {
        // Read and parse sanitized values
        const mazaneh = parseFloat(sanitizeNumber(inputs.mazaneh.value)) || 0;
        const weight = parseFloat(sanitizeNumber(inputs.weight.value)) || 0;
        const realCarat = parseInt(sanitizeNumber(inputs.carat.value)) || 0;
        const profitPercent = parseFloat(sanitizeNumber(inputs.profit.value)) || 0;
        const commissionPercent = parseFloat(sanitizeNumber(inputs.commission.value)) || 0;
        const taxPercent = parseFloat(sanitizeNumber(inputs.tax.value)) || 0;
        const extraCosts = parseFloat(sanitizeNumber(inputs.extraCosts.value)) || 0;

        const isValid = mazaneh > 0 && weight > 0 && realCarat > 0 && realCarat <= 1000;

        if (isValid) {
            resultsPlaceholder.classList.add('d-none');
            resultsContent.classList.remove('d-none');

            const pricePerGram750 = mazaneh / 4.3318;
            const pricePerRealGram = pricePerGram750 * (realCarat / 750);
            const baseGoldValue = weight * pricePerRealGram;

            const profitAmount = baseGoldValue * (profitPercent / 100);
            const commissionAmount = baseGoldValue * (commissionPercent / 100);
            const taxAmount = baseGoldValue * (taxPercent / 100);

            const sellPriceFinal = baseGoldValue + profitAmount + commissionAmount + taxAmount + extraCosts;
            const buyPriceFinal = baseGoldValue - commissionAmount - taxAmount - extraCosts; // Assuming profit isn't deducted for buy

            // Update display
            outputs.realCaratLabel.textContent = realCarat;
            outputs.pricePerRealGram.textContent = formatRial(pricePerRealGram);
            outputs.baseGoldValue.textContent = formatRial(baseGoldValue);
            outputs.dispProfitPercent.textContent = formatNumber(profitPercent, 2);
            outputs.profitAmount.textContent = formatRial(profitAmount);
            outputs.dispCommissionPercent.textContent = formatNumber(commissionPercent, 2);
            outputs.commissionAmount.textContent = formatRial(commissionAmount);
            outputs.dispTaxPercent.textContent = formatNumber(taxPercent, 2);
            outputs.taxAmount.textContent = formatRial(taxAmount);
            outputs.extraCostsDisp.textContent = formatRial(extraCosts);

            // Update table
            outputs.resultsByCaratBody.querySelectorAll('tr[data-ref-carat]').forEach(row => {
                const refCarat = parseInt(row.getAttribute('data-ref-carat')) || 0;
                if (refCarat > 0) {
                    const eqW = (weight * realCarat) / refCarat;
                    const prG = pricePerGram750 * (refCarat / 750);
                    const tV = eqW * prG;
                    row.querySelector('.res-equiv-weight').textContent = formatNumber(eqW, 3);
                    row.querySelector('.res-price-per-gram').textContent = formatRial(prG);
                    row.querySelector('.res-total-value').textContent = formatRial(tV);
                }
            });
            // Update real carat row in table
             if(outputs.realCaratRow && outputs.realCaratLabelTable){
                 outputs.realCaratLabelTable.textContent = realCarat;
                 outputs.realCaratRow.querySelector('.res-equiv-weight').textContent = formatNumber(weight, 3); // Eq weight is just weight
                 outputs.realCaratRow.querySelector('.res-price-per-gram').textContent = formatRial(pricePerRealGram);
                 outputs.realCaratRow.querySelector('.res-total-value').textContent = formatRial(baseGoldValue);
             }


            // Update final suggestions
            outputs.sellSuggestion.textContent = formatRial(sellPriceFinal);
            outputs.buySuggestion.textContent = formatRial(buyPriceFinal);
            const formattedWeight = formatNumber(weight, 3);
            [outputs.dispWeight, outputs.dispWeightBuy].forEach(el => el.textContent = formattedWeight);
            [outputs.dispCarat, outputs.dispCaratBuy].forEach(el => el.textContent = realCarat);

        } else {
            // Show placeholder, hide results if inputs are invalid
            resultsPlaceholder.classList.remove('d-none');
            resultsContent.classList.add('d-none');
        }
    }

    // --- Event Listeners ---
    form.addEventListener('input', (event) => {
         // Apply formatting as user types in number fields
         if (event.target && event.target.classList.contains('format-number-js')) {
              formatInputNumber(event.target);
         }
        // Recalculate everything on any input event
        calculate();
    });

    // Also format on paste
     Object.values(inputs).forEach(input => {
          if(input && input.classList.contains('format-number-js')) {
               input.addEventListener('paste', (e) => {
                   // Allow paste to happen, then format and calculate
                   setTimeout(() => {
                        formatInputNumber(e.target);
                        calculate();
                   }, 0);
               });
               // Also format on blur (when leaving the field)
               input.addEventListener('blur', (e) => {
                   formatInputNumber(e.target);
                   // Optionally re-calculate on blur too
                   // calculate();
               });
          }
     });


    if (resetButton) {
        resetButton.addEventListener('click', () => {
            form.reset(); // Reset native form fields
             form.classList.remove('was-validated'); // Reset validation state
            // Manually clear any fields that reset might not catch or recalculate outputs
             Object.values(inputs).forEach(input => { if(input) input.value = (input.step ? '0' : ''); }); // Reset to 0 or empty
            calculate(); // Recalculate to clear results display
        });
    }

    // Initial calculation and formatting on load
    Object.values(inputs).forEach(input => { if(input && input.classList.contains('format-number-js')) formatInputNumber(input); });
    calculate();

    // --- Bootstrap Validation ---
    // (Keep the submit prevention as we handle calculations via JS)
    // form.addEventListener('submit', event => {
    //     if (!form.checkValidity()) {
    //          event.preventDefault();
    //          event.stopPropagation();
    //     }
    //     form.classList.add('was-validated');
    // }, false);


});
</script>