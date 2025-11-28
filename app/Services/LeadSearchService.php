<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class LeadSearchService
{
    /**
     * Search leads with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function search(string $query = '', array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $leadsQuery = $this->buildQuery($filters);

        // Full-text search
        if (! empty($query)) {
            $leadsQuery->where(function ($q) use ($query) {
                $q->where('email', 'like', "%{$query}%")
                    ->orWhereJsonContains('data->name', $query)
                    ->orWhereJsonContains('data->phone', $query)
                    ->orWhereJsonContains('data->telephone', $query)
                    ->orWhereJsonContains('data->tel', $query);
            });
        }

        return $leadsQuery->with(['form', 'assignedAgent', 'callCenter'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Build query from filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildQuery(array $filters): Builder
    {
        $query = Lead::query();

        // Filter by status (support both slug and status_id)
        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            
            // Try to find status IDs from slugs
            $statusIds = \App\Models\LeadStatus::whereIn('slug', $statuses)->pluck('id')->toArray();
            
            if (! empty($statusIds)) {
                $query->whereIn('status_id', $statusIds);
            } else {
                // Fallback to old status column for backward compatibility
                $query->whereIn('status', $statuses);
            }
        }

        // Filter by date range (created_at)
        if (! empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        if (! empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        // Filter by email confirmation date
        if (! empty($filters['email_confirmed_from'])) {
            $query->where('email_confirmed_at', '>=', $filters['email_confirmed_from']);
        }
        if (! empty($filters['email_confirmed_to'])) {
            $query->where('email_confirmed_at', '<=', $filters['email_confirmed_to']);
        }

        // Filter by assigned agent
        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        // Filter by call center
        if (! empty($filters['call_center_id'])) {
            $query->where('call_center_id', $filters['call_center_id']);
        }

        // Filter by form
        if (! empty($filters['form_id'])) {
            $query->where('form_id', $filters['form_id']);
        }

        // Filter by email confirmation status
        if (isset($filters['email_confirmed']) && $filters['email_confirmed'] !== '') {
            if ($filters['email_confirmed']) {
                $query->whereNotNull('email_confirmed_at');
            } else {
                $query->whereNull('email_confirmed_at');
            }
        }

        // Filter by call date
        if (! empty($filters['called_from'])) {
            $query->where('called_at', '>=', $filters['called_from']);
        }
        if (! empty($filters['called_to'])) {
            $query->where('called_at', '<=', $filters['called_to']);
        }

        // Filter by notes presence
        if (isset($filters['has_notes']) && $filters['has_notes'] !== '') {
            if ($filters['has_notes']) {
                $query->whereHas('notes');
            } else {
                $query->whereDoesntHave('notes');
            }
        }

        // Filter by tags
        if (! empty($filters['tags'])) {
            $tagIds = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            $tagIds = array_filter(array_map('intval', $tagIds));

            if (! empty($tagIds)) {
                // If filter mode is 'any', use OR logic (leads with any of the selected tags)
                // If filter mode is 'all', use AND logic (leads with all selected tags)
                $mode = $filters['tags_mode'] ?? 'any';

                if ($mode === 'all') {
                    // Lead must have ALL selected tags
                    foreach ($tagIds as $tagId) {
                        $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
                    }
                } else {
                    // Lead must have ANY of the selected tags
                    $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
                }
            }
        }

        // Filter by no tags
        if (isset($filters['no_tags']) && $filters['no_tags']) {
            $query->whereDoesntHave('tags');
        }

        return $query;
    }

    /**
     * Get available filters.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAvailableFilters(): array
    {
        return [
            'status' => [
                'type' => 'multi-select',
                'label' => __('Statut'),
                'options' => \App\LeadStatus::options(),
            ],
            'created_from' => [
                'type' => 'date',
                'label' => __('Date de création (début)'),
            ],
            'created_to' => [
                'type' => 'date',
                'label' => __('Date de création (fin)'),
            ],
            'email_confirmed_from' => [
                'type' => 'date',
                'label' => __('Email confirmé (début)'),
            ],
            'email_confirmed_to' => [
                'type' => 'date',
                'label' => __('Email confirmé (fin)'),
            ],
            'assigned_to' => [
                'type' => 'select',
                'label' => __('Agent assigné'),
            ],
            'call_center_id' => [
                'type' => 'select',
                'label' => __('Centre d\'appels'),
            ],
            'form_id' => [
                'type' => 'select',
                'label' => __('Formulaire'),
            ],
            'email_confirmed' => [
                'type' => 'boolean',
                'label' => __('Email confirmé'),
            ],
            'called_from' => [
                'type' => 'date',
                'label' => __('Appelé (début)'),
            ],
            'called_to' => [
                'type' => 'date',
                'label' => __('Appelé (fin)'),
            ],
            'has_notes' => [
                'type' => 'boolean',
                'label' => __('A des notes'),
            ],
            'tags' => [
                'type' => 'multi-select',
                'label' => __('Tags'),
            ],
            'tags_mode' => [
                'type' => 'select',
                'label' => __('Mode de filtrage des tags'),
                'options' => [
                    'any' => __('N\'importe lequel'),
                    'all' => __('Tous'),
                ],
            ],
            'no_tags' => [
                'type' => 'boolean',
                'label' => __('Sans tags'),
            ],
        ];
    }
}
