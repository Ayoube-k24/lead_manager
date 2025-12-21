<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Livewire\Volt\Component;

new class extends Component
{
    public array $factors = [];

    public array $thresholds = [];

    public array $autoRecalculate = [];

    public function mount(): void
    {
        $config = Config::get('lead-scoring', []);
        $this->factors = $config['factors'] ?? [];
        $this->thresholds = $config['thresholds'] ?? ['high' => 80, 'medium' => 60, 'low' => 0];
        $this->autoRecalculate = $config['auto_recalculate'] ?? [
            'on_creation' => true,
            'on_email_confirmation' => true,
            'on_status_change' => true,
            'on_note_added' => true,
        ];
    }

    public function update(): void
    {
        $validated = $this->validate([
            'factors.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'thresholds.high' => ['required', 'numeric', 'min:0', 'max:100'],
            'thresholds.medium' => ['required', 'numeric', 'min:0', 'max:100'],
            'thresholds.low' => ['required', 'numeric', 'min:0', 'max:100'],
            'autoRecalculate.*' => ['boolean'],
        ], [
            'factors.*.weight.required' => __('Le poids est requis pour tous les facteurs.'),
            'factors.*.weight.numeric' => __('Le poids doit être un nombre.'),
            'factors.*.weight.min' => __('Le poids doit être au moins 0.'),
            'factors.*.weight.max' => __('Le poids ne peut pas dépasser 100.'),
            'thresholds.high.required' => __('Le seuil haute priorité est requis.'),
            'thresholds.medium.required' => __('Le seuil moyenne priorité est requis.'),
            'thresholds.low.required' => __('Le seuil basse priorité est requis.'),
        ]);

        // Vérifier que la somme des poids est proche de 100
        $totalWeight = array_sum(array_column($this->factors, 'weight'));
        if (abs($totalWeight - 100) > 1) {
            $this->addError('factors', __('La somme des poids doit être égale à 100 (actuellement : :total).', ['total' => $totalWeight]));

            return;
        }

        // Vérifier que les seuils sont dans l'ordre décroissant
        if ($this->thresholds['high'] <= $this->thresholds['medium'] || $this->thresholds['medium'] <= $this->thresholds['low']) {
            $this->addError('thresholds', __('Les seuils doivent être dans l\'ordre décroissant (haute > moyenne > basse).'));

            return;
        }

        // Sauvegarder dans le fichier de configuration
        $configPath = config_path('lead-scoring.php');
        $config = [
            'factors' => $this->factors,
            'thresholds' => $this->thresholds,
            'auto_recalculate' => $this->autoRecalculate,
        ];

        $configContent = "<?php\n\nreturn ".var_export($config, true).";\n";
        File::put($configPath, $configContent);

        // Recharger la configuration
        Config::set('lead-scoring', $config);

        session()->flash('message', __('Configuration du scoring mise à jour avec succès !'));
    }

    public function resetToDefaults(): void
    {
        $this->factors = [
            'form_source' => [
                'weight' => 10,
                'label' => 'Source du formulaire',
                'description' => 'Score basé sur la source du formulaire (formulaires premium = score plus élevé)',
            ],
            'email_confirmation_time' => [
                'weight' => 15,
                'label' => 'Temps de confirmation email',
                'description' => 'Score basé sur la rapidité de confirmation de l\'email (< 1h = +30, < 24h = +15)',
            ],
            'data_completeness' => [
                'weight' => 20,
                'label' => 'Complétude des données',
                'description' => 'Score basé sur le nombre de champs remplis dans le formulaire',
            ],
            'lead_history' => [
                'weight' => 25,
                'label' => 'Historique du lead',
                'description' => 'Score basé sur les interactions, notes et changements de statut',
            ],
            'current_status' => [
                'weight' => 20,
                'label' => 'Statut actuel',
                'description' => 'Score basé sur le statut actuel du lead (email_confirmed = +15, pending_call = +10)',
            ],
            'behavioral_data' => [
                'weight' => 10,
                'label' => 'Données comportementales',
                'description' => 'Score basé sur l\'heure de soumission et le jour de la semaine',
            ],
        ];
        $this->thresholds = ['high' => 80, 'medium' => 60, 'low' => 0];
        $this->autoRecalculate = [
            'on_creation' => true,
            'on_email_confirmation' => true,
            'on_status_change' => true,
            'on_note_added' => true,
        ];
    }

    public function getTotalWeightProperty(): float
    {
        return array_sum(array_column($this->factors, 'weight'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Configuration du Scoring') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Configurez les facteurs de scoring et les seuils de priorité') }}</p>
        </div>
    </div>

    <!-- Messages flash -->
    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    <!-- Formulaire -->
    <form wire:submit="update" class="flex flex-col gap-6">
        <!-- Facteurs de scoring -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ __('Facteurs de Scoring') }}</h2>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Total : :total%', ['total' => round($this->totalWeight, 1)]) }}
                    </span>
                    @if (abs($this->totalWeight - 100) > 1)
                        <flux:badge variant="danger" size="sm">{{ __('La somme doit être 100%') }}</flux:badge>
                    @else
                        <flux:badge variant="success" size="sm">{{ __('OK') }}</flux:badge>
                    @endif
                </div>
            </div>

            <flux:error name="factors" />

            <div class="space-y-4">
                @foreach ($factors as $key => $factor)
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex-1">
                                <h3 class="font-semibold text-neutral-900 dark:text-neutral-100">
                                    {{ $factor['label'] }}
                                </h3>
                                @if (isset($factor['description']))
                                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $factor['description'] }}
                                    </p>
                                @endif
                            </div>
                            <div class="ml-4 flex items-center gap-2">
                                <flux:input
                                    type="number"
                                    wire:model.live="factors.{{ $key }}.weight"
                                    min="0"
                                    max="100"
                                    step="1"
                                    class="w-20"
                                />
                                <span class="text-sm text-neutral-600 dark:text-neutral-400">%</span>
                            </div>
                        </div>
                        <flux:error name="factors.{{ $key }}.weight" />
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Seuils de priorité -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Seuils de Priorité') }}</h2>
            <flux:error name="thresholds" />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Haute priorité') }} (≥)</flux:label>
                    <flux:input
                        type="number"
                        wire:model="thresholds.high"
                        min="0"
                        max="100"
                        step="1"
                    />
                    <flux:error name="thresholds.high" />
                    <flux:description>{{ __('Score minimum pour une priorité haute') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Moyenne priorité') }} (≥)</flux:label>
                    <flux:input
                        type="number"
                        wire:model="thresholds.medium"
                        min="0"
                        max="100"
                        step="1"
                    />
                    <flux:error name="thresholds.medium" />
                    <flux:description>{{ __('Score minimum pour une priorité moyenne') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Basse priorité') }} (≥)</flux:label>
                    <flux:input
                        type="number"
                        wire:model="thresholds.low"
                        min="0"
                        max="100"
                        step="1"
                    />
                    <flux:error name="thresholds.low" />
                    <flux:description>{{ __('Score minimum pour une priorité basse') }}</flux:description>
                </flux:field>
            </div>

            <!-- Aperçu des seuils -->
            <div class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                <h3 class="mb-2 text-sm font-semibold">{{ __('Aperçu') }}</h3>
                <div class="flex items-center gap-2">
                    <div class="flex-1">
                        <div class="h-4 rounded-full bg-gradient-to-r from-red-500 via-yellow-500 to-green-500"></div>
                    </div>
                    <div class="flex gap-4 text-xs text-neutral-600 dark:text-neutral-400">
                        <span>0</span>
                        <span>{{ $thresholds['low'] }}</span>
                        <span>{{ $thresholds['medium'] }}</span>
                        <span>{{ $thresholds['high'] }}</span>
                        <span>100</span>
                    </div>
                </div>
                <div class="mt-2 flex justify-between text-xs">
                    <span class="text-red-600 dark:text-red-400">{{ __('Basse') }}</span>
                    <span class="text-yellow-600 dark:text-yellow-400">{{ __('Moyenne') }}</span>
                    <span class="text-green-600 dark:text-green-400">{{ __('Haute') }}</span>
                </div>
            </div>
        </div>

        <!-- Recalcul automatique -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Recalcul Automatique') }}</h2>
            <p class="mb-4 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Définissez quand le score doit être recalculé automatiquement') }}
            </p>

            <div class="space-y-3">
                <flux:field>
                    <flux:switch wire:model="autoRecalculate.on_creation" />
                    <flux:label>{{ __('Lors de la création du lead') }}</flux:label>
                    <flux:description>{{ __('Recalculer le score dès qu\'un nouveau lead est créé') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:switch wire:model="autoRecalculate.on_email_confirmation" />
                    <flux:label>{{ __('Lors de la confirmation de l\'email') }}</flux:label>
                    <flux:description>{{ __('Recalculer le score quand l\'email est confirmé') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:switch wire:model="autoRecalculate.on_status_change" />
                    <flux:label>{{ __('Lors du changement de statut') }}</flux:label>
                    <flux:description>{{ __('Recalculer le score quand le statut du lead change') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:switch wire:model="autoRecalculate.on_note_added" />
                    <flux:label>{{ __('Lors de l\'ajout d\'une note') }}</flux:label>
                    <flux:description>{{ __('Recalculer le score quand une note est ajoutée au lead') }}</flux:description>
                </flux:field>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <flux:button
                type="button"
                wire:click="resetToDefaults"
                wire:confirm="{{ __('Êtes-vous sûr de vouloir réinitialiser la configuration aux valeurs par défaut ?') }}"
                variant="ghost"
            >
                {{ __('Réinitialiser') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="update">
                    {{ __('Enregistrer') }}
                </span>
                <span wire:loading wire:target="update" class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('Enregistrement...') }}
                </span>
            </flux:button>
        </div>
    </form>
</div>









