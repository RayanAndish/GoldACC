/**
 * transaction-form.js (Refactored & Final)
 * Main module for the unified transaction form (add and edit).
 * Version: 5.4.0
 * Date: 1403/04/22
 *
 * This version fixes all reported visual and logical issues:
 * - Corrects thousand separators for all numeric fields.
 * - Enforces correct decimal places for all fields.
 * - Conditionally renders tax fields based on product settings.
 * - Stabilizes event handling to prevent script crashes.
 */

// --- Helper & Utility Module ---
const Utils = {
    formatRial(value) {
        const num = parseFloat(value) || 0;
        return new Intl.NumberFormat('fa-IR').format(Math.round(num));
    },
    getNumericValue(value) {
        if (!value && value !== 0) return 0;
        if (typeof value === 'number') return value;
        if (typeof value !== 'string') return parseFloat(value) || 0;
        
        // تبدیل اعداد فارسی به انگلیسی
        let sanitized = value.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
        
        // حذف جداکننده‌های هزارگان
        sanitized = sanitized.replace(/[٬،,]/g, '');
        
        // تبدیل ممیز فارسی به نقطه
        sanitized = sanitized.replace(/٫/g, '.');
        
        // حذف فاصله‌های اضافی
        sanitized = sanitized.trim();
        
        const num = parseFloat(sanitized);
        return isNaN(num) ? 0 : num;
    },
    showMessage(type, message, errors = []) {
        const container = document.getElementById('form-messages');
        if (!container) return;
        let errorList = '';
        if (errors.length > 0) {
            errorList = `<ul class="mb-0 mt-2 small">${errors.map(e => `<li>${e}</li>`).join('')}</ul>`;
        }
        container.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                ${errorList}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },
    debounce(func, delay) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), delay);
        };
    }
};

// --- Field & Formula Management Module ---
const DataManager = {
    fields: [],
    formulas: [],
    products: [],
    productsById: {},
    assayOffices: [],

    init(data) {
        console.log('Initializing DataManager with:', data);
        this.fields = data.fields || [];
        this.formulas = data.formulas || [];
        this.products = data.products || [];
        this.assayOffices = data.assayOffices || [];
        
        console.log('Fields loaded:', this.fields.length);
        console.log('Formulas loaded:', this.formulas.length);
        console.log('Products loaded:', this.products.length);
        
        if (this.products.length > 0) {
            this.products.forEach(p => { this.productsById[p.id] = p; });
        }
        
        // نمایش فرمول‌ها برای اشکال‌زدایی
        if (this.formulas.length > 0) {
            console.log('Available formulas:', this.formulas.map(f => ({
                group: f.group,
                target: f.target_field,
                expression: f.expression
            })));
        }
    },
    getProductById(id) {
        return this.productsById[id] || null;
    },
    getProductGroup(product) {
        if (product && product.category && product.category.base_category) {
            return product.category.base_category.toLowerCase();
        }
        return 'unknown';
    },
    getFieldsByGroup(group) {
        if (!group) return [];
        const groupLower = group.toLowerCase();
        return this.fields.filter(field => field.group && field.group.toLowerCase() === groupLower);
    },
    getFormulasByGroup(group) {
        if (!group) return [];
        const groupLower = group.toLowerCase();
        return this.formulas.filter(f => f.group && f.group.toLowerCase() === groupLower);
    },
    calculateExpression(expression, context) {
        try {
            if (!expression) {
                console.warn('Empty expression');
                return 0;
            }
            
            console.log('Calculating expression:', expression);
            console.log('Original context:', context);

            // تبدیل همه مقادیر به عددی و نرمال‌سازی نام فیلدها
            const numericContext = {};
            for (let [key, value] of Object.entries(context)) {
                // حذف پیشوند item_ و پسوند گروه از نام فیلدها
                key = key.replace(/^item_/, '').replace(/_(?:melted|manufactured|coin|bullion|stone)$/, '');
                
                // تبدیل مقدار به عدد
                if (typeof value === 'string') {
                    value = value.replace(/[٬،,]/g, '');
                }
                numericContext[key] = Utils.getNumericValue(value);
                console.log(`${key}: ${value} -> ${numericContext[key]}`);
            }

            // تعریف متغیرهای محاسباتی
            const contextDeclarations = Object.entries(numericContext)
                .map(([key, value]) => `const ${key} = ${value};`)
                .join('\n');

            // اضافه کردن توابع ریاضی پایه
            const mathFunctions = `
                const round = (num, decimals = 0) => {
                    if (typeof num !== 'number') num = Number(num);
                    if (isNaN(num)) return 0;
                    const factor = Math.pow(10, decimals);
                    return Math.round(num * factor) / factor;
                };
                const floor = (num) => Math.floor(Number(num) || 0);
                const ceil = (num) => Math.ceil(Number(num) || 0);
                const abs = (num) => Math.abs(Number(num) || 0);
                const max = (...args) => Math.max(...args.map(n => Number(n) || 0));
                const min = (...args) => Math.min(...args.map(n => Number(n) || 0));
                const multiply = (a, b) => (Number(a) || 0) * (Number(b) || 0);
                const divide = (a, b) => {
                    a = Number(a) || 0;
                    b = Number(b) || 0;
                    return b === 0 ? 0 : a / b;
                };
                const percent = (value, percent) => multiply(value, divide(percent, 100));
            `;

            // ساخت و اجرای تابع محاسباتی
            const code = `
                ${mathFunctions}
                ${contextDeclarations}
                try {
                    const result = ${expression};
                    if (isNaN(result) || !isFinite(result)) {
                        console.error('Invalid result:', result);
                        return 0;
                    }
                    console.log('Formula result:', result);
                    return result;
                } catch (e) {
                    console.error('Formula evaluation error:', e);
                    return 0;
                }
            `;

            const func = new Function(code);
            const result = func();
            
            console.log('Final result:', result);
            return typeof result === 'number' ? result : 0;

        } catch (e) {
            console.error('Error calculating expression:', expression, e);
            console.error('Context:', context);
            return 0;
        }
    }
};

