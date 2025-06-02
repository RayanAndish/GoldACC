/**
 * transaction-form.js - ماژول اصلی فرم تراکنش (افزودن و ویرایش)
 * نسخه: 3.0.0
 * تاریخ: 1404/04/12
 *
 * این فایل شامل ماژول‌های مختلف برای مدیریت فرم تراکنش است.
 * هر ماژول مسئولیت خاصی دارد و به صورت مستقل عمل می‌کند.
 */

// کلاس ساده برای لاگ کردن به کنسول
class ConsoleLogger {
    log(level, message, ...context) {
        // اگر window.phpConfig.app.debug تعریف نشده یا false باشد، لاگ‌های debug را نمایش نده
        if (level === 'debug' && (!window.phpConfig || !window.phpConfig.app || !window.phpConfig.app.debug)) return;
        console.log(`[${level.toUpperCase()}] ${message}`, ...context);
    }
}

// ماژول مدیریت فیلدها
const FieldManager = (function() {
    let _fields = [];
    let _initialized = false;
    let _logger;

    function _init(fields, logger) {
        if (_initialized) return;
        _fields = fields || [];
        _logger = logger;
        _initialized = true;
        _logger.log('info', 'FieldManager initialized with', _fields.length, 'fields');
    }

    function _getAllFields() {
        return _fields;
    }

    function _getAllProductGroups() {
        const groups = new Set();
        _fields.forEach(field => {
            if (field.group) {
                groups.add(field.group.toString().trim().toLowerCase());
            }
        });
        if (groups.size === 0) {
            // گروه‌های پیش‌فرض اگر از fields.json چیزی نیامد
            return ['melted', 'manufactured', 'coin', 'jewelry', 'goldbullion', 'silverbullion'];
        }
        return Array.from(groups);
    }

    function _getFieldsByGroup(group) {
        if (!group) return [];
        const groupLower = group.toLowerCase();
        return _fields.filter(field => {
            return field.group && field.group.toLowerCase() === groupLower;
        });
    }

    /**
     * تولید HTML فیلدهای یک گروه برای یک ردیف آیتم.
     * این متد فقط برای رندر کردن ردیف‌های *جدید* یا *بازسازی* ردیف موجود پس از تغییر محصول استفاده می‌شود.
     * @param {string} group - نام گروه محصول (مثلاً 'melted').
     * @param {number} index - شاخص ردیف آیتم.
     * @returns {string} - HTML فیلدها.
     */
    function _getFieldsHtmlByGroup(group, index) {
        if (!group) return '';

        const fields = _getFieldsByGroup(group);
        let html = '';

        // گروه‌بندی فیلدها بر اساس row_display
        const rows = {};
        fields.forEach(field => {
            const rowDisplay = field.row_display || 'row1'; // پیش‌فرض row1
            if (!rows[rowDisplay]) {
                rows[rowDisplay] = [];
            }
            rows[rowDisplay].push(field);
        });

        // رندر کردن هر ردیف
        Object.keys(rows).sort().forEach(rowKey => {
            html += `<div class="row g-2 mt-1">`; // ردیف جدید برای فیلدها
            rows[rowKey].forEach(field => {
                const fieldName = field.name || '';
                const fieldLabel = field.label || '';
                const fieldType = field.type || 'text';
                const isRequired = field.required || false;
                const colClass = field.col_class || 'col-md-2'; // پیش‌فرض
                const isReadonly = field.readonly || false;
                const step = field.step || null;
                const min = field.min || null;
                const max = field.max || null;

                html += `
                    <div class="${colClass}">
                        <label class="form-label">${fieldLabel}${isRequired ? ' <span class="text-danger">*</span>' : ''}</label>
                `;

                if (fieldType === 'select') {
                    html += `
                        <select name="items[${index}][${fieldName}]" class="form-select form-select-sm${isReadonly ? ' readonly' : ''}"${isRequired ? ' required' : ''}${isReadonly ? ' readonly' : ''}>
                            <option value="">انتخاب کنید...</option>
                    `;
                    if (field.options && Array.isArray(field.options)) {
                        field.options.forEach(option => {
                            html += `<option value="${option.value}">${option.label}</option>`;
                        });
                    }
                    html += `
                        </select>
                    `;
                } else if (fieldType === 'textarea') {
                    html += `
                        <textarea name="items[${index}][${fieldName}]" class="form-control form-control-sm${isReadonly ? ' readonly' : ''}"${isRequired ? ' required' : ''}${isReadonly ? ' readonly' : ''}></textarea>
                    `;
                } else {
                    const inputClasses = ['form-control', 'form-control-sm'];
                    if (field.is_numeric) inputClasses.push('autonumeric');
                    if (isReadonly) inputClasses.push('readonly');

                    html += `
                        <input
                            type="${fieldType === 'number' ? 'text' : fieldType}"
                            name="items[${index}][${fieldName}]"
                            class="${inputClasses.join(' ')}"
                            ${isRequired ? 'required' : ''}
                            ${isReadonly ? 'readonly' : ''}
                            ${step ? `step="${step}"` : ''}
                            ${min !== null ? `min="${min}"` : ''}
                            ${max !== null ? `max="${max}"` : ''}
                        >
                    `;
                }

                html += `
                        <div class="invalid-feedback">لطفا ${fieldLabel} را وارد کنید.</div>
                    </div>
                `;
            });
            html += `</div>`; // بستن ردیف
        });

        // اضافه کردن فیلد توضیحات این ردیف (مشترک برای همه گروه‌ها)
        const descriptionField = _fields.find(f => f.name === 'item_description' && f.section === 'item_row' && f.group === 'همه');
        if (descriptionField) {
            html += `<div class="row g-2 mt-1">
                        <div class="${descriptionField.col_class || 'col-12'}">
                            <label class="form-label">${descriptionField.label}</label>
                            <textarea name="items[${index}][${descriptionField.name}]" class="form-control form-control-sm"></textarea>
                        </div>
                    </div>`;
        }

        return html;
    }

    return {
        init: _init,
        getAllFields: _getAllFields,
        getAllProductGroups: _getAllProductGroups,
        getFieldsByGroup: _getFieldsByGroup,
        getFieldsHtmlByGroup: _getFieldsHtmlByGroup
    };
})();

