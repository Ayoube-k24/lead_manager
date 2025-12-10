<?php

use App\Models\Alert;
use App\Models\Role;
use App\Models\RoleAlertType;
use App\Services\AlertService;
use App\Services\AlertTypeService;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $activeTab = 'types'; // 'types' ou 'alerts'
    public string $selectedRole = 'call_center_owner';
    public bool $showCreateTypeModal = false;
    public bool $showCreateAlertModal = false;
    public ?int $editingTypeId = null;
    public ?int $editingAlertId = null;
    
    // Propriétés pour les types d'alertes
    public string $alertType = 'status_threshold';
    public string $name = '';
    public string $description = '';
    public bool $isEnabled = true;
    public array $defaultConditions = [];
    public int $order = 0;
    
    // Propriétés pour les alertes actives
    public string $alertName = '';
    public string $alertTypeForAlert = 'lead_stale';
    public array $alertConditions = [];
    public ?float $alertThreshold = null;
    public array $alertChannels = ['in_app'];
    public ?int $alertUserId = null;

    protected AlertTypeService $alertTypeService;
    protected AlertService $alertService;

    public function boot(AlertTypeService $alertTypeService, AlertService $alertService): void
    {
        $this->alertTypeService = $alertTypeService;
        $this->alertService = $alertService;
    }

    public function mount(): void
    {
        $this->resetTypeForm();
        $this->resetAlertForm();
    }

    public function updatingActiveTab(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedRole(): void
    {
        $this->resetPage();
    }

    // ========== GESTION DES TYPES D'ALERTES ==========

    public function resetTypeForm(): void
    {
        $defaultData = $this->alertTypeService->getDefaultFormData();
        $this->editingTypeId = null;
        $this->alertType = $defaultData['alert_type'];
        $this->name = $defaultData['name'];
        $this->description = $defaultData['description'];
        $this->isEnabled = $defaultData['is_enabled'];
        $this->defaultConditions = $defaultData['default_conditions'];
        $this->order = $defaultData['order'];
    }

    public function openCreateTypeModal(): void
    {
        $this->showCreateTypeModal = true;
        $this->resetTypeForm();
    }

    public function closeCreateTypeModal(): void
    {
        $this->showCreateTypeModal = false;
        $this->resetTypeForm();
    }

    public function setEditTypeId(int $id): void
    {
        $roleAlertType = RoleAlertType::findOrFail($id);
        $formData = $this->alertTypeService->getFormDataFromModel($roleAlertType);
        $this->editingTypeId = $formData['id'];
        $this->alertType = $formData['alert_type'];
        $this->name = $formData['name'];
        $this->description = $formData['description'];
        $this->isEnabled = $formData['is_enabled'];
        $this->defaultConditions = $formData['default_conditions'];
        $this->order = $formData['order'];
        $this->showCreateTypeModal = true;
    }

    public function saveType(): void
    {
        $rules = [
            'alertType' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'isEnabled' => ['boolean'],
            'defaultConditions' => ['nullable', 'array'],
            'order' => ['required', 'integer', 'min:0'],
        ];

        $this->validate($rules);

        $this->alertTypeService->saveRoleAlertType([
            'id' => $this->editingTypeId,
            'role_slug' => $this->selectedRole,
            'alert_type' => $this->alertType,
            'name' => $this->name,
            'description' => $this->description,
            'is_enabled' => $this->isEnabled,
            'default_conditions' => $this->defaultConditions ?: null,
            'order' => $this->order,
        ]);

        $this->closeCreateTypeModal();
        session()->flash('message', __('Configuration d\'alerte sauvegardée avec succès.'));
    }

    public function setToggleTypeId(int $id): void
    {
        $roleAlertType = RoleAlertType::findOrFail($id);
        $updated = $this->alertTypeService->toggleEnabled($roleAlertType);
        session()->flash('message', __('Configuration :action avec succès.', [
            'action' => $updated->is_enabled ? __('activée') : __('désactivée')
        ]));
    }

    public function setDeleteTypeId(int $id): void
    {
        $roleAlertType = RoleAlertType::findOrFail($id);
        $this->alertTypeService->delete($roleAlertType);
        session()->flash('message', __('Configuration supprimée avec succès.'));
    }

    // ========== GESTION DES ALERTES ACTIVES ==========

    public function resetAlertForm(): void
    {
        $this->alertName = '';
        $availableTypes = $this->getAvailableTypesForRole();
        $this->alertTypeForAlert = !empty($availableTypes) ? array_key_first($availableTypes) : 'lead_stale';
        $this->alertConditions = $this->getTypeConditions($this->alertTypeForAlert);
        $this->alertThreshold = null;
        $this->alertChannels = ['in_app'];
        $this->alertUserId = null;
        $this->editingAlertId = null;
    }

    public function openCreateAlertModal(): void
    {
        $this->showCreateAlertModal = true;
        $this->resetAlertForm();
    }

    public function closeCreateAlertModal(): void
    {
        $this->showCreateAlertModal = false;
        $this->resetAlertForm();
    }

    public function updatedAlertTypeForAlert(): void
    {
        $this->alertConditions = $this->getTypeConditions($this->alertTypeForAlert);
    }

    public function getAvailableTypesForRole(): array
    {
        return $this->alertService->getAvailableTypesForRole($this->selectedRole);
    }

    public function getTypeConditions(string $type): array
    {
        $roleAlertType = RoleAlertType::forRole($this->selectedRole)
            ->where('alert_type', $type)
            ->where('is_enabled', true)
            ->first();

        if ($roleAlertType && $roleAlertType->default_conditions) {
            return $roleAlertType->default_conditions;
        }

        // Fallback to hardcoded defaults
        return match ($type) {
            'status_threshold' => ['status_slug' => '', 'agent_id' => null, 'call_center_id' => null],
            'lead_stale' => ['days' => 7],
            'agent_performance' => ['threshold' => 80],
            default => [],
        };
    }

    public function setEditAlertId(int $id): void
    {
        $alert = Alert::findOrFail($id);
        $this->editingAlertId = $alert->id;
        $this->alertName = $alert->name;
        $this->alertTypeForAlert = $alert->type;
        $this->alertConditions = $alert->conditions ?? [];
        $this->alertThreshold = $alert->threshold;
        $this->alertChannels = $alert->notification_channels ?? ['in_app'];
        $this->alertUserId = $alert->user_id;
        $this->selectedRole = $alert->role_slug;
        $this->showCreateAlertModal = true;
    }

    public function saveAlert(): void
    {
        $rules = [
            'alertName' => ['required', 'string', 'max:255'],
            'alertTypeForAlert' => ['required', 'string'],
            'alertConditions' => ['required', 'array'],
            'alertChannels' => ['required', 'array', 'min:1'],
            'alertUserId' => ['nullable', 'integer', 'exists:users,id'],
        ];

        $this->validate($rules);

        $this->alertService->createOrUpdateAlert([
            'id' => $this->editingAlertId,
            'user_id' => $this->alertUserId,
            'role_slug' => $this->selectedRole,
            'name' => $this->alertName,
            'alert_type' => $this->alertTypeForAlert,
            'conditions' => $this->alertConditions,
            'threshold' => $this->alertThreshold,
            'channels' => $this->alertChannels,
        ]);

        $this->closeCreateAlertModal();
        session()->flash('message', __('Alerte sauvegardée avec succès.'));
    }

    public function setDeleteAlertId(int $id): void
    {
        $alert = Alert::findOrFail($id);
        $alert->delete();
        session()->flash('message', __('Alerte supprimée avec succès.'));
    }

    public function with(): array
    {
        $roles = $this->alertTypeService->getConfigurableRoles();
        $availableAlertTypes = $this->alertTypeService->getAvailableAlertTypes();

        if ($this->activeTab === 'types') {
            $alertTypes = $this->alertTypeService->getAlertTypesForRole($this->selectedRole);
        } else {
            $alertTypes = null;
        }

        if ($this->activeTab === 'alerts') {
            $alerts = Alert::where('role_slug', $this->selectedRole)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        } else {
            $alerts = null;
        }

        return [
            'roles' => $roles,
            'availableAlertTypes' => $availableAlertTypes,
            'alertTypes' => $alertTypes,
            'alerts' => $alerts,
            'activeTab' => $this->activeTab,
            'selectedRole' => $this->selectedRole,
            'editingTypeId' => $this->editingTypeId,
            'editingAlertId' => $this->editingAlertId,
            'alertType' => $this->alertType,
            'name' => $this->name,
            'description' => $this->description,
            'isEnabled' => $this->isEnabled,
            'order' => $this->order,
            'showCreateTypeModal' => $this->showCreateTypeModal,
            'defaultConditions' => $this->defaultConditions,
            'alertName' => $this->alertName,
            'alertTypeForAlert' => $this->alertTypeForAlert,
            'alertConditions' => $this->alertConditions,
            'alertThreshold' => $this->alertThreshold,
            'alertChannels' => $this->alertChannels,
            'alertUserId' => $this->alertUserId,
            'showCreateAlertModal' => $this->showCreateAlertModal,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Gestion des Alertes') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Configurez les types d\'alertes et gérez les alertes actives pour tous les rôles') }}
            </p>
        </div>
    </div>

    <!-- Messages flash -->
    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-circle">
            {{ session('error') }}
        </flux:callout>
    @endif

    <!-- Onglets -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="border-b border-neutral-200 dark:border-neutral-700">
            <nav class="flex -mb-px">
                <button
                    wire:click="$set('activeTab', 'types')"
                    class="px-6 py-4 text-sm font-medium transition-colors {{ $activeTab === 'types' ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400' : 'text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
                >
                    {{ __('Types d\'Alertes') }}
                </button>
                <button
                    wire:click="$set('activeTab', 'alerts')"
                    class="px-6 py-4 text-sm font-medium transition-colors {{ $activeTab === 'alerts' ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400' : 'text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
                >
                    {{ __('Alertes Actives') }}
                </button>
            </nav>
        </div>

        <!-- Filtre par rôle -->
        <div class="border-b border-neutral-200 p-4 dark:border-neutral-700">
            <flux:field>
                <flux:label>{{ __('Rôle') }}</flux:label>
                <flux:select wire:model.live="selectedRole">
                    @foreach ($roles as $role)
                        <option value="{{ $role->slug }}">{{ $role->name }}</option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>

        <!-- Contenu des onglets -->
        <div class="p-6">
            @if ($activeTab === 'types')
                <!-- Types d'Alertes -->
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Types d\'alertes pour :role', ['role' => $roles->firstWhere('slug', $selectedRole)?->name ?? $selectedRole]) }}
                    </h2>
                    <flux:button wire:click="openCreateTypeModal" variant="primary" icon="plus">
                        {{ __('Nouvelle configuration') }}
                    </flux:button>
                </div>

                @if ($alertTypes && $alertTypes->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900/50">
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Type') }}</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Nom') }}</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Description') }}</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Ordre') }}</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Statut') }}</th>
                                    <th class="px-6 py-3 text-right text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                @foreach ($alertTypes as $alertTypeItem)
                                    <tr class="transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                                        <td class="px-6 py-4">
                                            <flux:badge variant="neutral" size="sm">
                                                {{ $availableAlertTypes[$alertTypeItem->alert_type] ?? $alertTypeItem->alert_type }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium">{{ $alertTypeItem->name }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-neutral-600 dark:text-neutral-400">
                                                {{ \Illuminate\Support\Str::limit($alertTypeItem->description ?? '', 60) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-neutral-900 dark:text-neutral-100">{{ $alertTypeItem->order }}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <flux:badge :variant="$alertTypeItem->is_enabled ? 'success' : 'neutral'">
                                                {{ $alertTypeItem->is_enabled ? __('Actif') : __('Inactif') }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <flux:dropdown position="bottom" align="end">
                                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical">
                                                    {{ __('Actions') }}
                                                </flux:button>
                                                <flux:menu>
                                                    <flux:menu.radio.group>
                                                        <flux:menu.item 
                                                            wire:click="setEditTypeId({{ $alertTypeItem->id }})"
                                                            icon="pencil"
                                                        >
                                                            {{ __('Modifier') }}
                                                        </flux:menu.item>
                                                        <flux:menu.item 
                                                            wire:click="setToggleTypeId({{ $alertTypeItem->id }})"
                                                            :icon="$alertTypeItem->is_enabled ? 'eye-slash' : 'eye'"
                                                        >
                                                            {{ $alertTypeItem->is_enabled ? __('Désactiver') : __('Activer') }}
                                                        </flux:menu.item>
                                                    </flux:menu.radio.group>
                                                    <flux:menu.separator />
                                                    <flux:menu.radio.group>
                                                        <flux:menu.item 
                                                            wire:click="setDeleteTypeId({{ $alertTypeItem->id }})"
                                                            wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer cette configuration ?') }}"
                                                            icon="trash"
                                                            class="!text-red-600 dark:!text-red-400"
                                                        >
                                                            {{ __('Supprimer') }}
                                                        </flux:menu.item>
                                                    </flux:menu.radio.group>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($alertTypes->hasPages())
                        <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                            {{ $alertTypes->links() }}
                        </div>
                    @endif
                @else
                    <div class="p-12 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <svg class="h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Aucune configuration trouvée') }}</p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ __('Créez votre première configuration pour ce rôle') }}
                            </p>
                        </div>
                    </div>
                @endif
            @else
                <!-- Alertes Actives -->
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Alertes actives pour :role', ['role' => $roles->firstWhere('slug', $selectedRole)?->name ?? $selectedRole]) }}
                    </h2>
                    <flux:button wire:click="openCreateAlertModal" variant="primary" icon="plus">
                        {{ __('Nouvelle alerte') }}
                    </flux:button>
                </div>

                @if ($alerts && $alerts->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900/50">
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Nom') }}</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Type') }}</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Utilisateur') }}</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Canaux') }}</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Créée le') }}</th>
                                    <th class="px-6 py-3 text-right text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                @foreach ($alerts as $alert)
                                    <tr class="transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                                        <td class="px-6 py-4">
                                            <div class="font-medium">{{ $alert->name }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <flux:badge variant="neutral" size="sm">
                                                {{ $availableAlertTypes[$alert->type] ?? $alert->type }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-neutral-600 dark:text-neutral-400">
                                                {{ $alert->user?->name ?? __('Tous les utilisateurs') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex gap-1">
                                                @foreach ($alert->notification_channels ?? [] as $channel)
                                                    <flux:badge variant="neutral" size="sm">
                                                        {{ match($channel) {
                                                            'in_app' => __('In-App'),
                                                            'email' => __('Email'),
                                                            'sms' => __('SMS'),
                                                            default => $channel
                                                        } }}
                                                    </flux:badge>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-neutral-600 dark:text-neutral-400">
                                                {{ $alert->created_at->format('d/m/Y H:i') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <flux:dropdown position="bottom" align="end">
                                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical">
                                                    {{ __('Actions') }}
                                                </flux:button>
                                                <flux:menu>
                                                    <flux:menu.radio.group>
                                                        <flux:menu.item 
                                                            wire:click="setEditAlertId({{ $alert->id }})"
                                                            icon="pencil"
                                                        >
                                                            {{ __('Modifier') }}
                                                        </flux:menu.item>
                                                    </flux:menu.radio.group>
                                                    <flux:menu.separator />
                                                    <flux:menu.radio.group>
                                                        <flux:menu.item 
                                                            wire:click="setDeleteAlertId({{ $alert->id }})"
                                                            wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer cette alerte ?') }}"
                                                            icon="trash"
                                                            class="!text-red-600 dark:!text-red-400"
                                                        >
                                                            {{ __('Supprimer') }}
                                                        </flux:menu.item>
                                                    </flux:menu.radio.group>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($alerts->hasPages())
                        <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                            {{ $alerts->links() }}
                        </div>
                    @endif
                @else
                    <div class="p-12 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <svg class="h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Aucune alerte active') }}</p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ __('Créez votre première alerte pour ce rôle') }}
                            </p>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <!-- Modal de création/édition Type d'Alerte -->
    <flux:modal wire:model="showCreateTypeModal" name="create-alert-type">
        <form wire:submit="saveType" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ $editingTypeId ? __('Modifier la configuration') : __('Nouvelle configuration') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Configurez un type d\'alerte pour le rôle :role', ['role' => $roles->firstWhere('slug', $selectedRole)?->name ?? $selectedRole]) }}
                </p>
            </div>

            <flux:field>
                <flux:label>{{ __('Type d\'alerte') }}</flux:label>
                <flux:select wire:model.live="alertType">
                    @foreach ($availableAlertTypes as $type => $label)
                        <option value="{{ $type }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="alertType" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('Ex: Pas de réponse') }}" />
                <flux:error name="name" />
                <flux:description>{{ __('Nom affiché pour ce type d\'alerte') }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea wire:model="description" rows="3" placeholder="{{ __('Description du type d\'alerte...') }}" />
                <flux:error name="description" />
            </flux:field>

            @if ($alertType === 'status_threshold')
                <flux:field>
                    <flux:label>{{ __('Statut par défaut (optionnel)') }}</flux:label>
                    <flux:select wire:model="defaultConditions.status_slug">
                        <option value="">{{ __('Aucun statut par défaut') }}</option>
                        @foreach (\App\Models\LeadStatus::allStatuses() as $status)
                            <option value="{{ $status->slug }}">{{ $status->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="defaultConditions.status_slug" />
                </flux:field>
            @endif

            <flux:field>
                <flux:label>{{ __('Ordre d\'affichage') }}</flux:label>
                <flux:input wire:model="order" type="number" min="0" />
                <flux:error name="order" />
            </flux:field>

            <flux:field>
                <flux:checkbox wire:model="isEnabled" label="{{ __('Activer ce type d\'alerte') }}" />
                <flux:error name="isEnabled" />
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeCreateTypeModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveType">
                        {{ __('Enregistrer') }}
                    </span>
                    <span wire:loading wire:target="saveType">
                        {{ __('Enregistrement...') }}
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Modal de création/édition Alerte Active -->
    <flux:modal wire:model="showCreateAlertModal" name="create-alert">
        <form wire:submit="saveAlert" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ $editingAlertId ? __('Modifier l\'alerte') : __('Nouvelle alerte') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Créez une alerte pour le rôle :role', ['role' => $roles->firstWhere('slug', $selectedRole)?->name ?? $selectedRole]) }}
                </p>
            </div>

            <flux:field>
                <flux:label>{{ __('Nom de l\'alerte') }}</flux:label>
                <flux:input wire:model="alertName" placeholder="{{ __('Ex: Leads sans réponse depuis 7 jours') }}" />
                <flux:error name="alertName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Type d\'alerte') }}</flux:label>
                <flux:select wire:model.live="alertTypeForAlert">
                    @foreach ($availableAlertTypes as $type => $label)
                        <option value="{{ $type }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="alertTypeForAlert" />
            </flux:field>

            @if ($alertTypeForAlert === 'status_threshold')
                <flux:field>
                    <flux:label>{{ __('Statut') }}</flux:label>
                    <flux:select wire:model="alertConditions.status_slug">
                        <option value="">{{ __('Tous les statuts') }}</option>
                        @foreach (\App\Models\LeadStatus::allStatuses() as $status)
                            <option value="{{ $status->slug }}">{{ $status->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="alertConditions.status_slug" />
                </flux:field>
            @endif

            <flux:field>
                <flux:label>{{ __('Canaux de notification') }}</flux:label>
                <flux:checkbox wire:model="alertChannels" value="in_app" label="{{ __('Notification in-app') }}" />
                <flux:checkbox wire:model="alertChannels" value="email" label="{{ __('Email') }}" />
                <flux:checkbox wire:model="alertChannels" value="sms" label="{{ __('SMS') }}" />
                <flux:error name="alertChannels" />
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeCreateAlertModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveAlert">
                        {{ __('Enregistrer') }}
                    </span>
                    <span wire:loading wire:target="saveAlert">
                        {{ __('Enregistrement...') }}
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

