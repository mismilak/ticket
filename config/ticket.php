<?php

/*
 * تنظیمات قابل ویرایش از پنل ادمین.
 * هر گروه یک تب در صفحه تنظیمات می‌شود. مقدارها در جدول settings ذخیره می‌شوند.
 * types: text | textarea | number | color | image | checkbox | password | select
 */
return [
    'groups' => [
        'general' => [
            'title' => 'عمومی و برند',
            'fields' => [
                'site_name'     => ['label' => 'نام سایت', 'type' => 'text', 'default' => 'بلیط‌یار'],
                'site_tagline'  => ['label' => 'شعار سایت', 'type' => 'text', 'default' => 'خرید آنلاین بلیط کنسرت، تئاتر و رویداد'],
                'logo'          => ['label' => 'لوگو', 'type' => 'image'],
                'favicon'       => ['label' => 'فاوآیکن', 'type' => 'image'],
                'hero_title'    => ['label' => 'عنوان بالای صفحه اصلی', 'type' => 'text', 'default' => 'رویداد بعدی‌ات را پیدا کن'],
                'hero_subtitle' => ['label' => 'زیرعنوان صفحه اصلی', 'type' => 'text', 'default' => 'انتخاب صندلی، پرداخت امن و دریافت فوری بلیط'],
                'meta_description' => ['label' => 'توضیحات سئو (meta description)', 'type' => 'textarea'],
            ],
        ],
        'theme' => [
            'title' => 'ظاهر و رنگ‌ها',
            'fields' => [
                'color_primary'   => ['label' => 'رنگ اصلی', 'type' => 'color', 'default' => '#e63946'],
                'color_secondary' => ['label' => 'رنگ ثانویه', 'type' => 'color', 'default' => '#1d1b3a'],
                'color_bg'        => ['label' => 'رنگ پس‌زمینه', 'type' => 'color', 'default' => '#f6f7fb'],
                'color_header'    => ['label' => 'رنگ هدر', 'type' => 'color', 'default' => '#ffffff'],
                'color_footer'    => ['label' => 'رنگ فوتر', 'type' => 'color', 'default' => '#1d1b3a'],
                'radius'          => ['label' => 'گردی گوشه‌ها (px)', 'type' => 'number', 'default' => 14],
                'custom_css'      => ['label' => 'CSS سفارشی', 'type' => 'textarea'],
                'head_html'       => ['label' => 'کد دلخواه در <head> (آنالیتیکس و ...)', 'type' => 'textarea'],
            ],
        ],
        'footer' => [
            'title' => 'فوتر و تماس',
            'fields' => [
                'footer_about'  => ['label' => 'متن درباره ما در فوتر', 'type' => 'textarea'],
                'support_phone' => ['label' => 'تلفن پشتیبانی', 'type' => 'text'],
                'support_email' => ['label' => 'ایمیل پشتیبانی', 'type' => 'text'],
                'address'       => ['label' => 'آدرس', 'type' => 'textarea'],
                'instagram'     => ['label' => 'لینک اینستاگرام', 'type' => 'text'],
                'telegram'      => ['label' => 'لینک تلگرام', 'type' => 'text'],
                'whatsapp'      => ['label' => 'لینک واتساپ', 'type' => 'text'],
                'aparat'        => ['label' => 'لینک آپارات', 'type' => 'text'],
                'copyright'     => ['label' => 'متن کپی‌رایت', 'type' => 'text', 'default' => 'کلیه حقوق این سایت محفوظ است.'],
            ],
        ],
        'sales' => [
            'title' => 'فروش',
            'fields' => [
                'currency_label'  => ['label' => 'واحد پول (نمایش)', 'type' => 'text', 'default' => 'تومان'],
                'hold_minutes'    => ['label' => 'مهلت پرداخت / نگه‌داشتن صندلی (دقیقه)', 'type' => 'number', 'default' => 10],
                'max_per_order'   => ['label' => 'حداکثر تعداد بلیط در هر سفارش', 'type' => 'number', 'default' => 10],
                'service_fee'     => ['label' => 'کارمزد خدمات به ازای هر بلیط', 'type' => 'number', 'default' => 0],
                'terms'           => ['label' => 'قوانین و شرایط خرید (نمایش هنگام پرداخت)', 'type' => 'textarea'],
                'ticket_sms'      => ['label' => 'ارسال پیامک بلیط پس از خرید', 'type' => 'checkbox', 'default' => 0],
            ],
        ],
        'sms' => [
            'title' => 'پیامک (کاوه‌نگار)',
            'fields' => [
                'kavenegar_api_key'         => ['label' => 'API Key کاوه‌نگار', 'type' => 'password'],
                'kavenegar_otp_template'    => ['label' => 'نام قالب (Template) کد تایید — با یک توکن {token}', 'type' => 'text', 'default' => 'verify'],
                'kavenegar_ticket_template' => ['label' => 'نام قالب پیامک بلیط — token=کد سفارش، token2=نام رویداد', 'type' => 'text'],
            ],
        ],
        'gateways' => [
            'title' => 'درگاه‌های پرداخت',
            'fields' => [
                'default_gateway'    => ['label' => 'درگاه پیش‌فرض (کلید: zarinpal | mellat | saman | fake)', 'type' => 'text', 'default' => 'zarinpal'],

                'zarinpal_enabled'   => ['label' => 'زرین‌پال: فعال', 'type' => 'checkbox', 'default' => 0],
                'zarinpal_merchant'  => ['label' => 'زرین‌پال: Merchant ID', 'type' => 'text'],
                'zarinpal_sandbox'   => ['label' => 'زرین‌پال: حالت آزمایشی (Sandbox)', 'type' => 'checkbox', 'default' => 0],

                'mellat_enabled'     => ['label' => 'بانک ملت (به‌پرداخت): فعال', 'type' => 'checkbox', 'default' => 0],
                'mellat_terminal'    => ['label' => 'ملت: شماره ترمینال', 'type' => 'text'],
                'mellat_username'    => ['label' => 'ملت: نام کاربری', 'type' => 'text'],
                'mellat_password'    => ['label' => 'ملت: کلمه عبور', 'type' => 'password'],

                'saman_enabled'      => ['label' => 'سامان کیش (SEP): فعال', 'type' => 'checkbox', 'default' => 0],
                'saman_terminal'     => ['label' => 'سامان: شماره ترمینال (TerminalId)', 'type' => 'text'],

                'fake_enabled'       => ['label' => 'درگاه آزمایشی داخلی (فقط برای تست، در سایت واقعی خاموش باشد)', 'type' => 'checkbox', 'default' => 0],
            ],
        ],
    ],

    'gateways' => [
        'zarinpal' => ['title' => 'زرین‌پال', 'class' => \App\Payment\Zarinpal::class],
        'mellat'   => ['title' => 'بانک ملت', 'class' => \App\Payment\Mellat::class],
        'saman'    => ['title' => 'سامان کیش', 'class' => \App\Payment\Saman::class],
        'fake'     => ['title' => 'درگاه آزمایشی', 'class' => \App\Payment\Fake::class],
    ],
];
