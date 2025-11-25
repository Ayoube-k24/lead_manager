<?php

use App\Models\Lead;
use App\Models\User;
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
        $supervisor = Auth::user();
        
        // Récupérer tous les agents sous la supervision
        $agentIds = User::where('supervisor_id', $supervisor->id)
            ->whereHas('role', fn($q) => $q->where('slug', 'agent'))
            ->pluck('id');

        return Lead::whereIn('assigned_to', $agentIds)
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
                            {{ __('Agent') }}
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
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">
                                {{ $lead->email }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->form?->name ?? 'N/A' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->assignedAgent?->name ?? 'Non assigné' }}
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
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
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

