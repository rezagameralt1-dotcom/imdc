@extends('layouts.app')

@section('content')
<div class="container" style="max-width:720px;">
    <h1 class="mb-3">پروفایل من</h1>
    <form action="{{ route('profile.update') }}" method="post" class="card p-3" style="display:grid; gap:1rem;">
        @csrf
        @method('PUT')

        <div>
            <label class="form-label">نام</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
        </div>

        <div>
            <label class="form-label">ایمیل</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
        </div>

        <div class="card p-2">
            <strong class="mb-2">تغییر رمز عبور (اختیاری)</strong>
            <div style="display:grid; gap:.75rem;">
                <input type="password" name="password" class="form-control" placeholder="رمز جدید (حداقل ۸ کاراکتر)">
                <input type="password" name="password_confirmation" class="form-control" placeholder="تکرار رمز جدید">
            </div>
        </div>

        <div style="display:flex; gap:.5rem; justify-content:flex-end;">
            <a class="btn" href="{{ url()->previous() }}">بازگشت</a>
            <button class="btn btn-primary" type="submit">ذخیره</button>
        </div>
    </form>
</div>
@endsection

