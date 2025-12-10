<?php

namespace App\Observers;

use App\Events\LeadAssigned;
use App\Models\Lead;
use App\Services\LeadDistributionService;
use App\Services\LeadScoringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadObserver
{
    public function __construct(
        protected LeadDistributionService $distributionService,
        protected LeadScoringService $scoringService
    ) {}

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        // Check if distribution timing conditions are met
        if (! $lead->assigned_to) {
            // Load call center to check distribution_timing
            if (! $lead->relationLoaded('callCenter')) {
                $lead->load('callCenter');
            }

            if ($lead->callCenter) {
                $distributionTiming = $lead->callCenter->distribution_timing ?? 'after_email_confirmation';
                $status = $lead->status ?? $lead->getStatusEnum()->value ?? null;
                $changes = $lead->getChanges();

                // Check if status changed to a distributable state
                $statusChanged = isset($changes['status']);
                $emailConfirmed = isset($changes['email_confirmed_at']);

                $shouldDistribute = match ($distributionTiming) {
                    'after_registration' => $statusChanged && $status === 'pending_email',
                    'after_email_confirmation' => ($statusChanged && $status === 'email_confirmed') || ($emailConfirmed && $status === 'email_confirmed'),
                    default => ($statusChanged && $status === 'email_confirmed') || ($emailConfirmed && $status === 'email_confirmed'),
                };

                if ($shouldDistribute) {
                    $this->attemptDistribution($lead);
                }
            }
        }

        $this->recalculateScoreIfNeeded($lead, 'updated');
    }

    /**
     * Handle the Lead "saved" event.
     */
    public function saved(Lead $lead): void
    {
        // Check both the status attribute and getStatusEnum() for compatibility
        $status = $lead->status ?? $lead->getStatusEnum()->value ?? null;

        Log::debug('Observer: saved() called', [
            'lead_id' => $lead->id,
            'status' => $lead->status,
            'status_id' => $lead->status_id,
            'email_confirmed_at' => $lead->email_confirmed_at,
            'assigned_to' => $lead->assigned_to,
            'wasRecentlyCreated' => $lead->wasRecentlyCreated,
        ]);

        // Only attempt distribution if lead is not assigned
        if (! $lead->assigned_to) {
            // Load call center to check distribution_timing
            if (! $lead->relationLoaded('callCenter')) {
                $lead->load('callCenter');
            }

            // If lead doesn't have call_center_id, try to get it from the form
            if (! $lead->call_center_id && $lead->form && $lead->form->call_center_id) {
                $lead->call_center_id = $lead->form->call_center_id;
                $lead->saveQuietly();
                $lead->refresh();
                $lead->load('callCenter');
            }

            if ($lead->callCenter) {
                $distributionTiming = $lead->callCenter->distribution_timing ?? 'after_email_confirmation';

                // Check if distribution should happen based on timing setting
                $shouldDistribute = match ($distributionTiming) {
                    'after_registration' => $lead->wasRecentlyCreated && $status === 'pending_email',
                    'after_email_confirmation' => $status === 'email_confirmed',
                    default => $status === 'email_confirmed',
                };

                if ($shouldDistribute) {
                    $this->attemptDistribution($lead);
                }
            }
        }
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

        // Only process if lead is not assigned
        if (! $lead->assigned_to) {
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
                // Always reload call center from database to get latest distribution_method and distribution_timing
                $callCenter = \App\Models\CallCenter::find($lead->call_center_id);

                if (! $callCenter) {
                    Log::warning('Observer: Call center not found', [
                        'lead_id' => $lead->id,
                        'call_center_id' => $lead->call_center_id,
                    ]);

                    return;
                }

                // Skip automatic distribution if mode is manual
                if ($callCenter->distribution_method === 'manual') {
                    Log::info('Observer: Skipping automatic distribution (manual mode)', [
                        'lead_id' => $lead->id,
                        'call_center_id' => $lead->call_center_id,
                        'distribution_method' => 'manual',
                    ]);

                    return;
                }

                // Check distribution timing
                $distributionTiming = $callCenter->distribution_timing ?? 'after_email_confirmation';
                $status = $lead->status ?? $lead->getStatusEnum()->value ?? null;

                $shouldDistribute = match ($distributionTiming) {
                    'after_registration' => $status === 'pending_email',
                    'after_email_confirmation' => $status === 'email_confirmed',
                    default => $status === 'email_confirmed',
                };

                if (! $shouldDistribute) {
                    Log::info('Observer: Skipping distribution (timing not met)', [
                        'lead_id' => $lead->id,
                        'call_center_id' => $lead->call_center_id,
                        'status' => $status,
                        'distribution_timing' => $distributionTiming,
                    ]);

                    return;
                }

                Log::info('Observer: Attempting automatic distribution', [
                    'lead_id' => $lead->id,
                    'call_center_id' => $lead->call_center_id,
                    'status' => $lead->status,
                    'distribution_method' => $callCenter?->distribution_method ?? 'unknown',
                    'distribution_timing' => $distributionTiming,
                ]);

                try {
                    // Pass the call center directly to avoid refresh() in distributeLead
                    $agent = $this->distributionService->distributeLead($lead, $callCenter);

                    if ($agent) {
                        Log::info('Observer: Agent found, assigning lead', [
                            'lead_id' => $lead->id,
                            'agent_id' => $agent->id,
                            'agent_name' => $agent->name,
                            'lead_status' => $lead->status,
                            'lead_assigned_to_before' => $lead->assigned_to,
                        ]);

                        // Assign the lead directly without going through assignToAgent to avoid refresh issues
                        // Use DB::table to update directly to avoid any observer/event issues
                        \DB::table('leads')
                            ->where('id', $lead->id)
                            ->update([
                                'assigned_to' => $agent->id,
                                'status' => 'pending_call',
                                'updated_at' => now(),
                            ]);

                        // Reload the lead to get the updated values
                        $lead->refresh();

                        Log::info('Observer: Lead assigned and marked as pending_call', [
                            'lead_id' => $lead->id,
                            'agent_id' => $agent->id,
                            'assigned_to' => $lead->assigned_to,
                            'status' => $lead->status,
                        ]);

                        // Dispatch LeadAssigned event manually
                        event(new LeadAssigned($lead, $agent));
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
        $this->recalculateScoreIfNeeded($lead, 'created');
    }

    /**
     * Recalculate score if needed based on configuration.
     */
    protected function recalculateScoreIfNeeded(Lead $lead, string $event): void
    {
        $config = config('lead-scoring.auto_recalculate', []);

        $shouldRecalculate = match ($event) {
            'created' => $config['on_creation'] ?? true,
            'updated' => $this->shouldRecalculateOnUpdate($lead, $config),
            default => false,
        };

        if ($shouldRecalculate) {
            try {
                // Load necessary relationships before calculation
                $lead->loadMissing(['form', 'notes', 'reminders', 'tags']);

                $this->scoringService->updateScore($lead);
                Log::debug('Lead score recalculated', [
                    'lead_id' => $lead->id,
                    'event' => $event,
                    'score' => $lead->fresh()->score,
                ]);
            } catch (\Exception $e) {
                Log::error('Error recalculating lead score', [
                    'lead_id' => $lead->id,
                    'event' => $event,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Check if score should be recalculated on update.
     */
    protected function shouldRecalculateOnUpdate(Lead $lead, array $config): bool
    {
        $changes = $lead->getChanges();

        // Don't recalculate if only score-related fields changed (prevents infinite loop)
        $scoreFields = ['score', 'score_updated_at', 'score_factors'];
        $onlyScoreChanged = ! empty($changes) && count(array_diff_key($changes, array_flip($scoreFields))) === 0;
        if ($onlyScoreChanged) {
            return false;
        }

        // Recalculate on email confirmation
        if (($config['on_email_confirmation'] ?? true) && isset($changes['email_confirmed_at'])) {
            return true;
        }

        // Recalculate on status change
        if (($config['on_status_change'] ?? true) && isset($changes['status'])) {
            return true;
        }

        return false;
    }
}
