/**
 * transaction-form.js - ماژول اصلی
 * نسخه: 2.0.0
 * تاریخ: 1404/03/25
 * 
 * این فایل شامل ماژول‌های مختلف برای مدیریت فرم تراکنش است.
 * هر ماژول مسئولیت خاصی دارد و به صورت مستقل عمل می‌کند.
 */

/**
 * ماژول مدیریت فیلدها
 * مسئول مدیریت فیلدهای فرم و تولید HTML مربوط به آنها
 */
const FieldManager = (function() {
    // متغیرهای خصوصی
    let _fields = [];
    let _initialized = false;
    
    /**
     * مقداردهی اولیه ماژول
     * @param {Array} fields - آرایه فیلدهای تعریف شده
     */
    function _init(fields) {
        if (_initialized) return;
        
        _fields = fields || [];
        _initialized = true;
        
        console.log('FieldManager initialized with', _fields.length, 'fields');
    }
    
    /**
     * دریافت تمام فیلدها
     * @returns {Array} - آرایه فیلدها
     */
    function _getAllFields() {
        return _fields;
    }
    
    /**
     * دریافت تمام گروه‌های محصول از فیلدها
     * @returns {Array} - آرایه گروه‌های محصول
     */
    function _getAllProductGroups() {
        const groups = new Set();
        
        // استخراج از فیلدها
        _fields.forEach(field => {
            if (field.group) {
                groups.add(field.group.toString().trim().toLowerCase());
            }
        });
        
        // اگر هیچ گروهی پیدا نشد، حداقل گروه‌های اصلی را برگردان
        if (groups.size === 0) {
            groups.add('melted');
            groups.add('manufactured');
            groups.add('coin');
            groups.add('jewelry');
            groups.add('goldbullion');
            groups.add('silverbullion');
        }
        
        return Array.from(groups);
    }
    
    /**
     * دریافت فیلدهای یک گروه خاص
     * @param {string} group - نام گروه
     * @returns {Array} - آرایه‌ای از فیلدهای مربوط به گروه
     */
    function _getFieldsByGroup(group) {
        if (!group) return [];
        
        const groupLower = group.toLowerCase();
        return _fields.filter(field => {
            return field.group && field.group.toLowerCase() === groupLower;
        });
    }
    
    /**
     * تولید HTML فیلدهای یک گروه
     * @param {string} group - نام گروه
     * @param {number} index - شاخص ردیف
     * @returns {string} - HTML فیلدها
     */
    function _getFieldsHtmlByGroup(group, index) {
        if (!group) return '';
        
        const fields = _getFieldsByGroup(group);
        
        let html = '';
        
        fields.forEach(field => {
            const fieldName = field.name || '';
            const fieldLabel = field.label || '';
            const fieldType = field.type || 'text';
            const isRequired = field.required || false;
            const colClass = field.col_class || 'col-md-3';
            
            html += `
                <div class="${colClass}">
                    <label class="form-label">${fieldLabel}${isRequired ? ' <span class="text-danger">*</span>' : ''}</label>
            `;
            
            if (fieldType === 'select') {
                html += `
                    <select name="items[${index}][${fieldName}]" class="form-select"${isRequired ? ' required' : ''}>
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
            } else {
                const inputClass = 'form-control' + 
                                  (field.is_numeric ? ' autonumeric' : '') + 
                                  (fieldName.includes('weight') ? ' weight-field' : '');
                
                html += `
                    <input 
                        type="${fieldType}" 
                        name="items[${index}][${fieldName}]" 
                        class="${inputClass}"
                        ${isRequired ? 'required' : ''}
                    >
                `;
            }
            
            html += `
                    <div class="invalid-feedback">لطفا ${fieldLabel} را وارد کنید.</div>
                </div>
            `;
        });
        
        return html;
    }
    
    // API عمومی
    return {
        init: _init,
        getAllFields: _getAllFields,
        getAllProductGroups: _getAllProductGroups,
        getFieldsByGroup: _getFieldsByGroup,
        getFieldsHtmlByGroup: _getFieldsHtmlByGroup
    };
})();

// ماژول اصلی برنامه
const TransactionFormApp = (function() {
    // متغیرهای خصوصی
    let _initialized = false;
    
    // تنظیمات پیش‌فرض
    const _config = {
        baseUrl: window.baseUrl || '',
        debug: true,
        autoSave: false,
        autoCalculate: true
    };
    
    // داده‌های سراسری
    const _data = {
        fields: window.allFieldsData?.fields || [],
        formulas: window.allFormulasData?.formulas || [],
        products: window.productsData || [],
        transactionItems: window.transactionItemsData || [],
        assayOffices: []
    };
    
    // عناصر DOM اصلی
    let _elements = {
        form: null,
        itemsContainer: null,
        addItemButton: null,
        itemRowTemplate: null,
        summaryElements: {}
    };
    
    /**
     * مقداردهی اولیه و بارگذاری عناصر DOM
     */
    function _init() {
        if (_initialized) return;
        
        console.log('Initializing Transaction Form App...');
        
        // بارگذاری عناصر DOM اصلی
        _elements.form = document.getElementById('transaction-form');
        _elements.itemsContainer = document.getElementById('transaction-items-container');
        _elements.addItemButton = document.getElementById('add-transaction-item');
        const itemRowTemplateEl = document.getElementById('item-row-template');
        _elements.itemRowTemplate = itemRowTemplateEl ? itemRowTemplateEl.innerHTML : '';
        
        // بررسی وجود عناصر اصلی
        if (!_elements.form || !_elements.itemsContainer || !_elements.addItemButton || !_elements.itemRowTemplate) {
            console.error('Transaction form critical elements missing. Aborting initialization.');
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
        
        // راه‌اندازی ماژول‌های دیگر
        FieldManager.init(_data.fields);
        FormulaManager.init(_data.formulas);
        ProductManager.init(_data.products);
        ValidationManager.init();
        UIManager.init(_elements);
        
        // راه‌اندازی فیلد مظنه با تنظیمات AutoNumeric
        const mazanehPriceElement = document.getElementById('mazaneh_price');
        if (mazanehPriceElement) {
            // اطمینان از حذف نمونه قبلی AutoNumeric
            if (typeof AutoNumeric !== 'undefined' && AutoNumeric.getAutoNumericElement(mazanehPriceElement)) {
                AutoNumeric.getAutoNumericElement(mazanehPriceElement).remove();
            }
            
            // ایجاد نمونه جدید با تنظیمات مناسب
            if (typeof AutoNumeric !== 'undefined') {
                new AutoNumeric(mazanehPriceElement, {
                    digitGroupSeparator: '٬',
                    decimalPlaces: 0,
                    unformatOnSubmit: true,
                    selectOnFocus: false,
                    modifyValueOnWheel: false
                });
            }
        }
        
        // بارگذاری داده‌های اولیه
        _loadInitialData();
        
        // اتصال رویدادها
        _bindEvents();
        
        _initialized = true;
        console.log('Transaction Form App initialized successfully.');
    }
    
    /**
     * بارگذاری داده‌های اولیه
     */
    function _loadInitialData() {
        console.log('شروع بارگذاری داده‌های اولیه...');
        
        // بارگذاری مراکز ری‌گیری
        DataService.loadAssayOffices()
            .then(offices => {
                console.log('مراکز ری‌گیری با موفقیت بارگذاری شدند:', offices);
                
                // اطمینان از وجود حداقل یک مرکز ری‌گیری
                if (!Array.isArray(offices) || offices.length === 0) {
                    console.warn('هیچ مرکز ری‌گیری یافت نشد. استفاده از مرکز پیش‌فرض.');
                    _data.assayOffices = [{ id: 0, name: 'پیش‌فرض' }];
                } else {
                    _data.assayOffices = offices;
                    console.log(`${offices.length} مرکز ری‌گیری بارگذاری شد.`);
                }
                
                // افزودن ردیف‌های موجود یا یک ردیف خالی
                let itemIndex = 0;
                if (_data.transactionItems && _data.transactionItems.length > 0) {
                    console.log(`بارگذاری ${_data.transactionItems.length} ردیف موجود...`);
                    _data.transactionItems.forEach(item => {
                        UIManager.renderItemRow(item, itemIndex, _data.assayOffices);
                        itemIndex++;
                    });
                } else {
                    console.log('هیچ ردیفی موجود نیست. افزودن یک ردیف خالی...');
                    UIManager.renderItemRow(null, itemIndex, _data.assayOffices);
                    itemIndex++;
                }
                
                // استفاده از تابع یکتا برای پر کردن تمام فیلدهای مراکز ری‌گیری در صفحه
                setTimeout(() => {
                    UIManager.fillAssayOfficeSelects(_data.assayOffices);
                    
                    // بررسی نهایی وضعیت فیلدهای مراکز ری‌گیری
                    const assayOfficeSelects = document.querySelectorAll('select[name*="item_assay_office"]');
                    console.log(`پس از تکمیل رندر، ${assayOfficeSelects.length} فیلد مرکز ری‌گیری در صفحه وجود دارد.`);
                }, 500);
                
                // به‌روزرسانی فیلدهای خلاصه
                SummaryManager.updateSummaryFields();
                
                // راه‌اندازی AutoNumeric برای فیلد مظنه
                const mazanehPriceElement = document.getElementById('mazaneh_price');
                if (mazanehPriceElement) {
                    UIManager.initializeAutonumeric(mazanehPriceElement);
                }
            })
            .catch(error => {
                console.error('خطا در بارگذاری مراکز ری‌گیری:', error);
                UIManager.showMessage('error', 'assay_office_load_error');
                
                _data.assayOffices = [{ id: 0, name: 'پیش‌فرض' }];
                
                // افزودن ردیف‌های موجود یا یک ردیف خالی
                let itemIndex = 0;
                if (_data.transactionItems && _data.transactionItems.length > 0) {
                    _data.transactionItems.forEach(item => {
                        UIManager.renderItemRow(item, itemIndex, _data.assayOffices);
                        itemIndex++;
                    });
                } else {
                    UIManager.renderItemRow(null, itemIndex, _data.assayOffices);
                    itemIndex++;
                }
                
                // استفاده از تابع یکتا برای پر کردن تمام فیلدهای مراکز ری‌گیری در صفحه
                setTimeout(() => {
                    UIManager.fillAssayOfficeSelects(_data.assayOffices);
                }, 500);
                
                // به‌روزرسانی فیلدهای خلاصه
                SummaryManager.updateSummaryFields();
                
                // راه‌اندازی AutoNumeric برای فیلد مظنه
                const mazanehPriceElement = document.getElementById('mazaneh_price');
                if (mazanehPriceElement) {
                    UIManager.initializeAutonumeric(mazanehPriceElement);
                }
            });
    }
    
    /**
     * اتصال رویدادهای اصلی
     */
    function _bindEvents() {
        // رویداد ارسال فرم
        if (_elements.form) {
            _elements.form.addEventListener('submit', function(event) {
                event.preventDefault();
                
                const isValid = ValidationManager.validateForm();
                if (!isValid) {
                    UIManager.showMessage('error', 'لطفاً خطاهای موجود در فرم را اصلاح کنید سپس دوباره تلاش کنید.');
                    return;
                }
                
                // اصلاح فیلدهای خالی قبل از ارسال فرم
                _fixEmptyFields();
                
                // جمع‌آوری داده‌های فرم
                const formData = new FormData(_elements.form);
                
                // ارسال با fetch
                fetch(_elements.form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // موفقیت: ریدایرکت به لیست معاملات
                        window.location.href = _config.baseUrl + '/app/transactions';
                    } else {
                        // خطا: نمایش پیام خطا
                        if (data.errors && Array.isArray(data.errors)) {
                            UIManager.showMessage('error', data.errors.join('<br>'));
                        } else if (data.message) {
                            UIManager.showMessage('error', data.message);
                        } else {
                            UIManager.showMessage('error', 'خطای ناشناخته در ذخیره فرم');
                        }
                    }
                })
                .catch(err => {
                    UIManager.showMessage('error', 'خطا در ارتباط با سرور');
                    console.error(err);
                });
            });
        }
        
        // رویداد افزودن ردیف جدید
        if (_elements.addItemButton) {
            _elements.addItemButton.addEventListener('click', function() {
                UIManager.addNewEmptyItemRow();
            });
        }
        
        // رویداد تغییر مظنه
        const mazanehPriceElement = document.getElementById('mazaneh_price');
        if (mazanehPriceElement) {
            mazanehPriceElement.addEventListener('change', function() {
                // محاسبه مجدد تمام ردیف‌ها
                const rows = document.querySelectorAll('.transaction-item-row');
                rows.forEach(row => {
                    const productSelect = row.querySelector('.product-select');
                    if (productSelect && productSelect.value) {
                        const productId = productSelect.value;
                        const product = ProductManager.getProductById(productId);
                        if (product) {
                            const group = ProductManager.getProductGroup(product);
                            const groupFormulas = FormulaManager.getFormulasByGroup(group);
                            
                            // یافتن شاخص ردیف
                            const nameAttr = productSelect.getAttribute('name');
                            const indexMatch = nameAttr.match(/items\[(\d+)\]/);
                            if (indexMatch) {
                                const index = indexMatch[1];
                                FormulaManager.calculateFormulasForRow(row, groupFormulas, index, product);
                            }
                        }
                    }
                });
                
                // به‌روزرسانی فیلدهای خلاصه
                SummaryManager.updateSummaryFields();
            });
        }
        
        // رویداد تغییر نوع معامله برای هماهنگ کردن با وضعیت تحویل
        const transactionTypeSelect = document.getElementById('transaction_type');
        if (transactionTypeSelect) {
            transactionTypeSelect.addEventListener('change', function() {
                _synchronizeDeliveryStatusWithTransactionType();
            });
            
            // اجرای اولیه برای تنظیم وضعیت تحویل بر اساس نوع معامله فعلی
            _synchronizeDeliveryStatusWithTransactionType();
        }
    }
    
    /**
     * اصلاح فیلدهای خالی قبل از ارسال فرم
     * این تابع فیلدهای خالی را به null تبدیل می‌کند تا محدودیت‌های کلید خارجی رعایت شوند
     */
    function _fixEmptyFields() {
        // اصلاح فیلدهای assay_office_id - فقط برای آبشده
        const assayOfficeFields = document.querySelectorAll('[name$="[item_assay_office_melted]"]');
        
        assayOfficeFields.forEach(field => {
            // اگر فیلد خالی است، مقدار 0 را تنظیم کنیم
            if (!field.value || field.value.trim() === '') {
                field.value = '0';
            }
        });
        
        // اصلاح سایر فیلدهای خالی که ممکن است مشکل ایجاد کنند
        const numericFields = document.querySelectorAll('.autonumeric');
        numericFields.forEach(field => {
            if (!field.value || field.value.trim() === '') {
                field.value = '0';
            }
        });
        
        // اصلاح فیلدهای tag_number که ممکن است خالی باشند
        const tagNumberFields = document.querySelectorAll('[name$="[tag_number]"], [name$="[tag_number_melted]"], [name$="[tag_number_manufactured]"], [name$="[tag_number_coin]"], [name$="[tag_number_goldbullion]"], [name$="[tag_number_silverbullion]"], [name$="[tag_number_jewelry]"]');
        tagNumberFields.forEach(field => {
            if (!field.value || field.value.trim() === '') {
                field.value = ' '; // استفاده از یک فضای خالی به جای رشته خالی
            }
        });
        
        // اصلاح فیلدهای کلید خارجی دیگر
        const foreignKeyFields = document.querySelectorAll('[name$="[customer_id]"], [name$="[seller_id]"], [name$="[buyer_id]"], [name$="[branch_id]"]');
        foreignKeyFields.forEach(field => {
            if (!field.value || field.value.trim() === '') {
                // ایجاد یک فیلد مخفی با همان نام و مقدار 0
                const hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = field.name;
                hiddenField.value = '0'; // استفاده از 0 به جای مقدار خالی
                
                // جایگزینی فیلد اصلی با فیلد مخفی
                field.parentNode.appendChild(hiddenField);
                field.name = field.name + '_original'; // تغییر نام فیلد اصلی
            }
        });
    }
    
    // API عمومی
    return {
        init: _init,
        getConfig: () => _config,
        getData: () => _data,
        getElements: () => _elements
    };
})();

// راه‌اندازی برنامه پس از بارگذاری کامل صفحه
document.addEventListener('DOMContentLoaded', function() {
    // بررسی فرمول‌ها
    console.log('Checking formulas from window.allFormulasData:', window.allFormulasData);
    
    // بررسی فرمول‌های گروه melted
    if (window.allFormulasData && window.allFormulasData.formulas) {
        const meltedFormulas = window.allFormulasData.formulas.filter(f => f.group === 'melted');
        console.log('Melted formulas from window:', meltedFormulas.length, meltedFormulas);
    }
    
    // راه‌اندازی تقویم شمسی
    if (window.jalaliDatepicker) jalaliDatepicker.startWatch();
    
    // راه‌اندازی برنامه اصلی
    TransactionFormApp.init();
});
/**
 * ماژول مدیریت فرمول‌ها
 * مسئول مدیریت فرمول‌های محاسباتی و انجام محاسبات
 */
const FormulaManager = (function() {
    // متغیرهای خصوصی
    let _formulas = [];
    let _initialized = false;
    
    /**
     * مقداردهی اولیه ماژول
     * @param {Array} formulas - آرایه فرمول‌های تعریف شده
     */
    function _init(formulas) {
        if (_initialized) return;
        
        _formulas = formulas || [];
        _initialized = true;
        
        console.log('FormulaManager initialized with', _formulas.length, 'formulas');
        
        // بررسی فرمول‌های موجود
        if (_formulas.length > 0) {
            console.log('Sample formula:', _formulas[0]);
            
            // بررسی فرمول‌های گروه melted
            const meltedFormulas = _formulas.filter(f => f.group === 'melted');
            console.log('Melted formulas:', meltedFormulas.length, meltedFormulas);
        }
    }
    
    /**
     * دریافت تمام فرمول‌ها
     * @returns {Array} - آرایه فرمول‌ها
     */
    function _getFormulas() {
        return _formulas;
    }
    
    /**
     * دریافت فرمول‌های یک گروه خاص
     * @param {string} group - نام گروه
     * @returns {Array} - آرایه‌ای از فرمول‌های مربوط به گروه
     */
    function _getFormulasByGroup(group) {
        if (!group) return [];
        
        const groupLower = group.toLowerCase();
        return _formulas.filter(formula => {
            return formula.group && formula.group.toLowerCase() === groupLower;
        });
    }
    
    /**
     * محاسبه یک فرمول با مقادیر ورودی
     * @param {string} formulaName - نام فرمول
     * @param {Object} inputValues - مقادیر ورودی
     * @returns {number} - نتیجه محاسبه
     */
    function _calculate(formulaName, inputValues) {
        const formula = _formulas.find(f => f.name === formulaName);
        if (!formula) {
            console.warn(`Formula not found: ${formulaName}`);
            return 0;
        }
        
        try {
            // بررسی وجود تمام فیلدهای مورد نیاز
            const missingFields = [];
            (formula.fields || []).forEach(field => {
                if (inputValues[field] === undefined) {
                    missingFields.push(field);
                }
            });
            
            if (missingFields.length > 0) {
                console.warn(`Missing fields for formula ${formulaName}:`, missingFields);
                // مقداردهی پیش‌فرض برای فیلدهای گم‌شده
                missingFields.forEach(field => {
                    inputValues[field] = 0;
                });
            }
            
            // اجرای فرمول
            // استفاده از فیلد formula به جای expression
            const expression = formula.formula || formula.expression;
            if (!expression) {
                console.error(`No formula/expression defined for formula: ${formulaName}`);
                return 0;
            }
            
            // جایگزینی متغیرها در عبارت
            let evalExpression = expression;
            
            // ابتدا عبارات منطقی را با نشانه‌های خاص جایگزین کنیم
            evalExpression = evalExpression
                .replace(/&&/g, '##AND##')
                .replace(/\|\|/g, '##OR##')
                .replace(/!/g, '##NOT##')
                .replace(/==/g, '##EQ##')
                .replace(/>=/g, '##GTE##')
                .replace(/<=/g, '##LTE##')
                .replace(/>/g, '##GT##')
                .replace(/</g, '##LT##')
                .replace(/!=/g, '##NEQ##');
            
            // حالا متغیرها را جایگزین کنیم
            for (const [key, value] of Object.entries(inputValues)) {
                const regex = new RegExp(`\\b${key}\\b`, 'g');
                // اطمینان از اینکه مقدار یک عدد معتبر است
                const safeValue = isNaN(value) ? 0 : value;
                evalExpression = evalExpression.replace(regex, safeValue);
            }
            
            // برگرداندن عبارات منطقی
            evalExpression = evalExpression
                .replace(/##AND##/g, '&&')
                .replace(/##OR##/g, '||')
                .replace(/##NOT##/g, '!')
                .replace(/##EQ##/g, '==')
                .replace(/##GTE##/g, '>=')
                .replace(/##LTE##/g, '<=')
                .replace(/##GT##/g, '>')
                .replace(/##LT##/g, '<')
                .replace(/##NEQ##/g, '!=');
            
            // اصلاح عبارات شرطی در فرمول‌ها
            // مثال: (product_tax_enabled && product_tax_rate > 0) ? total * product_tax_rate / 100 : 0
            if (evalExpression.includes('?') && evalExpression.includes(':')) {
                // اگر فرمول شرطی است، ابتدا شرط را بررسی کنیم
                const conditionMatch = evalExpression.match(/\((.*?)\)\s*\?/);
                if (conditionMatch) {
                    const condition = conditionMatch[1];
                    // بررسی شرط به صورت مستقیم
                    let conditionResult;
                    try {
                        const conditionFn = new Function('return ' + condition);
                        conditionResult = conditionFn();
                    } catch (error) {
                        console.error(`Error evaluating condition in formula ${formulaName}:`, error);
                        conditionResult = false;
                    }
                    
                    // بر اساس نتیجه شرط، بخش مناسب فرمول را انتخاب کنیم
                    const parts = evalExpression.split('?');
                    if (parts.length === 2) {
                        const resultParts = parts[1].split(':');
                        if (resultParts.length === 2) {
                            evalExpression = conditionResult ? resultParts[0].trim() : resultParts[1].trim();
                        }
                    }
                }
            }
            
            // بررسی نهایی عبارت برای اطمینان از اینکه فقط شامل عملیات مجاز است
            if (!/^[\d\s\+\-\*\/\(\)\.\,\%\&\|\!\=\<\>]*$/.test(evalExpression)) {
                console.error(`Invalid expression after substitution: ${evalExpression}`);
                return 0;
            }
            
            // محاسبه عبارت با مدیریت خطا
            let result;
            try {
                // استفاده از Function به جای eval برای امنیت بیشتر
                const calculateFn = new Function('return ' + evalExpression);
                result = calculateFn();
                
                // بررسی معتبر بودن نتیجه
                if (isNaN(result) || !isFinite(result)) {
                    console.error(`Formula ${formulaName} resulted in invalid value:`, result);
                    result = 0;
                }
            } catch (error) {
                console.error(`Error evaluating expression for formula ${formulaName}:`, error);
                console.error(`Expression: ${evalExpression}`);
                result = 0;
            }
            
            // گرد کردن نتیجه بر اساس نوع فرمول
            let roundedResult = result;
            if (formula.type === 'price' || formula.type === 'amount') {
                roundedResult = Math.round(result);
            } else if (formula.type === 'weight') {
                roundedResult = parseFloat(result.toFixed(3));
            } else if (formula.type === 'percent') {
                roundedResult = parseFloat(result.toFixed(2));
            }
            
            console.log(`Formula ${formulaName} calculated:`, {
                expression: evalExpression,
                result: result,
                roundedResult: roundedResult
            });
            
            return roundedResult;
        } catch (error) {
            console.error(`Error calculating formula ${formulaName}:`, error);
            return 0;
        }
    }
    
    /**
     * محاسبه تمام فرمول‌های یک ردیف
     * @param {HTMLElement} container - المان حاوی ردیف
     * @param {Array} groupFormulas - فرمول‌های گروه
     * @param {number} index - شاخص ردیف
     * @param {Object} product - اطلاعات محصول انتخاب شده
     */
    function _calculateFormulasForRow(container, groupFormulas, index, product) {
        if (!container || !Array.isArray(groupFormulas)) return;
        
        // جلوگیری از حلقه بی‌نهایت با استفاده از یک قفل
        if (container.dataset.calculating === 'true') {
            console.log('Calculation already in progress for row', index);
            return;
        }
        
        // قفل کردن محاسبات
        container.dataset.calculating = 'true';
        
        try {
            // جمع‌آوری مقادیر ورودی از فیلدهای موجود
            const inputValues = {};
            
            // یافتن تمام فیلدهای ورودی مورد نیاز
            const requiredFields = new Set();
            groupFormulas.forEach(formula => {
                (formula.fields || []).forEach(field => {
                    requiredFields.add(field);
                });
            });
            
            // خواندن مقادیر فیلدها
            requiredFields.forEach(fieldName => {
                const fieldSelector = `[name="items[${index}][${fieldName}]"]`;
                const fieldElement = container.querySelector(fieldSelector);
                
                if (fieldElement) {
                    let value;
                    
                    // خواندن مقدار با در نظر گرفتن AutoNumeric
                    if (typeof AutoNumeric !== 'undefined' && AutoNumeric.getAutoNumericElement(fieldElement)) {
                        try {
                            value = AutoNumeric.getNumber(fieldElement);
                        } catch (e) {
                            console.warn(`Error using AutoNumeric for field ${fieldName}:`, e);
                            value = fieldElement.value;
                        }
                    } else {
                        value = fieldElement.value;
                    }
                    
                    // تبدیل به عدد
                    if (typeof value === 'string') {
                        // تبدیل اعداد فارسی به انگلیسی
                        const persianDigits = {'۰':'0', '۱':'1', '۲':'2', '۳':'3', '۴':'4', '۵':'5', '۶':'6', '۷':'7', '۸':'8', '۹':'9',
                                              '٠':'0', '١':'1', '٢':'2', '٣':'3', '٤':'4', '٥':'5', '٦':'6', '٧':'7', '٨':'8', '٩':'9'};
                        
                        value = value.replace(/[۰-۹٠-٩]/g, digit => persianDigits[digit] || digit);
                        
                        // حذف جداکننده‌های هزارگان
                        value = value.replace(/[٬,،]/g, '').replace(/٫/g, '.');
                        
                        // تبدیل به عدد
                        value = parseFloat(value) || 0;
                    }
                    
                    inputValues[fieldName] = value;
                } else {
                    // اگر فیلد پیدا نشد، مقدار پیش‌فرض صفر
                    inputValues[fieldName] = 0;
                }
            });
            
            // افزودن اطلاعات محصول به مقادیر ورودی
            if (product) {
                inputValues.product_tax_enabled = product.tax_enabled ? 1 : 0;
                inputValues.product_tax_rate = product.tax_rate || 0;
                inputValues.product_vat_enabled = product.vat_enabled ? 1 : 0;
                inputValues.product_vat_rate = product.vat_rate || 0;
                
                // افزودن سایر ویژگی‌های محصول که ممکن است در فرمول‌ها استفاده شوند
                if (product.carat) inputValues.product_carat = product.carat;
                if (product.weight) inputValues.product_weight = product.weight;
                if (product.price) inputValues.product_price = product.price;
            }
            
            // افزودن قیمت مظنه به مقادیر ورودی
            const mazanehPriceElement = document.getElementById('mazaneh_price');
            if (mazanehPriceElement) {
                let mazanehPrice;
                
                if (typeof AutoNumeric !== 'undefined' && AutoNumeric.getAutoNumericElement(mazanehPriceElement)) {
                    try {
                        mazanehPrice = AutoNumeric.getNumber(mazanehPriceElement);
                    } catch (e) {
                        mazanehPrice = parseFloat(mazanehPriceElement.value.replace(/[^0-9.-]+/g, '')) || 0;
                    }
                } else {
                    mazanehPrice = parseFloat(mazanehPriceElement.value.replace(/[^0-9.-]+/g, '')) || 0;
                }
                
                inputValues.mazaneh_price = mazanehPrice;
            }
            
            console.log(`Input values for row ${index}:`, inputValues);
            
            // محاسبه و مقداردهی فیلدهای خروجی
            groupFormulas.forEach(formula => {
                if (!formula.output_field) return;
                
                const outputFieldName = formula.output_field;
                const outputFieldSelector = `[name="items[${index}][${outputFieldName}]"]`;
                const outputElement = container.querySelector(outputFieldSelector);
                
                if (outputElement) {
                    const result = _calculate(formula.name, inputValues);
                    
                    // مقداردهی نتیجه به فیلد خروجی
                    if (typeof AutoNumeric !== 'undefined' && AutoNumeric.getAutoNumericElement(outputElement)) {
                        try {
                            AutoNumeric.set(outputElement, result);
                        } catch (e) {
                            console.warn(`Error using AutoNumeric to set ${outputFieldName}:`, e);
                            outputElement.value = result;
                        }
                    } else {
                        outputElement.value = result;
                    }
                    
                    // ذخیره نتیجه در مقادیر ورودی برای استفاده در فرمول‌های بعدی
                    inputValues[outputFieldName] = result;
                    
                    // فراخوانی رویداد change برای فیلد خروجی با جلوگیری از حلقه بی‌نهایت
                    // به جای فراخوانی مستقیم رویداد، یک پرچم تنظیم می‌کنیم
                    outputElement.dataset.valueUpdated = 'true';
                } else {
                    console.warn(`Output field not found for formula ${formula.name}: ${outputFieldName}`);
                }
            });
            
            // به‌روزرسانی فیلدهای خلاصه
            SummaryManager.updateSummaryFields();
        } finally {
            // آزاد کردن قفل محاسبات
            container.dataset.calculating = 'false';
        }
    }
    
    /**
     * محاسبه خلاصه تراکنش
     * @returns {Object} - مقادیر خلاصه محاسبه شده
     */
    function _calculateTransactionSummary() {
        try {
            const rows = document.querySelectorAll('.transaction-item-row');
            
            let sumBaseItems = 0;
            let sumProfitWageFee = 0;
            let totalGeneralTax = 0;
            let sumBeforeVat = 0;
            let totalVat = 0;
            let finalPayable = 0;
            
            rows.forEach(row => {
                try {
                    // یافتن شاخص ردیف
                    const productSelect = row.querySelector('.product-select');
                    if (!productSelect) return;
                    
                    const nameAttr = productSelect.getAttribute('name');
                    const indexMatch = nameAttr ? nameAttr.match(/items\[(\d+)\]/) : null;
                    if (!indexMatch) return;
                    
                    const index = indexMatch[1];
                    
                    // خواندن مقادیر از فیلدهای مختلف گروه‌ها
                    const groups = FieldManager.getAllProductGroups();
                    
                    // مقادیر پایه برای این ردیف
                    let rowBaseValue = 0;
                    let rowProfitWageFee = 0;
                    let rowGeneralTax = 0;
                    let rowBeforeVat = 0;
                    let rowVat = 0;
                    let rowTotal = 0;
                    
                    // بررسی هر گروه برای یافتن فیلدهای مربوطه
                    groups.forEach(group => {
                        try {
                            // فیلدهای مبلغ پایه
                            const baseValueField = row.querySelector(`[name="items[${index}][item_total_price_${group}]"]`);
                            if (baseValueField && baseValueField.value) {
                                const value = _parseNumericValue(baseValueField);
                                if (!isNaN(value)) rowBaseValue += value;
                            }
                            
                            // فیلدهای سود/اجرت/کارمزد
                            const profitField = row.querySelector(`[name="items[${index}][item_profit_amount_${group}]"]`);
                            const feeField = row.querySelector(`[name="items[${index}][item_fee_amount_${group}]"]`);
                            const wageField = row.querySelector(`[name="items[${index}][item_manufacturing_fee_amount_${group}]"]`);
                            
                            if (profitField && profitField.value) {
                                const value = _parseNumericValue(profitField);
                                if (!isNaN(value)) rowProfitWageFee += value;
                            }
                            
                            if (feeField && feeField.value) {
                                const value = _parseNumericValue(feeField);
                                if (!isNaN(value)) rowProfitWageFee += value;
                            }
                            
                            if (wageField && wageField.value) {
                                const value = _parseNumericValue(wageField);
                                if (!isNaN(value)) rowProfitWageFee += value;
                            }
                            
                            // فیلدهای مالیات عمومی
                            const taxField = row.querySelector(`[name="items[${index}][item_general_tax_${group}]"]`);
                            if (taxField && taxField.value) {
                                const value = _parseNumericValue(taxField);
                                if (!isNaN(value)) rowGeneralTax += value;
                            }
                            
                            // فیلدهای ارزش افزوده
                            const vatField = row.querySelector(`[name="items[${index}][item_vat_${group}]"]`);
                            if (vatField && vatField.value) {
                                const value = _parseNumericValue(vatField);
                                if (!isNaN(value)) rowVat += value;
                            }
                        } catch (groupError) {
                            console.error(`Error processing group ${group} for row ${index}:`, groupError);
                        }
                    });
                    
                    // محاسبه جمع قبل از ارزش افزوده
                    rowBeforeVat = rowBaseValue + rowProfitWageFee + rowGeneralTax;
                    
                    // محاسبه مبلغ نهایی ردیف
                    rowTotal = rowBeforeVat + rowVat;
                    
                    // افزودن به جمع کل
                    sumBaseItems += rowBaseValue;
                    sumProfitWageFee += rowProfitWageFee;
                    totalGeneralTax += rowGeneralTax;
                    sumBeforeVat += rowBeforeVat;
                    totalVat += rowVat;
                    finalPayable += rowTotal;
                } catch (rowError) {
                    console.error('Error processing row:', rowError);
                }
            });
            
            return {
                sumBaseItems,
                sumProfitWageFee,
                totalGeneralTax,
                sumBeforeVat,
                totalVat,
                finalPayable
            };
        } catch (error) {
            console.error('Error calculating transaction summary:', error);
            return {
                sumBaseItems: 0,
                sumProfitWageFee: 0,
                totalGeneralTax: 0,
                sumBeforeVat: 0,
                totalVat: 0,
                finalPayable: 0
            };
        }
    }
    
    /**
     * تجزیه مقدار عددی از یک المان
     * @param {HTMLElement} element - المان حاوی مقدار عددی
     * @returns {number} - مقدار عددی
     */
    function _parseNumericValue(element) {
        if (!element) return 0;
        
        let value;
        
        if (typeof AutoNumeric !== 'undefined' && AutoNumeric.getAutoNumericElement(element)) {
            try {
                value = AutoNumeric.getNumber(element);
            } catch (e) {
                console.warn('Error using AutoNumeric:', e);
                value = element.value;
            }
        } else {
            value = element.value;
        }
        
        if (typeof value === 'string') {
            // تبدیل اعداد فارسی به انگلیسی
            const persianDigits = {'۰':'0', '۱':'1', '۲':'2', '۳':'3', '۴':'4', '۵':'5', '۶':'6', '۷':'7', '۸':'8', '۹':'9',
                                  '٠':'0', '١':'1', '٢':'2', '٣':'3', '٤':'4', '٥':'5', '٦':'6', '٧':'7', '٨':'8', '٩':'9'};
            
            value = value.replace(/[۰-۹٠-٩]/g, digit => persianDigits[digit] || digit);
            
            // حذف جداکننده‌های هزارگان
            value = value.replace(/[٬,،]/g, '').replace(/٫/g, '.');
        }
        
        return parseFloat(value) || 0;
    }
    
    // API عمومی
    return {
        init: _init,
        getFormulas: _getFormulas,
        getFormulasByGroup: _getFormulasByGroup,
        calculate: _calculate,
        calculateFormulasForRow: _calculateFormulasForRow,
        calculateTransactionSummary: _calculateTransactionSummary,
        parseNumericValue: _parseNumericValue
    };
})();
/**
 * ماژول مدیریت محصولات
 * مسئول مدیریت محصولات و دسته‌بندی‌های آنها
 */
const ProductManager = (function() {
    // متغیرهای خصوصی
    let _products = [];
    let _initialized = false;
    
    // نگاشت ID دسته‌بندی به گروه پایه
    const _categoryIdToBaseCategory = window.categoryIdToBaseCategory || {
        20: 'melted',
        21: 'coin',
        22: 'manufactured',
        23: 'goldbullion',
        27: 'jewelry',
        28: 'silverbullion'
    };
    
    /**
     * مقداردهی اولیه ماژول
     * @param {Array} products - آرایه محصولات
     */
    function _init(products) {
        if (_initialized) return;
        
        _products = products || [];
        _initialized = true;
        
        console.log('ProductManager initialized with', _products.length, 'products');
    }
    
    /**
     * دریافت تمام محصولات
     * @returns {Array} - آرایه محصولات
     */
    function _getAllProducts() {
        return _products;
    }
    
    /**
     * دریافت یک محصول بر اساس شناسه
     * @param {number} productId - شناسه محصول
     * @returns {Object|null} - محصول یا null
     */
    function _getProductById(productId) {
        if (!productId) return null;
        
        const id = parseInt(productId, 10);
        return _products.find(product => product.id === id) || null;
    }
    
    /**
     * دریافت گروه یک دسته‌بندی
     * @param {number} categoryId - شناسه دسته‌بندی
     * @returns {string} - نام گروه
     */
    function _getCategoryGroup(categoryId) {
        if (!categoryId) return 'unknown';
        
        const id = parseInt(categoryId, 10);
        return _categoryIdToBaseCategory[id] || 'unknown';
    }
    
    /**
     * دریافت گروه یک محصول
     * @param {Object} product - محصول
     * @returns {string} - نام گروه
     */
    function _getProductGroup(product) {
        if (!product) return 'unknown';
        
        // اگر محصول دارای ویژگی category باشد
        if (product.category && product.category.id) {
            return _getCategoryGroup(product.category.id);
        }
        
        // اگر محصول دارای ویژگی category_id باشد
        if (product.category_id) {
            return _getCategoryGroup(product.category_id);
        }
        
        // اگر محصول دارای ویژگی gold_product_type باشد
        if (product.gold_product_type) {
            const type = product.gold_product_type.toLowerCase();
            if (type.includes('coin')) return 'coin';
            if (type.includes('melted')) return 'melted';
            if (type.includes('manufactured')) return 'manufactured';
            if (type.includes('jewelry')) return 'jewelry';
            if (type.includes('bullion') && type.includes('gold')) return 'goldbullion';
            if (type.includes('bullion') && type.includes('silver')) return 'silverbullion';
        }
        
        return 'unknown';
    }
    
    /**
     * دریافت محصولات گروه‌بندی شده
     * @returns {Object} - محصولات گروه‌بندی شده
     */
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
     * پر کردن یک المان select با محصولات
     * @param {HTMLElement} selectElement - المان select
     * @param {number|null} selectedId - شناسه محصول انتخاب شده
     */
    function _fillProductSelect(selectElement, selectedId = null) {
        if (!selectElement) return;
        
        
        // حذف گزینه‌های موجود به جز گزینه پیش‌فرض
        while (selectElement.options.length > 1) {
            selectElement.remove(1);
        }
        
        // گروه‌بندی محصولات
        const groupedProducts = _getGroupedProducts();
        
        // افزودن گزینه‌ها به صورت گروه‌بندی شده
        for (const [group, products] of Object.entries(groupedProducts)) {
            if (products.length === 0) continue;
            
            // ایجاد گروه
            const optgroup = document.createElement('optgroup');
            optgroup.label = _getGroupDisplayName(group);
            
            // افزودن محصولات به گروه
            products.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = product.name;
                option.dataset.category = product.category_id || '';
                option.dataset.group = group;
                
                // افزودن ویژگی‌های اضافی به گزینه
                if (product.carat) option.dataset.carat = product.carat;
                if (product.weight) option.dataset.weight = product.weight;
                if (product.price) option.dataset.price = product.price;
                
                // انتخاب گزینه در صورت نیاز
                if (selectedId !== null && parseInt(product.id, 10) === parseInt(selectedId, 10)) {
                    option.selected = true;
                }
                
                optgroup.appendChild(option);
            });
            
            selectElement.appendChild(optgroup);
        }
    }
    
    /**
     * دریافت نام نمایشی یک گروه
     * @param {string} group - نام گروه
     * @returns {string} - نام نمایشی
     */
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
    
    // API عمومی
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
/**
 * ماژول مدیریت رابط کاربری
 * مسئول مدیریت رابط کاربری و تعامل با DOM
 */
const UIManager = (function() {
    // متغیرهای خصوصی
    let _elements = {};
    let _initialized = false;
    let _itemIndex = 0;
    
    /**
     * مقداردهی اولیه ماژول
     * @param {Object} elements - عناصر DOM اصلی
     */
    function _init(elements) {
        if (_initialized) return;
        
        _elements = elements || {};
        _initialized = true;
        
        console.log('UIManager initialized');
    }
    
    /**
     * نمایش پیام به کاربر
     * @param {string} type - نوع پیام (success, error, warning, info)
     * @param {string} text - متن پیام
     */
    function _showMessage(type, text) {
        // بررسی آیا پیام یک کلید از فایل messages.js است
        if (window.MESSAGES && typeof text === 'string' && window.MESSAGES[text]) {
            text = window.MESSAGES[text];
        }
        
        // حذف پیام‌های قبلی
        const existingAlerts = document.querySelectorAll('.alert-message');
        existingAlerts.forEach(alert => {
            alert.remove();
        });
        
        // ایجاد المان پیام
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-message alert-${type === 'error' ? 'danger' : type}`;
        alertDiv.innerHTML = text;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.left = '50%';
        alertDiv.style.transform = 'translateX(-50%)';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '300px';
        alertDiv.style.maxWidth = '80%';
        
        // افزودن دکمه بستن
        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close';
        closeButton.setAttribute('aria-label', 'Close');
        closeButton.style.position = 'absolute';
        closeButton.style.right = '10px';
        closeButton.style.top = '10px';
        
        closeButton.addEventListener('click', function() {
            alertDiv.remove();
        });
        
        alertDiv.appendChild(closeButton);
        document.body.appendChild(alertDiv);
        
        // حذف خودکار پیام پس از 5 ثانیه
        setTimeout(() => {
            if (document.body.contains(alertDiv)) {
                alertDiv.remove();
            }
        }, 5000);
    }
    
    /**
     * رندر کردن یک ردیف جدید
     * @param {Object|null} itemData - داده‌های ردیف
     * @param {number} index - شاخص ردیف
     * @param {Array} assayOffices - آرایه مراکز ری‌گیری
     */
    function _renderItemRow(itemData, index, assayOffices) {
        if (!_elements.itemsContainer || !_elements.itemRowTemplate) return;
        
        console.log(`رندر کردن ردیف ${index}${itemData ? ' با داده‌های موجود' : ' خالی'}...`);
        
        // اطمینان از معتبر بودن آرایه مراکز ری‌گیری
        if (!Array.isArray(assayOffices) || assayOffices.length === 0) {
            console.warn(window.MESSAGES?.invalid_assay_offices_data || 'داده‌های مراکز ری‌گیری نامعتبر یا خالی است');
            assayOffices = [{ id: 0, name: 'پیش‌فرض' }];
        } else {
            console.log(`استفاده از ${assayOffices.length} مرکز ری‌گیری برای ردیف ${index}`);
        }
        
        // ایجاد ردیف جدید
        const newRow = document.createElement('div');
        let rowHtml = _elements.itemRowTemplate.replace(/{index}/g, index);
        newRow.innerHTML = rowHtml;
        
        // افزودن ردیف به کانتینر
        _elements.itemsContainer.appendChild(newRow.firstElementChild);
        
        // دریافت المان ردیف اضافه شده
        const rowElement = _elements.itemsContainer.lastElementChild;
        
        // پر کردن select محصولات
        const productSelect = rowElement.querySelector('.product-select');
        if (productSelect) {
            ProductManager.fillProductSelect(productSelect, itemData ? itemData.product_id : null);
        }
        
        // اتصال رویدادها به ردیف
        _bindRowEvents(rowElement, index);
        
        // راه‌اندازی AutoNumeric برای فیلدهای عددی
        _initializeAllAutonumerics(rowElement);
        
        // اگر داده‌های ردیف موجود باشد، فیلدها را پر کن
        if (itemData) {
            console.log(`پر کردن داده‌های ردیف ${index}:`, itemData);
            
            // بارگذاری فیلدهای مناسب بر اساس محصول انتخاب شده
            const product = ProductManager.getProductById(itemData.product_id);
            if (product) {
                const group = ProductManager.getProductGroup(product);
                console.log(`محصول با شناسه ${itemData.product_id} از گروه ${group} انتخاب شده است`);
                
                // ایجاد فیلد select برای مراکز ری‌گیری فقط اگر محصول از گروه آبشده است
                if (group === 'melted') {
                    console.log(`ایجاد فیلد مرکز ری‌گیری برای ردیف ${index} (گروه آبشده)`);
                    
                    const dynamicFieldsRow = rowElement.querySelector('.dynamic-fields-row');
                    if (dynamicFieldsRow) {
                        // ایجاد div برای فیلد select مراکز ری‌گیری
                        const assayOfficeFieldContainer = document.createElement('div');
                        assayOfficeFieldContainer.className = 'col-md-3';
                        assayOfficeFieldContainer.innerHTML = `
                            <label class="form-label">مرکز ری‌گیری</label>
                            <select name="items[${index}][item_assay_office_melted]" class="form-select assay-office-select">
                                <option value="0">انتخاب مرکز ری‌گیری...</option>
                            </select>
                        `;
                        
                        // افزودن به ردیف فیلدهای داینامیک
                        dynamicFieldsRow.appendChild(assayOfficeFieldContainer);
                        
                        // استفاده از تابع _fillAssayOfficeSelects برای پر کردن فیلد select
                        _fillAssayOfficeSelects(assayOffices, assayOfficeFieldContainer, true);
                        
                        // انتخاب مرکز ری‌گیری مناسب اگر در داده‌ها موجود باشد
                        if (itemData.item_assay_office_melted) {
                            const assayOfficeSelect = assayOfficeFieldContainer.querySelector('select.assay-office-select');
                            if (assayOfficeSelect) {
                                console.log(`انتخاب مرکز ری‌گیری ${itemData.item_assay_office_melted} برای ردیف ${index}`);
                                assayOfficeSelect.value = itemData.item_assay_office_melted;
                            }
                        }
                    }
                }
                
                _updateItemFields(rowElement, product);
                
                // پر کردن مقادیر فیلدها
                for (const [key, value] of Object.entries(itemData)) {
                    const field = rowElement.querySelector(`[name="items[${index}][${key}]"]`);
                    if (field) {
                        if (field.tagName.toLowerCase() === 'select' && key.includes('item_assay_office_melted')) {
                            // فقط برای محصولات گروه آبشده
                            if (group === 'melted') {
                                // برای select‌های مراکز ری‌گیری، مقدار را به صورت خاص تنظیم می‌کنیم
                                const options = Array.from(field.options);
                                const matchingOption = options.find(opt => opt.value === String(value));
                                if (matchingOption) {
                                    field.value = value;
                                    console.log(`مقدار ${value} برای فیلد مرکز ری‌گیری در ردیف ${index} تنظیم شد`);
                                } else {
                                    console.warn(`گزینه با مقدار ${value} در فیلد ${field.name} پیدا نشد`);
                                    field.value = '0';
                                }
                            }
                        } else if (field.type === 'checkbox') {
                            field.checked = !!value;
                        } else if (typeof AutoNumeric !== 'undefined' && field.classList.contains('autonumeric')) {
                            try {
                                AutoNumeric.set(field, value);
                            } catch (e) {
                                console.warn(`خطا در استفاده از AutoNumeric برای تنظیم مقدار ${key}:`, e);
                                field.value = value;
                            }
                        } else {
                            // برای فیلدهای assay_office_id، اگر مقدار خالی است، 0 را تنظیم می‌کنیم
                            if (key.includes('item_assay_office_melted') && (!value || value === '')) {
                                field.value = '0';
                            } else {
                                field.value = value;
                            }
                        }
                    } else {
                        // اگر فیلد در DOM وجود ندارد، یک فیلد مخفی ایجاد کن
                        const hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.name = `items[${index}][${key}]`;
                        
                        // برای فیلدهای assay_office_id، اگر مقدار خالی است، 0 را تنظیم می‌کنیم
                        if (key.includes('item_assay_office_melted') && (!value || value === '')) {
                            hiddenField.value = '0';
                        } else {
                            hiddenField.value = value;
                        }
                        
                        rowElement.appendChild(hiddenField);
                    }
                }
                
                // محاسبه فرمول‌ها
                const groupFormulas = FormulaManager.getFormulasByGroup(group);
                FormulaManager.calculateFormulasForRow(rowElement, groupFormulas, index, product);
            }
        }
        
        // افزایش شاخص برای ردیف بعدی
        _itemIndex = Math.max(_itemIndex, index + 1);
    }
    
    /**
     * افزودن یک ردیف خالی جدید
     */
    function _addNewEmptyItemRow() {
        // دریافت مراکز ری‌گیری از ماژول اصلی
        const assayOffices = TransactionFormApp.getData().assayOffices || [];
        
        // اطمینان از معتبر بودن آرایه مراکز ری‌گیری
        if (!Array.isArray(assayOffices) || assayOffices.length === 0) {
            console.warn(window.MESSAGES?.invalid_assay_offices_data || 'داده‌های مراکز ری‌گیری نامعتبر یا خالی است');
            _renderItemRow(null, _itemIndex, [{ id: 0, name: 'پیش‌فرض' }]);
        } else {
            _renderItemRow(null, _itemIndex, assayOffices);
        }
    }
    
    /**
     * اتصال رویدادها به یک ردیف
     * @param {HTMLElement} rowElement - المان ردیف
     * @param {number} index - شاخص ردیف
     */
    function _bindRowEvents(rowElement, index) {
        if (!rowElement) return;
        
        // رویداد تغییر محصول
        const productSelect = rowElement.querySelector('.product-select');
        if (productSelect) {
            productSelect.addEventListener('change', function() {
                const productId = this.value;
                if (!productId) {
                    // پاک کردن فیلدهای داینامیک
                    const dynamicFieldsRow = rowElement.querySelector('.dynamic-fields-row');
                    if (dynamicFieldsRow) {
                        dynamicFieldsRow.innerHTML = '';
                    }
                    return;
                }
                
                const product = ProductManager.getProductById(productId);
                if (product) {
                    _updateItemFields(rowElement, product);
                }
            });
        }
        
        // رویداد حذف ردیف
        const removeButton = rowElement.querySelector('.remove-item-btn');
        if (removeButton) {
            removeButton.addEventListener('click', function() {
                rowElement.remove();
                
                // به‌روزرسانی فیلدهای خلاصه
                SummaryManager.updateSummaryFields();
            });
        }
    }
    
    /**
     * به‌روزرسانی فیلدهای ردیف بر اساس محصول انتخاب شده
     * @param {HTMLElement} row - المان ردیف
     * @param {Object} product - اطلاعات محصول
     */
    function _updateItemFields(row, product) {
        if (!row || !product) return;
        
        // یافتن شاخص ردیف
        const productSelect = row.querySelector('.product-select');
        if (!productSelect) return;
        
        const nameAttr = productSelect.getAttribute('name');
        const indexMatch = nameAttr.match(/items\[(\d+)\]/);
        if (!indexMatch) return;
        
        const index = indexMatch[1];
        console.log(`به‌روزرسانی فیلدهای ردیف ${index} برای محصول ${product.id} (${product.name})`);
        
        // یافتن گروه محصول
        const group = ProductManager.getProductGroup(product);
        console.log(`گروه محصول: ${group}`);
        
        // یافتن کانتینر فیلدهای داینامیک
        const dynamicFieldsRow = row.querySelector('.dynamic-fields-row');
        if (!dynamicFieldsRow) return;
        
        // تولید HTML فیلدها بر اساس گروه
        const fieldsHtml = FieldManager.getFieldsHtmlByGroup(group, index);
        
        // جایگزینی فیلدها
        dynamicFieldsRow.innerHTML = fieldsHtml;
        
        // راه‌اندازی AutoNumeric برای فیلدهای عددی جدید
        _initializeAllAutonumerics(dynamicFieldsRow);
        
        // مقداردهی اولیه فیلدها بر اساس محصول
        if (product.carat) {
            const caratField = row.querySelector(`[name="items[${index}][item_carat_${group}]"]`);
            if (caratField) {
                caratField.value = product.carat;
            }
        }
        
        // اطمینان از تنظیم مقدار assay_office_id - فقط برای آبشده
        if (group === 'melted') {
            console.log(`ایجاد فیلد مرکز ری‌گیری برای ردیف ${index} (گروه آبشده)`);
            
            // استفاده از تابع یکتا برای پر کردن فیلدهای مراکز ری‌گیری
            const assayOffices = TransactionFormApp.getData().assayOffices;
            
            // اطمینان از معتبر بودن آرایه مراکز ری‌گیری
            if (!Array.isArray(assayOffices) || assayOffices.length === 0) {
                console.warn(window.MESSAGES?.invalid_assay_offices_data || 'داده‌های مراکز ری‌گیری نامعتبر یا خالی است');
            } else {
                console.log(`استفاده از ${assayOffices.length} مرکز ری‌گیری برای ردیف ${index}`);
            }
            
            // ایجاد div برای فیلد select مراکز ری‌گیری اگر وجود ندارد
            let assayOfficeSelect = dynamicFieldsRow.querySelector(`[name="items[${index}][item_assay_office_melted]"]`);
            if (!assayOfficeSelect) {
                console.log(`فیلد مرکز ری‌گیری برای ردیف ${index} وجود ندارد. ایجاد فیلد جدید...`);
                
                const assayOfficeFieldContainer = document.createElement('div');
                assayOfficeFieldContainer.className = 'col-md-3';
                assayOfficeFieldContainer.innerHTML = `
                    <label class="form-label">مرکز ری‌گیری</label>
                    <select name="items[${index}][item_assay_office_melted]" class="form-select assay-office-select">
                        <option value="0">انتخاب مرکز ری‌گیری...</option>
                    </select>
                `;
                
                // افزودن به ردیف فیلدهای داینامیک
                dynamicFieldsRow.appendChild(assayOfficeFieldContainer);
                
                // استفاده از تابع _fillAssayOfficeSelects برای پر کردن فیلد select
                _fillAssayOfficeSelects(assayOffices, assayOfficeFieldContainer, true);
            } else {
                console.log(`فیلد مرکز ری‌گیری برای ردیف ${index} یافت شد.`);
                // استفاده از تابع _fillAssayOfficeSelects برای پر کردن فیلد select
                _fillAssayOfficeSelects(assayOffices, dynamicFieldsRow, true);
            }
        } else {
            // برای سایر گروه‌ها، حذف فیلدهای مراکز ری‌گیری اگر وجود دارند
            const existingAssayOfficeField = dynamicFieldsRow.querySelector(`[name="items[${index}][item_assay_office_melted]"]`);
            if (existingAssayOfficeField) {
                const container = existingAssayOfficeField.closest('.col-md-3');
                if (container) {
                    console.log(`حذف فیلد مرکز ری‌گیری از ردیف ${index} (گروه ${group})`);
                    container.remove();
                }
            }
        }
        
        // تنظیم فیلدهای مالیات و ارزش افزوده
        const taxEnabled = product.tax_enabled || false;
        const vatEnabled = product.vat_enabled || false;
        const taxRate = product.tax_rate || 0;
        const vatRate = product.vat_rate || 0;
        
        // ذخیره مقادیر در فیلدهای مخفی
        const taxField = row.querySelector(`[name="items[${index}][item_general_tax_${group}]"]`);
        const vatField = row.querySelector(`[name="items[${index}][item_vat_${group}]"]`);
        
        if (taxField) {
            // اضافه کردن ویژگی‌های data برای استفاده در محاسبات
            taxField.dataset.taxEnabled = taxEnabled ? '1' : '0';
            taxField.dataset.taxRate = taxRate;
            
            // فقط اگر مالیات فعال است، فیلد را نمایش دهیم
            if (taxEnabled) {
                const taxFieldContainer = document.createElement('div');
                taxFieldContainer.className = 'col-md-3 mt-2';
                taxFieldContainer.innerHTML = `
                    <label class="form-label">مالیات (${taxRate}%)</label>
                    <input type="text" name="items[${index}][item_general_tax_${group}]" 
                        class="form-control autonumeric" readonly>
                `;
                
                dynamicFieldsRow.appendChild(taxFieldContainer);
                // راه‌اندازی AutoNumeric برای فیلد جدید
                _initializeAllAutonumerics(taxFieldContainer);
            }
        }
        
        if (vatField) {
            // اضافه کردن ویژگی‌های data برای استفاده در محاسبات
            vatField.dataset.vatEnabled = vatEnabled ? '1' : '0';
            vatField.dataset.vatRate = vatRate;
            
            // فقط اگر ارزش افزوده فعال است، فیلد را نمایش دهیم
            if (vatEnabled) {
                const vatFieldContainer = document.createElement('div');
                vatFieldContainer.className = 'col-md-3 mt-2';
                vatFieldContainer.innerHTML = `
                    <label class="form-label">ارزش افزوده (${vatRate}%)</label>
                    <input type="text" name="items[${index}][item_vat_${group}]" 
                        class="form-control autonumeric" readonly>
                `;
                
                dynamicFieldsRow.appendChild(vatFieldContainer);
                // راه‌اندازی AutoNumeric برای فیلد جدید
                _initializeAllAutonumerics(vatFieldContainer);
            }
        }
        
        // اتصال رویدادهای محاسبه
        _bindCalculationEvents(row, group, index);
    }
    
    /**
     * اتصال رویدادهای محاسبه به فیلدها
     * @param {HTMLElement} container - المان حاوی فیلدها
     * @param {string} group - نام گروه
     * @param {number} index - شاخص ردیف
     */
    function _bindCalculationEvents(container, group, index) {
        if (!container || !group) return;
        
        // دریافت فرمول‌های گروه
        const groupFormulas = FormulaManager.getFormulasByGroup(group);
        
        if (!groupFormulas || groupFormulas.length === 0) return;
        
        // یافتن تمام فیلدهای ورودی مورد نیاز
        const requiredFields = new Set();
        groupFormulas.forEach(formula => {
            (formula.fields || []).forEach(field => {
                requiredFields.add(field);
            });
        });
        
        // اتصال رویداد change به فیلدهای ورودی
        requiredFields.forEach(fieldName => {
            const fieldSelector = `[name="items[${index}][${fieldName}]"]`;
            const fieldElement = container.querySelector(fieldSelector);
            
            if (fieldElement) {
                fieldElement.addEventListener('change', function(event) {
                    // بررسی اینکه آیا این تغییر توسط محاسبات ایجاد شده است
                    if (this.dataset.valueUpdated === 'true') {
                        this.dataset.valueUpdated = 'false';
                        return;
                    }
                    
                    // دریافت محصول انتخاب شده
                    const productSelect = container.querySelector('.product-select');
                    const productId = productSelect ? productSelect.value : null;
                    const product = ProductManager.getProductById(productId);
                    
                    // محاسبه فرمول‌ها
                    FormulaManager.calculateFormulasForRow(container, groupFormulas, index, product);
                });
                
                // برای فیلدهای عددی، رویداد input نیز اضافه می‌شود
                if (fieldElement.type === 'number' || fieldElement.classList.contains('autonumeric')) {
                    fieldElement.addEventListener('input', function(event) {
                        // بررسی اینکه آیا این تغییر توسط محاسبات ایجاد شده است
                        if (this.dataset.valueUpdated === 'true') {
                            this.dataset.valueUpdated = 'false';
                            return;
                        }
                        
                        // دریافت محصول انتخاب شده
                        const productSelect = container.querySelector('.product-select');
                        const productId = productSelect ? productSelect.value : null;
                        const product = ProductManager.getProductById(productId);
                        
                        // محاسبه فرمول‌ها
                        FormulaManager.calculateFormulasForRow(container, groupFormulas, index, product);
                    });
                }
            }
        });
        
        // همچنین برای فیلدهای خروجی نیز رویدادها را اضافه می‌کنیم
        groupFormulas.forEach(formula => {
            if (!formula.output_field) return;
            
            const outputFieldName = formula.output_field;
            const outputFieldSelector = `[name="items[${index}][${outputFieldName}]"]`;
            const outputElement = container.querySelector(outputFieldSelector);
            
            if (outputElement) {
                // اضافه کردن یک MutationObserver برای تشخیص تغییرات مقدار
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                            // بررسی اینکه آیا این تغییر توسط محاسبات ایجاد شده است
                            if (outputElement.dataset.valueUpdated === 'true') {
                                outputElement.dataset.valueUpdated = 'false';
                                return;
                            }
                            
                            // به‌روزرسانی فیلدهای خلاصه
                            SummaryManager.updateSummaryFields();
                        }
                    });
                });
                
                observer.observe(outputElement, { attributes: true });
            }
        });
    }
    
    /**
     * راه‌اندازی AutoNumeric برای یک المان
     * @param {HTMLElement} element - المان
     */
    function _initializeAutonumeric(element) {
        if (!element || typeof AutoNumeric === 'undefined') return;
        
        // بررسی نوع المان
        if (element.classList.contains('autonumeric')) {
            // بررسی اینکه قبلاً AutoNumeric برای این المان را ایجاد نشده باشد
            if (!AutoNumeric.getAutoNumericElement(element)) {
                // تنظیمات پیش‌فرض
                let options = {
                    digitGroupSeparator: '٬',
                    decimalCharacter: '.',
                    decimalPlaces: 0,
                    unformatOnSubmit: true,
                    selectOnFocus: false, // تغییر به false برای جلوگیری از تداخل با caretPositionOnFocus
                    modifyValueOnWheel: false
                };
                
                // بررسی تنظیمات سفارشی
                const customOptions = element.dataset.autonumericOptions;
                if (customOptions) {
                    try {
                        const parsedOptions = JSON.parse(customOptions);
                        options = { ...options, ...parsedOptions };
                    } catch (e) {
                        console.warn('Error parsing AutoNumeric options:', e);
                    }
                }
                
                // تنظیم تعداد ارقام اعشار بر اساس نوع فیلد
                if (element.name.includes('weight')) {
                    options.decimalPlaces = 3;
                } else if (element.name.includes('percent')) {
                    options.decimalPlaces = 2;
                }
                
                // تنظیم ویژه برای فیلد مظنه
                if (element.id === 'mazaneh_price') {
                    options = {
                        ...options,
                        digitGroupSeparator: '٬',
                        decimalPlaces: 0,
                        unformatOnSubmit: true,
                        selectOnFocus: false,
                        modifyValueOnWheel: false
                    };
                }
                
                // راه‌اندازی AutoNumeric
                try {
                    new AutoNumeric(element, options);
                } catch (e) {
                    console.warn('Error initializing AutoNumeric:', e);
                }
            }
        }
    }
    
    /**
     * راه‌اندازی AutoNumeric برای تمام المان‌های یک کانتینر
     * @param {HTMLElement} context - المان کانتینر
     */
    function _initializeAllAutonumerics(context = document) {
        if (typeof AutoNumeric === 'undefined') return;
        
        const elements = context.querySelectorAll('.autonumeric');
        elements.forEach(_initializeAutonumeric);
    }
    
    /**
     * این تابع یکتا مسئول پر کردن تمام فیلدهای select مراکز ری‌گیری در صفحه است
     * @param {Array} offices - آرایه مراکز ری‌گیری
     * @param {HTMLElement|null} container - المان حاوی فیلدها (اگر null باشد، کل صفحه بررسی می‌شود)
     * @param {boolean} onlyMeltedGroup - فقط فیلدهای مربوط به گروه آبشده را پر کند
     */
    function _fillAssayOfficeSelects(offices, container = null, onlyMeltedGroup = false) {
        // اطمینان از معتبر بودن داده‌های ورودی
        if (!Array.isArray(offices) || offices.length === 0) {
            console.warn(window.MESSAGES?.invalid_assay_offices_data || 'داده‌های مراکز ری‌گیری نامعتبر یا خالی است');
            offices = [{ id: 0, name: 'پیش‌فرض' }];
        }
        
        console.log('پر کردن فیلدهای مراکز ری‌گیری با:', offices.length, 'مرکز', onlyMeltedGroup ? '(فقط برای گروه آبشده)' : '');
        
        // تعیین محدوده جستجو (کل صفحه یا یک کانتینر خاص)
        const searchContext = container || document;
        
        // انتخاب سلکتور مناسب بر اساس پارامتر onlyMeltedGroup
        const selector = onlyMeltedGroup ? 
            'select[name*="item_assay_office_melted"]' : 
            'select[name*="item_assay_office"], select.assay-office-select';
            
        const selects = searchContext.querySelectorAll(selector);
        console.log(`${selects.length} فیلد مرکز ری‌گیری پیدا شد`, onlyMeltedGroup ? '(فقط برای گروه آبشده)' : '');
        
        if (selects.length === 0) {
            console.log('هیچ فیلد مرکز ری‌گیری پیدا نشد');
            return;
        }
        
        // پر کردن تمام فیلدهای پیدا شده
        selects.forEach(select => {
            // ذخیره مقدار فعلی
            const currentValue = select.value;
            console.log(`پر کردن فیلد ${select.name} با مقدار فعلی ${currentValue}`);
            
            // حذف گزینه‌های موجود
            while (select.options.length > 0) {
                select.remove(0);
            }
            
            // اضافه کردن گزینه پیش‌فرض
            const defaultOption = document.createElement('option');
            defaultOption.value = '0';
            defaultOption.textContent = window.MESSAGES?.assay_office_select_default || 'انتخاب مرکز ری‌گیری...';
            select.appendChild(defaultOption);
            
            // افزودن گزینه‌ها
            offices.forEach(office => {
                const option = document.createElement('option');
                option.value = office.id;
                option.textContent = office.name;
                
                // انتخاب گزینه قبلی اگر وجود داشته باشد
                if (currentValue && currentValue == office.id) {
                    option.selected = true;
                }
                
                select.appendChild(option);
            });
            
            console.log(`فیلد ${select.name} با ${offices.length} مرکز ری‌گیری پر شد`);
        });
    }
    
    // API عمومی
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
        fillAssayOfficeSelects: _fillAssayOfficeSelects // استفاده از تابع اصلاح شده
    };
})();
/**
 * ماژول مدیریت خلاصه
 * مسئول مدیریت و به‌روزرسانی فیلدهای خلاصه مالی
 */
