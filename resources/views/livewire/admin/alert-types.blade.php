<?php

use App\Models\Role;
use App\Models\RoleAlertType;
use App\Services\AlertTypeService;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $selectedRole = 'call_center_owner';
    public bool $showCreateModal = false;
    public ?int $editingId = null;
    public string $alertType = 'status_threshold';
    public string $name = '';
    public string $description = '';
    public bool $isEnabled = true;
    public array $defaultConditions = [];
    public int $order = 0;

    protected AlertTypeService $alertTypeService;

    public function boot(AlertTypeService $alertTypeService): void
    {
        $this->alertTypeService = $alertTypeService;
    }

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatingSelectedRole(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $defaultData = $this->alertTypeService->getDefaultFormData();
        $this->editingId = null;
        $this->alertType = $defaultData['alert_type'];
        $this->name = $defaultData['name'];
        $this->description = $defaultData['description'];
        $this->isEnabled = $defaultData['is_enabled'];
        $this->defaultConditions = $defaultData['default_conditions'];
        $this->order = $defaultData['order'];
    }

    public function openCreateModal(): void
    {
        $this->showCreateModal = true;
        $this->resetForm();
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function setEditId(int $id): void
    {
        $roleAlertType = RoleAlertType::findOrFail($id);
        $formData = $this->alertTypeService->getFormDataFromModel($roleAlertType);
        $this->editingId = $formData['id'];
        $this->alertType = $formData['alert_type'];
        $this->name = $formData['name'];
        $this->description = $formData['description'];
        $this->isEnabled = $formData['is_enabled'];
        $this->defaultConditions = $formData['default_conditions'];
        $this->order = $formData['order'];
        $this->showCreateModal = true;
    }

    public function save(): void
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
            'id' => $this->editingId,
            'role_slug' => $this->selectedRole,
            'alert_type' => $this->alertType,
            'name' => $this->name,
            'description' => $this->description,
            'is_enabled' => $this->isEnabled,
            'default_conditions' => $this->defaultConditions ?: null,
            'order' => $this->order,
        ]);

        $this->closeCreateModal();
        session()->flash('message', __('Configuration d\'alerte sauvegardée avec succès.'));
    }

    public function setToggleId(int $id): void
    {
        $roleAlertType = RoleAlertType::findOrFail($id);
        $updated = $this->alertTypeService->toggleEnabled($roleAlertType);
        session()->flash('message', __('Configuration :action avec succès.', [
            'action' => $updated->is_enabled ? __('activée') : __('désactivée')
        ]));
    }

    public function setDeleteId(int $id): void
    {
        $roleAlertType = RoleAlertType::findOrFail($id);
        $this->alertTypeService->delete($roleAlertType);
        session()->flash('message', __('Configuration supprimée avec succès.'));
    }

    public function with(): array
    {
        $alertTypes = $this->alertTypeService->getAlertTypesForRole($this->selectedRole);
        $roles = $this->alertTypeService->getConfigurableRoles();
        $availableAlertTypes = $this->alertTypeService->getAvailableAlertTypes();

        return [
            'alertTypes' => $alertTypes,
            'roles' => $roles,
            'availableAlertTypes' => $availableAlertTypes,
            'editingId' => $this->editingId,
            'selectedRole' => $this->selectedRole,
            'alertType' => $this->alertType,
            'name' => $this->name,
            'description' => $this->description,
            'isEnabled' => $this->isEnabled,
            'order' => $this->order,
            'showCreateModal' => $this->showCreateModal,
            'defaultConditions' => $this->defaultConditions,
        ];
    }
}; ?>

