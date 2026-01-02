<?php

namespace App\Services\Reports;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    /**
     * Get system overview report
     *
     * @return array
     */
    public function getSystemOverview(): array
    {
        $usersCount = User::on('core')->count();
        $rolesCount = Role::on('core')->count();
        
        // Get key tables health (row counts)
        $tablesHealth = [];
        $keyTables = ['users', 'roles', 'permissions', 'audit_logs'];
        
        foreach ($keyTables as $table) {
            try {
                $count = DB::connection('core')->table($table)->count();
                $tablesHealth[$table] = [
                    'exists' => true,
                    'row_count' => $count,
                ];
            } catch (\Exception $e) {
                $tablesHealth[$table] = [
                    'exists' => false,
                    'row_count' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return [
            'users_count' => $usersCount,
            'roles_count' => $rolesCount,
            'tables_health' => $tablesHealth,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get guardrail runs summary (placeholder)
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getGuardrailRuns(int $limit = 50, int $offset = 0): array
    {
        // Placeholder: return empty structure for now
        // Future: query from guardrail_runs table if it exists
        return [
            'runs' => [],
            'total' => 0,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Get audit logs report
     *
     * @param array $filters
     * @param string|null $sortBy
     * @param string $sortOrder
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAuditLogs(
        array $filters = [],
        ?string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        int $limit = 50,
        int $offset = 0
    ): array {
        $query = AuditLog::on('core');
        
        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (isset($filters['action'])) {
            $query->where('action', 'like', '%' . $filters['action'] . '%');
        }
        
        if (isset($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        // Get total count before pagination
        $total = $query->count();
        
        // Apply sorting
        $allowedSortFields = ['created_at', 'action', 'auditable_type', 'user_id'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'created_at';
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
        
        $query->orderBy($sortBy, $sortOrder);
        
        // Apply pagination
        $logs = $query->limit($limit)->offset($offset)->get();
        
        return [
            'logs' => $logs->toArray(),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ];
    }
}
