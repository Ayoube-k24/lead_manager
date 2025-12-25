<?php

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadDistributionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    // Propriétés pour la réassignation
    public ?int $reassignAgentId = null;

    public ?int $reassignToAgentId = null;

    public ?int $reassignCount = null;

    public string $reassignMode = 'all'; // 'all' or 'count'

    public array $reassignStatuses = ['pending_call', 'email_confirmed', 'callback_pending'];

    public bool $showReassignModal = false;

    public ?array $reassignResult = null;

    public function mount(): void
    {
        //
    }

    public function getAgentsProperty()
    {
        $user = Auth::user();

        // Ensure callCenter relationship is loaded
        if (! $user->relationLoaded('callCenter')) {
            $user->load('callCenter');
        }

        $callCenter = $user->callCenter;

        if (! $callCenter) {
            return collect();
        }

        return User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->withCount([
                'assignedLeads as pending_leads_count' => function ($query) use ($callCenter) {
                    $query->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])
                        ->where('call_center_id', $callCenter->id);
                },
                'assignedLeads as total_leads_count' => function ($query) use ($callCenter) {
                    $query->where('call_center_id', $callCenter->id);
                },
            ])
            ->orderBy('pending_leads_count')
            ->orderBy('name')
            ->get();
    }

    public function openReassignModal(int $agentId): void
    {
        $user = Auth::user();
        $agent = User::findOrFail($agentId);

        // Vérifier que l'agent appartient au centre d'appels du owner
        if (! $user->isCallCenterOwner() || $agent->call_center_id !== $user->call_center_id) {
            $this->dispatch('error', message: __('Vous n\'êtes pas autorisé à réassigner les leads de cet agent.'));
            return;
        }

        // Vérifier que l'utilisateur est bien un agent
        if (! $agent->role || $agent->role->slug !== 'agent') {
            $this->dispatch('error', message: __('Utilisateur non trouvé ou n\'est pas un agent.'));
            return;
        }

        $this->reassignAgentId = $agentId;
        $this->reassignResult = null;
        $this->reassignToAgentId = null;
        $this->reassignCount = null;
        $this->reassignMode = 'all';
        $this->reassignStatuses = ['pending_call', 'email_confirmed', 'callback_pending'];
        $this->showReassignModal = true;
    }

    public function closeReassignModal(): void
    {
        $this->showReassignModal = false;
        $this->reassignResult = null;
        $this->reassignAgentId = null;
        $this->reassignToAgentId = null;
        $this->reassignCount = null;
        $this->reassignMode = 'all';
        $this->reassignStatuses = ['pending_call', 'email_confirmed', 'callback_pending'];
    }

    public function reassignUntreatedLeads(LeadDistributionService $distributionService): void
    {
        $rules = [
            'reassignMode' => ['required', 'in:all,count'],
            'reassignStatuses' => ['required', 'array', 'min:1'],
            'reassignStatuses.*' => ['required', 'string', 'in:pending_call,email_confirmed,callback_pending'],
        ];

        if ($this->reassignMode === 'count') {
            $rules['reassignCount'] = ['required', 'integer', 'min:1'];
        }

        $this->validate($rules);

        if (empty($this->reassignStatuses)) {
            $this->addError('reassignStatuses', __('Veuillez sélectionner au moins un type de lead à réassigner.'));
            return;
        }

        $user = Auth::user();
        $fromAgent = User::findOrFail($this->reassignAgentId);

        // Vérifier que l'agent appartient au centre d'appels du owner
        if ($fromAgent->call_center_id !== $user->call_center_id) {
            $this->dispatch('error', message: __('Vous n\'êtes pas autorisé à réassigner les leads de cet agent.'));
            return;
        }

        $toAgent = $this->reassignToAgentId ? User::findOrFail($this->reassignToAgentId) : null;

        if ($toAgent && $toAgent->call_center_id !== $user->call_center_id) {
            $this->dispatch('error', message: __('L\'agent de destination doit appartenir à votre centre d\'appels.'));
            return;
        }

        $maxCount = $this->reassignMode === 'count' ? $this->reassignCount : null;
        
        try {
            // Get counts before reassignment for verification
            $fromAgentBeforeCount = Lead::where('assigned_to', $fromAgent->id)
                ->whereIn('status', $this->reassignStatuses)
                ->where('call_center_id', $user->call_center_id)
                ->count();

            $toAgentBeforeCount = $toAgent ? Lead::where('assigned_to', $toAgent->id)
                ->whereIn('status', $this->reassignStatuses)
                ->where('call_center_id', $user->call_center_id)
                ->count() : 0;

            $result = $distributionService->reassignUntreatedLeads($fromAgent, $toAgent, $user->call_center_id, $maxCount, $this->reassignStatuses);

            // Refresh agents to get updated counts
            $fromAgent->refresh();
            if ($toAgent) {
                $toAgent->refresh();
            }

            // Verify counts after reassignment
            $fromAgentAfterCount = Lead::where('assigned_to', $fromAgent->id)
                ->whereIn('status', $this->reassignStatuses)
                ->where('call_center_id', $user->call_center_id)
                ->count();

            $toAgentAfterCount = $toAgent ? Lead::where('assigned_to', $toAgent->id)
                ->whereIn('status', $this->reassignStatuses)
                ->where('call_center_id', $user->call_center_id)
                ->count() : 0;

            // Verify the counts are correct
            $expectedFromAgentAfter = $fromAgentBeforeCount - $result['reassigned'];
            $expectedToAgentAfter = $toAgentBeforeCount + $result['reassigned'];

            $countsValid = true;
            $verificationMessage = '';

            if ($fromAgentAfterCount > $expectedFromAgentAfter) {
                $countsValid = false;
                $verificationMessage .= __('Le nombre de leads de l\'agent source ne correspond pas. ');
            }

            if ($toAgent && $toAgentAfterCount < $expectedToAgentAfter) {
                $countsValid = false;
                $verificationMessage .= __('Le nombre de leads de l\'agent destination ne correspond pas. ');
            }

            if (! $countsValid) {
                \Log::warning('Lead count mismatch after reassignment', [
                    'from_agent_id' => $fromAgent->id,
                    'from_agent_before' => $fromAgentBeforeCount,
                    'from_agent_after' => $fromAgentAfterCount,
                    'expected_from_agent_after' => $expectedFromAgentAfter,
                    'to_agent_id' => $toAgent?->id,
                    'to_agent_before' => $toAgentBeforeCount,
                    'to_agent_after' => $toAgentAfterCount,
                    'expected_to_agent_after' => $expectedToAgentAfter,
                    'reassigned' => $result['reassigned'],
                ]);
            }

            $this->reassignResult = array_merge($result, [
                'verification' => [
                    'from_agent_before' => $fromAgentBeforeCount,
                    'from_agent_after' => $fromAgentAfterCount,
                    'to_agent_before' => $toAgentBeforeCount,
                    'to_agent_after' => $toAgentAfterCount,
                    'is_valid' => $countsValid,
                ],
            ]);

            if ($result['reassigned'] === 0 && $result['failed'] === 0 && $result['unassigned'] === 0) {
                $this->dispatch('warning', message: __('Aucun lead trouvé à réassigner avec les critères sélectionnés.'));
            } else {
                $message = __('Réassignation terminée: :reassigned réassignés, :failed échecs, :unassigned non assignés.', [
                    'reassigned' => $result['reassigned'],
                    'failed' => $result['failed'],
                    'unassigned' => $result['unassigned'],
                ]);

                if (! $countsValid) {
                    $message .= ' ' . $verificationMessage;
                    $this->dispatch('warning', message: $message);
                } elseif ($result['reassigned'] > 0) {
                    $this->dispatch('success', message: $message);
                    $this->dispatch('lead-assigned');
                } else {
                    $this->dispatch('warning', message: $message);
                }
            }

            // Force refresh of agents data to update counts
            $this->dispatch('$refresh');
            
            // Reset the reassign agent ID to force recalculation
            $this->reassignAgentId = null;
        } catch (\Exception $e) {
            \Log::error('Error in reassignUntreatedLeads method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('error', message: __('Erreur lors de la réassignation: :error', ['error' => $e->getMessage()]));
        }
    }

    public function toggleReassignStatus(string $status): void
    {
        if (in_array($status, $this->reassignStatuses)) {
            $this->reassignStatuses = array_values(array_diff($this->reassignStatuses, [$status]));
        } else {
            $this->reassignStatuses[] = $status;
        }
    }

    public function getReassignAgentProperty()
    {
        if (! $this->reassignAgentId) {
            return null;
        }

        return User::find($this->reassignAgentId);
    }

    public function getSelectedLeadsCountProperty(): int
    {
        if (empty($this->reassignStatuses) || ! $this->reassignAgentId) {
            return 0;
        }

        $user = Auth::user();
        $callCenterId = $user->call_center_id;

        $count = 0;
        if (in_array('pending_call', $this->reassignStatuses)) {
            $count += Lead::where('assigned_to', $this->reassignAgentId)
                ->where('status', 'pending_call')
                ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                ->count();
        }
        if (in_array('email_confirmed', $this->reassignStatuses)) {
            $count += Lead::where('assigned_to', $this->reassignAgentId)
                ->where('status', 'email_confirmed')
                ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                ->count();
        }
        if (in_array('callback_pending', $this->reassignStatuses)) {
            $count += Lead::where('assigned_to', $this->reassignAgentId)
                ->where('status', 'callback_pending')
                ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                ->count();
        }

        return $count;
    }

    public function getReassignAgentsProperty()
    {
        if (! $this->reassignAgentId) {
            return collect();
        }

        $user = Auth::user();

        if (! $user->relationLoaded('callCenter')) {
            $user->load('callCenter');
        }

        $callCenter = $user->callCenter;

        if (! $callCenter) {
            return collect();
        }

        return User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->where('id', '!=', $this->reassignAgentId)
            ->withCount([
                'assignedLeads as pending_leads_count' => function ($query) use ($callCenter) {
                    $query->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])
                        ->where('call_center_id', $callCenter->id);
                },
            ])
            ->orderBy('name')
            ->get();
    }

    public function getPendingCallCountProperty(): int
    {
        if (! $this->reassignAgentId) {
            return 0;
        }

        $user = Auth::user();
        $callCenterId = $user->call_center_id;

        return Lead::where('assigned_to', $this->reassignAgentId)
            ->where('status', 'pending_call')
            ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
            ->count();
    }

    public function getEmailConfirmedCountProperty(): int
    {
        if (! $this->reassignAgentId) {
            return 0;
        }

        $user = Auth::user();
        $callCenterId = $user->call_center_id;

        return Lead::where('assigned_to', $this->reassignAgentId)
            ->where('status', 'email_confirmed')
            ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
            ->count();
    }

    public function getCallbackPendingCountProperty(): int
    {
        if (! $this->reassignAgentId) {
            return 0;
        }

        $user = Auth::user();
        $callCenterId = $user->call_center_id;

        return Lead::where('assigned_to', $this->reassignAgentId)
            ->where('status', 'callback_pending')
            ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
            ->count();
    }

    public function getUntreatedCountProperty(): int
    {
        return $this->pendingCallCount + $this->emailConfirmedCount + $this->callbackPendingCount;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Section de réassignation des leads en attente -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="mb-6 flex items-start justify-between">
            <div class="flex-1">
                <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100">
                    {{ __('Réassignation des Leads en Attente') }}
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                    {{ __('Réassignez les leads non traités (en attente d\'appel, email confirmé, rappel programmé) d\'un agent à un autre agent ou via distribution automatique') }}
                </p>
            </div>
        </div>

        <div class="mb-6 flex items-center justify-between border-b border-neutral-200 pb-4 dark:border-neutral-700">
            <div class="text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Total d\'agents') }}: <span class="font-bold text-neutral-900 dark:text-neutral-100">{{ $this->agents->count() }}</span>
            </div>
            <flux:button wire:click="$refresh" variant="ghost" size="sm" class="text-blue-600 hover:text-blue-700 dark:text-blue-400">
                {{ __('Actualiser') }}
            </flux:button>
        </div>

        <!-- Cartes des agents -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($this->agents as $agent)
                @php
                    $pendingCallCount = Lead::where('assigned_to', $agent->id)
                        ->where('status', 'pending_call')
                        ->where('call_center_id', Auth::user()->call_center_id)
                        ->count();
                    $emailConfirmedCount = Lead::where('assigned_to', $agent->id)
                        ->where('status', 'email_confirmed')
                        ->where('call_center_id', Auth::user()->call_center_id)
                        ->count();
                    $callbackPendingCount = Lead::where('assigned_to', $agent->id)
                        ->where('status', 'callback_pending')
                        ->where('call_center_id', Auth::user()->call_center_id)
                        ->count();
                    $totalUntreated = $pendingCallCount + $emailConfirmedCount + $callbackPendingCount;
                @endphp
                @if ($totalUntreated > 0)
                    <div class="relative rounded-lg border-2 {{ $totalUntreated > 5 ? 'border-orange-500 bg-orange-900/30 dark:bg-orange-950/50' : 'border-orange-400 bg-neutral-800 dark:bg-neutral-900' }} p-5 shadow-lg transition-all hover:shadow-xl">
                        <!-- Bouton Réassigner en haut à droite -->
                        <div class="absolute right-4 top-4 z-10">
                            <button
                                type="button"
                                wire:click="openReassignModal({{ $agent->id }})"
                                class="rounded-md bg-white px-4 py-2 text-sm font-bold text-neutral-900 shadow-lg ring-2 ring-white/50 transition-all hover:bg-neutral-50 hover:shadow-xl hover:ring-white/80 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-orange-500 dark:bg-white dark:text-neutral-900"
                            >
                                {{ __('Réassigner') }}
                            </button>
                        </div>

                        <!-- Nom de l'agent -->
                        <div class="mb-4 pr-24">
                            <h3 class="text-xl font-bold text-white">
                                {{ $agent->name }}
                            </h3>
                        </div>

                        <!-- Liste des leads avec points colorés -->
                        <div class="mb-4 space-y-2.5">
                            @if ($pendingCallCount > 0)
                                <div class="flex items-center gap-3 text-sm text-white">
                                    <div class="h-3 w-3 rounded-full bg-orange-500"></div>
                                    <span>{{ $pendingCallCount }} {{ __('en attente d\'appel') }}</span>
                                </div>
                            @endif
                            @if ($emailConfirmedCount > 0)
                                <div class="flex items-center gap-3 text-sm text-white">
                                    <div class="h-3 w-3 rounded-full bg-blue-500"></div>
                                    <span>{{ $emailConfirmedCount }} {{ __('email confirmé') }}</span>
                                </div>
                            @endif
                            @if ($callbackPendingCount > 0)
                                <div class="flex items-center gap-3 text-sm text-white">
                                    <div class="h-3 w-3 rounded-full bg-yellow-500"></div>
                                    <span>{{ $callbackPendingCount }} {{ __('rappel programmé') }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Total des leads -->
                        <div class="border-t border-orange-400/30 pt-3">
                            <p class="text-sm font-medium text-white">
                                {{ __('Total') }}: <span class="font-bold">{{ $totalUntreated }}</span> {{ __('lead(s) non traité(s)') }}
                            </p>
                        </div>
                    </div>
                @endif
            @empty
                <div class="col-span-full rounded-lg border border-neutral-200 bg-white p-8 text-center dark:border-neutral-700 dark:bg-neutral-800">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Aucun agent trouvé') }}
                    </p>
                </div>
            @endforelse
        </div>

        @if ($this->agents->filter(fn($agent) => 
            Lead::where('assigned_to', $agent->id)
                ->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])
                ->where('call_center_id', Auth::user()->call_center_id)
                ->count() === 0
        )->count() > 0)
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-700 dark:bg-green-900/20">
                <p class="text-sm text-green-700 dark:text-green-300">
                    {{ __('Tous les agents n\'ayant pas de leads en attente ne sont pas affichés.') }}
                </p>
            </div>
        @endif
    </div>

    <!-- Modal de réassignation -->
    <flux:modal wire:model="showReassignModal" name="reassign-leads">
        @if ($this->reassignAgent && !$reassignResult)
            <form wire:submit.prevent="reassignUntreatedLeads" class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Réassigner les leads') }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Réassigner les leads non traités de :agent à un autre agent ou distribution automatique.', ['agent' => $this->reassignAgent->name]) }}
                    </p>
                </div>

                <!-- Informations de l'agent -->
                <div class="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-700 dark:bg-orange-900/20">
                    <p class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Agent') }}: <strong>{{ $this->reassignAgent->name }}</strong>
                    </p>
                    <div class="space-y-1 text-xs">
                        @if ($this->pendingCallCount > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-700 dark:text-neutral-300">{{ __('En attente d\'appel') }}:</span>
                                <span class="font-semibold text-orange-600 dark:text-orange-400">{{ $this->pendingCallCount }}</span>
                            </div>
                        @endif
                        @if ($this->emailConfirmedCount > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Email confirmé') }}:</span>
                                <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $this->emailConfirmedCount }}</span>
                            </div>
                        @endif
                        @if ($this->callbackPendingCount > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Rappel programmé') }}:</span>
                                <span class="font-semibold text-yellow-600 dark:text-yellow-400">{{ $this->callbackPendingCount }}</span>
                            </div>
                        @endif
                        <div class="mt-2 border-t border-neutral-200 pt-1 dark:border-neutral-700">
                            <div class="flex items-center justify-between font-semibold">
                                <span class="text-neutral-900 dark:text-neutral-100">{{ __('Total disponible') }}:</span>
                                <span class="text-orange-600 dark:text-orange-400">{{ $this->untreatedCount }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Choix des types de leads à réassigner -->
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                        {{ __('Types de leads à réassigner') }}
                    </label>
                    <div class="space-y-2 rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-900">
                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded p-2 transition-colors hover:bg-white dark:hover:bg-neutral-800">
                            <div class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    wire:model="reassignStatuses"
                                    value="pending_call"
                                    class="h-4 w-4 rounded border-neutral-300 text-orange-600 focus:ring-orange-500"
                                />
                                <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                    {{ __('En attente d\'appel') }}
                                </span>
                            </div>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $this->pendingCallCount }} {{ __('lead(s)') }}
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded p-2 transition-colors hover:bg-white dark:hover:bg-neutral-800">
                            <div class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    wire:model="reassignStatuses"
                                    value="email_confirmed"
                                    class="h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                    {{ __('Email confirmé') }}
                                </span>
                            </div>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $this->emailConfirmedCount }} {{ __('lead(s)') }}
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded p-2 transition-colors hover:bg-white dark:hover:bg-neutral-800">
                            <div class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    wire:model="reassignStatuses"
                                    value="callback_pending"
                                    class="h-4 w-4 rounded border-neutral-300 text-yellow-600 focus:ring-yellow-500"
                                />
                                <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                    {{ __('Rappel programmé') }}
                                </span>
                            </div>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $this->callbackPendingCount }} {{ __('lead(s)') }}
                            </span>
                        </label>
                    </div>
                    @if (!empty($this->reassignStatuses))
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-2 dark:border-blue-700 dark:bg-blue-900/20">
                            <p class="text-xs text-blue-700 dark:text-blue-300">
                                <strong>{{ $this->selectedLeadsCount }}</strong> {{ __('lead(s) sélectionné(s)') }} sur {{ $this->untreatedCount }} {{ __('disponible(s)') }}
                            </p>
                        </div>
                    @endif
                    @error('reassignStatuses')
                        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Choix du nombre de leads à réassigner -->
                <div class="space-y-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                            {{ __('Nombre de leads à réassigner') }}
                        </label>
                        <div class="space-y-3">
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-neutral-200 p-3 transition-colors hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-900 {{ $reassignMode === 'all' ? 'border-blue-500 bg-blue-50 dark:border-blue-500 dark:bg-blue-900/20' : '' }}">
                                <input
                                    type="radio"
                                    wire:model.live="reassignMode"
                                    value="all"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="flex-1 text-sm text-neutral-700 dark:text-neutral-300">
                                    {{ __('Tous les leads sélectionnés') }}
                                    <span class="ml-2 text-neutral-500 dark:text-neutral-400">
                                        ({{ $this->selectedLeadsCount }} {{ __('lead(s)') }})
                                    </span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-neutral-200 p-3 transition-colors hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-900 {{ $reassignMode === 'count' ? 'border-blue-500 bg-blue-50 dark:border-blue-500 dark:bg-blue-900/20' : '' }}">
                                <input
                                    type="radio"
                                    wire:model.live="reassignMode"
                                    value="count"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="flex-1 text-sm text-neutral-700 dark:text-neutral-300">
                                    {{ __('Un nombre spécifique') }}
                                </span>
                            </label>
                        </div>
                    </div>

                    @if ($reassignMode === 'count')
                        <div>
                            <flux:input
                                type="number"
                                wire:model="reassignCount"
                                :label="__('Nombre de leads')"
                                min="1"
                                :max="$this->selectedLeadsCount"
                                required
                            />
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                {{ __('Maximum disponible selon sélection') }}: <strong>{{ $this->selectedLeadsCount }}</strong> {{ __('lead(s)') }}
                            </p>
                        </div>
                    @endif
                </div>

                <flux:select wire:model="reassignToAgentId" :label="__('Réassigner à (optionnel)')">
                    <option value="">{{ __('Distribution automatique') }}</option>
                    @foreach ($this->reassignAgents as $agentOption)
                        <option value="{{ $agentOption->id }}">
                            {{ $agentOption->name }} ({{ $agentOption->email }}) - {{ $agentOption->pending_leads_count }} {{ __('en attente') }}
                        </option>
                    @endforeach
                </flux:select>

                <!-- Aperçu de la charge de travail -->
                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                    <h3 class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Charge de travail des agents') }}</h3>
                    <div class="space-y-2">
                        @foreach ($this->reassignAgents as $agent)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-neutral-700 dark:text-neutral-300">{{ $agent->name }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ $agent->pending_leads_count }} {{ __('en attente') }}
                                    </span>
                                    @if ($agent->pending_leads_count > 0)
                                        <div class="h-2 w-16 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                                            @php
                                                $maxPending = $this->reassignAgents->max('pending_leads_count') ?? 1;
                                                $percentage = ($agent->pending_leads_count / $maxPending) * 100;
                                            @endphp
                                            <div
                                                class="h-full rounded-full {{ $percentage >= 80 ? 'bg-red-500' : ($percentage >= 50 ? 'bg-yellow-500' : 'bg-green-500') }}"
                                                style="width: {{ min($percentage, 100) }}%"
                                            ></div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-700">
                    <flux:button type="button" wire:click="closeReassignModal" variant="ghost">
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ __('Réassigner') }}</span>
                        <span wire:loading>{{ __('Traitement en cours...') }}</span>
                    </flux:button>
                </div>
            </form>
        @elseif ($reassignResult)
            <!-- Résultats de la réassignation -->
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Résultats de la réassignation') }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('La réassignation a été effectuée avec succès.') }}
                    </p>
                </div>

                <div class="rounded-lg border {{ $reassignResult['reassigned'] > 0 ? 'border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/20' : 'border-yellow-200 bg-yellow-50 dark:border-yellow-700 dark:bg-yellow-900/20' }} p-4">
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-neutral-700 dark:text-neutral-300">{{ __('Réassignés') }}:</span>
                            <span class="font-semibold text-green-600 dark:text-green-400">{{ $reassignResult['reassigned'] }}</span>
                        </div>
                        @if ($reassignResult['failed'] > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-700 dark:text-neutral-300">{{ __('Échecs') }}:</span>
                                <span class="font-semibold text-red-600 dark:text-red-400">{{ $reassignResult['failed'] }}</span>
                            </div>
                        @endif
                        @if ($reassignResult['unassigned'] > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-700 dark:text-neutral-300">{{ __('Non assignés') }}:</span>
                                <span class="font-semibold text-yellow-600 dark:text-yellow-400">{{ $reassignResult['unassigned'] }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                @if (isset($reassignResult['verification']))
                    @php
                        $verification = $reassignResult['verification'];
                    @endphp
                    <div class="rounded-lg border {{ $verification['is_valid'] ? 'border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/20' : 'border-red-200 bg-red-50 dark:border-red-700 dark:bg-red-900/20' }} p-4">
                        <h4 class="mb-2 text-sm font-semibold {{ $verification['is_valid'] ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100' }}">
                            {{ $verification['is_valid'] ? __('✓ Vérification réussie') : __('⚠ Vérification échouée') }}
                        </h4>
                        <div class="space-y-1 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-700 dark:text-neutral-300">{{ __('Agent source - Avant') }}:</span>
                                <span class="font-semibold">{{ $verification['from_agent_before'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-700 dark:text-neutral-300">{{ __('Agent source - Après') }}:</span>
                                <span class="font-semibold">{{ $verification['from_agent_after'] }}</span>
                            </div>
                            @if ($verification['to_agent_before'] > 0 || $verification['to_agent_after'] > 0)
                                <div class="flex items-center justify-between">
                                    <span class="text-neutral-700 dark:text-neutral-300">{{ __('Agent destination - Avant') }}:</span>
                                    <span class="font-semibold">{{ $verification['to_agent_before'] }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-neutral-700 dark:text-neutral-300">{{ __('Agent destination - Après') }}:</span>
                                    <span class="font-semibold">{{ $verification['to_agent_after'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-700">
                    <flux:button type="button" wire:click="closeReassignModal" variant="primary">
                        {{ __('Fermer') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>

@script
<script>
    $wire.on('success', (event) => {
        $dispatch('notify', {
            message: event.message || 'Opération réussie.',
            type: 'success'
        });
    });

    $wire.on('error', (event) => {
        $dispatch('notify', {
            message: event.message || 'Une erreur est survenue.',
            type: 'danger'
        });
    });

    $wire.on('warning', (event) => {
        $dispatch('notify', {
            message: event.message || 'Attention.',
            type: 'warning'
        });
    });

    $wire.on('lead-assigned', (event) => {
        $dispatch('notify', {
            message: event.message || 'Réassignation terminée.',
            type: 'success'
        });
        
        // Refresh the page data to update lead counts
        $wire.$refresh();
    });
</script>
@endscript
