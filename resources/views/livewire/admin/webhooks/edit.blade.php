<?php

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Webhook;
use Livewire\Volt\Component;

new class extends Component {
    public Webhook $webhook;
    public string $name = '';
    public string $url = '';
    public array $events = [];
    public ?int $form_id = null;
    public ?int $call_center_id = null;
    public bool $is_active = true;

    public function mount(Webhook $webhook): void
    {
        $this->webhook = $webhook;
        $this->name = $webhook->name;
        $this->url = $webhook->url;
        $this->events = $webhook->events ?? [];
        $this->form_id = $webhook->form_id;
        $this->call_center_id = $webhook->call_center_id;
        $this->is_active = $webhook->is_active;
    }

    public function getAvailableEvents(): array
    {
        return [
            'lead.created' => __('Lead créé'),
            'lead.email_confirmed' => __('Email confirmé'),
            'lead.assigned' => __('Lead assigné'),
            'lead.status_updated' => __('Statut mis à jour'),
            'lead.converted' => __('Lead converti'),
        ];
    }

    public function toggleEvent(string $event): void
    {
        if (in_array($event, $this->events)) {
            $this->events = array_values(array_diff($this->events, [$event]));
        } else {
            $this->events[] = $event;
        }
    }

    public function update(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'form_id' => ['nullable', 'exists:forms,id'],
            'call_center_id' => ['nullable', 'exists:call_centers,id'],
            'is_active' => ['boolean'],
        ], [
            'name.required' => __('Le nom est requis.'),
            'url.required' => __('L\'URL est requise.'),
            'url.url' => __('L\'URL doit être valide.'),
            'events.required' => __('Au moins un événement doit être sélectionné.'),
            'events.min' => __('Au moins un événement doit être sélectionné.'),
        ]);

        $this->webhook->update($validated);

        session()->flash('message', __('Webhook mis à jour avec succès !'));

        $this->redirect(route('admin.webhooks'), navigate: true);
    }

    public function with(): array
    {
        return [
            'forms' => Form::where('is_active', true)->orderBy('name')->get(),
            'callCenters' => CallCenter::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumb avec bouton de retour -->
    <div class="flex items-center justify-between">
        <flux:button href="{{ route('admin.webhooks') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
            {{ __('Retour') }}
        </flux:button>
        <nav class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
            <a href="{{ route('admin.webhooks') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
                {{ __('Webhooks') }}
            </a>
            <span>/</span>
            <span class="text-neutral-900 dark:text-neutral-100">{{ __('Modifier') }}</span>
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
        <h1 class="text-2xl font-bold">{{ __('Modifier le webhook') }}</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Mettez à jour la configuration de ce webhook') }}</p>
    </div>

    <!-- Guide rapide -->
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
        <p class="text-sm text-blue-900 dark:text-blue-100">
            💡 {{ __('Un guide complet est disponible dans le fichier GUIDE_WEBHOOKS.md à la racine du projet. Il contient des exemples de code, la résolution des problèmes et les bonnes pratiques.') }}
        </p>
    </div>

    <!-- Formulaire -->
    <form wire:submit="update" class="flex flex-col gap-6">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Informations générales') }}</h2>
            
            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Nom') }}</flux:label>
                    <flux:input wire:model="name" placeholder="{{ __('Ex: Webhook CRM') }}" />
                    <flux:error name="name" />
                    <flux:description>{{ __('Un nom descriptif pour identifier ce webhook') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('URL de destination') }}</flux:label>
                    <flux:input wire:model="url" type="url" placeholder="https://example.com/webhook" />
                    <flux:error name="url" />
                    <flux:description>{{ __('L\'URL qui recevra les notifications') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Secret') }}</flux:label>
                    <div class="flex items-center gap-2">
                        <flux:input value="{{ $webhook->secret }}" readonly class="font-mono text-sm" />
                        <flux:button 
                            type="button"
                            variant="ghost" 
                            size="sm"
                            onclick="copySecret('{{ $webhook->secret }}')"
                        >
                            {{ __('Copier') }}
                        </flux:button>
                    </div>
                    <flux:description>{{ __('Ce secret est utilisé pour signer les payloads. Ne le partagez pas.') }}</flux:description>
                </flux:field>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Événements') }}</h2>
            <flux:error name="events" />
            <flux:description class="mb-4">{{ __('Sélectionnez les événements qui déclencheront ce webhook') }}</flux:description>
            
            <div class="space-y-2">
                @foreach($this->getAvailableEvents() as $event => $label)
                    <flux:checkbox 
                        wire:click="toggleEvent('{{ $event }}')"
                        :checked="in_array('{{ $event }}', $events)"
                        label="{{ $label }}"
                    />
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Association (optionnel)') }}</h2>
            <flux:description class="mb-4">{{ __('Limitez ce webhook à un formulaire ou un centre d\'appels spécifique. Laissez vide pour recevoir tous les événements.') }}</flux:description>
            
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Formulaire') }}</flux:label>
                    <flux:select wire:model="form_id">
                        <option value="">{{ __('Tous les formulaires') }}</option>
                        @foreach($forms as $form)
                            <option value="{{ $form->id }}">{{ $form->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Centre d\'appels') }}</flux:label>
                    <flux:select wire:model="call_center_id">
                        <option value="">{{ __('Tous les centres') }}</option>
                        @foreach($callCenters as $callCenter)
                            <option value="{{ $callCenter->id }}">{{ $callCenter->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="call_center_id" />
                </flux:field>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Statut') }}</h2>
            
            <flux:field>
                <flux:checkbox wire:model="is_active" label="{{ __('Activer ce webhook') }}" />
                <flux:description>{{ __('Les webhooks inactifs ne seront pas déclenchés') }}</flux:description>
            </flux:field>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <flux:button href="{{ route('admin.webhooks') }}" variant="ghost" wire:navigate>
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="update">
                    {{ __('Mettre à jour') }}
                </span>
                <span wire:loading wire:target="update" class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('Mise à jour...') }}
                </span>
            </flux:button>
        </div>
    </form>
</div>

<script>
function copySecret(secret) {
    navigator.clipboard.writeText(secret).then(() => {
        showCopyNotification('Secret copié dans le presse-papiers !');
    }).catch(() => {
        showCopyNotification('Erreur lors de la copie', 'error');
    });
}

function showCopyNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 rounded-lg px-4 py-3 shadow-lg transition-all duration-300 ${
        type === 'success' 
            ? 'bg-green-500 text-white' 
            : 'bg-red-500 text-white'
    }`;
    notification.style.opacity = '0';
    notification.style.transform = 'translateY(-20px)';
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateY(0)';
    }, 10);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}
</script>
