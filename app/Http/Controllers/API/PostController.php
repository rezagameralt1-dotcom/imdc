<?php
namespace App\Http\Controllers\API;

use App\Models\Post;
use App\Http\Resources\PostResource;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;

class PostController extends ApiController
{
    public function index()
    {
        $posts = Post::with(['categories','tags'])->latest()->paginate(12);
        return PostResource::collection($posts);
    }

    public function store(StorePostRequest $request)
    {
        $post = Post::create($request->validated());
        if ($request->has('category_ids')) {
            $post->categories()->sync($request->input('category_ids'));
        }
        if ($request->has('tag_ids')) {
            $post->tags()->sync($request->input('tag_ids'));
        }
        return new PostResource($post->load(['categories','tags']));
    }

    public function show(Post $post)
    {
        return new PostResource($post->load(['categories','tags']));
    }

    public function update(UpdatePostRequest $request, Post $post)
    {
        $post->update($request->validated());
        if ($request->has('category_ids')) {
            $post->categories()->sync($request->input('category_ids'));
        }
        if ($request->has('tag_ids')) {
            $post->tags()->sync($request->input('tag_ids'));
        }
        return new PostResource($post->load(['categories','tags']));
    }

    public function destroy(Post $post)
    {
        $post->delete();
        return $this->ok(['deleted' => true]);
    }
}

