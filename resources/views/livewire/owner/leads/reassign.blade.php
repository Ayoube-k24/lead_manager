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
            $result = $distributionService->reassignUntreatedLeads($fromAgent, $toAgent, $user->call_center_id, $maxCount, $this->reassignStatuses);

            $this->reassignResult = $result;

            if ($result['reassigned'] === 0 && $result['failed'] === 0 && $result['unassigned'] === 0) {
                $this->dispatch('warning', message: __('Aucun lead trouvé à réassigner avec les critères sélectionnés.'));
            } else {
                $message = __('Réassignation terminée: :reassigned réassignés, :failed échecs, :unassigned non assignés.', [
                    'reassigned' => $result['reassigned'],
                    'failed' => $result['failed'],
                    'unassigned' => $result['unassigned'],
                ]);

                if ($result['reassigned'] > 0) {
                    $this->dispatch('success', message: $message);
                    $this->dispatch('lead-assigned');
                } else {
                    $this->dispatch('warning', message: $message);
                }
            }
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
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Réassignation de Leads') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Réassigner les leads non traités entre les agents de votre centre d\'appels') }}
            </p>
        </div>
    </div>

    <!-- Liste des agents avec leurs leads en attente -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Agent') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('En attente d\'appel') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Email confirmé') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Rappel programmé') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Total non traité') }}
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-neutral-800">
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
                        <tr>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ $agent->name }}
                                    </span>
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ $agent->email }}
                                    </span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400">
                                    {{ $pendingCallCount }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
                                    {{ $emailConfirmedCount }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400">
                                    {{ $callbackPendingCount }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $totalUntreated > 0 ? 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400' : 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' }}">
                                    {{ $totalUntreated }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                @if ($totalUntreated > 0)
                                    <flux:button
                                        wire:click="openReassignModal({{ $agent->id }})"
                                        variant="primary"
                                        size="sm"
                                    >
                                        {{ __('Réassigner') }}
                                    </flux:button>
                                @else
                                    <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ __('Aucun lead à réassigner') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Aucun agent trouvé') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
    });
</script>
@endscript
