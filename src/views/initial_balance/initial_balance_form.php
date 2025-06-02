<?php
/**
 * Template: src/views/initial_balance/form.php
 * Form for creating/editing initial balance
 */
use Monolog\Logger;
use Morilog\jalali\Jalalian;
use App\Utils\Helper;
use App\Core\CSRFProtector;

// Extract data from $viewData
$pageTitle = $viewData['pageTitle'] ?? $viewData['page_title'] ?? 'ثبت موجودی اولیه و سرمایه هدف جدید';
$products = $viewData['products'] ?? [];
$initialBalance = $viewData['initial_balance'] ?? null;
$errorMessage = $viewData['error_message'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';
$fields = $viewData['fields'] ?? [];
$formulas = $viewData['formulas'] ?? [];

// Debug information
error_log('Fields: ' . print_r($fields, true));
error_log('Formulas: ' . print_r($formulas, true));

// Form action and method
$formAction = $initialBalance 
    ? $baseUrl . '/app/initial-balance/update/' . $initialBalance->id
    : $baseUrl . '/app/initial-balance/save';
$formMethod = 'POST';
?>

<div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= htmlspecialchars($pageTitle) ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger"><?= $errorMessage ?></div>
                    <?php endif; ?>

                    <form id="initialBalanceForm" method="post" action="<?= $baseUrl ?>/app/initial-balance/save" class="needs-validation" novalidate>
                        <!-- بخش موجودی اولیه محصولات -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4>موجودی اولیه محصولات</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- انتخاب محصول -->
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="product_id">انتخاب محصول:</label>
                                            <select class="form-control" id="product_id" name="product_id" required>
                                                <option value="">انتخاب کنید...</option>
                                                <?php foreach ($products as $product): ?>
                                                    <option value="<?= $product->id ?>"
                                                        data-base-category="<?= $product->category->base_category ?? '' ?>"
                                                        data-product-type="<?= $product->type ?? '' ?>"
                                                        data-default-carat="<?= $product->default_carat ?>">
                                                        <?= $product->name ?>
                                                        <?php if (isset($product->category->base_category) && ($product->category->base_category === 'gold' || $product->category->base_category === 'silver')): ?>
                                                            (عیار: <?= $product->default_carat ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- تاریخ موجودی -->
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="balance_date">تاریخ موجودی:</label>
                                            <input type="text" class="form-control jalali-datepicker" id="balance_date" name="balance_date" required readonly>
                                        </div>
                                    </div>

                                    <!-- قیمت مظنه -->
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="market_price">قیمت مظنه (ریال):</label>
                                            <input type="text" class="form-control autonumeric" id="market_price" name="market_price" 
                                                   data-autonumeric-options='{"digitGroupSeparator": "٬", "decimalPlaces": 0}'>
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلدهای دینامیک بر اساس نوع محصول -->
                                <div class="row mt-3" id="dynamicFields">
                                    <!-- وزن ترازو -->
                                    <div class="col-md-3 weight-fields" style="display: none;">
                                        <div class="form-group">
                                            <label for="scale_weight">وزن ترازو (گرم):</label>
                                            <input type="text" class="form-control autonumeric" id="scale_weight" name="scale_weight" 
                                                   data-autonumeric-options='{"digitGroupSeparator": "٬", "decimalPlaces": 3}'>
                                        </div>
                                    </div>

                                    <!-- وزن 750 -->
                                    <div class="col-md-3 weight-fields" style="display: none;">
                                        <div class="form-group">
                                            <label for="weight_750">وزن 750 (گرم):</label>
                                            <input type="text" class="form-control autonumeric" id="weight_750" name="weight_750" readonly
                                                   data-autonumeric-options='{"digitGroupSeparator": "٬", "decimalPlaces": 3}'>
                                        </div>
                                    </div>

                                    <!-- موجودی وزنی -->
                                    <div class="col-md-3 weight-fields" style="display: none;">
                                        <div class="form-group">
                                            <label for="weight_grams">موجودی (گرم):</label>
                                            <input type="text" class="form-control autonumeric" id="weight_grams" name="weight_grams" 
                                                   data-autonumeric-options='{"digitGroupSeparator": "٬", "decimalPlaces": 3}'>
                                        </div>
                                    </div>

                                    <!-- موجودی تعدادی -->
                                    <div class="col-md-3 quantity-fields" style="display: none;">
                                        <div class="form-group">
                                            <label for="quantity">موجودی (تعداد):</label>
                                            <input type="text" class="form-control autonumeric" id="quantity" name="quantity" 
                                                   data-autonumeric-options='{"digitGroupSeparator": "٬", "decimalPlaces": 0}'>
                                        </div>
                                    </div>

                                    <!-- عیار -->
                                    <div class="col-md-3 carat-fields" style="display: none;">
                                        <div class="form-group">
                                            <label for="carat">عیار:</label>
                                            <input type="number" class="form-control" id="carat" name="carat" min="1" max="24">
                                        </div>
                                    </div>

                                    <!-- قیمت خرید واحد -->
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="average_purchase_price_per_unit">قیمت خرید واحد (ریال):</label>
                                            <input type="text" class="form-control autonumeric" id="average_purchase_price_per_unit" name="average_purchase_price_per_unit" required
                                                   data-autonumeric-options='{"digitGroupSeparator": "٬", "decimalPlaces": 0}'>
                                        </div>
                                    </div>

                                    <!-- ارزش کل -->
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="total_purchase_value">ارزش کل (ریال):</label>
                                            <input type="text" class="form-control autonumeric" id="total_purchase_value" name="total_purchase_value" readonly
                                                   data-autonumeric-options='{"digitGroupSeparator": "٬", "decimalPlaces": 0}'>
                                        </div>
                                    </div>

                                    <!-- سرمایه هدف -->
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="target_capital">سرمایه هدف (ریال):</label>
                                            <input type="text" class="form-control autonumeric" id="target_capital" name="target_capital" required
                                                   data-autonumeric-options='{"digitGroupSeparator": "٬", "decimalPlaces": 0}'>
                                        </div>
                                    </div>

                                    <!-- درصد تراز -->
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="balance_percentage">درصد تراز:</label>
                                            <input type="text" class="form-control" id="balance_percentage" readonly>
                                        </div>
                                    </div>

                                    <!-- وضعیت تراز -->
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="balance_status">وضعیت تراز:</label>
                                            <input type="text" class="form-control" id="balance_status" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- بخش موجودی اولیه حساب‌های بانکی -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4>موجودی اولیه حساب‌های بانکی</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>نام بانک</th>
                                                    <th>شماره حساب</th>
                                                    <th>موجودی اولیه</th>
                                                    <th>سرمایه هدف</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($viewData['bank_accounts'] as $account): ?>
                                                <tr>
                                                    <td><?= $account['bank_name'] ?></td>
                                                    <td><?= $account['account_number'] ?></td>
                                                    <td>
                                                        <input type="text" class="form-control autonumeric" 
                                                               name="bank_initial_balance[<?= $account['id'] ?>]" 
                                                               value="<?= $account['initial_balance'] ?? '0' ?>"
                                                               data-autonumeric-options='{"digitGroupSeparator": "٬", "decimalPlaces": 0}'>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control autonumeric" 
                                                               name="bank_target_capital[<?= $account['id'] ?>]" 
                                                               value="<?= $account['target_capital'] ?? '0' ?>"
                                                               data-autonumeric-options='{"digitGroupSeparator": "٬", "decimalPlaces": 0}'>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- توضیحات -->
                        <div class="form-group">
                            <label for="notes">توضیحات:</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">ذخیره</button>
                            <a href="<?= $baseUrl ?>/app/initial-balance" class="btn btn-secondary">انصراف</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    // مقداردهی اولیه تاریخ شمسی
    $('.jalali-datepicker').each(function() {
        jalaliDatepicker.startWatch({
            selector: '#' + $(this).attr('id'),
            persianDigits: false,
            showTodayBtn: true,
            showCloseBtn: true,
            autoClose: true,
            format: 'Y/m/d',
        });
    });

    // مقداردهی اولیه AutoNumeric برای همه فیلدهای عددی
    $('.autonumeric').each(function() {
        var options = { digitGroupSeparator: '٬', decimalPlaces: 0, unformatOnSubmit: true };
        var dataOptions = $(this).data('autonumeric-options');
        if (dataOptions) {
            try { $.extend(options, typeof dataOptions === 'string' ? JSON.parse(dataOptions) : dataOptions); } catch(e) {}
        }
        new AutoNumeric(this, options);
    });

    // نمایش/مخفی کردن فیلدها بر اساس base_category
    $('#product_id').on('change', function() {
        const $selected = $(this).find('option:selected');
        const baseCategory = $selected.data('base-category');
        console.log('baseCategory:', baseCategory);
        $('.weight-fields, .quantity-fields, .carat-fields').hide();
        $('#market_price').closest('.col-md-4').hide();
        if (baseCategory === 'MELTED' || baseCategory === 'MANUFACTURED') {
            $('.weight-fields, .carat-fields').show();
            $('#market_price').closest('.col-md-4').show();
        } else if (baseCategory === 'COIN') {
            $('.quantity-fields').show();
        } else if (baseCategory === 'GOLD') {
            $('.weight-fields, .carat-fields').show();
        }
        clearCalculations();
    });

    // محاسبه قیمت واحد بر اساس مظنه (برای MELTED و MANUFACTURED)
    $('#market_price, #product_id').on('input change', function() {
        const $selected = $('#product_id').find('option:selected');
        const baseCategory = $selected.data('base-category');
        const marketPrice = $('#market_price').autoNumeric('get');
        if ((baseCategory === 'MELTED' || baseCategory === 'MANUFACTURED') && marketPrice > 0) {
            const unitPrice = Math.round(marketPrice / 4.3318);
            $('#average_purchase_price_per_unit').autoNumeric('set', unitPrice);
            calculateTotalValue();
        }
    });

    // محاسبه وزن 750
    $('#scale_weight, #carat').on('input', function() {
        const scaleWeight = $('#scale_weight').autoNumeric('get');
        const carat = parseFloat($('#carat').val() || 0);
        if (scaleWeight > 0 && carat > 0) {
            const weight750 = scaleWeight * (carat / 24);
            $('#weight_750').autoNumeric('set', weight750);
        }
        calculateTotalValue();
    });

    // محاسبه ارزش کل
    $('#quantity, #weight_750, #average_purchase_price_per_unit').on('input', function() {
        calculateTotalValue();
    });

    $('#target_capital').on('input', function() {
        calculateBalanceStatus();
    });

    function calculateTotalValue() {
        const $selected = $('#product_id').find('option:selected');
        const productType = $selected.data('product-type');
        const price = $('#average_purchase_price_per_unit').autoNumeric('get');
        let total = 0;
        if (productType === 'coin') {
            const quantity = $('#quantity').autoNumeric('get');
            total = quantity * price;
        } else {
            const weight750 = $('#weight_750').autoNumeric('get');
            total = weight750 * price;
        }
        $('#total_purchase_value').autoNumeric('set', total);
        calculateBalanceStatus();
    }

    function calculateBalanceStatus() {
        const totalValue = $('#total_purchase_value').autoNumeric('get');
        const targetCapital = $('#target_capital').autoNumeric('get');
        if (targetCapital > 0) {
            const percentage = (totalValue / targetCapital) * 100;
            $('#balance_percentage').val(percentage.toFixed(2) + '%');
            let status = '';
            if (percentage < 95) status = 'کمبود موجودی';
            else if (percentage > 105) status = 'مازاد موجودی';
            else status = 'نرمال';
            $('#balance_status').val(status);
        } else {
            $('#balance_percentage').val('');
            $('#balance_status').val('');
        }
    }

    // پاک کردن مقادیر
    function clearCalculations() {
        const fieldsToReset = [
            '#quantity', '#weight_grams', '#scale_weight', '#weight_750',
            '#average_purchase_price_per_unit', '#total_purchase_value',
            '#target_capital', '#balance_percentage', '#balance_status'
        ];
        fieldsToReset.forEach(function(field) {
            if ($(field).hasClass('autonumeric')) {
                $(field).autoNumeric('set', 0);
            } else {
                $(field).val('');
            }
        });
    }

    // اعتبارسنجی فرم (Bootstrap)
    (function() {
        'use strict';
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
});
</script> 