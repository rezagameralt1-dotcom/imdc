<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Page;
use Illuminate\Http\Request;

class PublicApiController extends Controller
{
    public function search(Request $request)
    {
        $q = trim((string) $request->get('q',''));
        $posts = Post::where('status','published')
            ->when($q !== '', fn($qr)=>$qr->where(function($w) use ($q){
                $w->where('title','like',"%$q%")->orWhere('body','like',"%$q%");
            }))
            ->orderByDesc('published_at')
            ->limit(20)
            ->get(['id','title','slug','excerpt','published_at']);

        $pages = Page::when($q !== '', fn($qr)=>$qr->where(function($w) use ($q){
                $w->where('title','like',"%$q%")->orWhere('body','like',"%$q%");
            }))
            ->orderBy('title')
            ->limit(20)
            ->get(['id','title','slug']);

        return response()->json(['q'=>$q, 'posts'=>$posts, 'pages'=>$pages]);
    }
}

