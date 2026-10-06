@extends('layouts.admin')
@section('content')
<h1 class="h4 mb-3">کنترل و ورود بلیط</h1>
<form class="mb-4 d-flex gap-2" style="max-width:480px"><input name="code" value="{{ $code }}" class="form-control form-control-lg" dir="ltr" placeholder="کد بلیط" autofocus><button class="btn btn-primary">بررسی</button></form>
@if($item)
<div class="card" style="max-width:560px"><div class="card-body">
    <h2 class="h5">{{ $item->order->event->title }}</h2>
    <div class="mb-1">{{ jdate($item->order->event->starts_at) }}</div>
    <div class="mb-1">{{ $item->ticketType->name }} @if($item->seatLabel())— <b>{{ $item->seatLabel() }}</b>@endif</div>
    <div class="mb-3">دارنده: {{ $item->order->user->displayName() }} ({{ $item->order->user->mobile }})</div>
    @if($item->checked_in_at)
        <div class="alert alert-danger mb-0">⚠ این بلیط قبلاً در {{ jdate($item->checked_in_at) }} استفاده شده است.</div>
    @else
        <div class="alert alert-success">بلیط معتبر است.</div>
        <form method="post" action="{{ route('admin.check.in', $item) }}">@csrf<button class="btn btn-success">ثبت ورود</button></form>
    @endif
</div></div>
@endif
@endsection
