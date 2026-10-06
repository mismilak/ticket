from django.contrib.auth.base_user import AbstractBaseUser, BaseUserManager
from django.contrib.auth.models import PermissionsMixin
from django.db import models
from django.utils import timezone

from . import site as site_store


# ───────────── کاربران ─────────────
class UserManager(BaseUserManager):
    def create_user(self, mobile, password=None, **extra):
        user = self.model(mobile=mobile, **extra)
        user.set_password(password) if password else user.set_unusable_password()
        user.save(using=self._db)
        return user

    def create_superuser(self, mobile, password=None, **extra):
        extra.setdefault('role', 'admin')
        return self.create_user(mobile, password, **extra)


class User(AbstractBaseUser, PermissionsMixin):
    mobile = models.CharField('موبایل', max_length=11, unique=True)
    name = models.CharField('نام', max_length=80, blank=True)
    email = models.EmailField('ایمیل', blank=True)
    role = models.CharField(max_length=20, default='customer', choices=[('customer', 'کاربر'), ('admin', 'مدیر')])
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)

    USERNAME_FIELD = 'mobile'
    objects = UserManager()

    @property
    def is_admin(self):
        return self.role == 'admin'

    @property
    def is_staff(self):
        return self.is_admin

    def display_name(self):
        return self.name or self.mobile

    def __str__(self):
        return self.display_name()


class SiteSetting(models.Model):
    key = models.CharField(max_length=80, primary_key=True)
    value = models.TextField(blank=True, null=True)


class OtpCode(models.Model):
    mobile = models.CharField(max_length=11, db_index=True)
    code_hash = models.CharField(max_length=128)
    attempts = models.PositiveSmallIntegerField(default=0)
    expires_at = models.DateTimeField()
    created_at = models.DateTimeField(auto_now_add=True)


# ───────────── محتوا ─────────────
class Category(models.Model):
    name = models.CharField('نام', max_length=60)
    slug = models.CharField('نشانی', max_length=60, unique=True, blank=True)
    icon = models.CharField('آیکن (bootstrap-icons)', max_length=40, blank=True)
    sort = models.PositiveIntegerField('ترتیب', default=0)
    is_active = models.BooleanField('فعال', default=True)

    class Meta:
        ordering = ['sort', 'id']

    def __str__(self):
        return self.name


class Page(models.Model):
    title = models.CharField('عنوان', max_length=120)
    slug = models.CharField('نشانی', max_length=80, unique=True, blank=True)
    body = models.TextField('محتوا (HTML مجاز است)', blank=True)
    show_in_footer = models.BooleanField('نمایش لینک در فوتر', default=True)
    sort = models.PositiveIntegerField('ترتیب', default=0)
    is_active = models.BooleanField('فعال', default=True)

    def __str__(self):
        return self.title


class Slider(models.Model):
    title = models.CharField('عنوان', max_length=120, blank=True)
    subtitle = models.CharField('زیرعنوان', max_length=200, blank=True)
    image = models.FileField('تصویر (پیشنهادی ۱۶۰۰×۶۰۰)', upload_to='uploads/')
    link = models.CharField('لینک', max_length=255, blank=True)
    sort = models.PositiveIntegerField('ترتیب', default=0)
    is_active = models.BooleanField('فعال', default=True)


class License(models.Model):
    title = models.CharField('عنوان (مثلاً نماد اعتماد الکترونیکی)', max_length=120)
    image = models.FileField('تصویر نماد', upload_to='uploads/', blank=True)
    link = models.CharField('لینک نماد', max_length=500, blank=True)
    embed_code = models.TextField('یا کد HTML نماد (اولویت با این است؛ مناسب کد اینماد)', blank=True)
    sort = models.PositiveIntegerField('ترتیب', default=0)
    is_active = models.BooleanField('فعال', default=True)


# ───────────── سالن ─────────────
class Venue(models.Model):
    name = models.CharField('نام مجموعه / سالن', max_length=120)
    city = models.CharField('شهر', max_length=60, blank=True)
    address = models.CharField('آدرس', max_length=255, blank=True)
    map_url = models.CharField('لینک نقشه', max_length=500, blank=True)

    def __str__(self):
        return self.name


