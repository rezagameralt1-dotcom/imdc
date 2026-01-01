<?php

namespace App\Nfts\Services;

use App\Nfts\Models\NftToken;
use App\Services\Worm\WormLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NftMintService
{
    public function __construct(
        private readonly WormLogService $wormLogService,
    ) {
    }

    /**
     * Mint a new NFT token
     *
     * @param string $contract
     * @param string $tokenId
     * @param string $ownerUserId
     * @param string|null $metadataUri
     * @return NftToken
     */
    public function mint(string $contract, string $tokenId, string $ownerUserId, ?string $metadataUri = null): NftToken
    {
        return DB::connection('nfts')->transaction(function () use ($contract, $tokenId, $ownerUserId, $metadataUri) {
            // Check if token already exists
            $existing = NftToken::where('contract', $contract)
                ->where('token_id', $tokenId)
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'token' => "Token {$contract}:{$tokenId} already exists"
                ]);
            }

            $token = NftToken::create([
                'contract' => $contract,
                'token_id' => $tokenId,
                'owner_user_id' => $ownerUserId,
                'metadata_uri' => $metadataUri,
                'status' => NftToken::STATUS_MINTED,
            ]);

            // Log to WORM
            $this->wormLogService->log(
                'mint',
                'nft_token',
                $token->id,
                [
                    'contract' => $contract,
                    'token_id' => $tokenId,
                    'owner_user_id' => $ownerUserId,
                    'metadata_uri' => $metadataUri,
                ]
            );

            return $token;
        });
    }
}
