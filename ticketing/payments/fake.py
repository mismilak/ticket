from django.urls import reverse

from .base import Gateway


class Fake(Gateway):
    """درگاه آزمایشی داخلی (فقط تست)"""
    key, title = 'fake', 'درگاه آزمایشی'

    def start(self, payment, callback_url):
        payment.authority = f'FAKE{payment.id}'
        payment.save(update_fields=['authority'])
        return {'type': 'redirect', 'url': reverse('payment_fake', args=[payment.id])}

    def verify(self, payment, request):
        if self.enabled() and request.GET.get('result') == 'ok':
            return {'ok': True, 'ref': f'TEST{payment.id}', 'card': '6037-****-****-1234'}
        return {'ok': False, 'message': 'پرداخت آزمایشی لغو شد.'}
