@extends('layouts.app')
@section('title', 'ورود مدیر')
@section('content')
<div class="container"><div class="card auth-card"><div class="card-body p-4">
    <h1 class="h4 mb-3">ورود مدیر</h1>
    <form method="post">
        @csrf
        <label class="form-label">موبایل</label>
        <input name="mobile" value="{{ old('mobile') }}" class="form-control mb-3 @error('mobile') is-invalid @enderror" dir="ltr" required>
        @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <label class="form-label">رمز عبور</label>
        <input type="password" name="password" class="form-control mb-3" dir="ltr" required>
        <button class="btn btn-primary w-100">ورود</button>
    </form>
</div></div></div>
@endsection