class Hall(models.Model):
    venue = models.ForeignKey(Venue, on_delete=models.CASCADE, related_name='halls')
    name = models.CharField(max_length=120)

    def __str__(self):
        return f'{self.venue.name} — {self.name}'

    def layout(self):
        """ساختار کامل چیدمان (طراح و نقشه خرید)"""
        levels = []
        for l in self.levels.prefetch_related('seats'):
            levels.append({
                'id': l.id, 'name': l.name, 'width': l.width, 'height': l.height, 'shapes': l.shapes or [],
                'seats': [{'id': s.id, 'x': s.x, 'y': s.y, 'label': s.label, 'row': s.row_label or None, 'cat': s.seat_category_id}
                          for s in l.seats.all()],
            })
        return {'levels': levels, 'categories': [{'id': c.id, 'name': c.name, 'color': c.color} for c in self.categories.all()]}


class HallLevel(models.Model):
    hall = models.ForeignKey(Hall, on_delete=models.CASCADE, related_name='levels')
    name = models.CharField(max_length=60)
    sort = models.PositiveIntegerField(default=0)
    width = models.PositiveIntegerField(default=1200)
    height = models.PositiveIntegerField(default=800)
    shapes = models.JSONField(default=list, blank=True)

    class Meta:
        ordering = ['sort', 'id']


class SeatCategory(models.Model):
    hall = models.ForeignKey(Hall, on_delete=models.CASCADE, related_name='categories')
    name = models.CharField(max_length=60)
    color = models.CharField(max_length=9, default='#6c5ce7')

    class Meta:
        ordering = ['id']


class Seat(models.Model):
    level = models.ForeignKey(HallLevel, on_delete=models.CASCADE, related_name='seats', db_column='hall_level_id')
    seat_category = models.ForeignKey(SeatCategory, null=True, blank=True, on_delete=models.SET_NULL)
    row_label = models.CharField(max_length=20, blank=True, null=True)
    label = models.CharField(max_length=30)
    x = models.FloatField()
    y = models.FloatField()

    class Meta:
        ordering = ['id']

    def title(self):
        return (f'ردیف {self.row_label} - ' if self.row_label else '') + f'صندلی {self.label}'


# ───────────── رویداد ─────────────
class Event(models.Model):
    category = models.ForeignKey(Category, null=True, blank=True, on_delete=models.SET_NULL)
    hall = models.ForeignKey(Hall, null=True, blank=True, on_delete=models.SET_NULL)
    title = models.CharField(max_length=200)
    slug = models.CharField(max_length=150, unique=True)
    subtitle = models.CharField(max_length=200, blank=True)
    description = models.TextField(blank=True)
    poster = models.FileField(upload_to='uploads/', blank=True)
    venue_name = models.CharField(max_length=200, blank=True)
    venue_address = models.CharField(max_length=255, blank=True)
    starts_at = models.DateTimeField()
    ends_at = models.DateTimeField(null=True, blank=True)
    status = models.CharField(max_length=20, default='draft')  # draft | published
    sales_open = models.BooleanField(default=True)
    is_featured = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)

    def __str__(self):
        return self.title

    def poster_url(self):
        from django.conf import settings
        from django.templatetags.static import static
        return f'{settings.MEDIA_URL}{self.poster}' if self.poster else static('img/placeholder.svg')

    def venue_label(self):
        return f'{self.hall.venue.name} — {self.hall.name}' if self.hall_id else self.venue_name

    def is_seated(self):
        return bool(self.hall_id) and (self.ticket_types.filter(is_active=True, seat_category__isnull=False).exists() or self.seat_prices.exists())

    def is_on_sale(self):
        return self.status == 'published' and self.sales_open and (self.ends_at or self.starts_at) > timezone.now()

    def min_price(self):
        prices = list(self.ticket_types.filter(is_active=True).values_list('price', flat=True))
        prices += list(self.seat_prices.values_list('price', flat=True))
        return min(prices) if prices else None

    def sync_dates_from_sessions(self):
        sessions = list(self.sessions.order_by('starts_at'))
        if not sessions:
            return
        now = timezone.now()
        nxt = next((s for s in sessions if (s.ends_at or s.starts_at) > now), sessions[-1])
        self.starts_at = nxt.starts_at
        self.ends_at = max((s.ends_at or s.starts_at) for s in sessions)
        self.save(update_fields=['starts_at', 'ends_at'])


