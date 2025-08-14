@extends('site.layouts.site')

@section('content')
<article class="card p-4">
    <h1 class="mb-2">{{ $post->title }}</h1>
    <div class="text-muted mb-3">{{ $post->published_at?->format('Y-m-d H:i') }}</div>
    @if($post->excerpt)
        <p class="lead">{{ $post->excerpt }}</p>
    @endif
    <div class="prose">{!! nl2br(e($post->body)) !!}</div>
</article>
@endsection

