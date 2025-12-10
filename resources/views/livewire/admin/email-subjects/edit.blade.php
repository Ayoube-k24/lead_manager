<?php

use App\Http\Requests\UpdateEmailSubjectRequest;
use App\Models\EmailSubject;
use Livewire\Volt\Component;

new class extends Component {
    public EmailSubject $emailSubject;

    public string $subject = '';
    public ?string $default_template_html = null;
    public bool $is_active = true;
    public int $order = 0;
    public bool $showPreview = false;

    public function mount(EmailSubject $emailSubject): void
    {
        $this->emailSubject = $emailSubject;
        $this->subject = $emailSubject->subject;
        $this->default_template_html = $emailSubject->default_template_html;
        $this->is_active = $emailSubject->is_active;
        $this->order = $emailSubject->order;
    }

    public function update(): void
    {
        $validated = $this->validate((new UpdateEmailSubjectRequest)->rules());

        $this->emailSubject->update($validated);

        session()->flash('message', __('Sujet d\'email modifié avec succès !'));

        $this->redirect(route('admin.email-subjects'), navigate: true);
    }

    public function togglePreview(): void
    {
        $this->showPreview = ! $this->showPreview;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:button href="{{ route('admin.email-subjects') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
            {{ __('Retour') }}
        </flux:button>
        <nav class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
            <a href="{{ route('admin.email-subjects') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
                {{ __('Sujets d\'email') }}
            </a>
            <span>/</span>
            <span class="text-neutral-900 dark:text-neutral-100">{{ __('Modifier') }}</span>
        </nav>
    </div>

    <div>
        <h1 class="text-2xl font-bold">{{ __('Modifier le sujet d\'email') }}</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Modifiez les informations du sujet d\'email') }}</p>
    </div>

    <form wire:submit="update" class="space-y-6">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Informations générales') }}</h2>
            <div class="space-y-4">
                <flux:input wire:model.blur="subject" :label="__('Sujet de l\'email')" placeholder="Ex: Devis mutuelle santé" required autofocus />
                <flux:field>
                    <div class="mb-2 flex items-center justify-between">
                        <flux:label>{{ __('Template HTML par défaut (optionnel)') }}</flux:label>
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
                    
                    <flux:textarea wire:model.live.debounce.500ms="default_template_html" rows="20" placeholder="{{ __('Contenu HTML par défaut que l\'agent pourra modifier...') }}" class="min-h-[500px] w-full font-mono text-sm" />
                    
                    <flux:description>{{ __('Ce template sera pré-rempli lorsque l\'agent sélectionnera ce sujet') }}</flux:description>
                    
                    @if ($showPreview)
                        <div class="mt-4 rounded-lg border-2 border-blue-200 bg-white p-4 dark:border-blue-700 dark:bg-neutral-800" wire:key="preview-{{ md5($default_template_html) }}">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Prévisualisation') }}</h3>
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Aperçu du rendu HTML') }}</span>
                            </div>
                            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                                <div class="email-preview-content min-h-[200px] max-h-[600px] overflow-y-auto" style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937;">
                                    @if(empty(trim($default_template_html)))
                                        <p class="text-neutral-400 italic text-center py-8">{{ __('Aucun contenu à prévisualiser') }}</p>
                                    @else
                                        {!! $default_template_html !!}
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </flux:field>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Ordre d\'affichage') }}</flux:label>
                        <flux:input wire:model.blur="order" type="number" min="0" />
                        <flux:description>{{ __('Détermine l\'ordre d\'affichage dans la liste (plus petit = en premier)') }}</flux:description>
                    </flux:field>
                    <flux:field>
                        <flux:checkbox wire:model.blur="is_active" :label="__('Actif')" />
                        <flux:description>{{ __('Les sujets inactifs ne seront pas visibles par les agents') }}</flux:description>
                    </flux:field>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-neutral-200 pt-6 dark:border-neutral-700">
            <flux:button href="{{ route('admin.email-subjects') }}" variant="ghost" wire:navigate>
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="update">{{ __('Enregistrer les modifications') }}</span>
                <span wire:loading wire:target="update">{{ __('Enregistrement en cours...') }}</span>
            </flux:button>
        </div>
    </form>
</div>

