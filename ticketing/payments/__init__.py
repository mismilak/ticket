from .fake import Fake
from .mellat import Mellat
from .saman import Saman
from .zarinpal import Zarinpal

GATEWAYS = {g.key: g for g in (Zarinpal, Mellat, Saman, Fake)}


def make(key):
    cls = GATEWAYS.get(key)
    return cls() if cls else None


def active() -> dict:
    return {k: c.title for k, c in GATEWAYS.items() if c().enabled()}
