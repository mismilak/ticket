import logging

import requests

from . import site

log = logging.getLogger(__name__)


def configured() -> bool:
    return bool(site.get('kavenegar_api_key'))


def lookup(receptor, template, token, token2=None, token3=None) -> bool:
    """ارسال پیامک الگویی کاوه‌نگار (verify/lookup)"""
    key = site.get('kavenegar_api_key')
    if not key or not template:
        log.info('[SMS-DEV] to=%s template=%s token=%s', receptor, template, token)
        return False
    params = {'receptor': receptor, 'template': template, 'token': token}
    if token2:
        params['token2'] = str(token2).replace(' ', '_')   # کاوه‌نگار فاصله در توکن را نمی‌پذیرد
    if token3:
        params['token3'] = str(token3).replace(' ', '_')
    try:
        r = requests.get(f'https://api.kavenegar.com/v1/{key}/verify/lookup.json', params=params, timeout=15)
        ok = r.ok and (r.json().get('return') or {}).get('status') == 200
        if not ok:
            log.warning('Kavenegar failed: %s', r.text)
        return ok
    except Exception as e:  # noqa
        log.error('Kavenegar error: %s', e)
        return False
