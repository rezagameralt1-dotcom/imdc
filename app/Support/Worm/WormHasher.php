<?php

namespace App\Support\Worm;

/**
 * Canonical WORM Hash Specification (LOCKED)
 * 
 * This class implements the deterministic, immutable canonical hashing for WORM logs.
 * Both writer and verifier MUST use this exact specification.
 * 
 * CANONICAL HASH INPUT SPECIFICATION:
 * 1. Hash input format (delimiter-joined string):
 *    prev_hash + "\n" + event_type + "\n" + occurred_at + "\n" + payload_canonical_json
 *    - prev_hash: empty string "" for first log, previous log's hash for subsequent logs
 *    - event_type: event type string (e.g., "mint", "transfer")
 *    - occurred_at: ISO8601 UTC with seconds precision: "2026-01-01T21:24:57Z"
 *    - payload_canonical_json: canonical JSON string (normalized types, sorted keys)
 * 
 * 2. JSON Canonicalization Rules:
 *    - Associative arrays (objects): sort keys recursively (ksort)
 *    - List arrays: preserve order, canonicalize elements recursively
 *    - Scalars: normalize numeric strings to integers (e.g., "54" -> 54), preserve other types
 *    - Encoding flags: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
 *    - NO JSON_SORT_KEYS at top-level (order enforced by insertion)
 * 
 * 3. Timestamp Canonicalization:
 *    - Format: ISO8601 UTC with seconds precision: "2026-01-01T21:24:57Z"
 *    - Microseconds are TRUNCATED (not rounded)
 *    - Empty/invalid input => empty string
 * 
 * 4. prev_hash Chaining Rules:
 *    - First log in chain: prev_hash must be empty string ""
 *    - Subsequent logs: prev_hash must equal previous log's hash
 *    - Writer sets prev_hash BEFORE computing hash
 * 
 * 5. Hash Algorithm: SHA-256 (hex output, lowercase)
 */
class WormHasher
{
    /**
     * Compute canonical hash for a WORM log entry.
     * 
     * This is the SINGLE SOURCE OF TRUTH for WORM hash computation.
     * Both writer and verifier MUST use this method with identical inputs.
     * 
     * HASH INPUT FORMAT (delimiter-joined string):
     * prev_hash + "\n" + event_type + "\n" + occurred_at + "\n" + payload_canonical_json
     * 
     * @param array $fields Fields: prev_hash, event_type, occurred_at, payload_json
     * @return string SHA-256 hex hash (lowercase)
     */
    public static function compute(array $fields): string
    {
        // Extract fields for hash input (delimiter-joined format)
        $prevHash = $fields['prev_hash'] ?? '';
        $eventType = $fields['event_type'] ?? $fields['action'] ?? '';
        $occurredAt = $fields['occurred_at'] ?? $fields['created_at'] ?? '';
        $payload = $fields['payload_json'] ?? $fields['payload'] ?? [];

        // Canonicalize payload_json: returns canonical JSON string
        // Rules: sort keys for objects, preserve order for arrays, recursive, normalize numeric types
        $canonicalPayloadJson = self::canonicalizePayload($payload);

        // Normalize occurred_at to ISO8601 UTC with seconds precision: "2026-01-01T21:24:57Z"
        $occurredAtNormalized = self::normalizeTimestamp($occurredAt);

        // Build canonical hash input string (delimiter-joined format):
        // prev_hash + "\n" + event_type + "\n" + occurred_at + "\n" + payload_canonical_json
        // This ensures deterministic ordering and avoids JSON object key ordering issues
        $canonicalHashInput = $prevHash . "\n" . $eventType . "\n" . $occurredAtNormalized . "\n" . $canonicalPayloadJson;

        // Compute SHA-256 hash (hex, lowercase)
        return hash('sha256', $canonicalHashInput);
    }

    /**
     * Canonicalize payload_json according to locked specification.
     * 
     * CANONICALIZATION RULES:
     * - Associative arrays (objects): sort keys recursively (ksort)
     * - List arrays: preserve order, canonicalize elements recursively
     * - Scalars: normalize numeric strings to integers, preserve other types
     * - Encoding: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
     * 
     * @param mixed $payload Can be array, JSON string, or null
     * @return string Canonical JSON string
     */
    public static function canonicalizePayload($payload): string
    {
        if (is_string($payload)) {
            // If already JSON string, decode to PHP value
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            } else {
                // Invalid JSON, return empty object JSON
                return '{}';
            }
        }

