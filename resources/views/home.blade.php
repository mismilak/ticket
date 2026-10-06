@extends('layouts.app')
@section('content')
@if($sliders->count())
<div class="container mt-3">
    <div id="hs" class="carousel slide slider" data-bs-ride="carousel">
        <div class="carousel-inner rounded-ui">
            @foreach($sliders as $s)
                <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                    @if($s->link)<a href="{{ $s->link }}">@endif
                    <img src="{{ upload_url($s->image) }}" alt="{{ $s->title }}">
                    @if($s->title)<div class="carousel-caption"><h4>{{ $s->title }}</h4><p class="mb-0">{{ $s->subtitle }}</p></div>@endif
                    @if($s->link)</a>@endif
                </div>
            @endforeach
        </div>
        @if($sliders->count() > 1)
            <button class="carousel-control-prev" data-bs-target="#hs" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
            <button class="carousel-control-next" data-bs-target="#hs" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
        @endif
    </div>
</div>
@else
<div class="hero text-center">
    <div class="container">
        <h1>{{ setting('hero_title') }}</h1>
        <p class="opacity-75">{{ setting('hero_subtitle') }}</p>
        <form action="{{ route('events.index') }}" class="search"><div class="input-group input-group-lg">
            <input name="q" class="form-control" placeholder="نام کنسرت، تئاتر یا هنرمند..."><button class="btn btn-light"><i class="bi bi-search"></i></button>
        </div></form>
    </div>
</div>
@endif

<div class="container">
    @if($categories->count())
    <div class="row g-3 mt-2 row-cols-3 row-cols-md-5">
        @foreach($categories as $c)
            <div class="col"><a class="cat-chip" href="{{ route('events.index', ['category' => $c->slug]) }}"><i class="bi bi-{{ $c->icon ?: 'ticket-perforated' }}"></i><span>{{ $c->name }}</span></a></div>
        @endforeach
    </div>
    @endif

    @if($featured->count())
        <h2 class="section-title">ویژه‌ها</h2>
        <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-lg-4">@foreach($featured as $event)<div class="col">@include('partials.event-card')</div>@endforeach</div>
    @endif

    <h2 class="section-title">رویدادهای پیش رو</h2>
    @if($events->count())
        <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-lg-4">@foreach($events as $event)<div class="col">@include('partials.event-card')</div>@endforeach</div>
        <div class="text-center mt-4"><a class="btn btn-outline-primary px-5" href="{{ route('events.index') }}">مشاهده همه</a></div>
    @else
        <div class="alert alert-light text-center">در حال حاضر رویدادی ثبت نشده است.</div>
    @endif
</div>
@endsection
