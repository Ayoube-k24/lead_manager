<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\LeadDistributionService;
use Illuminate\Support\Facades\Log;

class LeadObserver
{
    public function __construct(
        protected LeadDistributionService $distributionService
    ) {}

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        $this->attemptDistribution($lead);
    }

    /**
     * Handle the Lead "saved" event.
     */
    public function saved(Lead $lead): void
    {
        $this->attemptDistribution($lead);
    }

    /**
     * Attempt to distribute a lead if conditions are met.
     */
    protected function attemptDistribution(Lead $lead): void
    {
        Log::debug('Observer: attemptDistribution called', [
            'lead_id' => $lead->id,
            'status' => $lead->status,
            'assigned_to' => $lead->assigned_to,
            'wasRecentlyCreated' => $lead->wasRecentlyCreated,
            'wasChanged' => $lead->wasChanged(),
            'getChanges' => $lead->getChanges(),
        ]);

        // Only process if status is email_confirmed and lead is not assigned
        if ($lead->status === 'email_confirmed' && ! $lead->assigned_to) {
            // Ensure we have the necessary relationships loaded
            if (! $lead->relationLoaded('form')) {
                $lead->load('form');
            }
            if (! $lead->relationLoaded('callCenter')) {
                $lead->load('callCenter');
            }

            // If lead doesn't have call_center_id, try to get it from the form
            if (! $lead->call_center_id && $lead->form && $lead->form->call_center_id) {
                Log::info('Observer: Setting call_center_id from form', [
                    'lead_id' => $lead->id,
                    'form_call_center_id' => $lead->form->call_center_id,
                ]);

                // Update without triggering observer again
                $lead->withoutEvents(function () use ($lead) {
                    $lead->call_center_id = $lead->form->call_center_id;
                    $lead->saveQuietly();
                });

                // Reload relationships
                $lead->refresh();
                $lead->load(['form', 'callCenter']);
            }

            // Only distribute if we have a call center
            if ($lead->call_center_id) {
                // Load call center to check distribution method
                if (! $lead->relationLoaded('callCenter')) {
                    $lead->load('callCenter');
                }

                $callCenter = $lead->callCenter;

                // Skip automatic distribution if mode is manual
                if ($callCenter && $callCenter->distribution_method === 'manual') {
                    Log::info('Observer: Skipping automatic distribution (manual mode)', [
                        'lead_id' => $lead->id,
                        'call_center_id' => $lead->call_center_id,
                        'distribution_method' => 'manual',
                    ]);

                    return;
                }

                Log::info('Observer: Attempting automatic distribution', [
                    'lead_id' => $lead->id,
                    'call_center_id' => $lead->call_center_id,
                    'status' => $lead->status,
                    'distribution_method' => $callCenter?->distribution_method ?? 'unknown',
                ]);

                try {
                    $agent = $this->distributionService->distributeLead($lead);

                    if ($agent) {
                        Log::info('Observer: Agent found, assigning lead', [
                            'lead_id' => $lead->id,
                            'agent_id' => $agent->id,
                            'agent_name' => $agent->name,
                        ]);

                        if ($this->distributionService->assignToAgent($lead, $agent)) {
                            // Reload to get fresh data
                            $lead->refresh();

                            // Mark as pending call if still email_confirmed
                            if ($lead->status === 'email_confirmed' && $lead->assigned_to) {
                                $lead->withoutEvents(function () use ($lead) {
                                    $lead->status = 'pending_call';
                                    $lead->saveQuietly();
                                });

                                Log::info('Observer: Lead assigned and marked as pending_call', [
                                    'lead_id' => $lead->id,
                                    'agent_id' => $agent->id,
                                ]);
                            }
                        } else {
                            Log::warning('Observer: Failed to assign lead to agent', [
                                'lead_id' => $lead->id,
                                'agent_id' => $agent->id,
                            ]);
                        }
                    } else {
                        Log::warning('Observer: No agent found for distribution', [
                            'lead_id' => $lead->id,
                            'call_center_id' => $lead->call_center_id,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Observer: Error during automatic distribution', [
                        'lead_id' => $lead->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                Log::warning('Observer: Cannot distribute lead, no call_center_id', [
                    'lead_id' => $lead->id,
                    'form_id' => $lead->form_id,
                    'form_call_center_id' => $lead->form?->call_center_id,
                ]);
            }
        }
    }

    /**
     * Handle the Lead "created" event.
     */
    public function created(Lead $lead): void
    {
        // No distribution on creation, only after email confirmation
    }
}
