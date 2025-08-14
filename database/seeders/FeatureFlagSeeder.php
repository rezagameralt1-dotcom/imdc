<?php
namespace Database\Seeders;

use App\Support\FeatureFlags;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        // Seed from ENV fallbacks if present
        $features = [
            'EXCHANGE' => env('FEATURE_EXCHANGE', null),
            'VR_TOUR'  => env('FEATURE_VR_TOUR', null),
            'MEZON'    => env('FEATURE_MEZON', null),
            'DAO'      => env('FEATURE_DAO', null),
            'PHARMA'   => env('FEATURE_PHARMA', null),
        ];
        foreach ($features as $key => $envVal) {
            if ($envVal !== null) {
                FeatureFlags::set($key, filter_var($envVal, FILTER_VALIDATE_BOOLEAN));
            }
        }
    }
}
