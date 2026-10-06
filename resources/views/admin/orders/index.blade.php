@extends('layouts.admin')
@section('content')
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2"><h1 class="h4">سفارش‌ها</h1>
<form class="d-flex gap-2"><select name="status" class="form-select form-select-sm"><option value="">همه</option>@foreach(\App\Models\Order::STATUS as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select>
<input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="کد یا موبایل"><button class="btn btn-sm btn-primary">فیلتر</button></form></div>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
<thead><tr><th>کد</th><th>رویداد</th><th>کاربر</th><th>مبلغ</th><th>درگاه</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
@foreach($orders as $o)<tr><td>{{ $o->code }}</td><td>{{ $o->event->title }}</td><td dir="ltr" class="text-end">{{ $o->user->mobile }}</td><td>{{ price($o->total) }}</td><td>{{ $o->gateway }}</td><td>{{ $o->statusLabel() }}</td><td>{{ jdate($o->created_at, 'date') }}</td><td><a href="{{ route('admin.orders.show', $o) }}">جزئیات</a></td></tr>@endforeach
</table></div></div><div class="mt-3">{{ $orders->links() }}</div>
@endsection
