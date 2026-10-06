"""پنل مدیریت (/panel)"""
import json
import re
from functools import wraps

from django import forms
from django.contrib import messages
from django.core.paginator import Paginator
from django.db import transaction
from django.db.models import Count, Q, Sum
from django.forms import modelform_factory
from django.http import Http404, HttpResponseForbidden, JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.utils import timezone
from django.views.decorators.http import require_POST

from .. import services, site
from ..jalali import en_digits, fa_digits, jdate, parse_jdate, slugify
from ..models import (Category, Event, EventSession, Hall, HallLevel, License, Order, OrderItem, Page, Seat, SeatCategory,
                      SeatPrice, SiteSetting, Slider, TicketType, User, Venue)


def admin_required(view):
    @wraps(view)
    def wrapper(request, *a, **kw):
        if not request.user.is_authenticated:
            from django.contrib.auth.views import redirect_to_login
            return redirect_to_login(request.get_full_path(), '/panel/login/')
        if not request.user.is_admin:
            return HttpResponseForbidden('دسترسی مجاز نیست.')
        return view(request, *a, **kw)
    return wrapper


def store_upload(file):
    from django.core.files.storage import default_storage
    import secrets
    ext = file.name.rsplit('.', 1)[-1].lower() if '.' in file.name else 'jpg'
    if ext not in ('jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'):
        raise ValueError('فرمت تصویر مجاز نیست.')
    return default_storage.save(f'uploads/{secrets.token_hex(10)}.{ext}', file)


def nested(post, name):
    """name[0][field] → [ {field: v}, ...] به ترتیب اندیس"""
    rows = {}
    for k in post:
        m = re.match(rf'^{name}\[(\d+)\]\[(\w+)\]$', k)
        if m:
            rows.setdefault(int(m[1]), {})[m[2]] = post.get(k)
    return [rows[i] for i in sorted(rows)]


# ───────────── داشبورد ─────────────
@admin_required
def dashboard(request):
    services.release_expired()
    paid = Order.objects.filter(status='paid')
    today = timezone.localtime().replace(hour=0, minute=0, second=0, microsecond=0)
    from ..templatetags.ticket_tags import price
    stats = [
        ('فروش کل', price(paid.aggregate(s=Sum('total'))['s'])),
        ('فروش امروز', price(paid.filter(paid_at__gte=today).aggregate(s=Sum('total'))['s'])),
        ('بلیط‌های فروخته‌شده', fa_digits(OrderItem.objects.filter(order__status='paid').count())),
        ('کاربران', fa_digits(User.objects.count())),
        ('رویدادهای فعال', fa_digits(Event.objects.filter(status='published', starts_at__gte=today).count())),
        ('سفارش در انتظار', fa_digits(Order.objects.filter(status='pending').count())),
    ]
    return render(request, 'panel/dashboard.html', {'stats': stats, 'latest': Order.objects.select_related('user', 'event')[:10]})


# ───────────── CRUD عمومی ─────────────
def _slug_cat(o):
    o.slug = o.slug or slugify(o.name) or f'cat-{o.pk or "new"}'


def _slug_page(o):
    o.slug = o.slug or slugify(o.title) or 'page'


RESOURCES = {
    'categories': dict(model=Category, title='دسته‌بندی رویدادها', singular='دسته‌بندی', order='sort',
                       fields=['name', 'slug', 'icon', 'sort', 'is_active'], columns=['name', 'slug'], prepare=_slug_cat),
    'sliders': dict(model=Slider, title='اسلایدر صفحه اصلی', singular='اسلاید', order='sort',
                    fields=['title', 'subtitle', 'image', 'link', 'sort', 'is_active'], columns=['title', 'link'], required_files=['image']),
    'pages': dict(model=Page, title='صفحات (درباره ما، قوانین، ...)', singular='صفحه', order='sort',
                  fields=['title', 'slug', 'body', 'show_in_footer', 'sort', 'is_active'], columns=['title', 'slug'], prepare=_slug_page),
    'licenses': dict(model=License, title='مجوزها و نمادهای فوتر (اینماد، ساماندهی، ...)', singular='مجوز', order='sort',
                     fields=['title', 'image', 'link', 'embed_code', 'sort', 'is_active'], columns=['title', 'link']),
    'venues': dict(model=Venue, title='سالن‌ها و مکان‌ها', singular='مکان', order='id',
                   fields=['name', 'city', 'address', 'map_url'], columns=['name', 'city'], halls=True),
}


