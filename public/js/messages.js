if (typeof window.MESSAGES === 'undefined') {
    window.MESSAGES = {
        // پیام‌های عمومی
        success_save: 'اطلاعات با موفقیت ذخیره شد.',
        error_save: 'خطا در ذخیره اطلاعات!',
        required_field: 'پر کردن این فیلد الزامی است.',
        invalid_number: 'عدد وارد شده معتبر نیست.',
        not_found: 'موردی یافت نشد.',
        access_denied: 'دسترسی غیرمجاز!',

        // پیام‌های فرم تراکنش
        transaction_added: 'تراکنش با موفقیت ثبت شد.',
        transaction_failed: 'ثبت تراکنش با خطا مواجه شد.',
        transaction_deleted: 'تراکنش حذف شد.',
        transaction_create_success: 'تراکنش جدید با موفقیت ثبت شد',
        transaction_update_success: 'تراکنش با موفقیت به‌روزرسانی شد',
        transaction_delete_success: 'تراکنش با موفقیت حذف شد',
        transaction_save_error: 'خطا در ذخیره تراکنش',
        transaction_delete_error: 'خطا در حذف تراکنش',
        transaction_not_found: 'تراکنش مورد نظر یافت نشد',
        transaction_items_required: 'حداقل یک ردیف کالا باید وارد شود',
        
        // پیام‌های موجودی و تراز عملکرد
        inventory_update_success: 'موجودی با موفقیت به‌روزرسانی شد',
        inventory_update_error: 'خطا در به‌روزرسانی موجودی',
        delivery_receipt_completed: 'دریافت با موفقیت تایید شد و موجودی به‌روز شد',
        delivery_send_completed: 'تحویل با موفقیت تایید شد',
        delivery_completion_error: 'خطا در تکمیل فرایند تحویل',
        capital_performance_shortage: 'کمبود موجودی نسبت به هدف تعیین شده',
        capital_performance_excess: 'موجودی بیش از هدف تعیین شده',
        
        // پیام‌های عملیات
        edit: 'ویرایش',
        add: 'افزودن',
        delete: 'حذف',
        save: 'ذخیره',
        cancel: 'انصراف',

        // پیام‌های خطا
        invalid_assay_offices_data: 'داده‌های مراکز ری‌گیری نامعتبر یا خالی است',
        assay_office_load_error: 'خطا در بارگذاری مراکز ری‌گیری',
        assay_office_required: 'انتخاب مرکز ری‌گیری برای طلای آبشده الزامی است',
        assay_office_not_found: 'مرکز ری‌گیری انتخاب شده یافت نشد',
        assay_office_select_default: 'انتخاب مرکز ری‌گیری...',
        foreign_key_constraint_error: 'خطا در ذخیره‌سازی: محدودیت کلید خارجی رعایت نشده است',
        field_rendering_error: 'خطا در نمایش فیلدها',
        csrf_token_invalid: 'توکن امنیتی نامعتبر است - لطفا صفحه را رفرش کنید.',
        csrf_token_missing: 'توکن امنیتی موجود نیست - لطفا صفحه را رفرش کنید.',
        
        // پیام‌های خطای سرور
        server_error: 'خطای سرور',
        server_error_details: 'خطای داخلی سرور رخ داده است.',
        database_error: 'خطای پایگاه داده',
        database_constraint_error: 'خطای محدودیت پایگاه داده',
        invalid_request_method: 'متد درخواست نامعتبر است',
        invalid_response_format: 'فرمت پاسخ نامعتبر است',
        method_not_allowed: 'متد غیرمجاز',
        method_not_allowed_details: 'متد درخواست غیرمجاز است.',
        page_not_found: 'صفحه مورد نظر یافت نشد.',
        dashboard: 'داشبورد',
        login_page: 'صفحه ورود',
        
        // ترجمه نوع محصول
        product_types: {
            melted: 'آبشده',
            used_jewelry: 'طلای دست دوم / متفرقه',
            new_jewelry: 'طلای نو / ساخته شده',
            coin_bahar_azadi_new: 'سکه بهار جدید',
            coin_bahar_azadi_old: 'سکه بهار قدیم',
            coin_emami: 'سکه امامی',
            coin_half: 'نیم سکه',
            coin_quarter: 'ربع سکه',
            coin_gerami: 'سکه گرمی',
            other_coin: 'سایر سکه ها',
            bullion: 'شمش / طلای خام'
        },

        // ترجمه نوع مخاطب
        contact_types: {
            debtor: 'مشتری',
            creditor_account: 'تأمین کننده',
            counterparty: 'همکار صنفی',
            mixed: 'حساب واسط',
        },

        // ترجمه وضعیت تحویل
        delivery_statuses: {
            pending: 'در انتظار',
            delivered: 'تحویل شده',
            canceled: 'لغو شده',
        },

        // ترجمه وضعیت مجوز
        license_statuses: {
            active: 'فعال',
            expired: 'منقضی',
            pending: 'در انتظار',
        },
        
        // عناوین فیلدهای مراکز ری‌گیری
        assay_office_fields: {
            name: 'نام مرکز ری‌گیری',
            phone: 'تلفن',
            address: 'آدرس'
        }
    };
}

/**
 * نمایش پیام به صورت نوتیفیکیشن
 * @param {string} message متن پیام 
 * @param {string} type نوع پیام (success, error, warning, info) 
 */
window.showMessage = function(message, type = 'success') {
    if (typeof Toastify !== 'undefined') {
        Toastify({
            text: message,
            duration: 5000,
            close: true,
            gravity: "top",
            position: "center",
            className: "toast-" + type,
            stopOnFocus: true
        }).showToast();
    } else {
        alert(message);
    }
}