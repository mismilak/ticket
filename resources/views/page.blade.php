@extends('layouts.app')
@section('title', $page->title)
@section('content')
<div class="container py-4"><div class="card"><div class="card-body p-4">
    <h1 class="h3 mb-3">{{ $page->title }}</h1>
    <div class="page-body">{!! $page->body !!}</div>
</div></div></div>
@endsection
