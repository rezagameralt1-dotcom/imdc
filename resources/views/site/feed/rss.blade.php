<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0">
  <channel>
    <title>{{ config('app.name', 'DigitalCity') }} Blog</title>
    <link>{{ route('blog.index') }}</link>
    <description>Latest posts</description>
    @foreach($posts as $p)
    <item>
      <title>{{ $p->title }}</title>
      <link>{{ route('blog.show', $p->slug) }}</link>
      <guid>{{ route('blog.show', $p->slug) }}</guid>
      @if($p->published_at)<pubDate>{{ $p->published_at->toRfc2822String() }}</pubDate>@endif
      @if($p->excerpt)<description><![CDATA[{{ $p->excerpt }}]]></description>@endif
    </item>
    @endforeach
  </channel>
</rss>

