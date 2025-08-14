<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $cats = collect(['News','Guides','Updates'])->map(fn($n) =>
            Category::firstOrCreate(['slug' => Str::slug($n)], ['name' => $n])
        );

        // Tags
        $tags = collect(['important','release','how-to','urgent'])->map(fn($n) =>
            Tag::firstOrCreate(['name' => $n])
        );

        // Pages
        Page::firstOrCreate(['slug' => 'about'], [
            'title' => 'About DigitalCity',
            'body'  => 'This is a demo About page for DigitalCity. Edit me in admin.',
        ]);

        Page::firstOrCreate(['slug' => 'home'], [
            'title' => 'Welcome to DigitalCity',
            'body'  => 'Landing page placeholder. Build your homepage here.',
        ]);

        // Sample Post
        $author = User::where('is_admin', true)->first() ?? User::first();
        if ($author) {
            $title = 'Welcome Post';
            $slug = Str::slug($title);
            $i = 1;
            while (Post::where('slug',$slug)->exists()) {
                $slug = Str::slug($title) . '-' . (++$i);
            }

            $post = Post::firstOrCreate(['slug' => $slug], [
                'author_id' => $author->id,
                'title' => $title,
                'excerpt' => 'Kick-off post created by ContentSeeder.',
                'body' => 'You can edit this post in the admin panel.',
                'status' => 'published',
                'published_at' => now(),
            ]);

            $post->categories()->sync($cats->pluck('id')->take(2)->all());
            $post->tags()->sync($tags->pluck('id')->take(3)->all());
        }
    }
}

