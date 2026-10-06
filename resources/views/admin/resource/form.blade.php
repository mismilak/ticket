@extends('layouts.admin')
@section('content')
<h1 class="h4 mb-3">{{ $title }}</h1>
<form method="post" enctype="multipart/form-data" class="card" action="{{ $item->exists ? route($route.'.update', $item) : route($route.'.store') }}"><div class="card-body">
    @csrf @if($item->exists) @method('PUT') @endif
    @include('admin.partials.fields', ['fields' => $fields, 'values' => $item])
    <button class="btn btn-primary">ذخیره</button> <a class="btn btn-light" href="{{ route($route.'.index') }}">بازگشت</a>
</div></form>
@endsection
