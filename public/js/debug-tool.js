/**
 * debug-tool.js - ابزار دیباگ پیشرفته برای فرم‌های تراکنش.
 * این اسکریپت فقط در محیط توسعه بارگذاری می‌شود.
 */

document.addEventListener('DOMContentLoaded', function() {
    // اطمینان از اینکه TransactionFormApp مقداردهی اولیه شده است
    if (typeof TransactionFormApp === 'undefined') {
        console.warn('Debug tool: TransactionFormApp not found. Aborting debug tool initialization.');
        return;
    }

    const debugBtn = document.createElement('button');
    debugBtn.innerText = 'نمایش داده‌های دیباگ';
    debugBtn.style.position = 'fixed';
    debugBtn.style.bottom = '10px';
    debugBtn.style.right = '10px';
    debugBtn.style.zIndex = '9999';
    debugBtn.style.padding = '5px 10px';
    debugBtn.style.background = '#007bff';
    debugBtn.style.color = 'white';
    debugBtn.style.border = 'none';
    debugBtn.style.borderRadius = '5px';
    debugBtn.style.cursor = 'pointer';
    document.body.appendChild(debugBtn);

    debugBtn.onclick = function() {
        const appData = TransactionFormApp.getData();
        const appConfig = TransactionFormApp.getConfig();

        console.log('=== DEBUG DATA ===');
        console.log('App Config:', appConfig);
        console.log('Transaction Data:', appData.transaction);
        console.log('Transaction Items Data:', appData.items);
        console.log('Products Data:', appData.products);
        console.log('Contacts Data:', appData.contacts);
        console.log('Assay Offices Data:', appData.assayOffices);
        console.log('Fields Data (All):', appData.fields);
        console.log('Formulas Data (All):', appData.formulas);
        console.log('Default Settings:', appData.defaultSettings);

        // ایجاد یک دیالوگ ساده برای نمایش اطلاعات
        const debugOutput = document.createElement('div');
        debugOutput.style.position = 'fixed';
        debugOutput.style.top = '10%';
        debugOutput.style.left = '10%';
        debugOutput.style.width = '80%';
        debugOutput.style.height = '80%';
        debugOutput.style.background = 'white';
        debugOutput.style.border = '1px solid #ccc';
        debugOutput.style.padding = '20px';
        debugOutput.style.zIndex = '10000';
        debugOutput.style.overflow = 'auto';
        debugOutput.style.direction = 'ltr';
        
        const transactionType = appData.transaction?.transaction_type || 'نامشخص';
        const deliveryStatus = appData.transaction?.delivery_status || 'نامشخص';

        debugOutput.innerHTML = `
            <h3>اطلاعات دیباگ</h3>
            <p><strong>نوع معامله:</strong> ${transactionType}</p>
            <p><strong>وضعیت تحویل:</strong> ${deliveryStatus}</p>
            <p><strong>تعداد آیتم‌ها:</strong> ${(appData.items || []).length}</p>
            <p><strong>اطلاعات طرف حساب:</strong></p>
            <ul>
                <li>نام: ${appData.transaction?.party_name || appData.transaction?.counterparty_name || 'نامشخص'}</li>
                <li>تلفن: ${appData.transaction?.party_phone || 'نامشخص'}</li>
                <li>کد ملی: ${appData.transaction?.party_national_code || 'نامشخص'}</li>
            </ul>
            <h4>داده‌های محصولات (آیتم‌های معامله):</h4>
            <div style="max-height: 150px; overflow: auto;">
                ${(appData.items || []).map((item, index) => `
                    <div style="border: 1px solid #eee; padding: 10px; margin-bottom: 10px;">
                        <p><strong>آیتم ${index + 1}:</strong></p>
                        <ul>
                            <li>محصول: ${item.product_name || 'نامشخص'}</li>
                            <li>دسته پایه: ${item.product_category_base || 'نامشخص'}</li>
                            <li>کد دسته: ${item.product_category_code || 'نامشخص'}</li>
                            <li>واحد اندازه‌گیری: ${item.product_unit_of_measure || 'نامشخص'}</li>
                            <li>وزن: ${item.weight_grams || '0'} گرم</li>
                            <li>تعداد: ${item.quantity || '1'}</li>
                            <li>عیار: ${item.carat || '18'}</li>
                            <li>قیمت واحد: ${item.unit_price_rials || '0'} ریال</li>
                            <li>مبلغ اجرت: ${item.ajrat_rials || '0'} ریال</li>
                            <li>شماره انگ: ${item.tag_number || 'ندارد'}</li>
                        </ul>
                    </div>
                `).join('')}
            </div>
            <h4>داده‌های کامل معامله:</h4>
            <pre style="direction: ltr; text-align: left; background: #f5f5f5; padding: 10px; max-height: 200px; overflow: auto;">${JSON.stringify(appData.transaction, null, 2)}</pre>
            <h4>داده‌های کامل آیتم‌ها:</h4>
            <pre style="direction: ltr; text-align: left; background: #f5f5f5; padding: 10px; max-height: 200px; overflow: auto;">${JSON.stringify(appData.items, null, 2)}</pre>
            
            <div style="margin-top: 20px;">
                <button id="close-debug" style="background: #dc3545; color: white; border: none; padding: 5px 10px; margin-top: 10px; cursor: pointer;">بستن</button>
                <button id="db-fix-delivery-status" style="background: #28a745; color: white; border: none; padding: 5px 10px; margin-top: 10px; margin-right: 10px; cursor: pointer;">تصحیح وضعیت تحویل</button>
            </div>
        `;
        
        document.body.appendChild(debugOutput);
        
        document.getElementById('close-debug').onclick = function() {
            document.body.removeChild(debugOutput);
        };
        
        document.getElementById('db-fix-delivery-status').onclick = function() {
            const select = document.getElementById('delivery_status');
            if (!select) {
                console.error('فیلد وضعیت تحویل یافت نشد!');
                return;
            }
            const transactionType = appData.transaction?.transaction_type;
            if (transactionType === 'buy') {
                select.value = 'pending_receipt';
                console.log('وضعیت تحویل به "منتظر دریافت" تغییر یافت.');
            } else if (transactionType === 'sell') {
                select.value = 'pending_delivery';
                console.log('وضعیت تحویل به "منتظر تحویل" تغییر یافت.');
            }
            select.dispatchEvent(new Event('change'));
        };
    };
});