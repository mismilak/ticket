@extends('layouts.admin')
@section('content')
<h1 class="h4 mb-3">تنظیمات سایت</h1>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.settings.update') }}">
    @csrf
    <ul class="nav nav-tabs mb-3">
        @foreach($groups as $gk => $g)<li class="nav-item"><button type="button" class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#t_{{ $gk }}">{{ $g['title'] }}</button></li>@endforeach
    </ul>
    <div class="tab-content card"><div class="card-body">
        @foreach($groups as $gk => $g)
        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="t_{{ $gk }}">
            @php
                $vals = collect($g['fields'])->map(fn ($f, $k) => $f['type'] === 'password' ? (setting($k) ? '*' : null) : \App\Models\Setting::get($k))->all();
            @endphp
            @include('admin.partials.fields', ['fields' => $g['fields'], 'values' => $vals])
            @if($gk === 'gateways')
                <div class="alert alert-info small">آدرس بازگشت (Callback) به‌صورت خودکار برای هر تراکنش ساخته می‌شود؛ فقط کافی است دامنه سایت در پنل بانک/زرین‌پال ثبت شده باشد.</div>
            @endif
        </div>
        @endforeach
        <button class="btn btn-primary">ذخیره تنظیمات</button>
    </div></div>
</form>
@endsection
