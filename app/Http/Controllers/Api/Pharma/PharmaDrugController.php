<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\ApiController;
use App\Services\Pharma\PharmaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DomainException;

class PharmaDrugController extends ApiController
{
    public function __construct(
        private readonly PharmaService $pharmaService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [];
            if ($request->has('name')) {
                $filters['name'] = $request->input('name');
            }
            if ($request->has('generic_name')) {
                $filters['generic_name'] = $request->input('generic_name');
            }

            $drugs = $this->pharmaService->listDrugs($filters);
            
            return $this->successResponse([
                'drugs' => $drugs,
                'disclaimer' => $this->pharmaService->getDisclaimer(),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve drugs: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $drug = $this->pharmaService->getDrug($id);
            
            return $this->successResponse([
                'drug' => $drug,
                'disclaimer' => $this->pharmaService->getDisclaimer(),
            ]);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve drug: ' . $e->getMessage(), 500);
        }
    }
}
