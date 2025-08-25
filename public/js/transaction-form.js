/**
 * Gold Accounting - Transaction Form Manager
 * Version: 7.1 (CRITICAL FIX: Full adherence to unified 'bullion' group in fields.json and formulas.json)
 * This is the definitive, fully functional, and carefully reviewed version.
 * It integrates all functionalities: UI rendering, real-time calculations via API, and robust submission.
 *
 * REVISION:
 * - CRITICAL: Completely adapts to the user's new fields.json where gold/silver bullion are unified under 'bullion' group.
 *   `item_form_group_name` is no longer used for dynamic rendering lookup, instead `base_category` directly drives it.
 * - `handleProductChange`: simplified logic for determining item group for rendering (now uses base_category directly).
 * - `getRowInputValues`: ensures payload sends correct, unified 'bullion' group names for fields.
 * - Addresses initial issues with `null` `item_form_group_name` on frontend for rendering (now unnecessary for `bullion`'s main case).
 * - General CSS adjustments reflected.
 */
class TransactionFormManager {
    constructor(config, data) {
        this.config = config;
        this.data = data;
        this.form = document.getElementById('transaction-form');
        this.itemsContainer = document.getElementById('transaction-items-container');
        this.itemTemplate = document.getElementById('item-row-template');
        this.summaryContainer = document.getElementById('summary-container');
        this.itemIndex = 0;
        this.isCalculating = false;
        this.abortController = null;

        // Pre-process fields by their 'group' from fields.json.
        // This is where 'bullion' group will now be stored, as per updated fields.json.
        this.fieldsByGroup = this.groupFieldsByGroup();
        
        console.log("TransactionFormManager v7.1 Initializing...");
        console.log("APP_DATA (products, assayOffices, fields, formulas):", this.data);
        console.log("Fields by Group (Pre-processed for rendering):", this.fieldsByGroup);
        this.init();
    }

    init() {
        if (!this.form || !this.itemsContainer || !this.itemTemplate) {
            console.error("FATAL ERROR: Essential form elements not found.");
            return;
        }

        this.renderSummaryFields(); 

        this.initAutoNumeric(this.form);
        this.initDatepicker();
        this.bindGlobalEvents();

        if (this.config.isEditMode && this.data.transactionItems && this.data.transactionItems.length > 0) {
            this.data.transactionItems.forEach(item => this.addNewItemRow(item));
        } else {
            this.addNewItemRow();
        }
        
        setTimeout(() => this.runCalculations(), 250); 
    }

    initDatepicker() {
        if (typeof jalaliDatepicker !== 'undefined') {
            jalaliDatepicker.startWatch({ selector: '#transaction_date', time: false, autoClose: true, persianDigits: true, format: 'Y/m/d' });
        } else {
            console.warn("Jalali Datepicker library not found. Date field may not function correctly.");
        }
    }
    
    initAutoNumeric(container) {
        if (typeof AutoNumeric !== 'undefined') {
            container.querySelectorAll('.autonumeric, .autonumeric-readonly, .format-number-js').forEach(el => {
                if (!AutoNumeric.getAutoNumericElement(el)) {
                    const name = el.getAttribute('name') || el.id || '';
                    let decimals = 0;

                    if (name.includes('weight') || name.includes('carat') || name.includes('quantity') || name.includes('stone_weight_grams')) {
                        decimals = 3; 
                    } else if (name.includes('percent') || name.includes('rate')) {
                        decimals = 2; 
                    }

                    try {
                        new AutoNumeric(el, {
                            digitGroupSeparator: ',',
                            decimalCharacter: '.',
                            decimalPlaces: decimals,
                            readOnly: el.hasAttribute('readonly'),
                            digitalGroupSpacing: '3'
                        });
                    } catch (e) {
                        console.error(`Error initializing AutoNumeric on ${name}:`, e);
                    }
                }
            });
        } else {
            console.warn("AutoNumeric library not found. Numeric formatting and calculations may not work.");
        }
    }

    bindGlobalEvents() {
        document.getElementById('add-transaction-item').addEventListener('click', () => this.addNewItemRow());
        this.form.addEventListener('submit', e => this.handleFormSubmit(e));
        
        const debounce = (func, delay) => {
            let timeout;
            return (...args) => { clearTimeout(timeout); timeout = setTimeout(() => func.apply(this, args), delay); };
        };
        const debouncedCalculations = debounce(() => this.runCalculations(), 300);
        
        this.form.addEventListener('input', debouncedCalculations);
        this.form.addEventListener('change', debouncedCalculations);
    }

