@extends('layouts.app')
@section('title', $category ? $category->name : 'رویدادها')
@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <h1 class="h4 mb-0 me-2">{{ $category ? $category->name : 'همه رویدادها' }}</h1>
        <a class="btn btn-sm {{ ! $category ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('events.index') }}">همه</a>
        @foreach($categories as $c)
            <a class="btn btn-sm {{ $category?->id === $c->id ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('events.index', ['category' => $c->slug]) }}">{{ $c->name }}</a>
        @endforeach
        <a class="btn btn-sm btn-link ms-auto" href="{{ route('events.index', ['past' => request('past') ? null : 1]) }}">{{ request('past') ? 'رویدادهای پیش رو' : 'رویدادهای گذشته' }}</a>
    </div>
    <form class="mb-4" style="max-width:420px"><div class="input-group">
        <input name="q" value="{{ request('q') }}" class="form-control" placeholder="جستجو...">
        @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
        <button class="btn btn-primary"><i class="bi bi-search"></i></button></div></form>
    @if($events->count())
        <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-lg-4">@foreach($events as $event)<div class="col">@include('partials.event-card')</div>@endforeach</div>
        <div class="mt-4">{{ $events->links() }}</div>
    @else
        <div class="alert alert-light text-center">رویدادی یافت نشد.</div>
    @endif
</div>
@endsection
