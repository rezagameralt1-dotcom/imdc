<?php

namespace App\Http\Controllers\Api\Dao;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Dao\CastVoteRequest;
use App\Services\Dao\DaoService;
use Illuminate\Http\JsonResponse;
use DomainException;

class DaoVoteController extends ApiController
{
    public function __construct(
        private readonly DaoService $daoService,
    ) {
    }

    public function store(CastVoteRequest $request, string $proposalId): JsonResponse
    {
        $data = $request->validated();

        try {
            $vote = $this->daoService->castVote(
                $proposalId,
                $data['voter_did'],
                $data['vote'],
                $data['weight'] ?? 1,
                null, // created_by: nullable UUID (user.id is integer, cannot map to UUID)
                $this->traceId()
            );

            return $this->successResponse($vote, 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to cast vote: ' . $e->getMessage(), 500);
        }
    }
}
