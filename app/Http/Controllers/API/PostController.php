<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    public function index()
    {
        if (!\Schema::hasTable('posts')) {
            return ApiResponse::success(['posts' => []]);
        }
        $rows = DB::table('posts')->orderByDesc('id')->limit(50)->get();
        return ApiResponse::success(['posts' => $rows]);
    }

    public function store()
    {
        if (!\Schema::hasTable('posts')) {
            return ApiResponse::error('posts table missing', 400);
        }
        $id = DB::table('posts')->insertGetId([
            'title' => request('title', 'Untitled'),
            'summary' => request('summary', null),
            'content' => request('content', null),
            'status' => request('status', 'draft'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return ApiResponse::success(['id' => $id]);
    }
}
