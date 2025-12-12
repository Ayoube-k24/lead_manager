<?php

use App\Models\Lead;
use App\Models\Tag;
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

    public function getLeadsProperty()
    {
        $user = Auth::user();

        $query = Lead::where('assigned_to', $user->id)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('email', 'like', '%'.$this->search.'%')
                        ->orWhereJsonContains('data->name', $this->search)
                        ->orWhereJsonContains('data->first_name', $this->search)
                        ->orWhereJsonContains('data->last_name', $this->search);
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->sourceFilter, function ($query) {
                $query->where('source', $this->sourceFilter);
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
            ->with(['form', 'callCenter', 'tags']);

        return $query->latest()->paginate(15);
    }

    public function getStatsProperty(): array
    {
        $user = Auth::user();
        $activeStatuses = array_map(fn ($s) => $s->value, \App\LeadStatus::activeStatuses());
        $finalStatuses = array_map(fn ($s) => $s->value, \App\LeadStatus::finalStatuses());

        return [
            'total' => Lead::where('assigned_to', $user->id)->count(),
            'active' => Lead::where('assigned_to', $user->id)
                ->whereIn('status', $activeStatuses)
                ->count(),
            'qualified' => Lead::where('assigned_to', $user->id)
                ->where('status', \App\LeadStatus::Qualified->value)
                ->count(),
            'converted' => Lead::where('assigned_to', $user->id)
                ->where('status', \App\LeadStatus::Converted->value)
                ->count(),
            'closed' => Lead::where('assigned_to', $user->id)
                ->whereIn('status', $finalStatuses)
                ->count(),
        ];
    }

    public function getTagsProperty()
    {
        $user = Auth::user();

        return Tag::whereHas('leads', fn ($q) => $q->where('assigned_to', $user->id))
            ->orderBy('name')
            ->get();
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

@php
    $user = Auth::user();
    $experienceLevel = $user->experience_level ?? 'beginner';
    $isBeginner = $experienceLevel === 'beginner';
    $isAdvanced = $experienceLevel === 'advanced';
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Mes Leads') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Gérez les leads qui vous sont attribués') }}
            </p>
        </div>
        @if ($isBeginner)
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 dark:border-blue-800 dark:bg-blue-900/20">
                <span class="text-xs font-medium text-blue-700 dark:text-blue-300">📚 {{ __('Mode Débutant') }}</span>
            </div>
        @endif
    </div>

    @if ($isBeginner)
        <!-- Guide rapide pour débutants -->
        <div class="rounded-xl border-2 border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100">{{ __('Comment utiliser cette page ?') }}</h3>
                    <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">
                        {{ __('Cette page liste tous les leads qui vous ont été attribués. Cliquez sur "Voir" pour accéder aux détails et mettre à jour le statut après votre appel.') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Statistiques -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Total') }}</p>
                    <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">{{ $this->stats['total'] }}</p>
                </div>
                <div class="rounded-full bg-blue-100 p-2 dark:bg-blue-900/20">
                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Actifs') }}</p>
                    <p class="mt-2 text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ $this->stats['active'] }}</p>
                </div>
                <div class="rounded-full bg-yellow-100 p-2 dark:bg-yellow-900/20">
                    <svg class="h-5 w-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Qualifiés') }}</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $this->stats['qualified'] }}</p>
                </div>
                <div class="rounded-full bg-emerald-100 p-2 dark:bg-emerald-900/20">
                    <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Convertis') }}</p>
                    <p class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ $this->stats['converted'] }}</p>
                </div>
                <div class="rounded-full bg-green-100 p-2 dark:bg-green-900/20">
                    <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Fermés') }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-600 dark:text-slate-400">{{ $this->stats['closed'] }}</p>
                </div>
                <div class="rounded-full bg-slate-100 p-2 dark:bg-slate-900/20">
                    <svg class="h-5 w-5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
            </div>
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

    <!-- Liste des leads -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Contact') }}
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Formulaire') }}
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Statut') }}
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Score') }}
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Date') }}
                        </th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-neutral-800">
                    @forelse ($this->leads as $lead)
                        <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-900/50 transition-colors">
                            <td class="px-6 py-4">
                                @php
                                    $phone = $lead->phone ?? data_get($lead->data, 'phone');
                                    $fullName = $lead->data['name']
                                        ?? trim(($lead->data['first_name'] ?? '') . ' ' . ($lead->data['last_name'] ?? ''));
                                    $fullName = $fullName ?: 'N/A';
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
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-md bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-700 dark:bg-neutral-700 dark:text-neutral-300">
                                    {{ $lead->form?->name ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $statusEnum = $lead->getStatusEnum();
                                @endphp
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusEnum->colorClass() }}">
                                        {{ $statusEnum->label() }}
                                    </span>
                                    @if ($isBeginner && $statusEnum->description())
                                        <flux:tooltip>
                                            <button type="button" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                            <flux:tooltip.content class="max-w-xs">
                                                <p class="font-semibold mb-1">{{ $statusEnum->label() }}</p>
                                                <p class="text-xs">{{ $statusEnum->description() }}</p>
                                            </flux:tooltip.content>
                                        </flux:tooltip>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if ($lead->score !== null)
                                    <div class="flex flex-col gap-1">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $lead->getScoreBadgeColor() }}">
                                            {{ $lead->score }}/100
                                        </span>
                                        <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                            {{ $lead->getScoreLabel() }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-xs text-neutral-400">{{ __('Non calculé') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                <div class="flex flex-col">
                                    <span>{{ $lead->created_at->format('d/m/Y') }}</span>
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ $lead->created_at->format('H:i') }}</span>
                                    @if ($isAdvanced && $lead->called_at)
                                        <span class="mt-1 text-xs text-green-600 dark:text-green-400">
                                            📞 {{ __('Appelé le') }} {{ $lead->called_at->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <flux:button
                                    href="{{ route('agent.leads.show', $lead) }}"
                                    variant="ghost"
                                    size="sm"
                                    wire:navigate
                                >
                                    {{ __('Voir') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="h-12 w-12 text-neutral-400 dark:text-neutral-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Aucun lead trouvé') }}</p>
                                    <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">{{ __('Essayez de modifier vos filtres de recherche') }}</p>
                                </div>
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
</div>

