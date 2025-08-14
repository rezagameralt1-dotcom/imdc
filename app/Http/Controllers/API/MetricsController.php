<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function scrape()
    {
        $lines = [];
        $lines[] = '# HELP imdc_app_info Static application info';
        $lines[] = '# TYPE imdc_app_info gauge';
        $lines[] = 'imdc_app_info{app="imdc"} 1';

        // Optional DB ready
        try {
            DB::select('SELECT 1');
            $dbReady = 1;
        } catch (\Throwable $e) {
            $dbReady = 0;
        }
        $lines[] = '# HELP imdc_db_ready Database connectivity';
        $lines[] = '# TYPE imdc_db_ready gauge';
        $lines[] = "imdc_db_ready {$dbReady}";

        $body = implode("\n", $lines)."\n";

        return new Response($body, 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }
}
