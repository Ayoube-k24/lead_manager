<?php

use App\Models\Lead;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

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

        return Lead::where('assigned_to', $user->id)
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
            ->with(['form', 'callCenter'])
            ->latest()
            ->paginate(15);
    }

    public function getStatsProperty(): array
    {
        $user = Auth::user();
        $activeStatuses = array_map(fn($s) => $s->value, \App\LeadStatus::activeStatuses());
        $finalStatuses = array_map(fn($s) => $s->value, \App\LeadStatus::finalStatuses());

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
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Mes Leads') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Gérez les leads qui vous sont attribués') }}
            </p>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Total') }}</div>
            <div class="mt-1 text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ $this->stats['total'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Actifs') }}</div>
            <div class="mt-1 text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $this->stats['active'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Qualifiés') }}</div>
            <div class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $this->stats['qualified'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Convertis') }}</div>
            <div class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->stats['converted'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Fermés') }}</div>
            <div class="mt-1 text-2xl font-bold text-slate-600 dark:text-slate-400">{{ $this->stats['closed'] }}</div>
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
            @foreach (\App\LeadStatus::cases() as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
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
                            {{ __('Nom') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Formulaire') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Statut') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Date') }}
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
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">
                                {{ $lead->data['name'] ?? $lead->data['first_name'] ?? 'N/A' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->form?->name ?? 'N/A' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @php
                                    $statusEnum = $lead->getStatusEnum();
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusEnum->colorClass() }}">
                                    {{ $statusEnum->label() }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <flux:button
                                    href="{{ route('agent.leads.show', $lead) }}"
                                    variant="ghost"
                                    size="sm"
                                >
                                    {{ __('Voir') }}
                                </flux:button>
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

        @if ($this->leads->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                {{ $this->leads->links() }}
            </div>
        @endif
    </div>
</div>

