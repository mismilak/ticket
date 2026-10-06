<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', setting('site_name')) @hasSection('title') | {{ setting('site_name') }} @endif</title>
    <meta name="description" content="@yield('description', setting('meta_description', setting('site_tagline')))">
    @if(setting('favicon'))<link rel="icon" href="{{ upload_url(setting('favicon')) }}">@endif
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>:root{--c-primary:{{ setting('color_primary') }};--c-secondary:{{ setting('color_secondary') }};--c-bg:{{ setting('color_bg') }};--c-header:{{ setting('color_header') }};--c-footer:{{ setting('color_footer') }};--radius:{{ (int) setting('radius') }}px}{!! setting('custom_css') !!}</style>
    {!! setting('head_html') !!}
    @stack('head')
</head>
<body>
<header class="site-header">
    <div class="container d-flex align-items-center gap-3 py-2 flex-wrap">
        <a href="{{ route('home') }}" class="brand">
            @if(setting('logo'))<img src="{{ upload_url(setting('logo')) }}" alt="{{ setting('site_name') }}">@else{{ setting('site_name') }}@endif
        </a>
        <nav class="site-nav d-none d-lg-flex gap-1 flex-grow-1">
            <a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.index') && ! request('category') ? 'active' : '' }}">همه رویدادها</a>
            @foreach(\App\Models\Category::where('is_active', true)->orderBy('sort')->limit(6)->get() as $c)
                <a href="{{ route('events.index', ['category' => $c->slug]) }}" class="{{ request('category') === $c->slug ? 'active' : '' }}">{{ $c->name }}</a>
            @endforeach
        </nav>
        <form action="{{ route('events.index') }}" class="d-none d-md-flex ms-auto" style="min-width:230px">
            <div class="input-group input-group-sm">
                <input name="q" value="{{ request('q') }}" class="form-control" placeholder="جستجوی رویداد...">
                <button class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
            </div>
        </form>
        <div class="d-flex gap-2 align-items-center ms-auto ms-md-0">
            @auth
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person"></i> {{ auth()->user()->displayName() }}</button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('orders') }}">بلیط‌های من</a></li>
                        <li><a class="dropdown-item" href="{{ route('profile') }}">حساب کاربری</a></li>
                        @if(auth()->user()->isAdmin())<li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">پنل مدیریت</a></li>@endif
                        <li><hr class="dropdown-divider"></li>
                        <li><form method="post" action="{{ route('logout') }}">@csrf<button class="dropdown-item">خروج</button></form></li>
                    </ul>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn btn-sm btn-primary"><i class="bi bi-box-arrow-in-left"></i> ورود / ثبت‌نام</a>
            @endauth
        </div>
    </div>
</header>

@if(session('status'))<div class="container mt-3"><div class="alert alert-success mb-0">{{ session('status') }}</div></div>@endif
@if(session('error'))<div class="container mt-3"><div class="alert alert-danger mb-0">{{ session('error') }}</div></div>@endif

<main>@yield('content')</main>

@php
    $footerPages = \App\Models\Page::where('is_active', true)->where('show_in_footer', true)->orderBy('sort')->get();
    $licenses = \App\Models\License::where('is_active', true)->orderBy('sort')->get()->filter(fn ($l) => $l->image || trim((string) $l->embed_code));
@endphp
<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h5>{{ setting('site_name') }}</h5>
                <p class="small">{{ setting('footer_about', setting('site_tagline')) }}</p>
                <div class="social">
                    @foreach(['instagram' => 'instagram', 'telegram' => 'telegram', 'whatsapp' => 'whatsapp', 'aparat' => 'play-circle'] as $k => $icon)
                        @if(setting($k))<a href="{{ setting($k) }}" target="_blank" rel="noopener"><i class="bi bi-{{ $icon }}"></i></a>@endif
                    @endforeach
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <h5>دسترسی سریع</h5>
                <ul>
                    <li><a href="{{ route('events.index') }}">همه رویدادها</a></li>
                    @foreach($footerPages as $p)<li><a href="{{ route('page', $p->slug) }}">{{ $p->title }}</a></li>@endforeach
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h5>تماس با ما</h5>
                <ul class="small">
                    @if(setting('support_phone'))<li><i class="bi bi-telephone"></i> {{ fa_digits(setting('support_phone')) }}</li>@endif
                    @if(setting('support_email'))<li><i class="bi bi-envelope"></i> {{ setting('support_email') }}</li>@endif
                    @if(setting('address'))<li><i class="bi bi-geo-alt"></i> {{ setting('address') }}</li>@endif
                </ul>
            </div>
            <div class="col-lg-3">
                <h5>مجوزها</h5>
                <div class="licenses">
                    @foreach($licenses as $l)
                        <div class="lic" title="{{ $l->title }}">
                            @if(trim((string) $l->embed_code)){!! $l->embed_code !!}
                            @elseif($l->link)<a href="{{ $l->link }}" target="_blank" rel="noopener"><img src="{{ upload_url($l->image) }}" alt="{{ $l->title }}"></a>
                            @else<img src="{{ upload_url($l->image) }}" alt="{{ $l->title }}">@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="copy">© {{ explode('/', jdate(now(), 'date'))[0] }} — {{ setting('copyright') }}</div>
    </div>
</footer>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