def _form_class(cfg):
    fields = cfg['fields']
    widgets = {}
    for f in fields:
        mf = cfg['model']._meta.get_field(f)
        if isinstance(mf, __import__('django.db.models', fromlist=['TextField']).TextField):
            widgets[f] = forms.Textarea(attrs={'rows': 12 if f == 'body' else 4, 'class': 'form-control'})
        elif mf.get_internal_type() == 'FileField':
            widgets[f] = forms.FileInput(attrs={'class': 'form-control', 'accept': 'image/*'})
    Form = modelform_factory(cfg['model'], fields=fields, widgets=widgets)
    for name, field in Form.base_fields.items():
        if isinstance(field.widget, forms.CheckboxInput):
            field.widget.attrs['class'] = 'form-check-input'
        elif 'class' not in field.widget.attrs:
            field.widget.attrs['class'] = 'form-control'
        if name in ('slug', 'link', 'map_url', 'embed_code'):
            field.widget.attrs['dir'] = 'ltr'
        if name == 'sort':
            field.required = False
        if name in cfg.get('required_files', []):
            field.required = True
    return Form


@admin_required
def resource_list(request, key):
    cfg = RESOURCES.get(key) or Http404
    if cfg is Http404:
        raise Http404
    items = cfg['model'].objects.order_by(cfg['order'])
    cols = [(c, cfg['model']._meta.get_field(c).verbose_name) for c in cfg['columns']]
    return render(request, 'panel/resource_list.html', {'key': key, 'cfg': cfg, 'items': Paginator(items, 50).get_page(request.GET.get('page')), 'cols': cols})


@admin_required
def resource_edit(request, key, pk=None):
    cfg = RESOURCES.get(key)
    if not cfg:
        raise Http404
    obj = get_object_or_404(cfg['model'], pk=pk) if pk else None
    Form = _form_class(cfg)
    form = Form(request.POST or None, request.FILES or None, instance=obj)
    if request.method == 'POST' and form.is_valid():
        o = form.save(commit=False)
        for f in cfg['fields']:
            if cfg['model']._meta.get_field(f).get_internal_type() == 'FileField':
                if request.FILES.get(f):
                    setattr(o, f, store_upload(request.FILES[f]))
                elif request.POST.get(f'remove_{f}') and f not in cfg.get('required_files', []):
                    setattr(o, f, '')
                elif obj:
                    setattr(o, f, getattr(obj, f))
        if hasattr(o, 'sort') and o.sort is None:
            o.sort = 0
        if cfg.get('prepare'):
            cfg['prepare'](o)
        o.save()
        messages.success(request, 'ذخیره شد.')
        return redirect('panel_resource_list', key=key)
    return render(request, 'panel/resource_form.html', {'key': key, 'cfg': cfg, 'form': form, 'obj': obj})


@admin_required
@require_POST
def resource_delete(request, key, pk):
    cfg = RESOURCES.get(key)
    if not cfg:
        raise Http404
    try:
        get_object_or_404(cfg['model'], pk=pk).delete()
        messages.success(request, 'حذف شد.')
    except Exception:  # noqa — PROTECT
        messages.error(request, 'این مورد در جای دیگری استفاده شده و قابل حذف نیست.')
    return redirect('panel_resource_list', key=key)


# ───────────── تنظیمات ─────────────
@admin_required
def settings_view(request):
    if request.method == 'POST':
        for g in site.GROUPS.values():
            for key, (label, typ, default) in g['fields'].items():
                if typ == 'checkbox':
                    site.put(key, '1' if request.POST.get(key) == '1' else '0')
                elif typ == 'image':
                    if request.FILES.get(key):
                        site.put(key, store_upload(request.FILES[key]))
                    elif request.POST.get(f'remove_{key}'):
                        site.put(key, '')
                elif typ == 'password':
                    if request.POST.get(key):
                        site.put(key, request.POST[key])
                elif key in request.POST:
                    site.put(key, request.POST[key])
        messages.success(request, 'تنظیمات ذخیره شد.')
        return redirect('panel_settings')
    groups = []
    for gk, g in site.GROUPS.items():
        fields = []
        for key, (label, typ, default) in g['fields'].items():
            val = site.get(key)
            fields.append({'key': key, 'label': label, 'type': typ, 'value': val, 'is_set': bool(val) and typ == 'password',
                           'checked': val == '1'})
        groups.append({'key': gk, 'title': g['title'], 'fields': fields})
    return render(request, 'panel/settings.html', {'groups': groups})


