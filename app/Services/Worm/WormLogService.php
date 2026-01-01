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

            // Get current timestamp in ISO8601 UTC format with seconds precision
            // This ensures occurred_at matches the canonical hash input format
            $occurredAt = now()->setTimezone('UTC');
            $occurredAtIso8601 = $occurredAt->format('Y-m-d\TH:i:s\Z'); // ISO8601 UTC: "2026-01-01T21:24:57Z"

            // Canonicalize payload_json BEFORE computing hash (must match what we store in DB)
            // WormHasher::canonicalizePayload returns canonical JSON string
            $canonicalPayloadJson = WormHasher::canonicalizePayload($payload);

            // Compute hash using canonical hasher BEFORE insert
            // Hash input format: prev_hash + "\n" + event_type + "\n" + occurred_at + "\n" + payload_canonical_json
            // Note: payload_json must be canonical JSON string, occurred_at must be ISO8601 UTC string
            $hash = WormHasher::compute([
                'prev_hash' => $prevHash,
                'event_type' => $eventType,
                'occurred_at' => $occurredAtIso8601, // ISO8601 UTC: "2026-01-01T21:24:57Z"
                'payload_json' => $canonicalPayloadJson, // Canonical JSON string
            ]);

            // Insert log with computed hash
            // Store prev_hash as empty string (not NULL) for consistency (first log has empty prev_hash)
            // Store payload_json as canonical JSON string (not PHP array)
            // Store created_at in ISO8601 format (DB will store as timestamp, but we use ISO8601 for hashing)
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
                'prev_hash' => $prevHash, // Empty string for first log, previous hash for subsequent logs
                'hash' => $hash,
                'created_at' => $occurredAtIso8601, // ISO8601 UTC string (DB converts to timestamp)
            ]);

            return WormLog::findOrFail($logId);
        });
    }

    /**
     * Get the last hash from the chain (for prev_hash calculation)
     * Returns empty string for first log, or previous log's hash
     * 
     * IMPORTANT: Uses the same ordering as verifier (created_at ASC, id ASC) to ensure
     * deterministic tail detection. The tail is the last log in this ordering.
     */
    private function getLastHash(): string
    {
        // Get all logs ordered the SAME way as verifier (created_at ASC, id ASC)
        // Then take the last one (tail of the chain)
        // This ensures deterministic prev_hash calculation even when multiple logs have the same occurred_at second
        $logs = WormLog::whereNotNull('hash')
            ->where('hash', '!=', '')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Return empty string if no previous log exists (first log in chain)
        // Otherwise return the hash of the tail (last log in ASC ordering)
        return $logs->isNotEmpty() ? $logs->last()->hash : '';
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

            // Normalize occurred_at to ISO8601 UTC format (matches canonical hash input)
            // This matches the canonical hash input format: "2026-01-01T21:24:57Z"
            $occurredAtNormalized = $log->created_at;
            if ($occurredAtNormalized instanceof \Carbon\Carbon || $occurredAtNormalized instanceof \DateTime) {
                $occurredAtNormalized = $occurredAtNormalized->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
            } elseif (is_string($occurredAtNormalized)) {
                // If in 'Y-m-d H:i:s' format, convert to ISO8601
                if (preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(\.\d+)?/', $occurredAtNormalized, $matches)) {
                    $occurredAtNormalized = $matches[1] . 'T' . $matches[2] . 'Z';
                } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $occurredAtNormalized)) {
                    // Try to parse and convert
                    try {
                        $dt = new \DateTime($occurredAtNormalized);
                        $dt->setTimezone(new \DateTimeZone('UTC'));
                        $occurredAtNormalized = $dt->format('Y-m-d\TH:i:s\Z');
                    } catch (\Exception $e) {
                        $occurredAtNormalized = '';
                    }
                }
            }

            // Canonicalize payload_json for diagnostics (first 200 chars)
            $canonicalPayloadJson = WormHasher::canonicalizePayload($log->payload_json);
            $canonicalPayloadPreview = mb_substr($canonicalPayloadJson, 0, 200);
            if (mb_strlen($canonicalPayloadJson) > 200) {
                $canonicalPayloadPreview .= '...';
            }

            // Recompute hash using the same canonical hasher
            // Hash input format: prev_hash + "\n" + event_type + "\n" + occurred_at + "\n" + payload_canonical_json
            // Note: payload_json is cast to array by Laravel model, canonicalizePayload() handles both array and JSON string
            // occurred_at normalized to ISO8601 UTC format
            $expectedHash = WormHasher::compute([
                'prev_hash' => $prevHash, // Use computed prev_hash (empty for first log, previous hash for subsequent)
                'event_type' => $log->event_type,
                'occurred_at' => $occurredAtNormalized, // ISO8601 UTC: "2026-01-01T21:24:57Z"
                'payload_json' => $log->payload_json, // Array (due to model cast) or JSON string - canonicalizePayload() handles both
            ]);

            if ($log->hash !== $expectedHash) {
                // Provide precise error with hint about potential cause
                $hint = 'Check payload_json canonicalization or created_at timestamp format';
                $errors[] = "Hash mismatch for log {$log->id}: expected {$expectedHash}, got {$log->hash} ({$hint})";
                
                // Store detailed diagnostics for first mismatch
                if (empty($diagnostics)) {
                    $diagnostics = [
                        'row_id' => $log->id,
                        'expected_hash' => $expectedHash,
                        'actual_hash' => $log->hash,
                        'occurred_at_normalized' => $occurredAtNormalized,
                        'payload_json_canonical_preview' => $canonicalPayloadPreview,
                    ];
                }
            }

            // Compare normalized prev_hash values
            if ($logPrevHash !== $prevHash) {
                $errors[] = "Prev hash mismatch for log {$log->id}: expected {$prevHash}, got {$logPrevHash}";
                
                // Store detailed diagnostics for first prev_hash mismatch if no hash mismatch yet
                if (empty($diagnostics)) {
                    $diagnostics = [
                        'row_id' => $log->id,
                        'expected_hash' => 'N/A (prev_hash mismatch)',
                        'actual_hash' => $log->hash ?? 'NULL',
                        'occurred_at_normalized' => $occurredAtNormalized,
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

