<?php

use App\Models\Alert;
use App\Services\AlertService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public bool $showCreateModal = false;
    public bool $showGuide = false;
    public string $alertName = '';
    public string $alertType = 'lead_stale';
    public array $alertConditions = [];
    public ?float $alertThreshold = null;
    public array $alertChannels = ['in_app'];
    public ?int $editingAlertId = null;

    public function mount(): void
    {
        $this->resetAlertForm();
    }

    public function openCreateModal(): void
    {
        $this->showCreateModal = true;
        $this->resetAlertForm();
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetAlertForm();
    }

    public function toggleGuide(): void
    {
        $this->showGuide = ! $this->showGuide;
    }

    public function resetAlertForm(): void
    {
        $this->alertName = '';
        
        // Get first available type for the role
        $availableTypes = $this->getAvailableTypes();
        $this->alertType = ! empty($availableTypes) ? array_key_first($availableTypes) : 'status_threshold';
        
        // Initialize conditions from default
        $this->alertConditions = $this->getTypeConditions($this->alertType);
        
        $this->alertThreshold = null;
        $this->alertChannels = ['in_app'];
        $this->editingAlertId = null;
    }

    public function updatedAlertType(): void
    {
        // When alert type changes, update conditions from default
        $this->alertConditions = $this->getTypeConditions($this->alertType);
    }

    public function getAvailableTypes(): array
    {
        $roleSlug = Auth::user()->role?->slug;
        
        if (! $roleSlug) {
            return [];
        }

        $service = app(AlertService::class);
        $types = $service->getAvailableTypesForRole($roleSlug);

        $result = [];
        foreach ($types as $typeKey => $typeData) {
            $result[$typeKey] = $typeData['name'];
        }

        return $result;
    }

    public function getTypeConditions(string $type): array
    {
        $roleSlug = Auth::user()->role?->slug;
        
        if (! $roleSlug) {
            return [];
        }

        // Get default conditions from RoleAlertType
        $roleAlertType = \App\Models\RoleAlertType::forRole($roleSlug)
            ->where('alert_type', $type)
            ->first();

        if ($roleAlertType && $roleAlertType->default_conditions) {
            $conditions = $roleAlertType->default_conditions;
        } else {
            // Fallback to default conditions
            $conditions = match ($type) {
                'lead_stale' => ['hours' => 24],
                'agent_performance' => ['agent_id' => null],
                'conversion_rate' => [],
                'high_volume' => ['hours' => 1],
                'low_volume' => ['hours' => 1],
                'form_performance' => ['form_id' => null],
                'status_threshold' => ['status_slug' => null, 'agent_id' => null, 'call_center_id' => null],
                default => [],
            };
        }
        
        // For agents creating status_threshold alerts, automatically set their own ID and call center
        if ($type === 'status_threshold' && Auth::user()->isAgent()) {
            $conditions['agent_id'] = Auth::id();
            if (Auth::user()->call_center_id) {
                $conditions['call_center_id'] = Auth::user()->call_center_id;
            }
        }
        
        return $conditions;
    }

    public function createAlert(): void
    {
        $rules = [
            'alertName' => ['required', 'string', 'max:255'],
            'alertType' => ['required', 'string'],
            'alertThreshold' => ['nullable', 'numeric', 'min:0'],
            'alertChannels' => ['required', 'array', 'min:1'],
        ];

        // Add validation rules for status_threshold type
        if ($this->alertType === 'status_threshold') {
            $rules['alertConditions.status_slug'] = ['required', 'string'];
            
            // For agents, automatically set agent_id and call_center_id
            if (Auth::user()->isAgent()) {
                // Automatically set agent_id to the current agent
                $this->alertConditions['agent_id'] = Auth::id();
                
                // Automatically set call_center_id to the agent's call center
                if (Auth::user()->call_center_id) {
                    $this->alertConditions['call_center_id'] = Auth::user()->call_center_id;
                }
            } else {
                // For other roles, allow optional selection
                $rules['alertConditions.agent_id'] = ['nullable', 'integer', 'exists:users,id'];
                $rules['alertConditions.call_center_id'] = ['nullable', 'integer', 'exists:call_centers,id'];
            }
        }

        $this->validate($rules);

        $service = app(AlertService::class);
        $service->createAlert(
            Auth::user(),
            $this->alertType,
            $this->alertConditions,
            $this->alertThreshold,
            $this->alertChannels
        );

        $this->closeCreateModal();
        session()->flash('message', __('Alerte créée avec succès.'));
    }

    public function toggleActive(Alert $alert): void
    {
        // Security check: user can only modify alerts for their role
        if ($alert->role_slug !== Auth::user()->role?->slug) {
            session()->flash('error', __('Vous n\'avez pas la permission de modifier cette alerte.'));
            return;
        }

        if ($alert->is_system) {
            session()->flash('error', __('Les alertes système ne peuvent pas être modifiées.'));
            return;
        }

        $alert->update(['is_active' => ! $alert->is_active]);
        session()->flash('message', __('Alerte :action avec succès.', [
            'action' => $alert->is_active ? __('activée') : __('désactivée')
        ]));
    }

    public function deleteAlert(Alert $alert): void
    {
        // Security check: user can only delete alerts for their role
        if ($alert->role_slug !== Auth::user()->role?->slug) {
            session()->flash('error', __('Vous n\'avez pas la permission de supprimer cette alerte.'));
            return;
        }

        if ($alert->is_system) {
            session()->flash('error', __('Les alertes système ne peuvent pas être supprimées.'));
            return;
        }

        $alert->delete();
        session()->flash('message', __('Alerte supprimée avec succès.'));
    }

    public function with(): array
    {
        // Filter alerts by user's role
        $roleSlug = Auth::user()->role?->slug;
        
        $alerts = Alert::where('role_slug', $roleSlug)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return [
            'alerts' => $alerts,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Alertes') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Configurez des alertes pour être notifié des événements importants') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <flux:button wire:click="toggleGuide" variant="ghost" icon="question-mark-circle">
                {{ $showGuide ? __('Masquer le guide') : __('Afficher le guide') }}
            </flux:button>
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                {{ __('Nouvelle alerte') }}
            </flux:button>
        </div>
    </div>

    <!-- Guide visuel -->
    @if ($showGuide)
        <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="border-b border-neutral-200 p-6 dark:border-neutral-700">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Guide des alertes') }}</h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Apprenez à créer et configurer des alertes efficacement') }}</p>
            </div>

            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                <!-- Comment créer une alerte -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        📝 {{ __('Comment créer une alerte') }}
                    </h3>
                    <ol class="list-decimal list-inside space-y-2 text-sm text-neutral-600 dark:text-neutral-400">
                        <li>{{ __('Cliquez sur "Nouvelle alerte" en haut à droite') }}</li>
                        <li>{{ __('Donnez un nom descriptif à votre alerte (ex: "Leads inactifs depuis 24h")') }}</li>
                        <li>{{ __('Sélectionnez le type d\'alerte approprié') }}</li>
                        <li>{{ __('Définissez un seuil si nécessaire (nombre ou pourcentage)') }}</li>
                        <li>{{ __('Choisissez les canaux de notification (in-app et/ou email)') }}</li>
                        <li>{{ __('Cliquez sur "Créer"') }}</li>
                    </ol>
                </div>

                <!-- Types d'alertes -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-4">
                        🔔 {{ __('Types d\'alertes disponibles') }}
                    </h3>
                    <div class="space-y-4">
                        @if (!Auth::user()->isAgent())
                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                                <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Lead inactif') }}</div>
                                <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Détecte les leads qui n\'ont pas été mis à jour depuis X heures') }}</p>
                                <div class="text-xs text-neutral-500 dark:text-neutral-500">
                                    <strong>{{ __('Exemple :') }}</strong> {{ __('Seuil: 10, Conditions: 24h → Alerte si 10+ leads inactifs depuis 24h') }}
                                </div>
                            </div>

                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                                <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Performance agent') }}</div>
                                <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Surveille le taux de conversion d\'un agent spécifique') }}</p>
                                <div class="text-xs text-neutral-500 dark:text-neutral-500">
                                    <strong>{{ __('Exemple :') }}</strong> {{ __('Seuil: 40%, Agent: #5 → Alerte si taux < 40%') }}
                                </div>
                            </div>

                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                                <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Taux de conversion') }}</div>
                                <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Surveille le taux de conversion global de tous les leads') }}</p>
                                <div class="text-xs text-neutral-500 dark:text-neutral-500">
                                    <strong>{{ __('Exemple :') }}</strong> {{ __('Seuil: 30% → Alerte si taux global < 30%') }}
                                </div>
                            </div>

                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                                <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Volume élevé') }}</div>
                                <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Détecte quand trop de leads arrivent dans un laps de temps') }}</p>
                                <div class="text-xs text-neutral-500 dark:text-neutral-500">
                                    <strong>{{ __('Exemple :') }}</strong> {{ __('Seuil: 50, Période: 1h → Alerte si 50+ leads en 1h') }}
                                </div>
                            </div>

                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                                <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Volume faible') }}</div>
                                <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Détecte quand trop peu de leads arrivent dans un laps de temps') }}</p>
                                <div class="text-xs text-neutral-500 dark:text-neutral-500">
                                    <strong>{{ __('Exemple :') }}</strong> {{ __('Seuil: 5, Période: 2h → Alerte si < 5 leads en 2h') }}
                                </div>
                            </div>

                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                                <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Performance formulaire') }}</div>
                                <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Surveille le taux de conversion d\'un formulaire spécifique') }}</p>
                                <div class="text-xs text-neutral-500 dark:text-neutral-500">
                                    <strong>{{ __('Exemple :') }}</strong> {{ __('Seuil: 20%, Formulaire: #3 → Alerte si taux < 20%') }}
                                </div>
                            </div>
                        @endif

                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Seuil de statut') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Alerte lorsque le nombre de leads avec un statut spécifique atteint un seuil') }}</p>
                            <div class="text-xs text-neutral-500 dark:text-neutral-500">
                                <strong>{{ __('Exemple :') }}</strong> {{ __('Statut: En attente d\'appel, Seuil: 20 → Alerte si 20+ leads en attente d\'appel') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fonctionnement -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        ⚙️ {{ __('Comment ça fonctionne') }}
                    </h3>
                    <div class="space-y-3 text-sm text-neutral-600 dark:text-neutral-400">
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-xs font-semibold text-blue-600 dark:text-blue-400">1</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Vérification automatique') }}</strong>
                                <p>{{ __('Le système vérifie toutes les alertes actives toutes les 15 minutes') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-xs font-semibold text-blue-600 dark:text-blue-400">2</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Évaluation des conditions') }}</strong>
                                <p>{{ __('Pour chaque alerte, le système vérifie si les conditions sont remplies') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-xs font-semibold text-blue-600 dark:text-blue-400">3</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Déclenchement') }}</strong>
                                <p>{{ __('Si les conditions sont remplies, l\'alerte est déclenchée et les notifications sont envoyées') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-xs font-semibold text-blue-600 dark:text-blue-400">4</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Cooldown') }}</strong>
                                <p>{{ __('L\'alerte ne peut pas être déclenchée à nouveau pendant 60 minutes (pour éviter le spam)') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Canaux de notification -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        📱 {{ __('Canaux de notification') }}
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                            <div class="font-semibold text-green-900 dark:text-green-100 mb-1">{{ __('Notification in-app') }}</div>
                            <p class="text-sm text-green-700 dark:text-green-300">{{ __('Visible dans l\'interface Lead Manager. Pas de configuration supplémentaire nécessaire.') }}</p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                            <div class="font-semibold text-blue-900 dark:text-blue-100 mb-1">{{ __('Email') }}</div>
                            <p class="text-sm text-blue-700 dark:text-blue-300">{{ __('Reçue même si vous n\'êtes pas connecté. Vérifiez votre dossier spam si nécessaire.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Exemples pratiques -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        💡 {{ __('Exemples pratiques') }}
                    </h3>
                    <div class="space-y-3">
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Exemple 1 : Surveiller les leads inactifs') }}</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400 space-y-1">
                                <p><strong>{{ __('Nom :') }}</strong> Leads inactifs depuis 24h</p>
                                <p><strong>{{ __('Type :') }}</strong> Lead inactif</p>
                                <p><strong>{{ __('Seuil :') }}</strong> 10</p>
                                <p><strong>{{ __('Canaux :') }}</strong> In-app + Email</p>
                                <p class="mt-2 text-xs italic">{{ __('Résultat : Notification si 10+ leads n\'ont pas été mis à jour depuis 24h') }}</p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Exemple 2 : Détecter un pic de volume') }}</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400 space-y-1">
                                <p><strong>{{ __('Nom :') }}</strong> Pic de volume de leads</p>
                                <p><strong>{{ __('Type :') }}</strong> Volume élevé</p>
                                <p><strong>{{ __('Seuil :') }}</strong> 50</p>
                                <p><strong>{{ __('Canaux :') }}</strong> In-app</p>
                                <p class="mt-2 text-xs italic">{{ __('Résultat : Notification si 50+ leads créés dans la dernière heure') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Problèmes courants -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        🔧 {{ __('Résolution des problèmes') }}
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-start gap-3">
                            <span class="text-red-500 dark:text-red-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('L\'alerte ne se déclenche jamais') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('Vérifiez que l\'alerte est active, que le seuil n\'est pas trop élevé, et que les conditions sont réalistes.') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-yellow-500 dark:text-yellow-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Trop de notifications') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('Augmentez le seuil ou ajustez les conditions pour être plus strict. Le cooldown limite déjà à 1 déclenchement par heure.') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-blue-500 dark:text-blue-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Pas d\'email reçu') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('Vérifiez votre email dans le profil, le dossier spam, et que le canal email est sélectionné.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

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

    <!-- Liste des alertes -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900/50">
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Nom') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Type') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Seuil') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Canaux') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Statut') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Dernier déclenchement') }}</th>
                        <th class="px-6 py-3 text-right text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse ($alerts as $alert)
                        <tr class="transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                            <td class="px-6 py-4">
                                <div class="font-medium">{{ $alert->name }}</div>
                                @if ($alert->is_system)
                                    <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                        <flux:badge variant="neutral" size="sm">{{ __('Système') }}</flux:badge>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-neutral-600 dark:text-neutral-400">
                                    {{ $alert->getTypeLabel() }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if ($alert->threshold !== null)
                                    <span class="text-sm text-neutral-900 dark:text-neutral-100">{{ $alert->threshold }}</span>
                                @else
                                    <span class="text-xs text-neutral-400">{{ __('N/A') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($alert->notification_channels as $channel)
                                        <flux:badge variant="neutral" size="sm">
                                            {{ match($channel) {
                                                'email' => __('Email'),
                                                'in_app' => __('In-App'),
                                                'sms' => __('SMS'),
                                                default => $channel
                                            } }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <flux:badge :variant="$alert->is_active ? 'success' : 'neutral'">
                                    {{ $alert->is_active ? __('Actif') : __('Inactif') }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4">
                                @if ($alert->last_triggered_at)
                                    <span class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $alert->last_triggered_at->format('d/m/Y H:i') }}
                                    </span>
                                @else
                                    <span class="text-xs text-neutral-400">{{ __('Jamais') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical">
                                        {{ __('Actions') }}
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.radio.group>
                                            @if (!$alert->is_system)
                                                <flux:menu.item 
                                                    wire:click="toggleActive({{ $alert->id }})"
                                                    :icon="$alert->is_active ? 'eye-slash' : 'eye'"
                                                >
                                                    <span wire:loading.remove wire:target="toggleActive({{ $alert->id }})">
                                                        {{ $alert->is_active ? __('Désactiver') : __('Activer') }}
                                                    </span>
                                                    <span wire:loading wire:target="toggleActive({{ $alert->id }})">
                                                        {{ __('Chargement...') }}
                                                    </span>
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu.radio.group>

                                        @if (!$alert->is_system)
                                            <flux:menu.separator />

                                            <flux:menu.radio.group>
                                                <flux:menu.item 
                                                    wire:click="deleteAlert({{ $alert->id }})"
                                                    wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer cette alerte ?') }}"
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
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Aucune alerte configurée') }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ __('Créez votre première alerte pour être notifié des événements importants') }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($alerts->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                {{ $alerts->links() }}
            </div>
        @endif
    </div>

    <!-- Modal de création d'alerte -->
    <flux:modal wire:model="showCreateModal" name="create-alert">
        <form wire:submit="createAlert" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Créer une alerte') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Configurez une alerte pour être notifié automatiquement') }}
                </p>
            </div>

            <flux:field>
                <flux:label>{{ __('Nom de l\'alerte') }}</flux:label>
                <flux:input wire:model="alertName" placeholder="{{ __('Ex: Leads inactifs depuis 24h') }}" />
                <flux:error name="alertName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Type d\'alerte') }}</flux:label>
                <flux:select wire:model.live="alertType">
                    @foreach ($this->getAvailableTypes() as $type => $label)
                        <option value="{{ $type }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="alertType" />
            </flux:field>

            @if ($alertType === 'status_threshold')
                <flux:field>
                    <flux:label>{{ __('Statut à surveiller') }}</flux:label>
                    <flux:select wire:model="alertConditions.status_slug">
                        <option value="">{{ __('Sélectionner un statut') }}</option>
                        @foreach (\App\Models\LeadStatus::allStatuses() as $status)
                            <option value="{{ $status->slug }}">{{ $status->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="alertConditions.status_slug" />
                    <flux:description>{{ __('Le statut pour lequel vous souhaitez être alerté') }}</flux:description>
                </flux:field>
                
                @php
                    // Get description from RoleAlertType if available
                    $roleSlug = Auth::user()->role?->slug;
                    $roleAlertType = $roleSlug ? \App\Models\RoleAlertType::forRole($roleSlug)
                        ->where('alert_type', $alertType)
                        ->where('default_conditions->status_slug', $alertConditions['status_slug'] ?? null)
                        ->first() : null;
                @endphp
                @if ($roleAlertType && $roleAlertType->description)
                    <flux:description class="mt-1 text-xs text-neutral-500">
                        {{ $roleAlertType->description }}
                    </flux:description>
                @endif

                @if (!Auth::user()->isAgent())
                    <flux:field>
                        <flux:label>{{ __('Agent (optionnel)') }}</flux:label>
                        <flux:select wire:model="alertConditions.agent_id">
                            <option value="">{{ __('Tous les agents') }}</option>
                            @foreach (\App\Models\User::whereHas('role', fn($q) => $q->where('slug', 'agent'))->get() as $agent)
                                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="alertConditions.agent_id" />
                        <flux:description>{{ __('Limiter l\'alerte aux leads d\'un agent spécifique') }}</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Centre d\'appels (optionnel)') }}</flux:label>
                        <flux:select wire:model="alertConditions.call_center_id">
                            <option value="">{{ __('Tous les centres') }}</option>
                            @foreach (\App\Models\CallCenter::all() as $callCenter)
                                <option value="{{ $callCenter->id }}">{{ $callCenter->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="alertConditions.call_center_id" />
                        <flux:description>{{ __('Limiter l\'alerte aux leads d\'un centre d\'appels spécifique') }}</flux:description>
                    </flux:field>
                @endif
            @endif

            <flux:field>
                <flux:label>{{ __('Seuil') }}</flux:label>
                <flux:input wire:model="alertThreshold" type="number" step="0.01" placeholder="{{ __('Ex: 10') }}" />
                <flux:error name="alertThreshold" />
                <flux:description>
                    @if ($alertType === 'status_threshold')
                        {{ __('Nombre minimum de leads avec ce statut pour déclencher l\'alerte') }}
                    @else
                        {{ __('Le seuil de déclenchement de l\'alerte') }}
                    @endif
                </flux:description>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Canaux de notification') }}</flux:label>
                <div class="space-y-2">
                    <flux:checkbox wire:model="alertChannels" value="in_app" label="{{ __('Notification in-app') }}" />
                    <flux:checkbox wire:model="alertChannels" value="email" label="{{ __('Email') }}" />
                </div>
                <flux:error name="alertChannels" />
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeCreateModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createAlert">
                        {{ __('Créer') }}
                    </span>
                    <span wire:loading wire:target="createAlert" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Création...') }}
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

