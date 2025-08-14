<footer class="container py-4 text-muted" style="border-top:1px solid #eee; margin-top:2rem;">
    <div class="d-flex justify-content-between flex-wrap gap-2">
        <div>&copy; {{ date('Y') }} DigitalCity</div>
        <div class="d-flex gap-3">
            <a href="{{ url('/sitemap.xml') }}">Sitemap</a>
            <a href="{{ url('/rss.xml') }}">RSS</a>
            <a href="{{ url('/contact') }}">{{ __('messages.contact_us') }}</a>
        </div>
    </div>
</footer>

