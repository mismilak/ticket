@extends('layouts.admin')
@section('content')
<h1 class="h4 mb-3">داشبورد</h1>
<div class="row g-3 mb-4">@foreach($stats as $label => $val)<div class="col-6 col-lg-4 col-xl-2"><div class="stat"><b>{{ $val }}</b><span>{{ $label }}</span></div></div>@endforeach</div>
<div class="card"><div class="card-header fw-bold">آخرین سفارش‌ها</div>
<div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th>کد</th><th>رویداد</th><th>کاربر</th><th>مبلغ</th><th>وضعیت</th><th></th></tr></thead>
    @foreach($latest as $o)<tr><td>{{ $o->code }}</td><td>{{ $o->event->title }}</td><td dir="ltr" class="text-end">{{ $o->user->mobile }}</td><td>{{ price($o->total) }}</td><td>{{ $o->statusLabel() }}</td><td><a href="{{ route('admin.orders.show', $o) }}">جزئیات</a></td></tr>@endforeach
</table></div></div>
@endsection