   addNewItemRow(itemData = null) {
        const templateContent = this.itemTemplate.content.cloneNode(true);
        const newRow = templateContent.querySelector('.transaction-item-row');
        
        // جایگزینی ایندکس در تمام فیلدها
        newRow.innerHTML = newRow.innerHTML.replace(/\{index\}/g, this.itemIndex);
        newRow.dataset.index = this.itemIndex;
        
        // **اصلاح کلیدی: اگر در حالت ویرایش هستیم، شناسه آیتم را در فیلد مخفی قرار بده**
        if (itemData && itemData.id) {
            const hiddenIdInput = newRow.querySelector(`input[name="items[${this.itemIndex}][id]"]`);
            if (hiddenIdInput) {
                hiddenIdInput.value = itemData.id;
            }
        }

        this.itemsContainer.appendChild(newRow);
        
        const productSelect = newRow.querySelector('.product-select');
        this.populateProductSelect(productSelect, itemData ? itemData.product_id : null);
        
        productSelect.addEventListener('change', () => this.handleProductChange(productSelect));

        if (itemData && itemData.product_id) {
            this.handleProductChange(productSelect, itemData); 
        } else {
            this.handleProductChange(productSelect); 
        }
        
        newRow.querySelector('.remove-item-btn').addEventListener('click', () => {
            newRow.remove();
            this.runCalculations();
        });
        this.itemIndex++;
    }

    populateProductSelect(select, selectedId) {
        select.innerHTML = '<option value="">انتخاب کالا...</option>';
        if (!Array.isArray(this.data.products)) return;
        this.data.products.forEach(p => {
            if (p && p.id && p.name) {
                const option = document.createElement('option');
                option.value = p.id;
                option.textContent = p.name;
                if (p.id == selectedId) option.selected = true;
                select.appendChild(option);
            }
        });
    }

    /**
     * Handles product change: determines item group for rendering and renders/populates dynamic fields.
     */
    handleProductChange(select, itemData = null) {
        const row = select.closest('.transaction-item-row');
        const dynamicContainer = row.querySelector('.dynamic-fields-container');
        dynamicContainer.innerHTML = '';
        const productId = select.value;

        if (!productId) {
            return;
        }
        
        const product = this.data.products.find(p => p.id == productId);
        if (!product || !product.category || !product.category.base_category) {
            console.warn(`Product or category data missing for product ID ${productId}.`);
            return;
        }
        
        const itemGroupToRender = product.category.base_category.toLowerCase();
        row.dataset.group = itemGroupToRender;
        
        const fieldsToRender = this.fieldsByGroup[itemGroupToRender] || [];

        dynamicContainer.innerHTML = this.renderFields(fieldsToRender, row.dataset.index);
        this.initAutoNumeric(dynamicContainer); 
        
        if (itemData) {
            console.log(`Populating data for row ${row.dataset.index} after product change and field rendering.`);
            // از یک تأخیر کوتاه استفاده می‌کنیم تا مطمئن شویم DOM کاملاً آماده است
            setTimeout(() => {
                this.populateRowData(row, itemData);
                // پس از پر شدن داده‌ها، محاسبات را دوباره اجرا می‌کنیم
                this.runCalculations();
            }, 100);
        }
    }
    
    renderFields(fields, index) {
            let html = '';
            const rows = {};
            
            // (اصلاح شده) اطلاعات مالیات مستقیماً از آبجکت محصول خوانده می‌شود
            const rowElement = this.itemsContainer.querySelector(`[data-index="${index}"]`);
            const productId = rowElement.querySelector('.product-select').value;
            const product = this.data.products.find(p => p.id == productId);
            const taxInfo = product || {};

            fields.forEach(field => {
                const isGeneralTaxField = field.name.includes('general_tax');
                const isVatField = field.name.includes('vat');
                
                const hasGeneralTax = taxInfo.general_tax_base_type && taxInfo.general_tax_base_type !== 'NONE' && parseFloat(taxInfo.tax_rate) > 0;
                const hasVat = taxInfo.vat_base_type && taxInfo.vat_base_type !== 'NONE' && parseFloat(taxInfo.vat_rate) > 0;

                if (isGeneralTaxField && !hasGeneralTax) { return; }
                if (isVatField && !hasVat) { return; }

                const rowKey = field.row_display || 'default_row';
                if (!rows[rowKey]) rows[rowKey] = '';
                
                rows[rowKey] += `<div class="${field.col_class || 'col-auto'} mb-2">${this.renderSingleField(field, index)}</div>`; 
            });

            Object.keys(rows).sort().forEach(rowKey => {
                html += `<div class="row g-2 mb-2">${rows[rowKey]}</div>`;
            });
            return html;
        }

