<?php

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Training\CompleteEnrollmentRequest;
use App\Services\Training\TrainingService;
use Illuminate\Http\JsonResponse;
use DomainException;

class EnrollmentController extends ApiController
{
    public function __construct(
        private readonly TrainingService $trainingService,
    ) {
    }

    public function complete(CompleteEnrollmentRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        try {
            $result = $this->trainingService->completeEnrollment(
                $id,
                $data['idempotency_key'],
                $user->id, // actor_user_id
                $this->traceId()
            );
            
            // Check if skill NFT was just created (firstOrCreate returns wasRecentlyCreated)
            $wasRecentlyCreated = isset($result['skill_nft']->wasRecentlyCreated) ? $result['skill_nft']->wasRecentlyCreated : false;
            return $this->successResponse($result, $wasRecentlyCreated ? 201 : 200);
        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'Idempotency key already used')) {
                return $this->errorResponse($e->getMessage(), 409);
            }
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 422;
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to complete enrollment: ' . $e->getMessage(), 500);
        }
    }
}
