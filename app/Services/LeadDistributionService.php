<?php

namespace App\Services;

use App\Events\LeadAssigned;
use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadDistributionService
{
    public function __construct(
        protected AuditService $auditService,
        protected LeadBusinessRulesService $businessRules
    ) {}

    /**
     * Distribute a lead to an agent based on the call center's distribution method.
     */
    public function distributeLead(Lead $lead, ?CallCenter $callCenter = null): ?User
    {
        \Log::info('Starting lead distribution', [
            'lead_id' => $lead->id,
            'lead_call_center_id' => $lead->call_center_id,
            'lead_status' => $lead->status,
            'lead_assigned_to' => $lead->assigned_to,
            'call_center_provided' => $callCenter !== null,
        ]);

        // If call center is not provided, try to get it from the lead
        if (! $callCenter) {
            // Load form and callCenter relationships if not already loaded
            if (! $lead->relationLoaded('form')) {
                $lead->load('form');
            }
            if (! $lead->relationLoaded('callCenter')) {
                $lead->load('callCenter');
            }

            // Reload lead to ensure we have the latest call_center_id
            $lead->refresh();

            if ($lead->call_center_id) {
                // Always reload call center from database to get latest distribution_method
                $callCenter = CallCenter::find($lead->call_center_id);
            } elseif ($lead->form && $lead->form->call_center_id) {
                // Try to get call center from form
                \Log::info('Setting call_center_id from form', [
                    'lead_id' => $lead->id,
                    'form_id' => $lead->form->id,
                    'form_call_center_id' => $lead->form->call_center_id,
                ]);
                $lead->call_center_id = $lead->form->call_center_id;
                $lead->save();
                $lead->refresh();
                // Always reload call center from database to get latest distribution_method
                $callCenter = CallCenter::find($lead->call_center_id);
            }
        } else {
            // If call center is provided, refresh it to ensure we have latest data
            // But don't refresh the lead to avoid losing the current status
            $callCenter->refresh();
        }

        if (! $callCenter) {
            \Log::warning('Cannot distribute lead: no call center associated', [
                'lead_id' => $lead->id,
                'form_id' => $lead->form_id,
                'form_call_center_id' => $lead->form?->call_center_id,
            ]);

            return null;
        }

        \Log::info('Call center found for distribution', [
            'lead_id' => $lead->id,
            'call_center_id' => $callCenter->id,
            'call_center_name' => $callCenter->name,
            'distribution_method' => $callCenter->distribution_method,
        ]);

        // Check business hours if configured
        if (! $this->businessRules->isBusinessHours()) {
            \Log::info('Cannot distribute lead: outside business hours', [
                'lead_id' => $lead->id,
                'call_center_id' => $callCenter->id,
                'current_time' => now()->toDateTimeString(),
            ]);

            return null;
        }

        // Get active agents for this call center
        $agents = $this->getActiveAgents($callCenter);

        if ($agents->isEmpty()) {
            \Log::warning('Cannot distribute lead: no active agents found', [
                'lead_id' => $lead->id,
                'call_center_id' => $callCenter->id,
            ]);

            return null;
        }

        // Filter agents by business rules (daily limits, pending limits)
        $availableAgents = $this->businessRules->getAvailableAgents($lead, $agents);

        if ($availableAgents->isEmpty()) {
            \Log::warning('Cannot distribute lead: no available agents (limits reached)', [
                'lead_id' => $lead->id,
                'call_center_id' => $callCenter->id,
                'total_agents' => $agents->count(),
            ]);

            return null;
        }

        // Use filtered agents
        $agents = $availableAgents;

        \Log::info('Active agents found', [
            'lead_id' => $lead->id,
            'call_center_id' => $callCenter->id,
            'agents_count' => $agents->count(),
            'agent_ids' => $agents->pluck('id')->toArray(),
        ]);

        // Distribute based on method (using filtered agents)
        $selectedAgent = match ($callCenter->distribution_method) {
            'round_robin' => $this->distributeRoundRobin($lead, $agents),
            'weighted' => $this->distributeWeighted($lead, $agents),
            'manual' => null, // Manual distribution, don't auto-assign
            default => $this->distributeRoundRobin($lead, $agents),
        };

        if ($selectedAgent) {
            \Log::info('Agent selected for distribution', [
                'lead_id' => $lead->id,
                'agent_id' => $selectedAgent->id,
                'agent_name' => $selectedAgent->name,
                'distribution_method' => $callCenter->distribution_method,
            ]);
        } else {
            \Log::warning('No agent selected for distribution', [
                'lead_id' => $lead->id,
                'distribution_method' => $callCenter->distribution_method,
            ]);
        }

        return $selectedAgent;
    }

    /**
     * Get active agents for a call center.
     */
    protected function getActiveAgents(CallCenter $callCenter)
    {
        return User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->where('is_active', true)
            ->with('role')
            ->get()
            ->filter(fn ($user) => $user->isAgent());
    }

    /**
     * Distribute lead using round-robin method.
     */
    protected function distributeRoundRobin(Lead $lead, $agents): ?User
    {
        // Get the call center ID from the lead
        $callCenterId = $lead->call_center_id;

        if (! $callCenterId) {
            \Log::warning('Cannot distribute round-robin: lead has no call_center_id', [
                'lead_id' => $lead->id,
            ]);

            return null;
        }

        // Get the agent with the least number of pending leads for THIS call center
        $agentCounts = DB::table('leads')
            ->select('assigned_to', DB::raw('COUNT(*) as lead_count'))
            ->where('call_center_id', $callCenterId)
            ->whereIn('assigned_to', $agents->pluck('id'))
            ->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        // Calculate minimum count including agents with 0 leads
        $allCounts = $agents->map(function ($agent) use ($agentCounts) {
            return $agentCounts->get($agent->id)?->lead_count ?? 0;
        });
        $minCount = $allCounts->min();

        // Get agents with minimum count
        $availableAgents = $agents->filter(function ($agent) use ($agentCounts, $minCount) {
            $count = $agentCounts->get($agent->id)?->lead_count ?? 0;

            return $count === $minCount;
        })->values();

        // If multiple agents have the same count, pick the one with the oldest last assignment
        if ($availableAgents->count() > 1) {
            $lastAssignments = DB::table('leads')
                ->select('assigned_to', DB::raw('MAX(updated_at) as last_assigned'))
                ->where('call_center_id', $callCenterId)
                ->whereIn('assigned_to', $availableAgents->pluck('id'))
                ->groupBy('assigned_to')
                ->get()
                ->keyBy('assigned_to');

            // Sort by last assignment (oldest first), then by ID for deterministic selection
            return $availableAgents->sortBy(function ($agent) use ($lastAssignments) {
                $lastAssigned = $lastAssignments->get($agent->id)?->last_assigned ?? '1970-01-01';

                return $lastAssigned.'_'.$agent->id;
            })->values()->first();
        }

        // Sort by ID for deterministic selection when only one agent has minimum count
        return $availableAgents->sortBy('id')->first();
    }

    /**
     * Distribute lead using weighted method (based on performance).
     */
    protected function distributeWeighted(Lead $lead, $agents): ?User
    {
        // Get the call center ID from the lead
        $callCenterId = $lead->call_center_id;

        if (! $callCenterId) {
            \Log::warning('Cannot distribute weighted: lead has no call_center_id', [
                'lead_id' => $lead->id,
            ]);

            return null;
        }

        // Calculate performance score for each agent (only for THIS call center)
        $scores = $agents->mapWithKeys(function ($agent) use ($callCenterId) {
            $stats = $this->calculateAgentStats($agent, $callCenterId);

            // Performance score: confirmed leads / total leads (higher is better)
            // If no leads, give a default score of 0.5
            $score = $stats['total'] > 0
                ? ($stats['confirmed'] / $stats['total'])
                : 0.5;

            return [$agent->id => $score];
        });

        // Distribute to agent with lowest score (they need more leads to improve)
        // But also consider current workload for THIS call center
        $workloads = DB::table('leads')
            ->select('assigned_to', DB::raw('COUNT(*) as pending_count'))
            ->where('call_center_id', $callCenterId)
            ->whereIn('assigned_to', $agents->pluck('id'))
            ->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        // Sort by combined score and workload, then by ID for deterministic selection
        return $agents->sortBy(function ($agent) use ($scores, $workloads) {
            $score = $scores->get($agent->id) ?? 0.5;
            $workload = $workloads->get($agent->id)?->pending_count ?? 0;

            // Combine score and workload (lower score + lower workload = higher priority)
            return (($score * 100) + $workload).'_'.$agent->id;
        })->first();
    }

    /**
     * Calculate agent statistics for a specific call center.
     *
     * @return array{total: int, confirmed: int, rejected: int, pending: int}
     */
    protected function calculateAgentStats(User $agent, ?int $callCenterId = null): array
    {
        $query = Lead::where('assigned_to', $agent->id);

        // Filter by call center if provided
        if ($callCenterId) {
            $query->where('call_center_id', $callCenterId);
        }

        $leads = $query->get();

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
        // Reload lead to ensure we have fresh data
        $lead->refresh();

        // Load agent role if not already loaded
        if (! $agent->relationLoaded('role')) {
            $agent->load('role');
        }

        // Verify agent belongs to the same call center as the lead
        if ($lead->call_center_id && $agent->call_center_id !== $lead->call_center_id) {
            \Log::warning('Cannot assign lead: agent belongs to different call center', [
                'lead_id' => $lead->id,
                'lead_call_center_id' => $lead->call_center_id,
                'agent_id' => $agent->id,
                'agent_call_center_id' => $agent->call_center_id,
            ]);

            // Ensure lead is not modified
            $lead->refresh();

            return false;
        }

        // Verify agent is actually an agent and active
        if (! $agent->isAgent() || ! $agent->is_active) {
            \Log::warning('Cannot assign lead: user is not an active agent', [
                'lead_id' => $lead->id,
                'user_id' => $agent->id,
                'user_role' => $agent->role?->slug,
                'is_active' => $agent->is_active,
            ]);

            // Ensure lead is not modified
            $lead->refresh();

            return false;
        }

        $lead->assigned_to = $agent->id;
        if (! $lead->call_center_id && $agent->call_center_id) {
            $lead->call_center_id = $agent->call_center_id;
        }

        $saved = $lead->save();

        \Log::info('Lead save attempt', [
            'lead_id' => $lead->id,
            'assigned_to' => $lead->assigned_to,
            'call_center_id' => $lead->call_center_id,
            'saved' => $saved,
            'lead_dirty' => $lead->isDirty(),
            'lead_was_changed' => $lead->wasChanged(),
        ]);

        // Reload to verify
        $lead->refresh();

        if ($saved) {
            // Dispatch LeadAssigned event for webhooks
            event(new LeadAssigned($lead, $agent));

            // Log the assignment
            try {
                $this->auditService->logLeadAssigned($lead, $agent);
            } catch (\Exception $e) {
                \Log::error('Failed to log lead assignment', [
                    'lead_id' => $lead->id,
                    'agent_id' => $agent->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Send notification to agent
            try {
                $agent->notify(new \App\Notifications\LeadAssignedNotification($lead));
            } catch (\Exception $e) {
                \Log::error('Failed to send notification to agent', [
                    'lead_id' => $lead->id,
                    'agent_id' => $agent->id,
                    'error' => $e->getMessage(),
                ]);
            }

            \Log::info('Lead assigned to agent successfully', [
                'lead_id' => $lead->id,
                'agent_id' => $agent->id,
                'call_center_id' => $lead->call_center_id,
            ]);
        } else {
            \Log::error('Failed to save lead assignment', [
                'lead_id' => $lead->id,
                'agent_id' => $agent->id,
            ]);
        }

        return $saved;
    }

    /**
     * Reassign untreated leads from one agent to another or auto-distribute.
     *
     * @param  int|null  $maxCount  Maximum number of leads to reassign (null = all)
     * @param  array<string>|null  $statuses  Specific statuses to reassign (null = use config default)
     * @return array{reassigned: int, failed: int, unassigned: int}
     */
    public function reassignUntreatedLeads(User $fromAgent, ?User $toAgent = null, ?int $callCenterId = null, ?int $maxCount = null, ?array $statuses = null): array
    {
        $untreatedStatuses = $statuses ?? config('lead-rules.reassignment.untreated_statuses', [
            'pending_call',
            'email_confirmed',
            'callback_pending',
        ]);

        $query = Lead::where('assigned_to', $fromAgent->id)
            ->whereIn('status', $untreatedStatuses)
            ->orderByRaw("CASE 
                WHEN status = 'pending_call' THEN 1 
                WHEN status = 'email_confirmed' THEN 2 
                WHEN status = 'callback_pending' THEN 3 
                ELSE 4 
            END")
            ->orderBy('created_at', 'asc');

        if ($callCenterId) {
            $query->where('call_center_id', $callCenterId);
        }

        // Limit the number of leads if specified
        if ($maxCount !== null && $maxCount > 0) {
            $query->limit($maxCount);
        }

        $leads = $query->get();

        if ($leads->isEmpty()) {
            return [
                'reassigned' => 0,
                'failed' => 0,
                'unassigned' => 0,
            ];
        }

        \Log::info('Starting lead reassignment', [
            'from_agent_id' => $fromAgent->id,
            'to_agent_id' => $toAgent?->id,
            'call_center_id' => $callCenterId,
            'max_count' => $maxCount,
            'statuses' => $untreatedStatuses,
            'leads_count' => $leads->count(),
        ]);

        $reassigned = 0;
        $failed = 0;
        $unassigned = 0;

        foreach ($leads as $lead) {
            try {
                $lead->refresh();
                $lead->loadMissing(['callCenter', 'form']);

                \Log::debug('Processing lead for reassignment', [
                    'lead_id' => $lead->id,
                    'current_assigned_to' => $lead->assigned_to,
                    'status' => $lead->status,
                    'call_center_id' => $lead->call_center_id,
                ]);

                if ($toAgent) {
                    // Reassign to specific agent
                    \Log::debug('Reassigning to specific agent', [
                        'lead_id' => $lead->id,
                        'to_agent_id' => $toAgent->id,
                        'to_agent_active' => $toAgent->is_active,
                        'to_agent_call_center_id' => $toAgent->call_center_id,
                    ]);

                    // Verify agent is valid before reassigning
                    if (! $toAgent->isAgent() || ! $toAgent->is_active) {
                        \Log::warning('Cannot reassign: destination agent is not active', [
                            'lead_id' => $lead->id,
                            'to_agent_id' => $toAgent->id,
                            'is_agent' => $toAgent->isAgent(),
                            'is_active' => $toAgent->is_active,
                        ]);
                        $failed++;
                        continue;
                    }

                    // Verify call center match
                    if ($lead->call_center_id && $toAgent->call_center_id !== $lead->call_center_id) {
                        \Log::warning('Cannot reassign: call center mismatch', [
                            'lead_id' => $lead->id,
                            'lead_call_center_id' => $lead->call_center_id,
                            'to_agent_call_center_id' => $toAgent->call_center_id,
                        ]);
                        $failed++;
                        continue;
                    }

                    // Unassign from current agent first
                    $lead->assigned_to = null;
                    $lead->saveQuietly();
                    $lead->refresh();

                    // Directly assign to new agent using saveQuietly to avoid observer conflicts
                    $lead->assigned_to = $toAgent->id;
                    if (! $lead->call_center_id && $toAgent->call_center_id) {
                        $lead->call_center_id = $toAgent->call_center_id;
                    }
                    $saved = $lead->saveQuietly();
                    
                    if ($saved) {
                        $lead->refresh();
                        
                        // Update status if needed
                        if ($lead->status === 'email_confirmed') {
                            $lead->markAsPendingCall();
                        }
                        
                        // Dispatch event manually
                        event(new LeadAssigned($lead, $toAgent));
                        
                        // Log the assignment
                        try {
                            $this->auditService->logLeadAssigned($lead, $toAgent);
                        } catch (\Exception $e) {
                            \Log::error('Failed to log lead assignment', [
                                'lead_id' => $lead->id,
                                'agent_id' => $toAgent->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                        
                        // Send notification
                        try {
                            $toAgent->notify(new \App\Notifications\LeadAssignedNotification($lead));
                        } catch (\Exception $e) {
                            \Log::error('Failed to send notification to agent', [
                                'lead_id' => $lead->id,
                                'agent_id' => $toAgent->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                        
                        $reassigned++;
                        \Log::info('Lead reassigned successfully to specific agent', [
                            'lead_id' => $lead->id,
                            'from_agent_id' => $fromAgent->id,
                            'to_agent_id' => $toAgent->id,
                        ]);
                    } else {
                        $failed++;
                        \Log::warning('Failed to save lead reassignment to specific agent', [
                            'lead_id' => $lead->id,
                            'from_agent_id' => $fromAgent->id,
                            'to_agent_id' => $toAgent->id,
                        ]);
                    }
                } else {
                    // Auto-distribute to available agent
                    \Log::debug('Auto-distributing lead', [
                        'lead_id' => $lead->id,
                    ]);

                    // Unassign from current agent first
                    $lead->assigned_to = null;
                    $lead->saveQuietly();
                    $lead->refresh();

                    $newAgent = $this->distributeLead($lead);

                    if ($newAgent) {
                        \Log::debug('Agent found for auto-distribution', [
                            'lead_id' => $lead->id,
                            'new_agent_id' => $newAgent->id,
                        ]);

                        if ($this->assignToAgent($lead, $newAgent)) {
                            if ($lead->status === 'email_confirmed') {
                                $lead->markAsPendingCall();
                            }
                            $reassigned++;
                            \Log::info('Lead reassigned successfully via auto-distribution', [
                                'lead_id' => $lead->id,
                                'from_agent_id' => $fromAgent->id,
                                'new_agent_id' => $newAgent->id,
                            ]);
                        } else {
                            $lead->assigned_to = null;
                            $lead->saveQuietly();
                            $unassigned++;
                            \Log::warning('Failed to assign lead after auto-distribution', [
                                'lead_id' => $lead->id,
                                'new_agent_id' => $newAgent->id,
                            ]);
                        }
                    } else {
                        // No agent available, unassign
                        $lead->assigned_to = null;
                        $lead->saveQuietly();
                        $unassigned++;
                        \Log::warning('No agent available for auto-distribution', [
                            'lead_id' => $lead->id,
                            'call_center_id' => $lead->call_center_id,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error reassigning lead', [
                    'lead_id' => $lead->id,
                    'from_agent_id' => $fromAgent->id,
                    'to_agent_id' => $toAgent?->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                try {
                    $lead->assigned_to = null;
                    $lead->saveQuietly();
                } catch (\Exception $saveException) {
                    \Log::error('Error unassigning lead after reassignment failure', [
                        'lead_id' => $lead->id,
                        'error' => $saveException->getMessage(),
                    ]);
                }

                $failed++;
            }
        }

        \Log::info('Lead reassignment completed', [
            'from_agent_id' => $fromAgent->id,
            'to_agent_id' => $toAgent?->id,
            'total_leads' => $leads->count(),
            'reassigned' => $reassigned,
            'failed' => $failed,
            'unassigned' => $unassigned,
        ]);

        return [
            'reassigned' => $reassigned,
            'failed' => $failed,
            'unassigned' => $unassigned,
        ];
    }

    /**
     * Reassign multiple leads in bulk.
     *
     * @param  array<int>  $leadIds
     * @return array{reassigned: int, failed: int}
     */
    public function reassignLeads(array $leadIds, User $toAgent): array
    {
        $leads = Lead::whereIn('id', $leadIds)->get();

        if ($leads->isEmpty()) {
            return [
                'reassigned' => 0,
                'failed' => 0,
            ];
        }

        $reassigned = 0;
        $failed = 0;

        foreach ($leads as $lead) {
            try {
                $lead->refresh();
                $lead->loadMissing(['callCenter', 'form']);

                if ($this->assignToAgent($lead, $toAgent)) {
                    if ($lead->status === 'email_confirmed') {
                        $lead->markAsPendingCall();
                    }
                    $reassigned++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                \Log::error('Error reassigning lead in bulk', [
                    'lead_id' => $lead->id,
                    'to_agent_id' => $toAgent->id,
                    'error' => $e->getMessage(),
                ]);

                $failed++;
            }
        }

        return [
            'reassigned' => $reassigned,
            'failed' => $failed,
        ];
    }
}
