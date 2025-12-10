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
    public bool $showPreview = false;

    public function store(): void
    {
        $validated = $this->validate((new StoreEmailTemplateRequest)->rules());

        EmailTemplate::create($validated);

        session()->flash('message', __('Template d\'email créé avec succès !'));

        $this->redirect(route('admin.email-templates'), navigate: true);
    }

    public function togglePreview(): void
    {
        $this->showPreview = ! $this->showPreview;
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
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ __('Contenu HTML') }}</h2>
                <flux:button 
                    type="button" 
                    wire:click="togglePreview"
                    variant="ghost"
                    size="sm"
                    icon="eye"
                >
                    {{ $showPreview ? __('Masquer la prévisualisation') : __('Prévisualiser') }}
                </flux:button>
            </div>
            
            <flux:textarea wire:model.live.debounce.500ms="body_html" rows="20" required class="min-h-[500px] w-full font-mono text-sm" />
            
            <div class="mt-3 rounded-lg border-2 border-blue-200 bg-blue-50 p-4 dark:border-blue-700 dark:bg-blue-900/20">
                <div class="mb-3">
                    <strong class="text-base font-bold text-blue-900 dark:text-blue-100">{{ __('Variables disponibles :') }}</strong>
                    <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">{{ __('Vous pouvez utiliser ces variables dans votre template') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 shadow-sm dark:bg-neutral-800">
                        <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Nom') }}:</span>
                        <code class="px-3 py-1.5 bg-blue-500 text-white rounded font-mono text-sm font-bold border-2 border-blue-600 shadow-sm">{{ '{' }}{{ '{' }}name{{ '}' }}{{ '}' }}</code>
                    </div>
                    <div class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 shadow-sm dark:bg-neutral-800">
                        <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Email') }}:</span>
                        <code class="px-3 py-1.5 bg-blue-500 text-white rounded font-mono text-sm font-bold border-2 border-blue-600 shadow-sm">{{ '{' }}{{ '{' }}email{{ '}' }}{{ '}' }}</code>
                    </div>
                    <div class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 shadow-sm dark:bg-neutral-800">
                        <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Lien') }}:</span>
                        <code class="px-3 py-1.5 bg-blue-500 text-white rounded font-mono text-sm font-bold border-2 border-blue-600 shadow-sm">{{ '{' }}{{ '{' }}confirmation_link{{ '}' }}{{ '}' }}</code>
                    </div>
                </div>
            </div>
            
            @if ($showPreview)
                <div class="mt-4 rounded-lg border-2 border-blue-200 bg-white p-4 dark:border-blue-700 dark:bg-neutral-800" wire:key="preview-{{ md5($body_html) }}">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Prévisualisation') }}</h3>
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Aperçu du rendu HTML') }}</span>
                    </div>
                    <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                        <div class="email-preview-content min-h-[200px] max-h-[600px] overflow-y-auto" style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937;">
                            @if(empty(trim($body_html)))
                                <p class="text-neutral-400 italic text-center py-8">{{ __('Aucun contenu à prévisualiser') }}</p>
                            @else
                                {!! $body_html !!}
                            @endif
                        </div>
                    </div>
                </div>
            @endif
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
