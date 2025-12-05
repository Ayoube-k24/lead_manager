<?php

use App\Models\Webhook;
use App\Services\WebhookService;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public ?string $filterStatus = null;
    public ?int $filterFormId = null;
    public ?int $filterCallCenterId = null;
    public array $selected = [];
    public ?int $testingWebhookId = null;
    public ?array $testResult = null;

    public function updatedSearch(): void
    {
        $this->reset('selected');
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterFormId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCallCenterId(): void
    {
        $this->resetPage();
    }

    public function delete(Webhook $webhook): void
    {
        $webhook->delete();
        session()->flash('message', __('Webhook supprimé avec succès.'));
        $this->reset('selected');
    }

    public function deleteSelected(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $count = count($this->selected);
        Webhook::whereIn('id', $this->selected)->delete();
        
        session()->flash('message', __(':count webhook(s) supprimé(s) avec succès.', ['count' => $count]));
        $this->reset('selected');
        $this->resetPage();
    }

    public function toggleActive(Webhook $webhook): void
    {
        $webhook->update(['is_active' => ! $webhook->is_active]);
        session()->flash('message', __('Webhook :action avec succès.', [
            'action' => $webhook->is_active ? __('activé') : __('désactivé')
        ]));
    }

    public function testWebhook(Webhook $webhook): void
    {
        $this->testingWebhookId = $webhook->id;
        $this->testResult = null;

        try {
            $service = app(WebhookService::class);
            $this->testResult = $service->testWebhook($webhook);
        } catch (\Exception $e) {
            $this->testResult = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } finally {
            $this->testingWebhookId = null;
        }
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

    public function with(): array
    {
        $webhooks = Webhook::query()
            ->with(['form', 'callCenter', 'user'])
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%")
                ->orWhere('url', 'like', "%{$this->search}%"))
            ->when($this->filterStatus !== null, fn ($query) => $query->where('is_active', $this->filterStatus === 'active'))
            ->when($this->filterFormId, fn ($query) => $query->where('form_id', $this->filterFormId))
            ->when($this->filterCallCenterId, fn ($query) => $query->where('call_center_id', $this->filterCallCenterId))
            ->latest()
            ->paginate(10);
        
        return [
            'webhooks' => $webhooks,
            'selectedCount' => count($this->selected),
            'forms' => \App\Models\Form::orderBy('name')->get(),
            'callCenters' => \App\Models\CallCenter::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Messages flash -->
    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    <!-- Header avec actions -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Webhooks') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Gérez vos webhooks pour intégrer la plateforme avec des systèmes externes') }}</p>
        </div>
        <flux:button href="{{ route('admin.webhooks.create') }}" variant="primary" icon="plus">
            {{ __('Nouveau webhook') }}
        </flux:button>
    </div>
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

                <!-- Créer un webhook -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        🚀 {{ __('Comment créer un webhook') }}
                    </h3>
                    <ol class="list-decimal list-inside space-y-3 text-sm text-neutral-600 dark:text-neutral-400">
                        <li>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Accéder à la page de création') }}</strong>
                            <p class="mt-1 ml-6">{{ __('Allez dans Admin → Webhooks → Créer un webhook') }}</p>
                        </li>
                        <li>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Remplir les informations') }}</strong>
                            <p class="mt-1 ml-6">{{ __('Donnez un nom descriptif et entrez l\'URL HTTPS qui recevra les notifications') }}</p>
                        </li>
                        <li>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Sélectionner les événements') }}</strong>
                            <p class="mt-1 ml-6">{{ __('Cochez les événements pour lesquels vous souhaitez recevoir des notifications (lead.created, lead.email_confirmed, etc.)') }}</p>
                        </li>
                        <li>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Association (optionnel)') }}</strong>
                            <p class="mt-1 ml-6">{{ __('Spécifiez un formulaire ou un centre d\'appels pour filtrer les événements, ou laissez vide pour tous les événements') }}</p>
                        </li>
                        <li>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Activer et créer') }}</strong>
                            <p class="mt-1 ml-6">{{ __('Activez le webhook et cliquez sur Créer. Un secret sera automatiquement généré pour sécuriser les requêtes.') }}</p>
                        </li>
                    </ol>
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

                <!-- Tester un webhook -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        🧪 {{ __('Tester un webhook') }}
                    </h3>
                    <div class="space-y-3 text-sm text-neutral-600 dark:text-neutral-400">
                        <div>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Méthode 1 : Utiliser un service de test') }}</strong>
                            <p class="mt-1">{{ __('Allez sur webhook.site ou RequestBin, copiez l\'URL unique générée, créez un webhook avec cette URL, puis déclenchez un événement pour voir la requête reçue.') }}</p>
                        </div>
                        <div>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Méthode 2 : Créer un endpoint de test local') }}</strong>
                            <p class="mt-1">{{ __('Utilisez ngrok pour créer un tunnel vers votre serveur local et tester les webhooks en développement.') }}</p>
                        </div>
                        <div>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Méthode 3 : Utiliser le bouton Tester') }}</strong>
                            <p class="mt-1">{{ __('Dans la liste des webhooks, utilisez le bouton "Tester" pour envoyer une requête de test immédiatement.') }}</p>
                        </div>
                    </div>
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
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Le webhook n\'est pas déclenché') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('Vérifiez que le webhook est actif, que les événements sont bien sélectionnés, et que les filtres (formulaire/centre) sont corrects.') }}</p>
                            </div>
                        </div>
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
                        <div class="flex items-start gap-3">
                            <span class="text-blue-500 dark:text-blue-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Webhook déclenché plusieurs fois') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('C\'est normal si votre serveur répond avec un code d\'erreur. Le système fait jusqu\'à 3 tentatives. Répondez toujours avec un code 200 si vous recevez le webhook, et traitez les doublons dans votre code.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        ❓ {{ __('Questions fréquentes') }}
                    </h3>
                    <div class="space-y-4">
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Puis-je créer plusieurs webhooks pour le même événement ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Oui, vous pouvez créer autant de webhooks que nécessaire. Chaque webhook recevra les notifications indépendamment.') }}</p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Que se passe-t-il si mon serveur est en panne ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Le système fait jusqu\'à 3 tentatives avec un délai exponentiel. Si toutes les tentatives échouent, l\'événement est loggé mais pas renvoyé.') }}</p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Les webhooks sont-ils envoyés en temps réel ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Oui, les webhooks sont envoyés immédiatement après l\'événement, via une queue Laravel pour ne pas bloquer l\'application.') }}</p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Comment désactiver temporairement un webhook ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Allez dans la page d\'édition du webhook et décochez "Activer ce webhook". Vous pouvez le réactiver à tout moment.') }}</p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Que dois-je répondre à la requête webhook ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Répondez avec un code HTTP 200 (OK) pour indiquer que vous avez bien reçu et traité le webhook. Tout autre code déclenchera un retry.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Filtres -->
    <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800 sm:flex-row">
        <div class="flex-1">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                placeholder="{{ __('Rechercher par nom ou URL...') }}" 
                icon="magnifying-glass"
            />
        </div>
        <flux:select wire:model.live="filterStatus" placeholder="{{ __('Tous les statuts') }}">
            <option value="">{{ __('Tous les statuts') }}</option>
            <option value="active">{{ __('Actifs') }}</option>
            <option value="inactive">{{ __('Inactifs') }}</option>
        </flux:select>
        <flux:select wire:model.live="filterFormId" placeholder="{{ __('Tous les formulaires') }}">
            <option value="">{{ __('Tous les formulaires') }}</option>
            @foreach($forms as $form)
                <option value="{{ $form->id }}">{{ $form->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterCallCenterId" placeholder="{{ __('Tous les centres') }}">
            <option value="">{{ __('Tous les centres') }}</option>
            @foreach($callCenters as $callCenter)
                <option value="{{ $callCenter->id }}">{{ $callCenter->name }}</option>
            @endforeach
        </flux:select>
    </div>

    <!-- Liste des webhooks -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900/50">
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Nom') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('URL') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Événements') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Association') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Statut') }}</th>
                        <th class="px-6 py-3 text-right text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse ($webhooks as $webhook)
                        <tr class="transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                            <td class="px-6 py-4">
                                <div class="font-medium">{{ $webhook->name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                    <code class="rounded bg-neutral-100 px-1.5 py-0.5 dark:bg-neutral-900">{{ Str::limit($webhook->url, 50) }}</code>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($webhook->events ?? [] as $event)
                                        <flux:badge variant="neutral" size="sm">
                                            {{ $this->getAvailableEvents()[$event] ?? $event }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                    @if($webhook->form)
                                        <div>{{ __('Formulaire') }}: {{ $webhook->form->name }}</div>
                                    @endif
                                    @if($webhook->callCenter)
                                        <div>{{ __('Centre') }}: {{ $webhook->callCenter->name }}</div>
                                    @endif
                                    @if(!$webhook->form && !$webhook->callCenter)
                                        <span class="text-neutral-400">{{ __('Global') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <flux:badge :variant="$webhook->is_active ? 'success' : 'neutral'">
                                    {{ $webhook->is_active ? __('Actif') : __('Inactif') }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical">
                                        {{ __('Actions') }}
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.radio.group>
                                            <flux:menu.item 
                                                wire:click="testWebhook({{ $webhook->id }})"
                                                icon="bolt"
                                                wire:loading.attr="disabled"
                                                wire:target="testWebhook({{ $webhook->id }})"
                                            >
                                                <span wire:loading.remove wire:target="testWebhook({{ $webhook->id }})">
                                                    {{ __('Tester') }}
                                                </span>
                                                <span wire:loading wire:target="testWebhook({{ $webhook->id }})">
                                                    {{ __('Test en cours...') }}
                                                </span>
                                            </flux:menu.item>
                                            <flux:menu.item 
                                                href="{{ route('admin.webhooks.edit', $webhook) }}" 
                                                icon="pencil"
                                                wire:navigate
                                            >
                                                {{ __('Modifier') }}
                                            </flux:menu.item>
                                            <flux:menu.item 
                                                wire:click="toggleActive({{ $webhook->id }})"
                                                :icon="$webhook->is_active ? 'eye-slash' : 'eye'"
                                            >
                                                <span wire:loading.remove wire:target="toggleActive({{ $webhook->id }})">
                                                    {{ $webhook->is_active ? __('Désactiver') : __('Activer') }}
                                                </span>
                                                <span wire:loading wire:target="toggleActive({{ $webhook->id }})">
                                                    {{ __('Chargement...') }}
                                                </span>
                                            </flux:menu.item>
                                        </flux:menu.radio.group>

                                        <flux:menu.separator />

                                        <flux:menu.radio.group>
                                            <flux:menu.item 
                                                wire:click="delete({{ $webhook->id }})"
                                                wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer ce webhook ?') }}"
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
                        @if($testingWebhookId === $webhook->id && $testResult)
                            <tr>
                                <td colspan="6" class="px-6 py-4">
                                    <div class="rounded-lg border p-4 {{ $testResult['success'] ? 'border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/20' : 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20' }}">
                                        <div class="flex items-center gap-2">
                                            @if($testResult['success'])
                                                <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="font-medium text-green-900 dark:text-green-100">{{ __('Test réussi') }}</span>
                                            @else
                                                <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="font-medium text-red-900 dark:text-red-100">{{ __('Test échoué') }}</span>
                                            @endif
                                        </div>
                                        @if(isset($testResult['status']))
                                            <div class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                                                {{ __('Statut HTTP') }}: {{ $testResult['status'] }}
                                            </div>
                                        @endif
                                        @if(isset($testResult['error']))
                                            <div class="mt-2 text-sm text-red-600 dark:text-red-400">
                                                {{ $testResult['error'] }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Aucun webhook trouvé') }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                        @if ($search)
                                            {{ __('Essayez de modifier votre recherche') }}
                                        @else
                                            {{ __('Commencez par créer votre premier webhook') }}
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($webhooks->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                {{ $webhooks->links() }}
            </div>
        @endif
    </div>
</div>
