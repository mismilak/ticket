from django.conf import settings
from django.urls import include, path, re_path
from django.views.static import serve

urlpatterns = [path('', include('ticketing.urls'))]
if settings.SERVE_MEDIA:
    urlpatterns += [re_path(r'^media/(?P<path>.*)$', serve, {'document_root': settings.MEDIA_ROOT})]
