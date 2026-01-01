<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Pharma\CheckInteractionsRequest;
use App\Services\Pharma\PharmaService;
use Illuminate\Http\JsonResponse;
use DomainException;

class PharmaCheckController extends ApiController
{
    public function __construct(
        private readonly PharmaService $pharmaService,
    ) {
    }

    public function check(CheckInteractionsRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->pharmaService->checkInteractions(
                $data['drug_ids'],
                null, // actor_user_id: nullable UUID (user.id is integer, cannot map to UUID)
                $this->traceId()
            );

            return $this->successResponse([
                'drugs' => $result['drugs'],
                'interactions' => $result['interactions'],
                'disclaimer' => $this->pharmaService->getDisclaimer(),
            ]);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to check interactions: ' . $e->getMessage(), 500);
        }
    }
}
