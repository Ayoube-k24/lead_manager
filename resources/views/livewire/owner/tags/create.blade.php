<?php

use App\Models\Category;
use App\Models\Tag;
use App\Services\TagService;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $color = '#6B7280';
    public ?string $description = null;
    public ?int $category_id = null;

    public function store(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ], [
            'name.required' => __('Le nom est requis.'),
            'name.unique' => __('Ce nom de tag existe déjà.'),
            'color.required' => __('La couleur est requise.'),
            'color.regex' => __('La couleur doit être au format hexadécimal (#RRGGBB).'),
        ]);

        $service = app(TagService::class);
        $service->createTag(
            $validated['name'],
            $validated['color'],
            $validated['description'],
            $validated['category_id']
        );

        session()->flash('message', __('Tag créé avec succès !'));

        $this->redirect(route('owner.tags'), navigate: true);
    }

    public function with(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumb -->
    <div class="flex items-center justify-between">
        <flux:button href="{{ route('owner.tags') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
            {{ __('Retour') }}
        </flux:button>
        <nav class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
            <a href="{{ route('owner.tags') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
                {{ __('Tags') }}
            </a>
            <span>/</span>
            <span class="text-neutral-900 dark:text-neutral-100">{{ __('Créer') }}</span>
        </nav>
    </div>

    <!-- Messages flash -->
    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold">{{ __('Créer un tag') }}</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Créez un nouveau tag pour organiser vos leads') }}</p>
    </div>

    <!-- Formulaire -->
    <form wire:submit="store" class="flex flex-col gap-6">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Informations du tag') }}</h2>
            
            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Nom') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="name" placeholder="{{ __('Ex: Hot Lead') }}" />
                    <flux:error name="name" />
                    <flux:description>{{ __('Un nom unique pour identifier ce tag') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Couleur') }} <span class="text-red-500">*</span></flux:label>
                    <div class="flex items-center gap-3">
                        <input 
                            type="color" 
                            wire:model="color" 
                            class="h-10 w-20 cursor-pointer rounded border border-neutral-300 dark:border-neutral-600"
                        />
                        <flux:input 
                            wire:model="color" 
                            placeholder="#6B7280"
                            class="flex-1"
                        />
                    </div>
                    <flux:error name="color" />
                    <flux:description>{{ __('Couleur du badge pour ce tag (format hexadécimal)') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea 
                        wire:model="description" 
                        placeholder="{{ __('Description optionnelle du tag...') }}"
                        rows="3"
                    />
                    <flux:error name="description" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Catégorie') }}</flux:label>
                    <flux:select wire:model="category_id">
                        <option value="">{{ __('Aucune catégorie') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="category_id" />
                    <flux:description>{{ __('Grouper ce tag dans une catégorie (optionnel)') }}</flux:description>
                </flux:field>
            </div>
        </div>

        <!-- Aperçu -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Aperçu') }}</h2>
            <div class="flex items-center gap-2">
                <div class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium" style="background-color: {{ $color }}20; color: {{ $color }};">
                    <div class="h-3 w-3 rounded-full" style="background-color: {{ $color }};"></div>
                    <span>{{ $name ?: __('Nom du tag') }}</span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <flux:button href="{{ route('owner.tags') }}" variant="ghost" wire:navigate>
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="store">
                    {{ __('Créer le tag') }}
                </span>
                <span wire:loading wire:target="store" class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('Création...') }}
                </span>
            </flux:button>
        </div>
    </form>
</div>









