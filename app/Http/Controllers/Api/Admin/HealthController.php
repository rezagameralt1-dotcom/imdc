<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends ApiController
{
    /**
     * Admin-scoped health check
     * Returns detailed system health information
     */
    public function index(): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
                'database' => [],
            ];
            
            // Check core database connection
            try {
                DB::connection('core')->select('SELECT 1');
                $health['database']['core'] = 'connected';
            } catch (\Exception $e) {
                $health['database']['core'] = 'disconnected';
                $health['status'] = 'degraded';
            }
            
            // Check orders database connection (if configured)
            try {
                if (config('database.connections.orders')) {
                    DB::connection('orders')->select('SELECT 1');
                    $health['database']['orders'] = 'connected';
                }
            } catch (\Exception $e) {
                $health['database']['orders'] = 'disconnected';
            }
            
            // Check nfts database connection (if configured)
            try {
                if (config('database.connections.nfts')) {
                    DB::connection('nfts')->select('SELECT 1');
                    $health['database']['nfts'] = 'connected';
                }
            } catch (\Exception $e) {
                $health['database']['nfts'] = 'disconnected';
            }
            
            return $this->successResponse($health);
        } catch (\Exception $e) {
            return $this->errorResponse('Health check failed: ' . $e->getMessage(), 500);
        }
    }
}