// ماژول مدیریت فرمول‌ها
const FormulaManager = (function() {
    let _formulas = [];
    let _initialized = false;
    let _logger; // برای لاگ کردن

    function _init(formulas, logger) {
        if (_initialized) return;
        _formulas = formulas || [];
        _logger = logger;
        _initialized = true;
        _logger.log('info', 'FormulaManager initialized with', _formulas.length, 'formulas');
    }

    function _getFormulas() {
        return _formulas;
    }

    /**
     * دریافت تمام فرمول‌های مربوط به یک گروه خاص.
     * @param {string} group - نام گروه (مثلاً 'melted', 'manufactured').
     * @returns {Array} آرایه‌ای از فرمول‌های مربوط به گروه.
     */
    function _getFormulasByGroup(group) {
        if (!group) {
            return [];
        }
        const groupLower = group.toLowerCase();
        return _formulas.filter(formula => {
            return formula.group && formula.group.toLowerCase() === groupLower;
        });
    }

    /**
     * محاسبه یک فرمول با مقادیر ورودی.
     * این متد باید از eval() یا new Function() اجتناب کند و از یک Parser/Evaluator امن استفاده کند.
     * فعلاً یک پیاده‌سازی ساده و ناامن (برای نشان دادن منطق) ارائه می‌شود.
     *
     * @param {string} formulaName - نام فرمول.
     * @param {Object} inputValues - مقادیر ورودی.
     * @returns {number|null} - نتیجه محاسبه یا null.
     */
    function _calculate(formulaName, inputValues) {
        const formula = _formulas.find(f => f.name === formulaName);
        if (!formula) {
            _logger.log('warn', `Formula not found: ${formulaName}`);
            return null;
        }
        let expression = formula.formula;
        const requiredFields = formula.fields || [];

        // اطمینان از وجود تمام فیلدهای مورد نیاز در inputValues
        requiredFields.forEach(field => {
            if (!inputValues.hasOwnProperty(field)) {
                _logger.log('warn', `Required field missing for formula ${formulaName}: ${field}. Setting to 0.`);
                inputValues[field] = 0; // مقداردهی پیش‌فرض برای جلوگیری از خطا
            }
        });

        // جایگزینی متغیرها در عبارت. مقادیر باید عددی باشند.
        for (const [key, value] of Object.entries(inputValues)) {
            const regex = new RegExp(`\\b${key}\\b`, 'g');
            // اطمینان از اینکه مقدار یک عدد معتبر است
            const safeValue = isNaN(value) ? 0 : parseFloat(value); // parseFloat برای اطمینان از عدد بودن
            expression = expression.replace(regex, safeValue);
        }

        // --- پیاده‌سازی ساده و ناامن eval ---
        // این بخش باید با یک Parser/Evaluator امن جایگزین شود.
        let result;
        try {
            // ارزیابی عبارات شرطی (ternary operator)
            if (expression.includes('?') && expression.includes(':')) {
                // مثال: (product_tax_enabled && product_tax_rate > 0) ? item_total_price * product_tax_rate / 100 : 0
                // این یک پیاده‌سازی بسیار ساده است و ممکن است برای همه موارد کار نکند.
                // برای راه‌حل قوی‌تر، نیاز به یک AST parser واقعی است.
                const parts = expression.split('?');
                if (parts.length === 2) {
                    const condition = parts[0].trim();
                    const trueFalseParts = parts[1].split(':');
                    if (trueFalseParts.length === 2) {
                        const truePart = trueFalseParts[0].trim();
                        const falsePart = trueFalseParts[1].trim();
                        
                        // ارزیابی شرط
                        let conditionResult;
                        try {
                            // استفاده از Function برای ارزیابی شرط
                            conditionResult = (new Function(`return ${condition};`))();
                        } catch (e) {
                            _logger.log('error', `Error evaluating condition "${condition}" for formula ${formulaName}: ${e.message}`);
                            conditionResult = false; // در صورت خطا، شرط را false در نظر می‌گیریم
                        }
                        expression = conditionResult ? truePart : falsePart;
                    }
                }
            }

            // پاکسازی نهایی برای اطمینان از اینکه فقط اعداد و عملگرهای ریاضی باقی مانده‌اند
            // این Regex باید با دقت بیشتری نوشته شود تا فقط کاراکترهای مجاز را عبور دهد.
            // مثلاً اگر توابع ریاضی مثل Math.floor هم مجاز هستند.
            if (/[^0-9+\-*\/().\s]/.test(expression)) { // حذف کاراکترهای غیرمجاز
                _logger.log('error', `Unsafe characters detected in expression after substitution for formula ${formulaName}: ${expression}`);
                return null;
            }

            result = (new Function(`return ${expression};`))(); // استفاده از Function به جای eval برای ایزوله‌سازی بهتر

            if (isNaN(result) || !isFinite(result)) {
                _logger.log('error', `Formula ${formulaName} resulted in invalid value: ${result}. Expression: ${expression}`);
                return null;
            }

            // گرد کردن نتیجه بر اساس نوع فرمول
            let roundedResult = parseFloat(result);
            if (formula.type === 'price' || formula.type === 'amount') {
                roundedResult = Math.round(result);
            } else if (formula.type === 'weight') {
                roundedResult = parseFloat(result.toFixed(4)); // 4 رقم اعشار برای وزن
            } else if (formula.type === 'percent') {
                roundedResult = parseFloat(result.toFixed(2)); // 2 رقم اعشار برای درصد
            }
            
            _logger.log('debug', `Formula ${formulaName} calculated:`, {expression: expression, result: result, roundedResult: roundedResult});
            return roundedResult;

        } catch (e) {
            _logger.log('error', `Error calculating formula ${formulaName}: ${e.message}. Expression: ${expression}`);
            return null;
        }
    }

    /**
     * محاسبه خلاصه تراکنش (جمع کل آیتم‌ها، سود، مالیات، ارزش افزوده).
     *
     * @param {array} items - آرایه‌ای از اقلام معامله (هر آیتم یک آرایه از داده‌ها).
     * @param {array} transactionData - داده‌های اصلی تراکنش.
     * @param {array} productsById - آرایه‌ای از آبجکت‌های Product با کلید product_id.
     * @param {array} defaults - مقادیر پیش‌فرض.
     * @param {array} taxSettings - تنظیمات مالیات و ارزش افزوده (اختیاری).
     * @returns {array} خلاصه‌های محاسبه شده.
     */
    function _calculateTransactionSummary(items, transactionData, productsById, defaults, taxSettings) {
        const summary = {
            total_items_value_rials: 0,
            total_profit_wage_commission_rials: 0,
            total_general_tax_rials: 0,
            total_before_vat_rials: 0,
            total_vat_rials: 0,
            final_payable_amount_rials: 0
        };

        // دریافت نرخ‌های مالیات/ارزش افزوده از تنظیمات یا مقادیر پیش‌فرض
        const globalTaxRate = parseFloat(taxSettings.tax_rate || 0);
        const globalVatRate = parseFloat(taxSettings.vat_rate || 0);

        // جمع‌آوری مقادیر از تمام آیتم‌های معامله
        items.forEach(item => {
            const productId = item.product_id;
            const product = productsById[productId];

            if (!product) {
                _logger.log('warn', "Product not found for item in summary calculation. Skipping item.", {item_data: item});
                return;
            }

            const productGroup = ProductManager.getProductGroup(product);

            // مقادیر اصلی آیتم
            const itemTotalPrice = parseFloat(item.total_value_rials || 0);
            const itemProfitAmount = parseFloat(item.profit_amount || 0);
            const itemFeeAmount = parseFloat(item.fee_amount || 0);
            const itemManufacturingFeeAmount = parseFloat(item.ajrat_rials || 0); // ajrat_rials در TransactionItem

            // محاسبه مالیات عمومی و ارزش افزوده برای هر آیتم
            let itemGeneralTax = 0;
            let itemVat = 0;

            // استفاده از نرخ‌های مالیات/ارزش افزوده محصول (اگر فعال باشد) یا نرخ‌های عمومی
            const productTaxEnabled = (product.tax_enabled === true || product.tax.enabled === 1);
            const productTaxRate = parseFloat(product.tax_rate || globalTaxRate);
            const productVatEnabled = (product.vat_enabled === true || product.vat.enabled === 1);
            const productVatRate = parseFloat(product.vat_rate || globalVatRate);

            // محاسبه مالیات عمومی
            if (productTaxEnabled && productTaxRate > 0) {
                itemGeneralTax = itemTotalPrice * productTaxRate / 100;
            }

            // محاسبه ارزش افزوده
            if (productVatEnabled && productVatRate > 0) {
                let baseForVat = 0;
                if (['melted', 'coin', 'goldbullion', 'silverbullion', 'jewelry'].includes(productGroup)) {
                    baseForVat = itemProfitAmount + itemFeeAmount;
                } else if (productGroup === 'manufactured') {
                    baseForVat = itemManufacturingFeeAmount + itemProfitAmount + itemFeeAmount;
                }
                itemVat = baseForVat * productVatRate / 100;
            }

            // جمع‌آوری به خلاصه‌های کلی
            summary.total_items_value_rials += itemTotalPrice;
            summary.total_profit_wage_commission_rials += (itemProfitAmount + itemFeeAmount + itemManufacturingFeeAmount);
            summary.total_general_tax_rials += itemGeneralTax;
            summary.total_vat_rials += itemVat;
        });

        summary.total_before_vat_rials = summary.total_items_value_rials + summary.total_profit_wage_commission_rials + summary.total_general_tax_rials;
        summary.final_payable_amount_rials = summary.total_before_vat_rials + summary.total_vat_rials;

        // گرد کردن نهایی
        summary.total_items_value_rials = Math.round(summary.total_items_value_rials);
        summary.total_profit_wage_commission_rials = Math.round(summary.total_profit_wage_commission_rials);
        summary.total_general_tax_rials = Math.round(summary.total_general_tax_rials);
        summary.total_before_vat_rials = Math.round(summary.total_before_vat_rials);
        summary.total_vat_rials = Math.round(summary.total_vat_rials);
        summary.final_payable_amount_rials = Math.round(summary.final_payable_amount_rials);

        return summary;
    }

    return {
        init: _init,
        getFormulas: _getFormulas,
        getFormulasByGroup: _getFormulasByGroup,
        calculate: _calculate,
        calculateFormulasForRow: _calculateFormulasForRow,
        calculateTransactionSummary: _calculateTransactionSummary
    };
})();

