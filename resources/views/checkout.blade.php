@extends('layouts.app')
@section('title', 'تکمیل خرید')
@section('content')
<div class="container py-4"><div class="row g-4">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h1 class="h5 mb-3">مرور سفارش</h1>
            <div class="d-flex gap-3 mb-3"><img src="{{ $event->posterUrl() }}" class="thumb" alt=""><div><div class="fw-bold">{{ $event->title }}</div><div class="small text-muted">{{ jdate($event->starts_at) }}<br>{{ $event->venueLabel() }}</div></div></div>
            @foreach($lines as $l)<div class="d-flex justify-content-between py-2 border-top small"><span>{{ $l['title'] }}</span><span>{{ price($l['price']) }}</span></div>@endforeach
            @if($fee)<div class="d-flex justify-content-between py-2 border-top small"><span>کارمزد خدمات</span><span>{{ price($fee) }}</span></div>@endif
            <div class="d-flex justify-content-between py-2 border-top fw-bold fs-5"><span>مبلغ قابل پرداخت</span><span class="text-primary">{{ price($total) }}</span></div>
            <a href="{{ route('events.show', $event) }}" class="small">تغییر انتخاب</a>
        </div></div>
    </div>
    <div class="col-lg-5">
        <form method="post" action="{{ route('checkout.store') }}" class="card"><div class="card-body">
            @csrf
            <h2 class="h5 mb-3">انتخاب درگاه پرداخت</h2>
            @forelse($gateways as $key => $title)
                <label class="d-flex align-items-center gap-2 border rounded-ui p-3 mb-2" style="cursor:pointer">
                    <input type="radio" name="gateway" value="{{ $key }}" class="form-check-input mt-0" @checked(old('gateway', setting('default_gateway')) === $key || $loop->first && ! array_key_exists(setting('default_gateway'), $gateways)) required>
                    <span class="fw-bold">{{ $title }}</span>
                </label>
            @empty
                <div class="alert alert-danger">هیچ درگاه پرداختی فعال نیست. لطفاً با پشتیبانی تماس بگیرید.</div>
            @endforelse
            @if(setting('terms'))
                <div class="small border rounded p-2 mb-2" style="max-height:120px;overflow:auto;white-space:pre-line">{{ setting('terms') }}</div>
                <label class="small d-block mb-2"><input type="checkbox" name="accept" value="1" class="form-check-input" required> قوانین را خوانده و می‌پذیرم</label>
            @endif
            <div class="small text-muted mb-3">پس از ثبت، {{ fa_digits(setting('hold_minutes')) }} دقیقه برای پرداخت فرصت دارید.</div>
            <button class="btn btn-primary w-100 py-2" @disabled(! $gateways)>پرداخت {{ price($total) }}</button>
        </div></form>
    </div>
</div></div>
@endsection
