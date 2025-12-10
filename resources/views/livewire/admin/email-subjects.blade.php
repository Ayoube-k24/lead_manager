<?php

use App\Models\EmailSubject;
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

    public function delete(EmailSubject $emailSubject): void
    {
        $emailSubject->delete();
        $this->dispatch('email-subject-deleted');
        $this->reset('selected');
    }

    public function deleteSelected(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $count = count($this->selected);
        EmailSubject::whereIn('id', $this->selected)->delete();
        
        session()->flash('message', __(':count sujet(s) supprimé(s) avec succès.', ['count' => $count]));
        $this->dispatch('email-subject-deleted');
        $this->reset('selected');
        $this->resetPage();
    }

    public function selectAll(): void
    {
        $currentSelected = array_map('intval', $this->selected);
        
        $query = EmailSubject::query()
            ->when($this->search, fn ($q) => $q->where('subject', 'like', "%{$this->search}%"))
            ->ordered();
        
        $paginator = $query->paginate(10);
        $allIds = array_map('intval', $paginator->pluck('id')->toArray());
        
        $allSelected = count($allIds) > 0 && count(array_intersect($currentSelected, $allIds)) === count($allIds);
        
        if ($allSelected) {
            $this->selected = array_values(array_diff($currentSelected, $allIds));
        } else {
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

    public function with(): array
    {
        $emailSubjects = EmailSubject::query()
            ->when($this->search, fn ($query) => $query->where('subject', 'like', "%{$this->search}%"))
            ->ordered()
            ->paginate(10);
        
        $selectedIds = array_map('intval', $this->selected);
        
        return [
            'emailSubjects' => $emailSubjects,
            'selectedCount' => count($this->selected),
            'selected' => $selectedIds,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Sujets d\'email') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Gérez les sujets d\'email prédéfinis pour les agents') }}</p>
        </div>
        <flux:button href="{{ route('admin.email-subjects.create') }}" variant="primary" icon="plus">
            {{ __('Nouveau sujet') }}
        </flux:button>
    </div>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex-1">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                placeholder="{{ __('Rechercher par sujet...') }}" 
                icon="magnifying-glass"
            />
        </div>
        <div class="text-sm text-neutral-600 dark:text-neutral-400">
            <span wire:loading.remove wire:target="search">{{ $emailSubjects->total() }} {{ __('sujet(s)') }}</span>
            <span wire:loading wire:target="search">{{ __('Recherche en cours...') }}</span>
        </div>
    </div>

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

    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900/50">
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                            <flux:checkbox 
                                wire:click="selectAll"
                                :checked="count(array_intersect($selected, array_map('intval', $emailSubjects->pluck('id')->toArray()))) === $emailSubjects->count() && $emailSubjects->count() > 0"
                            />
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Sujet') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Template par défaut') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Statut') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Ordre') }}</th>
                        <th class="px-6 py-3 text-right text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse ($emailSubjects as $subject)
                        <tr class="transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                            <td class="px-6 py-4">
                                <flux:checkbox 
                                    wire:model.live="selected"
                                    :value="(int) $subject->id"
                                    :checked="in_array((int) $subject->id, $selected)"
                                />
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium">{{ $subject->subject }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if ($subject->default_template_html)
                                    <flux:badge variant="success" size="sm">{{ __('Oui') }}</flux:badge>
                                @else
                                    <flux:badge variant="neutral" size="sm">{{ __('Non') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if ($subject->is_active)
                                    <flux:badge variant="success" size="sm">{{ __('Actif') }}</flux:badge>
                                @else
                                    <flux:badge variant="danger" size="sm">{{ __('Inactif') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                    {{ $subject->order }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical">
                                        {{ __('Actions') }}
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.radio.group>
                                            <flux:menu.item 
                                                href="{{ route('admin.email-subjects.edit', $subject) }}" 
                                                icon="pencil"
                                                wire:navigate
                                            >
                                                {{ __('Modifier') }}
                                            </flux:menu.item>
                                        </flux:menu.radio.group>

                                        <flux:menu.separator />

                                        <flux:menu.radio.group>
                                            <flux:menu.item 
                                                wire:click="delete({{ $subject->id }})"
                                                wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer ce sujet ? Cette action est irréversible.') }}"
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
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Aucun sujet trouvé') }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                        @if ($search)
                                            {{ __('Essayez de modifier votre recherche') }}
                                        @else
                                            {{ __('Commencez par créer votre premier sujet d\'email') }}
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($emailSubjects->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700" wire:key="pagination-email-subjects">
                {{ $emailSubjects->links() }}
            </div>
        @endif
    </div>
</div>
