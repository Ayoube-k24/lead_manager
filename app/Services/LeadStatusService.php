<?php

namespace App\Services;

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\LeadStatus;
use Illuminate\Support\Collection;

class LeadStatusService
{
    /**
     * Create a new lead status.
     */
    public function createStatus(array $data): LeadStatus
    {
        return LeadStatus::create(array_merge($data, [
            'is_system' => false,
        ]));
    }

    /**
     * Update a lead status.
     */
    public function updateStatus(LeadStatus $status, array $data): LeadStatus
    {
        // Prevent updating system statuses (except description and color)
        if ($status->is_system && (isset($data['slug']) || isset($data['name']))) {
            throw new \Exception(__('Le slug et le nom des statuts système ne peuvent pas être modifiés.'));
        }

        $status->update($data);

        return $status->fresh();
    }

    /**
     * Get all statuses with usage count.
     *
     * @return Collection<int, LeadStatus>
     */
    public function getAllStatusesWithCount(?CallCenter $callCenter = null): Collection
    {
        $query = LeadStatus::query();

        if ($callCenter) {
            $query->withCount(['leads' => function ($q) use ($callCenter) {
                $q->where('call_center_id', $callCenter->id);
            }])
                ->whereHas('leads', function ($q) use ($callCenter) {
                    $q->where('call_center_id', $callCenter->id);
                });
        } else {
            $query->withCount('leads');
        }

        return $query->orderBy('order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Delete a status (only if not system).
     * If the status is used by leads, you can provide a replacement status.
     *
     * @param  \App\Models\CallCenter|null  $callCenter  If provided, only check/update leads from this call center
     */
    public function deleteStatus(LeadStatus $status, ?LeadStatus $replacementStatus = null, ?\App\Models\CallCenter $callCenter = null): bool
    {
        if ($status->is_system) {
            throw new \Exception(__('Les statuts système ne peuvent pas être supprimés.'));
        }

        // Check leads count (filtered by call center if provided)
        $leadsQuery = $status->leads();
        if ($callCenter) {
            $leadsQuery->where('call_center_id', $callCenter->id);
        }
        $leadsCount = $leadsQuery->count();

        // Get global leads count
        $globalLeadsCount = $status->leads()->count();

        if ($leadsCount > 0) {
            if (! $replacementStatus) {
                $message = __('Ce statut est utilisé sur :count lead(s)', ['count' => $leadsCount]);
                if ($callCenter && $globalLeadsCount > $leadsCount) {
                    $message .= ' '.__('dans votre centre d\'appels');
                }
                $message .= '. '.__('Veuillez sélectionner un statut de remplacement.');
                throw new \Exception($message);
            }

            // Replace status on leads (filtered by call center if provided)
            $updateQuery = $status->leads();
            if ($callCenter) {
                $updateQuery->where('call_center_id', $callCenter->id);
            }
            $updateQuery->update([
                'status_id' => $replacementStatus->id,
                'status' => $replacementStatus->slug,
            ]);

            // After replacement, check if status is still used globally
            $remainingLeadsCount = $status->leads()->count();

            // If status is no longer used anywhere, delete it
            if ($remainingLeadsCount === 0) {
                return $status->delete();
            }

            // If call center is provided and status is still used in other call centers
            if ($callCenter && $remainingLeadsCount > 0) {
                // Status is still used in other call centers, don't delete globally
                // Just return success as we've updated the leads in this call center
                return true;
            }
        }

        // If no leads are using it, delete the status
        if ($globalLeadsCount === 0) {
            return $status->delete();
        }

        return true;
    }

    /**
     * Delete multiple statuses with optional replacement.
     */
    public function deleteStatuses(array $statusIds, ?int $replacementStatusId = null): array
    {
        $results = [
            'deleted' => 0,
            'errors' => 0,
            'messages' => [],
        ];

        $replacementStatus = $replacementStatusId ? LeadStatus::find($replacementStatusId) : null;

        foreach ($statusIds as $statusId) {
            try {
                $status = LeadStatus::find($statusId);
                if (! $status) {
                    continue;
                }

                $this->deleteStatus($status, $replacementStatus);
                $results['deleted']++;
            } catch (\Exception $e) {
                $results['errors']++;
                $results['messages'][] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get active statuses.
     *
     * @return Collection<int, LeadStatus>
     */
    public function getActiveStatuses(): Collection
    {
        return LeadStatus::active()->orderBy('order')->orderBy('name')->get();
    }

    /**
     * Get final statuses.
     *
     * @return Collection<int, LeadStatus>
     */
    public function getFinalStatuses(): Collection
    {
        return LeadStatus::final()->orderBy('order')->orderBy('name')->get();
    }

    /**
     * Get post-call statuses.
     *
     * @return Collection<int, LeadStatus>
     */
    public function getPostCallStatuses(): Collection
    {
        return LeadStatus::postCall()->orderBy('order')->orderBy('name')->get();
    }

    /**
     * Get status by slug.
     */
    public function getBySlug(string $slug): ?LeadStatus
    {
        return LeadStatus::where('slug', $slug)->first();
    }

    /**
     * Get all statuses as array for select options.
     *
     * @return array<int, string>
     */
    public function getOptions(): array
    {
        return LeadStatus::orderBy('order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
