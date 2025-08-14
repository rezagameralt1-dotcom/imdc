<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\Request;

class PublicApiV2Controller extends Controller
{
    public function posts(Request $request)
    {
        $q = Post::query()->where('status', 'published')
            ->when($request->filled('q'), function ($qq) use ($request) {
                $term = $request->query('q');
                $qq->where(fn ($w) => $w->where('title', 'ilike', "%{$term}%")
                    ->orWhere('body', 'ilike', "%{$term}%"));
            })
            ->latest('published_at');

        $per = (int) min(max((int) $request->query('per_page', 10), 1), 50);

        return response()->json($q->paginate($per));
    }

    public function pages(Request $request)
    {
        $q = Page::query()->where('status', 'published')
            ->when($request->filled('q'), function ($qq) use ($request) {
                $term = $request->query('q');
                $qq->where(fn ($w) => $w->where('title', 'ilike', "%{$term}%")
                    ->orWhere('body', 'ilike', "%{$term}%"));
            })
            ->latest('published_at');

        $per = (int) min(max((int) $request->query('per_page', 10), 1), 50);

        return response()->json($q->paginate($per));
    }
}