// --- UI Management Module ---
const UIManager = {
    elements: {},
    itemIndex: 0,

    init() {
        this.elements = {
            form: document.getElementById('transaction-form'),
            itemsContainer: document.getElementById('transaction-items-container'),
            addItemButton: document.getElementById('add-transaction-item'),
            itemRowTemplate: document.getElementById('item-row-template'),
            transactionTypeSelect: document.getElementById('transaction_type'),
            deliveryStatusSelect: document.getElementById('delivery_status'),
            submitButton: document.getElementById('submit-btn'),
            submitButtonSpinner: document.querySelector('#submit-btn .spinner-border'),
            summaryTotalItems: document.getElementById('summary-total_items_value_rials'),
            summaryTotalProfit: document.getElementById('summary-total_profit_wage_commission_rials'),
            summaryTotalTax: document.getElementById('summary-total_general_tax_rials'),
            summaryBeforeVat: document.getElementById('summary-total_before_vat_rials'),
            summaryTotalVat: document.getElementById('summary-total_vat_rials'),
            summaryFinalPayable: document.getElementById('summary-final_payable_amount_rials'),
        };
    },
    renderNewItemRow(itemData = null) {
        if (!this.elements.itemRowTemplate) return null;
        const templateContent = this.elements.itemRowTemplate.innerHTML.replace(/{index}/g, this.itemIndex);
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = templateContent;
        const newRow = tempDiv.firstElementChild;
        this.elements.itemsContainer.appendChild(newRow);
        this.populateProductSelect(newRow.querySelector('.product-select'), itemData?.product_id);
        // اگر محصول انتخاب شده باشد، فیلدهای تخصصی و مقداردهی انجام شود
        if (itemData && itemData.product_id) {
            const product = DataManager.getProductById(itemData.product_id);
            if (product) {
                this.renderDynamicFields(newRow, product);
                this.populateRowFields(newRow, itemData, product);
            }
        }
        // مقداردهی AutoNumeric و اتصال رویداد محاسباتی برای کل ردیف (همه فیلدهای عددی)
        this.initAutoNumeric(newRow);
        const inputs = newRow.querySelectorAll('input.autonumeric, select');
        inputs.forEach(input => {
            const recalcHandler = Utils.debounce(() => {
                console.log('Field changed:', input.name);
                TransactionFormApp.recalculateAll();
            }, 100);

            input.addEventListener('change', recalcHandler);
            
            if (input.classList.contains('autonumeric')) {
                // برای فیلدهای عددی، رویدادهای بیشتری اضافه می‌کنیم
                input.addEventListener('input', recalcHandler);
                input.addEventListener('blur', recalcHandler);
                input.addEventListener('keyup', recalcHandler);
                
                // اتصال رویداد به AutoNumeric
                const an = AutoNumeric.getAutoNumericElement(input);
                if (an) {
                    an.settings.onInvalidPaste = 'error';
                    an.settings.onChange = () => recalcHandler();
                    an.settings.onBlur = () => recalcHandler();
                }
            }
        });
        this.itemIndex++;
        return newRow;
    },
    getFieldHtml(field, index) {
        const { name, label, type = 'text', required = false, col_class = 'col-md-2', readonly = false, options = [] } = field;
        const fieldName = `items[${index}][${name}]`;
        let controlHtml = '';
        const commonAttrs = `name="${fieldName}" class="form-control form-control-sm" ${required ? 'required' : ''} ${readonly ? 'readonly' : ''}`;
        if (type === 'select') {
            let optionHtml = '<option value="">انتخاب کنید...</option>';
            if (name === 'item_assay_office_melted') {
                DataManager.assayOffices.forEach(office => {
                    optionHtml += `<option value="${office.id}">${office.name}</option>`;
                });
            } else {
                options.forEach(opt => {
                    optionHtml += `<option value="${opt.value}">${opt.label}</option>`;
                });
            }
            controlHtml = `<select ${commonAttrs}>${optionHtml}</select>`;
        } else {
            const inputType = (type === 'number' || name.includes('price') || name.includes('amount') || name.includes('total')) ? 'text' : type;
            const numericClass = field.is_numeric ? 'autonumeric' : '';
            let stepAttr = '';
            if (name.includes('price') || name.includes('amount') || name.includes('total')) {
                stepAttr = 'step="1"';
            }
            controlHtml = `<input type="${inputType}" ${commonAttrs} ${stepAttr} class="form-control form-control-sm ${numericClass}">`;
        }
        return `
            <div class="${col_class}">
                <label class="form-label">${label}${required ? ' <span class="text-danger">*</span>' : ''}</label>
                ${controlHtml}
            </div>
        `;
    },
    populateRowFields(rowEl, itemData, product) {
        const index = parseInt(rowEl.querySelector('[name$="[product_id]"]').name.match(/\[(\d+)\]/)[1]);
        const group = DataManager.getProductGroup(product);
        const dbToFormMap = {
            'quantity': `item_quantity_${group}`, 'weight_grams': `item_weight_scale_${group}`, 'carat': `item_carat_${group}`, 'unit_price_rials': `item_unit_price_${group}`, 'total_value_rials': `item_total_price_${group}`, 'tag_number': `item_tag_number_${group}`, 'assay_office_id': `item_assay_office_melted`, 'coin_year': `item_coin_year_${group}`, 'seal_name': `item_vacuum_name_${group}`, 'is_bank_coin': 'item_type_coin', 'ajrat_rials': `item_manufacturing_fee_amount_${group}`, 'workshop_name': `item_workshop_${group}`, 'stone_weight_grams': `item_attachment_weight_${group}`, 'description': 'item_description', 'profit_percent': `item_profit_percent_${group}`, 'profit_amount': `item_profit_amount_${group}`, 'fee_percent': `item_fee_percent_${group}`, 'fee_amount': `item_fee_amount_${group}`, 'general_tax': `item_general_tax_${group}`, 'vat': `item_vat_${group}`
        };
        for (const [dbCol, formField] of Object.entries(dbToFormMap)) {
            if (itemData[dbCol] !== null && itemData[dbCol] !== undefined) {
                let value = itemData[dbCol];
                if (dbCol === 'is_bank_coin') {
                    value = value ? 'bank' : 'misc';
                }
                this.setFieldValue(rowEl, formField, value);
            }
        }
    },
    setFieldValue(rowEl, fieldName, value) {
        const index = parseInt(rowEl.querySelector('[name$="[product_id]"]').name.match(/\[(\d+)\]/)[1]);
        const input = rowEl.querySelector(`[name="items[${index}][${fieldName}]"]`);
        if (input) {
            if (input.classList.contains('autonumeric')) {
                // فقط مقدار عددی خام به AutoNumeric داده شود
                let raw = value;
                if (typeof raw === 'string') {
                    raw = raw.replace(/[^\d.\-]/g, '');
                }
                AutoNumeric.getAutoNumericElement(input)?.set(raw);
            } else {
                input.value = value;
            }
        }
    },
    /**
     * رندر فیلدهای تخصصی هر کالا در ردیف مربوطه
     */
    renderDynamicFields: function(rowEl, product) {
        if (!rowEl || !product) return;
        const group = DataManager.getProductGroup(product);
        const fields = DataManager.getFieldsByGroup(group);
        const container = rowEl.querySelector('.dynamic-fields-container');
        if (!container) return;
        let html = '<div class="row">';
        fields.forEach(field => {
            html += this.getFieldHtml(field, this.getRowIndex(rowEl));
        });
        html += '</div>';
        container.innerHTML = html;
        // مقداردهی مجدد فیلدها (در صورت وجود داده قبلی) قبل از مقداردهی AutoNumeric
        const index = this.getRowIndex(rowEl);
        const itemData = (window.APP_DATA && window.APP_DATA.transactionItems && window.APP_DATA.transactionItems[index]) ? window.APP_DATA.transactionItems[index] : null;
        if (itemData) {
            this.populateRowFields(rowEl, itemData, product);
        }
        // مقداردهی اولیه AutoNumeric برای فیلدهای عددی جدید
        this.initAutoNumeric(container);
        // اتصال رویداد فقط روی فیلدهای عددی و select برای فعال شدن محاسبات
        const inputs = container.querySelectorAll('input.autonumeric, select');
        inputs.forEach(input => {
            input.addEventListener('change', Utils.debounce(() => TransactionFormApp.recalculateAll(), 100));
            input.addEventListener('input', Utils.debounce(() => TransactionFormApp.recalculateAll(), 100));
            if (input.classList.contains('autonumeric')) {
                input.addEventListener('blur', Utils.debounce(() => TransactionFormApp.recalculateAll(), 100));
            }
        });
    },
    /**
     * گرفتن ایندکس ردیف از نام فیلد product_id
     */
    getRowIndex: function(rowEl) {
        const input = rowEl.querySelector('[name$="[product_id]"]');
        if (!input) return 0;
        const match = input.name.match(/\[(\d+)\]/);
        return match ? parseInt(match[1]) : 0;
    },
    /**
     * رندر لیست محصولات با گروه‌بندی دسته‌بندی
     */
    populateProductSelect: function(selectEl, selectedId) {
        if (!selectEl) return;
        selectEl.innerHTML = '<option value="">انتخاب کالا...</option>';
        // گروه‌بندی محصولات بر اساس دسته‌بندی
        const groupedProducts = DataManager.products.reduce(function(acc, p) {
            var groupName = p.category && p.category.name ? p.category.name : 'سایر';
            if (!acc[groupName]) acc[groupName] = [];
            acc[groupName].push(p);
            return acc;
        }, {});
        for (var groupName in groupedProducts) {
            var optgroup = document.createElement('optgroup');
            optgroup.label = groupName;
            groupedProducts[groupName].forEach(function(product) {
                var option = document.createElement('option');
                option.value = product.id;
                option.textContent = product.name;
                if (selectedId && product.id == selectedId) {
                    option.selected = true;
                }
                optgroup.appendChild(option);
            });
            selectEl.appendChild(optgroup);
        }
    },
    getRowData(rowEl) {
        const data = {};
        const inputs = rowEl.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            const nameMatch = input.name.match(/\[([^\]]+)\]$/);
            if (nameMatch) {
                const fieldName = nameMatch[1];
                let value;
                if (input.classList.contains('autonumeric')) {
                    value = AutoNumeric.getNumber(input);
                } else if (input.type === 'checkbox') {
                    value = input.checked;
                } else {
                    value = input.value;
                }
                data[fieldName] = value;
            }
        });
        return data;
    },
    initAutoNumeric(context = document) {
        if (typeof AutoNumeric === 'undefined') return;
        const elements = context.querySelectorAll('.autonumeric');
        elements.forEach(el => {
            const fieldName = el.name || '';
            let options = {
                digitGroupSeparator: '٬',
                decimalCharacter: '.',
                decimalPlaces: 0,
                unformatOnSubmit: true,
                modifyValueOnWheel: false,
                watchExternalChanges: true,
                emptyInputBehavior: "zero",
                minimumValue: "0",
                formulaMode: true,
                rawValueDivisor: null,
                currencySymbol: '',
                currencySymbolPlacement: 's',
                roundingMethod: "U",
                allowDecimalPadding: true,
                alwaysAllowDecimalCharacter: true,
                failOnUnknownOption: false,
                parseNumericString: true
            };
            
            // تنظیم دقت اعشار بر اساس نوع فیلد
            if (fieldName.includes('weight')) {
                options.decimalPlaces = 3;
                options.minimumValue = "0.001";
            } else if (fieldName.includes('percent')) {
                options.decimalPlaces = 2;
                options.minimumValue = "0.01";
            } else if (fieldName.includes('price') || fieldName.includes('amount') || fieldName.includes('total')) {
                options.decimalPlaces = 0;
                options.minimumValue = "0";
            }

            // حذف نمونه قبلی AutoNumeric اگر وجود داشته باشد
            if (AutoNumeric.getAutoNumericElement(el)) {
                AutoNumeric.getAutoNumericElement(el).remove();
            }

            // ایجاد نمونه جدید
            const an = new AutoNumeric(el, options);

            // اگر مقدار اولیه وجود دارد، آن را تنظیم کنید
            if (el.value) {
                an.set(el.value);
            }
        });
    },
    updateSummaryView(summaryData) {
        this.elements.summaryTotalItems.textContent = Utils.formatRial(summaryData.total_items_value_rials);
        this.elements.summaryTotalProfit.textContent = Utils.formatRial(summaryData.total_profit_wage_commission_rials);
        this.elements.summaryTotalTax.textContent = Utils.formatRial(summaryData.total_general_tax_rials);
        this.elements.summaryBeforeVat.textContent = Utils.formatRial(summaryData.total_before_vat_rials);
        this.elements.summaryTotalVat.textContent = Utils.formatRial(summaryData.total_vat_rials);
        this.elements.summaryFinalPayable.textContent = Utils.formatRial(summaryData.final_payable_amount_rials);
    },
    toggleSubmitSpinner(show) {
        if (this.elements.submitButton) this.elements.submitButton.disabled = show;
        if (this.elements.submitButtonSpinner) this.elements.submitButtonSpinner.classList.toggle('d-none', !show);
    }
};

