<?php

namespace App\Services\Did;

use App\Dids\Models\DidProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DidService
{
    /**
     * Get or create DID profile for user
     *
     * @param int|string $userId
     * @param array $data Optional: wallet_address, display_name, metadata_json
     * @return DidProfile
     */
    public function getOrCreate(int|string $userId, array $data = []): DidProfile
    {
        return DB::connection('core')->transaction(function () use ($userId, $data) {
            $profile = DidProfile::where('user_id', $userId)->first();

            if (!$profile) {
                // Generate DID: did:imdc:<uuid>
                $did = 'did:imdc:' . (string) Str::uuid();

                $profile = DidProfile::create([
                    'user_id' => $userId,
                    'did' => $did,
                    'wallet_address' => $data['wallet_address'] ?? null,
                    'display_name' => $data['display_name'] ?? null,
                    'metadata_json' => $data['metadata_json'] ?? null,
                ]);
            }

            return $profile;
        });
    }

    /**
     * Get DID profile for user
     *
     * @param int|string $userId
     * @return DidProfile|null
     */
    public function getByUserId(int|string $userId): ?DidProfile
    {
        return DidProfile::where('user_id', $userId)->first();
    }

    /**
     * Update DID profile
     *
     * @param int|string $userId
     * @param array $data wallet_address, display_name, metadata_json
     * @return DidProfile
     */
    public function update(int|string $userId, array $data): DidProfile
    {
        return DB::connection('core')->transaction(function () use ($userId, $data) {
            $profile = DidProfile::where('user_id', $userId)->firstOrFail();

            $updateData = [];
            if (isset($data['wallet_address'])) {
                $updateData['wallet_address'] = $data['wallet_address'];
            }
            if (isset($data['display_name'])) {
                $updateData['display_name'] = $data['display_name'];
            }
            if (isset($data['metadata_json'])) {
                $updateData['metadata_json'] = $data['metadata_json'];
            }

            $profile->update($updateData);

            return $profile->fresh();
        });
    }
}