    renderSingleField(field, index) {
        const name = `items[${index}][${field.name}]`;
        const id = `items_${index}_${field.name}`;
        let label = `<label for="${id}" class="form-label form-label-sm">${field.label}${field.required ? '<span class="text-danger">*</span>' : ''}</label>`;
        const attrs = `id="${id}" name="${name}" class="form-control form-control-sm ${field.class || ''}" ${field.readonly ? 'readonly' : ''} ${field.required ? 'required' : ''}`;
        
        if (field.type === 'select') {
            let options = '<option value="">...</option>';
            if (field.source === 'assay_offices') {
                this.data.assayOffices.forEach(o => { options += `<option value="${o.id}">${o.name}</option>`; });
            } else if (Array.isArray(field.options)) {
                field.options.forEach(opt => { options += `<option value="${opt.value}">${opt.label}</option>`; });
            }
            return `${label}<select ${attrs}>${options}</select>`;
        } else if (field.type === 'radio') {
            let radioHtml = '';
            field.options.forEach(opt => {
                const radioId = `${id}_${opt.value}`;
                radioHtml += `
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="${name}" id="${radioId}" value="${opt.value}" ${field.required ? 'required' : ''}>
                        <label class="form-check-label" for="${radioId}">${opt.label}</label>
                    </div>
                `;
            });
            return `${label}<div class="mt-2">${radioHtml}</div>`;
        }
        return `${label}<input type="${field.type || 'text'}" ${attrs}>`;
    }

    groupFieldsByGroup() {
        const grouped = {};
        this.data.fields.forEach(field => {
            if (field.group && typeof field.group === 'string' && field.section === 'item_row') {
                const group = field.group.toLowerCase().trim();
                if (!grouped[group]) grouped[group] = [];
                grouped[group].push(field);
            }
        });
        for (const groupKey in grouped) {
            grouped[groupKey].sort((a, b) => {
                const priorityA = a.priority !== undefined ? a.priority : Infinity;
                const priorityB = b.priority !== undefined ? b.priority : Infinity;
                if (priorityA !== priorityB) { return priorityA - priorityB; }
                return a.id - b.id;
            });
        }
        return grouped;
    }

    renderSummaryFields() {
        if (!this.summaryContainer) { console.error("Summary container not found."); return; }
        let summaryHtml = '';
        const summaryFormulas = this.data.formulas.filter(f => {
            return f.form === 'transactions/form.php' && !f.group && !f.is_generic;
        }).sort((a, b) => {
            const priorityA = a.priority !== undefined ? a.priority : Infinity;
            const priorityB = b.priority !== undefined ? b.priority : Infinity;
            if (priorityA !== priorityB) { return priorityA - priorityB; }
            return a.id - b.id;
        });
        
        summaryFormulas.forEach(formula => {
            const label = formula.label || formula.name;
            const iconHtml = formula.icon ? `<i class="${formula.icon} me-1"></i> ` : '';
            summaryHtml += `
                <div class="col-md-3 col-sm-6">
                    <div class="form-group mb-3">
                        <label class="form-label form-label-sm d-block">
                            ${iconHtml}${label}
                        </label>
                        <input type="text" id="summary-${formula.name}" name="summary_${formula.name}" class="form-control form-control-sm autonumeric-readonly text-start" readonly value="0">
                    </div>
                </div>
            `;
        });
        this.summaryContainer.innerHTML = summaryHtml;
        this.initAutoNumeric(this.summaryContainer);
    }

    async runCalculations() {
        if (this.isCalculating) { this.abortController.abort(); }
        this.isCalculating = true;
        this.abortController = new AbortController();
        const signal = this.abortController.signal;

        try {
            const itemPromises = Array.from(this.itemsContainer.querySelectorAll('.transaction-item-row')).map(row => this.calculateItemRow(row, signal));
            const calculatedItems = await Promise.all(itemPromises);

            if (signal.aborted) { return; }

            const validItems = calculatedItems.filter(item => item !== 'aborted' && item !== null);
            this.calculateSummary(validItems);
        } catch (error) {
            if (error.name !== 'AbortError') console.error("Error in calculation chain:", error);
        } finally {
            this.isCalculating = false;
        }
    }

