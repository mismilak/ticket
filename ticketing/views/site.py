"""صفحات عمومی، ورود، سبد خرید و پرداخت"""
import json

from django.conf import settings
from django.contrib import messages
from django.contrib.auth import authenticate, login, logout
from django.contrib.auth.decorators import login_required
from django.db.models import Count, Q
from django.http import Http404, JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.urls import reverse
from django.utils import timezone
from django.utils.http import url_has_allowed_host_and_scheme
from django.views.decorators.csrf import csrf_exempt
from django.views.decorators.http import require_POST

from .. import otp, payments, services, site
from ..jalali import fa_digits, normalize_mobile
from ..models import Category, Event, Order, Payment, Seat, Slider, Page, User, EventSession


def _events():
    return Event.objects.filter(status='published').select_related('category', 'hall__venue').annotate(sessions_count=Count('sessions'))


def home(request):
    upcoming = _events().filter(starts_at__gte=timezone.localtime().replace(hour=0, minute=0, second=0)).order_by('starts_at')
    return render(request, 'home.html', {
        'sliders': Slider.objects.filter(is_active=True).order_by('sort'),
        'featured': upcoming.filter(is_featured=True)[:8],
        'events': upcoming[:12],
        'categories': Category.objects.filter(is_active=True),
    })


def event_list(request):
    qs = _events()
    qs = qs.filter(starts_at__lt=timezone.now()).order_by('-starts_at') if request.GET.get('past') else qs.filter(starts_at__gte=timezone.now().replace(hour=0, minute=0)).order_by('starts_at')
    category = None
    if request.GET.get('category'):
        category = Category.objects.filter(slug=request.GET['category']).first()
        qs = qs.filter(category=category)
    q = request.GET.get('q', '').strip()
    if q:
        qs = qs.filter(Q(title__icontains=q) | Q(venue_name__icontains=q) | Q(subtitle__icontains=q))
    from django.core.paginator import Paginator
    page = Paginator(qs, 12).get_page(request.GET.get('page'))
    qd = request.GET.copy()
    qd.pop('page', None)
    return render(request, 'events.html', {'page': page, 'category': category, 'categories': Category.objects.filter(is_active=True), 'qs': qd.urlencode()})


def page_view(request, slug):
    return render(request, 'page.html', {'page': get_object_or_404(Page, slug=slug, is_active=True)})


# ───────── رویداد ─────────
def _visible_event(request, slug):
    event = get_object_or_404(Event.objects.select_related('category', 'hall__venue'), slug=slug)
    if event.status != 'published' and not (request.user.is_authenticated and request.user.is_admin):
        raise Http404
    return event


def _pick_session(request, event):
    sessions = list(event.sessions.all())
    if not sessions:
        raise Http404
    picked = next((s for s in sessions if str(s.id) == request.GET.get('session')), None)
    return picked or next((s for s in sessions if s.is_on_sale()), None) or next((s for s in sessions if s.starts_at > timezone.now()), None) or sessions[-1], sessions


def event_detail(request, slug):
    event = _visible_event(request, slug)
    session, sessions = _pick_session(request, event)
    sold = services.sold_counts(session)
    types = list(event.ticket_types.filter(is_active=True))
    general = []
    for t in types:
        if t.seat_category_id is None:
            t.remaining = None if t.capacity is None else max(0, t.capacity - sold.get(t.id, 0))
            general.append(t)
    return render(request, 'event.html', {
        'event': event, 'session': session, 'sessions': sessions, 'general': general,
        'seated': event.is_seated(), 'on_sale': event.is_on_sale() and session.is_on_sale(),
        'max': int(site.get('max_per_order') or 10),
    })


def seatmap(request, slug):
    event = _visible_event(request, slug)
    if not event.hall_id:
        raise Http404
    session, _ = _pick_session(request, event)
    layout = event.hall.layout()
    types = {t.seat_category_id: t for t in event.ticket_types.filter(is_active=True, seat_category__isnull=False)}
    for c in layout['categories']:
        t = types.get(c['id'])
        c['price'] = t.price if t else None
    prices = services.seat_price_map(event)
    for lv in layout['levels']:
        for s in lv['seats']:
            if s['id'] in prices:
                s['price'] = prices[s['id']]          # قیمت اختصاصی صندلی
    layout['status'] = {str(k): v for k, v in services.seat_statuses(session).items()}
    resp = JsonResponse(layout)
    resp['Cache-Control'] = 'no-store'
    return resp


