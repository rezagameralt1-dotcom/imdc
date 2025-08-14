<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;

class HomeController extends Controller
{
    public function home()
    {
        $page = Page::where('slug', 'home')->first();
        if ($page) {
            return view('site.pages.show', compact('page'));
        }

        // اگر صفحه home نداریم، همان UI دمو را نشان بده
        return redirect()->route('ui');
    }
}
