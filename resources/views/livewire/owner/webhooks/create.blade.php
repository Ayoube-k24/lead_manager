<?php

use App\Models\Form;
use App\Models\Webhook;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $url = '';
    public array $events = [];
    public ?int $form_id = null;
    public bool $is_active = true;
    public bool $showGuide = false;

    public function mount(): void
    {
        // Ensure user is a call center owner
        $user = Auth::user();
        if (! $user->isCallCenterOwner()) {
            abort(403, __('Accès refusé'));
        }

        // Initialize with empty events
        $this->events = [];
    }

    public function toggleGuide(): void
    {
        $this->showGuide = ! $this->showGuide;
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

    public function store(): void
    {
        $user = Auth::user();
        
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'form_id' => ['nullable', 'exists:forms,id'],
            'is_active' => ['boolean'],
        ], [
            'name.required' => __('Le nom est requis.'),
            'url.required' => __('L\'URL est requise.'),
            'url.url' => __('L\'URL doit être valide.'),
            'events.required' => __('Au moins un événement doit être sélectionné.'),
            'events.min' => __('Au moins un événement doit être sélectionné.'),
        ]);

        $validated['user_id'] = $user->id;
        $validated['call_center_id'] = $user->call_center_id;
        $validated['secret'] = Webhook::generateSecret();

        Webhook::create($validated);

        session()->flash('message', __('Webhook créé avec succès !'));

        $this->redirect(route('owner.webhooks'), navigate: true);
    }

    public function with(): array
    {
        $user = Auth::user();
        
        return [
            'forms' => Form::where('call_center_id', $user->call_center_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumb avec bouton de retour -->
    <div class="flex items-center justify-between">
        <flux:button href="{{ route('owner.webhooks') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
            {{ __('Retour') }}
        </flux:button>
        <nav class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
            <a href="{{ route('owner.webhooks') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
                {{ __('Webhooks') }}
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
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Créer un webhook') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Configurez un webhook pour recevoir des notifications en temps réel') }}</p>
        </div>
        <flux:button wire:click="toggleGuide" variant="ghost" icon="question-mark-circle">
            {{ $showGuide ? __('Masquer le guide') : __('Afficher le guide') }}
        </flux:button>
    </div>

    <!-- Guide complet -->
    @if ($showGuide)
        <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="border-b border-neutral-200 p-6 dark:border-neutral-700">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Guide complet des webhooks') }}</h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Tout ce que vous devez savoir pour créer et utiliser les webhooks') }}</p>
            </div>

            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                <!-- Qu'est-ce qu'un webhook -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        🔔 {{ __('Qu\'est-ce qu\'un webhook ?') }}
                    </h3>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-3">
                        {{ __('Un webhook est un mécanisme qui permet à Lead Manager d\'envoyer automatiquement des notifications HTTP à votre application externe lorsqu\'un événement se produit (ex: création d\'un lead, confirmation d\'email, etc.).') }}
                    </p>
                    <div class="space-y-2 text-sm text-neutral-600 dark:text-neutral-400">
                        <div class="flex items-start gap-2">
                            <span class="text-green-500">✅</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Temps réel') }}</strong> - {{ __('Recevez les notifications instantanément') }}
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-green-500">✅</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Automatisation') }}</strong> - {{ __('Intégrez Lead Manager avec votre CRM, système de facturation, etc.') }}
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-green-500">✅</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Fiabilité') }}</strong> - {{ __('Système de retry automatique en cas d\'échec') }}
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-green-500">✅</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Sécurité') }}</strong> - {{ __('Signature cryptographique pour vérifier l\'authenticité') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Événements disponibles -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        📨 {{ __('Événements disponibles') }}
                    </h3>
                    <div class="space-y-3">
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('lead.created') }} - {{ __('Lead créé') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Déclenché quand un nouveau lead est créé dans le système.') }}</p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('lead.email_confirmed') }} - {{ __('Email confirmé') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Déclenché quand un lead confirme son adresse email via le lien de confirmation.') }}</p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('lead.assigned') }} - {{ __('Lead assigné') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Déclenché quand un lead est assigné à un agent.') }}</p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('lead.status_updated') }} - {{ __('Statut mis à jour') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Déclenché quand le statut d\'un lead change.') }}</p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('lead.converted') }} - {{ __('Lead converti') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Déclenché quand un lead est marqué comme converti.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Format des données -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        📦 {{ __('Format des données') }}
                    </h3>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-3">
                        {{ __('Toutes les requêtes webhook sont envoyées en POST avec un payload JSON signé :') }}
                    </p>
                    <pre class="overflow-x-auto rounded-lg bg-neutral-900 p-4 text-xs text-neutral-100 dark:bg-neutral-950"><code>{
  "payload": {
    "event": "lead.created",
    "lead_id": 123,
    "lead_email": "contact@example.com",
    "lead_status": "pending_email",
    "lead_data": {
      "name": "Jean Dupont",
      "phone": "+33 6 12 34 56 78"
    },
    "form_id": 5,
    "call_center_id": 1,
    "created_at": "2025-12-04T10:30:00+00:00"
  },
  "timestamp": 1701682200,
  "signature": "abc123def456..."
}</code></pre>
                </div>

                <!-- Sécurité -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        🔐 {{ __('Sécurité et signature') }}
                    </h3>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-3">
                        {{ __('Chaque requête webhook est signée avec un secret unique. Vous devez vérifier la signature pour garantir l\'authenticité de la requête.') }}
                    </p>
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                        <div class="font-semibold text-blue-900 dark:text-blue-100 mb-2">{{ __('Algorithme de signature') }}</div>
                        <p class="text-sm text-blue-800 dark:text-blue-200">{{ __('La signature est calculée avec HMAC-SHA256 : signature = HMAC-SHA256(json_encode(payload) + timestamp, secret)') }}</p>
                    </div>
                    <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Le secret est affiché dans la page d\'édition du webhook. Gardez ce secret confidentiel et ne le partagez jamais publiquement.') }}
                    </p>
                </div>

                <!-- Résolution des problèmes -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        🔧 {{ __('Résolution des problèmes') }}
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-start gap-3">
                            <span class="text-red-500 dark:text-red-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Erreur 404 ou URL inaccessible') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('Vérifiez que l\'URL est correcte et accessible publiquement, testez avec curl, et vérifiez les pare-feu et restrictions réseau.') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-yellow-500 dark:text-yellow-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Timeout (délai d\'attente)') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('Optimisez votre endpoint pour répondre rapidement (< 1 seconde). Répondez immédiatement avec un code 200, puis traitez les données de manière asynchrone.') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-red-500 dark:text-red-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Erreur 401 (Signature invalide)') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('Vérifiez que vous utilisez le bon secret, que vous calculez la signature correctement avec HMAC-SHA256, et que vous utilisez json_encode(payload) + timestamp dans cet ordre.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Formulaire -->
    <form wire:submit="store" class="flex flex-col gap-6">
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
            <flux:description class="mb-4">{{ __('Limitez ce webhook à un formulaire spécifique. Laissez vide pour recevoir tous les événements de votre centre d\'appels.') }}</flux:description>
            
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
            <flux:button href="{{ route('owner.webhooks') }}" variant="ghost" wire:navigate>
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="store">
                    {{ __('Créer le webhook') }}
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

