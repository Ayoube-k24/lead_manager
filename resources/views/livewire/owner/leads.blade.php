<?php

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadDistributionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public ?int $selectedLeadId = null;
    public ?int $selectedAgentId = null;
    public bool $showAssignModal = false;

    public function mount(): void
    {
        //
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function getLeadsProperty()
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if (! $callCenter) {
            return collect();
        }

        return Lead::where('call_center_id', $callCenter->id)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('email', 'like', '%'.$this->search.'%')
                        ->orWhereJsonContains('data->name', $this->search);
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->with(['form', 'assignedAgent'])
            ->latest()
            ->paginate(15);
    }

    public function getAgentsProperty()
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if (! $callCenter) {
            return collect();
        }

        return User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->withCount([
                'assignedLeads as pending_leads_count' => function ($query) {
                    $query->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending']);
                },
                'assignedLeads as total_leads_count',
            ])
            ->orderBy('pending_leads_count')
            ->orderBy('name')
            ->get();
    }

    public function openAssignModal(int $leadId): void
    {
        $this->selectedLeadId = $leadId;
        $this->selectedAgentId = null;
        $this->showAssignModal = true;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->selectedLeadId = null;
        $this->selectedAgentId = null;
    }

    public function assignLead(LeadDistributionService $distributionService): void
    {
        $this->validate([
            'selectedLeadId' => ['required', 'exists:leads,id'],
            'selectedAgentId' => ['required', 'exists:users,id'],
        ]);

        $lead = Lead::findOrFail($this->selectedLeadId);
        $agent = User::findOrFail($this->selectedAgentId);

        // Vérifier que le lead et l'agent appartiennent au même centre d'appels
        $user = Auth::user();
        if ($lead->call_center_id !== $user->call_center_id || $agent->call_center_id !== $user->call_center_id) {
            $this->addError('assignment', 'Le lead et l\'agent doivent appartenir au même centre d\'appels.');
            return;
        }

        if ($distributionService->assignToAgent($lead, $agent)) {
            if ($lead->status === 'email_confirmed') {
                $lead->markAsPendingCall();
            }
            $this->closeAssignModal();
            $this->dispatch('lead-assigned');
        } else {
            $this->addError('assignment', 'Impossible d\'assigner le lead à cet agent.');
        }
    }

    public function autoAssign(LeadDistributionService $distributionService, int $leadId): void
    {
        $lead = Lead::findOrFail($leadId);
        $user = Auth::user();

        if ($lead->call_center_id !== $user->call_center_id) {
            return;
        }

        $agent = $distributionService->distributeLead($lead);
        if ($agent) {
            $distributionService->assignToAgent($lead, $agent);
            if ($lead->status === 'email_confirmed') {
                $lead->markAsPendingCall();
            }
            $this->dispatch('lead-assigned');
        }
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Gestion des Leads') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Attribuez les leads aux agents de votre centre d\'appels') }}
            </p>
        </div>
    </div>

    <!-- Filtres -->
    <div class="flex flex-col gap-4 sm:flex-row">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Rechercher')"
            placeholder="{{ __('Email, nom...') }}"
            class="flex-1"
        />
        <flux:select wire:model.live="statusFilter" :label="__('Statut')" class="sm:w-48">
            <option value="">{{ __('Tous les statuts') }}</option>
            <option value="pending_email">{{ __('En attente email') }}</option>
            <option value="email_confirmed">{{ __('Email confirmé') }}</option>
            <option value="pending_call">{{ __('En attente d\'appel') }}</option>
            <option value="confirmed">{{ __('Confirmé') }}</option>
            <option value="rejected">{{ __('Rejeté') }}</option>
        </flux:select>
    </div>

    <!-- Liste des leads -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Email') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Formulaire') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Statut') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Agent') }}
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-neutral-800">
                    @forelse ($this->leads as $lead)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">
                                {{ $lead->email }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->form?->name ?? 'N/A' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @php
                                    $statusLabels = [
                                        'pending_email' => __('En attente email'),
                                        'email_confirmed' => __('Email confirmé'),
                                        'pending_call' => __('En attente d\'appel'),
                                        'confirmed' => __('Confirmé'),
                                        'rejected' => __('Rejeté'),
                                    ];
                                    $statusColors = [
                                        'pending_email' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
                                        'email_confirmed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
                                        'pending_call' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400',
                                        'confirmed' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                                        'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                                    ];
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusColors[$lead->status] ?? 'bg-neutral-100 text-neutral-800 dark:bg-neutral-900/20 dark:text-neutral-400' }}">
                                    {{ $statusLabels[$lead->status] ?? $lead->status }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->assignedAgent?->name ?? __('Non assigné') }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                @if (! $lead->assigned_to && in_array($lead->status, ['email_confirmed', 'pending_call']))
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button
                                            wire:click="autoAssign({{ $lead->id }})"
                                            variant="ghost"
                                            size="sm"
                                        >
                                            {{ __('Auto') }}
                                        </flux:button>
                                        <flux:button
                                            wire:click="openAssignModal({{ $lead->id }})"
                                            variant="primary"
                                            size="sm"
                                        >
                                            {{ __('Assigner') }}
                                        </flux:button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Aucun lead trouvé') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->leads->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                {{ $this->leads->links() }}
            </div>
        @endif
    </div>

    <!-- Modal d'attribution -->
    <flux:modal wire:model="showAssignModal" name="assign-lead">
        <form wire:submit="assignLead" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Attribuer un lead') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Sélectionnez un agent pour attribuer ce lead.') }}
                </p>
            </div>

            <flux:select wire:model="selectedAgentId" :label="__('Agent')" required>
                <option value="">{{ __('Sélectionner un agent') }}</option>
                @foreach ($this->agents as $agent)
                    <option value="{{ $agent->id }}">
                        {{ $agent->name }} ({{ $agent->email }}) - {{ $agent->pending_leads_count }} {{ __('en attente') }}
                    </option>
                @endforeach
            </flux:select>

            <!-- Aperçu de la charge de travail -->
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                <h3 class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Charge de travail des agents') }}</h3>
                <div class="space-y-2">
                    @foreach ($this->agents as $agent)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-neutral-700 dark:text-neutral-300">{{ $agent->name }}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $agent->pending_leads_count }} {{ __('en attente') }} / {{ $agent->total_leads_count }} {{ __('total') }}
                                </span>
                                @if ($agent->pending_leads_count > 0)
                                    <div class="h-2 w-16 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                                        @php
                                            $maxPending = $this->agents->max('pending_leads_count') ?? 1;
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

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeAssignModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Attribuer') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

