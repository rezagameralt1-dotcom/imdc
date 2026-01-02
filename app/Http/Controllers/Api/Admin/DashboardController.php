<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Services\Admin\DashboardService;
use Illuminate\Http\JsonResponse;

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
            $data = $this->dashboardService->getOverview();
            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard overview: ' . $e->getMessage(), 500);
        }
    }
}