@php
    // Fallback pour garantir que les variables sont toujours disponibles
    if (!isset($roles)) {
        $roles = \App\Models\Role::whereIn('slug', ['super_admin', 'call_center_owner', 'supervisor', 'agent'])
            ->orderBy('name')
            ->get();
    }
    if (!isset($alertTypes)) {
        try {
            $selectedRoleValue = $selectedRole ?? 'call_center_owner';
            $alertTypes = \App\Models\RoleAlertType::forRole($selectedRoleValue)
                ->orderBy('order')
                ->orderBy('name')
                ->paginate(20);
        } catch (\Exception $e) {
            $alertTypes = new \Illuminate\Pagination\LengthAwarePaginator(collect(), 0, 20, 1);
        }
    }
    if (!isset($availableAlertTypes)) {
        $alertTypeService = app(\App\Services\AlertTypeService::class);
        $availableAlertTypes = $alertTypeService->getAvailableAlertTypes();
    }
    if (!isset($editingId)) $editingId = null;
    if (!isset($selectedRole)) $selectedRole = 'call_center_owner';
    if (!isset($alertType)) $alertType = 'status_threshold';
    if (!isset($name)) $name = '';
    if (!isset($description)) $description = '';
    if (!isset($isEnabled)) $isEnabled = true;
    if (!isset($order)) $order = 0;
    if (!isset($showCreateModal)) $showCreateModal = false;
    if (!isset($defaultConditions)) $defaultConditions = [];
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Configuration des Types d\'Alertes') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Configurez les types d\'alertes disponibles pour chaque rôle') }}
            </p>
        </div>
        <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
            {{ __('Nouvelle configuration') }}
        </flux:button>
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

    <!-- Filtre par rôle -->
    <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <flux:field>
            <flux:label>{{ __('Rôle') }}</flux:label>
            <flux:select wire:model.live="selectedRole">
                @php
                    $rolesList = $roles ?? \App\Models\Role::whereIn('slug', ['super_admin', 'call_center_owner', 'supervisor', 'agent'])->orderBy('name')->get();
                @endphp
                @foreach ($rolesList as $role)
                    <option value="{{ $role->slug }}">{{ $role->name }}</option>
                @endforeach
            </flux:select>
        </flux:field>
    </div>

    <!-- Liste des configurations -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        @if ($alertTypes->count() > 0)
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
                        @foreach ($alertTypes as $alertType)
                            <tr class="transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                                <td class="px-6 py-4">
                                    <flux:badge variant="neutral" size="sm">
                                        {{ $availableAlertTypes[$alertType->alert_type] ?? $alertType->alert_type }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium">{{ $alertType->name }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ \Illuminate\Support\Str::limit($alertType->description ?? '', 60) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm text-neutral-900 dark:text-neutral-100">{{ $alertType->order }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <flux:badge :variant="$alertType->is_enabled ? 'success' : 'neutral'">
                                        {{ $alertType->is_enabled ? __('Actif') : __('Inactif') }}
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
                                                    wire:click="setEditId({{ $alertType->id }})"
                                                    icon="pencil"
                                                >
                                                    {{ __('Modifier') }}
                                                </flux:menu.item>
                                                <flux:menu.item 
                                                    wire:click="setToggleId({{ $alertType->id }})"
                                                    :icon="$alertType->is_enabled ? 'eye-slash' : 'eye'"
                                                >
                                                    {{ $alertType->is_enabled ? __('Désactiver') : __('Activer') }}
                                                </flux:menu.item>
                                            </flux:menu.radio.group>

                                            <flux:menu.separator />

                                            <flux:menu.radio.group>
                                                <flux:menu.item 
                                                    wire:click="setDeleteId({{ $alertType->id }})"
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

            <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                {{ $alertTypes->links() }}
            </div>
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
    </div>

    <!-- Modal de création/édition -->
    <flux:modal wire:model="showCreateModal" name="create-alert-type">
        <form wire:submit="save" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ $editingId ? __('Modifier la configuration') : __('Nouvelle configuration') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    @php
                        $rolesList = $roles ?? \App\Models\Role::whereIn('slug', ['super_admin', 'call_center_owner', 'supervisor', 'agent'])->orderBy('name')->get();
                    @endphp
                    {{ __('Configurez un type d\'alerte pour le rôle :role', ['role' => $rolesList->firstWhere('slug', $selectedRole ?? 'call_center_owner')?->name ?? ($selectedRole ?? 'call_center_owner')]) }}
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
                    <flux:description>{{ __('Le statut sera pré-sélectionné lors de la création d\'une alerte de ce type') }}</flux:description>
                </flux:field>
            @endif

            <flux:field>
                <flux:label>{{ __('Ordre d\'affichage') }}</flux:label>
                <flux:input wire:model="order" type="number" min="0" />
                <flux:error name="order" />
                <flux:description>{{ __('Ordre d\'affichage dans la liste (0 = premier)') }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:checkbox wire:model="isEnabled" label="{{ __('Activer ce type d\'alerte') }}" />
                <flux:error name="isEnabled" />
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeCreateModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">
                        {{ __('Enregistrer') }}
                    </span>
                    <span wire:loading wire:target="save" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Enregistrement...') }}
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