# ───────────── رویدادها ─────────────
@admin_required
def event_list(request):
    events = Event.objects.select_related('category').annotate(paid_orders=Count('orders', filter=Q(orders__status='paid'))).order_by('-starts_at')
    return render(request, 'panel/event_list.html', {'events': Paginator(events, 25).get_page(request.GET.get('page'))})


def _unique_slug(base, exclude_id):
    base = slugify(base) or 'event'
    slug, i = base, 1
    while Event.objects.filter(slug=slug).exclude(id=exclude_id).exists():
        i += 1
        slug = f'{base}-{i}'
    return slug


@admin_required
def event_edit(request, pk=None):
    event = get_object_or_404(Event, pk=pk) if pk else None
    errors = []
    if request.method == 'POST':
        P = request.POST
        rows = []
        for n, r in enumerate(nested(P, 'sessions')):
            if not (r.get('starts_at') or '').strip():
                continue
            st = parse_jdate(r['starts_at'])
            if not st:
                errors.append(f'تاریخ سانس {n + 1} نامعتبر است. نمونه: ۱۴۰۵/۰۷/۲۰ ۲۱:۳۰')
                continue
            rows.append({'id': r.get('id') or None, 'starts_at': st, 'ends_at': parse_jdate(r.get('ends_at')) if r.get('ends_at') else None,
                         'sales_open': r.get('sales_open') == '1'})
        if not rows and not errors:
            errors.append('حداقل یک سانس لازم است.')
        if not P.get('title', '').strip():
            errors.append('عنوان الزامی است.')
        if not errors:
            with transaction.atomic():
                e = event or Event()
                e.title, e.subtitle, e.description = P['title'].strip(), P.get('subtitle', ''), P.get('description', '')
                e.category_id = int(P['category_id']) if P.get('category_id') else None
                e.hall_id = int(P['hall_id']) if P.get('hall_id') else None
                e.venue_name, e.venue_address = P.get('venue_name', ''), P.get('venue_address', '')
                e.status = 'published' if P.get('status') == 'published' else 'draft'
                e.sales_open, e.is_featured = P.get('sales_open') == '1', P.get('is_featured') == '1'
                e.slug = _unique_slug(P.get('slug') or e.title, e.id)
                e.starts_at = rows[0]['starts_at']
                if request.FILES.get('poster'):
                    try:
                        e.poster = store_upload(request.FILES['poster'])
                    except ValueError as ex:
                        errors.append(str(ex))
                if not errors:
                    e.save()
                    _sync_sessions(e, rows)
                    _sync_ticket_types(e, P)
                    messages.success(request, 'رویداد ذخیره شد.')
                    return redirect('panel_event_edit', pk=e.id)
    ctx = _event_form_ctx(event)
    ctx['errors'] = errors
    if request.method == 'POST':
        ctx['posted'] = request.POST
    return render(request, 'panel/event_form.html', ctx)


def _event_form_ctx(event):
    halls = Hall.objects.select_related('venue').prefetch_related('categories')
    return {
        'event': event or Event(), 'categories': Category.objects.all(), 'posted': {}, 'halls': halls,
        'hall_cats': {h.id: [{'id': c.id, 'name': c.name, 'color': c.color} for c in h.categories.all()] for h in halls},
        'seat_prices': {t.seat_category_id: t.price for t in event.ticket_types.filter(is_active=True, seat_category__isnull=False)} if event else {},
        'general': [{'id': t.id, 'name': t.name, 'price': t.price, 'capacity': t.capacity}
                    for t in event.ticket_types.filter(is_active=True, seat_category__isnull=True)] if event else [],
        'sessions_json': [{'id': s.id, 'starts_at': jdate(s.starts_at, 'input'), 'ends_at': jdate(s.ends_at, 'input') if s.ends_at else '', 'sales_open': s.sales_open}
                          for s in event.sessions.all()] if event else [],
        'starts_input': jdate(event.starts_at, 'input') if event else '',
    }


