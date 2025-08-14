<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Artisan;

class ProgressController extends Controller
{
    public function show()
    {
        // Invoke the command and capture JSON
        Artisan::call('imdc:progress', ['--json' => true]);
        $json = Artisan::output();
        $data = json_decode($json, true) ?: ['percent'=>0,'error'=>'failed to parse progress'];
        return ApiResponse::success($data);
    }
}
