<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;

class AlertService
{
    /**
     * Create a new alert.
     */
    public function createAlert(
        User $user,
        string $type,
        array $conditions,
        ?float $threshold = null,
        array $channels = ['in_app']
    ): Alert {
        return Alert::create([
            'user_id' => $user->id,
            'name' => $this->generateAlertName($type, $conditions, $threshold),
            'type' => $type,
            'conditions' => $conditions,
            'threshold' => $threshold,
            'notification_channels' => $channels,
            'is_active' => true,
        ]);
    }

    /**
     * Check all active alerts.
     */
    public function checkAlerts(?User $user = null): Collection
    {
        $query = Alert::where('is_active', true);

        if ($user) {
            $query->where('user_id', $user->id);
        }

        $alerts = $query->get();
        $triggered = collect();

        foreach ($alerts as $alert) {
            if ($this->evaluateConditions($alert)) {
                $this->triggerAlert($alert);
                $triggered->push($alert);
            }
        }

        return $triggered;
    }

    /**
     * Evaluate alert conditions.
     */
    public function evaluateConditions(Alert $alert): bool
    {
        if (! $alert->canBeTriggered()) {
            return false;
        }

        return match ($alert->type) {
            'lead_stale' => $this->evaluateLeadStale($alert),
            'agent_performance' => $this->evaluateAgentPerformance($alert),
            'conversion_rate' => $this->evaluateConversionRate($alert),
            'high_volume' => $this->evaluateHighVolume($alert),
            'low_volume' => $this->evaluateLowVolume($alert),
            'form_performance' => $this->evaluateFormPerformance($alert),
            default => false,
        };
    }

    /**
     * Trigger an alert.
     */
    public function triggerAlert(Alert $alert, array $data = []): void
    {
        $alert->markAsTriggered();

        // Send notifications via configured channels
        foreach ($alert->notification_channels as $channel) {
            match ($channel) {
                'email' => $this->sendEmailNotification($alert, $data),
                'in_app' => $this->sendInAppNotification($alert, $data),
                'sms' => $this->sendSmsNotification($alert, $data),
                default => null,
            };
        }
    }

    /**
     * Evaluate lead stale condition.
     */
    protected function evaluateLeadStale(Alert $alert): bool
    {
        $hours = $alert->conditions['hours'] ?? 24;
        $threshold = $alert->threshold ?? 1;

        $staleLeads = Lead::where('status', '!=', 'converted')
            ->where('status', '!=', 'rejected')
            ->where('updated_at', '<', now()->subHours($hours))
            ->count();

        return $staleLeads >= $threshold;
    }

    /**
     * Evaluate agent performance condition.
     */
    protected function evaluateAgentPerformance(Alert $alert): bool
    {
        $threshold = $alert->threshold ?? 50; // Default 50% conversion rate
        $agentId = $alert->conditions['agent_id'] ?? null;

        if (! $agentId) {
            return false;
        }

        $agent = User::find($agentId);
        if (! $agent || ! $agent->isAgent()) {
            return false;
        }

        $totalLeads = Lead::where('assigned_to', $agentId)->count();
        if ($totalLeads === 0) {
            return false;
        }

        $convertedLeads = Lead::where('assigned_to', $agentId)
            ->where('status', 'converted')
            ->count();

        $conversionRate = ($convertedLeads / $totalLeads) * 100;

        return $conversionRate < $threshold;
    }

    /**
     * Evaluate conversion rate condition.
     */
    protected function evaluateConversionRate(Alert $alert): bool
    {
        $threshold = $alert->threshold ?? 50;

        $totalLeads = Lead::count();
        if ($totalLeads === 0) {
            return false;
        }

        $convertedLeads = Lead::where('status', 'converted')->count();
        $conversionRate = ($convertedLeads / $totalLeads) * 100;

        return $conversionRate < $threshold;
    }

    /**
     * Evaluate high volume condition.
     */
    protected function evaluateHighVolume(Alert $alert): bool
    {
        $threshold = $alert->threshold ?? 10;
        $hours = $alert->conditions['hours'] ?? 1;

        $leadsCount = Lead::where('created_at', '>=', now()->subHours($hours))->count();

        return $leadsCount > $threshold;
    }

    /**
     * Evaluate low volume condition.
     */
    protected function evaluateLowVolume(Alert $alert): bool
    {
        $threshold = $alert->threshold ?? 5;
        $hours = $alert->conditions['hours'] ?? 1;

        $leadsCount = Lead::where('created_at', '>=', now()->subHours($hours))->count();

        return $leadsCount < $threshold;
    }

    /**
     * Evaluate form performance condition.
     */
    protected function evaluateFormPerformance(Alert $alert): bool
    {
        $threshold = $alert->threshold ?? 30;
        $formId = $alert->conditions['form_id'] ?? null;

        if (! $formId) {
            return false;
        }

        $totalLeads = Lead::where('form_id', $formId)->count();
        if ($totalLeads === 0) {
            return false;
        }

        $convertedLeads = Lead::where('form_id', $formId)
            ->where('status', 'converted')
            ->count();

        $conversionRate = ($convertedLeads / $totalLeads) * 100;

        return $conversionRate < $threshold;
    }

    /**
     * Send email notification.
     */
    protected function sendEmailNotification(Alert $alert, array $data): void
    {
        // TODO: Implement email notification
        \Log::info('Email notification sent for alert', [
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'type' => $alert->type,
        ]);
    }

    /**
     * Send in-app notification.
     */
    protected function sendInAppNotification(Alert $alert, array $data): void
    {
        // TODO: Implement in-app notification
        \Log::info('In-app notification sent for alert', [
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'type' => $alert->type,
        ]);
    }

    /**
     * Send SMS notification.
     */
    protected function sendSmsNotification(Alert $alert, array $data): void
    {
        // TODO: Implement SMS notification (future sprint)
        \Log::info('SMS notification sent for alert', [
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'type' => $alert->type,
        ]);
    }

    /**
     * Generate alert name from type and conditions.
     */
    protected function generateAlertName(string $type, array $conditions, ?float $threshold): string
    {
        $baseName = match ($type) {
            'lead_stale' => __('Lead inactif'),
            'agent_performance' => __('Performance agent'),
            'conversion_rate' => __('Taux de conversion'),
            'high_volume' => __('Volume élevé'),
            'low_volume' => __('Volume faible'),
            'form_performance' => __('Performance formulaire'),
            default => __('Alerte'),
        };

        if ($threshold !== null) {
            $baseName .= " ({$threshold})";
        }

        return $baseName;
    }
}
