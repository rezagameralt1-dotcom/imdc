<?php

namespace App\Http\Controllers\Api\Linking;

use App\Http\Controllers\ApiController;
use App\Services\Linking\LinkingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkingQueryController extends ApiController
{
    public function __construct(
        private readonly LinkingService $linkingService,
    ) {
    }

    public function getByDid(Request $request, string $didId): JsonResponse
    {
        // Route middleware ensures Admin or Auditor role

        try {
            $links = $this->linkingService->getLinksForDid($didId);
            return $this->successResponse($links);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve links: ' . $e->getMessage(), 500);
        }
    }

    public function getByOrder(Request $request, string $orderId): JsonResponse
    {
        // Route middleware ensures Admin or Auditor role

        try {
            $links = $this->linkingService->getLinksForOrder($orderId);
            return $this->successResponse($links);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve links: ' . $e->getMessage(), 500);
        }
    }

    public function getByNft(Request $request, string $nftId): JsonResponse
    {
        // Route middleware ensures Admin or Auditor role

        try {
            $links = $this->linkingService->getLinksForNft($nftId);
            return $this->successResponse($links);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve links: ' . $e->getMessage(), 500);
        }
    }
}
