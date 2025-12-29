<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    public function log(string $action, mixed $auditable, ?User $user = null, array $payload = [], ?string $traceId = null): void
    {
        AuditLog::create([
            'action' => $action,
            'auditable_type' => is_object($auditable) ? $auditable::class : gettype($auditable),
            'auditable_id' => is_object($auditable) && property_exists($auditable, 'id') ? (string) $auditable->id : (string) $auditable,
            'payload' => $payload,
            'user_id' => $user?->id,
            'trace_id' => $traceId,
        ]);
    }
}

