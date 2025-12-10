<?php

namespace App\Services;

use App\Models\Role;
use App\Models\RoleAlertType;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AlertTypeService
{
    /**
     * Get available alert types with their labels.
     */
    public function getAvailableAlertTypes(): array
    {
        return [
            'lead_stale' => __('Lead inactif'),
            'agent_performance' => __('Performance agent'),
            'conversion_rate' => __('Taux de conversion'),
            'high_volume' => __('Volume élevé'),
            'low_volume' => __('Volume faible'),
            'form_performance' => __('Performance formulaire'),
            'status_threshold' => __('Seuil de statut'),
        ];
    }

    /**
     * Get alert types for a specific role with pagination.
     */
    public function getAlertTypesForRole(string $roleSlug, int $perPage = 20): LengthAwarePaginator
    {
        try {
            return RoleAlertType::forRole($roleSlug)
                ->orderBy('order')
                ->orderBy('name')
                ->paginate($perPage);
        } catch (\Exception $e) {
            // Si la table n'existe pas, retourner un paginator vide
            return new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                1
            );
        }
    }

    /**
     * Get all roles that can have alert types configured.
     */
    public function getConfigurableRoles(): Collection
    {
        return Role::whereIn('slug', ['super_admin', 'call_center_owner', 'supervisor', 'agent'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Create or update a role alert type.
     */
    public function saveRoleAlertType(array $data): RoleAlertType
    {
        return RoleAlertType::updateOrCreate(
            [
                'id' => $data['id'] ?? null,
                'role_slug' => $data['role_slug'],
                'alert_type' => $data['alert_type'],
            ],
            [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_enabled' => $data['is_enabled'] ?? true,
                'default_conditions' => $data['default_conditions'] ?? null,
                'order' => $data['order'] ?? 0,
            ]
        );
    }

    /**
     * Toggle the enabled status of a role alert type.
     */
    public function toggleEnabled(RoleAlertType $roleAlertType): RoleAlertType
    {
        $roleAlertType->update(['is_enabled' => ! $roleAlertType->is_enabled]);

        return $roleAlertType->fresh();
    }

    /**
     * Delete a role alert type.
     */
    public function delete(RoleAlertType $roleAlertType): bool
    {
        return $roleAlertType->delete();
    }

    /**
     * Get default form data for creating a new alert type.
     */
    public function getDefaultFormData(): array
    {
        return [
            'alert_type' => 'status_threshold',
            'name' => '',
            'description' => '',
            'is_enabled' => true,
            'default_conditions' => [],
            'order' => 0,
        ];
    }

    /**
     * Get form data from a RoleAlertType model.
     */
    public function getFormDataFromModel(RoleAlertType $roleAlertType): array
    {
        return [
            'id' => $roleAlertType->id,
            'alert_type' => $roleAlertType->alert_type,
            'name' => $roleAlertType->name,
            'description' => $roleAlertType->description ?? '',
            'is_enabled' => $roleAlertType->is_enabled,
            'default_conditions' => $roleAlertType->default_conditions ?? [],
            'order' => $roleAlertType->order,
        ];
    }
}
