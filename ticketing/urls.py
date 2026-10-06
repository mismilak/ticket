from django.urls import path

from .views import panel as p
from .views import site as v

urlpatterns = [
    path('', v.home, name='home'),
    path('events/', v.event_list, name='event_list'),
    path('events/<str:slug>/', v.event_detail, name='event_detail'),
    path('events/<str:slug>/seatmap/', v.seatmap, name='seatmap'),
    path('events/<str:slug>/reserve/', v.reserve, name='reserve'),
    path('page/<str:slug>/', v.page_view, name='page'),

    path('login/', v.login_view, name='login'),
    path('login/verify/', v.login_verify, name='login_verify'),
    path('logout/', v.logout_view, name='logout'),
    path('panel/login/', v.admin_login, name='admin_login'),
    path('profile/', v.profile, name='profile'),
    path('orders/', v.my_orders, name='orders'),
    path('orders/<str:code>/', v.order_detail, name='order_detail'),
    path('orders/<str:code>/pay/', v.order_pay, name='order_pay'),
    path('checkout/', v.checkout, name='checkout'),
    path('checkout/submit/', v.checkout_submit, name='checkout_submit'),
    path('payment/callback/<int:pk>/', v.payment_callback, name='payment_callback'),
    path('payment/fake/<int:pk>/', v.payment_fake, name='payment_fake'),

    # پنل مدیریت
    path('panel/', p.dashboard, name='panel_dashboard'),
    path('panel/r/<str:key>/', p.resource_list, name='panel_resource_list'),
    path('panel/r/<str:key>/new/', p.resource_edit, name='panel_resource_new'),
    path('panel/r/<str:key>/<int:pk>/', p.resource_edit, name='panel_resource_edit'),
    path('panel/r/<str:key>/<int:pk>/delete/', p.resource_delete, name='panel_resource_delete'),
    path('panel/settings/', p.settings_view, name='panel_settings'),
    path('panel/events/', p.event_list, name='panel_event_list'),
    path('panel/events/new/', p.event_edit, name='panel_event_new'),
    path('panel/events/<int:pk>/', p.event_edit, name='panel_event_edit'),
    path('panel/events/<int:pk>/delete/', p.event_delete, name='panel_event_delete'),
    path('panel/events/<int:pk>/report/', p.event_report, name='panel_event_report'),
    path('panel/events/<int:pk>/seat-prices/', p.event_seat_prices, name='panel_event_seat_prices'),
    path('panel/events/<int:pk>/seat-prices/save/', p.event_seat_prices_save, name='panel_event_seat_prices_save'),
    path('panel/venues/<int:venue_id>/halls/', p.hall_list, name='panel_hall_list'),
    path('panel/halls/<int:pk>/delete/', p.hall_delete, name='panel_hall_delete'),
    path('panel/halls/<int:pk>/designer/', p.hall_designer, name='panel_hall_designer'),
    path('panel/halls/<int:pk>/designer/save/', p.hall_designer_save, name='panel_hall_designer_save'),
    path('panel/orders/', p.order_list, name='panel_order_list'),
    path('panel/orders/<str:code>/', p.order_detail, name='panel_order_detail'),
    path('panel/orders/<str:code>/cancel/', p.order_cancel, name='panel_order_cancel'),
    path('panel/users/', p.user_list, name='panel_user_list'),
    path('panel/users/<int:pk>/', p.user_update, name='panel_user_update'),
    path('panel/check/', p.ticket_check, name='panel_check'),
    path('panel/check/<int:pk>/in/', p.ticket_checkin, name='panel_checkin'),
]
