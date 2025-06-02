/**
 * transaction-edit-form.js - ماژول ویرایش معاملات
 * نسخه: 1.0.0
 * تاریخ: 1404/03/28
 * 
 * این فایل شامل کد لازم برای ویرایش معاملات موجود است.
 * برخلاف فرم ثبت، این فرم ساده‌تر است و فقط داده‌های موجود را نمایش می‌دهد.
 */

const TransactionEditApp = (function() {
    // متغیرهای خصوصی
    let _initialized = false;
    
    // داده‌های معامله
    const _data = {
        transaction: window.transactionData || {},
        items: window.transactionItemsData || [],
        assayOffices: window.assayOfficesData || [],
        products: window.productsData || [],
        contacts: window.contactsData || [],
        fields: window.fieldsData || [],
        formulas: window.formulasData || [],
        defaultSettings: window.defaultSettings || {}
    };
    
    // عناصر DOM اصلی
    let _elements = {
        form: null,
        itemsContainer: null,
        assayOfficeSelects: [],
        summaryElements: {}
    };
    
    /**
     * مقداردهی اولیه و بارگذاری عناصر DOM
     */
    function _init() {
        if (_initialized) return;
        
        console.log('Initializing Transaction Edit Form...');
        
        // به‌روزرسانی _data با داده‌های دریافتی از صفحه
        _data.transaction = window.transactionData || {};
        _data.items = window.transactionItemsData || [];
        _data.assayOffices = window.assayOfficesData || [];
        _data.products = window.productsData || [];
        _data.contacts = window.contactsData || [];
        _data.fields = window.fieldsData || [];
        _data.formulas = window.formulasData || [];
        _data.defaultSettings = window.defaultSettings || {};
        
        // نمایش لاگ داده‌های دریافتی برای دیباگ
        console.log('Transaction Data:', _data.transaction);
        console.log('Items Data:', _data.items);
        console.log('Assay Offices Data:', _data.assayOffices);
        console.log('Products Data:', _data.products);
        console.log('Fields Data:', _data.fields);
        console.log('Formulas Data:', _data.formulas);
        
        // بارگذاری عناصر DOM اصلی
        _elements.form = document.getElementById('transaction-form');
        _elements.itemsContainer = document.getElementById('transaction-items-container');
        
        // بررسی وجود عناصر اصلی
        if (!_elements.form || !_elements.itemsContainer) {
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
        
        // یافتن تمام فیلدهای select مراکز ری‌گیری
        _elements.assayOfficeSelects = document.querySelectorAll('select[name*="item_assay_office_melted"]');
        
        // پر کردن فیلدهای مرکز ری‌گیری
        _fillAssayOfficeSelects();
        
        // مقداردهی فیلدهای خلاصه
        _updateSummaryFields();
        
        // اتصال رویدادهای اصلی
        _bindEvents();
        
        // راه‌اندازی AutoNumeric برای تمام فیلدهای عددی
        _initializeAllAutonumerics();
        
        // بارگذاری و مپینگ داده‌های آیتم‌ها به فیلدهای فرم
        _loadItemsData();
        
        // بارگذاری اطلاعات طرف حساب
        _loadPartyData();
        
        // محاسبه مجدد همه مقادیر
        _recalculateAllFields();
        
        _initialized = true;
        console.log('Transaction Edit Form initialized successfully.');
    }
    
    /**
     * محاسبه مجدد تمام فیلدهای محاسباتی
     */
    function _recalculateAllFields() {
        // محاسبه قیمت کل برای تمام آیتم‌ها
        document.querySelectorAll('.transaction-item-row').forEach(row => {
            // محاسبه مجدد قیمت کل بر اساس وزن و قیمت واحد
            const productGroup = _getItemProductGroup(row);
            
            if (productGroup === 'melted') {
                const weightInput = row.querySelector('input[name*="item_weight_scale_melted"]');
                const unitPriceInput = row.querySelector('input[name*="item_unit_price_melted"]');
                const totalPriceInput = row.querySelector('input[name*="item_total_price_melted"]');
                
                if (weightInput && unitPriceInput && totalPriceInput) {
                    const weight = parseFloat(weightInput.value.replace(/,/g, '')) || 0;
                    const unitPrice = parseFloat(unitPriceInput.value.replace(/,/g, '')) || 0;
                    const totalPrice = weight * unitPrice;
                    
                    // تنظیم قیمت کل
                    if (totalPriceInput.autoNumeric) {
                        totalPriceInput.autoNumeric.set(totalPrice);
                    } else {
                        try {
                            new AutoNumeric(totalPriceInput, {
                                currencySymbol: '',
                                decimalPlaces: 0,
                                digitGroupSeparator: ',',
                                decimalCharacter: '.',
                                unformatOnSubmit: true
                            }).set(totalPrice);
                        } catch (e) {
                            totalPriceInput.value = _formatNumber(totalPrice);
                        }
                    }
                    
                    // محاسبه مبلغ سود
                    const profitPercentInput = row.querySelector('input[name*="item_profit_percent_melted"]');
                    const profitAmountInput = row.querySelector('input[name*="item_profit_amount_melted"]');
                    
                    if (profitPercentInput && profitAmountInput) {
                        const profitPercent = parseFloat(profitPercentInput.value.replace(/,/g, '')) || 0;
                        
                        // فقط اگر درصد سود بزرگتر از صفر باشد محاسبه می‌کنیم
                        if (profitPercent > 0) {
                            const profitAmount = (totalPrice * profitPercent) / 100;
                            
                            if (profitAmountInput.autoNumeric) {
                                profitAmountInput.autoNumeric.set(profitAmount);
                            } else {
                                try {
                                    new AutoNumeric(profitAmountInput, {
                                        currencySymbol: '',
                                        decimalPlaces: 0,
                                        digitGroupSeparator: ',',
                                        decimalCharacter: '.',
                                        unformatOnSubmit: true
                                    }).set(profitAmount);
                                } catch (e) {
                                    profitAmountInput.value = _formatNumber(profitAmount);
                                }
                            }
                        } else {
                            // اگر درصد صفر است، مبلغ را هم صفر می‌کنیم
                            if (profitAmountInput.autoNumeric) {
                                profitAmountInput.autoNumeric.set(0);
                            } else {
                                try {
                                    new AutoNumeric(profitAmountInput, {
                                        currencySymbol: '',
                                        decimalPlaces: 0,
                                        digitGroupSeparator: ',',
                                        decimalCharacter: '.',
                                        unformatOnSubmit: true
                                    }).set(0);
                                } catch (e) {
                                    profitAmountInput.value = '0';
                                }
                            }
                        }
                    }
                    
                    // محاسبه مبلغ کارمزد
                    const feePercentInput = row.querySelector('input[name*="item_fee_percent_melted"]');
                    const feeAmountInput = row.querySelector('input[name*="item_fee_amount_melted"]');
                    
                    if (feePercentInput && feeAmountInput) {
                        const feePercent = parseFloat(feePercentInput.value.replace(/,/g, '')) || 0;
                        
                        // فقط اگر درصد کارمزد بزرگتر از صفر باشد محاسبه می‌کنیم
                        if (feePercent > 0) {
                            const feeAmount = (totalPrice * feePercent) / 100;
                            
                            if (feeAmountInput.autoNumeric) {
                                feeAmountInput.autoNumeric.set(feeAmount);
                            } else {
                                try {
                                    new AutoNumeric(feeAmountInput, {
                                        currencySymbol: '',
                                        decimalPlaces: 0,
                                        digitGroupSeparator: ',',
                                        decimalCharacter: '.',
                                        unformatOnSubmit: true
                                    }).set(feeAmount);
                                } catch (e) {
                                    feeAmountInput.value = _formatNumber(feeAmount);
                                }
                            }
                        } else {
                            // اگر درصد صفر است، مبلغ را هم صفر می‌کنیم
                            if (feeAmountInput.autoNumeric) {
                                feeAmountInput.autoNumeric.set(0);
                            } else {
                                try {
                                    new AutoNumeric(feeAmountInput, {
                                        currencySymbol: '',
                                        decimalPlaces: 0,
                                        digitGroupSeparator: ',',
                                        decimalCharacter: '.',
                                        unformatOnSubmit: true
                                    }).set(0);
                                } catch (e) {
                                    feeAmountInput.value = '0';
                                }
                            }
                        }
                    }
                }
            } else if (productGroup === 'manufactured') {
                // محاسبه برای محصولات مصنوعی
                const weightInput = row.querySelector('input[name*="item_weight_scale_manufactured"]');
                const unitPriceInput = row.querySelector('input[name*="item_unit_price_manufactured"]');
                const totalPriceInput = row.querySelector('input[name*="item_total_price_manufactured"]');
                const quantityInput = row.querySelector('input[name*="quantity"]');
                
                if (weightInput && unitPriceInput && totalPriceInput) {
                    const weight = parseFloat(weightInput.value.replace(/,/g, '')) || 0;
                    const unitPrice = parseFloat(unitPriceInput.value.replace(/,/g, '')) || 0;
                    const quantity = quantityInput ? (parseInt(quantityInput.value) || 1) : 1;
                    let totalPrice = weight * unitPrice * quantity;
                    
                    // محاسبه اجرت
                    const manufacturingFeePercentInput = row.querySelector('input[name*="item_manufacturing_fee_percent_manufactured"]');
                    const manufacturingFeeAmountInput = row.querySelector('input[name*="item_manufacturing_fee_amount_manufactured"]');
                    
                    if (manufacturingFeePercentInput && manufacturingFeeAmountInput) {
                        const manufacturingFeePercent = parseFloat(manufacturingFeePercentInput.value.replace(/,/g, '')) || 0;
                        
                        // فقط اگر درصد اجرت بزرگتر از صفر باشد محاسبه می‌کنیم
                        if (manufacturingFeePercent > 0) {
                            const manufacturingFeeAmount = (totalPrice * manufacturingFeePercent) / 100;
                            
                            if (manufacturingFeeAmountInput.autoNumeric) {
                                manufacturingFeeAmountInput.autoNumeric.set(manufacturingFeeAmount);
                            } else {
                                try {
                                    new AutoNumeric(manufacturingFeeAmountInput, {
                                        currencySymbol: '',
                                        decimalPlaces: 0,
                                        digitGroupSeparator: ',',
                                        decimalCharacter: '.',
                                        unformatOnSubmit: true
                                    }).set(manufacturingFeeAmount);
                                } catch (e) {
                                    manufacturingFeeAmountInput.value = _formatNumber(manufacturingFeeAmount);
                                }
                            }
                        } else {
                            // اگر درصد صفر است، مبلغ را هم صفر می‌کنیم
                            if (manufacturingFeeAmountInput.autoNumeric) {
                                manufacturingFeeAmountInput.autoNumeric.set(0);
                            } else {
                                try {
                                    new AutoNumeric(manufacturingFeeAmountInput, {
                                        currencySymbol: '',
                                        decimalPlaces: 0,
                                        digitGroupSeparator: ',',
                                        decimalCharacter: '.',
                                        unformatOnSubmit: true
                                    }).set(0);
                                } catch (e) {
                                    manufacturingFeeAmountInput.value = '0';
                                }
                            }
                        }
                    }
                    
                    // تنظیم قیمت کل
                    if (totalPriceInput.autoNumeric) {
                        totalPriceInput.autoNumeric.set(totalPrice);
                    } else {
                        try {
                            new AutoNumeric(totalPriceInput, {
                                currencySymbol: '',
                                decimalPlaces: 0,
                                digitGroupSeparator: ',',
                                decimalCharacter: '.',
                                unformatOnSubmit: true
                            }).set(totalPrice);
                        } catch (e) {
                            totalPriceInput.value = _formatNumber(totalPrice);
                        }
                    }
                    
                    // محاسبه مبلغ سود
                    const profitPercentInput = row.querySelector('input[name*="item_profit_percent_manufactured"]');
                    const profitAmountInput = row.querySelector('input[name*="item_profit_amount_manufactured"]');
                    
                    if (profitPercentInput && profitAmountInput) {
                        const profitPercent = parseFloat(profitPercentInput.value.replace(/,/g, '')) || 0;
                        
                        // فقط اگر درصد سود بزرگتر از صفر باشد محاسبه می‌کنیم
                        if (profitPercent > 0) {
                            const profitAmount = (totalPrice * profitPercent) / 100;
                            
                            if (profitAmountInput.autoNumeric) {
                                profitAmountInput.autoNumeric.set(profitAmount);
                            } else {
                                try {
                                    new AutoNumeric(profitAmountInput, {
                                        currencySymbol: '',
                                        decimalPlaces: 0,
                                        digitGroupSeparator: ',',
                                        decimalCharacter: '.',
                                        unformatOnSubmit: true
                                    }).set(profitAmount);
                                } catch (e) {
                                    profitAmountInput.value = _formatNumber(profitAmount);
                                }
                            }
                        } else {
                            // اگر درصد صفر است، مبلغ را هم صفر می‌کنیم
                            if (profitAmountInput.autoNumeric) {
                                profitAmountInput.autoNumeric.set(0);
                            } else {
                                try {
                                    new AutoNumeric(profitAmountInput, {
                                        currencySymbol: '',
                                        decimalPlaces: 0,
                                        digitGroupSeparator: ',',
                                        decimalCharacter: '.',
                                        unformatOnSubmit: true
                                    }).set(0);
                                } catch (e) {
                                    profitAmountInput.value = '0';
                                }
                            }
                        }
                    }
                    
                    // محاسبه مبلغ کارمزد
                    const feePercentInput = row.querySelector('input[name*="item_fee_percent_manufactured"]');
                    const feeAmountInput = row.querySelector('input[name*="item_fee_amount_manufactured"]');
                    
                    if (feePercentInput && feeAmountInput) {
                        const feePercent = parseFloat(feePercentInput.value.replace(/,/g, '')) || 0;
                        
                        // فقط اگر درصد کارمزد بزرگتر از صفر باشد محاسبه می‌کنیم
                        if (feePercent > 0) {
                            const feeAmount = (totalPrice * feePercent) / 100;
                            
                            if (feeAmountInput.autoNumeric) {
                                feeAmountInput.autoNumeric.set(feeAmount);
                            } else {
                                try {
                                    new AutoNumeric(feeAmountInput, {
                                        currencySymbol: '',
                                        decimalPlaces: 0,
                                        digitGroupSeparator: ',',
                                        decimalCharacter: '.',
                                        unformatOnSubmit: true
                                    }).set(feeAmount);
                                } catch (e) {
                                    feeAmountInput.value = _formatNumber(feeAmount);
                                }
                            }
                        } else {
                            // اگر درصد صفر است، مبلغ را هم صفر می‌کنیم
                            if (feeAmountInput.autoNumeric) {
                                feeAmountInput.autoNumeric.set(0);
                            } else {
                                try {
                                    new AutoNumeric(feeAmountInput, {
                                        currencySymbol: '',
                                        decimalPlaces: 0,
                                        digitGroupSeparator: ',',
                                        decimalCharacter: '.',
                                        unformatOnSubmit: true
                                    }).set(0);
                                } catch (e) {
                                    feeAmountInput.value = '0';
                                }
                            }
                        }
                    }
                }
            }
        });
        
        // به‌روزرسانی خلاصه مالی
        _updateSummaryFields();
    }
    
    /**
     * پر کردن فیلدهای select مرکز ری‌گیری
     */
    function _fillAssayOfficeSelects() {
        // اطمینان از معتبر بودن داده‌های مراکز ری‌گیری
        if (!Array.isArray(_data.assayOffices) || _data.assayOffices.length === 0) {
            console.warn('داده‌های مراکز ری‌گیری نامعتبر یا خالی است');
            return;
        }
        
        console.log(`پر کردن ${_elements.assayOfficeSelects.length} فیلد مرکز ری‌گیری با ${_data.assayOffices.length} مرکز`);
        
        // پر کردن تمام فیلدهای select
        _elements.assayOfficeSelects.forEach(select => {
            // ذخیره مقدار فعلی
            const currentValue = select.value;
            
            // حذف گزینه‌های موجود
            while (select.options.length > 0) {
                select.remove(0);
            }
            
            // اضافه کردن گزینه پیش‌فرض
            const defaultOption = document.createElement('option');
            defaultOption.value = '0';
            defaultOption.textContent = 'انتخاب مرکز ری‌گیری...';
            select.appendChild(defaultOption);
            
            // افزودن گزینه‌ها
            _data.assayOffices.forEach(office => {
                const option = document.createElement('option');
                option.value = office.id;
                option.textContent = office.name;
                
                // انتخاب گزینه فعلی
                if (currentValue && currentValue == office.id) {
                    option.selected = true;
                }
                
                select.appendChild(option);
            });
        });
    }
    
    /**
     * به‌روزرسانی فیلدهای خلاصه
     */
    function _updateSummaryFields() {
        // محاسبه مجدد مجموع ارزش اقلام
        let sumBaseItems = 0;
        let sumProfitWageFee = 0;
        let totalGeneralTax = 0;
        let totalVat = 0;
        
        // جمع‌آوری مقادیر از تمام آیتم‌های معامله
        document.querySelectorAll('.transaction-item-row').forEach(row => {
            // تشخیص نوع محصول
            const productGroup = _getItemProductGroup(row);
            if (!productGroup) return;
            
            // جمع‌آوری قیمت کل
            let totalPriceInput = null;
            if (productGroup === 'melted') {
                totalPriceInput = row.querySelector('input[name*="item_total_price_melted"]');
            } else if (productGroup === 'manufactured') {
                totalPriceInput = row.querySelector('input[name*="item_total_price_manufactured"]');
            } else if (productGroup === 'coin') {
                totalPriceInput = row.querySelector('input[name*="item_total_price_coin"]');
            }
            
            if (totalPriceInput) {
                const totalPrice = parseFloat(totalPriceInput.value.replace(/,/g, '')) || 0;
                sumBaseItems += totalPrice;
            }
            
            // جمع‌آوری سود/اجرت
            let profitAmountInput = null;
            if (productGroup === 'melted') {
                profitAmountInput = row.querySelector('input[name*="item_profit_amount_melted"]');
            } else if (productGroup === 'manufactured') {
                // جمع اجرت و سود برای مصنوعات
                const wageInput = row.querySelector('input[name*="item_manufacturing_fee_amount_manufactured"]');
                const wageAmount = wageInput ? parseFloat(wageInput.value.replace(/,/g, '')) || 0 : 0;
                
                profitAmountInput = row.querySelector('input[name*="item_profit_amount_manufactured"]');
                const profitAmount = profitAmountInput ? parseFloat(profitAmountInput.value.replace(/,/g, '')) || 0 : 0;
                
                sumProfitWageFee += wageAmount;
            } else if (productGroup === 'coin') {
                profitAmountInput = row.querySelector('input[name*="item_profit_amount_coin"]');
            }
            
            if (profitAmountInput) {
                const profitAmount = parseFloat(profitAmountInput.value.replace(/,/g, '')) || 0;
                sumProfitWageFee += profitAmount;
            }
            
            // جمع‌آوری کارمزد
            let feeAmountInput = null;
            if (productGroup === 'melted') {
                feeAmountInput = row.querySelector('input[name*="item_fee_amount_melted"]');
            } else if (productGroup === 'manufactured') {
                feeAmountInput = row.querySelector('input[name*="item_fee_amount_manufactured"]');
            } else if (productGroup === 'coin') {
                feeAmountInput = row.querySelector('input[name*="item_fee_amount_coin"]');
            }
            
            if (feeAmountInput) {
                const feeAmount = parseFloat(feeAmountInput.value.replace(/,/g, '')) || 0;
                sumProfitWageFee += feeAmount;
            }
            
            // جمع‌آوری مالیات آبشده و ارزش افزوده آبشده (فقط برای آبشده)
            if (productGroup === 'melted') {
                const taxInput = row.querySelector('input[name*="item_general_tax_melted"]');
                if (taxInput) {
                    const taxAmount = parseFloat(taxInput.value.replace(/,/g, '')) || 0;
                    totalGeneralTax += taxAmount;
                }
                
                const vatInput = row.querySelector('input[name*="item_vat_melted"]');
                if (vatInput) {
                    const vatAmount = parseFloat(vatInput.value.replace(/,/g, '')) || 0;
                    totalVat += vatAmount;
                }
            }
        });
        
        // محاسبه جمع‌های نهایی
        const sumBeforeVat = sumBaseItems + sumProfitWageFee + totalGeneralTax;
        const finalPayable = sumBeforeVat + totalVat;
        
        // به‌روزرسانی فیلدهای خلاصه
        if (_elements.summaryElements.sumBaseItems) {
            _elements.summaryElements.sumBaseItems.textContent = _formatNumber(sumBaseItems);
        }
        
        if (_elements.summaryElements.sumProfitWageFee) {
            _elements.summaryElements.sumProfitWageFee.textContent = _formatNumber(sumProfitWageFee);
        }
        
        if (_elements.summaryElements.totalGeneralTax) {
            _elements.summaryElements.totalGeneralTax.textContent = _formatNumber(totalGeneralTax);
        }
        
        if (_elements.summaryElements.sumBeforeVat) {
            _elements.summaryElements.sumBeforeVat.textContent = _formatNumber(sumBeforeVat);
        }
        
        if (_elements.summaryElements.totalVat) {
            _elements.summaryElements.totalVat.textContent = _formatNumber(totalVat);
        }
        
        if (_elements.summaryElements.finalPayable) {
            _elements.summaryElements.finalPayable.textContent = _formatNumber(finalPayable);
        }
        
        // ذخیره مقادیر در فیلدهای مخفی
        _setHiddenField('total_items_value_rials', sumBaseItems);
        _setHiddenField('total_profit_wage_commission_rials', sumProfitWageFee);
        _setHiddenField('total_general_tax_rials', totalGeneralTax);
        _setHiddenField('total_before_vat_rials', sumBeforeVat);
        _setHiddenField('total_vat_rials', totalVat);
        _setHiddenField('final_payable_amount_rials', finalPayable);
    }
    
    /**
     * فرمت‌بندی اعداد با اضافه کردن جداکننده هزارگان
     * @param {number|string} number عدد موردنظر
     * @returns {string} عدد فرمت‌بندی شده
     */
    function _formatNumber(number) {
        // تبدیل ورودی به عدد و اطمینان از معتبر بودن آن
        let num = 0;
        try {
            // حذف کاراکترهای غیرعددی (مثل جداکننده هزارگان)
            if (typeof number === 'string') {
                number = number.replace(/[^\d.-]/g, '');
            }
            num = parseFloat(number);
            if (isNaN(num)) num = 0;
        } catch (e) {
            console.warn('خطا در تبدیل به عدد:', e);
            num = 0;
        }
        
        // فرمت‌بندی عدد با جداکننده هزارگان
        return num.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
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
    
    /**
     * اتصال رویدادهای اصلی
     */
    function _bindEvents() {
        // رویداد ارسال فرم
        if (_elements.form) {
            _elements.form.addEventListener('submit', function(event) {
                event.preventDefault();
                
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
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error ${response.status}`);
                    }
                    
                    // بررسی نوع محتوای پاسخ
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        // اگر پاسخ JSON نیست، ممکن است یک ریدایرکت یا صفحه HTML باشد
                        console.log('پاسخ غیر JSON از سرور دریافت شد - ریدایرکت به صفحه لیست معاملات');
                        window.location.href = window.baseUrl + '/app/transactions';
                        return null; // توقف پردازش بیشتر
                    }
                    
                    return response.json();
                })
                .then(data => {
                    if (!data) return; // اگر پاسخ غیر JSON بود و در مرحله قبل پردازش شد
                    
                    if (data.success) {
                        // موفقیت: ریدایرکت به لیست معاملات
                        window.location.href = window.baseUrl + '/app/transactions';
                    } else {
                        // خطا: نمایش پیام خطا
                        if (data.errors && Array.isArray(data.errors)) {
                            _showMessage('error', data.errors.join('<br>'));
                        } else if (data.message) {
                            _showMessage('error', data.message);
                        } else {
                            _showMessage('error', 'خطای ناشناخته در ذخیره فرم');
                        }
                    }
                })
                .catch(err => {
                    _showMessage('error', 'خطا در ارتباط با سرور');
                    console.error(err);
                });
            });
        }
        
        // رویداد تغییر نوع معامله برای هماهنگ کردن با وضعیت تحویل
        const transactionTypeSelect = document.getElementById('transaction_type');
        const deliveryStatusSelect = document.getElementById('delivery_status');
        
        if (transactionTypeSelect && deliveryStatusSelect) {
            transactionTypeSelect.addEventListener('change', function() {
                _synchronizeDeliveryStatusWithTransactionType();
            });
            
            // اجرای اولیه برای تنظیم وضعیت تحویل بر اساس نوع معامله فعلی
            _synchronizeDeliveryStatusWithTransactionType();
        }
        
        // رویداد محاسبه قیمت کل
        document.querySelectorAll('input[name*="weight_grams"], input[name*="item_weight_scale"], input[name*="item_unit_price"], input[name*="item_quantity"]').forEach(input => {
            input.addEventListener('change', _calculateTotalPrice);
            input.addEventListener('input', _calculateTotalPrice);
        });
        
        // رویداد محاسبه مبلغ سود
        document.querySelectorAll('input[name*="item_profit_percent"]').forEach(input => {
            input.addEventListener('change', _calculateProfitAmount);
            input.addEventListener('input', _calculateProfitAmount);
        });
        
        // رویداد محاسبه مبلغ کارمزد
        document.querySelectorAll('input[name*="item_fee_percent"]').forEach(input => {
            input.addEventListener('change', _calculateFeeAmount);
            input.addEventListener('input', _calculateFeeAmount);
        });
    }
    
    /**
     * هماهنگ کردن وضعیت تحویل با نوع معامله
     * برای معامله خرید، فقط وضعیت "منتظر دریافت" قابل انتخاب است
     * برای معامله فروش، فقط وضعیت "منتظر تحویل" قابل انتخاب است
     * برای هر دو نوع، وضعیت‌های "تکمیل شده" و "لغو شده" قابل انتخاب هستند
     */
    function _synchronizeDeliveryStatusWithTransactionType() {
        const transactionTypeSelect = document.getElementById('transaction_type');
        const deliveryStatusSelect = document.getElementById('delivery_status');
        
        if (!transactionTypeSelect || !deliveryStatusSelect) {
            console.warn('عناصر نوع معامله یا وضعیت تحویل یافت نشد.');
            return;
        }
        
        const transactionType = transactionTypeSelect.value;
        console.log('هماهنگ‌سازی وضعیت تحویل با نوع معامله:', transactionType);
        
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
                        console.log('تغییر وضعیت تحویل به "منتظر دریافت"');
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
                        console.log('تغییر وضعیت تحویل به "منتظر تحویل"');
                    }
                }
            });
        }
        
        console.log('وضعیت تحویل پس از هماهنگ‌سازی:', deliveryStatusSelect.value);
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
     * اصلاح فیلدهای خالی قبل از ارسال فرم
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
        
        // اصلاح سایر فیلدهای خالی که ممکن ایجاد کنند
        const numericFields = document.querySelectorAll('.autonumeric');
        numericFields.forEach(field => {
            if (!field.value || field.value.trim() === '') {
                field.value = '0';
            }
        });
    }
    
    /**
     * راه‌اندازی AutoNumeric برای تمام فیلدهای عددی
     */
    function _initializeAllAutonumerics() {
        // راه‌اندازی AutoNumeric برای تمام فیلدهای با کلاس autonumeric
        document.querySelectorAll('.autonumeric').forEach(function(element) {
            try {
                // بررسی اینکه آیا قبلاً AutoNumeric روی فیلد مقداردهی شده است
                let existingAN = null;
                try {
                    existingAN = AutoNumeric.getAutoNumericElement(element);
                } catch (e) {
                    // فیلد در AutoNumeric ثبت نشده است
                }
                
                // تنظیم متفاوت برای فیلدهای وزن (با اعشار) و سایر فیلدها (بدون اعشار)
                const isWeightField = element.name && (
                    element.name.includes('weight_scale') || 
                    element.name.includes('weight_grams') ||
                    element.name.includes('carat')
                );
                
                const config = {
                    currencySymbol: '',
                    decimalPlaces: isWeightField ? 4 : 0,
                    digitGroupSeparator: ',',
                    decimalCharacter: '.',
                    unformatOnSubmit: true
                };
                
                if (!existingAN) {
                    new AutoNumeric(element, config);
                } else {
                    // اگر از قبل مقداردهی شده، مطمئن شویم تنظیمات درست است
                    existingAN.update(config);
                }
            } catch (e) {
                console.error(`خطا در راه‌اندازی AutoNumeric برای عنصر:`, element, e);
            }
        });
        
        // اتصال رویدادهای محاسباتی
        _initializeItemCalculations();
    }
    
    /**
     * اتصال رویدادهای محاسباتی برای محاسبه خودکار فیلدها
     */
    function _initializeItemCalculations() {
        // محاسبه قیمت کل برای آبشده
        const weightInputs = document.querySelectorAll('input[name*="weight_grams"]');
        const unitPriceInputs = document.querySelectorAll('input[name*="item_unit_price_melted"]');
        
        weightInputs.forEach(input => {
            input.addEventListener('input', _calculateTotalPrice);
        });
        
        unitPriceInputs.forEach(input => {
            input.addEventListener('input', _calculateTotalPrice);
        });
        
        // محاسبه مبلغ سود
        const profitPercentInputs = document.querySelectorAll('input[name*="profit_percent"]');
        profitPercentInputs.forEach(input => {
            input.addEventListener('input', _calculateProfitAmount);
        });
        
        // محاسبه مبلغ کارمزد
        const feePercentInputs = document.querySelectorAll('input[name*="fee_percent"]');
        feePercentInputs.forEach(input => {
            input.addEventListener('input', _calculateFeeAmount);
        });
    }
    
    /**
     * تابع جنرال برای محاسبه مقدار بر اساس درصد و مقدار پایه
     */
    function _calculatePercentAmount(baseValue, percent) {
        return (parseFloat(baseValue) || 0) * (parseFloat(percent) || 0) / 100;
    }

    /**
     * محاسبه قیمت کل آیتم (برای همه گروه‌ها)
     */
    function _calculateTotalPrice(event) {
        const row = event.target.closest('.transaction-item-row');
        if (!row) return;
        const productGroup = _getItemProductGroup(row);
        let weightInput, unitPriceInput, totalPriceInput, quantityInput;
        if (productGroup === 'melted') {
            weightInput = row.querySelector('input[name*="item_weight_scale_melted"]');
            unitPriceInput = row.querySelector('input[name*="item_unit_price_melted"]');
            totalPriceInput = row.querySelector('input[name*="item_total_price_melted"]');
        } else if (productGroup === 'manufactured') {
            weightInput = row.querySelector('input[name*="item_weight_scale_manufactured"]');
            unitPriceInput = row.querySelector('input[name*="item_unit_price_manufactured"]');
            totalPriceInput = row.querySelector('input[name*="item_total_price_manufactured"]');
            quantityInput = row.querySelector('input[name*="quantity"]');
        } else if (productGroup === 'coin') {
            quantityInput = row.querySelector('input[name*="item_quantity_coin"]') || row.querySelector('input[name*="quantity"]');
            unitPriceInput = row.querySelector('input[name*="item_unit_price_coin"]');
            totalPriceInput = row.querySelector('input[name*="item_total_price_coin"]');
        }
        let total = 0;
        if (weightInput && unitPriceInput) {
            total = (parseFloat(weightInput.value.replace(/,/g, '')) || 0) * (parseFloat(unitPriceInput.value.replace(/,/g, '')) || 0);
        } else if (quantityInput && unitPriceInput) {
            total = (parseFloat(quantityInput.value.replace(/,/g, '')) || 0) * (parseFloat(unitPriceInput.value.replace(/,/g, '')) || 0);
        }
        if (totalPriceInput) {
            if (totalPriceInput.autoNumeric) {
                totalPriceInput.autoNumeric.set(total);
            } else {
                try {
                    new AutoNumeric(totalPriceInput, {
                        currencySymbol: '',
                        decimalPlaces: 0,
                        digitGroupSeparator: ',',
                        decimalCharacter: '.',
                        unformatOnSubmit: true
                    }).set(total);
                } catch (e) {
                    totalPriceInput.value = _formatNumber(total);
                }
            }
        }
        _updateSummaryFields();
    }

    /**
     * محاسبه مبلغ سود (برای همه گروه‌ها)
     */
    function _calculateProfitAmount(event) {
        if (!event || !event.target) return;
        const row = event.target.closest('.transaction-item-row');
        if (!row) return;
        const productGroup = _getItemProductGroup(row);
        let totalPriceInput, profitPercentInput, profitAmountInput;
        if (productGroup === 'melted') {
            totalPriceInput = row.querySelector('input[name*="item_total_price_melted"]');
            profitPercentInput = row.querySelector('input[name*="item_profit_percent_melted"]');
            profitAmountInput = row.querySelector('input[name*="item_profit_amount_melted"]');
        } else if (productGroup === 'manufactured') {
            totalPriceInput = row.querySelector('input[name*="item_total_price_manufactured"]');
            profitPercentInput = row.querySelector('input[name*="item_profit_percent_manufactured"]');
            profitAmountInput = row.querySelector('input[name*="item_profit_amount_manufactured"]');
        } else if (productGroup === 'coin') {
            totalPriceInput = row.querySelector('input[name*="item_total_price_coin"]');
            profitPercentInput = row.querySelector('input[name*="item_profit_percent_coin"]');
            profitAmountInput = row.querySelector('input[name*="item_profit_amount_coin"]');
        } else {
            totalPriceInput = row.querySelector('input[name*="item_total_price"]');
            profitPercentInput = event.target;
            profitAmountInput = row.querySelector('input[name*="item_profit_amount"]');
        }
        if (!profitPercentInput || !totalPriceInput || !profitAmountInput) return;
        const percent = parseFloat(profitPercentInput.value.replace(/,/g, '')) || 0;
        const totalPrice = parseFloat(totalPriceInput.value.replace(/,/g, '')) || 0;
        const profitAmount = _calculatePercentAmount(totalPrice, percent);
        if (profitAmountInput.autoNumeric) {
            profitAmountInput.autoNumeric.set(profitAmount);
        } else {
            try {
                new AutoNumeric(profitAmountInput, {
                    currencySymbol: '',
                    decimalPlaces: 0,
                    digitGroupSeparator: ',',
                    decimalCharacter: '.',
                    unformatOnSubmit: true
                }).set(profitAmount);
            } catch (e) {
                profitAmountInput.value = _formatNumber(profitAmount);
            }
        }
        _updateSummaryFields();
    }

    /**
     * محاسبه مبلغ کارمزد (برای همه گروه‌ها)
     */
    function _calculateFeeAmount(event) {
        if (!event || !event.target) return;
        const row = event.target.closest('.transaction-item-row');
        if (!row) return;
        const productGroup = _getItemProductGroup(row);
        let totalPriceInput, feePercentInput, feeAmountInput;
        if (productGroup === 'melted') {
            totalPriceInput = row.querySelector('input[name*="item_total_price_melted"]');
            feePercentInput = row.querySelector('input[name*="item_fee_percent_melted"]');
            feeAmountInput = row.querySelector('input[name*="item_fee_amount_melted"]');
        } else if (productGroup === 'manufactured') {
            totalPriceInput = row.querySelector('input[name*="item_total_price_manufactured"]');
            feePercentInput = row.querySelector('input[name*="item_fee_percent_manufactured"]');
            feeAmountInput = row.querySelector('input[name*="item_fee_amount_manufactured"]');
        } else if (productGroup === 'coin') {
            totalPriceInput = row.querySelector('input[name*="item_total_price_coin"]');
            feePercentInput = row.querySelector('input[name*="item_fee_percent_coin"]');
            feeAmountInput = row.querySelector('input[name*="item_fee_amount_coin"]');
        } else {
            totalPriceInput = row.querySelector('input[name*="item_total_price"]');
            feePercentInput = event.target;
            feeAmountInput = row.querySelector('input[name*="item_fee_amount"]');
        }
        if (!feePercentInput || !totalPriceInput || !feeAmountInput) return;
        const percent = parseFloat(feePercentInput.value.replace(/,/g, '')) || 0;
        const totalPrice = parseFloat(totalPriceInput.value.replace(/,/g, '')) || 0;
        const feeAmount = _calculatePercentAmount(totalPrice, percent);
        if (feeAmountInput.autoNumeric) {
            feeAmountInput.autoNumeric.set(feeAmount);
        } else {
            try {
                new AutoNumeric(feeAmountInput, {
                    currencySymbol: '',
                    decimalPlaces: 0,
                    digitGroupSeparator: ',',
                    decimalCharacter: '.',
                    unformatOnSubmit: true
                }).set(feeAmount);
            } catch (e) {
                feeAmountInput.value = _formatNumber(feeAmount);
            }
        }
        _updateSummaryFields();
    }
    
    /**
     * تشخیص نوع محصول (گروه) برای یک ردیف آیتم معامله
     * @param {HTMLElement} row - عنصر ردیف آیتم
     * @return {string|null} - نوع محصول ('melted', 'manufactured', 'coin') یا null
     */
    function _getItemProductGroup(row) {
        // بررسی وجود فیلدهای مخصوص آبشده
        if (row.querySelector('input[name*="_melted"]')) {
            return 'melted';
        } 
        // بررسی وجود فیلدهای مخصوص مصنوعات
        else if (row.querySelector('input[name*="_manufactured"]')) {
            return 'manufactured';
        } 
        // بررسی وجود فیلدهای مخصوص سکه
        else if (row.querySelector('input[name*="_coin"]')) {
            return 'coin';
        }
        
        // تلاش برای تشخیص از طریق متن محصول
        const productNameElement = row.querySelector('option:checked') || row.querySelector('select option:checked');
        if (productNameElement) {
            const productName = productNameElement.textContent.toLowerCase();
            if (productName.includes('آبشده') || productName.includes('شمش')) {
                return 'melted';
            } else if (productName.includes('سکه')) {
                return 'coin';
            } else {
                return 'manufactured'; // پیش‌فرض
            }
        }
        
        return null; // گروه مشخص نیست
    }
    
    /**
     * بارگذاری و مپینگ داده‌های آیتم‌ها به فیلدهای فرم
     */
    function _loadItemsData() {
        console.log('Loading items data...');
        
        // بررسی وجود آیتم‌ها
        if (!Array.isArray(_data.items) || _data.items.length === 0) {
            console.warn('No items data available or empty items array');
            return;
        }
        
        // گروه‌بندی فیلدها بر اساس گروه محصول
        const fieldsByGroup = {};
        if (Array.isArray(_data.fields)) {
            _data.fields.forEach(field => {
                if (field.group && field.name) {
                    const group = field.group.toLowerCase();
                    if (!fieldsByGroup[group]) {
                        fieldsByGroup[group] = [];
                    }
                    fieldsByGroup[group].push(field);
                }
            });
        }
        
        // برای هر آیتم، فیلدهای مربوطه را پر کن
        _data.items.forEach((item, index) => {
            console.log(`Processing item #${index+1}:`, item);
            
            // تشخیص نوع محصول (گروه) برای اعمال فیلدهای مخصوص
            let productGroup = '';
            
            // روش 1: گروه پایه از اطلاعات دسته‌بندی محصول
            if (item.product_category_base) {
                productGroup = item.product_category_base.toLowerCase();
                console.log(`Found product_category_base for item #${index+1}: ${productGroup}`);
            }
            
            // روش 2: استفاده از داده‌های محصول
            if (!productGroup && _data.products && Array.isArray(_data.products)) {
                const product = _data.products.find(p => {
                    if (typeof p === 'object' && p !== null) {
                        return p.id == item.product_id;
                    }
                    return false;
                });
                
                if (product) {
                    console.log(`Found product for item #${index+1}:`, product);
                    if (typeof product === 'object' && product !== null) {
                        // استفاده از گروه پایه یا کد دسته‌بندی
                        if (product.category && product.category.base_category) {
                            productGroup = product.category.base_category.toLowerCase();
                        } else if (product.category_base_category) {
                            productGroup = product.category_base_category.toLowerCase();
                        } else if (product.category && product.category.code) {
                            productGroup = _mapCategoryCodeToGroup(product.category.code);
                        } else if (product.category_code) {
                            productGroup = _mapCategoryCodeToGroup(product.category_code);
                        }
                    }
                }
            }
            
            // روش 3: استفاده از کد دسته‌بندی محصول
            if (!productGroup && item.product_category_code) {
                productGroup = _mapCategoryCodeToGroup(item.product_category_code);
                console.log(`Mapped category code ${item.product_category_code} to group ${productGroup}`);
            }
            
            // روش 4: استفاده از واحد اندازه‌گیری
            if (!productGroup && item.product_unit_of_measure) {
                if (item.product_unit_of_measure === 'count') {
                    productGroup = 'coin';
                } else if (item.product_unit_of_measure === 'gram') {
                    // بررسی اگر نام محصول شامل "آبشده" باشد
                    if (item.product_name && item.product_name.includes('آبشده')) {
                        productGroup = 'melted';
                    } else {
                        productGroup = 'manufactured';
                    }
                }
                console.log(`Determined group from unit_of_measure: ${productGroup}`);
            }
            
            // روش 5: استفاده از نام محصول
            if (!productGroup && item.product_name) {
                if (item.product_name.includes('سکه') || 
                    item.product_name.includes('طلا') ||
                    item.product_name.includes('امامی') ||
                    item.product_name.includes('بهار آزادی')) {
                    productGroup = 'coin';
                } else if (item.product_name.includes('آبشده') ||
                         item.product_name.includes('شمش')) {
                    productGroup = 'melted';
                } else {
                    productGroup = 'manufactured'; // پیش‌فرض: مصنوعات
                }
                console.log(`Determined group from product name: ${productGroup}`);
            }
            
            // روش 6: استفاده از فیلدهای تخصصی موجود
            if (!productGroup) {
                if (item.hasOwnProperty('item_carat_melted') || 
                    item.hasOwnProperty('item_assay_office_melted') ||
                    item.hasOwnProperty('tag_number') ||
                    item.hasOwnProperty('item_tag_number_melted')) {
                    productGroup = 'melted';
                } else if (item.hasOwnProperty('item_carat_manufactured') ||
                       item.hasOwnProperty('item_manufacturing_fee_percent_manufactured')) {
                    productGroup = 'manufactured';
                } else if (item.hasOwnProperty('item_quantity_coin') ||
                        item.hasOwnProperty('item_year_coin') ||
                        item.hasOwnProperty('coin_year')) {
                    productGroup = 'coin';
                }
                
                if (productGroup) {
                    console.log(`Determined group from specialized fields: ${productGroup}`);
                }
            }
            
            // روش 7: مقدار پیش‌فرض
            if (!productGroup) {
                // بر اساس نوع محصول معمول، پیش‌فرض آبشده در نظر گرفته می‌شود
                productGroup = 'melted'; 
                console.log(`Using default group for item #${index+1}: ${productGroup}`);
            }
            
            console.log(`Item #${index+1} final product group:`, productGroup);
            
            // پر کردن فیلدهای مشترک
            // وزن - با استفاده از نام فیلد صحیح بر اساس نوع محصول
            if (item.weight_grams !== undefined) {
                if (productGroup === 'melted') {
                    _setFieldValue(`items[${index}][item_weight_scale_melted]`, item.weight_grams);
                } else if (productGroup === 'manufactured') {
                    _setFieldValue(`items[${index}][item_weight_scale_manufactured]`, item.weight_grams);
                } else {
                    _setFieldValue(`items[${index}][item_weight_scale]`, item.weight_grams);
                }
            }
            
            // تعداد
            if (item.quantity !== undefined) {
                if (productGroup === 'coin') {
                    _setFieldValue(`items[${index}][item_quantity_coin]`, item.quantity);
                } else {
                    _setFieldValue(`items[${index}][quantity]`, item.quantity);
                }
            }
            
            // قیمت واحد (مستقیم)
            if (item.unit_price_rials !== undefined) {
                const fieldName = `items[${index}][item_unit_price_${productGroup}]`;
                _setFieldValue(fieldName, item.unit_price_rials);
            }
            
            // قیمت کل (مستقیم)
            if (item.total_value_rials !== undefined) {
                const fieldName = `items[${index}][item_total_price_${productGroup}]`;
                _setFieldValue(fieldName, item.total_value_rials);
            }
            
            // عیار
            if (item.carat !== undefined) {
                const fieldName = `items[${index}][item_carat_${productGroup}]`;
                _setFieldValue(fieldName, item.carat);
            }
            
            // سود و کارمزد - فقط مقادیر موجود را استفاده می‌کنیم، بدون هیچ مقدار پیش‌فرض
            // درصد سود
            if (item.profit_percent !== undefined) {
                const fieldName = `items[${index}][item_profit_percent_${productGroup}]`;
                _setFieldValue(fieldName, item.profit_percent);
            }
            
            // مبلغ سود
            if (item.profit_amount !== undefined) {
                const fieldName = `items[${index}][item_profit_amount_${productGroup}]`;
                _setFieldValue(fieldName, item.profit_amount);
            }
            
            // درصد کارمزد
            if (item.fee_percent !== undefined) {
                const fieldName = `items[${index}][item_fee_percent_${productGroup}]`;
                _setFieldValue(fieldName, item.fee_percent);
            }
            
            // مبلغ کارمزد
            if (item.fee_amount !== undefined) {
                const fieldName = `items[${index}][item_fee_amount_${productGroup}]`;
                _setFieldValue(fieldName, item.fee_amount);
            }
            
            // پر کردن فیلدهای مخصوص گروه
            switch (productGroup) {
                case 'melted':
                    // مرکز ری‌گیری
                    if (item.assay_office_id !== undefined) {
                        _setFieldValue(`items[${index}][item_assay_office_melted]`, item.assay_office_id);
                    }
                    
                    // شماره انگ
                    if (item.tag_number !== undefined) {
                        _setFieldValue(`items[${index}][item_tag_number_melted]`, item.tag_number);
                    }
                    
                    // نوع انگ
                    if (item.tag_type !== undefined) {
                        _setFieldValue(`items[${index}][item_tag_type_melted]`, item.tag_type);
                    }
                    break;
                    
                case 'manufactured':
                    // درصد اجرت
                    if (item.manufacturing_fee_percent !== undefined) {
                        _setFieldValue(`items[${index}][item_manufacturing_fee_percent_manufactured]`, item.manufacturing_fee_percent);
                    }
                    
                    // مبلغ اجرت
                    if (item.manufacturing_fee_amount !== undefined) {
                        _setFieldValue(`items[${index}][item_manufacturing_fee_amount_manufactured]`, item.manufacturing_fee_amount);
                    } else if (item.wage_amount !== undefined) {
                        _setFieldValue(`items[${index}][item_manufacturing_fee_amount_manufactured]`, item.wage_amount);
                    }
                    break;
                    
                case 'coin':
                    // سال ضرب
                    if (item.coin_year !== undefined) {
                        _setFieldValue(`items[${index}][item_year_coin]`, item.coin_year);
                    }
                    break;
            }
        });
    }
    
    /**
     * تبدیل کد دسته‌بندی به گروه محصول
     * @param {string} categoryCode کد دسته‌بندی
     * @return {string} نام گروه محصول
     */
    function _mapCategoryCodeToGroup(categoryCode) {
        const codeMap = {
            'new_jewelry': 'manufactured',
            'used_jewelry': 'manufactured',
            'melted': 'melted',
            'bullion': 'melted',
            'coin_emami': 'coin',
            'coin_bahar_azadi_new': 'coin',
            'coin_bahar_azadi_old': 'coin',
            'coin_half': 'coin',
            'coin_quarter': 'coin',
            'coin_gerami': 'coin',
            'other_coin': 'coin',
            // نگاشت‌های احتمالی دیگر اضافه شوند
        };
        
        return codeMap[categoryCode] || 'melted'; // پیش‌فرض: آبشده
    }
    
    /**
     * تنظیم مقدار فیلد فرم با نام مشخص
     * @param {string} fieldName نام فیلد
     * @param {any} value مقدار فیلد
     */
    function _setFieldValue(fieldName, value) {
        // بررسی مقادیر نامعتبر
        if (value === undefined || value === null) {
            console.debug(`Skipping field ${fieldName} with undefined/null value`);
            return;
        }
        
        // یافتن عنصر
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (!field) {
            console.debug(`Field not found: ${fieldName}`);
            return;
        }
        
        try {
            // تنظیم مقدار بر اساس نوع فیلد
            if (field.tagName === 'SELECT') {
                // برای select ها
                if (field.querySelector(`option[value="${value}"]`)) {
                    field.value = value;
                } else {
                    console.warn(`Option with value ${value} not found in select ${fieldName}`);
                }
            } else if (field.type === 'checkbox') {
                // برای چک باکس‌ها
                field.checked = Boolean(value);
            } else if (field.type === 'radio') {
                // برای رادیو دکمه‌ها
                const radioGroup = document.querySelectorAll(`[name="${fieldName}"]`);
                radioGroup.forEach(radio => {
                    radio.checked = (radio.value == value);
                });
            } else {
                // برای سایر فیلدها (text, number, ...)
                
                // اگر فیلد Autonumeric است
                if (field.classList.contains('autonumeric') && window.AutoNumeric) {
                    // تبدیل مقدار به عدد
                    let numValue;
                    try {
                        // حذف کاراکترهای فرمت‌بندی
                        if (typeof value === 'string') {
                            numValue = parseFloat(value.replace(/[^\d.-]/g, ''));
                        } else {
                            numValue = parseFloat(value);
                        }
                        
                        if (isNaN(numValue)) numValue = 0;
                    } catch (e) {
                        console.warn(`Error converting value to number for field ${fieldName}:`, e);
                        numValue = 0;
                    }
                    
                    // تنظیم متفاوت برای فیلدهای وزن (با اعشار) و سایر فیلدها (بدون اعشار)
                    const isWeightField = fieldName && (
                        fieldName.includes('weight_scale') || 
                        fieldName.includes('weight_grams') ||
                        fieldName.includes('carat')
                    );
                    
                    const config = {
                        currencySymbol: '',
                        decimalPlaces: isWeightField ? 4 : 0,
                        digitGroupSeparator: ',',
                        decimalCharacter: '.',
                        unformatOnSubmit: true
                    };
                    
                    // بررسی آیا فیلد دارای نمونه AutoNumeric است
                    let anInstance = null;
                    try {
                        anInstance = AutoNumeric.getAutoNumericElement(field);
                    } catch (e) {
                        // فیلد هنوز مقداردهی نشده است
                        anInstance = null;
                    }
                    
                    if (anInstance) {
                        // استفاده از نمونه موجود و بروزرسانی تنظیمات آن
                        anInstance.update(config);
                        anInstance.set(numValue);
                    } else {
                        // ابتدا مقدار عددی را تنظیم کنیم
                        field.value = numValue.toString();
                        
                        // سپس AutoNumeric را مقداردهی کنیم
                        try {
                            new AutoNumeric(field, config);
                        } catch (e) {
                            console.debug(`Could not initialize AutoNumeric for field ${fieldName}:`, e);
                        }
                    }
                } else {
                    // فیلدهای معمولی
                    field.value = value;
                }
            }
        } catch (e) {
            console.error(`Error setting value for field ${fieldName}:`, e);
        }
    }
    
    /**
     * بارگذاری اطلاعات طرف حساب
     */
    function _loadPartyData() {
        console.log('Loading party data...');
        
        // بررسی وجود اطلاعات معامله
        if (!_data.transaction) {
            console.warn('No transaction data available');
            return;
        }
        
        // منطق جدید برای بارگذاری اطلاعات طرف حساب با اولویت‌های مشخص:
        
        // 1. اولویت اول: اطلاعات مستقیم party_name/party_phone/party_national_code
        let partyName = _data.transaction.party_name || null;
        let partyPhone = _data.transaction.party_phone || null;
        let partyNationalCode = _data.transaction.party_national_code || null;
        
        // 2. اولویت دوم: اطلاعات counterparty_name
        if (!partyName) partyName = _data.transaction.counterparty_name || null;
        
        // 3. اولویت سوم: اطلاعات از لیست مخاطبین
        if ((!partyName || !partyPhone || !partyNationalCode) && _data.transaction.counterparty_contact_id && _data.contacts) {
            const contactId = _data.transaction.counterparty_contact_id;
            const contact = _data.contacts.find(c => {
                if (typeof c === 'object' && c !== null) {
                    return c.id == contactId;
                }
                return false;
            });
            
            if (contact) {
                console.log('Found contact data:', contact);
                if (!partyName) partyName = contact.name;
                if (!partyPhone) partyPhone = contact.phone || '';
                if (!partyNationalCode) partyNationalCode = contact.national_code || '';
            }
        }
        
        // تنظیم مقادیر فیلدها (با مقادیر پیش‌فرض خالی اگر مقداری پیدا نشد)
        _setFieldValue('party_name', partyName || '');
        _setFieldValue('party_phone', partyPhone || '');
        _setFieldValue('party_national_code', partyNationalCode || '');
        
        console.log('Party data loaded:', { partyName, partyPhone, partyNationalCode });
    }
    
    // API عمومی
    return {
        init: _init
    };
})();

// راه‌اندازی برنامه پس از بارگذاری کامل صفحه
document.addEventListener('DOMContentLoaded', function() {
    // راه‌اندازی تقویم شمسی
    if (window.jalaliDatepicker) jalaliDatepicker.startWatch();
    
    // راه‌اندازی برنامه اصلی
    TransactionEditApp.init();
}); 