def _sync_sessions(event, rows):
    keep = []
    for r in rows:
        s = event.sessions.filter(id=r['id']).first() if r['id'] else None
        s = s or EventSession(event=event)
        s.starts_at, s.ends_at, s.sales_open = r['starts_at'], r['ends_at'], r['sales_open']
        s.save()
        keep.append(s.id)
    for s in event.sessions.exclude(id__in=keep):
        if Order.objects.filter(session=s).exists():
            s.sales_open = False   # سانسی که سفارش دارد حذف نمی‌شود
            s.save()
        else:
            s.delete()
    event.sync_dates_from_sessions()


def _sync_ticket_types(event, P):
    keep = []
    if event.hall_id:
        cats = {c.id: c for c in event.hall.categories.all()}
        for k, v in P.items():
            m = re.match(r'^seat_price\[(\d+)\]$', k)
            if m and int(m[1]) in cats and v.strip():
                cat = cats[int(m[1])]
                t, _ = TicketType.objects.update_or_create(event=event, seat_category=cat, defaults={'name': cat.name, 'price': int(en_digits(v) or 0), 'is_active': True, 'capacity': None})
                keep.append(t.id)
    for r in nested(P, 'general'):
        if not (r.get('name') or '').strip():
            continue
        attrs = {'name': r['name'].strip(), 'price': int(en_digits(r.get('price') or 0) or 0),
                 'capacity': int(en_digits(r['capacity'])) if (r.get('capacity') or '').strip() else None, 'is_active': True, 'seat_category': None}
        t = event.ticket_types.filter(id=r['id'], seat_category__isnull=True).first() if r.get('id') else None
        if t:
            for a, v in attrs.items():
                setattr(t, a, v)
            t.save()
        else:
            t = TicketType.objects.create(event=event, **attrs)
        keep.append(t.id)
    for t in event.ticket_types.exclude(id__in=keep):
        if OrderItem.objects.filter(ticket_type=t).exists():
            t.is_active = False
            t.save()
        else:
            t.delete()


@admin_required
@require_POST
def event_delete(request, pk):
    e = get_object_or_404(Event, pk=pk)
    if e.orders.exists():
        messages.error(request, 'برای این رویداد سفارش ثبت شده است؛ به‌جای حذف آن را پیش‌نویس کنید.')
        return redirect('panel_event_list')
    e.delete()
    messages.success(request, 'رویداد حذف شد.')
    return redirect('panel_event_list')


@admin_required
def event_report(request, pk):
    event = get_object_or_404(Event, pk=pk)
    services.release_expired()
    items = OrderItem.objects.select_related('order__user', 'order__session', 'ticket_type', 'seat').filter(event=event, order__status='paid').order_by('id')
    if request.GET.get('session'):
        items = items.filter(session_id=request.GET['session'])
    items = list(items)
    return render(request, 'panel/event_report.html', {
        'event': event, 'items': items, 'sessions': event.sessions.all(), 'revenue': sum(i.price for i in items),
        'checked': sum(1 for i in items if i.checked_in_at),
    })


# ───────────── قیمت اختصاصی صندلی‌ها ─────────────
@admin_required
def event_seat_prices(request, pk):
    event = get_object_or_404(Event.objects.select_related('hall'), pk=pk)
    if not event.hall_id:
        messages.error(request, 'ابتدا برای رویداد یک سالن (پلان صندلی) انتخاب و ذخیره کنید.')
        return redirect('panel_event_edit', pk=pk)
    layout = event.hall.layout()
    types = {t.seat_category_id: t.price for t in event.ticket_types.filter(is_active=True, seat_category__isnull=False)}
    for c in layout['categories']:
        c['price'] = types.get(c['id'])
    return render(request, 'panel/event_seat_prices.html', {
        'event': event, 'layout': layout, 'overrides': services.seat_price_map(event),
    })


