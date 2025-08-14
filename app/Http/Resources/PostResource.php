<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'categories' => $this->whenLoaded('categories', fn() => $this->categories->pluck('name')->all()),
            'tags' => $this->whenLoaded('tags', fn() => $this->tags->pluck('name')->all()),
        ];
    }
}

