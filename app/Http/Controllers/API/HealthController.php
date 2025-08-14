<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function ping()
    {
        return ApiResponse::success(['ok' => true, 'time' => now()->toISOString()]);
    }

    public function live()
    {
        return ApiResponse::success(['live' => true]);
    }

    public function ready()
    {
        // Simple DB check
        try {
            DB::select('SELECT 1');
            $db = true;
        } catch (\Throwable $e) {
            $db = false;
        }
        return ApiResponse::success(['ready' => $db]);
    }
}
