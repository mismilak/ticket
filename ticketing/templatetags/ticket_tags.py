from django import template
from django.conf import settings
from django.utils.safestring import mark_safe

from .. import site as site_store
from ..jalali import fa_digits, jdate as _jdate

register = template.Library()


@register.filter
def jdate(value, fmt='full'):
    return _jdate(value, fmt)


@register.filter
def fa(value):
    return fa_digits(value)


@register.filter
def price(value):
    try:
        n = int(value or 0)
    except (TypeError, ValueError):
        n = 0
    return f"{fa_digits(format(n, ','))} {site_store.get('currency_label')}"


@register.filter
def media(path):
    return f'{settings.MEDIA_URL}{path}' if path else ''


@register.filter
def getitem(d, key):
    try:
        return d[key]
    except (KeyError, TypeError, IndexError):
        return ''


@register.simple_tag
def raw(value):
    """چاپ HTML خام (کد نماد، CSS سفارشی)"""
    return mark_safe(value or '')


@register.filter
def attr(obj, name):
    return getattr(obj, name, '')
