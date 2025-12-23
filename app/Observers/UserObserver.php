<?php

namespace App\Observers;

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadBusinessRulesService;
use App\Services\LeadDistributionService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function __construct(
        protected LeadDistributionService $distributionService,
        protected LeadBusinessRulesService $businessRules
    ) {}

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Check if user is an agent and was deactivated
        if (! $user->isAgent()) {
            return;
        }

        $changes = $user->getChanges();

        // Check if is_active changed from true to false
        if (isset($changes['is_active']) && $user->is_active === false && ($changes['is_active'] ?? true) === true) {
            $this->handleAgentDeactivation($user);
        }
    }

    /**
     * Handle agent deactivation by reassigning untreated leads.
     */
    protected function handleAgentDeactivation(User $agent): void
    {
        $autoReassign = config('lead-rules.reassignment.auto_reassign_on_deactivation', true);

        if (! $autoReassign) {
            Log::info('Auto-reassignment disabled, skipping lead reassignment', [
                'agent_id' => $agent->id,
            ]);

            return;
        }

        $untreatedStatuses = config('lead-rules.reassignment.untreated_statuses', [
            'pending_call',
            'email_confirmed',
            'callback_pending',
        ]);

        // Get untreated leads for this agent
        $untreatedLeads = Lead::where('assigned_to', $agent->id)
            ->whereIn('status', $untreatedStatuses)
            ->get();

        if ($untreatedLeads->isEmpty()) {
            Log::info('No untreated leads to reassign', [
                'agent_id' => $agent->id,
            ]);

            return;
        }

        Log::info('Reassigning untreated leads after agent deactivation', [
            'agent_id' => $agent->id,
            'leads_count' => $untreatedLeads->count(),
        ]);

        $reassignedCount = 0;
        $failedCount = 0;

        foreach ($untreatedLeads as $lead) {
            try {
                // Reload lead to ensure fresh data
                $lead->refresh();
                $lead->loadMissing(['callCenter', 'form']);

                // Get available agents for reassignment
                $availableAgents = $this->businessRules->getAvailableAgentsForReassignment($lead->call_center_id);

                if ($availableAgents->isEmpty()) {
                    Log::warning('No available agents for reassignment, lead will remain unassigned', [
                        'lead_id' => $lead->id,
                        'agent_id' => $agent->id,
                        'call_center_id' => $lead->call_center_id,
                    ]);

                    // Unassign the lead
                    $lead->assigned_to = null;
                    $lead->saveQuietly();

                    $failedCount++;

                    continue;
                }

                // Distribute lead to available agent
                $newAgent = $this->distributionService->distributeLead($lead);

                if ($newAgent) {
                    $this->distributionService->assignToAgent($lead, $newAgent);

                    // Update status if needed
                    if ($lead->status === 'email_confirmed') {
                        $lead->markAsPendingCall();
                    }

                    $reassignedCount++;

                    Log::info('Lead reassigned successfully', [
                        'lead_id' => $lead->id,
                        'old_agent_id' => $agent->id,
                        'new_agent_id' => $newAgent->id,
                    ]);
                } else {
                    // No agent available, unassign the lead
                    $lead->assigned_to = null;
                    $lead->saveQuietly();

                    $failedCount++;

                    Log::warning('Could not reassign lead, unassigned', [
                        'lead_id' => $lead->id,
                        'old_agent_id' => $agent->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error reassigning lead', [
                    'lead_id' => $lead->id,
                    'agent_id' => $agent->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Unassign the lead on error
                try {
                    $lead->assigned_to = null;
                    $lead->saveQuietly();
                } catch (\Exception $saveException) {
                    Log::error('Error unassigning lead after reassignment failure', [
                        'lead_id' => $lead->id,
                        'error' => $saveException->getMessage(),
                    ]);
                }

                $failedCount++;
            }
        }

        Log::info('Lead reassignment completed', [
            'agent_id' => $agent->id,
            'total_leads' => $untreatedLeads->count(),
            'reassigned' => $reassignedCount,
            'failed' => $failedCount,
        ]);
    }
}
