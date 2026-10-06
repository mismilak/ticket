import re
from datetime import datetime
from xml.sax.saxutils import escape

import requests

from .. import site
from .base import Gateway, GatewayError

ENDPOINT = 'https://bpm.shaparak.ir/pgwchannel/services/pgw'
NS = 'http://interfaces.core.sw.bps.com/'


class Mellat(Gateway):
    """بانک ملت — به‌پرداخت (SOAP با HTTP خام)"""
    key, title = 'mellat', 'بانک ملت'

    def call(self, method, params):
        body = ''.join(f'<{k}>{escape(str(v))}</{k}>' for k, v in params.items())
        xml = ('<?xml version="1.0" encoding="utf-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
               f'xmlns:int="{NS}"><soapenv:Body><int:{method}>{body}</int:{method}></soapenv:Body></soapenv:Envelope>')
        r = requests.post(ENDPOINT, data=xml.encode('utf-8'), headers={'Content-Type': 'text/xml; charset=utf-8', 'SOAPAction': ''}, timeout=30)
        m = re.search(r'<return[^>]*>(.*?)</return>', r.text, re.S)
        if not m:
            raise GatewayError('پاسخ نامعتبر از درگاه بانک ملت.')
        return m.group(1).strip()

    def auth(self):
        return {'terminalId': site.get('mellat_terminal'), 'userName': site.get('mellat_username'), 'userPassword': site.get('mellat_password')}

    def start(self, payment, callback_url):
        now = datetime.now()
        out = self.call('bpPayRequest', {**self.auth(), 'orderId': payment.id, 'amount': self.rial(payment),
                                         'localDate': now.strftime('%Y%m%d'), 'localTime': now.strftime('%H%M%S'),
                                         'additionalData': payment.order.code, 'callBackUrl': callback_url, 'payerId': 0})
        parts = out.split(',')
        if parts[0] != '0' or len(parts) < 2 or not parts[1]:
            raise GatewayError(f'خطا از بانک ملت (کد {parts[0]})')
        payment.authority = parts[1]
        payment.save(update_fields=['authority'])
        return {'type': 'post', 'url': 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat', 'fields': {'RefId': parts[1]}}

    def verify(self, payment, request):
        p = request.POST or request.GET
        if p.get('ResCode') != '0':
            return {'ok': False, 'message': f'پرداخت ناموفق بود (کد {p.get("ResCode")})'}
        ref = p.get('SaleReferenceId')
        common = {**self.auth(), 'orderId': payment.id, 'saleOrderId': payment.id, 'saleReferenceId': ref}
        v = self.call('bpVerifyRequest', common)
        if v != '0':
            self.call('bpInquiryRequest', common)
            return {'ok': False, 'message': f'تایید پرداخت ناموفق بود (کد {v})'}
        self.call('bpSettleRequest', common)   # تسویه؛ کد 0 یا 45 موفق است
        return {'ok': True, 'ref': str(ref), 'card': p.get('CardHolderPan')}