// ماژول مدیریت محصولات
const ProductManager = (function() {
    let _products = [];
    let _initialized = false;
    let _logger;

    // نگاشت ID دسته‌بندی به گروه پایه (این باید از دیتابیس یا یک فایل پیکربندی داینامیک بیاید)
    const _categoryIdToBaseCategory = {
        20: 'melted', // آبشده
        21: 'coin', // سکه
        22: 'manufactured', // ساخته شده
        23: 'goldbullion', // شمش طلا
        27: 'jewelry', // جواهر
        28: 'silverbullion' // شمش نقره
    };

    function _init(products, logger) {
        if (_initialized) return;
        _products = products || [];
        _logger = logger;
        _initialized = true;
        _logger.log('info', 'ProductManager initialized with', _products.length, 'products');
    }

    function _getAllProducts() {
        return _products;
    }

    function _getProductById(productId) {
        if (!productId) return null;
        const id = parseInt(productId, 10);
        return _products.find(product => product.id === id) || null;
    }

    function _getCategoryGroup(categoryId) {
        if (!categoryId) return 'unknown';
        const id = parseInt(categoryId, 10);
        return _categoryIdToBaseCategory[id] || 'unknown';
    }

    function _getProductGroup(product) {
        if (!product) return 'unknown';
        if (product.category && product.category.id) {
            return _getCategoryGroup(product.category.id);
        }
        if (product.category_id) {
            return _getCategoryGroup(product.category_id);
        }
        // Fallback to product_category_base if available (from joined data)
        if (product.product_category_base) {
            return product.product_category_base.toLowerCase();
        }
        // اگر product object از نوع App\Models\Product باشد، category آن یک آبجکت ProductCategory است
        if (product.category && product.category.base_category) {
            return product.category.base_category.toLowerCase();
        }
        return 'unknown';
    }

    function _getGroupedProducts() {
        const grouped = {};
        _products.forEach(product => {
            const group = _getProductGroup(product);
            if (!grouped[group]) {
                grouped[group] = [];
            }
            grouped[group].push(product);
        });
        return grouped;
    }

    /**
     * پر کردن یک المان select با محصولات.
     * @param {HTMLElement} selectElement - المان select.
     * @param {number|null} selectedId - شناسه محصول انتخاب شده.
     */
    function _fillProductSelect(selectElement, selectedId = null) {
        if (!selectElement) return;

        // حذف گزینه‌های موجود به جز گزینه پیش‌فرض (اولین گزینه)
        while (selectElement.options.length > 1) {
            selectElement.remove(1);
        }

        const groupedProducts = _getGroupedProducts();

        for (const [group, products] of Object.entries(groupedProducts)) {
            if (products.length === 0) continue;

            const optgroup = document.createElement('optgroup');
            optgroup.label = _getGroupDisplayName(group);

            products.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = product.name;
                option.dataset.categoryId = product.category_id || '';
                option.dataset.group = group;

                // اضافه کردن ویژگی‌های اضافی به گزینه برای استفاده در JS
                if (product.default_carat) option.dataset.defaultCarat = product.default_carat;
                if (product.unit_of_measure) option.dataset.unitOfMeasure = product.unit_of_measure;
                if (product.tax_enabled !== null) option.dataset.taxEnabled = product.tax_enabled ? '1' : '0';
                if (product.tax_rate !== null) option.dataset.taxRate = product.tax_rate;
                if (product.vat_enabled !== null) option.dataset.vatEnabled = product.vat_enabled ? '1' : '0';
                if (product.vat_rate !== null) option.dataset.vatRate = product.vat_rate;
                if (product.coin_year !== null) option.dataset.coinYear = product.coin_year;
                if (product.is_bank_coin !== null) option.dataset.isBankCoin = product.is_bank_coin ? '1' : '0';


                if (selectedId !== null && parseInt(product.id, 10) === parseInt(selectedId, 10)) {
                    option.selected = true;
                }
                optgroup.appendChild(option);
            });
            selectElement.appendChild(optgroup);
        }
    }

    function _getGroupDisplayName(group) {
        const displayNames = {
            'melted': 'طلای آبشده',
            'manufactured': 'مصنوعات طلا',
            'coin': 'سکه',
            'jewelry': 'جواهرات',
            'goldbullion': 'شمش طلا',
            'silverbullion': 'شمش نقره',
            'unknown': 'سایر'
        };
        return displayNames[group] || group;
    }

    return {
        init: _init,
        getAllProducts: _getAllProducts,
        getProductById: _getProductById,
        getCategoryGroup: _getCategoryGroup,
        getProductGroup: _getProductGroup,
        getGroupedProducts: _getGroupedProducts,
        fillProductSelect: _fillProductSelect,
        getGroupDisplayName: _getGroupDisplayName
    };
})();

