<?php

use App\Models\Lead;
use App\Models\Tag;
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

    public array $tagsFilter = [];

    public string $tagsMode = 'any';

    public ?string $sourceFilter = null;

    public string $activeTab = 'form'; // 'form' or 'import'

    public ?int $selectedLeadId = null;

    public ?int $selectedAgentId = null;

    public bool $showAssignModal = false;

    public ?int $reassignAgentId = null;

    public ?int $reassignToAgentId = null;

    public bool $showReassignModal = false;

    public ?int $reassignCount = null;

    public string $reassignMode = 'all'; // 'all' or 'count'

    public array $reassignStatuses = ['pending_call', 'email_confirmed', 'callback_pending'];

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

    public function updatingSourceFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTagsFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTagsMode(): void
    {
        $this->resetPage();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function getLeadsProperty()
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

        // Filtrer par source selon l'onglet actif
        $source = $this->activeTab === 'form' ? 'form' : 'import';

        $query = Lead::where('call_center_id', $callCenter->id)
            ->where('source', $source)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('email', 'like', '%'.$this->search.'%')
                        ->orWhereJsonContains('data->name', $this->search);
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when(! empty($this->tagsFilter), function ($query) {
                $tagIds = array_filter(array_map('intval', $this->tagsFilter));
                if (! empty($tagIds)) {
                    if ($this->tagsMode === 'all') {
                        foreach ($tagIds as $tagId) {
                            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
                        }
                    } else {
                        $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
                    }
                }
            })
            ->with(['form', 'assignedAgent', 'tags']);

        return $query->latest()->paginate(15);
    }

    public function getStatsProperty(): array
    {
        $user = Auth::user();

        // Ensure callCenter relationship is loaded
        if (! $user->relationLoaded('callCenter')) {
            $user->load('callCenter');
        }

        $callCenter = $user->callCenter;

        if (! $callCenter) {
            return [
                'form' => ['total' => 0, 'confirmed' => 0, 'rejected' => 0, 'pending' => 0],
                'import' => ['total' => 0, 'confirmed' => 0, 'rejected' => 0, 'pending' => 0],
            ];
        }

        $formLeads = Lead::where('call_center_id', $callCenter->id)->where('source', 'form')->get();
        $seoLeads = Lead::where('call_center_id', $callCenter->id)->where('source', 'import')->get();

        return [
            'form' => [
                'total' => $formLeads->count(),
                'confirmed' => $formLeads->where('status', 'confirmed')->count(),
                'rejected' => $formLeads->where('status', 'rejected')->count(),
                'pending' => $formLeads->whereIn('status', ['pending_email', 'email_confirmed', 'pending_call', 'callback_pending'])->count(),
            ],
            'import' => [
                'total' => $seoLeads->count(),
                'confirmed' => $seoLeads->where('status', 'confirmed')->count(),
                'rejected' => $seoLeads->where('status', 'rejected')->count(),
                'pending' => $seoLeads->whereIn('status', ['pending_email', 'email_confirmed', 'pending_call', 'callback_pending'])->count(),
            ],
        ];
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

    public function getCallCenterProperty()
    {
        $user = Auth::user();

        // Ensure callCenter relationship is loaded
        if (! $user->relationLoaded('callCenter')) {
            $user->load('callCenter');
        }

        return $user->callCenter;
    }

    public function getTagsProperty()
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if (! $callCenter) {
            return collect();
        }

        return Tag::whereHas('leads', fn ($q) => $q->where('call_center_id', $callCenter->id))
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

    public function reassignUntreatedLeads(LeadDistributionService $distributionService): void
    {
        $rules = [
            'reassignAgentId' => ['required', 'exists:users,id'],
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
            $this->closeReassignModal();
            return;
        }

        $toAgent = $this->reassignToAgentId ? User::findOrFail($this->reassignToAgentId) : null;

        if ($toAgent && $toAgent->call_center_id !== $user->call_center_id) {
            $this->dispatch('error', message: __('L\'agent de destination doit appartenir à votre centre d\'appels.'));
            $this->closeReassignModal();
            return;
        }

        $maxCount = $this->reassignMode === 'count' ? $this->reassignCount : null;
        
        try {
            $result = $distributionService->reassignUntreatedLeads($fromAgent, $toAgent, $user->call_center_id, $maxCount, $this->reassignStatuses);

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

        $this->closeReassignModal();
        $this->resetPage();
    }

    public function openReassignModal(int $agentId): void
    {
        $this->reassignAgentId = $agentId;
        $this->reassignToAgentId = null;
        $this->reassignCount = null;
        $this->reassignMode = 'all';
        $this->reassignStatuses = ['pending_call', 'email_confirmed', 'callback_pending'];
        $this->showReassignModal = true;
    }

    public function closeReassignModal(): void
    {
        $this->showReassignModal = false;
        $this->reassignAgentId = null;
        $this->reassignToAgentId = null;
        $this->reassignCount = null;
        $this->reassignMode = 'all';
        $this->reassignStatuses = ['pending_call', 'email_confirmed', 'callback_pending'];
    }

    public function toggleReassignStatus(string $status): void
    {
        if (in_array($status, $this->reassignStatuses)) {
            $this->reassignStatuses = array_values(array_diff($this->reassignStatuses, [$status]));
        } else {
            $this->reassignStatuses[] = $status;
        }
    }

    public function getSelectedLeadsCountProperty(): int
    {
        if (! $this->reassignAgentId || empty($this->reassignStatuses)) {
            return 0;
        }

        $user = Auth::user();
        $callCenterId = $user->call_center_id;

        $count = 0;
        if (in_array('pending_call', $this->reassignStatuses)) {
            $count += \App\Models\Lead::where('assigned_to', $this->reassignAgentId)
                ->where('status', 'pending_call')
                ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                ->count();
        }
        if (in_array('email_confirmed', $this->reassignStatuses)) {
            $count += \App\Models\Lead::where('assigned_to', $this->reassignAgentId)
                ->where('status', 'email_confirmed')
                ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                ->count();
        }
        if (in_array('callback_pending', $this->reassignStatuses)) {
            $count += \App\Models\Lead::where('assigned_to', $this->reassignAgentId)
                ->where('status', 'callback_pending')
                ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                ->count();
        }

        return $count;
    }

    public function toggleTag(int $tagId): void
    {
        $tagId = (int) $tagId;
        if (in_array($tagId, $this->tagsFilter)) {
            $this->tagsFilter = array_values(array_diff($this->tagsFilter, [$tagId]));
        } else {
            $this->tagsFilter = array_merge($this->tagsFilter, [$tagId]);
        }
        $this->resetPage();
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

    <!-- Onglets de source -->
    <div class="border-b border-neutral-200 dark:border-neutral-700">
        <nav class="-mb-px flex space-x-8">
            <button
                wire:click="switchTab('form')"
                type="button"
                class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium transition-colors {{ $activeTab === 'form' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
            >
                {{ __('Leads Formulaire') }}
                <span class="ml-2 rounded-full bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-800">
                    {{ $this->stats['form']['total'] }}
                </span>
            </button>
            <button
                wire:click="switchTab('import')"
                type="button"
                class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium transition-colors {{ $activeTab === 'import' ? 'border-green-500 text-green-600 dark:text-green-400' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
            >
                {{ __('Import') }}
                <span class="ml-2 rounded-full bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-800">
                    {{ $this->stats['import']['total'] }}
                </span>
            </button>
        </nav>
    </div>

    <!-- Statistiques par source -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        @php
            $currentStats = $this->stats[$activeTab];
        @endphp
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('Total') }}</div>
            <div class="mt-1 text-xl font-bold text-neutral-900 dark:text-neutral-100">{{ $currentStats['total'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('Confirmés') }}</div>
            <div class="mt-1 text-xl font-bold text-green-600 dark:text-green-400">{{ $currentStats['confirmed'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('Rejetés') }}</div>
            <div class="mt-1 text-xl font-bold text-red-600 dark:text-red-400">{{ $currentStats['rejected'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('En attente') }}</div>
            <div class="mt-1 text-xl font-bold text-orange-600 dark:text-orange-400">{{ $currentStats['pending'] }}</div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="flex flex-col gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Rechercher')"
            placeholder="{{ __('Email, nom...') }}"
            class="w-full"
        />
        
        <!-- Nuage de tags pour les statuts -->
        <div>
            <label class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                {{ __('Filtrer par statut') }}
            </label>
            <div class="flex flex-wrap gap-2">
                <button
                    wire:click="$set('statusFilter', '')"
                    type="button"
                    class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-all {{ empty($statusFilter) ? 'bg-blue-600 text-white shadow-md ring-2 ring-blue-500 ring-offset-2' : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700' }}"
                >
                    {{ __('Tous') }}
                </button>
                @foreach (\App\Models\LeadStatus::allStatuses() as $status)
                    @php
                        $isActive = $statusFilter === $status->slug;
                    @endphp
                    <button
                        wire:click="$set('statusFilter', '{{ $status->slug }}')"
                        type="button"
                        class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-all {{ $isActive ? 'shadow-md ring-2 ring-offset-2 ' . str_replace('bg-', 'ring-', explode(' ', $status->getColorClass())[0]) . ' ' . $status->getColorClass() : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700' }}"
                    >
                        {{ $status->getLabel() }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Filtres par tags -->
        @if ($this->tags->count() > 0)
            <div>
                <label class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    {{ __('Filtrer par tags') }}
                </label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->tags as $tag)
                        @php
                            $isSelected = in_array($tag->id, $tagsFilter);
                        @endphp
                        <button
                            wire:click="toggleTag({{ $tag->id }})"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-all {{ $isSelected ? 'ring-2 ring-offset-2' : '' }}"
                            style="{{ $isSelected ? 'background-color: ' . $tag->color . '20; color: ' . $tag->color . '; ring-color: ' . $tag->color : 'background-color: #f3f4f6; color: #6b7280' }}"
                        >
                            <div class="h-2 w-2 rounded-full" style="background-color: {{ $tag->color }};"></div>
                            {{ $tag->name }}
                        </button>
                    @endforeach
                </div>
                @if (!empty($tagsFilter))
                    <div class="mt-2">
                        <flux:select wire:model.live="tagsMode" size="sm">
                            <option value="any">{{ __('Leads avec n\'importe lequel des tags sélectionnés') }}</option>
                            <option value="all">{{ __('Leads avec tous les tags sélectionnés') }}</option>
                        </flux:select>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Section de réassignation rapide -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Réassignation des Leads en Attente') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Réassignez les leads non traités (en attente d\'appel, email confirmé, rappel programmé) d\'un agent à un autre agent ou via distribution automatique') }}
                </p>
            </div>
        </div>

        <!-- Liste des agents avec leurs leads en attente -->
        <div class="mt-4">
            <div class="mb-3 flex items-center justify-between">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Total d\'agents') }}: <strong>{{ $this->agents->count() }}</strong>
                </p>
                <button
                    type="button"
                    wire:click="$refresh"
                    class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400"
                >
                    {{ __('Actualiser') }}
                </button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->agents as $agent)
                @php
                    $user = Auth::user();
                    $callCenterId = $user->call_center_id;
                    $untreatedStatuses = config('lead-rules.reassignment.untreated_statuses', ['pending_call', 'email_confirmed', 'callback_pending']);
                    $pendingCallCount = \App\Models\Lead::where('assigned_to', $agent->id)
                        ->where('status', 'pending_call')
                        ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                        ->count();
                    $emailConfirmedCount = \App\Models\Lead::where('assigned_to', $agent->id)
                        ->where('status', 'email_confirmed')
                        ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                        ->count();
                    $callbackPendingCount = \App\Models\Lead::where('assigned_to', $agent->id)
                        ->where('status', 'callback_pending')
                        ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                        ->count();
                    $untreatedCount = $pendingCallCount + $emailConfirmedCount + $callbackPendingCount;
                @endphp
                @if ($untreatedCount > 0)
                    <div class="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-700 dark:bg-orange-900/20">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                    {{ $agent->name }}
                                </h3>
                                <div class="mt-2 space-y-1">
                                    @if ($pendingCallCount > 0)
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="inline-flex h-2 w-2 rounded-full bg-orange-500"></span>
                                            <span class="text-neutral-700 dark:text-neutral-300">
                                                <strong>{{ $pendingCallCount }}</strong> {{ __('en attente d\'appel') }}
                                            </span>
                                        </div>
                                    @endif
                                    @if ($emailConfirmedCount > 0)
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="inline-flex h-2 w-2 rounded-full bg-blue-500"></span>
                                            <span class="text-neutral-600 dark:text-neutral-400">
                                                {{ $emailConfirmedCount }} {{ __('email confirmé') }}
                                            </span>
                                        </div>
                                    @endif
                                    @if ($callbackPendingCount > 0)
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="inline-flex h-2 w-2 rounded-full bg-yellow-500"></span>
                                            <span class="text-neutral-600 dark:text-neutral-400">
                                                {{ $callbackPendingCount }} {{ __('rappel programmé') }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                                <p class="mt-2 text-xs font-medium text-neutral-700 dark:text-neutral-300">
                                    {{ __('Total') }}: <strong>{{ $untreatedCount }}</strong> {{ __('lead(s) non traité(s)') }}
                                </p>
                            </div>
                            <flux:button
                                type="button"
                                wire:click="openReassignModal({{ $agent->id }})"
                                variant="primary"
                                size="sm"
                                class="ml-3"
                            >
                                {{ __('Réassigner') }}
                            </flux:button>
                        </div>
                    </div>
                @endif
                @endforeach
            </div>
            
            <!-- Debug: Afficher tous les agents même sans leads -->
            @if ($this->agents->filter(fn($agent) => $agent->pending_leads_count == 0)->isNotEmpty())
                <div class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="mb-2 text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                        {{ __('Agents sans leads en attente') }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->agents->filter(fn($agent) => $agent->pending_leads_count == 0) as $agent)
                            <span class="inline-flex items-center gap-1 rounded-full bg-neutral-200 px-2 py-1 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                                {{ $agent->name }}
                                <span class="text-neutral-400">(0)</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @php
            $user = Auth::user();
            $callCenterId = $user->call_center_id;
            $agentsWithLeads = $this->agents->filter(fn($agent) => \App\Models\Lead::where('assigned_to', $agent->id)
                ->whereIn('status', config('lead-rules.reassignment.untreated_statuses', ['pending_call', 'email_confirmed', 'callback_pending']))
                ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                ->count() > 0);
        @endphp
        @if ($agentsWithLeads->isEmpty())
            <div class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-4 text-center dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Aucun agent avec des leads non traités pour le moment.') }}
                </p>
                @if ($this->agents->isNotEmpty())
                    <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                        {{ __('Agents disponibles') }}: {{ $this->agents->pluck('name')->join(', ') }}
                    </p>
                @endif
            </div>
        @endif
    </div>

    <!-- Liste des leads -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Lead') }}
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
                            <td class="px-6 py-4">
                                @php
                                    $phone = $lead->phone ?? data_get($lead->data, 'phone');
                                    $fullName = $lead->data['name']
                                        ?? trim(($lead->data['first_name'] ?? '') . ' ' . ($lead->data['last_name'] ?? ''));
                                    $fullName = $fullName ?: $lead->email;
                                @endphp
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                                        #{{ $lead->id }}
                                    </span>
                                    <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                        {{ $fullName }}
                                    </span>
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ $lead->email }}
                                    </span>
                                    @if ($phone)
                                        <span class="text-xs text-neutral-500 dark:text-neutral-400 flex items-center gap-1">
                                            📞 <span>{{ $phone }}</span>
                                        </span>
                                    @endif
                                </div>
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
                                    @php
                                        $callCenter = $this->callCenter;
                                        $isManualMode = $callCenter && $callCenter->distribution_method === 'manual';
                                    @endphp
                                    <div class="flex items-center justify-end gap-2">
                                        @if (! $isManualMode)
                                            {{-- Mode automatique : afficher le bouton Auto pour forcer la distribution --}}
                                            <flux:button
                                                wire:click="autoAssign({{ $lead->id }})"
                                                variant="ghost"
                                                size="sm"
                                            >
                                                {{ __('Auto') }}
                                            </flux:button>
                                        @endif
                                        {{-- Toujours afficher le bouton Assigner pour assignation manuelle --}}
                                        <flux:button
                                            wire:click="openAssignModal({{ $lead->id }})"
                                            variant="primary"
                                            size="sm"
                                        >
                                            {{ __('Assigner') }}
                                        </flux:button>
                                    </div>
                                @else
                                    <div class="flex items-center justify-end gap-2">
                                        @if (in_array($lead->status, ['pending_call', 'email_confirmed', 'callback_pending']))
                                            <flux:button
                                                wire:click="openAssignModal({{ $lead->id }})"
                                                variant="ghost"
                                                size="sm"
                                            >
                                                {{ __('Réassigner') }}
                                            </flux:button>
                                        @endif
                                        <flux:button href="{{ route('owner.leads.show', $lead) }}" variant="ghost" size="sm" wire:navigate>
                                            {{ __('Voir') }}
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
                                    <flux:button
                                        type="button"
                                        wire:click="openReassignModal({{ $agent->id }})"
                                        variant="ghost"
                                        size="sm"
                                        class="text-xs"
                                    >
                                        {{ __('Réassigner') }}
                                    </flux:button>
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

    <!-- Modal de réassignation en masse -->
    <flux:modal name="reassign-modal" :show="$showReassignModal" wire:model="showReassignModal">
        <form wire:submit.prevent="reassignUntreatedLeads" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Réassigner les leads non traités') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Réassigner tous les leads non traités (en attente d\'appel, email confirmé, rappel programmé) de cet agent à un autre agent ou distribution automatique.') }}
                </p>
            </div>

            @if ($reassignAgentId)
                @php
                    $user = Auth::user();
                    $callCenterId = $user->call_center_id;
                    $agent = $this->agents->firstWhere('id', $reassignAgentId);
                    $pendingCallCount = \App\Models\Lead::where('assigned_to', $reassignAgentId)
                        ->where('status', 'pending_call')
                        ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                        ->count();
                    $emailConfirmedCount = \App\Models\Lead::where('assigned_to', $reassignAgentId)
                        ->where('status', 'email_confirmed')
                        ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                        ->count();
                    $callbackPendingCount = \App\Models\Lead::where('assigned_to', $reassignAgentId)
                        ->where('status', 'callback_pending')
                        ->when($callCenterId, fn($q) => $q->where('call_center_id', $callCenterId))
                        ->count();
                    $untreatedCount = $pendingCallCount + $emailConfirmedCount + $callbackPendingCount;
                    
                    // Utiliser la propriété computed pour le nombre sélectionné
                    $selectedCount = $this->selectedLeadsCount;
                @endphp
                <div class="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-700 dark:bg-orange-900/20">
                    <p class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Agent') }}: <strong>{{ $agent->name }}</strong>
                    </p>
                    <div class="space-y-2 text-sm">
                        @if ($pendingCallCount > 0)
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-neutral-700 dark:text-neutral-300">
                                    <span class="inline-flex h-2 w-2 rounded-full bg-orange-500"></span>
                                    {{ __('En attente d\'appel') }}
                                </span>
                                <span class="font-semibold text-orange-600 dark:text-orange-400">{{ $pendingCallCount }}</span>
                            </div>
                        @endif
                        @if ($emailConfirmedCount > 0)
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-neutral-600 dark:text-neutral-400">
                                    <span class="inline-flex h-2 w-2 rounded-full bg-blue-500"></span>
                                    {{ __('Email confirmé') }}
                                </span>
                                <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $emailConfirmedCount }}</span>
                            </div>
                        @endif
                        @if ($callbackPendingCount > 0)
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-neutral-600 dark:text-neutral-400">
                                    <span class="inline-flex h-2 w-2 rounded-full bg-yellow-500"></span>
                                    {{ __('Rappel programmé') }}
                                </span>
                                <span class="font-semibold text-yellow-600 dark:text-yellow-400">{{ $callbackPendingCount }}</span>
                            </div>
                        @endif
                        <div class="mt-3 border-t border-neutral-200 pt-2 dark:border-neutral-700">
                            <div class="flex items-center justify-between font-semibold">
                                <span class="text-neutral-900 dark:text-neutral-100">{{ __('Total disponible') }}</span>
                                <span class="text-orange-600 dark:text-orange-400">{{ $untreatedCount }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

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
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-2 w-2 rounded-full bg-orange-500"></span>
                                <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                    {{ __('En attente d\'appel') }}
                                </span>
                            </div>
                        </div>
                        @if ($reassignAgentId)
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $pendingCallCount }} {{ __('lead(s)') }}
                            </span>
                        @endif
                    </label>
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded p-2 transition-colors hover:bg-white dark:hover:bg-neutral-800">
                        <div class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                wire:model="reassignStatuses"
                                value="email_confirmed"
                                class="h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500"
                            />
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-2 w-2 rounded-full bg-blue-500"></span>
                                <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                    {{ __('Email confirmé') }}
                                </span>
                            </div>
                        </div>
                        @if ($reassignAgentId)
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $emailConfirmedCount }} {{ __('lead(s)') }}
                            </span>
                        @endif
                    </label>
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded p-2 transition-colors hover:bg-white dark:hover:bg-neutral-800">
                        <div class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                wire:model="reassignStatuses"
                                value="callback_pending"
                                class="h-4 w-4 rounded border-neutral-300 text-yellow-600 focus:ring-yellow-500"
                            />
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-2 w-2 rounded-full bg-yellow-500"></span>
                                <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                    {{ __('Rappel programmé') }}
                                </span>
                            </div>
                        </div>
                        @if ($reassignAgentId)
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $callbackPendingCount }} {{ __('lead(s)') }}
                            </span>
                        @endif
                    </label>
                </div>
                @if ($reassignAgentId && !empty($this->reassignStatuses))
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-2 dark:border-blue-700 dark:bg-blue-900/20">
                        <p class="text-xs text-blue-700 dark:text-blue-300">
                            <strong>{{ $this->selectedLeadsCount }}</strong> {{ __('lead(s) sélectionné(s)') }} sur {{ $untreatedCount }} {{ __('disponible(s)') }}
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
                                wire:model="reassignMode"
                                value="all"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="flex-1 text-sm text-neutral-700 dark:text-neutral-300">
                                {{ __('Tous les leads sélectionnés') }}
                                @if ($reassignAgentId)
                                    <span class="ml-2 text-neutral-500 dark:text-neutral-400">
                                        ({{ $this->selectedLeadsCount }} {{ __('lead(s)') }})
                                    </span>
                                @endif
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-neutral-200 p-3 transition-colors hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-900 {{ $reassignMode === 'count' ? 'border-blue-500 bg-blue-50 dark:border-blue-500 dark:bg-blue-900/20' : '' }}">
                            <input
                                type="radio"
                                wire:model="reassignMode"
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
                            :max="$untreatedCount ?? 999"
                            required
                        />
                        @if ($reassignAgentId)
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                {{ __('Maximum disponible selon sélection') }}: <strong>{{ $this->selectedLeadsCount }}</strong> {{ __('lead(s)') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <flux:select wire:model="reassignToAgentId" :label="__('Réassigner à (optionnel)')">
                <option value="">{{ __('Distribution automatique') }}</option>
                @foreach ($this->agents as $agent)
                    @if ($agent->id !== $reassignAgentId)
                        <option value="{{ $agent->id }}">
                            {{ $agent->name }} ({{ $agent->email }}) - {{ $agent->pending_leads_count }} {{ __('en attente') }}
                        </option>
                    @endif
                @endforeach
            </flux:select>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeReassignModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Réassigner') }}
                </flux:button>
            </div>
        </form>
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

    $wire.on('leads-reassigned', (event) => {
        $dispatch('notify', {
            message: event.message || 'Réassignation terminée.',
            type: 'success'
        });
    });
</script>
@endscript

