<?php
return [
    // پیام‌های سیستمی و لاگ
    'log_user_login'      => 'ورود کاربر با موفقیت انجام شد.',
    'log_user_logout'     => 'خروج کاربر ثبت شد.',
    'log_data_updated'    => 'اطلاعات ویرایش شد.',
    'log_data_deleted'    => 'حذف اطلاعات انجام شد.',
    'log_error'           => 'خطای سیستمی رخ داد: :error',

    // پیام‌های خطا و دسترسی
    'error_db'            => 'خطا در ارتباط با پایگاه داده.',
    'error_access_denied' => 'شما مجاز به انجام این عملیات نیستید.',
    'error_not_found'     => 'مورد مورد نظر یافت نشد.',
    'error_csrf'          => 'توکن امنیتی نامعتبر است.',

    // پیام‌های موفقیت (در صورت نیاز به نمایش سمت سرور)
    'success_save'        => 'اطلاعات با موفقیت ذخیره شد.',
    'success_delete'      => 'حذف با موفقیت انجام شد.',

    // ترجمه وضعیت‌ها و نوع‌ها (در صورت نیاز به نمایش در خروجی PHP)
    'product_types' => [
        'melted'                 => 'آبشده',
        'used_jewelry'           => 'طلای دست دوم / متفرقه',
        'new_jewelry'            => 'طلای نو / ساخته شده',
        'coin_bahar_azadi_new'   => 'سکه بهار جدید',
        'coin_bahar_azadi_old'   => 'سکه بهار قدیم',
        'coin_emami'             => 'سکه امامی',
        'coin_half'              => 'نیم سکه',
        'coin_quarter'           => 'ربع سکه',
        'coin_gerami'            => 'سکه گرمی',
        'other_coin'             => 'سایر سکه ها',
        'bullion'                => 'شمش / طلای خام'
    ],
    'contact_types' => [
        'debtor'           => 'مشتری',
        'creditor_account' => 'تأمین کننده',
        'counterparty'     => 'همکار صنفی',
        'mixed'            => 'حساب واسط',
    ],
    'delivery_statuses' => [
        'pending'   => 'در انتظار',
        'delivered' => 'تحویل شده',
        'canceled'  => 'لغو شده',
    ],
    'license_statuses' => [
        'active'   => 'فعال',
        'expired'  => 'منقضی',
        'pending'  => 'در انتظار',
    ],
];