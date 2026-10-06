import logging
import secrets
from datetime import timedelta

from django.conf import settings
from django.contrib.auth.hashers import check_password, make_password
from django.utils import timezone

from . import site, sms
from .jalali import en_digits
from .models import OtpCode

log = logging.getLogger(__name__)
TTL = 120          # ثانیه اعتبار کد
RESEND = 60        # ثانیه فاصله ارسال مجدد
MAX_ATTEMPTS = 5


def send(mobile):
    """→ dict(ok, message?, wait?, dev_code?)"""
    now = timezone.now()
    last = OtpCode.objects.filter(mobile=mobile).order_by('-id').first()
    if last and (now - last.created_at).total_seconds() < RESEND:
        return {'ok': False, 'wait': RESEND - int((now - last.created_at).total_seconds()), 'message': 'لطفاً کمی صبر کنید و دوباره تلاش کنید.'}
    if OtpCode.objects.filter(mobile=mobile, created_at__gt=now - timedelta(hours=1)).count() >= 8:
        return {'ok': False, 'message': 'تعداد درخواست‌ها زیاد است. بعداً تلاش کنید.'}

    code = str(secrets.randbelow(90000) + 10000)
    OtpCode.objects.filter(mobile=mobile).delete()
    OtpCode.objects.create(mobile=mobile, code_hash=make_password(code), expires_at=now + timedelta(seconds=TTL))

    if sms.configured():
        if not sms.lookup(mobile, site.get('kavenegar_otp_template', 'verify'), code):
            return {'ok': False, 'message': 'ارسال پیامک با خطا مواجه شد. دوباره تلاش کنید.'}
        return {'ok': True}
    log.info('[OTP-DEV] %s => %s', mobile, code)   # کلید پیامک تنظیم نشده (توسعه)
    return {'ok': True, 'dev_code': code if settings.DEBUG else None}


def check(mobile, code) -> bool:
    otp = OtpCode.objects.filter(mobile=mobile).order_by('-id').first()
    if not otp or otp.expires_at < timezone.now() or otp.attempts >= MAX_ATTEMPTS:
        return False
    otp.attempts += 1
    otp.save(update_fields=['attempts'])
    if check_password(en_digits(str(code).strip()), otp.code_hash):
        otp.delete()
        return True
    return False
