<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostSlug;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $posts = Post::where('status', 'published')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('title', 'like', "%$q%")
                        ->orWhere('excerpt', 'like', "%$q%")
                        ->orWhere('body', 'like', "%$q%");
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
        $metaTitle = $q ? "بلاگ - جست‌وجو: $q" : 'بلاگ';
        $metaDescription = 'آخرین مطالب منتشرشده';

        return view('site.blog.index', compact('posts', 'q', 'metaTitle', 'metaDescription'));
    }

    public function show(string $slug)
    {
        $post = Post::where('slug', $slug)->where('status', 'published')->first();
        if (! $post) {
            $old = PostSlug::where('slug', $slug)->first();
            if ($old && $old->post) {
                return redirect()->route('blog.show', $old->post->slug, 301);
            }
            abort(404);
        }
        $metaTitle = $post->title;
        $metaDescription = $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 150);

        return view('site.blog.show', compact('post','metaTitle','metaDescription'));
    }
}
