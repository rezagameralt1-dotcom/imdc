<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class Settings
{
    public static function get(string $key, $default = null)
    {
        $settings = Cache::remember('settings.all', 300, function () {
            return Setting::query()->pluck('value', 'key')->toArray();
        });

        return $settings[$key] ?? $default;
    }

    public static function forget(): void
    {
        Cache::forget('settings.all');
    }
}