    async calculateItemRow(row, signal) {
        const itemGroup = row.dataset.group; // Derived from base_category on this row.
        if (!itemGroup) { return null; }
        
        let inputValues = this.getRowInputValues(row);
        
        const product = this.data.products.find(p => p.id == inputValues.product_id);
        if (product) {
            inputValues['product_tax_base_type'] = product.general_tax_base_type;
            inputValues['product_tax_rate'] = product.tax_rate;
            inputValues['product_vat_base_type'] = product.vat_base_type;
            inputValues['product_vat_rate'] = product.vat_rate;
            inputValues['item_group_for_backend'] = itemGroup; // Send this unified group to backend.
        }

        try {
            const response = await fetch(`${this.config.baseUrl}/api/calculate-item`, {
                method: 'POST',
                signal,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(inputValues)
            });
            if (!response.ok) {
                 let errorMessage = `API Error: ${response.statusText}`;
                 try { const errorJson = await response.json(); if (errorJson.message) errorMessage += ` (${errorJson.message})`; } catch (e) { /* ignore */ }
                 throw new Error(errorMessage);
            }
            const result = await response.json();
            if (signal.aborted) return 'aborted';
            if (result.success) {
                this.updateRowUI(row, result.data);
                return result.data;
            }
            console.error(`API call for row ${row.dataset.index} returned success: false. Message: ${result.message || 'No message'}`);
            return inputValues;
        } catch (error) {
            if (error.name === 'AbortError') return 'aborted';
            console.error(`API call for row ${row.dataset.index} failed unexpectedly:`, error);
            return inputValues;
        }
    }
    
    updateRowUI(row, data) {
        for (const key in data) {
            const el = row.querySelector(`[name$="[${key}]"]`);
            if (el && el.hasAttribute('readonly')) {
                this.setNumericValue(el, data[key]);
            }
        }
    }

    calculateSummary(calculatedItems) {
        let summaryValues = {};
        const summaryFormulas = this.data.formulas.filter(f => f.form === 'transactions/form.php' && !f.group && !f.is_generic).sort((a,b) => (a.priority || 99) - (b.priority || 99));
        
        for(const formula of summaryFormulas) {
            const variables = {};
            for (const field of formula.fields) {
                if(summaryValues[field] !== undefined) {
                    variables[field] = summaryValues[field];
                } else {
                    variables[field] = calculatedItems.reduce((sum, item) => {
                        const itemValue = parseFloat(item[field]) || 0;
                        return sum + itemValue;
                    }, 0);
                }
            }
            summaryValues[formula.name] = this.evaluateFormulaLocally(formula.formula, variables);
        }
        this.updateSummaryUI(summaryValues);
    }
    
    evaluateFormulaLocally(expression, values) {
        for(const key in values) { expression = expression.replace(new RegExp('\\b' + key + '\\b', 'g'), `(${values[key] || 0})`); }
        try { return new Function(`return ${expression}`)() || 0; }
        catch (e) { console.error("Error evaluating formula locally:", expression, e); return 0; }
    }

    updateSummaryUI(summary) {
        for(const formulaName in summary){
            const el = document.getElementById(`summary-${formulaName}`);
            if(el) this.setNumericValue(el, summary[formulaName]);
        }
    }

    getRowInputValues(row) {
        const values = {};
        row.querySelectorAll('input, select, textarea').forEach(el => {
            const nameMatch = el.name.match(/\[([^\]]+)\]$/);
            if (nameMatch) {
                const key = nameMatch[1];
                if (el.type === 'radio') { if (el.checked) { values[key] = el.value; } }
                else if (el.type === 'checkbox') { values[key] = el.checked; }
                else { 
                    if (el.classList.contains('autonumeric') || el.classList.contains('format-number-js') || 
                        el.name.includes('amount') || el.name.includes('price') || el.name.includes('weight') || 
                        el.name.includes('carat') || el.name.includes('percent') || el.name.includes('quantity') || el.name.includes('rate') ) {
                         values[key] = this.getNumericValue(el);
                    } else { values[key] = el.value; }
                }
            }
        });
        values['mazaneh_price'] = this.getNumericValue(document.getElementById('mazaneh_price'));
        
        const product = this.data.products.find(p => p.id == row.querySelector('.product-select').value);
        if(product && product.category){
            values['item_group_for_backend'] = product.category.base_category.toLowerCase(); // Now uses direct base_category for backend group name.
        } else {
            console.warn(`Product or product category missing when getting input values for row ${row.dataset.index}. Cannot determine specific item group.`);
            values['item_group_for_backend'] = 'default';
        }
        
