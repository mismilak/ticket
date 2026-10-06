@extends('layouts.admin')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1 class="h4">گزارش فروش: {{ $event->title }}</h1><a href="{{ route('admin.events.index') }}" class="btn btn-light">بازگشت</a></div>
<div class="row g-3 mb-3"><div class="col-6 col-md-3"><div class="stat"><b>{{ fa_digits($items->count()) }}</b><span>بلیط فروخته‌شده</span></div></div>
<div class="col-6 col-md-3"><div class="stat"><b>{{ price($items->sum('price')) }}</b><span>درآمد بلیط</span></div></div>
<div class="col-6 col-md-3"><div class="stat"><b>{{ fa_digits($items->whereNotNull('checked_in_at')->count()) }}</b><span>وارد شده</span></div></div></div>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>کد بلیط</th><th>خریدار</th><th>نوع</th><th>صندلی</th><th>قیمت</th><th>ورود</th></tr></thead>
@foreach($items as $i)<tr><td dir="ltr">{{ $i->ticket_code }}</td><td>{{ $i->order->user->displayName() }} <span class="text-muted small" dir="ltr">{{ $i->order->user->mobile }}</span></td><td>{{ $i->ticketType->name }}</td><td>{{ $i->seatLabel() }}</td><td>{{ price($i->price) }}</td><td>{{ $i->checked_in_at ? jdate($i->checked_in_at, 'time') : '-' }}</td></tr>@endforeach
</table></div></div>
@endsection
