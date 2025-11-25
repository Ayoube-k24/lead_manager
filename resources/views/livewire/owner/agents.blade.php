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
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if (! $callCenter) {
            return collect();
        }

        $query = User::where('call_center_id', $callCenter->id)
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

    public function toggleStatus(User $agent): void
    {
        // Vérifier que l'agent appartient au centre d'appels du propriétaire
        $user = Auth::user();
        if ($agent->call_center_id !== $user->call_center_id) {
            return;
        }

        // Vérifier qu'il n'y a pas de leads assignés si on désactive
        if ($agent->is_active && $agent->assignedLeads()->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])->exists()) {
            $this->dispatch('agent-has-leads');
            return;
        }

        $agent->is_active = ! $agent->is_active;
        $agent->save();

        $this->dispatch($agent->is_active ? 'agent-activated' : 'agent-deactivated');
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
                {{ __('Gérez les agents de votre centre d\'appels') }}
            </p>
        </div>
        <flux:button href="{{ route('owner.agents.create') }}" variant="primary">
            {{ __('Créer un agent') }}
        </flux:button>
    </div>

    <!-- Recherche -->
    <flux:input
        wire:model.live.debounce.300ms="search"
        :label="__('Rechercher')"
        placeholder="{{ __('Nom, email...') }}"
    />

    <!-- Liste des agents -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Nom') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Email') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Statut') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Leads assignés') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Taux de conversion') }}
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
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                {{ $agent->name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $agent->email }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($agent->is_active)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300">
                                        {{ __('Actif') }}
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-neutral-200 px-2 py-1 text-xs font-semibold text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                        {{ __('Désactivé') }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $agent->assigned_leads_count }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $stats['conversion_rate'] >= 50 ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' : ($stats['conversion_rate'] >= 30 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400' : 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400') }}">
                                    {{ $stats['conversion_rate'] }}%
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button
                                        href="{{ route('owner.agents.stats', $agent) }}"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        {{ __('Stats') }}
                                    </flux:button>
                                    <flux:button
                                        href="{{ route('owner.agents.edit', $agent) }}"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        {{ __('Modifier') }}
                                    </flux:button>
                                    <flux:button
                                        wire:click="toggleStatus({{ $agent->id }})"
                                        wire:confirm="{{ $agent->is_active ? __('Désactiver cet agent ? Les leads en cours doivent être réassignés.') : __('Réactiver cet agent ?') }}"
                                        variant="{{ $agent->is_active ? 'danger' : 'success' }}"
                                        size="sm"
                                    >
                                        {{ $agent->is_active ? __('Désactiver') : __('Réactiver') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Aucun agent trouvé') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->agents->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                {{ $this->agents->links() }}
            </div>
        @endif
    </div>
</div>

