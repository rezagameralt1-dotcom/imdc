<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>{{ url('/') }}</loc></url>
  <url><loc>{{ route('blog.index') }}</loc></url>
  @foreach($posts as $p)
    <url>
      <loc>{{ route('blog.show', $p->slug) }}</loc>
      @if($p->updated_at)
      <lastmod>{{ $p->updated_at->toAtomString() }}</lastmod>
      @endif
      <changefreq>weekly</changefreq>
      <priority>0.7</priority>
    </url>
  @endforeach
</urlset>

