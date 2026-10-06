"""تبدیل تاریخ شمسی/میلادی و ابزارهای ارقام فارسی"""
import re
from datetime import datetime

from django.utils import timezone

FA = '۰۱۲۳۴۵۶۷۸۹'
MONTHS = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند']
DAYS = ['دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه', 'یکشنبه']  # weekday(): دوشنبه=0


def fa_digits(v) -> str:
    return ''.join(FA[int(c)] if c.isdigit() and c.isascii() else c for c in str(v))


def en_digits(v) -> str:
    out = []
    for c in str(v):
        if c in FA:
            out.append(str(FA.index(c)))
        elif '٠' <= c <= '٩':
            out.append(str(ord(c) - ord('٠')))
        else:
            out.append(c)
    return ''.join(out)


def g2j(gy, gm, gd):
    g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
    gy2 = gy + 1 if gm > 2 else gy
    days = 355666 + 365 * gy + (gy2 + 3) // 4 - (gy2 + 99) // 100 + (gy2 + 399) // 400 + gd + g_d_m[gm - 1]
    jy = -1595 + 33 * (days // 12053)
    days %= 12053
    jy += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        jy += (days - 1) // 365
        days = (days - 1) % 365
    if days < 186:
        jm, jd = 1 + days // 31, 1 + days % 31
    else:
        jm, jd = 7 + (days - 186) // 30, 1 + (days - 186) % 30
    return jy, jm, jd


def j2g(jy, jm, jd):
    jy += 1595
    days = -355668 + 365 * jy + (jy // 33) * 8 + ((jy % 33) + 3) // 4 + jd + ((jm - 1) * 31 if jm < 7 else (jm - 7) * 30 + 186)
    gy = 400 * (days // 146097)
    days %= 146097
    if days > 36524:
        days -= 1
        gy += 100 * (days // 36524)
        days %= 36524
        if days >= 365:
            days += 1
    gy += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        gy += (days - 1) // 365
        days = (days - 1) % 365
    gd = days + 1
    leap = (gy % 4 == 0 and gy % 100 != 0) or gy % 400 == 0
    sal = [0, 31, 29 if leap else 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
    gm = 0
    while gm < 13 and gd > sal[gm]:
        gd -= sal[gm]
        gm += 1
    return gy, gm, gd


def jdate(dt, fmt='full') -> str:
    """fmt: full | date | time | input | short"""
    if not dt:
        return ''
    if timezone.is_aware(dt):
        dt = timezone.localtime(dt)
    y, m, d = g2j(dt.year, dt.month, dt.day)
    t = dt.strftime('%H:%M')
    if fmt == 'input':
        return f'{y:04d}/{m:02d}/{d:02d} {t}'
    if fmt == 'date':
        return fa_digits(f'{y:04d}/{m:02d}/{d:02d}')
    if fmt == 'time':
        return fa_digits(t)
    if fmt == 'short':
        return f'{fa_digits(d)} {MONTHS[m]}'
    if fmt == 'year':
        return fa_digits(y)
    return f'{DAYS[dt.weekday()]} {fa_digits(d)} {MONTHS[m]} {fa_digits(y)} - {fa_digits(t)}'


def parse_jdate(value):
    """'1405/07/20 21:30' → datetime آگاه به منطقه زمانی (یا None)"""
    m = re.match(r'^(\d{4})[/-](\d{1,2})[/-](\d{1,2})(?:\s+(\d{1,2}):(\d{2}))?$', en_digits((value or '')).strip())
    if not m or int(m[1]) < 1300:
        return None
    gy, gm, gd = j2g(int(m[1]), int(m[2]), int(m[3]))
    try:
        naive = datetime(gy, gm, gd, int(m[4] or 0), int(m[5] or 0))
    except ValueError:
        return None
    return timezone.make_aware(naive)


def normalize_mobile(v):
    m = re.sub(r'\D', '', en_digits(v or ''))
    if m.startswith('0098'):
        m = '0' + m[4:]
    elif m.startswith('98') and len(m) == 12:
        m = '0' + m[2:]
    elif len(m) == 10 and m[0] == '9':
        m = '0' + m
    return m if re.match(r'^09\d{9}$', m) else None


def slugify(s) -> str:
    s = re.sub(r'[^\w]+', '-', en_digits(s or '').lower(), flags=re.UNICODE).replace('_', '-')
    return s.strip('-')