@admin_required
@require_POST
def event_seat_prices_save(request, pk):
    event = get_object_or_404(Event, pk=pk)
    try:
        data = json.loads(request.body)['prices']   # {seat_id: price|null}
    except (ValueError, KeyError):
        return JsonResponse({'message': 'درخواست نامعتبر است.'}, status=400)
    valid = set(Seat.objects.filter(level__hall_id=event.hall_id).values_list('id', flat=True))
    with transaction.atomic():
        for sid, price in data.items():
            sid = int(sid)
            if sid not in valid:
                continue
            if price in (None, ''):
                SeatPrice.objects.filter(event=event, seat_id=sid).delete()
            else:
                SeatPrice.objects.update_or_create(event=event, seat_id=sid, defaults={'price': max(0, int(en_digits(price)))})
    return JsonResponse({'ok': True, 'overrides': services.seat_price_map(event)})


# ───────────── سالن و طراح ─────────────
@admin_required
def hall_list(request, venue_id):
    venue = get_object_or_404(Venue, pk=venue_id)
    if request.method == 'POST':
        name = request.POST.get('name', '').strip()
        if name:
            hall = Hall.objects.create(venue=venue, name=name)
            HallLevel.objects.create(hall=hall, name='طبقه همکف', sort=0)
            SeatCategory.objects.create(hall=hall, name='عمومی', color='#3b82f6')
            return redirect('panel_hall_designer', pk=hall.id)
    return render(request, 'panel/hall_list.html', {'venue': venue, 'halls': venue.halls.annotate(n=Count('levels'))})


@admin_required
@require_POST
def hall_delete(request, pk):
    hall = get_object_or_404(Hall, pk=pk)
    if Event.objects.filter(hall=hall).exists():
        messages.error(request, 'برای این پلان رویداد ثبت شده و قابل حذف نیست.')
    else:
        hall.delete()
        messages.success(request, 'پلان حذف شد.')
    return redirect('panel_hall_list', venue_id=hall.venue_id)


@admin_required
def hall_designer(request, pk):
    hall = get_object_or_404(Hall.objects.select_related('venue'), pk=pk)
    return render(request, 'panel/hall_designer.html', {'hall': hall, 'layout': hall.layout()})


def _is_id(v):
    return v is not None and str(v).isdigit()


@admin_required
@require_POST
def hall_designer_save(request, pk):
    hall = get_object_or_404(Hall, pk=pk)
    try:
        d = json.loads(request.body)
        assert d['categories'] and d['levels']
    except Exception:  # noqa
        return JsonResponse({'message': 'داده نامعتبر است.'}, status=400)
    try:
        with transaction.atomic():
            if d.get('name'):
                hall.name = d['name'][:120]
                hall.save(update_fields=['name'])
            # دسته‌بندی‌ها
            cat_map, keep_cats = {}, []
            for c in d['categories']:
                cat = SeatCategory.objects.filter(hall=hall, id=c['id']).first() if _is_id(c.get('id')) else None
                cat = cat or SeatCategory(hall=hall)
                cat.name, cat.color = str(c['name'])[:60], str(c['color'])[:9]
                cat.save()
                cat_map[str(c['id'])] = cat.id
                keep_cats.append(cat.id)
            SeatCategory.objects.filter(hall=hall).exclude(id__in=keep_cats).delete()
            # طبقات و صندلی‌ها
            before = set(Seat.objects.filter(level__hall=hall).values_list('id', flat=True))
            own_levels = set(HallLevel.objects.filter(hall=hall).values_list('id', flat=True))
            keep_levels, keep_seats = [], set()
            for i, l in enumerate(d['levels']):
                lv = HallLevel.objects.filter(hall=hall, id=l['id']).first() if _is_id(l.get('id')) else None
                lv = lv or HallLevel(hall=hall)
                lv.name, lv.sort = str(l['name'])[:60], i
                lv.width, lv.height = max(200, min(6000, int(l['width']))), max(200, min(6000, int(l['height'])))
                lv.shapes = l.get('shapes') or []
                lv.save()
                keep_levels.append(lv.id)
                new = []
                for s in l.get('seats') or []:
                    vals = dict(level_id=lv.id, seat_category_id=cat_map.get(str(s.get('cat'))), row_label=(s.get('row') or None),
                                label=str(s['label'])[:30], x=round(float(s['x']), 1), y=round(float(s['y']), 1))
                    if _is_id(s.get('id')) and int(s['id']) in before:
                        Seat.objects.filter(id=int(s['id'])).update(**vals)
                        keep_seats.add(int(s['id']))
                    else:
                        new.append(Seat(**vals))
                Seat.objects.bulk_create(new, batch_size=500)
            removed = before - keep_seats
            if removed and OrderItem.objects.filter(seat_id__in=removed).exists():
                raise ValueError('برخی از صندلی‌های حذف‌شده قبلاً بلیط فروخته‌اند و قابل حذف نیستند.')
            Seat.objects.filter(id__in=removed).delete()
            HallLevel.objects.filter(hall=hall).exclude(id__in=keep_levels).delete()
    except ValueError as e:
        return JsonResponse({'message': str(e)}, status=422)
    return JsonResponse({'ok': True, 'layout': hall.layout()})