// --- Main Application Logic ---
const TransactionFormApp = {
    init() {
        DataManager.init(window.APP_DATA);
        UIManager.init();
        this.bindGlobalEvents();
        if (window.APP_CONFIG.isEditMode && window.APP_DATA.transactionItems.length > 0) {
            window.APP_DATA.transactionItems.forEach(item => {
                UIManager.renderNewItemRow(item);
            });
        } else {
            UIManager.renderNewItemRow();
        }
        this.handleTransactionTypeChange();
        this.recalculateAll();
        if (window.jalaliDatepicker) {
            jalaliDatepicker.startWatch({ selector: '.jalali-datepicker', showTodayBtn: true, showCloseBtn: true, format: 'Y/m/d H:i:s' });
        }
    },
    bindGlobalEvents() {
        UIManager.elements.addItemButton.addEventListener('click', () => {
            UIManager.renderNewItemRow();
        });

        UIManager.elements.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        
        document.getElementById('mazaneh_price')?.addEventListener('change', () => this.recalculateAll());
        
        UIManager.elements.transactionTypeSelect?.addEventListener('change', () => this.handleTransactionTypeChange());

        UIManager.elements.itemsContainer.addEventListener('change', Utils.debounce((e) => {
            const target = e.target;
            if (target.classList.contains('product-select')) {
                const rowEl = target.closest('.transaction-item-row');
                const productId = target.value;
                const product = DataManager.getProductById(productId);
                if (product) {
                    UIManager.renderDynamicFields(rowEl, product);
                } else {
                    rowEl.querySelector('.dynamic-fields-container').innerHTML = '';
                }
            }
            TransactionFormApp.recalculateAll();
        }, 200));

        UIManager.elements.itemsContainer.addEventListener('click', (e) => {
            if (e.target.closest('.remove-item-btn')) {
                e.target.closest('.transaction-item-row').remove();
                this.recalculateAll();
            }
        });
    },
    handleTransactionTypeChange() {
        const type = UIManager.elements.transactionTypeSelect.value;
        const deliveryStatusEl = UIManager.elements.deliveryStatusSelect;
        if (type === 'buy') {
            deliveryStatusEl.value = 'pending_receipt';
        } else if (type === 'sell') {
            deliveryStatusEl.value = 'pending_delivery';
        }
    },
    recalculateAll() {
        console.log('Starting recalculateAll...');
        
        const summary = {
            total_items_value_rials: 0,
            total_profit_wage_commission_rials: 0,
            total_general_tax_rials: 0,
            total_vat_rials: 0,
            total_before_vat_rials: 0,
            final_payable_amount_rials: 0
        };

        const itemRows = UIManager.elements.itemsContainer.querySelectorAll('.transaction-item-row');
        const mazanehPrice = Utils.getNumericValue(document.getElementById('mazaneh_price')?.value || 0);
        console.log('Mazaneh Price:', mazanehPrice);
        
        itemRows.forEach((rowEl, index) => {
            console.log(`Processing row ${index + 1}...`);
            
            const item = UIManager.getRowData(rowEl);
            const product = DataManager.getProductById(item.product_id);
            if (!product) {
                console.log('No product selected for row', index + 1);
                return;
            }
            
            const group = DataManager.getProductGroup(product);
            console.log('Product Group:', group);
            
            const formulas = DataManager.getFormulasByGroup(group);
            console.log(`Found ${formulas.length} formulas for group ${group}`);
            
            let isUpdated = false;
            
            // محاسبه مقادیر بر اساس فرمول‌های تعریف شده
            formulas.forEach((formula, formulaIndex) => {
                // تبدیل نام فیلد به target_field
                const target_field = formula.name;
                // استفاده از فرمول از فیلد formula یا code
                const expression = formula.formula || formula.code || '';
                
                if (!expression || !target_field) {
                    console.warn('Invalid formula structure:', formula);
                    return;
                }

                // اضافه کردن پیشوند item_ به نام فیلد هدف اگر نداشته باشد
                const fullTargetField = target_field.startsWith('item_') ? target_field : `item_${target_field}`;
                console.log(`Processing formula ${formulaIndex + 1} - Target: ${fullTargetField}, Expression: ${expression}`);
                
                const context = {
                    ...item,
                    mazaneh_price: mazanehPrice,
                    transaction_type: UIManager.elements.transactionTypeSelect?.value || 'sell'
                };
                
                try {
                    // اضافه کردن پسوند گروه به نام فیلد هدف
                    const targetFieldWithGroup = fullTargetField.includes('_' + group) ? 
                        fullTargetField : 
                        `${fullTargetField}_${group}`;
                    
                    const result = DataManager.calculateExpression(expression, context);
                    console.log(`Formula result for ${targetFieldWithGroup}:`, result);
                    
                    if (result !== null) {
                        // جستجو برای فیلد با استفاده از نام دقیق
                        const targetField = rowEl.querySelector(`[name$="[${targetFieldWithGroup}]"]`);
                        if (!targetField) {
                            console.warn(`Target field not found: ${targetFieldWithGroup}`);
                            return;
                        }

                        const currentValue = targetField.classList.contains('autonumeric') ?
                            (AutoNumeric.getAutoNumericElement(targetField)?.getNumber() || 0) :
                            parseFloat(targetField.value) || 0;

                        if (Math.abs(currentValue - result) > 0.001) {
                            isUpdated = true;
                            
                            if (targetField.classList.contains('autonumeric')) {
                                const an = AutoNumeric.getAutoNumericElement(targetField);
                                if (an) {
                                    console.log(`Setting ${targetFieldWithGroup} to:`, result);
                                    an.set(result);
                                }
                            } else {
                                targetField.value = result;
                            }
                        }
                    }
                } catch (error) {
                    console.error(`Error calculating formula for ${formula.target_field}:`, error);
                    console.error('Formula:', formula.expression);
                    console.error('Context:', context);
                }
            });

            // بروزرسانی خلاصه حساب با داده‌های جدید
            const updatedItem = UIManager.getRowData(rowEl);
            
            const total_price = Utils.getNumericValue(item[`item_total_price_${group}`]);
            const profit_amount = Utils.getNumericValue(item[`item_profit_amount_${group}`]);
            const fee_amount = Utils.getNumericValue(item[`item_fee_amount_${group}`]);
            const manufacturing_fee = Utils.getNumericValue(item[`item_manufacturing_fee_amount_${group}`]);
            const general_tax = Utils.getNumericValue(item[`item_general_tax_${group}`]);
            const vat = Utils.getNumericValue(item[`item_vat_${group}`]);

            summary.total_items_value_rials += total_price;
            summary.total_profit_wage_commission_rials += (profit_amount + fee_amount + manufacturing_fee);
            summary.total_general_tax_rials += general_tax;
            summary.total_vat_rials += vat;
        });

        // محاسبه جمع کل
        summary.total_before_vat_rials = summary.total_items_value_rials + 
                                       summary.total_profit_wage_commission_rials + 
                                       summary.total_general_tax_rials;
        summary.final_payable_amount_rials = summary.total_before_vat_rials + summary.total_vat_rials;

        // بروزرسانی نمایش خلاصه حساب
        UIManager.updateSummaryView(summary);
        return summary;
    },
    async handleFormSubmit(e) {
        e.preventDefault();
        UIManager.toggleSubmitSpinner(true);
        const form = e.target;
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            Utils.showMessage('danger', 'لطفاً تمام فیلدهای الزامی را پر کنید.');
            UIManager.toggleSubmitSpinner(false);
            return;
        }
        // --- Fix transaction_date: convert and update input value before FormData ---
        const dateInput = document.getElementById('transaction_date');
        let transactionDate = dateInput ? dateInput.value : '';
        if (window.jalaali && window.jalaali.toGregorian && transactionDate) {
            const [datePart, timePart] = transactionDate.split(' ');
            if (datePart) {
                const [jy, jm, jd] = datePart.split('/').map(Number);
                const { gy, gm, gd } = window.jalaali.toGregorian(jy, jm, jd);
                let validTime = '00:00';
                if (timePart && /^\d{2}:\d{2}$/.test(timePart)) {
                    validTime = timePart;
                } else if (timePart && /^\d{2}:\d{2}:\d{2}$/.test(timePart)) {
                    validTime = timePart.substring(0,5);
                }
                transactionDate = `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')} ${validTime}`;
                dateInput.value = transactionDate;
            }
        }
        const formData = new FormData(form);
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const result = await response.json();
            if (result.success) {
                Utils.showMessage('success', result.message);
                if (result.redirect_url) {
                    setTimeout(() => { window.location.href = result.redirect_url; }, 1500);
                }
            } else {
                Utils.showMessage('danger', result.message, result.errors || []);
            }
        } catch (error) {
            console.error('Submit Error:', error);
            Utils.showMessage('danger', 'خطا در ارتباط با سرور. لطفاً اتصال اینترنت خود را بررسی کنید.');
        } finally {
            UIManager.toggleSubmitSpinner(false);
        }
        // حذف بلاک تکراری تبدیل تاریخ (در انتهای تابع)
    }
};

// --- Initialize the App on DOMContentLoaded ---
document.addEventListener('DOMContentLoaded', () => {
    TransactionFormApp.init();
});
