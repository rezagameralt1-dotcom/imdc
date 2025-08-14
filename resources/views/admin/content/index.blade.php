@extends('layouts.app')

@section('content')
  <h1>Content</h1>
  <p class="lead">پست‌ها و صفحات</p>

  <div class="kit">
    <a class="btn" href="#">+ New Post</a>
    <a class="btn secondary" href="#">+ New Page</a>
  </div>

  <table class="table">
    <thead><tr><th>Title</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <tr><td>Welcome</td><td>Page</td><td><span class="badge green">Published</span></td><td><a class="btn secondary" href="#">Edit</a></td></tr>
      <tr><td>Roadmap</td><td>Post</td><td><span class="badge amber">Draft</span></td><td><a class="btn secondary" href="#">Edit</a></td></tr>
    </tbody>
  </table>
@endsection
---

