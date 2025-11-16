<?php

namespace App\Services;

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadDistributionService
{
    /**
     * Distribute a lead to an agent based on the call center's distribution method.
     */
    public function distributeLead(Lead $lead, ?CallCenter $callCenter = null): ?User
    {
        // If call center is not provided, try to get it from the lead
        if (! $callCenter && $lead->call_center_id) {
            $callCenter = $lead->callCenter;
        }

        if (! $callCenter) {
            return null;
        }

        // Get active agents for this call center
        $agents = $this->getActiveAgents($callCenter);

        if ($agents->isEmpty()) {
            return null;
        }

        // Distribute based on method
        return match ($callCenter->distribution_method) {
            'round_robin' => $this->distributeRoundRobin($lead, $agents),
            'weighted' => $this->distributeWeighted($lead, $agents),
            'manual' => null, // Manual distribution, don't auto-assign
            default => $this->distributeRoundRobin($lead, $agents),
        };
    }

    /**
     * Get active agents for a call center.
     */
    protected function getActiveAgents(CallCenter $callCenter)
    {
        return User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->get();
    }

    /**
     * Distribute lead using round-robin method.
     */
    protected function distributeRoundRobin(Lead $lead, $agents): ?User
    {
        // Get the agent with the least number of pending leads
        $agentCounts = DB::table('leads')
            ->select('assigned_to', DB::raw('COUNT(*) as lead_count'))
            ->whereIn('assigned_to', $agents->pluck('id'))
            ->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        $minCount = $agentCounts->min('lead_count') ?? 0;

        // Get agents with minimum count
        $availableAgents = $agents->filter(function ($agent) use ($agentCounts, $minCount) {
            $count = $agentCounts->get($agent->id)?->lead_count ?? 0;

            return $count === $minCount;
        });

        // If multiple agents have the same count, pick the one with the oldest last assignment
        if ($availableAgents->count() > 1) {
            $lastAssignments = DB::table('leads')
                ->select('assigned_to', DB::raw('MAX(updated_at) as last_assigned'))
                ->whereIn('assigned_to', $availableAgents->pluck('id'))
                ->groupBy('assigned_to')
                ->get()
                ->keyBy('assigned_to');

            return $availableAgents->sortBy(function ($agent) use ($lastAssignments) {
                return $lastAssignments->get($agent->id)?->last_assigned ?? '1970-01-01';
            })->first();
        }

        return $availableAgents->first();
    }

    /**
     * Distribute lead using weighted method (based on performance).
     */
    protected function distributeWeighted(Lead $lead, $agents): ?User
    {
        // Calculate performance score for each agent
        $scores = $agents->mapWithKeys(function ($agent) {
            $stats = $this->calculateAgentStats($agent);

            // Performance score: confirmed leads / total leads (higher is better)
            // If no leads, give a default score of 0.5
            $score = $stats['total'] > 0
                ? ($stats['confirmed'] / $stats['total'])
                : 0.5;

            return [$agent->id => $score];
        });

        // Distribute to agent with lowest score (they need more leads to improve)
        // But also consider current workload
        $workloads = DB::table('leads')
            ->select('assigned_to', DB::raw('COUNT(*) as pending_count'))
            ->whereIn('assigned_to', $agents->pluck('id'))
            ->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        return $agents->sortBy(function ($agent) use ($scores, $workloads) {
            $score = $scores->get($agent->id) ?? 0.5;
            $workload = $workloads->get($agent->id)?->pending_count ?? 0;

            // Combine score and workload (lower score + lower workload = higher priority)
            return ($score * 100) + $workload;
        })->first();
    }

    /**
     * Calculate agent statistics.
     *
     * @return array{total: int, confirmed: int, rejected: int, pending: int}
     */
    protected function calculateAgentStats(User $agent): array
    {
        $leads = Lead::where('assigned_to', $agent->id)->get();

        return [
            'total' => $leads->count(),
            'confirmed' => $leads->where('status', 'confirmed')->count(),
            'rejected' => $leads->where('status', 'rejected')->count(),
            'pending' => $leads->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])->count(),
        ];
    }

    /**
     * Manually assign a lead to an agent.
     */
    public function assignToAgent(Lead $lead, User $agent): bool
    {
        // Verify agent belongs to the same call center as the lead
        if ($lead->call_center_id && $agent->call_center_id !== $lead->call_center_id) {
            return false;
        }

        // Verify agent is actually an agent
        if (! $agent->isAgent()) {
            return false;
        }

        $lead->assigned_to = $agent->id;
        if (! $lead->call_center_id && $agent->call_center_id) {
            $lead->call_center_id = $agent->call_center_id;
        }

        $saved = $lead->save();

        if ($saved) {
            // Send notification to agent
            $agent->notify(new \App\Notifications\LeadAssignedNotification($lead));
        }

        return $saved;
    }
}
