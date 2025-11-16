<?php

use App\Http\Requests\StoreEmailTemplateRequest;
use App\Models\EmailTemplate;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $subject = '';
    public string $body_html = '';
    public ?string $body_text = null;
    public array $variables = [];

    public function store(): void
    {
        $validated = $this->validate((new StoreEmailTemplateRequest)->rules());

        EmailTemplate::create($validated);

        session()->flash('message', __('Template d\'email créé avec succès !'));

        $this->redirect(route('admin.email-templates'), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumb avec bouton de retour -->
    <div class="flex items-center justify-between">
        <flux:button href="{{ route('admin.email-templates') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
            {{ __('Retour') }}
        </flux:button>
        <nav class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
            <a href="{{ route('admin.email-templates') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
                {{ __('Templates d\'email') }}
            </a>
            <span>/</span>
            <span class="text-neutral-900 dark:text-neutral-100">{{ __('Créer') }}</span>
        </nav>
    </div>

    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold">{{ __('Créer un template d\'email') }}</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Créez un nouveau template d\'email de validation pour vos leads') }}</p>
    </div>

    <!-- Formulaire -->
    <form wire:submit="store" class="space-y-6">
        <!-- Informations générales -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Informations générales') }}</h2>
            <div class="space-y-4">
                <flux:input wire:model.blur="name" :label="__('Nom du template')" required autofocus />
                <flux:input wire:model.blur="subject" :label="__('Sujet de l\'email')" placeholder="Ex: Confirmez votre email - {name}" required />
            </div>
        </div>

        <!-- Contenu HTML -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Contenu HTML') }}</h2>
            <flux:textarea wire:model.blur="body_html" :label="__('Corps HTML')" rows="12" required />
            <div class="mt-3 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                <flux:text class="text-sm text-blue-900 dark:text-blue-100">
                    <strong>{{ __('Variables disponibles :') }}</strong> {{ __('Vous pouvez utiliser {name}, {email}, {confirmation_link}, etc. dans votre template') }}
                </flux:text>
            </div>
        </div>

        <!-- Contenu texte -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Contenu texte (optionnel)') }}</h2>
            <flux:textarea wire:model.blur="body_text" :label="__('Corps texte')" rows="10" />
            <flux:text class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                {{ __('Version texte alternative pour les clients email qui ne supportent pas HTML') }}
            </flux:text>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between border-t border-neutral-200 pt-6 dark:border-neutral-700">
            <flux:button href="{{ route('admin.email-templates') }}" variant="ghost" wire:navigate>
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="store">{{ __('Créer le template') }}</span>
                <span wire:loading wire:target="store">{{ __('Création en cours...') }}</span>
            </flux:button>
        </div>
    </form>
</div>
