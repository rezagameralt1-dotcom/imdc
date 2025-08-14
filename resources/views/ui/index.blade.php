@extends('layouts.app')

@section('content')
  <h1>UI Preview</h1>
  <p class="lead">کتابخانه عناصر پایه برای شروع سریع.</p>

  <div class="kit">
    <a href="/admin/dashboard" class="btn">رفتن به داشبورد</a>
    <a href="/" class="btn secondary">Auth Demo</a>
    <button class="btn success" type="button">Success</button>
    <button class="btn warn" type="button">Warn</button>
    <button class="btn danger" type="button">Danger</button>
  </div>

  <div class="row" style="margin-top:18px">
    <div class="card">
      <h3>Card One</h3>
      <p>نمونه کارت ساده</p>
    </div>
    <div class="card">
      <h3>Card Two</h3>
      <p>برای سکشن‌های داشبورد</p>
    </div>
    <div class="card">
      <h3>Card Three</h3>
      <p>لِی‌اوت واکنش‌گرا</p>
    </div>
  </div>

  <h2 style="margin-top:24px">فرم سریع</h2>
  <form class="inline-form" onsubmit="return false;">
    <input class="input" placeholder="جستجو..." />
    <select class="select">
      <option>All</option><option>Draft</option><option>Published</option>
    </select>
    <button class="btn">اعمال</button>
  </form>

  <h2 style="margin-top:24px">Sample Data Table</h2>
  <table class="table">
    <thead><tr><th>ID</th><th>Title</th><th>Status</th></tr></thead>
    <tbody>
      <tr><td>1</td><td>Item A</td><td><span class="badge amber">Draft</span></td></tr>
      <tr><td>2</td><td>Item B</td><td><span class="badge green">Published</span></td></tr>
      <tr><td>3</td><td>Item C</td><td><span class="badge red">Archived</span></td></tr>
    </tbody>
  </table>

  <h2 style="margin-top:24px">Result Box</h2>
  <div class="panel mono" id="resultBox">{ }</div>
@endsection
---

