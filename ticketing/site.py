"""تنظیمات قابل ویرایش از پنل (جدول SiteSetting) + تعریف فیلدها"""
from django.core.cache import cache

GROUPS = {
    'general': {'title': 'عمومی و برند', 'fields': {
        'site_name': ('نام سایت', 'text', 'بلیط‌یار'),
        'site_tagline': ('شعار سایت', 'text', 'خرید آنلاین بلیط کنسرت، تئاتر و رویداد'),
        'logo': ('لوگو', 'image', None),
        'favicon': ('فاوآیکن', 'image', None),
        'hero_title': ('عنوان بالای صفحه اصلی', 'text', 'رویداد بعدی‌ات را پیدا کن'),
        'hero_subtitle': ('زیرعنوان صفحه اصلی', 'text', 'انتخاب صندلی، پرداخت امن و دریافت فوری بلیط'),
        'meta_description': ('توضیحات سئو (meta description)', 'textarea', None),
    }},
    'theme': {'title': 'ظاهر و رنگ‌ها', 'fields': {
        'color_primary': ('رنگ اصلی', 'color', '#e63946'),
        'color_secondary': ('رنگ ثانویه', 'color', '#1d1b3a'),
        'color_bg': ('رنگ پس‌زمینه', 'color', '#f6f7fb'),
        'color_header': ('رنگ هدر', 'color', '#ffffff'),
        'color_footer': ('رنگ فوتر', 'color', '#1d1b3a'),
        'radius': ('گردی گوشه‌ها (px)', 'number', '14'),
        'custom_css': ('CSS سفارشی', 'textarea', None),
        'head_html': ('کد دلخواه در <head> (آنالیتیکس و ...)', 'textarea', None),
    }},
    'footer': {'title': 'فوتر و تماس', 'fields': {
        'footer_about': ('متن درباره ما در فوتر', 'textarea', None),
        'support_phone': ('تلفن پشتیبانی', 'text', None),
        'support_email': ('ایمیل پشتیبانی', 'text', None),
        'address': ('آدرس', 'textarea', None),
        'instagram': ('لینک اینستاگرام', 'text', None),
        'telegram': ('لینک تلگرام', 'text', None),
        'whatsapp': ('لینک واتساپ', 'text', None),
        'aparat': ('لینک آپارات', 'text', None),
        'copyright': ('متن کپی‌رایت', 'text', 'کلیه حقوق این سایت محفوظ است.'),
    }},
    'sales': {'title': 'فروش', 'fields': {
        'currency_label': ('واحد پول (نمایش)', 'text', 'تومان'),
        'hold_minutes': ('مهلت پرداخت / نگه‌داشتن صندلی (دقیقه)', 'number', '10'),
        'max_per_order': ('حداکثر تعداد بلیط در هر سفارش', 'number', '10'),
        'service_fee': ('کارمزد خدمات به ازای هر بلیط', 'number', '0'),
        'terms': ('قوانین و شرایط خرید (نمایش هنگام پرداخت)', 'textarea', None),
        'ticket_sms': ('ارسال پیامک بلیط پس از خرید', 'checkbox', '0'),
    }},
    'sms': {'title': 'پیامک (کاوه‌نگار)', 'fields': {
        'kavenegar_api_key': ('API Key کاوه‌نگار', 'password', None),
        'kavenegar_otp_template': ('نام قالب کد تایید — با یک توکن %token', 'text', 'verify'),
        'kavenegar_ticket_template': ('نام قالب پیامک بلیط — token=کد سفارش، token2=نام رویداد', 'text', None),
    }},
    'gateways': {'title': 'درگاه‌های پرداخت', 'fields': {
        'default_gateway': ('درگاه پیش‌فرض (zarinpal | mellat | saman | fake)', 'text', 'zarinpal'),
        'zarinpal_enabled': ('زرین‌پال: فعال', 'checkbox', '0'),
        'zarinpal_merchant': ('زرین‌پال: Merchant ID', 'text', None),
        'zarinpal_sandbox': ('زرین‌پال: حالت آزمایشی (Sandbox)', 'checkbox', '0'),
        'mellat_enabled': ('بانک ملت (به‌پرداخت): فعال', 'checkbox', '0'),
        'mellat_terminal': ('ملت: شماره ترمینال', 'text', None),
        'mellat_username': ('ملت: نام کاربری', 'text', None),
        'mellat_password': ('ملت: کلمه عبور', 'password', None),
        'saman_enabled': ('سامان کیش (SEP): فعال', 'checkbox', '0'),
        'saman_terminal': ('سامان: شماره ترمینال (TerminalId)', 'text', None),
        'fake_enabled': ('درگاه آزمایشی داخلی (فقط برای تست؛ در سایت واقعی خاموش باشد)', 'checkbox', '0'),
    }},
}

DEFAULTS = {k: f[2] for g in GROUPS.values() for k, f in g['fields'].items()}
CACHE_KEY = 'site.settings'


def _all():
    data = cache.get(CACHE_KEY)
    if data is None:
        from .models import SiteSetting
        try:
            data = dict(SiteSetting.objects.values_list('key', 'value'))
        except Exception:  # قبل از migrate
            return {}
        cache.set(CACHE_KEY, data, 300)
    return data


def get(key, default=None):
    v = _all().get(key)
    if v is None or v == '':
        v = DEFAULTS.get(key) if default is None else default
    return v


def put(key, value):
    from .models import SiteSetting
    SiteSetting.objects.update_or_create(key=key, defaults={'value': value})
    cache.delete(CACHE_KEY)


class SiteProxy:
    """دسترسی در قالب‌ها: {{ site.site_name }}"""
    def __getattr__(self, key):
        if key.startswith('_'):
            raise AttributeError(key)
        return get(key)
