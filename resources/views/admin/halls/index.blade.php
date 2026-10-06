@extends('layouts.admin')
@section('content')
<h1 class="h4 mb-3">پلان‌های سالن «{{ $venue->name }}»</h1>
<form method="post" action="{{ route('admin.halls.store', $venue) }}" class="card mb-3"><div class="card-body d-flex gap-2">@csrf
<input name="name" class="form-control" placeholder="نام سالن / پلان (مثلاً سالن اصلی)" required><button class="btn btn-primary text-nowrap">ساخت پلان جدید</button></div></form>
<div class="card"><table class="table mb-0 align-middle">@forelse($halls as $h)<tr><td>{{ $h->name }}</td><td>{{ fa_digits($h->levels_count) }} طبقه</td><td class="text-end">
<a class="btn btn-sm btn-primary" href="{{ route('admin.halls.designer', $h) }}"><i class="bi bi-pencil-square"></i> طراحی چیدمان صندلی‌ها</a>
<form method="post" class="d-inline" action="{{ route('admin.halls.destroy', $h) }}" onsubmit="return confirm('حذف پلان؟')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">حذف</button></form></td></tr>
@empty<tr><td class="text-center text-muted py-4">پلانی ثبت نشده است.</td></tr>@endforelse</table></div>
<a href="{{ route('admin.venues.index') }}" class="btn btn-link mt-2">بازگشت</a>
@endsection
