@extends('layouts.app')

@section('content')
<div class="container" style="max-width:720px;">
    <h1 class="mb-3">ویرایش کاربر #{{ $user->id }}</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul style="margin:0; padding-inline-start:1.2rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('admin.users.update', $user) }}" class="card p-3" style="display:grid; gap:1rem;">
        @csrf
        @method('PUT')

        <div>
            <label class="form-label">نام</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required />
        </div>

        <div>
            <label class="form-label">ایمیل</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required />
        </div>

        <div style="display:flex; gap:1rem; align-items:center;">
            <label style="display:flex; gap:.5rem; align-items:center;">
                <input type="checkbox" name="is_admin" value="1" {{ old('is_admin', $user->is_admin) ? 'checked' : '' }} />
                <span>ادمین</span>
            </label>
            <label style="display:flex; gap:.5rem; align-items:center;">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }} />
                <span>فعال</span>
            </label>
        </div>

        <div style="display:flex; gap:.5rem; justify-content:flex-end;">
            <a href="{{ route('admin.users.index') }}" class="btn">بازگشت</a>
            <button class="btn btn-primary" type="submit">ذخیره تغییرات</button>
        </div>
    </form>
</div>
@endsection

