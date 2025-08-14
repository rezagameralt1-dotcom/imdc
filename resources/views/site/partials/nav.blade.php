<nav class="navbar" style="display:flex; gap:1rem; padding:.75rem 1rem; border-bottom:1px solid var(--border,#e5e7eb);">
    <a href="{{ url('/') }}" class="btn">خانه</a>
    <a href="{{ route('blog.index') }}" class="btn">بلاگ</a>
    <span style="flex:1"></span>
    @auth
        <a href="{{ route('admin.dashboard') }}" class="btn">ادمین</a>
    @endauth
</nav>

