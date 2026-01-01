<?php

namespace App\Support;

/**
 * DID Feature Gate Helper
 * 
 * Checks if DID feature is enabled by reading from environment or config.
 * This works for both CLI and HTTP requests (php-fpm).
 */
class DidFeatureGate
{
    /**
     * Check if DID feature is enabled
     * 
     * Checks env('FEATURE_DID') first (from .env file, works for php-fpm),
     * then falls back to config('did.enabled').
     * 
     * Accepts: true, 1, "true", "1", "yes", "on" (case-insensitive)
     * 
     * @return bool
     */
    public static function isEnabled(): bool
    {
        // First check env() - this reads from .env file (works for php-fpm workers)
        $envValue = env('FEATURE_DID');
        
        if ($envValue !== null) {
            return self::parseBoolean($envValue);
        }
        
        // Fallback to config (in case env is not set but config is)
        $configValue = config('did.enabled', false);
        return self::parseBoolean($configValue);
    }
    
    /**
     * Parse boolean value strictly
     * 
     * @param mixed $value
     * @return bool
     */
    private static function parseBoolean($value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }
        
        if ($value === false || $value === 0 || $value === null) {
            return false;
        }
        
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['true', '1', 'yes', 'on'], true);
        }
        
        return false;
    }
}
