<?php

namespace App\Nfts\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\IdempotencyKey;
use App\Nfts\Http\Requests\MintNftRequest;
use App\Nfts\Http\Requests\TransferNftRequest;
use App\Nfts\Models\NftToken;
use App\Nfts\Services\NftMintService;
use App\Nfts\Services\NftTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $userId = $request->user()->id;

            // Check for existing idempotency record FIRST (before any side-effects)
            if ($idempotencyKey) {
                $idempotencyKey = substr(trim($idempotencyKey), 0, 128);
                $requestHash = $this->hashRequest($data);

                $existingIdempotency = IdempotencyKey::where('user_id', $userId)
                    ->where('scope', 'nfts.transfer')
                    ->where('key', $idempotencyKey)
                    ->first();

                if ($existingIdempotency) {
                    // Verify request hash matches (same payload)
                    if ($existingIdempotency->request_hash !== $requestHash) {
                        return $this->errorResponse(
                            'Idempotency key already used with different payload',
                            409,
                            ['fields' => ['idempotency_key' => ['Idempotency key already used with different request payload']]]
                        );
                    }

                    // Return stored response with HTTP 200 (force 200 on replay)
                    // Update trace_id to current request's trace_id for better observability
                    $responseBody = json_decode($existingIdempotency->response_body, true);
                    if (is_array($responseBody)) {
                        $responseBody['trace_id'] = $this->traceId();
                    }
                    return response()->json($responseBody, 200);
                }
            }

            // No existing idempotency record, proceed with transfer
            $result = $this->transferService->transfer(
                $data['token_uuid'],
                $data['to_user_id'],
                $userId,
                $idempotencyKey
            );

            // Build response
            $responseData = [
                'transfer' => $result['transfer'],
                'token' => $result['token'],
            ];
            $statusCode = ($result['is_new'] ?? true) ? 201 : 200;
            $response = $this->successResponse($responseData, $statusCode);

            // Store idempotency record if key is present
            if ($idempotencyKey) {
                $requestHash = $this->hashRequest($data);
                // Get the actual response data structure
                $responseArray = $response->getData(true);
                $responseBody = json_encode($responseArray);

                try {
                    IdempotencyKey::create([
                        'user_id' => $userId,
                        'scope' => 'nfts.transfer',
                        'key' => $idempotencyKey,
                        'request_hash' => $requestHash,
                        'response_code' => $statusCode,
                        'response_body' => $responseBody,
                        'resource_id' => $result['transfer']->id,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle unique constraint violation (race condition)
                    // Another request stored the idempotency key between our check and insert
                    if ($e->getCode() === '23000' || str_contains($e->getMessage(), '23000') || 
                        $e->getCode() === '23505' || str_contains($e->getMessage(), '23505')) {
                        // Fetch the existing record and return its stored response
                        $existingIdempotency = IdempotencyKey::where('user_id', $userId)
                            ->where('scope', 'nfts.transfer')
                            ->where('key', $idempotencyKey)
                            ->first();

                        if ($existingIdempotency && $existingIdempotency->request_hash === $requestHash) {
                            $storedResponse = json_decode($existingIdempotency->response_body, true);
                            return response()->json($storedResponse, 200);
                        }
                    }
                    // Re-throw if it's not a unique constraint or hash mismatch
                    throw $e;
                }
            }

            return $response;
        } catch (ValidationException $e) {
            // Check if it's an idempotency key conflict from service
            // If we have an idempotency key, check if it's a true replay vs conflict
            if ($idempotencyKey && $e->errors() && isset($e->errors()['idempotency_key'])) {
                $requestHash = $this->hashRequest($data);
                
                // Check if this is a true replay (same request_hash)
                $existingIdempotency = IdempotencyKey::where('user_id', $userId)
                    ->where('scope', 'nfts.transfer')
                    ->where('key', $idempotencyKey)
                    ->first();
                
                if ($existingIdempotency && $existingIdempotency->request_hash === $requestHash) {
                    // True replay - return stored response with HTTP 200
                    $responseBody = json_decode($existingIdempotency->response_body, true);
                    if (is_array($responseBody)) {
                        $responseBody['trace_id'] = $this->traceId();
                    }
                    return response()->json($responseBody, 200);
                }
                
                // Different payload with same key - return 409 conflict
                return $this->errorResponse(
                    'Idempotency key already used with different payload',
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

    /**
     * Hash request payload for idempotency validation
     */
    private function hashRequest(array $data): string
    {
        // Normalize data for hashing (exclude idempotency_key itself)
        $normalized = [
            'token_uuid' => $data['token_uuid'] ?? null,
            'to_user_id' => $data['to_user_id'] ?? null,
        ];
        ksort($normalized);
        return hash('sha256', json_encode($normalized));
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

    public function show(string $token)
    {
        $this->authorize('read', NftToken::class);

        $nftToken = NftToken::where('id', $token)
            ->orWhere('token_id', $token)
            ->firstOrFail();

        return $this->successResponse($nftToken);
    }
}
