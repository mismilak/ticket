from datetime import timedelta

from django.core.management import call_command
from django.core.management.base import BaseCommand
from django.utils import timezone

from ticketing.models import Category, Event, SeatPrice, Venue, Seat


class Command(BaseCommand):
    help = 'داده نمونه: سالن دو طبقه + رویداد سه‌سانسه + رویداد تعدادی'

    def handle(self, *a, **o):
        call_command('bootstrap_site')
        venue, _ = Venue.objects.get_or_create(name='تالار وحدت', defaults={'city': 'تهران', 'address': 'میدان امام حسین، خیابان ایرانشهر'})
        hall, created = venue.halls.get_or_create(name='سالن اصلی')
        if not created and hall.levels.exists():
            return self.stdout.write('داده نمونه از قبل وجود دارد.')
        vip, gold, std, balc = [hall.categories.create(name=n, color=c) for n, c in
                                [('VIP', '#e11d48'), ('طلایی', '#f59e0b'), ('معمولی', '#3b82f6'), ('بالکن', '#10b981')]]
        ground = hall.levels.create(name='همکف', sort=0, width=1000, height=700, shapes=[{'type': 'stage', 'x': 300, 'y': 30, 'w': 400, 'h': 60, 'text': 'صحنه'}])
        up = hall.levels.create(name='بالکن', sort=1, width=1000, height=400, shapes=[{'type': 'text', 'x': 500, 'y': 30, 'text': 'طبقه بالا (بالکن)', 'size': 18}])
        for r, label in enumerate('ABCDEFGH'):
            c = vip if r < 2 else gold if r < 5 else std
            for i in range(16):
                ground.seats.create(seat_category=c, row_label=label, label=str(16 - i), x=190 + i * 38, y=140 + r * 40)
        for r, label in enumerate('ABC'):
            for i in range(20):
                up.seats.create(seat_category=balc, row_label=label, label=str(20 - i), x=130 + i * 38, y=100 + r * 40)

        day = timezone.localtime().replace(minute=0, second=0, microsecond=0)
        ev = Event.objects.create(category=Category.objects.filter(slug='concert').first(), hall=hall, title='کنسرت نمونه (طراحی صندلی)', slug='demo-concert',
                                  subtitle='اجرای زنده', description='این یک رویداد نمونه است.\nبرای ویرایش به پنل مدیریت بروید.',
                                  starts_at=day + timedelta(days=20), status='published', is_featured=True)
        for d, h in [(20, 21), (21, 18), (21, 21)]:
            ev.sessions.create(starts_at=(day + timedelta(days=d)).replace(hour=h))
        ev.sync_dates_from_sessions()
        for c, p in [(vip, 1500000), (gold, 900000), (std, 500000), (balc, 350000)]:
            ev.ticket_types.create(seat_category=c, name=c.name, price=p)
        # نمونه قیمت اختصاصی: دو صندلی کنار راهرو ردیف C گران‌تر
        for s in Seat.objects.filter(level=ground, row_label='C', label__in=['8', '9']):
            SeatPrice.objects.create(event=ev, seat=s, price=1200000)

        conf = Event.objects.create(category=Category.objects.filter(slug='conference').first(), title='همایش نمونه (بلیط تعدادی)', slug='demo-conference',
                                    venue_name='برج میلاد', description='رویداد بدون پلان صندلی؛ فروش تعدادی.', starts_at=day + timedelta(days=10), status='published')
        conf.sessions.create(starts_at=conf.starts_at)
        conf.ticket_types.create(name='بلیط عادی', price=200000, capacity=100)
        conf.ticket_types.create(name='دانشجویی', price=100000, capacity=30)
        self.stdout.write(self.style.SUCCESS('داده نمونه ساخته شد.'))
