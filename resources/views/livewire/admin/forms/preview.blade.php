<?php

use App\Models\Form;
use Livewire\Volt\Component;

new class extends Component {
    public Form $form;

    public function mount(Form $form): void
    {
        $this->form = $form;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumb avec bouton de retour -->
    <div class="flex items-center justify-between">
        <flux:button href="{{ route('admin.forms') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
            {{ __('Retour') }}
        </flux:button>
        <nav class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
            <a href="{{ route('admin.forms') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
                {{ __('Formulaires') }}
            </a>
            <span>/</span>
            <span class="text-neutral-900 dark:text-neutral-100">{{ $form->name }}</span>
            <span>/</span>
            <span class="text-neutral-900 dark:text-neutral-100">{{ __('Prévisualisation') }}</span>
        </nav>
    </div>

    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold">{{ __('Prévisualisation du formulaire') }}</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ $form->name }}</p>
    </div>

    @if ($form->description)
        <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
            <flux:text>{{ $form->description }}</flux:text>
        </div>
    @endif

    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
        <form class="space-y-4">
            @foreach ($form->fields ?? [] as $field)
                <div>
                    @if ($field['type'] === 'text')
                        <flux:input
                            :label="$field['label']"
                            :placeholder="$field['placeholder'] ?? ''"
                            :required="$field['required'] ?? false"
                        />
                    @elseif ($field['type'] === 'email')
                        <flux:input
                            type="email"
                            :label="$field['label']"
                            :placeholder="$field['placeholder'] ?? ''"
                            :required="$field['required'] ?? false"
                        />
                    @elseif ($field['type'] === 'tel')
                        <flux:input
                            type="tel"
                            :label="$field['label']"
                            :placeholder="$field['placeholder'] ?? ''"
                            :required="$field['required'] ?? false"
                        />
                    @elseif ($field['type'] === 'textarea')
                        <flux:textarea
                            :label="$field['label']"
                            :placeholder="$field['placeholder'] ?? ''"
                            :required="$field['required'] ?? false"
                            rows="4"
                        />
                    @elseif ($field['type'] === 'select')
                        <flux:select
                            :label="$field['label']"
                            :required="$field['required'] ?? false"
                        >
                            <option value="">{{ __('Sélectionner...') }}</option>
                            @foreach ($field['options'] ?? [] as $option)
                                <option value="{{ $option['value'] ?? $option }}">{{ $option['label'] ?? $option }}</option>
                            @endforeach
                        </flux:select>
                    @elseif ($field['type'] === 'checkbox')
                        <flux:checkbox
                            :label="$field['label']"
                            :required="$field['required'] ?? false"
                        />
                    @elseif ($field['type'] === 'file')
                        <flux:input
                            type="file"
                            :label="$field['label']"
                            :required="$field['required'] ?? false"
                        />
                    @elseif ($field['type'] === 'number')
                        <flux:input
                            type="number"
                            :label="$field['label']"
                            :placeholder="$field['placeholder'] ?? ''"
                            :required="$field['required'] ?? false"
                        />
                    @elseif ($field['type'] === 'date')
                        <flux:input
                            type="date"
                            :label="$field['label']"
                            :required="$field['required'] ?? false"
                        />
                    @endif
                </div>
            @endforeach

            <div class="pt-4">
                <flux:button type="submit" variant="primary" disabled>
                    {{ __('Soumission désactivée (prévisualisation)') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>

