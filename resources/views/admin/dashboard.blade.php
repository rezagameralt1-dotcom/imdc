@extends('layouts.app')

@section('content')
  <h1>Dashboard</h1>
  <p class="lead">نمای کلی</p>

  <div class="row">
    <a class="card" href="/admin/assets">
      <h3>Assets</h3>
      <p>رسانه‌ها و فایل‌ها</p>
    </a>
    <a class="card" href="/admin/content">
      <h3>Content</h3>
      <p>پست‌ها، صفحات و…</p>
    </a>
    <a class="card" href="/admin/users">
      <h3>Users</h3>
      <p>لیست و مدیریت کاربران</p>
    </a>
  </div>
@endsection


