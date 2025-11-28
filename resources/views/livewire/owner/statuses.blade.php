<?php

use App\Models\LeadStatus;
use App\Services\LeadStatusService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public array $selected = [];
    public bool $showDeleteModal = false;
    public ?int $statusToDelete = null;
    public ?int $replacementStatusId = null;
    public bool $showBulkDeleteModal = false;
    public ?int $bulkReplacementStatusId = null;

    public function updatedSearch(): void
    {
        $this->reset('selected');
        $this->resetPage();
    }

    public function updatingPage(): void
    {
        $this->reset('selected');
    }

    public function selectAll(): void
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;
        
        // Normaliser les IDs sélectionnés en entiers
        $currentSelected = array_map('intval', $this->selected);
        
        // Obtenir les IDs de la page actuelle depuis la pagination
        $query = LeadStatus::query()
            ->withCount(['leads' => function ($query) use ($callCenter) {
                if ($callCenter) {
                    $query->where('call_center_id', $callCenter->id);
                }
            }])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"))
            ->orderBy('order')
            ->orderBy('name');
        
        $paginator = $query->paginate(15);
        
        // Filter to only non-system statuses from current page
        $allIds = [];
        foreach ($paginator->items() as $status) {
            if (! $status->is_system) {
                $allIds[] = (int) $status->id;
            }
        }
        
        // Vérifier si tous les éléments de la page actuelle sont déjà sélectionnés
        $allSelected = count($allIds) > 0 && count(array_intersect($currentSelected, $allIds)) === count($allIds);
        
        if ($allSelected) {
            // Désélectionner uniquement les éléments de la page actuelle
            $this->selected = array_values(array_diff($currentSelected, $allIds));
        } else {
            // Sélectionner tous les éléments de la page actuelle (en gardant les autres sélections)
            $this->selected = array_values(array_unique(array_merge($currentSelected, $allIds)));
        }
    }

    public function deselectAll(): void
    {
        $this->reset('selected');
    }

    public function getSelectedCountProperty(): int
    {
        return count($this->selected);
    }

    public function confirmDelete(LeadStatus $status): void
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;
        
        // Check if status is used globally (owners need replacement if used anywhere)
        $globalLeadsCount = $status->leads()->count();
        $callCenterLeadsCount = $status->leads()
            ->when($callCenter, fn ($q) => $q->where('call_center_id', $callCenter->id))
            ->count();
            
        // Show modal if used anywhere (we'll replace in call center, but need to check global usage)
        if ($globalLeadsCount > 0) {
            $this->statusToDelete = $status->id;
            $this->replacementStatusId = null;
            $this->showDeleteModal = true;
        } else {
            // Direct delete if not used anywhere
            $this->delete($status);
        }
    }

    public function delete(LeadStatus $status): void
    {
        try {
            $service = app(LeadStatusService::class);
            $replacementStatus = $this->replacementStatusId 
                ? LeadStatus::find($this->replacementStatusId) 
                : null;

            $user = Auth::user();
            $callCenter = $user->callCenter;
            $leadsCount = $status->leads()
                ->when($callCenter, fn ($q) => $q->where('call_center_id', $callCenter->id))
                ->count();
                
            $service->deleteStatus($status, $replacementStatus, $callCenter);
            
            $message = __('Statut supprimé avec succès.');
            if ($replacementStatus && $leadsCount > 0) {
                $message = __('Statut supprimé avec succès. :count lead(s) ont été mis à jour.', [
                    'count' => $leadsCount
                ]);
            }
            
            session()->flash('message', $message);
            $this->dispatch('status-deleted');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
        
        $this->closeDeleteModal();
        $this->reset('selected');
    }

    public function deleteStatusFromModal(): void
    {
        if (! $this->statusToDelete) {
            return;
        }

        $status = LeadStatus::find($this->statusToDelete);
        if ($status) {
            $this->delete($status);
        }
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->statusToDelete = null;
        $this->replacementStatusId = null;
    }

    public function confirmBulkDelete(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $user = Auth::user();
        $callCenter = $user->callCenter;

        // Check if any selected status is used in this call center
        $usedStatuses = LeadStatus::whereIn('id', $this->selected)
            ->whereHas('leads', function ($q) use ($callCenter) {
                if ($callCenter) {
                    $q->where('call_center_id', $callCenter->id);
                }
            })
            ->get();

        // Also check if any status is used globally (for owners, we need replacement if used anywhere)
        $globallyUsedStatuses = LeadStatus::whereIn('id', $this->selected)
            ->whereHas('leads')
            ->get();

        if ($usedStatuses->count() > 0 || $globallyUsedStatuses->count() > 0) {
            $this->bulkReplacementStatusId = null;
            $this->showBulkDeleteModal = true;
        } else {
            $this->deleteSelected();
        }
    }

    public function deleteSelected(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $service = app(LeadStatusService::class);
        $count = 0;
        $errors = 0;
        $totalLeadsUpdated = 0;

        $replacementStatus = $this->bulkReplacementStatusId 
            ? LeadStatus::find($this->bulkReplacementStatusId) 
            : null;

        $user = Auth::user();
        $callCenter = $user->callCenter;

        foreach ($this->selected as $statusId) {
            try {
                $status = LeadStatus::find($statusId);
                if ($status && ! $status->is_system) {
                    $leadsCount = $status->leads()
                        ->when($callCenter, fn ($q) => $q->where('call_center_id', $callCenter->id))
                        ->count();
                    $service->deleteStatus($status, $replacementStatus, $callCenter);
                    $count++;
                    $totalLeadsUpdated += $leadsCount;
                }
            } catch (\Exception $e) {
                $errors++;
            }
        }

        if ($count > 0) {
            $message = __(':count statut(s) supprimé(s) avec succès.', ['count' => $count]);
            if ($totalLeadsUpdated > 0) {
                $message .= ' ' . __(':count lead(s) ont été mis à jour.', ['count' => $totalLeadsUpdated]);
            }
            session()->flash('message', $message);
            $this->dispatch('status-deleted');
        }
        if ($errors > 0) {
            session()->flash('error', __(':count statut(s) n\'ont pas pu être supprimé(s).', ['count' => $errors]));
        }

        $this->closeBulkDeleteModal();
        $this->reset('selected');
        $this->resetPage();
    }

    public function closeBulkDeleteModal(): void
    {
        $this->showBulkDeleteModal = false;
        $this->bulkReplacementStatusId = null;
    }

    public function with(): array
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        // Owners can see all statuses, but we show usage count for their call center
        $statuses = LeadStatus::query()
            ->withCount(['leads' => function ($query) use ($callCenter) {
                if ($callCenter) {
                    $query->where('call_center_id', $callCenter->id);
                }
            }])
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"))
            ->orderBy('order')
            ->orderBy('name')
            ->paginate(15);

        $availableStatuses = LeadStatus::query()
            ->whereNotIn('id', $this->selected)
            ->orderBy('name')
            ->get();

        $statusToDelete = $this->statusToDelete ? LeadStatus::withCount(['leads' => function ($query) use ($callCenter) {
            if ($callCenter) {
                $query->where('call_center_id', $callCenter->id);
            }
        }])->find($this->statusToDelete) : null;

        // Normaliser les IDs sélectionnés en entiers pour la comparaison
        $selectedIds = array_map('intval', $this->selected);

        return [
            'statuses' => $statuses,
            'availableStatuses' => $availableStatuses,
            'statusToDelete' => $statusToDelete,
            'selectedCount' => count($this->selected),
            'selected' => $selectedIds,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Statuts des leads') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Gérez les statuts disponibles pour votre centre d\'appels') }}</p>
        </div>
        <flux:button href="{{ route('owner.statuses.create') }}" variant="primary" icon="plus" wire:navigate>
            {{ __('Nouveau statut') }}
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

    <!-- Filtres et recherche -->
    <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="flex items-center justify-between">
            <flux:field class="flex-1">
                <flux:label>{{ __('Rechercher') }}</flux:label>
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="{{ __('Rechercher par nom, slug ou description...') }}"
                    icon="magnifying-glass"
                />
            </flux:field>
            <div class="ml-4 flex items-center gap-2">
                <span wire:loading.remove wire:target="search" class="text-sm text-neutral-600 dark:text-neutral-400">
                    {{ $statuses->total() }} {{ __('statut(s)') }}
                </span>
                <span wire:loading wire:target="search" class="text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Recherche en cours...') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Barre d'actions en masse -->
    @if ($selectedCount > 0)
        <div 
            wire:transition
            class="flex flex-col gap-4 rounded-lg border-2 border-red-300 bg-red-50 p-4 shadow-sm transition-all dark:border-red-700 dark:bg-red-900/20 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                    <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-red-900 dark:text-red-100">
                        {{ $selectedCount }} {{ __('élément(s) sélectionné(s)') }}
                    </p>
                    <p class="text-xs text-red-700 dark:text-red-300">
                        {{ __('La suppression est irréversible. Si les statuts sont utilisés, vous devrez sélectionner un statut de remplacement.') }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <flux:button 
                    wire:click="confirmBulkDelete"
                    variant="danger" 
                    size="sm"
                    icon="trash"
                    wire:loading.attr="disabled"
                    wire:target="confirmBulkDelete,deleteSelected"
                >
                    <span wire:loading.remove wire:target="confirmBulkDelete,deleteSelected">
                        {{ __('Supprimer') }}
                    </span>
                    <span wire:loading wire:target="confirmBulkDelete,deleteSelected" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Suppression...') }}
                    </span>
                </flux:button>
                <flux:button 
                    wire:click="deselectAll"
                    variant="ghost" 
                    size="sm"
                    wire:loading.attr="disabled"
                    wire:target="confirmBulkDelete,deleteSelected"
                >
                    {{ __('Annuler') }}
                </flux:button>
            </div>
        </div>
    @endif

    <!-- Liste des statuts -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        @if ($statuses->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                                @php
                                    $selectableIds = [];
                                    foreach ($statuses as $status) {
                                        if (!$status->is_system) {
                                            $selectableIds[] = (int) $status->id;
                                        }
                                    }
                                    $allSelected = count($selectableIds) > 0 && count(array_intersect($selected, $selectableIds)) === count($selectableIds) && count($selectableIds) > 0;
                                @endphp
                                <flux:checkbox 
                                    wire:click="selectAll"
                                    :checked="$allSelected"
                                />
                            </th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Statut') }}</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Slug') }}</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Description') }}</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Propriétés') }}</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Utilisation') }}</th>
                            <th class="px-6 py-3 text-right text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($statuses as $status)
                            <tr class="transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                                <td class="px-6 py-4">
                                    <flux:checkbox 
                                        wire:model.live="selected"
                                        :value="(int) $status->id"
                                        :checked="in_array((int) $status->id, $selected)"
                                        :disabled="$status->is_system"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full" style="background-color: {{ $status->color }}20;">
                                            <div class="h-3 w-3 rounded-full" style="background-color: {{ $status->color }};"></div>
                                        </div>
                                        <div class="font-medium text-neutral-900 dark:text-neutral-100">
                                            {{ $status->name }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-mono text-xs text-neutral-500 dark:text-neutral-500">
                                        {{ $status->slug }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $status->description ?: '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($status->is_system)
                                            <flux:badge variant="primary" size="sm">{{ __('Système') }}</flux:badge>
                                        @endif
                                        @if ($status->is_active)
                                            <flux:badge variant="success" size="sm">{{ __('Actif') }}</flux:badge>
                                        @endif
                                        @if ($status->is_final)
                                            <flux:badge variant="warning" size="sm">{{ __('Final') }}</flux:badge>
                                        @endif
                                        @if ($status->can_be_set_after_call)
                                            <flux:badge variant="ghost" size="sm">{{ __('Post-appel') }}</flux:badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        @php
                                            $user = Auth::user();
                                            $callCenter = $user->callCenter;
                                            $globalCount = $status->leads()->count();
                                        @endphp
                                        @if ($callCenter)
                                            {{ __(':count dans votre centre', ['count' => $status->leads_count]) }}
                                            @if ($globalCount > $status->leads_count)
                                                <span class="text-xs text-neutral-400">({{ $globalCount }} au total)</span>
                                            @endif
                                        @else
                                            {{ __(':count lead(s)', ['count' => $status->leads_count]) }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            <flux:menu.radio.group>
                                                <flux:menu.item 
                                                    href="{{ route('owner.statuses.edit', $status) }}" 
                                                    icon="pencil"
                                                    wire:navigate
                                                >
                                                    {{ __('Modifier') }}
                                                </flux:menu.item>
                                            </flux:menu.radio.group>
                                            @if (! $status->is_system)
                                                <flux:menu.separator />
                                                <flux:menu.radio.group>
                                                    <flux:menu.item 
                                                        wire:click="confirmDelete({{ $status->id }})"
                                                        icon="trash"
                                                        class="!text-red-600 dark:!text-red-400"
                                                    >
                                                        {{ __('Supprimer') }}
                                                    </flux:menu.item>
                                                </flux:menu.radio.group>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                {{ $statuses->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <p class="text-neutral-500 dark:text-neutral-400">{{ __('Aucun statut trouvé') }}</p>
                <flux:button href="{{ route('owner.statuses.create') }}" variant="primary" class="mt-4" wire:navigate>
                    {{ __('Créer le premier statut') }}
                </flux:button>
            </div>
        @endif
    </div>

    <!-- Modal de suppression avec remplacement -->
    @if ($statusToDelete)
        <flux:modal wire:model="showDeleteModal" name="delete-status">
            <form wire:submit.prevent="deleteStatusFromModal" class="space-y-6">
                <div>
                    <h2 class="text-xl font-semibold">{{ __('Supprimer le statut') }}</h2>
                    <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Êtes-vous sûr de vouloir supprimer le statut ":name" ?', ['name' => $statusToDelete->name]) }}
                    </p>
                </div>

                @php
                    $user = Auth::user();
                    $callCenter = $user->callCenter;
                    $globalCount = $statusToDelete->leads()->count();
                @endphp
                @if ($statusToDelete->leads_count > 0 || $globalCount > 0)
                    <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                        <p class="text-sm font-medium text-yellow-900 dark:text-yellow-100">
                            @if ($statusToDelete->leads_count > 0)
                                {{ __('Attention : Ce statut est utilisé sur :count lead(s) dans votre centre d\'appels.', ['count' => $statusToDelete->leads_count]) }}
                            @else
                                {{ __('Attention : Ce statut est utilisé sur :count lead(s) dans d\'autres centres d\'appels.', ['count' => $globalCount]) }}
                            @endif
                        </p>
                        <p class="mt-1 text-sm text-yellow-800 dark:text-yellow-200">
                            {{ __('Veuillez sélectionner un statut de remplacement. Les leads de votre centre d\'appels seront mis à jour.') }}
                        </p>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Statut de remplacement') }} <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model="replacementStatusId" required>
                            <option value="">{{ __('Sélectionner un statut...') }}</option>
                            @foreach ($availableStatuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="replacementStatusId" />
                        <flux:description>{{ __('Les leads de votre centre d\'appels avec ce statut seront mis à jour avec le statut de remplacement.') }}</flux:description>
                    </flux:field>
                @else
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Ce statut n\'est utilisé sur aucun lead dans votre centre d\'appels. La suppression est définitive.') }}
                        </p>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3">
                    <flux:button type="button" wire:click="closeDeleteModal" variant="ghost">
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button type="submit" variant="danger" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="deleteStatusFromModal">
                            {{ __('Supprimer') }}
                        </span>
                        <span wire:loading wire:target="deleteStatusFromModal">
                            {{ __('Suppression...') }}
                        </span>
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <!-- Modal de suppression en masse avec remplacement -->
    <flux:modal wire:model="showBulkDeleteModal" name="bulk-delete-statuses">
        <form wire:submit.prevent="deleteSelected" class="space-y-6">
<div>
                <h2 class="text-xl font-semibold">{{ __('Supprimer les statuts sélectionnés') }}</h2>
                <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Êtes-vous sûr de vouloir supprimer :count statut(s) ?', ['count' => count($selected)]) }}
                </p>
            </div>

                @php
                    $user = Auth::user();
                    $callCenter = $user->callCenter;
                    
                    // Check leads in this call center
                    $usedStatuses = \App\Models\LeadStatus::whereIn('id', $selected)
                        ->whereHas('leads', function ($q) use ($callCenter) {
                            if ($callCenter) {
                                $q->where('call_center_id', $callCenter->id);
                            }
                        })
                        ->withCount(['leads' => function ($query) use ($callCenter) {
                            if ($callCenter) {
                                $query->where('call_center_id', $callCenter->id);
                            }
                        }])
                        ->get();
                    $totalLeads = $usedStatuses->sum('leads_count');
                    
                    // Also check global usage
                    $globallyUsedStatuses = \App\Models\LeadStatus::whereIn('id', $selected)
                        ->whereHas('leads')
                        ->withCount('leads')
                        ->get();
                    $totalGlobalLeads = $globallyUsedStatuses->sum('leads_count');
                @endphp

                @if ($totalLeads > 0 || $totalGlobalLeads > 0)
                    <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                        <p class="text-sm font-medium text-yellow-900 dark:text-yellow-100">
                            @if ($totalLeads > 0)
                                {{ __('Attention : Certains statuts sont utilisés sur :count lead(s) dans votre centre d\'appels.', ['count' => $totalLeads]) }}
                            @else
                                {{ __('Attention : Certains statuts sont utilisés sur :count lead(s) dans d\'autres centres d\'appels.', ['count' => $totalGlobalLeads]) }}
                            @endif
                        </p>
                        <p class="mt-1 text-sm text-yellow-800 dark:text-yellow-200">
                            {{ __('Veuillez sélectionner un statut de remplacement. Les leads de votre centre d\'appels seront mis à jour.') }}
                        </p>
                    </div>

                <flux:field>
                    <flux:label>{{ __('Statut de remplacement') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="bulkReplacementStatusId" required>
                        <option value="">{{ __('Sélectionner un statut...') }}</option>
                        @foreach ($availableStatuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="bulkReplacementStatusId" />
                        <flux:description>{{ __('Les leads de votre centre d\'appels avec les statuts sélectionnés seront mis à jour avec le statut de remplacement.') }}</flux:description>
                </flux:field>
            @else
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Les statuts sélectionnés ne sont utilisés sur aucun lead dans votre centre d\'appels. La suppression est définitive.') }}
                        </p>
                    </div>
            @endif

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeBulkDeleteModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="danger" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="deleteSelected">
                        {{ __('Supprimer :count statut(s)', ['count' => count($selected)]) }}
                    </span>
                    <span wire:loading wire:target="deleteSelected">
                        {{ __('Suppression...') }}
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
