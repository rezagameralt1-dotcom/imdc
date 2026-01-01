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

            // Get previous hash (last hash in the chain) - returns '' for first log
            $prevHash = $this->getLastHash();

            // Get current timestamp with seconds precision only (truncate microseconds)
            // This ensures created_at matches the canonical hash input format
            $occurredAt = now()->setTimezone('UTC');
            $createdAtSeconds = $occurredAt->format('Y-m-d H:i:s'); // Truncate microseconds

            // Canonicalize payload_json BEFORE computing hash (must match what we store in DB)
            // WormHasher::canonicalizePayload returns canonical JSON string
            $canonicalPayloadJson = WormHasher::canonicalizePayload($payload);

            // Compute hash using canonical hasher BEFORE insert
            // Use exact DB column names: id, prev_hash, event_type, entity_type, entity_id, payload_json, created_at
            // Note: payload_json must be canonical JSON string, created_at must be seconds-only UTC string
            $hash = WormHasher::compute([
                'id' => $logId,
                'prev_hash' => $prevHash,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload_json' => $canonicalPayloadJson, // Canonical JSON string
                'created_at' => $createdAtSeconds, // UTC seconds string
            ]);

            // Insert log with computed hash
            // Store prev_hash as empty string (not NULL) for consistency
            // Store payload_json as canonical JSON string (not PHP array)
            // Store created_at with seconds precision only
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
                    :created_at::timestamp
                )
            ", [
                'id' => $logId,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload_json' => $canonicalPayloadJson, // Canonical JSON string (not PHP array)
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_at' => $createdAtSeconds, // UTC seconds string
            ]);

            return WormLog::findOrFail($logId);
        });
    }

    /**
     * Get the last hash from the chain (for prev_hash calculation)
     * Returns empty string for first log, or previous log's hash
     */
    private function getLastHash(): string
    {
        // Get the last log that has a hash set (not NULL) - this ensures we get the correct prev_hash
        $lastLog = WormLog::whereNotNull('hash')
            ->where('hash', '!=', '')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        // Return empty string if no previous log exists (first log in chain)
        return $lastLog?->hash ?? '';
    }

    /**
     * Verify hash chain integrity
     *
     * @return array ['valid' => bool, 'errors' => array, 'diagnostics' => array]
     */
    public function verifyChain(): array
    {
        $logs = WormLog::orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $errors = [];
        $diagnostics = [];
        $prevHash = ''; // Start with empty string (first log has no previous hash)

        foreach ($logs as $log) {
            // Normalize prev_hash from DB (NULL -> empty string for consistency)
            $logPrevHash = $log->prev_hash ?? '';

            // Normalize created_at: ensure seconds precision (truncate microseconds if present)
            // This matches the canonical hash input format
            $createdAtNormalized = $log->created_at;
            if (is_string($createdAtNormalized) && preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(\.\d+)?/', $createdAtNormalized, $matches)) {
                $createdAtNormalized = $matches[1]; // Truncate microseconds
            } elseif ($createdAtNormalized instanceof \Carbon\Carbon || $createdAtNormalized instanceof \DateTime) {
                $createdAtNormalized = $createdAtNormalized->setTimezone('UTC')->format('Y-m-d H:i:s');
            }

            // Canonicalize payload_json for diagnostics (first 200 chars)
            $canonicalPayloadJson = WormHasher::canonicalizePayload($log->payload_json);
            $canonicalPayloadPreview = mb_substr($canonicalPayloadJson, 0, 200);
            if (mb_strlen($canonicalPayloadJson) > 200) {
                $canonicalPayloadPreview .= '...';
            }

            // Recompute hash using the same canonical hasher
            // Use exact DB column names: id, prev_hash, event_type, entity_type, entity_id, payload_json, created_at
            // Note: payload_json is cast to array by Laravel model, canonicalizePayload() handles both array and JSON string
            // created_at normalized to seconds (truncate microseconds)
            $expectedHash = WormHasher::compute([
                'id' => $log->id,
                'prev_hash' => $prevHash,
                'event_type' => $log->event_type,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'payload_json' => $log->payload_json, // Array (due to model cast) or JSON string - canonicalizePayload() handles both
                'created_at' => $createdAtNormalized, // UTC seconds string
            ]);

            if ($log->hash !== $expectedHash) {
                // Provide precise error with hint about potential cause
                $hint = 'Check payload_json canonicalization or created_at timestamp format';
                $errors[] = "Hash mismatch for log {$log->id}: expected {$expectedHash}, got {$log->hash} ({$hint})";
                
                // Store detailed diagnostics for first mismatch
                if (empty($diagnostics)) {
                    $diagnostics[] = [
                        'row_id' => $log->id,
                        'expected_hash' => $expectedHash,
                        'actual_hash' => $log->hash,
                        'created_at_normalized' => $createdAtNormalized,
                        'payload_json_canonical_preview' => $canonicalPayloadPreview,
                    ];
                }
            }

            // Compare normalized prev_hash values
            if ($logPrevHash !== $prevHash) {
                $errors[] = "Prev hash mismatch for log {$log->id}: expected {$prevHash}, got {$logPrevHash}";
                
                // Store detailed diagnostics for first prev_hash mismatch if no hash mismatch yet
                if (empty($diagnostics)) {
                    $diagnostics[] = [
                        'row_id' => $log->id,
                        'expected_hash' => 'N/A (prev_hash mismatch)',
                        'actual_hash' => $log->hash ?? 'NULL',
                        'created_at_normalized' => $createdAtNormalized,
                        'payload_json_canonical_preview' => $canonicalPayloadPreview,
                    ];
                }
            }

            $prevHash = $log->hash;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'diagnostics' => $diagnostics,
        ];
    }
}

