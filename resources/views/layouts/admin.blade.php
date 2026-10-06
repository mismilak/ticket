<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'پنل مدیریت') | {{ setting('site_name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>:root{--c-primary:{{ setting('color_primary') }};--c-secondary:{{ setting('color_secondary') }}}</style>
    @stack('head')
</head>
<body class="admin-body">
<div class="d-flex admin-wrap">
    @php
        $nav = [
            ['admin.dashboard', 'speedometer2', 'داشبورد'],
            ['admin.events.index', 'calendar-event', 'رویدادها'],
            ['admin.orders.index', 'receipt', 'سفارش‌ها'],
            ['admin.check', 'qr-code-scan', 'کنترل بلیط'],
            ['admin.venues.index', 'building', 'سالن‌ها و پلان صندلی'],
            ['admin.categories.index', 'tags', 'دسته‌بندی‌ها'],
            ['admin.sliders.index', 'images', 'اسلایدر'],
            ['admin.pages.index', 'file-text', 'صفحات'],
            ['admin.licenses.index', 'patch-check', 'مجوزهای فوتر'],
            ['admin.users.index', 'people', 'کاربران'],
            ['admin.settings', 'gear', 'تنظیمات'],
        ];
    @endphp
    <aside class="admin-side">
        <div class="brand">{{ setting('site_name') }}</div>
        @foreach($nav as [$r, $icon, $label])
            <a href="{{ route($r) }}" class="{{ request()->routeIs(str_replace(['.index'], '.*', $r)) ? 'active' : '' }}"><i class="bi bi-{{ $icon }}"></i>{{ $label }}</a>
        @endforeach
        <a href="{{ route('home') }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i>مشاهده سایت</a>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-link text-start text-decoration-none d-flex gap-2 px-3" style="color:#c8cae0"><i class="bi bi-power"></i> خروج</button></form>
    </aside>
    <div class="admin-main">
        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </div>
</div>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
