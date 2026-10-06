import os

from django.core.management.base import BaseCommand

from ticketing.models import Category, License, Page, User


class Command(BaseCommand):
    help = 'ساخت مدیر اولیه، دسته‌بندی‌ها، صفحات ثابت و جای نمادهای فوتر (بدون تکرار)'

    def add_arguments(self, parser):
        parser.add_argument('--mobile', default=os.environ.get('ADMIN_MOBILE', '09120000000'))
        parser.add_argument('--password', default=os.environ.get('ADMIN_PASSWORD', 'admin1234'))

    def handle(self, *a, **o):
        u, created = User.objects.get_or_create(mobile=o['mobile'], defaults={'name': 'مدیر سایت', 'role': 'admin'})
        if created or not u.has_usable_password():
            u.set_password(o['password'])
        u.role = 'admin'
        u.save()
        for i, (n, s, ic) in enumerate([('کنسرت', 'concert', 'mic'), ('تئاتر', 'theater', 'stars'), ('سینما', 'cinema', 'film'), ('همایش', 'conference', 'people'), ('کودک', 'kids', 'balloon')]):
            Category.objects.get_or_create(slug=s, defaults={'name': n, 'icon': ic, 'sort': i})
        for i, (s, t) in enumerate([('about', 'درباره ما'), ('terms', 'قوانین و مقررات'), ('faq', 'سوالات متداول')], 1):
            Page.objects.get_or_create(slug=s, defaults={'title': t, 'body': f'<p>متن «{t}» را از پنل مدیریت ویرایش کنید.</p>', 'sort': i})
        if not License.objects.exists():
            License.objects.create(title='نماد اعتماد الکترونیکی (اینماد)', sort=1, is_active=False)
            License.objects.create(title='نماد ساماندهی', sort=2, is_active=False)
        self.stdout.write(self.style.SUCCESS(f'آماده شد. مدیر: {o["mobile"]}'))