@require_POST
def reserve(request, slug):
    event = _visible_event(request, slug)
    session = get_object_or_404(EventSession, id=request.POST.get('session') or 0, event=event)
    seats = [int(i) for i in request.POST.getlist('seats') if i.isdigit()]
    general = {}
    for k, v in request.POST.items():
        if k.startswith('general[') and v.isdigit() and int(v) > 0:
            general[int(k[8:-1])] = int(v)
    if not seats and not general:
        messages.error(request, 'لطفاً حداقل یک بلیط انتخاب کنید.')
        return redirect(event_detail_url(event, session))
    request.session['cart'] = {'event': event.id, 'session': session.id, 'seats': seats, 'general': {str(k): v for k, v in general.items()}}
    return redirect('checkout')


def event_detail_url(event, session):
    return f"{reverse('event_detail', args=[event.slug])}?session={session.id}"


# ───────── ورود ─────────
def login_view(request):
    if request.user.is_authenticated:
        return redirect('home')
    nxt = request.GET.get('next')
    if nxt and url_has_allowed_host_and_scheme(nxt, request.get_host()):
        request.session['next'] = nxt
    if request.method == 'POST':
        mobile = normalize_mobile(request.POST.get('mobile'))
        if not mobile:
            return render(request, 'auth/login.html', {'error': 'شماره موبایل معتبر نیست (مثال: 09123456789)', 'mobile': request.POST.get('mobile', '')})
        r = otp.send(mobile)
        if not r['ok'] and not r.get('wait'):
            return render(request, 'auth/login.html', {'error': r['message'], 'mobile': mobile})
        request.session['otp_mobile'], request.session['otp_dev'] = mobile, r.get('dev_code')
        (messages.success if r['ok'] else messages.error)(request, 'کد تایید ارسال شد.' if r['ok'] else r['message'])
        return redirect('login_verify')
    return render(request, 'auth/login.html')


def login_verify(request):
    mobile = request.session.get('otp_mobile')
    if not mobile:
        return redirect('login')
    error = None
    if request.method == 'POST':
        if otp.check(mobile, request.POST.get('code', '')):
            user, _ = User.objects.get_or_create(mobile=mobile)
            login(request, user, backend='django.contrib.auth.backends.ModelBackend')
            request.session.pop('otp_mobile', None)
            request.session.pop('otp_dev', None)
            if not user.name:
                return redirect('profile')
            return redirect(request.session.pop('next', None) or ('panel_dashboard' if user.is_admin else 'home'))
        error = 'کد وارد شده نادرست یا منقضی است.'
    return render(request, 'auth/verify.html', {'mobile': mobile, 'dev': request.session.get('otp_dev'), 'error': error})


def admin_login(request):
    error = None
    if request.method == 'POST':
        mobile = normalize_mobile(request.POST.get('mobile'))
        user = authenticate(request, mobile=mobile, password=request.POST.get('password', '')) if mobile else None
        if user and user.is_admin:
            login(request, user)
            return redirect(request.GET.get('next') or 'panel_dashboard')
        error = 'اطلاعات ورود نادرست است.'
    return render(request, 'auth/password.html', {'error': error})


@require_POST
def logout_view(request):
    logout(request)
    return redirect('home')


# ───────── حساب کاربری ─────────
@login_required
def profile(request):
    if request.method == 'POST':
        name = request.POST.get('name', '').strip()
        if not name:
            messages.error(request, 'نام را وارد کنید.')
        else:
            request.user.name, request.user.email = name[:80], request.POST.get('email', '')[:120]
            request.user.save()
            messages.success(request, 'اطلاعات ذخیره شد.')
            return redirect(request.session.pop('next', None) or ('checkout' if request.session.get('cart') else 'orders'))
    return render(request, 'account/profile.html')


@login_required
def my_orders(request):
    orders = request.user.orders.select_related('event', 'session')
    return render(request, 'account/orders.html', {'orders': orders})


@login_required
def order_detail(request, code):
    order = get_object_or_404(Order.objects.select_related('event__hall__venue', 'session', 'user'), code=code)
    if order.user_id != request.user.id and not request.user.is_admin:
        raise Http404
    return render(request, 'account/order.html', {
        'order': order, 'items': order.items.select_related('ticket_type', 'seat__level'),
        'payment': order.payments.filter(status='paid').first(), 'gateways': payments.active(),
    })


