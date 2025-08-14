<?php
namespace App\Http\Controllers\API;

use App\Models\Page;
use App\Http\Resources\PageResource;

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

