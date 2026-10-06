@extends('layouts.app')
@section('title', 'بلیط‌های من')
@section('content')
<div class="container py-4">
    <h1 class="h4 mb-3">بلیط‌های من</h1>
    @forelse($orders as $o)
        <a href="{{ route('orders.show', $o) }}" class="card mb-2 text-decoration-none text-body"><div class="card-body d-flex gap-3 align-items-center flex-wrap">
            <img src="{{ $o->event->posterUrl() }}" class="thumb" alt="">
            <div class="flex-grow-1"><div class="fw-bold">{{ $o->event->title }}</div><div class="small text-muted">{{ jdate($o->event->starts_at) }} · سفارش {{ $o->code }}</div></div>
            <div class="text-end"><div class="fw-bold">{{ price($o->total) }}</div>
                <span class="badge bg-{{ ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger'][$o->status] ?? 'secondary' }}">{{ $o->statusLabel() }}</span></div>
        </div></a>
    @empty
        <div class="alert alert-light text-center">هنوز سفارشی ندارید.</div>
    @endforelse
    {{ $orders->links() }}
</div>
@endsection
