@extends('layouts.app')

@section('content')
<div class="container" style="max-width:720px;">
    <div class="card p-4">
        <h1 class="mb-2">تأیید ایمیل</h1>
        <p class="mb-3">یک لینک تأیید برای ایمیل شما ارسال شد. اگر دریافت نکردید، می‌توانید دوباره درخواست دهید.</p>
        @if (session('status') == 'verification-link-sent')
            <div class="alert alert-success">لینک تأیید جدید به ایمیل شما ارسال شد.</div>
        @endif
        <form method="post" action="{{ route('verification.send') }}" class="mb-2">
            @csrf
            <button class="btn btn-primary" type="submit">ارسال دوباره لینک</button>
        </form>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="btn" type="submit">خروج</button>
        </form>
    </div>
</div>
@endsection

