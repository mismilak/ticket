@extends('layouts.admin')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1 class="h4">{{ $title }}</h1><a class="btn btn-primary" href="{{ route($route.'.create') }}"><i class="bi bi-plus-lg"></i> افزودن {{ $singular }}</a></div>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr>@foreach($columns as $label)<th>{{ $label }}</th>@endforeach<th></th></tr></thead>
    @forelse($items as $item)
    <tr>
        @foreach($columns as $k => $label)<td>{{ $item->$k }}</td>@endforeach
        <td class="text-end text-nowrap">
            @foreach($extraActions as $a)<a class="btn btn-sm btn-outline-secondary" href="{{ route($a['route'], $item) }}"><i class="bi bi-{{ $a['icon'] }}"></i> {{ $a['label'] }}</a>@endforeach
            <a class="btn btn-sm btn-outline-primary" href="{{ route($route.'.edit', $item) }}">ویرایش</a>
            <form method="post" action="{{ route($route.'.destroy', $item) }}" class="d-inline" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">حذف</button></form>
        </td>
    </tr>
    @empty<tr><td colspan="9" class="text-center text-muted py-4">موردی ثبت نشده است.</td></tr>@endforelse
</table></div></div>
<div class="mt-3">{{ $items->links() }}</div>
@endsection