        return values;
    }
    
    getNumericValue(element) {
        if (!element) { return 0; }
        const an = AutoNumeric.getAutoNumericElement(element);
        if (an) { return an.getNumber(); }
        const rawValue = String(element.value).replace(/,/g, '');
        return parseFloat(rawValue) || 0;
    }

    setNumericValue(element, value) {
        if (!element) { return; }
        const an = AutoNumeric.getAutoNumericElement(element);
        if (an) { an.set(value); }
        else { element.value = value; }
    }
    

    populateRowData(row, itemData) {
            console.log(`Populating row ${row.dataset.index} with data:`, itemData);
            const index = row.dataset.index;

            for (const key in itemData) {
                if (itemData.hasOwnProperty(key) && itemData[key] !== null) {
                    const value = itemData[key];
                    const fieldName = `items[${index}][${key}]`;
                    
                    // برای دکمه‌های رادیویی، باید به صورت خاص جستجو کنیم
                    if (key === 'item_manufacturing_fee_type_manufactured') {
                        const radioToCheck = row.querySelector(`[name="${fieldName}"][value="${value}"]`);
                        if (radioToCheck) {
                            radioToCheck.checked = true;
                            radioToCheck.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        continue; // به حلقه بعدی برو
                    }

                    const field = row.querySelector(`[name="${fieldName}"]`);
                    if (field) {
                        const fieldType = field.type || field.tagName.toLowerCase();
                        
                        // (اصلاح شده) منطق checkbox حذف شد و همه چیز به درستی مدیریت می‌شود
                        if (fieldType === 'select-one') {
                            field.value = value;
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        } else if (fieldType === 'checkbox') { // برای موارد احتمالی آینده
                            field.checked = (value == 1 || value === true || value === 'yes');
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        else {
                            this.setNumericValue(field, value);
                        }
                    }
                }
            }
        }

    showMessage(message, type = 'danger') {
        document.getElementById('form-messages').innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        window.scrollTo(0, 0); 
    }

     async handleFormSubmit(event) {
        event.preventDefault(); 
        const btn = document.getElementById('submit-btn');
        btn.disabled = true; 
        btn.querySelector('.spinner-border')?.classList.remove('d-none');
        document.getElementById('form-messages').innerHTML = ''; 

        const dataToSubmit = { items: [] };
        const formData = new FormData(this.form);

        // خواندن تمام فیلدهای اصلی فرم
        for (const [key, value] of formData.entries()) {
            if (!key.startsWith('items[')) {
                if (key === 'mazaneh_price') {
                    dataToSubmit[key] = this.getNumericValue(document.getElementById('mazaneh_price'));
                } else {
                    dataToSubmit[key] = value;
                }
            }
        }

        // خواندن تمام فیلدهای آیتم‌ها
        this.itemsContainer.querySelectorAll('.transaction-item-row').forEach((row) => {
            const item = { id: row.querySelector(`input[name="items[${row.dataset.index}][id]"]`)?.value || null };
            row.querySelectorAll('input, select, textarea').forEach(el => {
                const nameMatch = el.name.match(/\[([^\]]+)\]$/);
                if (nameMatch) {
                    const key = nameMatch[1];
                    if (el.type === 'radio') {
                        if (el.checked) { item[key] = el.value; }
                    } else if (el.type === 'checkbox') {
                        item[key] = el.checked ? 1 : 0;
                    } else if (el.tagName.toLowerCase() === 'select' && el.name.includes('has_attachments')) {
                        item[key] = el.value === 'yes' ? 1 : 0;
                    }
                    else { 
                        if (el.classList.contains('autonumeric') || el.classList.contains('autonumeric-readonly')) {
                            item[key] = this.getNumericValue(el);
                        } else {
                            item[key] = el.value;
                        }
                    }
                }
            });
            if (item.product_id) { dataToSubmit.items.push(item); }
        });

        // **اصلاح کلیدی: تعیین آدرس صحیح برای ویرایش**
        let action = this.form.getAttribute('action');
        if (this.config.isEditMode && this.data.transactionData && this.data.transactionData.id) {
            // اطمینان حاصل می‌کنیم که آدرس همیشه شامل شناسه معامله است
            action = `${this.config.baseUrl}/app/transactions/save/${this.data.transactionData.id}`;
        }

        try {
            const response = await fetch(action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(dataToSubmit)
            });
            
            const result = await response.json();

            if (result.success && result.redirect_url) {
                window.location.href = result.redirect_url; 
            } else {
                this.showMessage(result.message || 'خطا در ذخیره معامله.', 'danger');
            }
        } catch (error) { 
            this.showMessage('یک خطای ارتباطی رخ داد: ' + error.message, 'danger'); 
        } finally { 
            btn.disabled = false; 
            btn.querySelector('.spinner-border')?.classList.add('d-none');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.APP_DATA && window.APP_CONFIG) { new TransactionFormManager(window.APP_CONFIG, window.APP_DATA); }
    else { console.error('Required application data (window.APP_DATA or window.APP_CONFIG) missing from PHP template.'); }
});