@extends('site.layouts.site')

@section('content')
<article class="card p-4">
    <h1 class="mb-3">{{ $page->title }}</h1>
    <div class="prose">{!! nl2br(e($page->body)) !!}</div>
</article>
@endsection

