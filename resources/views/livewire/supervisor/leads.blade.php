<?php

use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
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

    public string $activeTab = 'form'; // 'form' or 'leads_seo'

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

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function getLeadsProperty()
    {
        $supervisor = Auth::user();

        // Récupérer tous les agents sous la supervision
        $agentIds = User::where('supervisor_id', $supervisor->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->pluck('id');

        // Filtrer par source selon l'onglet actif
        $source = $this->activeTab === 'form' ? 'form' : 'leads_seo';

        $query = Lead::whereIn('assigned_to', $agentIds)
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
        $supervisor = Auth::user();
        $agentIds = User::where('supervisor_id', $supervisor->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->pluck('id');

        $formLeads = Lead::whereIn('assigned_to', $agentIds)->where('source', 'form')->get();
        $seoLeads = Lead::whereIn('assigned_to', $agentIds)->where('source', 'leads_seo')->get();

        return [
            'form' => [
                'total' => $formLeads->count(),
                'confirmed' => $formLeads->where('status', 'confirmed')->count(),
                'rejected' => $formLeads->where('status', 'rejected')->count(),
                'pending' => $formLeads->whereIn('status', ['pending_email', 'email_confirmed', 'pending_call', 'callback_pending'])->count(),
            ],
            'leads_seo' => [
                'total' => $seoLeads->count(),
                'confirmed' => $seoLeads->where('status', 'confirmed')->count(),
                'rejected' => $seoLeads->where('status', 'rejected')->count(),
                'pending' => $seoLeads->whereIn('status', ['pending_email', 'email_confirmed', 'pending_call', 'callback_pending'])->count(),
            ],
        ];
    }

    public function getTagsProperty()
    {
        $supervisor = Auth::user();
        $agentIds = User::where('supervisor_id', $supervisor->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->pluck('id');

        return Tag::whereHas('leads', fn ($q) => $q->whereIn('assigned_to', $agentIds))
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Leads de l\'équipe') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Tous les leads assignés aux agents sous votre supervision') }}
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
                wire:click="switchTab('leads_seo')"
                type="button"
                class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium transition-colors {{ $activeTab === 'leads_seo' ? 'border-green-500 text-green-600 dark:text-green-400' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
            >
                {{ __('Leads SEO') }}
                <span class="ml-2 rounded-full bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-800">
                    {{ $this->stats['leads_seo']['total'] }}
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
                            wire:click="
                                @if($isSelected)
                                    $set('tagsFilter', array_values(array_diff($tagsFilter, [{{ $tag->id }}])))
                                @else
                                    $set('tagsFilter', array_merge($tagsFilter, [{{ $tag->id }}]))
                                @endif
                            "
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
                            {{ __('Agent') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Tags') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Statut') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Date') }}
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
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->assignedAgent?->name ?? 'Non assigné' }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($lead->tags as $tag)
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium" style="background-color: {{ $tag->color }}20; color: {{ $tag->color }};">
                                            <div class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $tag->color }};"></div>
                                            {{ $tag->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ __('Aucun tag') }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @php
                                    $statusEnum = $lead->getStatusEnum();
                                @endphp
                                <flux:badge :variant="$statusEnum->colorClass()">
                                    {{ $statusEnum->label() }}
                                </flux:badge>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->created_at->format('d/m/Y H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Aucun lead trouvé') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->leads->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                {{ $this->leads->links() }}
            </div>
        @endif
    </div>
</div>

