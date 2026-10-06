from urllib.parse import quote

import requests

from .. import site
from ..models import Payment
from .base import Gateway, GatewayError


class Saman(Gateway):
    """سامان کیش — SEP / OnlinePG"""
    key, title = 'saman', 'سامان کیش'

    def start(self, payment, callback_url):
        r = requests.post('https://sep.shaparak.ir/onlinepg/onlinepg', timeout=20, json={
            'action': 'token', 'TerminalId': site.get('saman_terminal'), 'Amount': self.rial(payment),
            'ResNum': str(payment.id), 'RedirectUrl': callback_url, 'CellNumber': payment.order.user.mobile})
        j = r.json() if r.content else {}
        if j.get('status') != 1 or not j.get('token'):
            raise GatewayError(f'خطا از سامان کیش: {j.get("errorDesc", r.status_code)}')
        payment.authority = j['token']
        payment.save(update_fields=['authority'])
        return {'type': 'redirect', 'url': 'https://sep.shaparak.ir/OnlinePG/SendToken?token=' + quote(j['token'])}

    def verify(self, payment, request):
        p = request.POST or request.GET
        state = p.get('State') or p.get('Status')
        if p.get('State') != 'OK' and p.get('Status') != '2':
            return {'ok': False, 'message': f'پرداخت ناموفق بود ({state})'}
        ref = p.get('RefNum')
        if not ref or p.get('ResNum') != str(payment.id):
            return {'ok': False, 'message': 'اطلاعات بازگشتی نامعتبر است.'}
        if Payment.objects.filter(ref_id=ref).exclude(id=payment.id).exists():
            return {'ok': False, 'message': 'این تراکنش قبلاً استفاده شده است.'}
        r = requests.post('https://sep.shaparak.ir/verifyTxnRandomSessionkey/ipg/VerifyTransaction', timeout=30,
                          json={'RefNum': ref, 'TerminalNumber': site.get('saman_terminal')})
        j = r.json() if r.content else {}
        orig = (j.get('TransactionDetail') or {}).get('OrginalAmount', self.rial(payment))
        if j.get('Success') is True and j.get('ResultCode') == 0 and int(orig) == self.rial(payment):
            return {'ok': True, 'ref': str(ref), 'card': p.get('SecurePan')}
        return {'ok': False, 'message': 'تایید پرداخت ناموفق بود: ' + str(j.get('ResultDescription', 'خطای ناشناخته'))}
