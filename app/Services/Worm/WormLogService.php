<?php

namespace App\Services\Worm;

use App\Nfts\Models\WormLog;
use App\Support\Worm\WormHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WormLogService
{
    /**
     * Create a WORM log entry with hash chain
     *
     * @param string $eventType (mint, transfer, lease, etc.)
     * @param string $entityType
     * @param string $entityId
     * @param array $payload
     * @return WormLog
     */
    public function log(string $eventType, string $entityType, string $entityId, array $payload): WormLog
    {
        return DB::connection('nfts')->transaction(function () use ($eventType, $entityType, $entityId, $payload) {
            // Generate UUID in application BEFORE computing hash
            $logId = (string) Str::uuid();

            // Get previous hash (last hash in the chain) - must get hash that's already set (not NULL)
            $prevHash = $this->getLastHash();

            // Get current timestamp (will be stored as occurred_at/created_at)
            $occurredAt = now();

            // Extract idempotency_key from payload if present
            $idempotencyKey = $payload['idempotency_key'] ?? '';

            // Compute hash using canonical hasher BEFORE insert
            $hash = WormHasher::compute([
                'id' => $logId,
                'prev_hash' => $prevHash ?? '',
                'action' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => $occurredAt,
            ]);

            // Insert log with computed hash
            DB::connection('nfts')->insert("
                INSERT INTO worm_logs (id, event_type, entity_type, entity_id, payload_json, prev_hash, hash, created_at)
                VALUES (
                    :id::uuid,
                    :event_type,
                    :entity_type,
                    :entity_id::uuid,
                    :payload_json::jsonb,
                    :prev_hash,
                    :hash,
                    :created_at
                )
            ", [
                'id' => $logId,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload_json' => json_encode($payload),
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_at' => $occurredAt,
            ]);

            return WormLog::findOrFail($logId);
        });
    }

    /**
     * Get the last hash from the chain (for prev_hash calculation)
     */
    private function getLastHash(): ?string
    {
        // Get the last log that has a hash set (not NULL) - this ensures we get the correct prev_hash
        $lastLog = WormLog::whereNotNull('hash')
            ->where('hash', '!=', '')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastLog?->hash;
    }

    /**
     * Verify hash chain integrity
     *
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function verifyChain(): array
    {
        $logs = WormLog::orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $errors = [];
        $prevHash = null;

        foreach ($logs as $log) {
            // Extract idempotency_key from payload if present
            $idempotencyKey = '';
            if (is_array($log->payload_json) && isset($log->payload_json['idempotency_key'])) {
                $idempotencyKey = $log->payload_json['idempotency_key'];
            }

            // Recompute hash using the same canonical hasher
            $expectedHash = WormHasher::compute([
                'id' => $log->id,
                'prev_hash' => $prevHash ?? '',
                'action' => $log->event_type,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'payload' => $log->payload_json,
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => $log->created_at,
            ]);

            if ($log->hash !== $expectedHash) {
                $errors[] = "Hash mismatch for log {$log->id}: expected {$expectedHash}, got {$log->hash}";
            }

            if ($log->prev_hash !== $prevHash) {
                $errors[] = "Prev hash mismatch for log {$log->id}: expected {$prevHash}, got {$log->prev_hash}";
            }

            $prevHash = $log->hash;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}

