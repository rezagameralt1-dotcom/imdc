<?php

namespace App\Support\Worm;

class WormHasher
{
    /**
     * Compute canonical hash for a WORM log entry.
     * 
     * This function ensures deterministic hashing by:
     * 1. Using a strict field order
     * 2. Canonicalizing JSON payload (sorted keys, consistent encoding)
     * 3. Using ISO8601 UTC timestamps
     * 4. Joining fields with a delimiter
     * 
     * @param array $fields Fields in order: id, prev_hash, action, entity_type, entity_id, payload, idempotency_key, occurred_at
     * @return string SHA-256 hex hash
     */
    public static function compute(array $fields): string
    {
        // Extract fields with defaults
        $id = $fields['id'] ?? '';
        $prevHash = $fields['prev_hash'] ?? '';
        $action = $fields['action'] ?? $fields['event_type'] ?? '';
        $entityType = $fields['entity_type'] ?? '';
        $entityId = $fields['entity_id'] ?? '';
        $payload = $fields['payload'] ?? $fields['payload_json'] ?? [];
        $idempotencyKey = $fields['idempotency_key'] ?? '';
        $occurredAt = $fields['occurred_at'] ?? $fields['created_at'] ?? '';

        // Canonicalize payload: recursively sort keys and encode with consistent flags
        $canonicalPayload = self::canonicalizePayload($payload);

        // Normalize occurred_at to ISO8601 UTC format
        $occurredAtIso = self::normalizeTimestamp($occurredAt);

        // Build hash input string with strict field order and delimiter
        // Order: id, prev_hash, action, entity_type, entity_id, payload, idempotency_key, occurred_at
        $hashInput = implode("\n", [
            (string) $id,
            (string) $prevHash,
            (string) $action,
            (string) $entityType,
            (string) $entityId,
            $canonicalPayload,
            (string) $idempotencyKey,
            $occurredAtIso,
        ]);

        // Compute SHA-256 hash
        return hash('sha256', $hashInput);
    }

    /**
     * Canonicalize payload array to JSON string with sorted keys.
     * 
     * @param mixed $payload
     * @return string JSON string
     */
    private static function canonicalizePayload($payload): string
    {
        if (is_string($payload)) {
            // If already JSON string, decode and re-encode to ensure canonicalization
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            } else {
                // Invalid JSON, return as-is (shouldn't happen in normal flow)
                return $payload;
            }
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        // Recursively sort keys
        $canonical = self::recursiveKsort($payload);

        // Encode with consistent flags: no unicode escaping, no slash escaping, sorted keys
        // JSON_SORT_KEYS = 64 (PHP constant, use numeric value for compatibility)
        return json_encode($canonical, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | 64);
    }

    /**
     * Recursively sort array keys.
     * 
     * @param array $array
     * @return array
     */
    private static function recursiveKsort(array $array): array
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::recursiveKsort($value);
            }
        }
        return $array;
    }

    /**
     * Normalize timestamp to ISO8601 UTC format.
     * 
     * @param mixed $timestamp Can be Carbon instance, DateTime, string, or ISO8601 string
     * @return string ISO8601 format: YYYY-MM-DDTHH:MM:SS+00:00
     */
    private static function normalizeTimestamp($timestamp): string
    {
        if (empty($timestamp)) {
            return '';
        }

        // If already ISO8601 string, validate and return
        if (is_string($timestamp)) {
            // Check if it's already in ISO8601 format
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/', $timestamp)) {
                // Ensure UTC timezone
                if (strpos($timestamp, '+00:00') !== false || strpos($timestamp, 'Z') !== false) {
                    return str_replace('Z', '+00:00', $timestamp);
                }
                // Parse and convert to UTC
                try {
                    $dt = new \DateTime($timestamp);
                    $dt->setTimezone(new \DateTimeZone('UTC'));
                    return $dt->format('Y-m-d\TH:i:s+00:00');
                } catch (\Exception $e) {
                    return $timestamp;
                }
            }
        }

        // Try to parse as Carbon/DateTime
        try {
            if ($timestamp instanceof \Carbon\Carbon) {
                return $timestamp->setTimezone('UTC')->format('Y-m-d\TH:i:s+00:00');
            }
            if ($timestamp instanceof \DateTime) {
                $dt = clone $timestamp;
                $dt->setTimezone(new \DateTimeZone('UTC'));
                return $dt->format('Y-m-d\TH:i:s+00:00');
            }
            // Try to parse string
            $dt = new \DateTime($timestamp);
            $dt->setTimezone(new \DateTimeZone('UTC'));
            return $dt->format('Y-m-d\TH:i:s+00:00');
        } catch (\Exception $e) {
            return (string) $timestamp;
        }
    }
}