        if ($payload === null) {
            return 'null';
        }

        if (!is_array($payload)) {
            // Scalar value: encode directly (preserves type)
            return json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | 1024);
        }

        // Canonicalize array: sort keys for objects, preserve order for lists
        $canonical = self::canonicalizeValue($payload);

        // Encode back using canonical flags
        // JSON_PRESERVE_ZERO_FRACTION = 1024
        // Do NOT use JSON_SORT_KEYS (64) - we handle sorting structurally
        return json_encode($canonical, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | 1024);
    }

    /**
     * Recursively canonicalize a value according to locked specification.
     * 
     * - Associative arrays (objects): sort keys (ksort), canonicalize values
     * - List arrays: preserve order, canonicalize each element
     * - Scalars: normalize numeric strings to integers, preserve other types
     * 
     * @param mixed $value
     * @return mixed Canonicalized value
     */
    private static function canonicalizeValue($value)
    {
        if (!is_array($value)) {
            // Normalize numeric strings to integers (deterministic type normalization)
            // This ensures "54" (string) and 54 (int) both become 54 (int) in canonical form
            if (is_string($value) && is_numeric($value) && !str_contains($value, '.')) {
                // Integer string: convert to int
                $intVal = (int) $value;
                // Verify conversion is lossless (no precision loss)
                if ((string) $intVal === $value) {
                    return $intVal;
                }
            }
            // Float strings are preserved as-is (not normalized to avoid precision issues)
            // UUIDs and other non-numeric strings are preserved as-is
            return $value;
        }

        // Check if array is associative (object) or list (array)
        $isAssoc = self::isAssociativeArray($value);

        if ($isAssoc) {
            // Associative array (object): sort keys, canonicalize values
            ksort($value);
            foreach ($value as $key => $val) {
                $value[$key] = self::canonicalizeValue($val);
            }
        } else {
            // List array: preserve order, canonicalize elements
            foreach ($value as $key => $val) {
                $value[$key] = self::canonicalizeValue($val);
            }
        }

        return $value;
    }

    /**
     * Check if array is associative (object) or list (array).
     * 
     * @param array $array
     * @return bool true if associative, false if list
     */
    private static function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false; // Empty array treated as list
        }

        // Check if keys are sequential starting from 0
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Normalize timestamp to ISO8601 UTC format with seconds precision (LOCKED SPEC).
     * 
     * CANONICALIZATION RULES:
     * - Format: ISO8601 UTC with seconds precision: "2026-01-01T21:24:57Z"
     * - Microseconds are TRUNCATED (not rounded)
     * - Empty/invalid input => empty string
     * 
     * @param mixed $timestamp Can be Carbon instance, DateTime, or string
     * @return string Format: "2026-01-01T21:24:57Z" (ISO8601 UTC, seconds precision)
     */
    private static function normalizeTimestamp($timestamp): string
    {
        if (empty($timestamp)) {
            return '';
        }

        try {
            $dt = null;

            if ($timestamp instanceof \Carbon\Carbon) {
                $dt = $timestamp->copy()->setTimezone('UTC');
            } elseif ($timestamp instanceof \DateTime) {
                $dt = clone $timestamp;
                $dt->setTimezone(new \DateTimeZone('UTC'));
            } elseif (is_string($timestamp)) {
                // If already in ISO8601 format with Z, check format
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $timestamp)) {
                    return $timestamp;
                }
                // If contains microseconds, truncate (not round)
                if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})Z?/', $timestamp, $matches)) {
                    return $matches[1] . 'Z'; // Return seconds part with Z suffix
                }
                // If in 'Y-m-d H:i:s' format, convert to ISO8601
                if (preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(\.\d+)?/', $timestamp, $matches)) {
                    return $matches[1] . 'T' . $matches[2] . 'Z';
                }
                // Parse and convert to UTC
                $dt = new \DateTime($timestamp);
                $dt->setTimezone(new \DateTimeZone('UTC'));
            } else {
                return '';
            }

            if ($dt) {
                // Format to ISO8601 UTC with seconds precision (truncates microseconds)
                return $dt->format('Y-m-d\TH:i:s\Z');
            }

            return '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
