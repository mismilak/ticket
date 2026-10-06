@extends('layouts.admin')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1 class="h4">رویدادها</h1><a class="btn btn-primary" href="{{ route('admin.events.create') }}"><i class="bi bi-plus-lg"></i> رویداد جدید</a></div>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
<thead><tr><th></th><th>عنوان</th><th>تاریخ</th><th>وضعیت</th><th>سفارش پرداخت‌شده</th><th></th></tr></thead>
@foreach($events as $e)<tr><td><img src="{{ $e->posterUrl() }}" class="thumb"></td><td>{{ $e->title }}<div class="small text-muted">{{ $e->category?->name }}</div></td><td>{{ jdate($e->starts_at) }}</td>
<td><span class="badge bg-{{ $e->status === 'published' ? 'success' : 'secondary' }}">{{ $e->status === 'published' ? 'منتشر شده' : 'پیش‌نویس' }}</span></td><td>{{ fa_digits($e->paid_orders) }}</td>
<td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="{{ route('events.show', $e) }}" target="_blank">مشاهده</a>
<a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.events.report', $e) }}">گزارش</a>
<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.events.edit', $e) }}">ویرایش</a>
<form method="post" class="d-inline" action="{{ route('admin.events.destroy', $e) }}" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">حذف</button></form></td></tr>@endforeach
</table></div></div><div class="mt-3">{{ $events->links() }}</div>
@endsection
