@php
  $is = fn($p) => request()->is($p) ? 'active' : '';
@endphp

<nav class="nav" role="navigation" aria-label="Main">
  <a href="/me" class="{{ $is('me') }}">Me</a>
  <a href="/admin/dashboard" class="{{ $is('admin*') }}">Admin</a>
  <a href="/" class="{{ $is('/') }}">SPA Auth Demo</a>
  <a href="/ui" class="{{ $is('ui') }}">UI</a>
  <span class="right"></span>
</nav>
---

