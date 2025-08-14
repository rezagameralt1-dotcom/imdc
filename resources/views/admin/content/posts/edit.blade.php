@extends('layouts.app')

@section('content')
<div class="container" style="max-width:920px;">
    <h1 class="mb-3">ویرایش پست #{{ $post->id }}</h1>
    <form method="post" action="{{ route('admin.content.posts.update', $post) }}" class="card p-3">
        @csrf
        @method('PUT')
        @include('admin.content.posts._form', ['post'=>$post])
        <div style="display:flex; gap:.5rem; justify-content:flex-end;">
            <a href="{{ route('admin.content.posts.index') }}" class="btn">بازگشت</a>
            <button class="btn btn-primary" type="submit">ذخیره تغییرات</button>
        </div>
    </form>
</div>
@endsection

