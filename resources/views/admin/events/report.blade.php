@extends('layouts.admin')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1 class="h4">گزارش فروش: {{ $event->title }}</h1><a href="{{ route('admin.events.index') }}" class="btn btn-light">بازگشت</a></div>
<form class="mb-3 d-flex gap-2" style="max-width:420px"><select name="session" class="form-select" onchange="this.form.submit()"><option value="">همه سانس‌ها</option>@foreach($event->sessions as $s)<option value="{{ $s->id }}" @selected(request('session') == $s->id)>{{ jdate($s->starts_at) }}</option>@endforeach</select></form>
<div class="row g-3 mb-3"><div class="col-6 col-md-3"><div class="stat"><b>{{ fa_digits($items->count()) }}</b><span>بلیط فروخته‌شده</span></div></div>
<div class="col-6 col-md-3"><div class="stat"><b>{{ price($items->sum('price')) }}</b><span>درآمد بلیط</span></div></div>
<div class="col-6 col-md-3"><div class="stat"><b>{{ fa_digits($items->whereNotNull('checked_in_at')->count()) }}</b><span>وارد شده</span></div></div></div>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>کد بلیط</th><th>خریدار</th><th>سانس</th><th>نوع</th><th>صندلی</th><th>قیمت</th><th>ورود</th></tr></thead>
@foreach($items as $i)<tr><td dir="ltr">{{ $i->ticket_code }}</td><td>{{ $i->order->user->displayName() }} <span class="text-muted small" dir="ltr">{{ $i->order->user->mobile }}</span></td><td>{{ jdate($i->order->startsAt(), 'short') }} {{ jdate($i->order->startsAt(), 'time') }}</td><td>{{ $i->ticketType->name }}</td><td>{{ $i->seatLabel() }}</td><td>{{ price($i->price) }}</td><td>{{ $i->checked_in_at ? jdate($i->checked_in_at, 'time') : '-' }}</td></tr>@endforeach
</table></div></div>
@endsection
