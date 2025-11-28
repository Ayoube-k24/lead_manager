<?php

use App\Models\Category;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public ?int $filterCategoryId = null;
    public array $selected = [];

    public function updatedSearch(): void
    {
        $this->reset('selected');
        $this->resetPage();
    }

    public function updatedFilterCategoryId(): void
    {
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
        $query = Tag::query()
            ->with(['category'])
            ->withCount(['leads' => function ($query) use ($callCenter) {
                if ($callCenter) {
                    $query->where('call_center_id', $callCenter->id);
                }
            }])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"))
            ->when($this->filterCategoryId, fn ($q) => $q->where('category_id', $this->filterCategoryId))
            ->when($callCenter, function ($query) use ($callCenter) {
                $query->whereHas('leads', function ($q) use ($callCenter) {
                    $q->where('call_center_id', $callCenter->id);
                });
            })
            ->orderBy('name');
        
        $paginator = $query->paginate(15);
        
        // Filter to only non-system tags from current page
        $allIds = [];
        foreach ($paginator->items() as $tag) {
            if (! $tag->is_system) {
                $allIds[] = (int) $tag->id;
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

    public function delete(Tag $tag): void
    {
        try {
            $service = app(TagService::class);
            $service->deleteTag($tag);
            session()->flash('message', __('Tag supprimé avec succès.'));
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
        $this->reset('selected');
    }

    public function deleteSelected(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $service = app(TagService::class);
        $count = 0;
        $errors = 0;

        foreach ($this->selected as $tagId) {
            try {
                $tag = Tag::find($tagId);
                if ($tag && ! $tag->is_system) {
                    $service->deleteTag($tag);
                    $count++;
                }
            } catch (\Exception $e) {
                $errors++;
            }
        }

        if ($count > 0) {
            session()->flash('message', __(':count tag(s) supprimé(s) avec succès.', ['count' => $count]));
        }
        if ($errors > 0) {
            session()->flash('error', __(':count tag(s) n\'ont pas pu être supprimé(s).', ['count' => $errors]));
        }

        $this->reset('selected');
        $this->resetPage();
    }

    public function with(): array
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        // Get tags used in this call center
        $tags = Tag::query()
            ->with(['category'])
            ->withCount(['leads' => function ($query) use ($callCenter) {
                if ($callCenter) {
                    $query->where('call_center_id', $callCenter->id);
                }
            }])
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"))
            ->when($this->filterCategoryId, fn ($query) => $query->where('category_id', $this->filterCategoryId))
            ->when($callCenter, function ($query) use ($callCenter) {
                $query->whereHas('leads', function ($q) use ($callCenter) {
                    $q->where('call_center_id', $callCenter->id);
                });
            })
            ->orderBy('name')
            ->paginate(15);

        // Normaliser les IDs sélectionnés en entiers pour la comparaison
        $selectedIds = array_map('intval', $this->selected);

        return [
            'tags' => $tags,
            'categories' => Category::orderBy('name')->get(),
            'selectedCount' => count($this->selected),
            'selected' => $selectedIds,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Tags') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Gérez les tags utilisés dans votre centre d\'appels') }}</p>
        </div>
        <flux:button href="{{ route('owner.tags.create') }}" variant="primary" icon="plus" wire:navigate>
            {{ __('Nouveau tag') }}
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
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Rechercher') }}</flux:label>
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="{{ __('Rechercher par nom ou description...') }}"
                    icon="magnifying-glass"
                />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Catégorie') }}</flux:label>
                <flux:select wire:model.live="filterCategoryId">
                    <option value="">{{ __('Toutes les catégories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </flux:select>
            </flux:field>
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

    <!-- Liste des tags -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        @if ($tags->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                                @php
                                    $selectableIds = [];
                                    foreach ($tags as $tag) {
                                        if (!$tag->is_system) {
                                            $selectableIds[] = (int) $tag->id;
                                        }
                                    }
                                    $allSelected = count($selectableIds) > 0 && count(array_intersect($selected, $selectableIds)) === count($selectableIds) && count($selectableIds) > 0;
                                @endphp
                                <flux:checkbox 
                                    wire:click="selectAll"
                                    :checked="$allSelected"
                                />
                            </th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Tag') }}</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Catégorie') }}</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Description') }}</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Utilisation') }}</th>
                            <th class="px-6 py-3 text-right text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($tags as $tag)
                            <tr class="transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                                <td class="px-6 py-4">
                                    <flux:checkbox 
                                        wire:model.live="selected"
                                        :value="(int) $tag->id"
                                        :checked="in_array((int) $tag->id, $selected)"
                                        :disabled="$tag->is_system"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full" style="background-color: {{ $tag->color }}20;">
                                            <div class="h-3 w-3 rounded-full" style="background-color: {{ $tag->color }};"></div>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-neutral-900 dark:text-neutral-100">
                                                    {{ $tag->name }}
                                                </span>
                                                @if ($tag->is_system)
                                                    <flux:badge variant="primary" size="sm">{{ __('Système') }}</flux:badge>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($tag->category)
                                        <flux:badge variant="ghost" size="sm">{{ $tag->category->name }}</flux:badge>
                                    @else
                                        <span class="text-sm text-neutral-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $tag->description ? Str::limit($tag->description, 50) : '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ __(':count lead(s)', ['count' => $tag->leads_count]) }}
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
                                                    href="{{ route('owner.tags.edit', $tag) }}" 
                                                    icon="pencil"
                                                    wire:navigate
                                                >
                                                    {{ __('Modifier') }}
                                                </flux:menu.item>
                                            </flux:menu.radio.group>
                                            @if (! $tag->is_system)
                                                <flux:menu.separator />
                                                <flux:menu.radio.group>
                                                    <flux:menu.item 
                                                        wire:click="delete({{ $tag->id }})"
                                                        wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer ce tag ?') }}"
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
                {{ $tags->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <p class="text-neutral-500 dark:text-neutral-400">{{ __('Aucun tag trouvé') }}</p>
                <flux:button href="{{ route('owner.tags.create') }}" variant="primary" class="mt-4" wire:navigate>
                    {{ __('Créer le premier tag') }}
                </flux:button>
            </div>
        @endif
    </div>

                        <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: {{ $tag->color }}20;">
                            <div class="h-4 w-4 rounded-full" style="background-color: {{ $tag->color }};"></div>
                        </div>

                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-neutral-900 dark:text-neutral-100">
                                    {{ $tag->name }}
                                </h3>
                                @if ($tag->is_system)
                                    <flux:badge variant="primary" size="sm">{{ __('Système') }}</flux:badge>
                                @endif
                                @if ($tag->category)
                                    <flux:badge variant="ghost" size="sm">{{ $tag->category->name }}</flux:badge>
                                @endif
                            </div>
                            @if ($tag->description)
                                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                    {{ $tag->description }}
                                </p>
                            @endif
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-500">
                                {{ __('Utilisé sur :count lead(s)', ['count' => $tag->leads_count]) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <flux:button 
                                href="{{ route('owner.tags.edit', $tag) }}" 
                                variant="ghost" 
                                size="sm"
                                wire:navigate
                            >
                                {{ __('Modifier') }}
                            </flux:button>
                            @if (! $tag->is_system)
                                <flux:button 
                                    wire:click="delete({{ $tag->id }})" 
                                    wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer ce tag ?') }}"
                                    variant="danger" 
                                    size="sm"
                                >
                                    {{ __('Supprimer') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                {{ $tags->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <p class="text-neutral-500 dark:text-neutral-400">{{ __('Aucun tag trouvé') }}</p>
                <flux:button href="{{ route('owner.tags.create') }}" variant="primary" class="mt-4" wire:navigate>
                    {{ __('Créer le premier tag') }}
                </flux:button>
            </div>
        @endif
    </div>
</div>

