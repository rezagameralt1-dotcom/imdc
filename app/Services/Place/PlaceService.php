<?php

namespace App\Services\Place;

use App\Models\Place;
use App\Models\PlaceEvent;
use App\Models\PlaceLink;
use App\Dids\Models\DidProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;

class PlaceService
{
    /**
     * List places
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listPlaces(array $filters = [])
    {
        $query = Place::on('core');

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['owner_did'])) {
            $this->validateUuid($filters['owner_did'], 'owner_did');
            $query->where('owner_did', $filters['owner_did']);
        }

        if (isset($filters['latitude_min']) && isset($filters['latitude_max']) &&
            isset($filters['longitude_min']) && isset($filters['longitude_max'])) {
            $query->whereBetween('latitude', [$filters['latitude_min'], $filters['latitude_max']])
                ->whereBetween('longitude', [$filters['longitude_min'], $filters['longitude_max']]);
        }

        return $query->orderBy('name', 'asc')->get();
    }

    /**
     * Get place by ID
     *
     * @param string $placeId
     * @return Place
     * @throws DomainException
     */
    public function getPlace(string $placeId): Place
    {
        $this->validateUuid($placeId, 'place_id');

        // Query place on core DB, explicitly set connection and table
        $place = Place::on('core')
            ->where('id', $placeId)
            ->first();
        
        if (!$place) {
            throw new DomainException("Place not found: {$placeId}");
        }

        // Load links separately to avoid relationship issues during initial query
        // This ensures the place is returned even if links relationship has issues
        try {
            $place->load(['links' => function ($query) {
                $query->on('core');
            }]);
        } catch (\Exception $e) {
            // If relationship loading fails, continue without links
            // This ensures the place is still returned even if links table has issues
        }

        return $place;
    }

    /**
     * Create place (idempotent via name + coordinates)
     *
     * @param string $name
     * @param float $latitude
     * @param float $longitude
     * @param array $options
     * @param string|null $actorDid
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return Place
     * @throws DomainException
     */
    public function createPlace(
        string $name,
        float $latitude,
        float $longitude,
        array $options = [],
        ?string $actorDid = null,
        ?string $actorUserId = null,
        ?string $traceId = null
    ): Place {
        // Validate coordinates
        if ($latitude < -90 || $latitude > 90) {
            throw new DomainException("Invalid latitude: must be between -90 and 90");
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new DomainException("Invalid longitude: must be between -180 and 180");
        }

        // Validate owner_did if provided
        if (isset($options['owner_did'])) {
            $this->validateUuid($options['owner_did'], 'owner_did');
            $didProfile = DidProfile::on('core')->find($options['owner_did']);
            if (!$didProfile) {
                throw new DomainException("DID not found: {$options['owner_did']}");
            }
        }

        return DB::connection('core')->transaction(function () use ($name, $latitude, $longitude, $options, $actorDid, $actorUserId, $traceId) {
            // Idempotency: same name + coordinates (within small tolerance)
            $tolerance = 0.0001; // ~11 meters
            $place = Place::on('core')
                ->where('name', $name)
                ->whereBetween('latitude', [$latitude - $tolerance, $latitude + $tolerance])
                ->whereBetween('longitude', [$longitude - $tolerance, $longitude + $tolerance])
                ->first();

            if ($place) {
                // Update mutable fields
                $place->update([
                    'description' => $options['description'] ?? $place->description,
                    'type' => $options['type'] ?? $place->type,
                    'altitude' => $options['altitude'] ?? $place->altitude,
                    'owner_did' => $options['owner_did'] ?? $place->owner_did,
                    'metadata' => $options['metadata'] ?? $place->metadata,
                ]);
            } else {
                $place = Place::on('core')->create([
                    'name' => $name,
                    'description' => $options['description'] ?? null,
                    'type' => $options['type'] ?? 'building',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'altitude' => $options['altitude'] ?? null,
                    'owner_did' => $options['owner_did'] ?? null,
                    'metadata' => $options['metadata'] ?? null,
                ]);
            }

            $this->logEvent('place_created_or_updated', $place->toArray(), $actorDid, $actorUserId, $traceId);
            return $place;
        });
    }

    /**
     * Link NFT or DID to place (idempotent)
     *
     * @param string $placeId
     * @param string|null $nftId
     * @param string|null $didId
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return PlaceLink
     * @throws DomainException
     */
    public function linkToPlace(
        string $placeId,
        ?string $nftId = null,
        ?string $didId = null,
        ?string $actorUserId = null,
        ?string $traceId = null
    ): PlaceLink {
        $this->validateUuid($placeId, 'place_id');

        // Validate at least one link target
        if (!$nftId && !$didId) {
            throw new DomainException("Either nft_id or did_id must be provided");
        }

        // Validate place exists
        $place = Place::on('core')->find($placeId);
        if (!$place) {
            throw new DomainException("Place not found: {$placeId}");
        }

        // Validate UUIDs if provided
        if ($nftId) {
            $this->validateUuid($nftId, 'nft_id');
        }
        if ($didId) {
            $this->validateUuid($didId, 'did_id');
            $didProfile = DidProfile::on('core')->find($didId);
            if (!$didProfile) {
                throw new DomainException("DID not found: {$didId}");
            }
        }

        return DB::connection('core')->transaction(function () use ($placeId, $nftId, $didId, $actorUserId, $traceId) {
            // Determine link type
            $linkType = $nftId ? 'nft' : 'did';

            // Idempotency: unique constraint on (place_id, nft_id, did_id)
            $link = PlaceLink::on('core')->firstOrCreate(
                [
                    'place_id' => $placeId,
                    'nft_id' => $nftId,
                    'did_id' => $didId,
                ],
                [
                    'link_type' => $linkType,
                    'metadata' => null,
                    'created_by' => null, // user.id is integer, created_by expects UUID
                ]
            );

            if (!$link->wasRecentlyCreated) {
                // Update metadata if provided
                $link->update([
                    'metadata' => null, // Can be extended later
                ]);
            }

            $this->logEvent('place_linked', $link->toArray(), $didId, $actorUserId, $traceId);
            return $link;
        });
    }

    /**
     * Validate UUID
     *
     * @param string $uuid
     * @param string $name
     * @return void
     * @throws DomainException
     */
    private function validateUuid(string $uuid, string $name): void
    {
        if (!Str::isUuid($uuid)) {
            throw new DomainException("Invalid {$name} format: must be UUID");
        }
    }

    /**
     * Log place event (append-only)
     *
     * @param string $eventType
     * @param array $payload
     * @param string|null $actorDid
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return void
     */
    private function logEvent(
        string $eventType,
        array $payload,
        ?string $actorDid = null,
        ?string $actorUserId = null,
        ?string $traceId = null
    ): void {
        PlaceEvent::create([
            'event_type' => $eventType,
            'payload' => $payload,
            'actor_did' => $actorDid,
            'actor_user_id' => $actorUserId,
            'trace_id' => $traceId,
            'created_at' => now(),
        ]);
    }
}
