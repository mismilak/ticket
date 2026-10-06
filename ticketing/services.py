"""منطق سفارش: رزرو صندلی، ظرفیت، قیمت اختصاصی صندلی، پرداخت و آزادسازی"""
import secrets
import string
from datetime import timedelta

from django.db import IntegrityError, transaction
from django.db.models import Count
from django.utils import timezone

from . import site, sms
from .models import Event, EventSession, Order, OrderItem, Seat, SeatPrice, TicketType

ALPHABET = string.ascii_uppercase + string.digits


class OrderError(Exception):
    """پیام فارسی قابل نمایش به کاربر"""


def rand_code(n):
    return ''.join(secrets.choice(ALPHABET) for _ in range(n))


def release_expired():
    ids = list(Order.objects.filter(status='pending', expires_at__lt=timezone.now()).values_list('id', flat=True))
    if ids:
        with transaction.atomic():
            Order.objects.filter(id__in=ids).update(status='expired')
            OrderItem.objects.filter(order_id__in=ids).update(active=False, lock_key=None)


def seat_statuses(session) -> dict:
    """{seat_id: 'sold'|'held'}"""
    release_expired()
    rows = OrderItem.objects.filter(session=session, active=True, seat__isnull=False).values_list('seat_id', 'order__status')
    return {sid: ('sold' if st == 'paid' else 'held') for sid, st in rows}


def sold_counts(session) -> dict:
    release_expired()
    return {r['ticket_type_id']: r['c'] for r in
            OrderItem.objects.filter(session=session, active=True).values('ticket_type_id').annotate(c=Count('id'))}


def seat_price_map(event) -> dict:
    return dict(SeatPrice.objects.filter(event=event).values_list('seat_id', 'price'))


def create_order(user, event: Event, session: EventSession, cart: dict) -> Order:
    release_expired()
    if session.event_id != event.id or not event.is_on_sale() or not session.is_on_sale():
        raise OrderError('فروش بلیط این رویداد فعال نیست.')

    seat_ids = list(dict.fromkeys(int(i) for i in cart.get('seats', [])))
    general = {int(k): int(v) for k, v in (cart.get('general') or {}).items() if int(v) > 0}
    count = len(seat_ids) + sum(general.values())
    mx = int(site.get('max_per_order') or 10)
    if count < 1:
        raise OrderError('هیچ بلیطی انتخاب نشده است.')
    if count > mx:
        raise OrderError(f'حداکثر {mx} بلیط در هر سفارش مجاز است.')

    types = list(event.ticket_types.filter(is_active=True))
    by_cat = {t.seat_category_id: t for t in types if t.seat_category_id}
    overrides = seat_price_map(event)

    try:
        with transaction.atomic():
            order = Order.objects.create(code=rand_code(8), user=user, event=event, session=session, status='pending',
                                         expires_at=timezone.now() + timedelta(minutes=int(site.get('hold_minutes') or 10)))
            total = 0
            if seat_ids:
                seats = list(Seat.objects.select_related('level', 'seat_category').filter(id__in=seat_ids))
                if len(seats) != len(seat_ids) or any(s.level.hall_id != event.hall_id for s in seats):
                    raise OrderError('صندلی انتخاب‌شده معتبر نیست.')
                for seat in seats:
                    t = by_cat.get(seat.seat_category_id)
                    if not t and seat.id in overrides and seat.seat_category_id:
                        # صندلی با قیمت اختصاصی در دسته‌ای که قیمت پایه ندارد: نوع بلیط غیرفعال فقط برای ثبت سفارش
                        t, _ = TicketType.objects.get_or_create(event=event, seat_category_id=seat.seat_category_id, is_active=False,
                                                                defaults={'name': seat.seat_category.name, 'price': 0})
                    if not t:
                        raise OrderError(f'صندلی {seat.label} برای فروش در دسترس نیست.')
                    price = overrides.get(seat.id, t.price)       # قیمت اختصاصی صندلی یا قیمت دسته
                    OrderItem.objects.create(order=order, event=event, session=session, ticket_type=t, seat=seat,
                                             price=price, lock_key=f'{session.id}:{seat.id}')
                    total += price
            for type_id, qty in general.items():
                t = next((x for x in types if x.id == type_id and x.seat_category_id is None), None)
                if not t:
                    raise OrderError('نوع بلیط معتبر نیست.')
                if t.capacity is not None:
                    taken = OrderItem.objects.filter(ticket_type=t, session=session, active=True).count()
                    if taken + qty > t.capacity:
                        raise OrderError(f'ظرفیت «{t.name}» کافی نیست.')
                for _ in range(qty):
                    OrderItem.objects.create(order=order, event=event, session=session, ticket_type=t, price=t.price)
                total += t.price * qty
            total += int(site.get('service_fee') or 0) * count
            order.total = total
            order.save(update_fields=['total'])
            return order
    except IntegrityError:
        raise OrderError('متاسفانه یکی از صندلی‌های انتخابی هم‌اکنون توسط شخص دیگری رزرو شد.')


def mark_paid(order: Order):
    if order.status == 'paid':
        return
    with transaction.atomic():
        for item in order.items.all():
            if not item.ticket_code:
                while True:
                    code = rand_code(10)
                    if not OrderItem.objects.filter(ticket_code=code).exists():
                        break
                item.ticket_code, item.active = code, True
                item.save(update_fields=['ticket_code', 'active'])
        order.status, order.paid_at = 'paid', timezone.now()
        order.save(update_fields=['status', 'paid_at'])
    if site.get('ticket_sms') == '1' and site.get('kavenegar_ticket_template'):
        sms.lookup(order.user.mobile, site.get('kavenegar_ticket_template'), order.code, order.event.title)


def release(order: Order, status: str):
    if order.status == 'paid':
        return
    with transaction.atomic():
        order.status = status
        order.save(update_fields=['status'])
        order.items.update(active=False, lock_key=None)