const SummaryManager = (function() {
    // متغیرهای خصوصی
    let _initialized = false;
    
    /**
     * مقداردهی اولیه ماژول
     */
    function _init() {
        if (_initialized) return;
        
        _initialized = true;
        
        console.log('SummaryManager initialized');
    }
    
    /**
     * به‌روزرسانی فیلدهای خلاصه
     */
    function _updateSummaryFields() {
        // جلوگیری از حلقه بی‌نهایت با استفاده از یک قفل
        const summaryContainer = document.querySelector('.card.mt-4');
        if (summaryContainer && summaryContainer.dataset.updating === 'true') {
            console.log('Summary update already in progress');
            return;
        }
        
        // قفل کردن به‌روزرسانی
        if (summaryContainer) {
            summaryContainer.dataset.updating = 'true';
        }
        
        try {
            // محاسبه خلاصه تراکنش
            const summary = FormulaManager.calculateTransactionSummary();
            
            // دریافت عناصر خلاصه
            const elements = TransactionFormApp.getElements().summaryElements;
            
            // به‌روزرسانی مقادیر
            if (elements.sumBaseItems) {
                elements.sumBaseItems.textContent = _formatNumber(summary.sumBaseItems);
            }
            
            if (elements.sumProfitWageFee) {
                elements.sumProfitWageFee.textContent = _formatNumber(summary.sumProfitWageFee);
            }
            
            if (elements.totalGeneralTax) {
                elements.totalGeneralTax.textContent = _formatNumber(summary.totalGeneralTax);
            }
            
            if (elements.sumBeforeVat) {
                elements.sumBeforeVat.textContent = _formatNumber(summary.sumBeforeVat);
            }
            
            if (elements.totalVat) {
                elements.totalVat.textContent = _formatNumber(summary.totalVat);
            }
            
            if (elements.finalPayable) {
                elements.finalPayable.textContent = _formatNumber(summary.finalPayable);
            }
            
            // ذخیره مقادیر در فیلدهای مخفی
            _setHiddenField('total_items_value_rials', summary.sumBaseItems);
            _setHiddenField('total_profit_wage_commission_rials', summary.sumProfitWageFee);
            _setHiddenField('total_general_tax_rials', summary.totalGeneralTax);
            _setHiddenField('total_before_vat_rials', summary.sumBeforeVat);
            _setHiddenField('total_vat_rials', summary.totalVat);
            _setHiddenField('final_payable_amount_rials', summary.finalPayable);
        } finally {
            // آزاد کردن قفل
            if (summaryContainer) {
                summaryContainer.dataset.updating = 'false';
            }
        }
    }
    
    /**
     * قالب‌بندی عدد به صورت فارسی
     * @param {number} number - عدد
     * @returns {string} - عدد قالب‌بندی شده
     */
    function _formatNumber(number) {
        if (isNaN(number)) return '۰';
        
        // تبدیل به رشته با جداکننده هزارگان
        const formattedNumber = number.toLocaleString('fa-IR');
        
        return formattedNumber;
    }
    
    /**
     * تنظیم مقدار یک فیلد مخفی
     * @param {string} fieldName - نام فیلد
     * @param {*} value - مقدار
     */
    function _setHiddenField(fieldName, value) {
        let field = document.querySelector(`input[name="${fieldName}"]`);
        
        // اگر فیلد وجود نداشت، آن را ایجاد کن
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = fieldName;
            
            const form = document.getElementById('transaction-form');
            if (form) {
                form.appendChild(field);
            }
        }
        
        // تنظیم مقدار
        field.value = value;
    }
    
    // API عمومی
    return {
        init: _init,
        updateSummaryFields: _updateSummaryFields,
        formatNumber: _formatNumber
    };
})();
/**
 * ماژول سرویس داده
 * مسئول مدیریت داده‌ها
 */
