<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class LeadBusinessRulesService
{
    /**
     * Check if an agent can receive a new lead today.
     */
    public function canAgentReceiveLeadToday(User $agent, ?int $callCenterId = null): bool
    {
        $maxLeadsPerDay = Config::get('lead-rules.distribution.max_leads_per_agent_per_day');

        // No limit configured
        if ($maxLeadsPerDay === null) {
            return true;
        }

        $todayLeadsCount = $this->getTodayLeadsCount($agent, $callCenterId);

        return $todayLeadsCount < $maxLeadsPerDay;
    }

    /**
     * Get the number of leads assigned to an agent today.
     */
    public function getTodayLeadsCount(User $agent, ?int $callCenterId = null): int
    {
        $query = Lead::where('assigned_to', $agent->id)
            ->whereDate('updated_at', today());

        if ($callCenterId) {
            $query->where('call_center_id', $callCenterId);
        }

        return $query->count();
    }

    /**
     * Get the number of pending leads for an agent.
     */
    public function getPendingLeadsCount(User $agent, ?int $callCenterId = null): int
    {
        $untreatedStatuses = Config::get('lead-rules.reassignment.untreated_statuses', [
            'pending_call',
            'email_confirmed',
            'callback_pending',
        ]);

        $query = Lead::where('assigned_to', $agent->id)
            ->whereIn('status', $untreatedStatuses);

        if ($callCenterId) {
            $query->where('call_center_id', $callCenterId);
        }

        return $query->count();
    }

    /**
     * Check if an agent has reached the maximum pending leads limit.
     */
    public function hasReachedPendingLimit(User $agent, ?int $callCenterId = null): bool
    {
        $maxPending = Config::get('lead-rules.distribution.max_pending_leads_per_agent');

        // No limit configured
        if ($maxPending === null) {
            return false;
        }

        $pendingCount = $this->getPendingLeadsCount($agent, $callCenterId);

        return $pendingCount >= $maxPending;
    }

    /**
     * Check if distribution should happen based on business hours.
     */
    public function isBusinessHours(): bool
    {
        $businessHoursOnly = Config::get('lead-rules.distribution.business_hours_only', false);

        if (! $businessHoursOnly) {
            return true;
        }

        $hour = now()->hour;
        $dayOfWeek = now()->dayOfWeek;

        // Monday (1) to Friday (5), 9 AM to 6 PM
        return $dayOfWeek >= 1 && $dayOfWeek <= 5 && $hour >= 9 && $hour < 18;
    }

    /**
     * Check if a lead can be distributed.
     */
    public function canDistribute(Lead $lead): array
    {
        $errors = [];

        // Check phone requirement
        if (Config::get('lead-rules.validation.require_phone_for_distribution', true)) {
            $phone = $lead->data['phone'] ?? null;
            if (empty($phone)) {
                $errors[] = __('Le lead doit avoir un numéro de téléphone pour être distribué.');
            }
        }

        // Check data completeness
        $minCompleteness = Config::get('lead-rules.validation.min_data_completeness');
        if ($minCompleteness !== null) {
            $completeness = $this->calculateDataCompleteness($lead);
            if ($completeness < $minCompleteness) {
                $errors[] = __('La complétude des données (:completeness%) est inférieure au minimum requis (:min%).', [
                    'completeness' => $completeness,
                    'min' => $minCompleteness,
                ]);
            }
        }

        return $errors;
    }

    /**
     * Calculate data completeness percentage for a lead.
     */
    public function calculateDataCompleteness(Lead $lead): int
    {
        $data = $lead->data ?? [];
        $requiredFields = ['name', 'email', 'phone'];
        $optionalFields = ['company', 'message', 'address'];

        $filledRequired = 0;
        $filledOptional = 0;

        foreach ($requiredFields as $field) {
            if (! empty($data[$field])) {
                $filledRequired++;
            }
        }

        foreach ($optionalFields as $field) {
            if (! empty($data[$field])) {
                $filledOptional++;
            }
        }

        // Required fields are worth 70%, optional 30%
        $requiredScore = ($filledRequired / count($requiredFields)) * 70;
        $optionalScore = ($filledOptional / count($optionalFields)) * 30;

        return min(100, (int) round($requiredScore + $optionalScore));
    }

    /**
     * Get available agents for a lead (filtered by rules).
     */
    public function getAvailableAgents(Lead $lead, Collection $agents): Collection
    {
        $callCenterId = $lead->call_center_id;

        return $agents->filter(function (User $agent) use ($lead, $callCenterId) {
            // Agent must be active
            if (! $agent->is_active) {
                return false;
            }

            // Check daily limit
            if (! $this->canAgentReceiveLeadToday($agent, $callCenterId)) {
                Log::debug('Agent excluded: daily limit reached', [
                    'agent_id' => $agent->id,
                    'lead_id' => $lead->id,
                    'today_count' => $this->getTodayLeadsCount($agent, $callCenterId),
                ]);

                return false;
            }

            // Check pending limit
            if ($this->hasReachedPendingLimit($agent, $callCenterId)) {
                Log::debug('Agent excluded: pending limit reached', [
                    'agent_id' => $agent->id,
                    'lead_id' => $lead->id,
                    'pending_count' => $this->getPendingLeadsCount($agent, $callCenterId),
                ]);

                return false;
            }

            return true;
        });
    }

    /**
     * Get agents available for reassignment (not at daily limit).
     */
    public function getAvailableAgentsForReassignment(?int $callCenterId = null): Collection
    {
        $query = User::whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->where('is_active', true);

        if ($callCenterId) {
            $query->where('call_center_id', $callCenterId);
        }

        $agents = $query->get();

        return $agents->filter(function (User $agent) use ($callCenterId) {
            return $this->canAgentReceiveLeadToday($agent, $callCenterId);
        });
    }
}
