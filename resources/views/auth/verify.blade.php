@extends('layouts.app')
@section('title', 'تایید شماره موبایل')
@section('content')
<div class="container"><div class="card auth-card"><div class="card-body p-4">
    <h1 class="h4 mb-1">کد تایید</h1>
    <p class="text-muted small">کد ارسال‌شده به <b dir="ltr">{{ fa_digits($mobile) }}</b> را وارد کنید.</p>
    @if($dev)<div class="alert alert-warning small">حالت توسعه (API پیامک تنظیم نشده): کد شما <b>{{ $dev }}</b></div>@endif
    <form method="post" action="{{ route('login.check') }}">
        @csrf
        <input name="code" class="form-control form-control-lg otp-input @error('code') is-invalid @enderror" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <button class="btn btn-primary btn-lg w-100 mt-3">ورود</button>
    </form>
    <div class="d-flex justify-content-between mt-3 small">
        <a href="{{ route('login') }}">ویرایش شماره</a>
        <form method="post" action="{{ route('login.send') }}">@csrf<input type="hidden" name="mobile" value="{{ $mobile }}"><button class="btn btn-link btn-sm p-0">ارسال مجدد کد</button></form>
    </div>
</div></div></div>
@endsection