// ماژول مدیریت رابط کاربری
const UIManager = (function() {
    let _elements = {};
    let _initialized = false;
    let _itemIndex = 0; // شاخص برای ردیف‌های جدید
    let _logger;

    function _init(elements, logger) {
        if (_initialized) return;
        _elements = elements || {};
        _logger = logger;
        _initialized = true;
        _logger.log('info', 'UIManager initialized');
    }

    /**
     * نمایش پیام به کاربر با استفاده از Toastify یا alert.
     * @param {string} type - نوع پیام (success, error, warning, info).
     * @param {string} text - متن پیام یا کلید پیام از window.MESSAGES.
     */
    function _showMessage(type, text) {
        // بررسی آیا پیام یک کلید از فایل messages.js است
        if (window.MESSAGES && typeof text === 'string' && window.MESSAGES[text]) {
            text = window.MESSAGES[text];
        }
        
        if (typeof Toastify !== 'undefined') {
            Toastify({
                text: text,
                duration: 5000,
                close: true,
                gravity: "top",
                position: "center",
                className: "toast-" + type,
                stopOnFocus: true
            }).showToast();
        } else {
            // Fallback به console.log به جای alert در محیط Canvas
            console.log(`[${type.toUpperCase()}] ${text}`);
            // alert(text); // در محیط واقعی مرورگر
        }
    }

    /**
     * رندر کردن یک ردیف آیتم جدید یا موجود.
     * @param {Object|null} itemData - داده‌های ردیف (اختیاری، برای ویرایش).
     * @param {number} index - شاخص ردیف.
     * @param {Array} assayOffices - آرایه مراکز ری‌گیری.
     */
    function _renderItemRow(itemData, index, assayOffices) {
        if (!_elements.itemsContainer || !_elements.itemRowTemplate) {
            _logger.log('error', 'Critical DOM elements for item row rendering are missing.');
            return;
        }
        
        _logger.log('info', `Rendering item row ${index}${itemData ? ' with existing data' : ' (empty)'}.`);

        // ایجاد ردیف جدید از template
        const newRowContainer = document.createElement('div');
        newRowContainer.innerHTML = _elements.itemRowTemplate.replace(/{index}/g, index);
        const rowElement = newRowContainer.firstElementChild; // عنصر اصلی ردیف

        // افزودن ردیف به کانتینر
        _elements.itemsContainer.appendChild(rowElement);

        // پر کردن select محصولات
        const productSelect = rowElement.querySelector('.product-select');
        if (productSelect) {
            ProductManager.fillProductSelect(productSelect, itemData ? itemData.product_id : null);
        }

        // اتصال رویدادها به ردیف
        _bindRowEvents(rowElement, index);

        // اگر داده‌های ردیف موجود باشد، فیلدها را پر کن
        if (itemData) {
            _logger.log('debug', `Populating row ${index} with data:`, itemData);
            
            // ابتدا فیلدهای داینامیک را بر اساس محصول انتخاب شده رندر می‌کنیم
            const product = ProductManager.getProductById(itemData.product_id);
            if (product) {
                // _updateItemFields را با initialItemData فراخوانی می‌کنیم تا فیلدها رندر و پر شوند
                _updateItemFields(rowElement, product, index, itemData); 

                // تریگر کردن رویداد change روی productSelect برای رندر صحیح فیلدهای داینامیک و محاسبات اولیه
                // این کار باعث می‌شود که _updateItemFields و _bindCalculationEvents فراخوانی شوند.
                if (productSelect) {
                    productSelect.dispatchEvent(new Event('change'));
                }
            }
        }
        
        // افزایش شاخص برای ردیف بعدی
        _itemIndex = Math.max(_itemIndex, index + 1);
    }

    /**
     * افزودن یک ردیف خالی جدید.
     */
    function _addNewEmptyItemRow() {
        const assayOffices = TransactionFormApp.getData().assayOffices || [];
        _renderItemRow(null, _itemIndex, assayOffices);
        _itemIndex++; // افزایش شاخص برای ردیف بعدی
    }

    /**
     * اتصال رویدادها به یک ردیف آیتم.
     * @param {HTMLElement} rowElement - المان ردیف.
     * @param {number} index - شاخص ردیف.
     */
    function _bindRowEvents(rowElement, index) {
        if (!rowElement) return;

        // رویداد تغییر محصول
        const productSelect = rowElement.querySelector('.product-select');
        if (productSelect) {
            productSelect.addEventListener('change', function() {
                const productId = this.value;
                if (!productId) {
                    // پاک کردن فیلدهای داینامیک اگر محصول انتخاب نشده باشد
                    const dynamicFieldsRow = rowElement.querySelector('.dynamic-fields-row');
                    if (dynamicFieldsRow) {
                        dynamicFieldsRow.innerHTML = '';
                    }
                    SummaryManager.updateSummaryFields(); // به‌روزرسانی خلاصه
                    return;
                }
                
                const product = ProductManager.getProductById(productId);
                if (product) {
                    // هنگام تغییر محصول، فیلدهای داینامیک را دوباره رندر و پر می‌کنیم
                    _updateItemFields(rowElement, product, index);
                    // سپس محاسبات را تریگر می‌کنیم
                    const group = ProductManager.getProductGroup(product);
                    const groupFormulas = FormulaManager.getFormulasByGroup(group);
                    FormulaManager.calculateFormulasForRow(rowElement, groupFormulas, index, product);
                }
            });
        }

        // رویداد حذف ردیف
        const removeButton = rowElement.querySelector('.remove-item-btn');
        if (removeButton) {
            removeButton.addEventListener('click', function() {
                rowElement.remove();
                SummaryManager.updateSummaryFields(); // به‌روزرسانی خلاصه پس از حذف
            });
        }
        
        // رویداد تغییر فیلدهای متعلقات مصنوعات
        const hasAttachmentsToggle = rowElement.querySelector(`[name="items[${index}][item_has_attachments_manufactured]"]`);
        if (hasAttachmentsToggle) {
            hasAttachmentsToggle.addEventListener('change', function() {
                _handleManufacturedAccessoriesDependency(rowElement, index);
            });
        }
    }

    /**
     * به‌روزرسانی فیلدهای ردیف بر اساس محصول انتخاب شده.
     * این شامل رندر فیلدهای داینامیک و پر کردن مقادیر پیش‌فرض است.
     * @param {HTMLElement} row - المان ردیف.
     * @param {Object} product - اطلاعات محصول.
     * @param {number} index - شاخص ردیف.
     * @param {Object|null} initialItemData - داده‌های اولیه آیتم (فقط برای حالت ویرایش).
     */
    function _updateItemFields(row, product, index, initialItemData = null) {
        if (!row || !product) return;

        _logger.log('debug', `Updating item fields for row ${index} with product ${product.id} (${product.name}).`);

        const productGroup = ProductManager.getProductGroup(product);
        _logger.log('debug', `Product group: ${productGroup}`);

        const dynamicFieldsRow = row.querySelector('.dynamic-fields-row');
        if (!dynamicFieldsRow) return;

        // رندر HTML فیلدها بر اساس گروه محصول از fields.json
        const fieldsHtml = FieldManager.getFieldsHtmlByGroup(productGroup, index);
        dynamicFieldsRow.innerHTML = fieldsHtml;

        // راه‌اندازی AutoNumeric برای فیلدهای عددی جدید
        _initializeAllAutonumerics(dynamicFieldsRow);

        // پر کردن فیلدهای از initialItemData (اگر موجود باشد) یا از Product object
        const setFieldValue = (fieldName, value) => {
            const fieldElement = row.querySelector(`[name="items[${index}][${fieldName}]"]`);
            if (fieldElement) {
                if (fieldElement.classList.contains('autonumeric') && typeof AutoNumeric !== 'undefined') {
                    AutoNumeric.getAutoNumericElement(fieldElement)?.set(value);
                } else {
                    fieldElement.value = value;
                }
            }
        };

        // اولویت با initialItemData (برای حالت ویرایش)
        const sourceData = initialItemData || product;

        // نگاشت فیلدهای دیتابیس/محصول به نام فیلدهای فرم
        const dbToFormMapping = {
            'quantity': `item_quantity_${productGroup}`,
            'weight_grams': `item_weight_scale_${productGroup}`,
            'carat': `item_carat_${productGroup}`,
            'unit_price_rials': `item_unit_price_${productGroup}`,
            'total_value_rials': `item_total_price_${productGroup}`,
            'tag_number': `item_tag_number_${productGroup}`,
            'assay_office_id': `item_assay_office_melted`, // فقط برای melted
            'coin_year': `item_coin_year_${productGroup}`,
            'seal_name': `item_vacuum_name_${productGroup}`,
            'is_bank_coin': `item_type_coin`, // برای coin: 'bank'/'misc'
            'ajrat_rials': `item_manufacturing_fee_amount_manufactured`, // برای manufactured
            'workshop_name': `item_workshop_${productGroup}`,
            'stone_weight_grams': `item_attachment_weight_manufactured`, // برای manufactured
            'description': `item_description`, // فیلد توضیحات عمومی آیتم
            'profit_percent': `item_profit_percent_${productGroup}`,
            'profit_amount': `item_profit_amount_${productGroup}`,
            'fee_percent': `item_fee_percent_${productGroup}`,
            'fee_amount': `item_fee_amount_${productGroup}`,
            'item_type_manufactured': `item_type_manufactured`, // نوع مصنوعات
            'item_tag_type_melted': `item_tag_type_melted`, // نوع انگ آبشده
        };

        for (const dbField in dbToFormMapping) {
            const formField = dbToFormMapping[dbField];
            if (sourceData.hasOwnProperty(dbField) && sourceData[dbField] !== null) {
                let valueToSet = sourceData[dbField];

                // تبدیل مقادیر خاص برای select ها
                if (dbField === 'is_bank_coin' && productGroup === 'coin') {
                    valueToSet = valueToSet == 1 ? 'bank' : 'misc';
                }
                setFieldValue(formField, valueToSet);
            }
        }
        
        // مدیریت فیلد has_attachments_manufactured
        if (productGroup === 'manufactured') {
            const hasAttachmentsField = row.querySelector(`[name="items[${index}][item_has_attachments_manufactured]"]`);
            if (hasAttachmentsField) {
                if (initialItemData && (initialItemData.stone_weight_grams > 0 || (initialItemData.description && (initialItemData.description.includes('سنگ') || initialItemData.description.includes('جواهر'))))) {
                    hasAttachmentsField.value = 'yes';
                } else {
                    hasAttachmentsField.value = 'no';
                }
                hasAttachmentsField.dispatchEvent(new Event('change')); // تریگر کردن برای نمایش/پنهان کردن فیلدهای وابسته
            }
        }

        // پر کردن select مراکز ری‌گیری (فقط برای آبشده)
        if (productGroup === 'melted') {
            _fillAssayOfficeSelects(TransactionFormApp.getData().assayOffices, row, true);
            // انتخاب مقدار assay_office_id اگر در itemData موجود باشد
            if (initialItemData && initialItemData.assay_office_id) {
                setFieldValue(`item_assay_office_melted`, initialItemData.assay_office_id);
            }
        }
        
        // اتصال رویدادهای محاسبه به فیلدهای جدید
        _bindCalculationEvents(row, productGroup, index);
    }

    /**
     * اتصال رویدادهای محاسبه به فیلدها.
     * @param {HTMLElement} container - المان حاوی فیلدها.
     * @param {string} group - نام گروه.
     * @param {number} index - شاخص ردیف.
     */
    function _bindCalculationEvents(container, group, index) {
        if (!container || !group) return;

        const groupFormulas = FormulaManager.getFormulasByGroup(group);
        if (!groupFormulas || groupFormulas.length === 0) return;

        // یافتن تمام فیلدهای ورودی مورد نیاز برای این گروه از فرمول‌ها
        const requiredFields = new Set();
        groupFormulas.forEach(formula => {
            (formula.fields || []).forEach(field => {
                requiredFields.add(field);
            });
        });

        // اتصال رویداد change و input به فیلدهای ورودی
        requiredFields.forEach(fieldName => {
            // نام فیلد در فرم ممکن است شامل پیشوند گروه باشد
            const fieldElement = container.querySelector(`[name="items[${index}][${fieldName}]"]`);
            if (fieldElement) {
                const handler = function(event) {
                    if (this.dataset.valueUpdated === 'true') {
                        this.dataset.valueUpdated = 'false';
                        return;
                    }
                    const productSelect = container.querySelector('.product-select');
                    const productId = productSelect ? productSelect.value : null;
                    const product = ProductManager.getProductById(productId);
                    FormulaManager.calculateFormulasForRow(container, groupFormulas, index, product);
                };

                fieldElement.removeEventListener('change', handler); // جلوگیری از تکرار شنونده
                fieldElement.addEventListener('change', handler);
                if (fieldElement.type === 'number' || fieldElement.classList.contains('autonumeric')) {
                    fieldElement.removeEventListener('input', handler); // جلوگیری از تکرار شنونده
                    fieldElement.addEventListener('input', handler);
                }
            }
        });
    }

    /**
     * راه‌اندازی AutoNumeric برای یک المان.
     * @param {HTMLElement} element - المان.
     */
    function _initializeAutonumeric(element) {
        if (!element || typeof AutoNumeric === 'undefined') return;

        if (element.classList.contains('autonumeric')) {
            // اگر قبلاً AutoNumeric برای این المان راه‌اندازی شده، آن را حذف می‌کنیم
            let existingAN = AutoNumeric.getAutoNumericElement(element);
            if (existingAN) {
                existingAN.remove();
            }

            let options = {
                digitGroupSeparator: '٬',
                decimalCharacter: '.',
                decimalPlaces: 0,
                unformatOnSubmit: true,
                selectOnFocus: false,
                modifyValueOnWheel: false
            };

            // تنظیم تعداد ارقام اعشار بر اساس نام فیلد
            const fieldName = element.name || '';
            if (fieldName.includes('weight') || fieldName.includes('carat')) {
                options.decimalPlaces = 4;
            } else if (fieldName.includes('percent')) {
                options.decimalPlaces = 2;
            } else if (fieldName.includes('price') || fieldName.includes('amount')) {
                options.decimalPlaces = 0; // برای مبالغ ریالی
            }

            // تنظیم ویژه برای فیلد مظنه
            if (element.id === 'mazaneh_price') {
                options.decimalPlaces = 0;
            }

            try {
                new AutoNumeric(element, options);
            } catch (e) {
                _logger.log('error', `Error initializing AutoNumeric for element: ${element.name}`, e);
            }
        }
    }

    /**
     * راه‌اندازی AutoNumeric برای تمام المان‌های یک کانتینر.
     * @param {HTMLElement} context - المان کانتینر.
     */
    function _initializeAllAutonumerics(context = document) {
        if (typeof AutoNumeric === 'undefined') return;
        const elements = context.querySelectorAll('.autonumeric');
        elements.forEach(_initializeAutonumeric);
    }

    /**
     * این تابع یکتا مسئول پر کردن تمام فیلدهای select مراکز ری‌گیری در صفحه است.
     * @param {Array} offices - آرایه مراکز ری‌گیری.
     * @param {HTMLElement|null} container - المان حاوی فیلدها (اگر null باشد، کل صفحه بررسی می‌شود).
     * @param {boolean} onlyMeltedGroup - فقط فیلدهای مربوط به گروه آبشده را پر کند.
     */
    function _fillAssayOfficeSelects(offices, container = null, onlyMeltedGroup = false) {
        if (!Array.isArray(offices) || offices.length === 0) {
            _showMessage('warn', window.MESSAGES?.invalid_assay_offices_data || 'داده‌های مراکز ری‌گیری نامعتبر یا خالی است');
            offices = [{ id: 0, name: 'پیش‌فرض' }];
        }

        const searchContext = container || document;
        const selector = onlyMeltedGroup ? 'select[name*="item_assay_office_melted"]' : 'select[name*="item_assay_office"], select.assay-office-select';
        const selects = searchContext.querySelectorAll(selector);
        
        selects.forEach(select => {
            const currentValue = select.value;
            while (select.options.length > 0) {
                select.remove(0);
            }
            const defaultOption = document.createElement('option');
            defaultOption.value = '0';
            defaultOption.textContent = window.MESSAGES?.assay_office_select_default || 'انتخاب مرکز ری‌گیری...';
            select.appendChild(defaultOption);
            
            offices.forEach(office => {
                const option = document.createElement('option');
                option.value = office.id;
                option.textContent = office.name;
                if (currentValue && currentValue == office.id) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        });
    }

    /**
     * مدیریت فیلدهای متعلقات برای محصولات ساخته شده.
     * @param {HTMLElement} rowElement - المان ردیف آیتم.
     * @param {number} index - شاخص ردیف.
     */
    function _handleManufacturedAccessoriesDependency(rowElement, index) {
        const get = (name) => rowElement.querySelector(`[name="items[${index}][${name}]"]`);
        const hasAttachments = get('item_has_attachments_manufactured');
        const attachmentType = get('item_attachment_type_manufactured');
        const attachmentWeight = get('item_attachment_weight_manufactured');
    
        if (!hasAttachments || !attachmentType || !attachmentWeight) return;
    
        function toggleState() {
            const disabled = hasAttachments.value === 'no';
            // یافتن div والد برای پنهان/نمایش
            const typeCol = attachmentType.closest('[class*="col-"]');
            const weightCol = attachmentWeight.closest('[class*="col-"]');

            if (typeCol) typeCol.style.display = disabled ? 'none' : '';
            if (weightCol) weightCol.style.display = disabled ? 'none' : '';

            attachmentType.disabled = disabled;
            attachmentWeight.disabled = disabled;
    
            if (disabled) {
                attachmentType.removeAttribute('required');
                attachmentWeight.removeAttribute('required');
                attachmentType.classList.remove('is-invalid');
                attachmentWeight.classList.remove('is-invalid');
            } else {
                attachmentType.setAttribute('required', 'required');
                attachmentWeight.setAttribute('required', 'required');
            }
        }
    
        // رویداد change قبلاً در _bindRowEvents اضافه شده است.
        toggleState(); // اجرای اولیه
    }

    return {
        init: _init,
        showMessage: _showMessage,
        renderItemRow: _renderItemRow,
        addNewEmptyItemRow: _addNewEmptyItemRow,
        bindRowEvents: _bindRowEvents,
        updateItemFields: _updateItemFields,
        bindCalculationEvents: _bindCalculationEvents,
        initializeAutonumeric: _initializeAutonumeric,
        initializeAllAutonumerics: _initializeAllAutonumerics,
        fillAssayOfficeSelects: _fillAssayOfficeSelects,
        handleManufacturedAccessoriesDependency: _handleManufacturedAccessoriesDependency // برای استفاده مستقیم
    };
})();

// ماژول مدیریت خلاصه مالی
const SummaryManager = (function() {
    let _initialized = false;
    let _logger;

    function _init(logger) {
        if (_initialized) return;
        _logger = logger;
        _initialized = true;
        _logger.log('info', 'SummaryManager initialized');
    }

    function _updateSummaryFields() {
        const summaryContainer = document.querySelector('.card.mt-4');
        if (summaryContainer && summaryContainer.dataset.updating === 'true') {
            _logger.log('debug', 'Summary update already in progress');
            return;
        }
        if (summaryContainer) {
            summaryContainer.dataset.updating = 'true';
        }

        try {
            const itemsForSummary = [];
            document.querySelectorAll('.transaction-item-row').forEach(rowElement => {
                const productSelect = rowElement.querySelector('.product-select');
                if (!productSelect || !productSelect.value) return;

                const productId = productSelect.value;
                const product = ProductManager.getProductById(productId);
                if (!product) return;

                const group = ProductManager.getProductGroup(product);
                const indexMatch = productSelect.name.match(/items\[(\d+)\]/);
                const index = indexMatch ? parseInt(indexMatch[1], 10) : null;
                if (index === null) return;

                const itemData = {};
                // جمع‌آوری مقادیر از فیلدهای ردیف (هم ورودی و هم خروجی فرمول)
                FieldManager.getAllFields().forEach(field => {
                    const fieldName = field.name;
                    const formFieldName = `items[${index}][${fieldName}]`;
                    const fieldElement = rowElement.querySelector(`[name="${formFieldName}"]`);
                    if (fieldElement) {
                        let value;
                        if (typeof AutoNumeric !== 'undefined' && AutoNumeric.getAutoNumericElement(fieldElement)) {
                            value = AutoNumeric.getNumber(fieldElement);
                        } else {
                            value = Helper.sanitizeFormattedNumber(fieldElement.value);
                        }
                        itemData[fieldName] = parseFloat(value) || 0;
                    }
                });
                
                // اضافه کردن اطلاعات مالیات و ارزش افزوده محصول
                itemData.product_tax_enabled = (product.tax_enabled === true || product.tax.enabled === 1) ? 1 : 0;
                itemData.product_tax_rate = parseFloat(product.tax.rate || 0); // FIX: از product.tax.rate استفاده کنید
                itemData.product_vat_enabled = (product.vat_enabled === true || product.vat.enabled === 1) ? 1 : 0;
                itemData.product_vat_rate = parseFloat(product.vat.rate || 0); // FIX: از product.vat.rate استفاده کنید

                itemsForSummary.push(itemData);
            });

            const transactionData = TransactionFormApp.getData().transaction || {};
            const productsById = ProductManager.getAllProducts().reduce((acc, p) => {
                acc[p.id] = p;
                return acc;
            }, {});
            const defaultSettings = TransactionFormApp.getData().defaultSettings;
            const taxSettings = {
                tax_rate: parseFloat(defaultSettings.tax_rate || 0),
                vat_rate: parseFloat(defaultSettings.vat_rate || 0)
            };

            const summary = FormulaManager.calculateTransactionSummary(
                itemsForSummary,
                transactionData,
                productsById,
                defaultSettings,
                taxSettings
            );
            
            const elements = TransactionFormApp.getElements().summaryElements;
            
            // به‌روزرسانی مقادیر نمایش داده شده
            if (elements.sumBaseItems) {
                elements.sumBaseItems.textContent = Helper.formatRial(summary.total_items_value_rials);
            }
            if (elements.sumProfitWageFee) {
                elements.sumProfitWageFee.textContent = Helper.formatRial(summary.total_profit_wage_commission_rials);
            }
            if (elements.totalGeneralTax) {
                elements.totalGeneralTax.textContent = Helper.formatRial(summary.total_general_tax_rials);
            }
            if (elements.sumBeforeVat) {
                elements.sumBeforeVat.textContent = Helper.formatRial(summary.total_before_vat_rials);
            }
            if (elements.totalVat) {
                elements.totalVat.textContent = Helper.formatRial(summary.total_vat_rials);
            }
            if (elements.finalPayable) {
                elements.finalPayable.textContent = Helper.formatRial(summary.final_payable_amount_rials);
            }
            
            // ذخیره مقادیر در فیلدهای مخفی
            _setHiddenField('total_items_value_rials', summary.total_items_value_rials);
            _setHiddenField('total_profit_wage_commission_rials', summary.total_profit_wage_commission_rials);
            _setHiddenField('total_general_tax_rials', summary.total_general_tax_rials);
            _setHiddenField('total_before_vat_rials', summary.total_before_vat_rials);
            _setHiddenField('total_vat_rials', summary.total_vat_rials);
            _setHiddenField('final_payable_amount_rials', summary.final_payable_amount_rials);

        } catch (e) {
            _logger.log('error', 'Error updating summary fields:', e);
            // در صورت خطا، مقادیر را صفر یا نامشخص نمایش می‌دهیم
            const elements = TransactionFormApp.getElements().summaryElements;
            if (elements.sumBaseItems) elements.sumBaseItems.textContent = Helper.formatRial(0);
            if (elements.sumProfitWageFee) elements.sumProfitWageFee.textContent = Helper.formatRial(0);
            if (elements.totalGeneralTax) elements.totalGeneralTax.textContent = Helper.formatRial(0);
            if (elements.sumBeforeVat) elements.sumBeforeVat.textContent = Helper.formatRial(0);
            if (elements.totalVat) elements.totalVat.textContent = Helper.formatRial(0);
            if (elements.finalPayable) elements.finalPayable.textContent = Helper.formatRial(0);
        } finally {
            if (summaryContainer) {
                summaryContainer.dataset.updating = 'false';
            }
        }
    }

    /**
     * تنظیم مقدار یک فیلد مخفی.
     * @param {string} fieldName - نام فیلد.
     * @param {*} value - مقدار.
     */
    function _setHiddenField(fieldName, value) {
        let field = document.querySelector(`input[name="${fieldName}"]`);
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = fieldName;
            const form = document.getElementById('transaction-form');
            if (form) {
                form.appendChild(field);
            }
        }
        field.value = value;
    }

    return {
        init: _init,
        updateSummaryFields: _updateSummaryFields
    };
})();

// ماژول سرویس داده (برای بارگذاری داده‌های از سرور)
const DataService = (function() {
    let _initialized = false;
    let _logger;

    function _init(logger) {
        if (_initialized) return;
        _logger = logger;
        _initialized = true;
        _logger.log('info', 'DataService initialized');
    }

    /**
     * بارگذاری مراکز ری‌گیری از سرور یا از داده‌های موجود در صفحه.
     * @returns {Promise<Array>} - وعده آرایه مراکز ری‌گیری.
     */
    function _loadAssayOffices() {
        return new Promise((resolve, reject) => {
            if (window.assayOfficesData && Array.isArray(window.assayOfficesData) && window.assayOfficesData.length > 0) {
                _logger.log('info', 'Assay offices loaded from window.assayOfficesData.');
                resolve(window.assayOfficesData);
                return;
            }
            
            _logger.log('info', 'Loading assay offices from server...');
            const url = `${window.baseUrl}/app/assay-offices/list`;
            fetch(url, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data && Array.isArray(data.data)) {
                    window.assayOfficesData = data.data; // کش کردن داده‌ها
                    resolve(data.data);
                } else if (data && Array.isArray(data)) { // اگر آرایه مستقیماً برگشت
                    window.assayOfficesData = data;
                    resolve(data);
                } else {
                    _logger.log('warn', 'Invalid assay offices data from server.', data);
                    resolve([]); // بازگرداندن آرایه خالی
                }
            })
            .catch(error => {
                _logger.log('error', 'Error loading assay offices from server:', error);
                resolve([]); // در صورت خطا، آرایه خالی برگردان
            });
        });
    }

    return {
        init: _init,
        loadAssayOffices: _loadAssayOffices
    };
})();

