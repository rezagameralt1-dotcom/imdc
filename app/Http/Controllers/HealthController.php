<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $checks = [];

        // DB
        try {
            $t0 = microtime(true);
            DB::select('select 1');
            $checks['db'] = ['ok'=>true, 'ms'=> round((microtime(true)-$t0)*1000,2)];
        } catch (\Throwable $e) {
            $checks['db'] = ['ok'=>false, 'error'=>$e->getMessage()];
        }

        // Cache
        try {
            $t0 = microtime(true);
            Cache::put('healthz', 'ok', 60);
            $val = Cache::get('healthz');
            $checks['cache'] = ['ok'=>$val==='ok', 'ms'=> round((microtime(true)-$t0)*1000,2)];
        } catch (\Throwable $e) {
            $checks['cache'] = ['ok'=>false, 'error'=>$e->getMessage()];
        }

        // Storage writable
        try {
            $t0 = microtime(true);
            Storage::disk('local')->put('healthz.txt', (string) now());
            $checks['storage'] = ['ok'=>Storage::disk('local')->exists('healthz.txt'), 'ms'=> round((microtime(true)-$t0)*1000,2)];
        } catch (\Throwable $e) {
            $checks['storage'] = ['ok'=>false, 'error'=>$e->getMessage()];
        }

        return response()->json([
            'ok' => collect($checks)->every(fn($c) => $c['ok'] ?? false),
            'checks' => $checks,
            'time' => now()->toAtomString(),
        ]);
    }
}

