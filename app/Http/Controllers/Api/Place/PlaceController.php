<?php

namespace App\Http\Controllers\Api\Place;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Place\CreatePlaceRequest;
use App\Http\Requests\Place\LinkToPlaceRequest;
use App\Services\Place\PlaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DomainException;

class PlaceController extends ApiController
{
    public function __construct(
        private readonly PlaceService $placeService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [];
            if ($request->has('type')) {
                $filters['type'] = $request->input('type');
            }
            if ($request->has('owner_did')) {
                $filters['owner_did'] = $request->input('owner_did');
            }
            if ($request->has('latitude_min') && $request->has('latitude_max') &&
                $request->has('longitude_min') && $request->has('longitude_max')) {
                $filters['latitude_min'] = $request->input('latitude_min');
                $filters['latitude_max'] = $request->input('latitude_max');
                $filters['longitude_min'] = $request->input('longitude_min');
                $filters['longitude_max'] = $request->input('longitude_max');
            }

            $places = $this->placeService->listPlaces($filters);
            
            return $this->successResponse($places);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve places: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $place = $this->placeService->getPlace($id);
            
            return $this->successResponse($place);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve place: ' . $e->getMessage(), 500);
        }
    }

    public function store(CreatePlaceRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $place = $this->placeService->createPlace(
                $data['name'],
                (float) $data['latitude'],
                (float) $data['longitude'],
                $data,
                null, // actorDid: optional
                null, // actorUserId: user.id is integer, cannot map to UUID
                $this->traceId()
            );
            
            return $this->successResponse($place, 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create place: ' . $e->getMessage(), 500);
        }
    }

    public function linkNft(LinkToPlaceRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $link = $this->placeService->linkToPlace(
                $id,
                $data['nft_id'] ?? null,
                $data['did_id'] ?? null,
                null, // actorUserId: user.id is integer, cannot map to UUID
                $this->traceId()
            );
            
            return $this->successResponse($link, $link->wasRecentlyCreated ? 201 : 200);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to link to place: ' . $e->getMessage(), 500);
        }
    }
}
