<?php

namespace App\Services\Pharma;

use App\Models\PharmaDrug;
use App\Models\PharmaEvent;
use App\Models\PharmaInteraction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;

class PharmaService
{
    /**
     * Get disclaimer text
     *
     * @return string
     */
    public function getDisclaimer(): string
    {
        return config('pharma.disclaimer', 'This information is for informational purposes only and does not constitute medical advice. Always consult with a qualified healthcare professional before making any medical decisions.');
    }

    /**
     * List drugs
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listDrugs(array $filters = [])
    {
        $query = PharmaDrug::on('core');

        if (isset($filters['name'])) {
            $query->where('name', 'ilike', '%' . $filters['name'] . '%');
        }

        if (isset($filters['generic_name'])) {
            $query->where('generic_name', 'ilike', '%' . $filters['generic_name'] . '%');
        }

        return $query->orderBy('name', 'asc')->get();
    }

    /**
     * Get drug by ID
     *
     * @param string $drugId
     * @return PharmaDrug
     * @throws DomainException
     */
    public function getDrug(string $drugId): PharmaDrug
    {
        if (!Str::isUuid($drugId)) {
            throw new DomainException("Invalid drug_id format: must be UUID");
        }

        $drug = PharmaDrug::on('core')->find($drugId);
        if (!$drug) {
            throw new DomainException("Drug not found: {$drugId}");
        }

        return $drug;
    }

    /**
     * Check drug interactions (idempotent via drug IDs)
     *
     * @param array $drugIds
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return array
     * @throws DomainException
     */
    public function checkInteractions(
        array $drugIds,
        ?string $actorUserId = null,
        ?string $traceId = null
    ): array {
        // Validate all drug IDs are UUIDs
        foreach ($drugIds as $drugId) {
            if (!Str::isUuid($drugId)) {
                throw new DomainException("Invalid drug_id format: must be UUID: {$drugId}");
            }
        }

        // Remove duplicates and sort for consistent results
        $drugIds = array_unique($drugIds);
        sort($drugIds);

        if (count($drugIds) < 2) {
            throw new DomainException("At least 2 drug IDs required for interaction check");
        }

        return DB::connection('core')->transaction(function () use ($drugIds, $actorUserId, $traceId) {
            // Validate all drugs exist
            $drugs = PharmaDrug::on('core')->whereIn('id', $drugIds)->get();
            if ($drugs->count() !== count($drugIds)) {
                $foundIds = $drugs->pluck('id')->toArray();
                $missingIds = array_diff($drugIds, $foundIds);
                throw new DomainException("Drug(s) not found: " . implode(', ', $missingIds));
            }

            // Find all interactions between the provided drugs
            $interactions = [];
            for ($i = 0; $i < count($drugIds); $i++) {
                for ($j = $i + 1; $j < count($drugIds); $j++) {
                    $drug1Id = $drugIds[$i];
                    $drug2Id = $drugIds[$j];

                    // Check both directions (drug1-drug2 and drug2-drug1)
                    $interaction = PharmaInteraction::on('core')
                        ->where(function ($query) use ($drug1Id, $drug2Id) {
                            $query->where('drug1_id', $drug1Id)
                                ->where('drug2_id', $drug2Id);
                        })
                        ->orWhere(function ($query) use ($drug1Id, $drug2Id) {
                            $query->where('drug1_id', $drug2Id)
                                ->where('drug2_id', $drug1Id);
                        })
                        ->first();

                    if ($interaction) {
                        $interactions[] = $interaction;
                    }
                }
            }

            // Log event
            $this->logEvent('interaction_check', [
                'drug_ids' => $drugIds,
                'interactions_found' => count($interactions),
            ], $actorUserId, $traceId);

            return [
                'drugs' => $drugs,
                'interactions' => $interactions,
            ];
        });
    }

    /**
     * Log pharma event (append-only)
     *
     * @param string $eventType
     * @param array $payload
     * @param string|null $actorUserId
     * @param string|null $traceId
     * @return void
     */
    private function logEvent(
        string $eventType,
        array $payload,
        ?string $actorUserId = null,
        ?string $traceId = null
    ): void {
        PharmaEvent::create([
            'event_type' => $eventType,
            'payload' => $payload,
            'actor_user_id' => $actorUserId,
            'trace_id' => $traceId,
            'created_at' => now(),
        ]);
    }
}
