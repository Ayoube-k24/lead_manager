<?php

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public $stats = [];

    public function mount(): void
    {
        $supervisor = Auth::user();
        
        // Récupérer tous les agents sous la supervision
        $agents = User::where('supervisor_id', $supervisor->id)
            ->whereHas('role', fn($q) => $q->where('slug', 'agent'))
            ->get();

        $agentIds = $agents->pluck('id');

        $this->stats = [
            'total_agents' => $agents->count(),
            'active_agents' => $agents->where('is_active', true)->count(),
            'total_leads' => Lead::whereIn('assigned_to', $agentIds)->count(),
            'pending_leads' => Lead::whereIn('assigned_to', $agentIds)
                ->whereIn('status', ['pending_call', 'email_confirmed'])
                ->count(),
            'confirmed_leads' => Lead::whereIn('assigned_to', $agentIds)
                ->where('status', 'confirmed')
                ->count(),
            'rejected_leads' => Lead::whereIn('assigned_to', $agentIds)
                ->where('status', 'rejected')
                ->count(),
            'callback_pending' => Lead::whereIn('assigned_to', $agentIds)
                ->where('status', 'callback_pending')
                ->count(),
        ];
    }

    public function getAgentsProperty()
    {
        return User::where('supervisor_id', Auth::id())
            ->whereHas('role', fn($q) => $q->where('slug', 'agent'))
            ->withCount('assignedLeads')
            ->latest()
            ->take(5)
            ->get();
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Bannière Superviseur -->
    <div class="rounded-xl border-2 border-blue-500 bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <div class="mb-2 inline-block rounded-full bg-white/20 px-4 py-1 text-sm font-bold backdrop-blur-sm">
                    👥 SUPERVISEUR
                </div>
                <h1 class="text-3xl font-bold">{{ __('Dashboard Superviseur') }}</h1>
                <p class="mt-2 text-blue-100">
                    {{ __('Bienvenue') }}, <strong>{{ Auth::user()->name }}</strong> - {{ __('Vue d\'ensemble de votre équipe') }}
                </p>
            </div>
            <div class="hidden md:block">
                <svg class="h-20 w-20 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Statistiques principales -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Agents') }}</p>
                    <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">{{ $this->stats['total_agents'] }}</p>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $this->stats['active_agents'] }} {{ __('actifs') }}
                    </p>
                </div>
                <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900/20">
                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Leads totaux') }}</p>
                    <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">{{ $this->stats['total_leads'] }}</p>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        {{ __('Équipe complète') }}
                    </p>
                </div>
                <div class="rounded-full bg-green-100 p-3 dark:bg-green-900/20">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('En attente') }}</p>
                    <p class="mt-2 text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ $this->stats['pending_leads'] }}</p>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        {{ __('Appels à faire') }}
                    </p>
                </div>
                <div class="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900/20">
                    <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Confirmés') }}</p>
                    <p class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ $this->stats['confirmed_leads'] }}</p>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        {{ __('Qualifiés') }}
                    </p>
                </div>
                <div class="rounded-full bg-green-100 p-3 dark:bg-green-900/20">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Rejetés') }}</p>
                    <p class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">{{ $this->stats['rejected_leads'] }}</p>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        {{ __('Non qualifiés') }}
                    </p>
                </div>
                <div class="rounded-full bg-red-100 p-3 dark:bg-red-900/20">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Taux de conversion de l'équipe -->
    @php
        $teamConversionRate = $this->stats['total_leads'] > 0 
            ? round(($this->stats['confirmed_leads'] / $this->stats['total_leads']) * 100, 1) 
            : 0;
    @endphp
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Taux de conversion de l\'équipe') }}</h3>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Pourcentage de leads confirmés par votre équipe') }}
                </p>
            </div>
            <div class="text-right">
                <p class="text-4xl font-bold text-green-600 dark:text-green-400">{{ $teamConversionRate }}%</p>
                <div class="mt-2 h-2 w-32 rounded-full bg-neutral-200 dark:bg-neutral-700">
                    <div class="h-2 rounded-full bg-green-500" style="width: {{ min($teamConversionRate, 100) }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Gestion -->
    <div class="rounded-xl border-2 border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-6 dark:border-blue-800 dark:from-blue-900/20 dark:to-indigo-900/20">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 rounded-full bg-blue-100 p-3 dark:bg-blue-900/40">
                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <div class="flex-1">
                <h2 class="mb-2 text-xl font-bold text-blue-900 dark:text-blue-100">{{ __('Gestion de votre équipe') }}</h2>
                <p class="mb-4 text-sm text-blue-700 dark:text-blue-300">
                    {{ __('Supervisez vos agents, suivez leurs performances et analysez les statistiques de votre équipe.') }}
                </p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <flux:button href="{{ route('supervisor.agents') }}" variant="primary" class="w-full" wire:navigate>
                        {{ __('Gérer mes agents') }}
                    </flux:button>
                    <flux:button href="{{ route('supervisor.leads') }}" variant="primary" class="w-full" wire:navigate>
                        {{ __('Voir tous les leads') }}
                    </flux:button>
                    <flux:button href="{{ route('supervisor.statistics') }}" variant="primary" class="w-full" wire:navigate>
                        {{ __('Statistiques détaillées') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions rapides -->
    <div class="grid gap-6 sm:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Accès rapide') }}</h2>
            <div class="flex flex-col gap-3">
                <flux:button href="{{ route('supervisor.agents') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    {{ __('Mes agents') }}
                </flux:button>
                <flux:button href="{{ route('supervisor.leads') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    {{ __('Leads de l\'équipe') }}
                </flux:button>
                <flux:button href="{{ route('supervisor.statistics') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    {{ __('Statistiques') }}
                </flux:button>
            </div>
        </div>

        <!-- Agents récents -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Mes agents') }}</h2>
                <flux:button href="{{ route('supervisor.agents') }}" variant="ghost" size="sm" wire:navigate>
                    {{ __('Voir tout') }}
                </flux:button>
            </div>
            @if($this->agents->count() > 0)
                <div class="space-y-3">
                    @foreach($this->agents as $agent)
                        <div class="flex items-center justify-between rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-900">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400">
                                    {{ $agent->initials() }}
                                </div>
                                <div>
                                    <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $agent->name }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ $agent->assigned_leads_count }} {{ __('leads assignés') }}
                                    </p>
                                </div>
                            </div>
                            <flux:badge :variant="$agent->is_active ? 'success' : 'danger'">
                                {{ $agent->is_active ? __('Actif') : __('Inactif') }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">
                    {{ __('Aucun agent assigné pour le moment') }}
                </p>
            @endif
        </div>
    </div>
</section>

