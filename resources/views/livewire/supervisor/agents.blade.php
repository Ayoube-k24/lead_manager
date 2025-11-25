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

    public function mount(): void
    {
        //
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getAgentsProperty()
    {
        $supervisor = Auth::user();

        $query = User::where('supervisor_id', $supervisor->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->withCount(['assignedLeads']);

        // Only order by is_active if the column exists
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')) {
            $query->orderByDesc('is_active');
        }

        return $query->latest()->paginate(15);
    }

    public function getAgentStats(User $agent): array
    {
        $leads = Lead::where('assigned_to', $agent->id)->get();

        return [
            'total' => $leads->count(),
            'confirmed' => $leads->where('status', 'confirmed')->count(),
            'rejected' => $leads->where('status', 'rejected')->count(),
            'pending' => $leads->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])->count(),
            'conversion_rate' => $leads->count() > 0
                ? round(($leads->where('status', 'confirmed')->count() / $leads->count()) * 100, 2)
                : 0,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Mes Agents') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Vue d\'ensemble des agents sous votre supervision') }}
            </p>
        </div>
    </div>

    <!-- Recherche -->
    <div class="flex gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('Rechercher un agent...') }}"
            icon="magnifying-glass"
            class="flex-1"
        />
    </div>

    <!-- Liste des agents -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Agent') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Statut') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Leads') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Performance') }}
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-neutral-800">
                    @forelse ($this->agents as $agent)
                        @php
                            $stats = $this->getAgentStats($agent);
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400">
                                        {{ $agent->initials() }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-neutral-900 dark:text-neutral-100">{{ $agent->name }}</div>
                                        <div class="text-sm text-neutral-500 dark:text-neutral-400">{{ $agent->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <flux:badge :variant="$agent->is_active ? 'success' : 'danger'">
                                    {{ $agent->is_active ? __('Actif') : __('Inactif') }}
                                </flux:badge>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                <div class="space-y-1">
                                    <div>{{ __('Total') }}: <strong>{{ $stats['total'] }}</strong></div>
                                    <div class="text-xs">
                                        <span class="text-green-600 dark:text-green-400">{{ __('Confirmés') }}: {{ $stats['confirmed'] }}</span> |
                                        <span class="text-red-600 dark:text-red-400">{{ __('Rejetés') }}: {{ $stats['rejected'] }}</span> |
                                        <span class="text-yellow-600 dark:text-yellow-400">{{ __('En attente') }}: {{ $stats['pending'] }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 rounded-full bg-neutral-200 dark:bg-neutral-700">
                                        <div class="h-2 rounded-full bg-green-500" style="width: {{ $stats['conversion_rate'] }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium">{{ $stats['conversion_rate'] }}%</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <flux:button
                                    href="{{ route('supervisor.agents.stats', $agent) }}"
                                    variant="ghost"
                                    size="sm"
                                    wire:navigate
                                >
                                    {{ __('Détails') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Aucun agent sous votre supervision pour le moment') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->agents->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                {{ $this->agents->links() }}
            </div>
        @endif
    </div>
</div>

