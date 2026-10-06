@extends('layouts.admin')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1 class="h4">سفارش {{ $order->code }} — {{ $order->statusLabel() }}</h1>
@if(in_array($order->status, ['paid', 'pending']))<form method="post" action="{{ route('admin.orders.cancel', $order) }}" onsubmit="return confirm('سفارش لغو شود؟')">@csrf<button class="btn btn-outline-danger">لغو سفارش</button></form>@endif</div>
<div class="row g-3">
<div class="col-lg-7"><div class="card"><div class="card-header fw-bold">{{ $order->event->title }}</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>نوع</th><th>صندلی</th><th>قیمت</th><th>کد بلیط</th><th>ورود</th></tr></thead>
@foreach($order->items as $i)<tr><td>{{ $i->ticketType->name }}</td><td>{{ $i->seatLabel() }}</td><td>{{ price($i->price) }}</td><td dir="ltr">{{ $i->ticket_code }}</td><td>{{ $i->checked_in_at ? jdate($i->checked_in_at, 'time') : '-' }}</td></tr>@endforeach
</table></div><div class="card-footer fw-bold">جمع: {{ price($order->total) }}</div></div></div>
<div class="col-lg-5"><div class="card mb-3"><div class="card-body small"><b>کاربر:</b> {{ $order->user->displayName() }} ({{ $order->user->mobile }})<br><b>ایجاد:</b> {{ jdate($order->created_at) }}<br><b>پرداخت:</b> {{ $order->paid_at ? jdate($order->paid_at) : '-' }}</div></div>
<div class="card"><div class="card-header">تراکنش‌ها</div><ul class="list-group list-group-flush small">
@forelse($order->payments as $p)<li class="list-group-item">{{ $p->gateway }} · {{ $p->status }} · {{ price($p->amount) }}<br>مرجع: {{ $p->ref_id ?: '-' }} · کارت: {{ $p->card_pan ?: '-' }}@if($p->message)<br><span class="text-danger">{{ $p->message }}</span>@endif</li>@empty<li class="list-group-item">—</li>@endforelse</ul></div></div>
</div>
@endsection
