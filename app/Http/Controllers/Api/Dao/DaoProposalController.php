<?php

namespace App\Http\Controllers\Api\Dao;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Dao\CreateProposalRequest;
use App\Services\Dao\DaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DomainException;

class DaoProposalController extends ApiController
{
    public function __construct(
        private readonly DaoService $daoService,
    ) {
    }

    public function store(CreateProposalRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $proposal = $this->daoService->createProposal(
                $data['title'],
                $data['description'],
                $data['created_by_did'],
                [
                    'status' => $data['status'] ?? 'draft',
                    'voting_starts_at' => $data['voting_starts_at'] ?? null,
                    'voting_ends_at' => $data['voting_ends_at'] ?? null,
                    'quorum' => $data['quorum'] ?? 0,
                    'metadata' => $data['metadata'] ?? null,
                ],
                null, // created_by: nullable UUID (user.id is integer, cannot map to UUID)
                $this->traceId()
            );

            return $this->successResponse($proposal, 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create proposal: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [];
            if ($request->has('status')) {
                $filters['status'] = $request->input('status');
            }
            if ($request->has('created_by_did')) {
                $filters['created_by_did'] = $request->input('created_by_did');
            }

            $proposals = $this->daoService->listProposals($filters);
            return $this->successResponse($proposals);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve proposals: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $result = $this->daoService->getProposalResults($id);
            return $this->successResponse($result);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve proposal: ' . $e->getMessage(), 500);
        }
    }
}
