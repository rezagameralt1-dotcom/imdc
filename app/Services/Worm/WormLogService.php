<?php

namespace App\Services\Worm;

use App\Nfts\Models\WormLog;
use Illuminate\Support\Facades\DB;

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
            // Get previous hash (last hash in the chain)
            $prevHash = $this->getLastHash();

            // Create canonical JSON from payload (sorted keys for determinism)
            $canonicalJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS);

            // Create hash: SHA-256(prev_hash + canonical_json + event_type + entity_type + entity_id + created_at)
            // Note: created_at is set to now() in DB, so we use a placeholder and recalculate after insert
            $createdAt = now();
            $hashInput = ($prevHash ?? '') . $canonicalJson . $eventType . $entityType . $entityId . $createdAt->toIso8601String();
            $hash = hash('sha256', $hashInput);

            $log = WormLog::create([
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload_json' => $payload,
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_at' => $createdAt,
            ]);

            return $log;
        });
    }

    /**
     * Get the last hash from the chain (for prev_hash calculation)
     */
    private function getLastHash(): ?string
    {
        $lastLog = WormLog::orderBy('created_at', 'desc')
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
            // Recalculate hash
            $canonicalJson = json_encode($log->payload_json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS);
            $hashInput = ($prevHash ?? '') . $canonicalJson . $log->event_type . $log->entity_type . $log->entity_id . $log->created_at->toIso8601String();
            $expectedHash = hash('sha256', $hashInput);

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

