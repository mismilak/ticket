@extends('layouts.app')
@section('title', 'سفارش '.$order->code)
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h5 mb-0">سفارش {{ $order->code }}
            <span class="badge bg-{{ ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger'][$order->status] ?? 'secondary' }}">{{ $order->statusLabel() }}</span></h1>
        @if($order->status === 'paid')<button class="btn btn-outline-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> چاپ بلیط</button>@endif
    </div>

    @if($order->isPending())
        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>پرداخت این سفارش تکمیل نشده است. تا {{ jdate($order->expires_at, 'time') }} فرصت دارید.</span>
            <form method="post" action="{{ route('orders.pay', $order) }}" class="d-flex gap-2">@csrf
                <select name="gateway" class="form-select form-select-sm">@foreach(\App\Payment\Gateway::active() as $k => $t)<option value="{{ $k }}" @selected($k === $order->gateway)>{{ $t }}</option>@endforeach</select>
                <button class="btn btn-primary btn-sm text-nowrap">پرداخت {{ price($order->total) }}</button></form>
        </div>
    @endif

    @if($order->status === 'paid')
        @foreach($order->items as $item)
            <div class="ticket {{ $item->checked_in_at ? 'used' : '' }}">
                <div class="main">
                    <div class="small text-muted">{{ setting('site_name') }}</div>
                    <h2 class="h5 fw-bold">{{ $order->event->title }}</h2>
                    <div class="small mb-1"><i class="bi bi-calendar-event text-primary"></i> {{ jdate($order->startsAt()) }}</div>
                    <div class="small mb-1"><i class="bi bi-geo-alt text-primary"></i> {{ $order->event->venueLabel() }}</div>
                    <div class="small mb-1"><i class="bi bi-ticket text-primary"></i> {{ $item->ticketType->name }}@if($item->seatLabel()) · <b>{{ $item->seatLabel() }}</b> ({{ $item->seat->level->name }})@endif</div>
                    <div class="small"><i class="bi bi-person text-primary"></i> {{ $order->user->displayName() }}</div>
                    @if($item->checked_in_at)<div class="badge bg-secondary mt-2">استفاده شده</div>@endif
                </div>
                <div class="stub">
                    <div class="qr" data-code="{{ $item->ticket_code }}"></div>
                    <div class="fw-bold mt-2" dir="ltr">{{ $item->ticket_code }}</div>
                </div>
            </div>
        @endforeach
    @else
        <div class="card"><div class="card-body">
            @foreach($order->items as $item)
                <div class="d-flex justify-content-between py-1 border-bottom"><span>{{ $item->ticketType->name }} @if($item->seatLabel())— {{ $item->seatLabel() }}@endif</span><span>{{ price($item->price) }}</span></div>
            @endforeach
            <div class="d-flex justify-content-between fw-bold pt-2"><span>جمع</span><span>{{ price($order->total) }}</span></div>
        </div></div>
    @endif
    @if($order->status === 'paid')
        <div class="card mt-3"><div class="card-body small">
            <b>مبلغ پرداختی:</b> {{ price($order->total) }} ·
            @php $pay = $order->payments->firstWhere('status', 'paid'); @endphp
            @if($pay)<b>کد پیگیری بانک:</b> {{ $pay->ref_id }} · @endif
            <b>تاریخ:</b> {{ jdate($order->paid_at) }}
        </div></div>
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/qrcode/qrcode.js') }}"></script>
<script>
document.querySelectorAll('.qr').forEach(e => { const q = qrcode(0, 'M'); q.addData(e.dataset.code); q.make(); e.innerHTML = q.createSvgTag({ cellSize: 4, margin: 0, scalable: true }); e.style.width = '120px'; });
</script>
@endpush
