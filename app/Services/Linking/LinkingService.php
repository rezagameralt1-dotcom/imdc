<?php

namespace App\Services\Linking;

use App\Models\DidOrderLink;
use App\Models\DidNftLink;
use App\Models\OrderNftLink;
use App\Models\LinkingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;

class LinkingService
{
    /**
     * Create or get DID-Order link
     *
     * @param string $didId
     * @param string $orderId
     * @param string $scope
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return DidOrderLink
     * @throws DomainException
     */
    public function createDidOrderLink(
        string $didId,
        string $orderId,
        string $scope = 'ownership',
        ?string $actorUserId = null,
        ?string $traceId = null
    ): DidOrderLink {
        // Validate UUIDs
        if (!Str::isUuid($didId)) {
            throw new DomainException("Invalid did_id format: must be UUID");
        }
        if (!Str::isUuid($orderId)) {
            throw new DomainException("Invalid order_id format: must be UUID");
        }
        if ($actorUserId && !Str::isUuid($actorUserId)) {
            throw new DomainException("Invalid created_by format: must be UUID");
        }

        return DB::connection('core')->transaction(function () use ($didId, $orderId, $scope, $actorUserId, $traceId) {
            $link = DidOrderLink::firstOrCreate(
                [
                    'did_id' => $didId,
                    'order_id' => $orderId,
                    'scope' => $scope,
                ],
                [
                    'order_db_connection' => 'orders',
                    'created_by' => $actorUserId,
                ]
            );

            // Log event
            $this->logLinkingEvent('did_order_linked', [
                'did_id' => $didId,
                'order_id' => $orderId,
                'scope' => $scope,
            ], $actorUserId, $traceId);

            return $link;
        });
    }

    /**
     * Create or get DID-NFT link
     *
     * @param string $didId
     * @param string $nftId
     * @param string $role
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return DidNftLink
     * @throws DomainException
     */
    public function createDidNftLink(
        string $didId,
        string $nftId,
        string $role = 'owner',
        ?string $actorUserId = null,
        ?string $traceId = null
    ): DidNftLink {
        // Validate UUIDs
        if (!Str::isUuid($didId)) {
            throw new DomainException("Invalid did_id format: must be UUID");
        }
        if (!Str::isUuid($nftId)) {
            throw new DomainException("Invalid nft_id format: must be UUID");
        }
        if ($actorUserId && !Str::isUuid($actorUserId)) {
            throw new DomainException("Invalid created_by format: must be UUID");
        }

        return DB::connection('core')->transaction(function () use ($didId, $nftId, $role, $actorUserId, $traceId) {
            $link = DidNftLink::firstOrCreate(
                [
                    'did_id' => $didId,
                    'nft_id' => $nftId,
                    'role' => $role,
                ],
                [
                    'nft_db_connection' => 'nfts',
                    'created_by' => $actorUserId,
                ]
            );

            // Log event
            $this->logLinkingEvent('did_nft_linked', [
                'did_id' => $didId,
                'nft_id' => $nftId,
                'role' => $role,
            ], $actorUserId, $traceId);

            return $link;
        });
    }

    /**
     * Create or get Order-NFT link
     *
     * @param string $orderId
     * @param string $nftId
     * @param string $purpose
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return OrderNftLink
     * @throws DomainException
     */
    public function createOrderNftLink(
        string $orderId,
        string $nftId,
        string $purpose = 'fulfillment',
        ?string $actorUserId = null,
        ?string $traceId = null
    ): OrderNftLink {
        // Validate UUIDs
        if (!Str::isUuid($orderId)) {
            throw new DomainException("Invalid order_id format: must be UUID");
        }
        if (!Str::isUuid($nftId)) {
            throw new DomainException("Invalid nft_id format: must be UUID");
        }
        if ($actorUserId && !Str::isUuid($actorUserId)) {
            throw new DomainException("Invalid created_by format: must be UUID");
        }

        return DB::connection('core')->transaction(function () use ($orderId, $nftId, $purpose, $actorUserId, $traceId) {
            $link = OrderNftLink::firstOrCreate(
                [
                    'order_id' => $orderId,
                    'nft_id' => $nftId,
                    'purpose' => $purpose,
                ],
                [
                    'orders_db_connection' => 'orders',
                    'nfts_db_connection' => 'nfts',
                    'created_by' => $actorUserId,
                ]
            );

            // Log event
            $this->logLinkingEvent('order_nft_linked', [
                'order_id' => $orderId,
                'nft_id' => $nftId,
                'purpose' => $purpose,
            ], $actorUserId, $traceId);

            return $link;
        });
    }

    /**
     * Get all links for a DID
     *
     * @param string $didId
     * @return array
     * @throws DomainException
     */
    public function getLinksForDid(string $didId): array
    {
        // Validate UUID
        if (!Str::isUuid($didId)) {
            throw new DomainException("Invalid did_id format: must be UUID");
        }

        // Query using core connection (models have connection='core' set)
        $orderLinks = DidOrderLink::on('core')->where('did_id', $didId)->get();
        $nftLinks = DidNftLink::on('core')->where('did_id', $didId)->get();

        return [
            'did_id' => $didId,
            'order_links' => $orderLinks->toArray(),
            'nft_links' => $nftLinks->toArray(),
        ];
    }

    /**
     * Get all links for an Order
     *
     * @param string $orderId
     * @return array
     * @throws DomainException
     */
    public function getLinksForOrder(string $orderId): array
    {
        // Validate UUID
        if (!Str::isUuid($orderId)) {
            throw new DomainException("Invalid order_id format: must be UUID");
        }

        // Query using core connection (models have connection='core' set)
        $didLinks = DidOrderLink::on('core')->where('order_id', $orderId)->get();
        $nftLinks = OrderNftLink::on('core')->where('order_id', $orderId)->get();

        return [
            'order_id' => $orderId,
            'did_links' => $didLinks->toArray(),
            'nft_links' => $nftLinks->toArray(),
        ];
    }

    /**
     * Get all links for an NFT
     *
     * @param string $nftId
     * @return array
     * @throws DomainException
     */
    public function getLinksForNft(string $nftId): array
    {
        // Validate UUID
        if (!Str::isUuid($nftId)) {
            throw new DomainException("Invalid nft_id format: must be UUID");
        }

        // Query using core connection (models have connection='core' set)
        $didLinks = DidNftLink::on('core')->where('nft_id', $nftId)->get();
        $orderLinks = OrderNftLink::on('core')->where('nft_id', $nftId)->get();

        return [
            'nft_id' => $nftId,
            'did_links' => $didLinks->toArray(),
            'order_links' => $orderLinks->toArray(),
        ];
    }

    /**
     * Log linking event (append-only)
     *
     * @param string $eventType
     * @param array $payload
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return void
     */
    private function logLinkingEvent(
        string $eventType,
        array $payload,
        ?string $actorUserId = null,
        ?string $traceId = null
    ): void {
        LinkingEvent::create([
            'event_type' => $eventType,
            'payload' => $payload,
            'actor_user_id' => $actorUserId,
            'trace_id' => $traceId,
            'created_at' => now(),
        ]);
    }
}
