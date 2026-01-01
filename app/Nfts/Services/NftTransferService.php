<?php

namespace App\Nfts\Services;

use App\Nfts\Models\NftToken;
use App\Nfts\Models\NftTransfer;
use App\Services\Worm\WormLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NftTransferService
{
    public function __construct(
        private readonly WormLogService $wormLogService,
    ) {
    }

    /**
     * Transfer an NFT token
     *
     * @param string $tokenUuid
     * @param int|string $toUserId
     * @param int|string $requestedByUserId
     * @param string|null $idempotencyKey
     * @return array ['transfer' => NftTransfer, 'token' => NftToken]
     */
    public function transfer(string $tokenUuid, int|string $toUserId, int|string $requestedByUserId, ?string $idempotencyKey = null): array
    {
        // Normalize idempotency_key
        $idempotencyKey = $idempotencyKey ? substr(trim($idempotencyKey), 0, 128) : null;

        return DB::connection('nfts')->transaction(function () use ($tokenUuid, $toUserId, $requestedByUserId, $idempotencyKey) {
            // STRICT IDEMPOTENCY: Check for existing transfer INSIDE transaction
            // Check BEFORE any side-effects to ensure true idempotency
            if ($idempotencyKey) {
                // First, try to find existing transfer (without lock to avoid blocking if not exists)
                $existingTransfer = NftTransfer::where('idempotency_key', $idempotencyKey)
                    ->where('requested_by_user_id', $requestedByUserId)
                    ->first();

                if ($existingTransfer) {
                    // Lock the existing row to prevent concurrent modifications
                    $existingTransfer = NftTransfer::where('idempotency_key', $idempotencyKey)
                        ->where('requested_by_user_id', $requestedByUserId)
                        ->lockForUpdate()
                        ->first();

                    if ($existingTransfer) {
                        // Verify payload matches
                        if ($existingTransfer->token_id !== $tokenUuid || $existingTransfer->to_user_id !== $toUserId) {
                            throw ValidationException::withMessages([
                                'idempotency_key' => 'Idempotency key already used with different payload'
                            ]);
                        }

                        $token = $existingTransfer->token;
                        return ['transfer' => $existingTransfer, 'token' => $token, 'is_new' => false];
                    }
                }
            }

            $token = NftToken::findOrFail($tokenUuid);

            // Check if token can be transferred
            if ($token->status === NftToken::STATUS_BURNED) {
                throw ValidationException::withMessages([
                    'token' => 'Token has been burned and cannot be transferred'
                ]);
            }

            $fromUserId = $token->owner_user_id;

            // Double-check idempotency before creating (race condition protection)
            // This prevents the unique constraint violation in most cases
            if ($idempotencyKey) {
                $doubleCheck = NftTransfer::where('idempotency_key', $idempotencyKey)
                    ->where('requested_by_user_id', $requestedByUserId)
                    ->lockForUpdate()
                    ->first();

                if ($doubleCheck) {
                    // Another request created it between our first check and now
                    // Verify payload matches
                    if ($doubleCheck->token_id !== $tokenUuid || $doubleCheck->to_user_id !== $toUserId) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'Idempotency key already used with different payload'
                        ]);
                    }

                    $token = $doubleCheck->token;
                    return ['transfer' => $doubleCheck, 'token' => $token, 'is_new' => false];
                }
            }

            // Create transfer record
            $isNewTransfer = true;
            try {
                $transfer = NftTransfer::create([
                    'token_id' => $token->id,
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'idempotency_key' => $idempotencyKey,
                    'requested_by_user_id' => $requestedByUserId,
                    'status' => NftTransfer::STATUS_COMMITTED,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle unique constraint violation (race condition - another request created it)
                // This is a safety net in case lockForUpdate didn't prevent the race
                if ($e->getCode() === '23505' || str_contains($e->getMessage(), '23505')) {
                    if ($idempotencyKey) {
                        // Fetch the existing transfer that was just created by another request
                        $existingTransfer = NftTransfer::where('idempotency_key', $idempotencyKey)
                            ->where('requested_by_user_id', $requestedByUserId)
                            ->first();

                        if ($existingTransfer) {
                            // Verify payload matches
                            if ($existingTransfer->token_id !== $tokenUuid || $existingTransfer->to_user_id !== $toUserId) {
                                throw ValidationException::withMessages([
                                    'idempotency_key' => 'Idempotency key already used with different payload'
                                ]);
                            }

                            $isNewTransfer = false;
                            $transfer = $existingTransfer;
                            $token = $transfer->token;
                            return ['transfer' => $transfer, 'token' => $token, 'is_new' => false];
                        }
                    }
                    // Re-throw if it's not an idempotency-related unique constraint or transfer not found
                    throw $e;
                } else {
                    throw $e;
                }
            }

            // If this is a new transfer, update token and log
            if ($isNewTransfer) {
                $token->owner_user_id = $toUserId;
                $token->status = NftToken::STATUS_TRANSFERRED;
                $token->save();

                // Log to WORM
                $this->wormLogService->log(
                    'transfer',
                    'nft_token',
                    $token->id,
                    [
                        'transfer_id' => $transfer->id,
                        'from_user_id' => $fromUserId,
                        'to_user_id' => $toUserId,
                        'requested_by_user_id' => $requestedByUserId,
                    ]
                );
            }

            return ['transfer' => $transfer, 'token' => $token, 'is_new' => $isNewTransfer];
        });
    }
}
