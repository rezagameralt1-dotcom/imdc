@extends('layouts.app')

@section('content')
<div class="container" style="max-width:920px;">
    <h1 class="mb-3">ایجاد پست جدید</h1>
    <form method="post" action="{{ route('admin.content.posts.store') }}" class="card p-3">
        @csrf
        @include('admin.content.posts._form')
        <div style="display:flex; gap:.5rem; justify-content:flex-end;">
            <a href="{{ route('admin.content.posts.index') }}" class="btn">انصراف</a>
            <button class="btn btn-primary" type="submit">ثبت پست</button>
        </div>
    </form>
</div>
@endsection