class EventSession(models.Model):
    event = models.ForeignKey(Event, on_delete=models.CASCADE, related_name='sessions')
    starts_at = models.DateTimeField()
    ends_at = models.DateTimeField(null=True, blank=True)
    sales_open = models.BooleanField(default=True)

    class Meta:
        ordering = ['starts_at']

    def is_on_sale(self):
        return self.sales_open and self.event.status == 'published' and (self.ends_at or self.starts_at) > timezone.now()


class TicketType(models.Model):
    event = models.ForeignKey(Event, on_delete=models.CASCADE, related_name='ticket_types')
    seat_category = models.ForeignKey(SeatCategory, null=True, blank=True, on_delete=models.SET_NULL)
    name = models.CharField(max_length=120)
    price = models.PositiveBigIntegerField(default=0)
    capacity = models.PositiveIntegerField(null=True, blank=True)  # فقط بلیط عمومی؛ به‌ازای هر سانس
    is_active = models.BooleanField(default=True)


class SeatPrice(models.Model):
    """قیمت اختصاصی یک صندلی در یک رویداد (جایگزین قیمت دسته‌بندی)"""
    event = models.ForeignKey(Event, on_delete=models.CASCADE, related_name='seat_prices')
    seat = models.ForeignKey(Seat, on_delete=models.CASCADE, related_name='prices')
    price = models.PositiveBigIntegerField()

    class Meta:
        unique_together = [('event', 'seat')]


# ───────────── سفارش و پرداخت ─────────────
class Order(models.Model):
    STATUS = {'pending': 'در انتظار پرداخت', 'paid': 'پرداخت‌شده', 'failed': 'ناموفق', 'expired': 'منقضی‌شده', 'canceled': 'لغوشده'}

    code = models.CharField(max_length=16, unique=True)
    user = models.ForeignKey(User, on_delete=models.PROTECT, related_name='orders')
    event = models.ForeignKey(Event, on_delete=models.PROTECT, related_name='orders')
    session = models.ForeignKey(EventSession, null=True, on_delete=models.PROTECT, related_name='orders')
    status = models.CharField(max_length=20, default='pending', db_index=True)
    total = models.PositiveBigIntegerField(default=0)
    gateway = models.CharField(max_length=30, blank=True)
    expires_at = models.DateTimeField(null=True)
    paid_at = models.DateTimeField(null=True, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-id']

    def status_label(self):
        return self.STATUS.get(self.status, self.status)

    def is_pending(self):
        return self.status == 'pending' and self.expires_at and self.expires_at > timezone.now()

    def starts_at(self):
        return self.session.starts_at if self.session_id else self.event.starts_at

    def badge(self):
        return {'paid': 'success', 'pending': 'warning', 'failed': 'danger'}.get(self.status, 'secondary')


class OrderItem(models.Model):
    order = models.ForeignKey(Order, on_delete=models.CASCADE, related_name='items')
    event = models.ForeignKey(Event, on_delete=models.PROTECT)
    session = models.ForeignKey(EventSession, null=True, on_delete=models.PROTECT)
    ticket_type = models.ForeignKey(TicketType, on_delete=models.PROTECT, related_name='items')
    seat = models.ForeignKey(Seat, null=True, blank=True, on_delete=models.SET_NULL)
    price = models.PositiveBigIntegerField()
    ticket_code = models.CharField(max_length=16, null=True, blank=True, unique=True)
    active = models.BooleanField(default=True)
    # «سانس:صندلی» — ایندکس یکتا از فروش مضاعف جلوگیری می‌کند (NULL چندبار مجاز است)
    lock_key = models.CharField(max_length=40, null=True, blank=True, unique=True)
    checked_in_at = models.DateTimeField(null=True, blank=True)

    def seat_label(self):
        return self.seat.title() if self.seat_id else ''


class Payment(models.Model):
    order = models.ForeignKey(Order, on_delete=models.CASCADE, related_name='payments')
    gateway = models.CharField(max_length=30)
    amount = models.PositiveBigIntegerField()
    status = models.CharField(max_length=20, default='pending')
    authority = models.CharField(max_length=255, blank=True, null=True)
    ref_id = models.CharField(max_length=100, blank=True, null=True)
    card_pan = models.CharField(max_length=40, blank=True, null=True)
    message = models.TextField(blank=True, null=True)
    raw = models.JSONField(null=True, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
