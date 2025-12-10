<?php

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $distributionMethod = 'round_robin';
    public string $distributionTiming = 'after_email_confirmation';

    public function mount(): void
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if ($callCenter) {
            $this->distributionMethod = $callCenter->distribution_method ?? 'round_robin';
            $this->distributionTiming = $callCenter->distribution_timing ?? 'after_email_confirmation';
        }
    }

    public function updateDistributionMethod(AuditService $auditService): void
    {
        $this->validate([
            'distributionMethod' => ['required', 'in:round_robin,weighted,manual'],
            'distributionTiming' => ['required', 'in:after_registration,after_email_confirmation'],
        ]);

        $user = Auth::user();
        $callCenter = $user->callCenter;

        if (! $callCenter) {
            $this->addError('call_center', 'Vous devez être associé à un centre d\'appels.');
            return;
        }

        $oldMethod = $callCenter->distribution_method;
        $oldTiming = $callCenter->distribution_timing;
        $callCenter->distribution_method = $this->distributionMethod;
        $callCenter->distribution_timing = $this->distributionTiming;
        $callCenter->save();

        // Log the change
        if ($oldMethod !== $this->distributionMethod) {
            $auditService->logDistributionMethodChanged($callCenter, $oldMethod, $this->distributionMethod);
        }

        if ($oldTiming !== $this->distributionTiming) {
            $auditService->log('distribution_timing_changed', [
                'call_center_id' => $callCenter->id,
                'old_timing' => $oldTiming,
                'new_timing' => $this->distributionTiming,
            ]);
        }

        session()->flash('message', __('Configuration mise à jour avec succès !'));
        $this->dispatch('distribution-method-updated');
    }

    public function getAgentsWithWorkloadProperty()
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if (! $callCenter) {
            return collect();
        }

        $agents = User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->withCount([
                'assignedLeads as total_leads_count',
                'assignedLeads as pending_leads_count' => function ($query) {
                    $query->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending']);
                },
                'assignedLeads as confirmed_leads_count' => function ($query) {
                    $query->where('status', 'confirmed');
                },
            ])
            ->orderBy('pending_leads_count')
            ->get();

        return $agents->map(function ($agent) {
            $stats = $this->calculateAgentStats($agent);
            $agent->workload_percentage = $this->calculateWorkloadPercentage($agent, $stats);

            return $agent;
        });
    }

    protected function calculateAgentStats(User $agent): array
    {
        $leads = Lead::where('assigned_to', $agent->id)->get();

        return [
            'total' => $leads->count(),
            'pending' => $leads->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])->count(),
            'confirmed' => $leads->where('status', 'confirmed')->count(),
            'rejected' => $leads->where('status', 'rejected')->count(),
            'conversion_rate' => $leads->count() > 0
                ? round(($leads->where('status', 'confirmed')->count() / $leads->count()) * 100, 2)
                : 0,
        ];
    }

    protected function calculateWorkloadPercentage(User $agent, array $stats): float
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if (! $callCenter) {
            return 0;
        }

        $allAgents = User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->withCount([
                'assignedLeads as pending_leads_count' => function ($query) {
                    $query->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending']);
                },
            ])
            ->get();

        if ($allAgents->isEmpty()) {
            return 0;
        }

        $maxPending = $allAgents->max('pending_leads_count') ?? 0;
        if ($maxPending === 0) {
            return 0;
        }

        return round(($stats['pending'] / $maxPending) * 100, 0);
    }

    public function getDistributionMethodDescriptionProperty(): string
    {
        return match ($this->distributionMethod) {
            'round_robin' => 'Les leads sont distribués de manière équilibrée entre tous les agents, en privilégiant ceux qui ont le moins de leads en attente.',
            'weighted' => 'Les leads sont distribués en fonction des performances des agents. Les agents avec un taux de conversion plus faible reçoivent plus de leads pour améliorer leur performance.',
            'manual' => 'Aucune attribution automatique. Vous devez attribuer manuellement chaque lead aux agents.',
            default => '',
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Configuration de la Distribution') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Configurez comment les leads seront attribués automatiquement aux agents') }}
            </p>
        </div>
    </div>

    @if (session()->has('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    <!-- Configuration de la méthode de distribution -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
            {{ __('Méthode de distribution') }}
        </h2>

        <form wire:submit="updateDistributionMethod" class="space-y-6">
            <div class="space-y-4">
                <label class="flex items-start gap-3 rounded-lg border border-neutral-200 p-4 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-700/50 {{ $distributionMethod === 'round_robin' ? 'border-blue-500 bg-blue-50 dark:border-blue-500 dark:bg-blue-900/20' : '' }}">
                    <input type="radio" wire:model="distributionMethod" value="round_robin" class="mt-1" />
                    <div class="flex-1">
                        <div class="font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Rotation équilibrée (Round Robin)') }}</div>
                        <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Les leads sont distribués de manière équilibrée entre tous les agents, en privilégiant ceux qui ont le moins de leads en attente.') }}
                        </div>
                    </div>
                </label>

                <label class="flex items-start gap-3 rounded-lg border border-neutral-200 p-4 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-700/50 {{ $distributionMethod === 'weighted' ? 'border-blue-500 bg-blue-50 dark:border-blue-500 dark:bg-blue-900/20' : '' }}">
                    <input type="radio" wire:model="distributionMethod" value="weighted" class="mt-1" />
                    <div class="flex-1">
                        <div class="font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Pondérée par performance') }}</div>
                        <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Les leads sont distribués en fonction des performances des agents. Les agents avec un taux de conversion plus faible reçoivent plus de leads pour améliorer leur performance.') }}
                        </div>
                    </div>
                </label>

                <label class="flex items-start gap-3 rounded-lg border border-neutral-200 p-4 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-700/50 {{ $distributionMethod === 'manual' ? 'border-blue-500 bg-blue-50 dark:border-blue-500 dark:bg-blue-900/20' : '' }}">
                    <input type="radio" wire:model="distributionMethod" value="manual" class="mt-1" />
                    <div class="flex-1">
                        <div class="font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Manuelle') }}</div>
                        <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Aucune attribution automatique. Vous devez attribuer manuellement chaque lead aux agents.') }}
                        </div>
                    </div>
                </label>
            </div>

            <div class="border-t border-neutral-200 pt-6 dark:border-neutral-700">
                <h3 class="mb-4 text-base font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Moment de distribution') }}
                </h3>
                <p class="mb-4 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Choisissez à quel moment les leads doivent être distribués aux agents') }}
                </p>

                <div class="space-y-4">
                    <label class="flex items-start gap-3 rounded-lg border border-neutral-200 p-4 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-700/50 {{ $distributionTiming === 'after_registration' ? 'border-blue-500 bg-blue-50 dark:border-blue-500 dark:bg-blue-900/20' : '' }}">
                        <input type="radio" wire:model="distributionTiming" value="after_registration" class="mt-1" />
                        <div class="flex-1">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Après inscription') }}</div>
                            <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ __('Les leads sont distribués immédiatement après leur inscription, sans attendre la confirmation de l\'email.') }}
                            </div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-neutral-200 p-4 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-700/50 {{ $distributionTiming === 'after_email_confirmation' ? 'border-blue-500 bg-blue-50 dark:border-blue-500 dark:bg-blue-900/20' : '' }}">
                        <input type="radio" wire:model="distributionTiming" value="after_email_confirmation" class="mt-1" />
                        <div class="flex-1">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Après validation email opt-in') }}</div>
                            <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ __('Les leads sont distribués uniquement après que l\'utilisateur ait confirmé son email via le lien de confirmation.') }}
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="updateDistributionMethod">
                        {{ __('Enregistrer la configuration') }}
                    </span>
                    <span wire:loading wire:target="updateDistributionMethod" class="flex items-center gap-2">
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

    <!-- Répartition actuelle des leads par agent -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
            {{ __('Répartition actuelle des leads par agent') }}
        </h2>

        <div class="space-y-4">
            @forelse ($this->agentsWithWorkload as $agent)
                @php
                    $stats = $this->calculateAgentStats($agent);
                    $workloadPercentage = $this->calculateWorkloadPercentage($agent, $stats);
                @endphp
                <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $agent->name }}</h3>
                                    <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ $agent->email }}</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                                        {{ $agent->pending_leads_count }}
                                    </div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('en attente') }}</div>
                                </div>
                            </div>

                            <!-- Barre de progression de la charge de travail -->
                            <div class="mt-3">
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Charge de travail') }}</span>
                                    <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ $workloadPercentage }}%</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                                    <div
                                        class="h-full rounded-full transition-all {{ $workloadPercentage >= 80 ? 'bg-red-500' : ($workloadPercentage >= 50 ? 'bg-yellow-500' : 'bg-green-500') }}"
                                        style="width: {{ min($workloadPercentage, 100) }}%"
                                    ></div>
                                </div>
                            </div>

                            <!-- Statistiques détaillées -->
                            <div class="mt-4 grid grid-cols-4 gap-4 text-center">
                                <div>
                                    <div class="text-lg font-bold text-neutral-900 dark:text-neutral-100">{{ $stats['total'] }}</div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Total') }}</div>
                                </div>
                                <div>
                                    <div class="text-lg font-bold text-green-600 dark:text-green-400">{{ $stats['confirmed'] }}</div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Confirmés') }}</div>
                                </div>
                                <div>
                                    <div class="text-lg font-bold text-red-600 dark:text-red-400">{{ $stats['rejected'] }}</div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Rejetés') }}</div>
                                </div>
                                <div>
                                    <div class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $stats['conversion_rate'] }}%</div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Taux') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-neutral-200 p-8 text-center dark:border-neutral-700">
                    <p class="text-neutral-500 dark:text-neutral-400">{{ __('Aucun agent trouvé') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