const DataService = (function() {
    // متغیرهای خصوصی
    let _initialized = false;
    
    /**
     * مقداردهی اولیه ماژول
     */
    function _init() {
        if (_initialized) return;
        
        _initialized = true;
        
        console.log('DataService initialized');
    }
    
    /**
     * بارگذاری مراکز ری‌گیری
     * @returns {Promise<Array>} - وعده آرایه مراکز ری‌گیری
     */
    function _loadAssayOffices() {
        return new Promise((resolve, reject) => {
            try {
                console.log('شروع بارگذاری مراکز ری‌گیری...');
                
                // ابتدا بررسی می‌کنیم آیا داده‌ها در صفحه موجود است
                if (window.assayOfficesData && Array.isArray(window.assayOfficesData) && window.assayOfficesData.length > 0) {
                    console.log('مراکز ری‌گیری از داده‌های صفحه بارگذاری شدند:', window.assayOfficesData.length);
                    console.log('داده‌های مراکز ری‌گیری:', JSON.stringify(window.assayOfficesData));
                    resolve(window.assayOfficesData);
                    return;
                }
                
                // اگر داده‌ها در صفحه موجود نبود، از سرور بارگذاری می‌کنیم
                console.log('بارگذاری مراکز ری‌گیری از سرور...');
                
                // آدرس API را از تنظیمات می‌گیریم یا مقدار پیش‌فرض استفاده می‌کنیم
                const baseUrl = window.baseUrl || '';
                const url = baseUrl + '/app/assay-offices/list';
                
                console.log('درخواست مراکز ری‌گیری از آدرس:', url);
                
                fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    console.log('وضعیت پاسخ سرور:', response.status);
                    if (!response.ok) {
                        throw new Error(`خطای HTTP: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('پاسخ سرور برای مراکز ری‌گیری:', data);
                    
                    if (data && Array.isArray(data.data)) {
                        console.log('مراکز ری‌گیری از سرور بارگذاری شدند (data.data):', data.data.length);
                        // ذخیره داده‌ها در متغیر جهانی برای استفاده‌های بعدی
                        window.assayOfficesData = data.data;
                        resolve(data.data);
                    } else if (data && Array.isArray(data)) {
                        console.log('مراکز ری‌گیری از سرور بارگذاری شدند (آرایه مستقیم):', data.length);
                        window.assayOfficesData = data;
                        resolve(data);
                    } else {
                        console.warn('داده‌های نامعتبر مراکز ری‌گیری از سرور:', data);
                        // در صورت خطا، یک مرکز ری‌گیری پیش‌فرض ایجاد می‌کنیم
                        const defaultOffices = [{ id: 0, name: 'پیش‌فرض' }];
                        window.assayOfficesData = defaultOffices;
                        resolve(defaultOffices);
                    }
                })
                .catch(error => {
                    console.error('خطا در بارگذاری مراکز ری‌گیری از سرور:', error);
                    // در صورت خطا، یک مرکز ری‌گیری پیش‌فرض ایجاد می‌کنیم
                    const defaultOffices = [{ id: 0, name: 'پیش‌فرض' }];
                    window.assayOfficesData = defaultOffices;
                    resolve(defaultOffices);
                });
            } catch (error) {
                console.error('خطای داخلی در بارگذاری مراکز ری‌گیری:', error);
                // در صورت خطا، یک مرکز ری‌گیری پیش‌فرض ایجاد می‌کنیم
                const defaultOffices = [{ id: 0, name: 'پیش‌فرض' }];
                window.assayOfficesData = defaultOffices;
                resolve(defaultOffices);
            }
        });
    }
    
    // API عمومی
    return {
        init: _init,
        loadAssayOffices: _loadAssayOffices
    };
})();

/**
 * ماژول اعتبارسنجی
 * مسئول اعتبارسنجی فرم و فیلدهای آن
 */
const ValidationManager = (function() {
    // متغیرهای خصوصی
    let _initialized = false;
    
    /**
     * مقداردهی اولیه ماژول
     */
    function _init() {
        if (_initialized) return;
        
        _initialized = true;
        
        console.log('ValidationManager initialized');
    }
    
    /**
     * اعتبارسنجی کل فرم
     * @returns {boolean} - آیا فرم معتبر است
     */
    function _validateForm() {
        const form = document.getElementById('transaction-form');
        if (!form) return false;
        
        // بررسی فیلدهای اصلی فرم
        const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        // بررسی ردیف‌های اقلام
        const itemRows = document.querySelectorAll('.transaction-item-row');
        if (itemRows.length === 0) {
            UIManager.showMessage('error', 'حداقل یک قلم معامله باید وجود داشته باشد.');
            isValid = false;
        } else {
            itemRows.forEach((row, index) => {
                const rowValid = _validateRow(row, index);
                if (!rowValid) {
                    isValid = false;
                }
            });
        }
        
        return isValid;
    }
    
    /**
     * اعتبارسنجی یک ردیف
     * @param {HTMLElement} row - المان ردیف
     * @param {number} index - شاخص ردیف
     * @returns {boolean} - آیا ردیف معتبر است
     */
    function _validateRow(row, index) {
        if (!row) return false;
        
        // بررسی انتخاب محصول
        const productSelect = row.querySelector('.product-select');
        if (!productSelect || !productSelect.value) {
            productSelect?.classList.add('is-invalid');
            return false;
        }
        
        // بررسی فیلدهای required
        const requiredFields = row.querySelectorAll('input[required], select[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    }
    
    /**
     * پاکسازی خطاهای اعتبارسنجی
     */
    function _clearValidationErrors() {
        const invalidFields = document.querySelectorAll('.is-invalid');
        invalidFields.forEach(field => {
            field.classList.remove('is-invalid');
        });
    }
    
    // API عمومی
    return {
        init: _init,
        validateForm: _validateForm,
        validateRow: _validateRow,
        clearValidationErrors: _clearValidationErrors
    };
})();

// راه‌اندازی ماژول‌های مستقل
document.addEventListener('DOMContentLoaded', function() {
    DataService.init();
    SummaryManager.init();
});

/**
 * هماهنگ کردن وضعیت تحویل با نوع معامله
 * برای معامله خرید، فقط وضعیت "منتظر دریافت" قابل انتخاب است
 * برای معامله فروش، فقط وضعیت "منتظر تحویل" قابل انتخاب است
 */
function _synchronizeDeliveryStatusWithTransactionType() {
    const transactionTypeSelect = document.getElementById('transaction_type');
    const deliveryStatusSelect = document.getElementById('delivery_status');
    
    if (!transactionTypeSelect || !deliveryStatusSelect) {
        console.warn('عناصر نوع معامله یا وضعیت تحویل یافت نشد.');
        return;
    }
    
    const transactionType = transactionTypeSelect.value;
    
    // بازنشانی همه گزینه‌ها
    Array.from(deliveryStatusSelect.options).forEach(option => {
        option.disabled = false;
    });
    
    // اعمال محدودیت‌ها بر اساس نوع معامله
    if (transactionType === 'buy') {
        // برای خرید، "منتظر تحویل" غیرفعال می‌شود
        Array.from(deliveryStatusSelect.options).forEach(option => {
            if (option.value === 'pending_delivery') {
                option.disabled = true;
                
                // اگر این گزینه انتخاب شده بود، به گزینه "منتظر دریافت" تغییر می‌دهیم
                if (deliveryStatusSelect.value === 'pending_delivery') {
                    deliveryStatusSelect.value = 'pending_receipt';
                }
            }
        });
    } else if (transactionType === 'sell') {
        // برای فروش، "منتظر دریافت" غیرفعال می‌شود
        Array.from(deliveryStatusSelect.options).forEach(option => {
            if (option.value === 'pending_receipt') {
                option.disabled = true;
                
                // اگر این گزینه انتخاب شده بود، به گزینه "منتظر تحویل" تغییر می‌دهیم
                if (deliveryStatusSelect.value === 'pending_receipt') {
                    deliveryStatusSelect.value = 'pending_delivery';
                }
            }
        });
    }
}

