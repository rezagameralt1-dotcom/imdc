<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\PageResource;
use App\Models\Page;

class PageController extends ApiController
{
    public function index()
    {
        return PageResource::collection(Page::latest()->paginate(50));
    }

    public function show(Page $page)
    {
        return new PageResource($page);
    }
}
