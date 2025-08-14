<?php
namespace App\Http\Controllers\API;

use App\Models\Tag;
use App\Http\Resources\TagResource;

class TagController extends ApiController
{
    public function index()
    {
        return TagResource::collection(Tag::orderBy('name')->paginate(100));
    }

    public function show(Tag $tag)
    {
        return new TagResource($tag);
    }
}