# ───────── خرید و پرداخت ─────────
@login_required
def checkout(request):
    cart = request.session.get('cart')
    if not cart:
        messages.error(request, 'سبد خرید خالی است.')
        return redirect('home')
    if not request.user.name:
        messages.success(request, 'برای ادامه خرید نام خود را وارد کنید.')
        return redirect('profile')
    event = get_object_or_404(Event, id=cart['event'])
    session = get_object_or_404(EventSession, id=cart['session'], event=event)
    by_cat = {t.seat_category_id: t for t in event.ticket_types.filter(seat_category__isnull=False)}
    overrides = services.seat_price_map(event)
    lines, total = [], 0
    for seat in Seat.objects.select_related('level', 'seat_category').filter(id__in=cart.get('seats', [])):
        t = by_cat.get(seat.seat_category_id)
        p = overrides.get(seat.id, t.price if t else 0)
        lines.append({'title': f'{t.name if t else (seat.seat_category.name if seat.seat_category_id else "صندلی")} — {seat.title()} ({seat.level.name})', 'price': p})
        total += p
    for tid, qty in (cart.get('general') or {}).items():
        t = event.ticket_types.filter(id=tid).first()
        if t:
            lines.append({'title': f'{t.name} × {fa_digits(qty)}', 'price': t.price * qty})
            total += t.price * qty
    count = len(cart.get('seats', [])) + sum((cart.get('general') or {}).values())
    fee = int(site.get('service_fee') or 0) * count
    gws = payments.active()
    default = site.get('default_gateway')
    return render(request, 'checkout.html', {
        'event': event, 'session': session, 'lines': lines, 'fee': fee, 'total': total + fee,
        'gateways': gws, 'selected': default if default in gws else next(iter(gws), None),
    })


@login_required
@require_POST
def checkout_submit(request):
    cart = request.session.get('cart')
    if not cart:
        raise Http404
    gws = payments.active()
    key = request.POST.get('gateway')
    if key not in gws:
        messages.error(request, 'درگاه پرداخت را انتخاب کنید.')
        return redirect('checkout')
    if site.get('terms') and not request.POST.get('accept'):
        messages.error(request, 'برای ادامه باید قوانین را بپذیرید.')
        return redirect('checkout')
    event = get_object_or_404(Event, id=cart['event'])
    session = get_object_or_404(EventSession, id=cart['session'], event=event)
    try:
        order = services.create_order(request.user, event, session, cart)
    except services.OrderError as e:
        messages.error(request, str(e))
        return redirect(event_detail_url(event, session))
    request.session.pop('cart', None)
    order.gateway = key
    order.save(update_fields=['gateway'])
    return _start_payment(request, order, key)


@login_required
@require_POST
def order_pay(request, code):
    order = get_object_or_404(Order, code=code, user=request.user)
    key = request.POST.get('gateway') or order.gateway
    if not order.is_pending() or key not in payments.active():
        raise Http404
    order.gateway = key
    order.save(update_fields=['gateway'])
    return _start_payment(request, order, key)


def _start_payment(request, order, key):
    payment = Payment.objects.create(order=order, gateway=key, amount=order.total)
    callback = request.build_absolute_uri(reverse('payment_callback', args=[payment.id]))
    try:
        go = payments.make(key).start(payment, callback)
    except Exception as e:  # noqa
        payment.status, payment.message = 'failed', str(e)
        payment.save(update_fields=['status', 'message'])
        messages.error(request, str(e))
        return redirect('order_detail', code=order.code)
    if go['type'] == 'redirect':
        return redirect(go['url'])
    return render(request, 'payment/redirect.html', go)


@csrf_exempt
def payment_callback(request, pk):
    payment = get_object_or_404(Payment.objects.select_related('order__user', 'order__event'), id=pk)
    order = payment.order
    if payment.status == 'paid' or order.status == 'paid':
        return redirect('order_detail', code=order.code)
    try:
        r = payments.make(payment.gateway).verify(payment, request)
    except Exception as e:  # noqa
        r = {'ok': False, 'message': f'خطا در تایید پرداخت: {e}'}
    raw = {k: v for k, v in (request.POST or request.GET).items()}
    if r['ok']:
        payment.status, payment.ref_id, payment.card_pan, payment.raw = 'paid', r.get('ref'), r.get('card'), raw
        payment.save()
        if order.status in ('expired', 'canceled'):
            order.status = 'failed'
            order.save(update_fields=['status'])
            messages.error(request, f'پرداخت انجام شد اما مهلت سفارش تمام شده بود. برای بازگشت وجه با پشتیبانی تماس بگیرید. کد پیگیری: {r.get("ref", "")}')
            return redirect('order_detail', code=order.code)
        services.mark_paid(order)
        messages.success(request, 'پرداخت با موفقیت انجام شد.')
        return redirect('order_detail', code=order.code)
    payment.status, payment.message, payment.raw = 'failed', r.get('message'), raw
    payment.save()
    messages.error(request, r.get('message') or 'پرداخت ناموفق بود.')
    return redirect('order_detail', code=order.code)


def payment_fake(request, pk):
    payment = get_object_or_404(Payment, id=pk, gateway='fake', status='pending')
    return render(request, 'payment/fake.html', {'payment': payment})
