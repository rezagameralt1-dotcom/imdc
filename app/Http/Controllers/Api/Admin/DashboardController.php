<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Services\Admin\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardController extends ApiController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    /**
     * Get dashboard overview
     */
    public function overview(): JsonResponse
    {
        try {
            // Enforce permission: reports.read OR admin.dashboard.read
            if (!Gate::any(['reports.read', 'admin.dashboard.read'])) {
                return $this->errorResponse('Unauthorized: Missing required permission', 403);
            }
            
            $data = $this->dashboardService->getOverview();
            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard overview: ' . $e->getMessage(), 500);
        }
    }
}
