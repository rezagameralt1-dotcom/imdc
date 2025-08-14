@extends('site.layouts.site')

@section('content')
<h1 class="mb-3">بلاگ</h1>

<form method="get" class="mb-3" style="display:flex; gap:.5rem;">
    <input type="text" name="q" value="{{ $q }}" placeholder="جست‌وجوی عنوان/متن" class="form-control" />
    <button class="btn btn-primary" type="submit">جست‌وجو</button>
    @if($q)
        <a href="{{ route('blog.index') }}" class="btn">حذف فیلتر</a>
    @endif
</form>

@forelse($posts as $p)
    <div class="card p-3 mb-3">
        <h3 style="margin:0 0 .25rem 0;">
            <a href="{{ route('blog.show', $p->slug) }}">{{ $p->title }}</a>
        </h3>
        <div class="text-muted" style="font-size:.9rem;">
            {{ $p->published_at?->format('Y-m-d H:i') }}
        </div>
        @if($p->excerpt)
            <p style="margin:.5rem 0 0 0;">{{ $p->excerpt }}</p>
        @endif
    </div>
@empty
    <div class="card p-3"><em>پستی یافت نشد.</em></div>
@endforelse

<div class="mt-3">
    {{ $posts->links() }}
</div>
@endsection

