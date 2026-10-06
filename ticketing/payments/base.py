from .. import site


class GatewayError(Exception):
    pass


class Gateway:
    key = ''
    title = ''

    def enabled(self) -> bool:
        return site.get(f'{self.key}_enabled') == '1'

    def start(self, payment, callback_url) -> dict:
        """→ {'type': 'redirect', 'url'} یا {'type': 'post', 'url', 'fields'}"""
        raise NotImplementedError

    def verify(self, payment, request) -> dict:
        """→ {'ok': bool, 'ref', 'card', 'message'}"""
        raise NotImplementedError

    @staticmethod
    def rial(payment) -> int:
        return payment.amount * 10
