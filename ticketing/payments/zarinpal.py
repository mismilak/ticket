import requests

from .. import site
from .base import Gateway, GatewayError


class Zarinpal(Gateway):
    """زرین‌پال — API نسخه 4"""
    key, title = 'zarinpal', 'زرین‌پال'

    def host(self):
        return 'sandbox.zarinpal.com' if site.get('zarinpal_sandbox') == '1' else 'payment.zarinpal.com'

    def start(self, payment, callback_url):
        r = requests.post(f'https://{self.host()}/pg/v4/payment/request.json', timeout=20, json={
            'merchant_id': site.get('zarinpal_merchant'), 'amount': payment.amount, 'currency': 'IRT',
            'callback_url': callback_url, 'description': f'سفارش {payment.order.code}',
            'metadata': {'mobile': payment.order.user.mobile},
        })
        j = r.json() if r.content else {}
        data = j.get('data') or {}
        if data.get('code') != 100 or not data.get('authority'):
            code = (j.get('errors') or {}).get('code', r.status_code) if isinstance(j.get('errors'), dict) else r.status_code
            raise GatewayError(f'خطا در اتصال به زرین‌پال (کد {code})')
        payment.authority = data['authority']
        payment.save(update_fields=['authority'])
        return {'type': 'redirect', 'url': f'https://{self.host()}/pg/StartPay/{data["authority"]}'}

    def verify(self, payment, request):
        if request.GET.get('Status') != 'OK' or request.GET.get('Authority') != payment.authority:
            return {'ok': False, 'message': 'پرداخت توسط کاربر لغو شد یا ناموفق بود.'}
        r = requests.post(f'https://{self.host()}/pg/v4/payment/verify.json', timeout=20, json={
            'merchant_id': site.get('zarinpal_merchant'), 'amount': payment.amount, 'authority': payment.authority})
        data = (r.json() or {}).get('data') or {}
        if data.get('code') in (100, 101):
            return {'ok': True, 'ref': str(data.get('ref_id')), 'card': data.get('card_pan')}
        return {'ok': False, 'message': f'تایید پرداخت ناموفق بود (کد {data.get("code")})'}
