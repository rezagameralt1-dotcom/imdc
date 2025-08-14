<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $q = Post::query()->with('user')->latest();
        if ($request->boolean('mine')) {
            $q->where('user_id', Auth::id());
        } else {
            $q->where('is_public', true);
        }
        return response()->json($q->paginate(20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable','string','max:200'],
            'body' => ['required','string'],
            'media' => ['array'],
            'is_public' => ['boolean'],
        ]);
        $data['user_id'] = Auth::id();
        $post = Post::create($data);
        return response()->json($post, 201);
    }

    public function show(Post $post)
    {
        if (!$post->is_public && $post->user_id !== Auth::id()) {
            abort(403);
        }
        return response()->json($post->load('user'));
    }

    public function update(Request $request, Post $post)
    {
        if ($post->user_id !== Auth::id()) abort(403);
        $data = $request->validate([
            'title' => ['nullable','string','max:200'],
            'body' => ['sometimes','string'],
            'media' => ['sometimes','array'],
            'is_public' => ['sometimes','boolean'],
        ]);
        $post->update($data);
        return response()->json($post);
    }

    public function destroy(Post $post)
    {
        if ($post->user_id !== Auth::id()) abort(403);
        $post->delete();
        return response()->noContent();
    }
}
