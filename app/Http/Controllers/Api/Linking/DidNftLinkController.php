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

        // Check permission
        if (!$user->hasPermission('linking.admin')) {
            if (!$user->hasPermission('linking.create')) {
                return $this->errorResponse('Unauthorized: linking.create permission required', 403);
            }

            // Ownership checks: both DID and NFT must belong to user
            $didProfile = DidProfile::where('id', $data['did_id'])
                ->where('user_id', $user->id)
                ->first();

            if (!$didProfile) {
                return $this->errorResponse('Unauthorized: DID does not belong to user', 403);
            }

            $nft = NftToken::find($data['nft_id']);
            if (!$nft) {
                return $this->errorResponse('NFT not found', 404);
            }

            if ($nft->owner_user_id != $user->id) {
                return $this->errorResponse('Unauthorized: NFT does not belong to user', 403);
            }
        }

        try {
            $link = $this->linkingService->createDidNftLink(
                $data['did_id'],
                $data['nft_id'],
                $data['role'] ?? 'owner',
                $user->id,
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
