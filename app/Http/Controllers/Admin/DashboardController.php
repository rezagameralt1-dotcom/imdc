<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Post;
use App\Models\Asset;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index()
    {
        $userCount = User::count();
        $postCount = Post::count();
        $postPublished = Post::where('status','published')->count();
        $assetCount = class_exists(Asset::class) ? Asset::count() : 0;

        $size = 0;
        if (Storage::disk('public')->exists('/')) {
            foreach (Storage::disk('public')->allFiles() as $f) {
                $size += Storage::disk('public')->size($f);
            }
        }

        return view('admin.dashboard', compact('userCount','postCount','postPublished','assetCount','size'));
    }
}

