@extends('layouts.app')
@section('title', 'حساب کاربری')
@section('content')
<div class="container py-4" style="max-width:560px"><div class="card"><div class="card-body p-4">
    <h1 class="h5 mb-3">اطلاعات حساب</h1>
    <form method="post">
        @csrf
        <div class="mb-3"><label class="form-label">موبایل</label><input class="form-control" value="{{ $user->mobile }}" disabled dir="ltr"></div>
        <div class="mb-3"><label class="form-label">نام و نام خانوادگی</label><input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label">ایمیل (اختیاری)</label><input name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" dir="ltr"></div>
        <button class="btn btn-primary">ذخیره</button>
    </form>
</div></div></div>
@endsection
