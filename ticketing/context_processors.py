from django.utils import timezone

from . import site as site_store
from .jalali import jdate


def site(request):
    from .models import Category, License, Page
    ctx = {'site': site_store.SiteProxy(), 'now': timezone.now()}
    if request.path.startswith('/panel'):
        return ctx
    ctx.update({
        'nav_categories': Category.objects.filter(is_active=True).order_by('sort')[:6],
        'footer_pages': Page.objects.filter(is_active=True, show_in_footer=True).order_by('sort'),
        'licenses': [l for l in License.objects.filter(is_active=True).order_by('sort') if l.image or (l.embed_code or '').strip()],
        'year': jdate(timezone.now(), 'year'),
    })
    return ctx
