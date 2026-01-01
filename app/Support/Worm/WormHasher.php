<?php

namespace App\Support\Worm;

/**
 * Canonical WORM Hash Specification (LOCKED)
 * 
 * This class implements the deterministic, immutable canonical hashing for WORM logs.
 * Both writer and verifier MUST use this exact specification.
 * 
 * CANONICAL HASH INPUT SPECIFICATION:
 * 1. Field order (EXACT, matching DB columns):
 *    - id
 *    - prev_hash
 *    - event_type
 *    - entity_type
 *    - entity_id
 *    - payload_json (canonical JSON string)
 *    - created_at (UTC seconds string: "Y-m-d H:i:s")
 * 
 * 2. JSON Canonicalization Rules:
 *    - Associative arrays (objects): sort keys recursively (ksort)
 *    - List arrays: preserve order, canonicalize elements recursively
 *    - Scalars: preserve types (numbers stay numeric)
 *    - Encoding flags: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
 *    - NO JSON_SORT_KEYS at top-level (order enforced by insertion)
 * 
 * 3. Timestamp Canonicalization:
 *    - Format: "Y-m-d H:i:s" (UTC, seconds precision only)
 *    - Microseconds are TRUNCATED (not rounded)
 *    - Empty/invalid input => empty string
 * 
 * 4. Hash Algorithm: SHA-256 (hex output)
 */
class WormHasher
{
    /**
     * Compute canonical hash for a WORM log entry.
     * 
     * This is the SINGLE SOURCE OF TRUTH for WORM hash computation.
     * Both writer and verifier MUST use this method with identical inputs.
     * 
     * @param array $fields Fields matching DB columns: id, prev_hash, event_type, entity_type, entity_id, payload_json, created_at
     * @return string SHA-256 hex hash
     */
    public static function compute(array $fields): string
    {
        // Extract fields matching DB columns: id, prev_hash, event_type, entity_type, entity_id, payload_json, created_at
        $id = $fields['id'] ?? '';
        $prevHash = $fields['prev_hash'] ?? '';
        $eventType = $fields['event_type'] ?? $fields['action'] ?? '';
        $entityType = $fields['entity_type'] ?? '';
        $entityId = $fields['entity_id'] ?? '';
        $payload = $fields['payload_json'] ?? $fields['payload'] ?? [];
        $occurredAt = $fields['occurred_at'] ?? $fields['created_at'] ?? '';

        // Canonicalize payload_json: returns canonical JSON string
        // Rules: sort keys for objects, preserve order for arrays, recursive
        $canonicalPayloadJson = self::canonicalizePayload($payload);

        // Normalize created_at to UTC seconds string: "Y-m-d H:i:s" (truncate microseconds)
        $createdAtNormalized = self::normalizeTimestamp($occurredAt);

        // Build canonical JSON object with fields in EXACT order (matching DB columns):
        // id, prev_hash, event_type, entity_type, entity_id, payload_json, created_at
        // Note: PHP 7.2+ preserves insertion order in JSON objects
        $canonicalObject = [
            'id' => (string) $id,
            'prev_hash' => (string) $prevHash,
            'event_type' => (string) $eventType,
            'entity_type' => (string) $entityType,
            'entity_id' => (string) $entityId,
            'payload_json' => $canonicalPayloadJson, // Canonical JSON string (not object)
            'created_at' => $createdAtNormalized,
        ];

        // Encode to canonical JSON string (no whitespace, preserves insertion order in PHP 7.2+)
        // JSON_PRESERVE_ZERO_FRACTION = 1024
        // Do NOT use JSON_SORT_KEYS (64) on top-level to preserve exact field order
        $canonicalJson = json_encode($canonicalObject, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | 1024);

        // Compute SHA-256 hash
        return hash('sha256', $canonicalJson);
    }

    /**
     * Canonicalize payload_json according to locked specification.
     * 
     * CANONICALIZATION RULES:
     * - Associative arrays (objects): sort keys recursively (ksort)
     * - List arrays: preserve order, canonicalize elements recursively
     * - Scalars: preserve types (numbers stay numeric)
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
     * - Scalars: return as-is
     * 
     * @param mixed $value
     * @return mixed Canonicalized value
     */
    private static function canonicalizeValue($value)
    {
        if (!is_array($value)) {
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
     * Normalize timestamp to UTC seconds string format (LOCKED SPEC).
     * 
     * CANONICALIZATION RULES:
     * - Format: "Y-m-d H:i:s" (UTC, seconds precision only)
     * - Microseconds are TRUNCATED (not rounded)
     * - Empty/invalid input => empty string
     * 
     * @param mixed $timestamp Can be Carbon instance, DateTime, or string
     * @return string Format: "Y-m-d H:i:s" (UTC, no timezone suffix, no microseconds)
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
                // If already in 'Y-m-d H:i:s' format (no microseconds), assume UTC
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $timestamp)) {
                    return $timestamp;
                }
                // If contains microseconds, truncate (not round)
                if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(\.\d+)?/', $timestamp, $matches)) {
                    return $matches[1]; // Return seconds part only (truncate microseconds)
                }
                // Parse and convert to UTC
                $dt = new \DateTime($timestamp);
                $dt->setTimezone(new \DateTimeZone('UTC'));
            } else {
                return '';
            }

            if ($dt) {
                // Format to seconds precision (truncates microseconds)
                return $dt->format('Y-m-d H:i:s');
            }

            return '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
