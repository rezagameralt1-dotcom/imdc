<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    public function sitemap(): Response
    {
        $posts = Post::where('status','published')->orderByDesc('published_at')->take(200)->get();
        return response()->view('site.feed.sitemap', compact('posts'))->header('Content-Type','application/xml');
    }

    public function rss(): Response
    {
        $posts = Post::where('status','published')->orderByDesc('published_at')->take(50)->get();
        return response()->view('site.feed.rss', compact('posts'))->header('Content-Type','application/rss+xml');
    }
}