// ماژول اعتبارسنجی فرم
const ValidationManager = (function() {
    let _initialized = false;
    let _logger;

    function _init(logger) {
        if (_initialized) return;
        _logger = logger;
        _initialized = true;
        _logger.log('info', 'ValidationManager initialized');
    }

    /**
     * اعتبارسنجی کل فرم.
     * @returns {boolean} - آیا فرم معتبر است.
     */
    function _validateForm() {
        const form = document.getElementById('transaction-form');
        if (!form) {
            _logger.log('error', 'Transaction form not found for validation.');
            return false;
        }
        
        _clearValidationErrors(); // پاک کردن خطاهای قبلی
        let isValid = true;

        // اعتبارسنجی فیلدهای اصلی فرم (required)
        const mainRequiredFields = form.querySelectorAll('input[required]:not([name^="items["]), select[required]:not([name^="items["]), textarea[required]:not([name^="items["])');
        mainRequiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            }
        });

        // بررسی ردیف‌های اقلام
        const itemRows = document.querySelectorAll('.transaction-item-row');
        if (itemRows.length === 0) {
            UIManager.showMessage('error', window.MESSAGES?.transaction_items_required || 'حداقل یک ردیف کالا باید وارد شود.');
            isValid = false;
        } else {
            itemRows.forEach((row, index) => {
                const rowValid = _validateRow(row, index);
                if (!rowValid) {
                    isValid = false;
                }
            });
        }
        
        // اعتبارسنجی فیلد مظنه (اگر مقدار دارد، باید عددی باشد)
        const mazanehPriceElement = document.getElementById('mazaneh_price');
        if (mazanehPriceElement && mazanehPriceElement.value.trim() !== '') {
            const value = Helper.sanitizeFormattedNumber(mazanehPriceElement.value);
            if (!is_numeric(value)) {
                mazanehPriceElement.classList.add('is-invalid');
                isValid = false;
            }
        }

        return isValid;
    }

    /**
     * اعتبارسنجی یک ردیف آیتم.
     * @param {HTMLElement} row - المان ردیف.
     * @param {number} index - شاخص ردیف.
     * @returns {boolean} - آیا ردیف معتبر است.
     */
    function _validateRow(row, index) {
        if (!row) return false;
        let rowValid = true;

        // بررسی انتخاب محصول
        const productSelect = row.querySelector('.product-select');
        if (!productSelect || !productSelect.value) {
            productSelect?.classList.add('is-invalid');
            rowValid = false;
        }

        // بررسی فیلدهای required درون ردیف
        const requiredFieldsInRow = row.querySelectorAll(`[name^="items[${index}]"][required]`);
        requiredFieldsInRow.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                rowValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });

        // اعتبارسنجی فیلدهای عددی
        const numericFieldsInRow = row.querySelectorAll(`[name^="items[${index}]"].autonumeric`);
        numericFieldsInRow.forEach(field => {
            if (field.value.trim() !== '') {
                const value = Helper.sanitizeFormattedNumber(field.value);
                if (!is_numeric(value)) {
                    field.classList.add('is-invalid');
                    rowValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            }
        });
        
        // اعتبارسنجی عیار (بین 700 تا 999.9)
        const caratFields = row.querySelectorAll(`[name^="items[${index}]"][name*="carat"]`);
        caratFields.forEach(field => {
            if (field.value.trim() !== '') {
                const carat = parseFloat(Helper.sanitizeFormattedNumber(field.value));
                if (isNaN(carat) || carat < 700 || carat > 999.9) {
                    field.classList.add('is-invalid');
                    rowValid = false;
                    // می‌توانید پیام خطای خاصی را در اینجا نمایش دهید
                } else {
                    field.classList.remove('is-invalid');
                }
            }
        });

        return rowValid;
    }

    /**
     * پاکسازی خطاهای اعتبارسنجی از فرم.
     */
    function _clearValidationErrors() {
        const invalidFields = document.querySelectorAll('.is-invalid');
        invalidFields.forEach(field => {
            field.classList.remove('is-invalid');
        });
    }

    return {
        init: _init,
        validateForm: _validateForm,
        validateRow: _validateRow,
        clearValidationErrors: _clearValidationErrors
    };
})();

