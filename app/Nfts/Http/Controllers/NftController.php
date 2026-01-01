<?php

namespace App\Nfts\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Nfts\Http\Requests\MintNftRequest;
use App\Nfts\Http\Requests\TransferNftRequest;
use App\Nfts\Models\NftToken;
use App\Nfts\Services\NftMintService;
use App\Nfts\Services\NftTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NftController extends ApiController
{
    public function __construct(
        private readonly NftMintService $mintService,
        private readonly NftTransferService $transferService,
    ) {
    }

    public function mint(MintNftRequest $request)
    {
        try {
            $this->authorize('mint', NftToken::class);

            $data = $request->validated();
            $token = $this->mintService->mint(
                $data['contract'],
                $data['token_id'],
                $data['owner_user_id'],
                $data['metadata_uri'] ?? null
            );

            return $this->successResponse($token, 201);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                ['fields' => $e->errors()]
            );
        } catch (\Exception $e) {
            \Log::error('NFT mint failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('NFT mint failed', 500);
        }
    }

    public function transfer(TransferNftRequest $request)
    {
        try {
            $this->authorize('transfer', NftToken::class);

            $data = $request->validated();
            $idempotencyKey = $data['idempotency_key'] ?? null;

            $result = $this->transferService->transfer(
                $data['token_uuid'],
                $data['to_user_id'],
                $request->user()->id,
                $idempotencyKey
            );

            // Return 200 for idempotent returns, 201 for new transfers
            $statusCode = ($result['is_new'] ?? true) ? 201 : 200;
            return $this->successResponse([
                'transfer' => $result['transfer'],
                'token' => $result['token'],
            ], $statusCode);
        } catch (ValidationException $e) {
            // Check if it's an idempotency key conflict
            if ($e->errors() && isset($e->errors()['idempotency_key'])) {
                return $this->errorResponse(
                    'Idempotency key conflict',
                    409,
                    ['fields' => $e->errors()]
                );
            }
            return $this->errorResponse(
                'Validation failed',
                422,
                ['fields' => $e->errors()]
            );
        } catch (\Exception $e) {
            \Log::error('NFT transfer failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('NFT transfer failed', 500);
        }
    }

    public function index(Request $request)
    {
        $this->authorize('read', NftToken::class);

        $query = NftToken::query();

        if ($request->filled('owner_user_id')) {
            $query->where('owner_user_id', $request->string('owner_user_id'));
        }

        return $this->successResponse($query->orderByDesc('created_at')->paginate($request->integer('per_page', 15)));
    }
}
