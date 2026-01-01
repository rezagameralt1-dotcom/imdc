<?php

namespace App\Http\Controllers\Api\Linking;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Linking\CreateDidNftLinkRequest;
use App\Services\Linking\LinkingService;
use App\Dids\Models\DidProfile;
use App\Nfts\Models\NftToken;
use Illuminate\Http\JsonResponse;

class DidNftLinkController extends ApiController
{
    public function __construct(
        private readonly LinkingService $linkingService,
    ) {
    }

    public function store(CreateDidNftLinkRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Route middleware ensures Admin role, so ownership checks are bypassed
        // Admin can create any links

        try {
            // created_by is optional; if not provided, set to null (user.id is integer, created_by expects UUID)
            $link = $this->linkingService->createDidNftLink(
                $data['did_id'],
                $data['nft_id'],
                $data['role'] ?? 'owner',
                null, // created_by: nullable UUID (user.id is integer, cannot map to UUID)
                $this->traceId()
            );

            return $this->successResponse($link, 201);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create link: ' . $e->getMessage(), 500);
        }
    }
}
