<?php

use App\Http\Requests\StoreSmtpProfileRequest;
use App\Http\Requests\UpdateSmtpProfileRequest;
use App\Models\SmtpProfile;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public array $selected = [];

    public function updatedSearch(): void
    {
        $this->reset('selected');
        $this->resetPage();
    }

    public function updatingPage(): void
    {
        $this->reset('selected');
    }

    public function delete(SmtpProfile $smtpProfile): void
    {
        $smtpProfile->delete();
        $this->dispatch('smtp-profile-deleted');
        $this->reset('selected');
    }

    public function deleteSelected(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $count = count($this->selected);
        SmtpProfile::whereIn('id', $this->selected)->delete();
        
        session()->flash('message', __(':count profil(s) SMTP supprimé(s) avec succès.', ['count' => $count]));
        $this->dispatch('smtp-profile-deleted');
        $this->reset('selected');
        $this->resetPage();
    }

    public function selectAll(): void
    {
        // Normaliser les IDs sélectionnés en entiers
        $currentSelected = array_map('intval', $this->selected);
        
        // Obtenir les IDs de la page actuelle depuis la pagination
        $query = SmtpProfile::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('host', 'like', "%{$this->search}%")
                ->orWhere('from_address', 'like', "%{$this->search}%"))
            ->latest();
        
        $paginator = $query->paginate(10);
        $allIds = array_map('intval', $paginator->pluck('id')->toArray());
        
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

    public function toggleActive(SmtpProfile $smtpProfile): void
    {
        $smtpProfile->update(['is_active' => ! $smtpProfile->is_active]);
        $this->dispatch('smtp-profile-updated');
    }

    public function getSelectedCountProperty(): int
    {
        return count($this->selected);
    }

    public function with(): array
    {
        $smtpProfiles = SmtpProfile::query()
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%")
                ->orWhere('host', 'like', "%{$this->search}%")
                ->orWhere('from_address', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);
        
        // Normaliser les IDs sélectionnés en entiers pour la comparaison
        $selectedIds = array_map('intval', $this->selected);
        
        return [
            'smtpProfiles' => $smtpProfiles,
            'selectedCount' => count($this->selected),
            'selected' => $selectedIds,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Messages flash -->
    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    <!-- Header avec actions -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Profils SMTP') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Gérez vos profils SMTP réutilisables pour l\'envoi d\'emails') }}</p>
        </div>
        <flux:button href="{{ route('admin.smtp-profiles.create') }}" variant="primary" icon="plus">
            {{ __('Nouveau profil SMTP') }}
        </flux:button>
    </div>

    <!-- Barre de recherche avec statistiques -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex-1">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                placeholder="{{ __('Rechercher par nom, hôte ou email...') }}" 
                icon="magnifying-glass"
            />
        </div>
        <div class="text-sm text-neutral-600 dark:text-neutral-400">
            <span wire:loading.remove wire:target="search">{{ $smtpProfiles->total() }} {{ __('profil(s)') }}</span>
            <span wire:loading wire:target="search">{{ __('Recherche en cours...') }}</span>
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
                        {{ __('La suppression est irréversible.') }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <flux:button 
                    wire:click="deleteSelected"
                    wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer :count élément(s) ? Cette action est irréversible.', ['count' => $selectedCount]) }}"
                    variant="danger" 
                    size="sm"
                    icon="trash"
                    wire:loading.attr="disabled"
                    wire:target="deleteSelected"
                >
                    <span wire:loading.remove wire:target="deleteSelected">
                        {{ __('Supprimer') }}
                    </span>
                    <span wire:loading wire:target="deleteSelected" class="flex items-center gap-2">
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
                    wire:target="deleteSelected"
                >
                    {{ __('Annuler') }}
                </flux:button>
            </div>
        </div>
    @endif

    <!-- Liste des profils -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900/50">
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                            <flux:checkbox 
                                wire:click="selectAll"
                                :checked="count(array_intersect($selected, array_map('intval', $smtpProfiles->pluck('id')->toArray()))) === $smtpProfiles->count() && $smtpProfiles->count() > 0"
                            />
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Nom') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Configuration') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Expéditeur') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Statut') }}</th>
                        <th class="px-6 py-3 text-right text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse ($smtpProfiles as $profile)
                        <tr class="transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                            <td class="px-6 py-4">
                                <flux:checkbox 
                                    wire:model.live="selected"
                                    :value="(int) $profile->id"
                                    :checked="in_array((int) $profile->id, $selected)"
                                />
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium">{{ $profile->name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                    <div>{{ $profile->host }}:{{ $profile->port }}</div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-500">{{ strtoupper($profile->encryption) }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm">
                                    <div class="font-medium">{{ $profile->from_address }}</div>
                                    @if ($profile->from_name)
                                        <div class="text-xs text-neutral-500 dark:text-neutral-500">{{ $profile->from_name }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <flux:badge :variant="$profile->is_active ? 'success' : 'neutral'">
                                    {{ $profile->is_active ? __('Actif') : __('Inactif') }}
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
                                                href="{{ route('admin.smtp-profiles.edit', $profile) }}" 
                                                icon="pencil"
                                                wire:navigate
                                            >
                                                {{ __('Modifier') }}
                                            </flux:menu.item>
                                            <flux:menu.item 
                                                wire:click="toggleActive({{ $profile->id }})"
                                                :icon="$profile->is_active ? 'eye-slash' : 'eye'"
                                            >
                                                <span wire:loading.remove wire:target="toggleActive({{ $profile->id }})">
                                                    {{ $profile->is_active ? __('Désactiver') : __('Activer') }}
                                                </span>
                                                <span wire:loading wire:target="toggleActive({{ $profile->id }})">
                                                    {{ __('Chargement...') }}
                                                </span>
                                            </flux:menu.item>
                                        </flux:menu.radio.group>

                                        <flux:menu.separator />

                                        <flux:menu.radio.group>
                                            <flux:menu.item 
                                                wire:click="delete({{ $profile->id }})"
                                                wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer ce profil SMTP ? Cette action est irréversible.') }}"
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
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Aucun profil SMTP trouvé') }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                        @if ($search)
                                            {{ __('Essayez de modifier votre recherche') }}
                                        @else
                                            {{ __('Commencez par créer votre premier profil SMTP') }}
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($smtpProfiles->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                {{ $smtpProfiles->links() }}
            </div>
        @endif
    </div>
</div>
