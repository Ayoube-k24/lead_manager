<?php

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public $stats = [];

    public function mount(): void
    {
        $supervisor = Auth::user();
        
        // Récupérer tous les agents sous la supervision
        $agents = User::where('supervisor_id', $supervisor->id)
            ->whereHas('role', fn($q) => $q->where('slug', 'agent'))
            ->get();

        $agentIds = $agents->pluck('id');
        $leads = Lead::whereIn('assigned_to', $agentIds)->get();

        $this->stats = [
            'total_agents' => $agents->count(),
            'active_agents' => $agents->where('is_active', true)->count(),
            'total_leads' => $leads->count(),
            'confirmed_leads' => $leads->where('status', 'confirmed')->count(),
            'rejected_leads' => $leads->where('status', 'rejected')->count(),
            'pending_leads' => $leads->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])->count(),
            'conversion_rate' => $leads->count() > 0
                ? round(($leads->where('status', 'confirmed')->count() / $leads->count()) * 100, 2)
                : 0,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Statistiques de l\'équipe') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Vue d\'ensemble des performances de votre équipe') }}
            </p>
        </div>
    </div>

    <!-- Statistiques principales -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Agents') }}</p>
            <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ $stats['total_agents'] }}
            </p>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                {{ $stats['active_agents'] }} {{ __('actifs') }}
            </p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Leads totaux') }}</p>
            <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ $stats['total_leads'] }}
            </p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Taux de conversion') }}</p>
            <p class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">
                {{ $stats['conversion_rate'] }}%
            </p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('En attente') }}</p>
            <p class="mt-2 text-3xl font-bold text-yellow-600 dark:text-yellow-400">
                {{ $stats['pending_leads'] }}
            </p>
        </div>
    </div>
</div>

