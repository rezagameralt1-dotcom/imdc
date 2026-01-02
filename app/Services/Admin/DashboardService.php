<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\ReportsService;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly ReportsService $reportsService,
    ) {
    }

    /**
     * Get dashboard overview (aggregated data)
     *
     * @return array
     */
    public function getOverview(): array
    {
        // System overview (reuse existing service)
        $systemOverview = $this->reportsService->getSystemOverview();
        
        // Recent guardrail runs (latest 10)
        $guardrailRuns = $this->reportsService->getGuardrailRuns(10, 0);
        
        // Audit logs summary (count + latest 10)
        $auditLogsTotal = AuditLog::on('core')->count();
        $auditLogsRecent = AuditLog::on('core')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'auditable_type' => $log->auditable_type,
                    'auditable_id' => $log->auditable_id,
                    'user_id' => $log->user_id,
                    'trace_id' => $log->trace_id,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            })
            ->toArray();
        
        // RBAC stats
        $rolesCount = Role::on('core')->count();
        $permissionsCount = Permission::on('core')->count();
        $usersCount = User::on('core')->count();
        
        // Count users with Admin role
        $adminsCount = User::on('core')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'Admin');
            })
            ->count();
        
        return [
            'system_overview' => $systemOverview,
            'recent_guardrail_runs' => $guardrailRuns,
            'audit_logs' => [
                'total' => $auditLogsTotal,
                'latest' => $auditLogsRecent,
            ],
            'rbac_stats' => [
                'users_total' => $usersCount,
                'admins_total' => $adminsCount,
                'roles_total' => $rolesCount,
                'permissions_total' => $permissionsCount,
            ],
        ];
    }
}
