@extends('layouts.admin')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1 class="h4">کاربران</h1>
<form class="d-flex gap-2"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="موبایل یا نام"><button class="btn btn-sm btn-primary">جستجو</button></form></div>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
<thead><tr><th>نام</th><th>موبایل</th><th>سفارش</th><th>عضویت</th><th>نقش / رمز عبور</th></tr></thead>
@foreach($users as $u)<tr><td>{{ $u->name }}</td><td dir="ltr" class="text-end">{{ $u->mobile }}</td><td>{{ fa_digits($u->orders_count) }}</td><td>{{ jdate($u->created_at, 'date') }}</td>
<td><form method="post" action="{{ route('admin.users.update', $u) }}" class="d-flex gap-2">@csrf @method('PUT')
<select name="role" class="form-select form-select-sm" style="width:110px"><option value="customer" @selected($u->role === 'customer')>کاربر</option><option value="admin" @selected($u->role === 'admin')>مدیر</option></select>
<input name="password" class="form-control form-control-sm" placeholder="رمز جدید (مدیر)" dir="ltr" style="width:150px"><button class="btn btn-sm btn-outline-primary">ذخیره</button></form></td></tr>@endforeach
</table></div></div><div class="mt-3">{{ $users->links() }}</div>
@endsection
