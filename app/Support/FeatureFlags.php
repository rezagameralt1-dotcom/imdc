<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeatureFlags
{
    public static function enabled(string $key, ?bool $default = null): bool
    {
        $key = strtoupper(trim($key));
        // 1) ENV fallback if present (e.g., FEATURE_EXCHANGE=true)
        $envName = 'FEATURE_' . $key;
        $env = env($envName, null);
        if ($env !== null) {
            return filter_var($env, FILTER_VALIDATE_BOOLEAN);
        }

        // 2) DB cached 30s
        $val = Cache::remember("ff:{$key}", 30, function () use ($key) {
            try {
                $row = DB::table('feature_flags')->where('key', $key)->first();
                return $row ? (bool) $row->enabled : null;
            } catch (\Throwable $e) {
                return null;
            }
        });

        // 3) default if nothing found
        if ($val === null) {
            return (bool) ($default ?? false);
        }
        return (bool) $val;
    }

    public static function set(string $key, bool $state, array $meta = []): void
    {
        $key = strtoupper(trim($key));
        DB::table('feature_flags')->updateOrInsert(
            ['key' => $key],
            ['enabled' => $state, 'meta' => $meta ? json_encode($meta) : null, 'updated_at' => now(), 'created_at' => now()]
        );
        Cache::forget("ff:{$key}");
    }

    public static function all(): array
    {
        try {
            return DB::table('feature_flags')->orderBy('key')->get()->map(function ($r) {
                return ['key' => $r->key, 'enabled' => (bool) $r->enabled, 'meta' => $r->meta ? json_decode($r->meta, true) : null];
            })->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
