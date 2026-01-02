<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Services\Reports\ReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends ApiController
{
    public function __construct(
        private readonly ReportsService $reportsService,
    ) {
    }

    /**
     * Get system overview report
     */
    public function systemOverview(Request $request): JsonResponse
    {
        try {
            $data = $this->reportsService->getSystemOverview();
            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate system overview: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get guardrail runs summary
     */
    public function guardrailRuns(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->input('limit', 50);
            $offset = (int) $request->input('offset', 0);
            
            $data = $this->reportsService->getGuardrailRuns($limit, $offset);
            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve guardrail runs: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get audit logs report
     */
    public function auditLogs(Request $request): JsonResponse
    {
        try {
            $filters = [
                'user_id' => $request->input('user_id'),
                'action' => $request->input('action'),
                'auditable_type' => $request->input('auditable_type'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ];
            
            // Remove null filters
            $filters = array_filter($filters, fn($value) => $value !== null);
            
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $limit = (int) $request->input('limit', 50);
            $offset = (int) $request->input('offset', 0);
            
            $data = $this->reportsService->getAuditLogs($filters, $sortBy, $sortOrder, $limit, $offset);
            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve audit logs: ' . $e->getMessage(), 500);
        }
    }
}