// ماژول اصلی برنامه TransactionFormApp
const TransactionFormApp = (function() {
    let _initialized = false;
    let _logger; // لاگر
    
    const _config = {
        baseUrl: window.baseUrl || '',
        debug: false, // این مقدار از PHP تزریق می‌شود
        isEditMode: false // این مقدار از PHP تزریق می‌شود
    };
    
    // داده‌های سراسری از PHP
    const _data = {
        transaction: window.transactionData || null, // برای حالت ویرایش
        items: window.transactionItemsData || [], // برای حالت ویرایش
        products: window.productsData || [],
        assayOffices: window.assayOfficesData || [],
        contacts: window.contactsData || [],
        fields: window.allFieldsData?.fields || [], // FIX: استفاده از allFieldsData
        formulas: window.allFormulasData?.formulas || [], // FIX: استفاده از allFormulasData
        defaultSettings: window.defaultSettings || {}
    };
    
    // عناصر DOM اصلی
    const _elements = {
        form: null,
        itemsContainer: null,
        addItemButton: null,
        itemRowTemplate: null,
        summaryElements: {}
    };

    /**
     * مقداردهی اولیه برنامه اصلی.
     */
    function _init() {
        if (_initialized) return;

        // مقداردهی اولیه لاگر
        _logger = new ConsoleLogger(); // می‌توانید یک لاگر پیشرفته‌تر اینجا تزریق کنید
        // به‌روزرسانی config بر اساس داده‌های PHP
        _config.debug = (window.phpConfig && window.phpConfig.app && window.phpConfig.app.debug) || false;
        _config.isEditMode = (_data.transaction !== null); // اگر transactionData وجود دارد، حالت ویرایش است
        _logger.log('info', 'Initializing Transaction Form App...');

        // مقداردهی عناصر DOM اصلی
        _elements.form = document.getElementById('transaction-form');
        _elements.itemsContainer = document.getElementById('transaction-items-container');
        _elements.addItemButton = document.getElementById('add-transaction-item');
        const itemRowTemplateEl = document.getElementById('item-row-template');
        _elements.itemRowTemplate = itemRowTemplateEl ? itemRowTemplateEl.innerHTML : '';

        // بررسی وجود عناصر اصلی
        if (!_elements.form || !_elements.itemsContainer || !_elements.addItemButton || !_elements.itemRowTemplate) {
            _logger.log('error', 'Transaction form critical elements missing. Aborting initialization.');
            return;
        }

        // مقداردهی عناصر خلاصه مالی
        _elements.summaryElements = {
            sumBaseItems: document.getElementById('summary-sum_base_items'),
            sumProfitWageFee: document.getElementById('summary-sum_profit_wage_fee'),
            totalGeneralTax: document.getElementById('summary-total_general_tax'),
            sumBeforeVat: document.getElementById('summary-sum_before_vat'),
            totalVat: document.getElementById('summary-total_vat'),
            finalPayable: document.getElementById('summary-final_payable')
        };

        // راه‌اندازی ماژول‌های دیگر با لاگر
        FieldManager.init(_data.fields, _logger); // ارسال لاگر
        FormulaManager.init(_data.formulas, _logger); // ارسال لاگر
        ProductManager.init(_data.products, _logger); // ارسال لاگر
        ValidationManager.init(_logger); // ارسال لاگر
        UIManager.init(_elements, _logger); // ارسال لاگر
        SummaryManager.init(_logger); // ارسال لاگر

        // راه‌اندازی فیلد مظنه با تنظیمات AutoNumeric
        const mazanehPriceElement = document.getElementById('mazaneh_price');
        if (mazanehPriceElement) {
            UIManager.initializeAutonumeric(mazanehPriceElement);
        }

        // بارگذاری داده‌های اولیه (برای حالت ویرایش یا افزودن ردیف خالی)
        _loadInitialData();
        
        // اتصال رویدادهای اصلی
        _bindEvents();
        
        _initialized = true;
        _logger.log('info', 'Transaction Form App initialized successfully.');
    }

    /**
     * بارگذاری داده‌های اولیه فرم (برای ویرایش یا افزودن ردیف خالی).
     */
    function _loadInitialData() {
        _logger.log('info', 'Loading initial data...');
        
        // اگر در حالت ویرایش هستیم و آیتم‌ها موجودند
        if (_config.isEditMode && _data.items && _data.items.length > 0) {
            _logger.log('debug', `Loading ${_data.items.length} existing items for edit mode.`);
            // در این حالت، ردیف‌ها توسط PHP رندر شده‌اند.
            // فقط باید AutoNumeric و رویدادهای محاسباتی را برای آن‌ها راه‌اندازی کنیم.
            document.querySelectorAll('.transaction-item-row').forEach((rowElement, index) => {
                const productSelect = rowElement.querySelector('.product-select');
                const productId = productSelect ? productSelect.value : null;
                const product = ProductManager.getProductById(productId);
                if (product) {
                    UIManager.initializeAllAutonumerics(rowElement); // راه‌اندازی AutoNumeric
                    // FIX: اطمینان حاصل شود که itemData به bindCalculationEvents و handleManufacturedAccessoriesDependency پاس داده می‌شود
                    const itemData = _data.items[index]; // دریافت itemData مربوطه
                    UIManager.bindCalculationEvents(rowElement, ProductManager.getProductGroup(product), index); // اتصال رویدادها
                    UIManager.handleManufacturedAccessoriesDependency(rowElement, index); // مدیریت متعلقات
                    
                    // پس از اتصال رویدادها، محاسبات اولیه را تریگر می‌کنیم
                    const group = ProductManager.getProductGroup(product);
                    const groupFormulas = FormulaManager.getFormulasByGroup(group);
                    FormulaManager.calculateFormulasForRow(rowElement, groupFormulas, index, product);
                }
            });
            // بارگذاری اطلاعات طرف حساب
            _loadPartyData();
        } else {
            _logger.log('debug', 'No existing items found. Adding one empty row.');
            UIManager.addNewEmptyItemRow(); // افزودن یک ردیف خالی برای شروع
        }
        
        // اطمینان از پر شدن صحیح selectهای مراکز ری‌گیری پس از رندر اولیه
        UIManager.fillAssayOfficeSelects(_data.assayOffices);

        // به‌روزرسانی فیلدهای خلاصه
        SummaryManager.updateSummaryFields();
    }

    /**
     * بارگذاری اطلاعات طرف حساب (Party Data) در حالت ویرایش.
     */
    function _loadPartyData() {
        _logger.log('info', 'Loading party data...');
        if (!_data.transaction) {
            _logger.log('warn', 'No transaction data available to load party info.');
            return;
        }

        // اولویت‌ها: party_name/phone/national_code مستقیم، سپس counterparty_name، سپس از لیست contacts
        let partyName = _data.transaction.party_name || _data.transaction.counterparty_name || null;
        let partyPhone = _data.transaction.party_phone || null;
        let partyNationalCode = _data.transaction.party_national_code || null;

        // اگر اطلاعات کامل نیست، از لیست contacts تلاش می‌کنیم
        if ((!partyName || !partyPhone || !partyNationalCode) && _data.transaction.counterparty_contact_id && _data.contacts) {
            const contactId = _data.transaction.counterparty_contact_id;
            const contact = _data.contacts.find(c => c.id == contactId);
            
            if (contact) {
                _logger.log('debug', 'Found contact data for party:', contact);
                if (!partyName) partyName = contact.name;
                if (!partyPhone) partyPhone = contact.phone || '';
                if (!partyNationalCode) partyNationalCode = contact.national_code || '';
            }
        }
        
        // تنظیم مقادیر فیلدهای مخفی طرف حساب
        const setHiddenFieldValue = (name, value) => {
            const field = document.querySelector(`input[name="${name}"]`);
            if (field) field.value = value || '';
        };

        setHiddenFieldValue('party_name', partyName);
        setHiddenFieldValue('party_phone', partyPhone);
        setHiddenFieldValue('party_national_code', partyNationalCode);

        _logger.log('debug', 'Party data loaded:', { partyName, partyPhone, partyNationalCode });
    }

    /**
     * اتصال رویدادهای اصلی فرم.
     */
    function _bindEvents() {
        // رویداد ارسال فرم
        if (_elements.form) {
            _elements.form.addEventListener('submit', function(event) {
                event.preventDefault();
                
                const isValid = ValidationManager.validateForm();
                if (!isValid) {
                    UIManager.showMessage('error', window.MESSAGES?.validation_errors || 'لطفاً خطاهای موجود در فرم را اصلاح کنید سپس دوباره تلاش کنید.');
                    return;
                }
                
                _fixEmptyFieldsBeforeSubmit(); // اصلاح فیلدهای خالی قبل از ارسال
                
                const formData = new FormData(_elements.form);
                
                fetch(_elements.form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => {
                    // اگر پاسخ JSON نیست، فرض می‌کنیم ریدایرکت شده است
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        _logger.log('info', 'Non-JSON response received, likely a redirect.');
                        window.location.href = window.baseUrl + '/app/transactions';
                        return null;
                    }
                    return response.json();
                })
                .then(data => {
                    if (data === null) return; // اگر قبلاً ریدایرکت شده باشد
                    
                    if (data.success) {
                        UIManager.showMessage('success', data.message || window.MESSAGES?.success_save || 'عملیات با موفقیت انجام شد.');
                        window.location.href = window.baseUrl + '/app/transactions';
                    } else {
                        const errorMessage = data.errors && Array.isArray(data.errors) ? data.errors.join('<br>') : (data.message || window.MESSAGES?.error_save || 'خطا در ذخیره فرم');
                        UIManager.showMessage('error', errorMessage);
                        _logger.log('error', 'Form submission failed:', data);
                    }
                })
                .catch(err => {
                    UIManager.showMessage('error', window.MESSAGES?.server_error || 'خطا در ارتباط با سرور.');
                    _logger.log('error', 'Fetch error:', err);
                });
            });
        }
        
        // رویداد افزودن ردیف جدید
        if (_elements.addItemButton) {
            _elements.addItemButton.addEventListener('click', function() {
                UIManager.addNewEmptyItemRow();
            });
        }
        
        // رویداد تغییر مظنه (Mazaneh Price)
        const mazanehPriceElement = document.getElementById('mazaneh_price');
        if (mazanehPriceElement) {
            mazanehPriceElement.addEventListener('change', function() {
                // محاسبه مجدد تمام ردیف‌ها
                document.querySelectorAll('.transaction-item-row').forEach(row => {
                    const productSelect = row.querySelector('.product-select');
                    if (productSelect && productSelect.value) {
                        const productId = productSelect.value;
                        const product = ProductManager.getProductById(productId);
                        if (product) {
                            const group = ProductManager.getProductGroup(product);
                            const groupFormulas = FormulaManager.getFormulasByGroup(group);
                            
                            const indexMatch = productSelect.name.match(/items\[(\d+)\]/);
                            if (indexMatch) {
                                const index = parseInt(indexMatch[1], 10);
                                FormulaManager.calculateFormulasForRow(row, groupFormulas, index, product);
                            }
                        }
                    }
                });
                SummaryManager.updateSummaryFields(); // به‌روزرسانی خلاصه پس از تغییر مظنه
            });
        }
        
        // رویداد تغییر نوع معامله برای هماهنگ کردن با وضعیت تحویل
        const transactionTypeSelect = document.getElementById('transaction_type');
        const deliveryStatusSelect = document.getElementById('delivery_status');
        
        if (transactionTypeSelect && deliveryStatusSelect) {
            transactionTypeSelect.addEventListener('change', function() {
                _synchronizeDeliveryStatusWithTransactionType();
            });
            _synchronizeDeliveryStatusWithTransactionType(); // اجرای اولیه
        }
    }

    /**
     * هماهنگ کردن وضعیت تحویل با نوع معامله.
     */
    function _synchronizeDeliveryStatusWithTransactionType() {
        const transactionTypeSelect = document.getElementById('transaction_type');
        const deliveryStatusSelect = document.getElementById('delivery_status');
        
        if (!transactionTypeSelect || !deliveryStatusSelect) {
            _logger.log('warn', 'Transaction type or delivery status select elements not found for synchronization.');
            return;
        }
        
        const transactionType = transactionTypeSelect.value;
        _logger.log('debug', `Synchronizing delivery status with transaction type: ${transactionType}`);
        
        // بازنشانی همه گزینه‌ها
        Array.from(deliveryStatusSelect.options).forEach(option => {
            option.disabled = false;
        });
        
        // اعمال محدودیت‌ها بر اساس نوع معامله
        if (transactionType === 'buy') {
            Array.from(deliveryStatusSelect.options).forEach(option => {
                if (option.value === 'pending_delivery') {
                    option.disabled = true;
                    if (deliveryStatusSelect.value === 'pending_delivery') {
                        deliveryStatusSelect.value = 'pending_receipt';
                        _logger.log('debug', 'Changed delivery status to "منتظر دریافت" for buy transaction.');
                    }
                }
            });
        } else if (transactionType === 'sell') {
            Array.from(deliveryStatusSelect.options).forEach(option => {
                if (option.value === 'pending_receipt') {
                    option.disabled = true;
                    if (deliveryStatusSelect.value === 'pending_receipt') {
                        deliveryStatusSelect.value = 'pending_delivery';
                        _logger.log('debug', 'Changed delivery status to "منتظر تحویل" for sell transaction.');
                    }
                }
            });
        }
        _logger.log('debug', `Final delivery status after sync: ${deliveryStatusSelect.value}`);
    }

    /**
     * اصلاح فیلدهای خالی قبل از ارسال فرم.
     * این تابع فیلدهای خالی را به '0' یا null تبدیل می‌کند تا محدودیت‌های دیتابیس رعایت شوند.
     */
    function _fixEmptyFieldsBeforeSubmit() {
        _logger.log('debug', 'Fixing empty fields before form submission.');

        // فیلدهای autonumeric و عددی باید 0 شوند اگر خالی هستند
        document.querySelectorAll('.autonumeric, input[type="number"]').forEach(field => {
            if (field.value.trim() === '') {
                field.value = '0';
            }
        });

        // فیلدهای select که مقدار 0 (پیش‌فرض) دارند و نباید به عنوان ID معتبر ارسال شوند
        document.querySelectorAll('select.assay-office-select').forEach(select => {
            if (select.value === '0') {
                // اگر 0 انتخاب شده، آن را به رشته خالی تغییر می‌دهیم تا در بک‌اند به NULL تبدیل شود
                // یا می‌توانید این فیلد را disabled کنید تا ارسال نشود
                select.value = ''; 
            }
        });
    }

    return {
        init: _init,
        getConfig: () => _config,
        getData: () => _data,
        getElements: () => _elements
    };
})();

// راه‌اندازی برنامه پس از بارگذاری کامل صفحه
document.addEventListener('DOMContentLoaded', function() {
    // راه‌اندازی تقویم شمسی
    if (window.jalaliDatepicker) {
        jalaliDatepicker.startWatch({
            selector: '.jalali-datepicker',
            showTodayBtn: true,
            showCloseBtn: true,
            format: 'Y/m/d H:i:s' // فرمت شامل ساعت و دقیقه و ثانیه
        });
    }
    
    // راه‌اندازی برنامه اصلی
    TransactionFormApp.init();
});

