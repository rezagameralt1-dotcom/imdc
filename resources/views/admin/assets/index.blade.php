@extends('layouts.app')

@section('content')
  <h1>Assets</h1>
  <p class="lead">کتابخانه فایل‌ها</p>

  <div class="kit">
    <a class="btn" href="#">Upload</a>
    <a class="btn secondary" href="#">Create Folder</a>
  </div>

  <div class="row" style="margin-top:10px">
    <div class="card"><h3>images/</h3><p>23 files</p></div>
    <div class="card"><h3>docs/</h3><p>8 files</p></div>
    <div class="card"><h3>videos/</h3><p>2 files</p></div>
  </div>
@endsection


