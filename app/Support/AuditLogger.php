<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    public function log(string $action, mixed $auditable, ?User $user = null, array $payload = [], ?string $traceId = null): void
    {
        $auditableType = is_object($auditable) ? $auditable::class : (string) $auditable;
        $auditableId = $auditable instanceof Model
            ? (string) $auditable->getKey()
            : (string) $auditable;

        $normalizedPayload = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true);
        $normalizedTrace = $traceId ? (string) $traceId : null;

        AuditLog::create([
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'payload' => $normalizedPayload,
            'user_id' => $user?->id,
            'trace_id' => $normalizedTrace,
        ]);
    }
}


