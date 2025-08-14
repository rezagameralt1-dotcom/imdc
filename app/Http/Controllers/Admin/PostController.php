<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostSlug;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Post::class);

        $q = trim((string) $request->get('q', ''));
        $status = $request->get('status');
        $sort = in_array($request->get('sort'), ['id', 'title', 'published_at', 'status']) ? $request->get('sort') : 'id';
        $dir = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        $posts = Post::with('author')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('title', 'like', "%$q%")
                        ->orWhere('excerpt', 'like', "%$q%");
                });
            })
            ->when($status, fn ($qq) => $qq->where('status', $status))
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString();

        return view('admin.content.posts.index', compact('posts', 'q', 'status', 'sort', 'dir'));
    }

    public function create()
    {
        $this->authorize('create', Post::class);

        $categories = Category::orderBy('name')->get();
        $allTags = Tag::orderBy('name')->pluck('name')->implode(', ');

        return view('admin.content.posts.create', compact('categories', 'allTags'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Post::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'tags' => ['nullable', 'string'],
        ]);

        $slug = Str::slug($data['title']);
        $base = $slug;
        $i = 1;
        while (Post::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        $post = Post::create([
            'author_id' => $request->user()->id,
            'title' => $data['title'],
            'slug' => $slug,
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'] ?? null,
            'status' => $data['status'],
            'published_at' => $data['published_at'] ?? null,
        ]);

        $post->categories()->sync($data['categories'] ?? []);

        $tagIds = [];
        if (! empty($data['tags'])) {
            $names = array_filter(array_map('trim', explode(',', $data['tags'])));
            foreach ($names as $name) {
                $tag = Tag::firstOrCreate(['name' => $name]);
                $tagIds[] = $tag->id;
            }
        }
        $post->tags()->sync($tagIds);

        return redirect()->route('admin.content.posts.index')->with('ok', 'پست ایجاد شد.');
    }

    public function edit(Post $post)
    {
        $this->authorize('update', $post);

        $categories = Category::orderBy('name')->get();
        $selectedCats = $post->categories()->pluck('categories.id')->toArray();
        $tags = $post->tags()->pluck('name')->implode(', ');

        return view('admin.content.posts.edit', compact('post', 'categories', 'selectedCats', 'tags'));
    }

    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'tags' => ['nullable', 'string'],
        ]);

        $newSlug = $post->slug;
        if ($post->title !== $data['title']) {
            $slug = \Illuminate\Support\Str::slug($data['title']);
            $base = $slug;
            $i = 1;
            while (Post::where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                $slug = $base.'-'.(++$i);
            }
            if ($slug !== $post->slug) {
                // Keep old slug for redirect
                PostSlug::firstOrCreate(['post_id' => $post->id, 'slug' => $post->slug]);
                $newSlug = $slug;
            }
        }

        $post->update([
            'title' => $data['title'],
            'slug' => $newSlug,
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'] ?? null,
            'status' => $data['status'],
            'published_at' => $data['published_at'] ?? null,
        ]);

        $post->categories()->sync($data['categories'] ?? []);

        $tagIds = [];
        if (! empty($data['tags'])) {
            $names = array_filter(array_map('trim', explode(',', $data['tags'])));
            foreach ($names as $name) {
                $tag = Tag::firstOrCreate(['name' => $name]);
                $tagIds[] = $tag->id;
            }
        }
        $post->tags()->sync($tagIds);

        return redirect()->route('admin.content.posts.index')->with('ok', 'پست به‌روزرسانی شد.');
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return back()->with('ok','پست حذف شد.');
    }
}
