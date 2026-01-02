<?php

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\ApiController;
use App\Services\Training\TrainingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserTrainingController extends ApiController
{
    public function __construct(
        private readonly TrainingService $trainingService,
    ) {
    }

    public function enrollments(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $enrollments = $this->trainingService->getUserEnrollments($user->id);
            
            return $this->successResponse($enrollments);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve enrollments: ' . $e->getMessage(), 500);
        }
    }

    public function skillNfts(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $skillNfts = $this->trainingService->getUserSkillNfts($user->id);
            
            return $this->successResponse($skillNfts);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve skill NFTs: ' . $e->getMessage(), 500);
        }
    }
}
