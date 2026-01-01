<?php

namespace App\Support;

/**
 * DID Feature Gate Helper
 * 
 * Reads FEATURE_DID environment variable at runtime (bypasses config cache).
 * This ensures feature flag changes take effect immediately without requiring
 * config cache clearing in all scenarios.
 */
class DidFeatureGate
{
    /**
     * Check if DID feature is enabled
     * 
     * Reads env('FEATURE_DID') directly at runtime and parses it strictly.
     * Accepts: true, 1, "true", "1", "yes" (case-insensitive)
     * 
     * @return bool
     */
    public static function isEnabled(): bool
    {
        $value = env('FEATURE_DID', false);
        
        if ($value === true || $value === 1) {
            return true;
        }
        
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['true', '1', 'yes'], true);
        }
        
        return false;
    }
}