# ───────────── سفارش‌ها، کاربران، کنترل بلیط ─────────────
@admin_required
def order_list(request):
    services.release_expired()
    qs = Order.objects.select_related('user', 'event')
    if request.GET.get('status'):
        qs = qs.filter(status=request.GET['status'])
    q = request.GET.get('q', '').strip()
    if q:
        qs = qs.filter(Q(code__icontains=q) | Q(user__mobile__icontains=q) | Q(user__name__icontains=q))
    qd = request.GET.copy()
    qd.pop('page', None)
    return render(request, 'panel/order_list.html', {'orders': Paginator(qs, 30).get_page(request.GET.get('page')), 'statuses': Order.STATUS, 'qs': qd.urlencode()})


@admin_required
def order_detail(request, code):
    order = get_object_or_404(Order.objects.select_related('user', 'event', 'session'), code=code)
    return render(request, 'panel/order_detail.html', {'order': order, 'items': order.items.select_related('ticket_type', 'seat'), 'payments': order.payments.all()})


@admin_required
@require_POST
def order_cancel(request, code):
    order = get_object_or_404(Order, code=code)
    was_paid = order.status == 'paid'
    order.status = 'canceled'
    order.save(update_fields=['status'])
    order.items.update(active=False, lock_key=None, **({'ticket_code': None} if was_paid else {}))
    messages.success(request, 'سفارش لغو و بلیط‌ها باطل شد. (بازگشت وجه دستی از درگاه انجام شود)' if was_paid else 'سفارش لغو شد.')
    return redirect('panel_order_detail', code=code)


@admin_required
def user_list(request):
    qs = User.objects.annotate(orders_count=Count('orders')).order_by('-id')
    q = request.GET.get('q', '').strip()
    if q:
        qs = qs.filter(Q(mobile__icontains=q) | Q(name__icontains=q))
    return render(request, 'panel/user_list.html', {'users': Paginator(qs, 30).get_page(request.GET.get('page')), 'q': q})


@admin_required
@require_POST
def user_update(request, pk):
    u = get_object_or_404(User, pk=pk)
    role = request.POST.get('role')
    if role not in ('admin', 'customer'):
        raise Http404
    if u.id == request.user.id and role != 'admin':
        messages.error(request, 'نمی‌توانید نقش خودتان را کاهش دهید.')
    else:
        u.role = role
        if request.POST.get('password'):
            if len(request.POST['password']) < 6:
                messages.error(request, 'رمز باید حداقل ۶ کاراکتر باشد.')
                return redirect('panel_user_list')
            u.set_password(request.POST['password'])
        u.save()
        messages.success(request, 'کاربر ویرایش شد.')
    return redirect('panel_user_list')


@admin_required
def ticket_check(request):
    code = (request.GET.get('code') or '').strip().upper()
    item = None
    if code:
        item = OrderItem.objects.select_related('order__user', 'order__event', 'order__session', 'seat', 'ticket_type').filter(ticket_code=code, order__status='paid').first()
        if not item:
            messages.error(request, 'بلیطی با این کد معتبر نیست.')
    return render(request, 'panel/check.html', {'item': item, 'code': code})


@admin_required
@require_POST
def ticket_checkin(request, pk):
    item = get_object_or_404(OrderItem, pk=pk, order__status='paid', ticket_code__isnull=False)
    if item.checked_in_at:
        messages.error(request, f'این بلیط قبلاً استفاده شده است ({jdate(item.checked_in_at)}).')
    else:
        item.checked_in_at = timezone.now()
        item.save(update_fields=['checked_in_at'])
        messages.success(request, 'ورود ثبت شد.')
    return redirect(f"/panel/check/?code={item.ticket_code}")
