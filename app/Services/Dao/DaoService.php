<?php

namespace App\Services\Dao;

use App\Models\DaoEvent;
use App\Models\DaoProposal;
use App\Models\DaoVote;
use App\Dids\Models\DidProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;

class DaoService
{
    /**
     * Create a new proposal (idempotent via title + created_by_did)
     *
     * @param string $title
     * @param string $description
     * @param string $createdByDid
     * @param array $options
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return DaoProposal
     * @throws DomainException
     */
    public function createProposal(
        string $title,
        string $description,
        string $createdByDid,
        array $options = [],
        ?string $actorUserId = null,
        ?string $traceId = null
    ): DaoProposal {
        // Validate UUIDs
        if (!Str::isUuid($createdByDid)) {
            throw new DomainException("Invalid created_by_did format: must be UUID");
        }

        // Validate DID exists
        $didProfile = DidProfile::on('core')->find($createdByDid);
        if (!$didProfile) {
            throw new DomainException("DID not found: {$createdByDid}");
        }

        return DB::connection('core')->transaction(function () use ($title, $description, $createdByDid, $options, $actorUserId, $traceId) {
            // Idempotency: check if proposal with same title and creator exists
            $existing = DaoProposal::on('core')->where('title', $title)
                ->where('created_by_did', $createdByDid)
                ->where('status', 'draft')
                ->first();

            if ($existing) {
                return $existing;
            }

            $proposal = DaoProposal::on('core')->create([
                'title' => $title,
                'description' => $description,
                'status' => $options['status'] ?? 'draft',
                'created_by_did' => $createdByDid,
                'voting_starts_at' => $options['voting_starts_at'] ?? null,
                'voting_ends_at' => $options['voting_ends_at'] ?? null,
                'quorum' => $options['quorum'] ?? 0,
                'metadata' => $options['metadata'] ?? null,
            ]);

            $this->logEvent('proposal_created', [
                'proposal_id' => $proposal->id,
                'title' => $title,
                'status' => $proposal->status,
            ], $createdByDid, $actorUserId, $traceId);

            return $proposal;
        });
    }

    /**
     * Cast a vote (idempotent: one vote per DID per proposal)
     *
     * @param string $proposalId
     * @param string $voterDid
     * @param string $vote
     * @param int $weight
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return DaoVote
     * @throws DomainException
     */
    public function castVote(
        string $proposalId,
        string $voterDid,
        string $vote,
        int $weight = 1,
        ?string $actorUserId = null,
        ?string $traceId = null
    ): DaoVote {
        // Validate UUIDs
        if (!Str::isUuid($proposalId)) {
            throw new DomainException("Invalid proposal_id format: must be UUID");
        }
        if (!Str::isUuid($voterDid)) {
            throw new DomainException("Invalid voter_did format: must be UUID");
        }

        // Validate vote value
        if (!in_array($vote, ['yes', 'no', 'abstain'], true)) {
            throw new DomainException("Invalid vote value: must be 'yes', 'no', or 'abstain'");
        }

        // Validate proposal exists and is active
        $proposal = DaoProposal::on('core')->find($proposalId);
        if (!$proposal) {
            throw new DomainException("Proposal not found: {$proposalId}");
        }

        if ($proposal->status !== 'active') {
            throw new DomainException("Proposal is not active (current status: {$proposal->status})");
        }

        // Validate DID exists
        $didProfile = DidProfile::on('core')->find($voterDid);
        if (!$didProfile) {
            throw new DomainException("DID not found: {$voterDid}");
        }

        return DB::connection('core')->transaction(function () use ($proposalId, $voterDid, $vote, $weight, $actorUserId, $traceId) {
            // Idempotency: use firstOrCreate
            $daoVote = DaoVote::on('core')->firstOrCreate(
                [
                    'proposal_id' => $proposalId,
                    'voter_did' => $voterDid,
                ],
                [
                    'vote' => $vote,
                    'weight' => $weight,
                    'created_by' => null, // user.id is integer, created_by expects UUID
                ]
            );

            // Update vote if it already existed
            if ($daoVote->wasRecentlyCreated === false) {
                $daoVote->update([
                    'vote' => $vote,
                    'weight' => $weight,
                ]);
            }

            $this->logEvent('vote_cast', [
                'proposal_id' => $proposalId,
                'voter_did' => $voterDid,
                'vote' => $vote,
                'weight' => $weight,
            ], $voterDid, $actorUserId, $traceId);

            return $daoVote;
        });
    }

    /**
     * Get proposal with vote results
     *
     * @param string $proposalId
     * @return array
     * @throws DomainException
     */
    public function getProposalResults(string $proposalId): array
    {
        if (!Str::isUuid($proposalId)) {
            throw new DomainException("Invalid proposal_id format: must be UUID");
        }

        $proposal = DaoProposal::on('core')->find($proposalId);
        if (!$proposal) {
            throw new DomainException("Proposal not found: {$proposalId}");
        }

        $votes = DaoVote::on('core')->where('proposal_id', $proposalId)->get();
        $results = [
            'yes' => 0,
            'no' => 0,
            'abstain' => 0,
            'total_votes' => $votes->count(),
            'total_weight' => 0,
        ];

        foreach ($votes as $vote) {
            $weight = $vote->weight ?? 1;
            $results[$vote->vote] += $weight;
            $results['total_weight'] += $weight;
        }

        return [
            'proposal' => $proposal,
            'results' => $results,
        ];
    }

    /**
     * List proposals
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listProposals(array $filters = [])
    {
        $query = DaoProposal::on('core');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['created_by_did'])) {
            $query->where('created_by_did', $filters['created_by_did']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Log DAO event (append-only)
     *
     * @param string $eventType
     * @param array $payload
     * @param string|null $actorDid
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return void
     */
    private function logEvent(
        string $eventType,
        array $payload,
        ?string $actorDid = null,
        ?string $actorUserId = null,
        ?string $traceId = null
    ): void {
        DaoEvent::create([
            'event_type' => $eventType,
            'payload' => $payload,
            'actor_did' => $actorDid,
            'actor_user_id' => $actorUserId,
            'trace_id' => $traceId,
            'created_at' => now(),
        ]);
    }
}
