<?php

use App\Models\Category;
use App\Models\Tag;
use App\Services\TagService;
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
                if ($tag) {
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
        $tags = Tag::query()
            ->with(['category', 'leads'])
            ->withCount('leads')
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"))
            ->when($this->filterCategoryId, fn ($query) => $query->where('category_id', $this->filterCategoryId))
            ->orderBy('name')
            ->paginate(15);

        return [
            'tags' => $tags,
            'categories' => Category::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Tags') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Gérez les tags pour organiser vos leads') }}</p>
        </div>
        <flux:button href="{{ route('admin.tags.create') }}" variant="primary" icon="plus" wire:navigate>
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

    <!-- Actions en masse -->
    @if (count($selected) > 0)
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-blue-900 dark:text-blue-100">
                    {{ __(':count tag(s) sélectionné(s)', ['count' => count($selected)]) }}
                </span>
                <flux:button 
                    wire:click="deleteSelected" 
                    wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer les tags sélectionnés ?') }}"
                    variant="danger" 
                    size="sm"
                >
                    {{ __('Supprimer la sélection') }}
                </flux:button>
            </div>
        </div>
    @endif

    <!-- Liste des tags -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        @if ($tags->count() > 0)
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($tags as $tag)
                    <div class="flex items-center gap-4 p-4 hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                        <flux:checkbox 
                            wire:model="selected" 
                            value="{{ $tag->id }}"
                        />

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
                                href="{{ route('admin.tags.edit', $tag) }}" 
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
                <flux:button href="{{ route('admin.tags.create') }}" variant="primary" class="mt-4" wire:navigate>
                    {{ __('Créer le premier tag') }}
                </flux:button>
            </div>
        @endif
    </div>
</div>

