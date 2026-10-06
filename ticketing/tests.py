from django.core.cache import cache
from django.core.management import call_command
from django.test import TestCase
from django.urls import reverse

from . import services, site
from .models import Event, Order, Payment, Seat, SeatPrice, User


class Base(TestCase):
    def setUp(self):
        cache.clear()   # کش تنظیمات بین تست‌ها نشت نکند

    @classmethod
    def setUpTestData(cls):
        call_command('seed_demo', verbosity=0)
        site.put('fake_enabled', '1')
        cls.event = Event.objects.get(slug='demo-concert')
        cls.u1 = User.objects.create_user('09111111111', name='الف')
        cls.u2 = User.objects.create_user('09122222222', name='ب')


class OrderRules(Base):
    def test_seat_cannot_be_sold_twice_and_is_released(self):
        seat, session = Seat.objects.first(), self.event.sessions.first()
        order = services.create_order(self.u1, self.event, session, {'seats': [seat.id]})
        with self.assertRaisesMessage(services.OrderError, 'رزرو'):
            services.create_order(self.u2, self.event, session, {'seats': [seat.id]})
        services.release(order, 'expired')
        self.assertIsNotNone(services.create_order(self.u2, self.event, session, {'seats': [seat.id]}))

    def test_sessions_have_independent_inventory(self):
        seat = Seat.objects.first()
        s1, s2 = self.event.sessions.all()[:2]
        services.create_order(self.u1, self.event, s1, {'seats': [seat.id]})
        services.create_order(self.u1, self.event, s2, {'seats': [seat.id]})
        self.assertEqual(services.seat_statuses(s1)[seat.id], 'held')
        self.assertEqual(services.seat_statuses(s2)[seat.id], 'held')

    def test_per_seat_price_overrides_category_price(self):
        session = self.event.sessions.first()
        special = Seat.objects.get(level__name='همکف', row_label='C', label='8')   # قیمت اختصاصی از seed_demo
        normal = Seat.objects.get(level__name='همکف', row_label='C', label='7')
        o = services.create_order(self.u1, self.event, session, {'seats': [special.id, normal.id]})
        prices = {i.seat_id: i.price for i in o.items.all()}
        self.assertEqual(prices[special.id], 1200000)
        self.assertEqual(prices[normal.id], 900000)
        self.assertEqual(o.total, 2100000)

    def test_override_makes_seat_sellable_without_category_price(self):
        session = self.event.sessions.first()
        seat = Seat.objects.get(level__name='بالکن', row_label='A', label='1')
        self.event.ticket_types.filter(seat_category=seat.seat_category).delete()
        with self.assertRaises(services.OrderError):
            services.create_order(self.u1, self.event, session, {'seats': [seat.id]})
        SeatPrice.objects.create(event=self.event, seat=seat, price=123000)
        o = services.create_order(self.u1, self.event, session, {'seats': [seat.id]})
        self.assertEqual(o.total, 123000)

    def test_general_capacity(self):
        conf = Event.objects.get(slug='demo-conference')
        t = conf.ticket_types.get(name='دانشجویی')
        t.capacity = 2
        t.save()
        s = conf.sessions.first()
        services.create_order(self.u1, conf, s, {'general': {t.id: 2}})
        with self.assertRaisesMessage(services.OrderError, 'ظرفیت'):
            services.create_order(self.u1, conf, s, {'general': {t.id: 1}})


class Flow(Base):
    def test_full_purchase_with_otp_and_test_gateway(self):
        seat = Seat.objects.first()
        session = self.event.sessions.first()
        r = self.client.post(reverse('reserve', args=[self.event.slug]), {'seats': [seat.id], 'session': session.id})
        self.assertRedirects(r, reverse('checkout'), fetch_redirect_response=False)
        self.assertEqual(self.client.get(reverse('checkout')).status_code, 302)   # لاگین نیست

        with self.settings(DEBUG=True):
            self.client.post(reverse('login'), {'mobile': '09123456789'})
        code = self.client.session['otp_dev']
        self.assertTrue(code)
        r = self.client.post(reverse('login_verify'), {'code': code})
        self.assertRedirects(r, reverse('profile'), fetch_redirect_response=False)
        self.client.post(reverse('profile'), {'name': 'تست'})

        r = self.client.post(reverse('checkout_submit'), {'gateway': 'fake'})
        self.assertEqual(r.status_code, 302)
        payment = Payment.objects.get()
        self.client.get(reverse('payment_callback', args=[payment.id]), {'result': 'ok'})
        order = Order.objects.get()
        self.assertEqual(order.status, 'paid')
        self.assertTrue(order.items.first().ticket_code)

    def test_fake_gateway_rejected_when_disabled(self):
        site.put('fake_enabled', '0')
        self.assertNotIn('fake', __import__('ticketing.payments', fromlist=['active']).active())

    def test_panel_requires_admin(self):
        self.assertEqual(self.client.get(reverse('panel_dashboard')).status_code, 302)
        self.client.force_login(self.u1)
        self.assertEqual(self.client.get(reverse('panel_dashboard')).status_code, 403)
        admin = User.objects.get(role='admin')
        self.client.force_login(admin)
        self.assertEqual(self.client.get(reverse('panel_dashboard')).status_code, 200)

    def test_public_pages(self):
        for name, args in [('home', []), ('event_list', []), ('event_detail', ['demo-concert']), ('seatmap', ['demo-concert']), ('login', [])]:
            self.assertEqual(self.client.get(reverse(name, args=args)).status_code, 200, name)
        j = self.client.get(reverse('seatmap', args=['demo-concert'])).json()
        self.assertTrue(any('price' in s for l in j['levels'] for s in l['seats']))
