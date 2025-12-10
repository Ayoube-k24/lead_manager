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
            'role_slug' => $user->role?->slug,
            'name' => $this->generateAlertName($type, $conditions, $threshold),
            'type' => $type,
            'conditions' => $conditions,
            'threshold' => $threshold,
            'notification_channels' => $channels,
            'is_active' => true,
        ]);
    }

    /**
     * Create or update an alert from array data.
     */
    public function createOrUpdateAlert(array $data): Alert
    {
        $alertData = [
            'user_id' => $data['user_id'] ?? null,
            'role_slug' => $data['role_slug'],
            'name' => $data['name'],
            'type' => $data['alert_type'],
            'conditions' => $data['conditions'] ?? [],
            'threshold' => $data['threshold'] ?? null,
            'notification_channels' => $data['channels'] ?? ['in_app'],
            'is_active' => true,
        ];

        if (isset($data['id']) && $data['id']) {
            $alert = Alert::findOrFail($data['id']);
            $alert->update($alertData);

            return $alert;
        }

        return Alert::create($alertData);
    }

    /**
     * Check all active alerts.
     */
    public function checkAlerts(?User $user = null, ?string $roleSlug = null): Collection
    {
        $query = Alert::where('is_active', true);

        if ($user) {
            $query->where('user_id', $user->id);
        }

        if ($roleSlug) {
            $query->where('role_slug', $roleSlug);
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
     * Check alerts for a specific role.
     */
    public function checkAlertsForRole(string $roleSlug): Collection
    {
        return $this->checkAlerts(null, $roleSlug);
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
            'status_threshold' => $this->evaluateStatusThreshold($alert),
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
     * Evaluate status threshold condition.
     * Alerts when the number of leads with a specific status reaches a threshold.
     */
    protected function evaluateStatusThreshold(Alert $alert): bool
    {
        $threshold = $alert->threshold ?? 10;
        $statusSlug = $alert->conditions['status_slug'] ?? null;
        $agentId = $alert->conditions['agent_id'] ?? null;
        $callCenterId = $alert->conditions['call_center_id'] ?? null;

        if (! $statusSlug) {
            return false;
        }

        $query = Lead::query();

        // Filter by status using status_id (preferred) or status enum (fallback)
        $status = \App\Models\LeadStatus::where('slug', $statusSlug)->first();
        if ($status) {
            $query->where('status_id', $status->id);
        } else {
            // Fallback to enum if status not found in database
            try {
                $enumStatus = \App\LeadStatus::tryFrom($statusSlug);
                if ($enumStatus) {
                    $query->where('status', $statusSlug);
                } else {
                    return false;
                }
            } catch (\ValueError $e) {
                return false;
            }
        }

        // Filter by agent if specified
        if ($agentId) {
            $query->where('assigned_to', $agentId);
        }

        // Filter by call center if specified
        if ($callCenterId) {
            $query->where('call_center_id', $callCenterId);
        }

        $leadsCount = $query->count();

        return $leadsCount >= $threshold;
    }

    /**
     * Send email notification.
     */
    protected function sendEmailNotification(Alert $alert, array $data): void
    {
        // Send notification to all users with the same role as the alert
        if ($alert->role_slug) {
            $users = User::whereHas('role', function ($query) use ($alert) {
                $query->where('slug', $alert->role_slug);
            })->get();

            foreach ($users as $user) {
                // TODO: Implement email notification system
                \Log::info('Email notification sent for alert', [
                    'alert_id' => $alert->id,
                    'user_id' => $user->id,
                    'role_slug' => $alert->role_slug,
                    'type' => $alert->type,
                ]);
            }
        } else {
            // Fallback to user-specific notification if no role_slug
            \Log::info('Email notification sent for alert', [
                'alert_id' => $alert->id,
                'user_id' => $alert->user_id,
                'type' => $alert->type,
            ]);
        }
    }

    /**
     * Send in-app notification.
     */
    protected function sendInAppNotification(Alert $alert, array $data): void
    {
        // Send notification to all users with the same role as the alert
        if ($alert->role_slug) {
            $users = User::whereHas('role', function ($query) use ($alert) {
                $query->where('slug', $alert->role_slug);
            })->get();

            foreach ($users as $user) {
                // TODO: Implement in-app notification system
                \Log::info('In-app notification sent for alert', [
                    'alert_id' => $alert->id,
                    'user_id' => $user->id,
                    'role_slug' => $alert->role_slug,
                    'type' => $alert->type,
                ]);
            }
        } else {
            // Fallback to user-specific notification if no role_slug
            \Log::info('In-app notification sent for alert', [
                'alert_id' => $alert->id,
                'user_id' => $alert->user_id,
                'type' => $alert->type,
            ]);
        }
    }

    /**
     * Send SMS notification.
     */
    protected function sendSmsNotification(Alert $alert, array $data): void
    {
        // Send notification to all users with the same role as the alert
        if ($alert->role_slug) {
            $users = User::whereHas('role', function ($query) use ($alert) {
                $query->where('slug', $alert->role_slug);
            })->get();

            foreach ($users as $user) {
                // TODO: Implement SMS notification system (future sprint)
                \Log::info('SMS notification sent for alert', [
                    'alert_id' => $alert->id,
                    'user_id' => $user->id,
                    'role_slug' => $alert->role_slug,
                    'type' => $alert->type,
                ]);
            }
        } else {
            // Fallback to user-specific notification if no role_slug
            \Log::info('SMS notification sent for alert', [
                'alert_id' => $alert->id,
                'user_id' => $alert->user_id,
                'type' => $alert->type,
            ]);
        }
    }

    /**
     * Generate alert name from type and conditions.
     */
    protected function generateAlertName(string $type, array $conditions, ?float $threshold): string
    {
        // Try to get name from RoleAlertType if status_threshold with specific status
        if ($type === 'status_threshold' && isset($conditions['status_slug'])) {
            $status = \App\Models\LeadStatus::getBySlug($conditions['status_slug']);
            if ($status) {
                $baseName = $status->name;
            } else {
                $baseName = __('Seuil de statut');
            }
        } else {
            $baseName = match ($type) {
                'lead_stale' => __('Lead inactif'),
                'agent_performance' => __('Performance agent'),
                'conversion_rate' => __('Taux de conversion'),
                'high_volume' => __('Volume élevé'),
                'low_volume' => __('Volume faible'),
                'form_performance' => __('Performance formulaire'),
                'status_threshold' => __('Seuil de statut'),
                default => __('Alerte'),
            };
        }

        if ($threshold !== null) {
            $baseName .= " ({$threshold})";
        }

        return $baseName;
    }

    /**
     * Get available alert types for a role.
     */
    public function getAvailableTypesForRole(string $roleSlug): array
    {
        // Check if table exists
        if (! \Illuminate\Support\Facades\Schema::hasTable('role_alert_types')) {
            // Return default types if table doesn't exist
            return $this->getDefaultTypesForRole($roleSlug);
        }

        $types = \App\Models\RoleAlertType::getEnabledForRole($roleSlug);

        // If no configured types, return defaults
        if ($types->isEmpty()) {
            return $this->getDefaultTypesForRole($roleSlug);
        }

        $result = [];
        foreach ($types as $type) {
            $result[$type->alert_type] = [
                'name' => $type->name,
                'description' => $type->description,
                'default_conditions' => $type->default_conditions ?? [],
            ];
        }

        return $result;
    }

    /**
     * Get default alert types for a role (fallback).
     */
    protected function getDefaultTypesForRole(string $roleSlug): array
    {
        // Default types for each role
        $defaults = match ($roleSlug) {
            'agent' => [
                'status_threshold' => [
                    'name' => __('Seuil de statut'),
                    'description' => __('Alerte lorsque le nombre de vos leads avec un statut spécifique atteint un seuil'),
                    'default_conditions' => [],
                ],
            ],
            default => [
                'lead_stale' => [
                    'name' => __('Lead inactif'),
                    'description' => __('Détecte les leads qui n\'ont pas été mis à jour depuis X heures'),
                    'default_conditions' => ['hours' => 24],
                ],
                'agent_performance' => [
                    'name' => __('Performance agent'),
                    'description' => __('Surveille le taux de conversion d\'un agent spécifique'),
                    'default_conditions' => [],
                ],
                'conversion_rate' => [
                    'name' => __('Taux de conversion'),
                    'description' => __('Surveille le taux de conversion global de tous les leads'),
                    'default_conditions' => [],
                ],
                'high_volume' => [
                    'name' => __('Volume élevé'),
                    'description' => __('Détecte quand trop de leads arrivent dans un laps de temps'),
                    'default_conditions' => ['hours' => 1],
                ],
                'low_volume' => [
                    'name' => __('Volume faible'),
                    'description' => __('Détecte quand trop peu de leads arrivent dans un laps de temps'),
                    'default_conditions' => ['hours' => 1],
                ],
                'form_performance' => [
                    'name' => __('Performance formulaire'),
                    'description' => __('Surveille le taux de conversion d\'un formulaire spécifique'),
                    'default_conditions' => [],
                ],
                'status_threshold' => [
                    'name' => __('Seuil de statut'),
                    'description' => __('Alerte lorsque le nombre de leads avec un statut spécifique atteint un seuil'),
                    'default_conditions' => [],
                ],
            ],
        };

        return $defaults;
    }
}
