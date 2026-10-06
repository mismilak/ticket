@extends('layouts.app')
@section('title', 'ورود / ثبت‌نام')
@section('content')
<div class="container"><div class="card auth-card"><div class="card-body p-4">
    <h1 class="h4 mb-1">ورود یا ثبت‌نام</h1>
    <p class="text-muted small">شماره موبایل خود را وارد کنید تا کد تایید برایتان پیامک شود.</p>
    <form method="post" action="{{ route('login.send') }}">
        @csrf
        <label class="form-label">شماره موبایل</label>
        <input name="mobile" value="{{ old('mobile') }}" class="form-control form-control-lg text-center @error('mobile') is-invalid @enderror" dir="ltr" inputmode="numeric" placeholder="09123456789" autofocus required>
        @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <button class="btn btn-primary btn-lg w-100 mt-3">دریافت کد تایید</button>
    </form>
</div></div></div>
@endsection
