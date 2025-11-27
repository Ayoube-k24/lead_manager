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
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if ($callCenter) {
            // Get all agent IDs for this call center
            $agentIds = User::where('call_center_id', $callCenter->id)
                ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
                ->pluck('id');

            $this->stats = [
                'call_center_name' => $callCenter->name,
                'total_agents' => $agentIds->count(),
                'total_leads' => Lead::where('call_center_id', $callCenter->id)->count(),
                'confirmed_leads' => Lead::where('call_center_id', $callCenter->id)
                    ->where('status', 'confirmed')
                    ->count(),
                'pending_leads' => Lead::where('call_center_id', $callCenter->id)
                    ->whereIn('status', ['pending_email', 'email_confirmed', 'pending_call'])
                    ->count(),
                'rejected_leads' => Lead::where('call_center_id', $callCenter->id)
                    ->where('status', 'rejected')
                    ->count(),
            ];
        } else {
            // If no call center, set default values
            $this->stats = [
                'call_center_name' => 'Non défini',
                'total_agents' => 0,
                'total_leads' => 0,
                'confirmed_leads' => 0,
                'pending_leads' => 0,
                'rejected_leads' => 0,
            ];
        }
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Bannière Propriétaire -->
        <div class="rounded-xl border-2 border-blue-500 bg-gradient-to-r from-blue-600 to-cyan-600 p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <div class="mb-2 inline-block rounded-full bg-white/20 px-4 py-1 text-sm font-bold backdrop-blur-sm">
                        👔 PROPRIÉTAIRE DE CENTRE D'APPELS
                    </div>
                    <h1 class="text-3xl font-bold">{{ __('Dashboard Centre d\'Appels') }}</h1>
                    <p class="mt-2 text-blue-100">
                        {{ __('Vue d\'ensemble de votre centre d\'appels') }}
                        @if(isset($stats['call_center_name']))
                            - {{ $stats['call_center_name'] }}
                        @endif
                    </p>
                </div>
                <div class="hidden md:block">
                    <svg class="h-20 w-20 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
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
                        <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">{{ $stats['total_agents'] ?? 0 }}</p>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ __('Dans votre centre') }}
                        </p>
                    </div>
                    <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900/20">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Leads totaux') }}</p>
                        <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">{{ $stats['total_leads'] ?? 0 }}</p>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ __('Tous les leads') }}
                        </p>
                    </div>
                    <div class="rounded-full bg-orange-100 p-3 dark:bg-orange-900/20">
                        <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Confirmés') }}</p>
                        <p class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ $stats['confirmed_leads'] ?? 0 }}</p>
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
                        <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('En attente') }}</p>
                        <p class="mt-2 text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['pending_leads'] ?? 0 }}</p>
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
                        <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Rejetés') }}</p>
                        <p class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">{{ $stats['rejected_leads'] ?? 0 }}</p>
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

        <!-- Taux de conversion du centre -->
        @php
            $centerConversionRate = ($stats['total_leads'] ?? 0) > 0 
                ? round((($stats['confirmed_leads'] ?? 0) / ($stats['total_leads'] ?? 1)) * 100, 1) 
                : 0;
        @endphp
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Taux de conversion du centre') }}</h3>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Pourcentage de leads confirmés sur le total du centre') }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-4xl font-bold text-green-600 dark:text-green-400">{{ $centerConversionRate }}%</p>
                    <div class="mt-2 h-2 w-32 rounded-full bg-neutral-200 dark:bg-neutral-700">
                        <div class="h-2 rounded-full bg-green-500" style="width: {{ min($centerConversionRate, 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Gestion -->
        <div class="rounded-xl border-2 border-blue-200 bg-gradient-to-r from-blue-50 to-cyan-50 p-6 dark:border-blue-800 dark:from-blue-900/20 dark:to-cyan-900/20">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 rounded-full bg-blue-100 p-3 dark:bg-blue-900/40">
                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h2 class="mb-2 text-xl font-bold text-blue-900 dark:text-blue-100">{{ __('Gestion de votre Centre d\'Appels') }}</h2>
                    <p class="mb-4 text-sm text-blue-700 dark:text-blue-300">
                        {{ __('Gérez vos agents, consultez les performances de votre équipe et suivez l\'évolution des leads de votre centre d\'appels.') }}
                    </p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <flux:button href="{{ route('owner.agents') }}" variant="primary" class="w-full" wire:navigate>
                            {{ __('Gérer les Agents') }}
                        </flux:button>
                        <flux:button href="{{ route('owner.leads') }}" variant="primary" class="w-full" wire:navigate>
                            {{ __('Gérer les Leads') }}
                        </flux:button>
                        <flux:button href="{{ route('owner.distribution') }}" variant="primary" class="w-full" wire:navigate>
                            {{ __('Configuration Distribution') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Accès rapide') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <flux:button href="{{ route('owner.agents.create') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Créer un agent') }}
                </flux:button>
                <flux:button href="{{ route('owner.agents') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    {{ __('Gérer les agents') }}
                </flux:button>
                <flux:button href="{{ route('owner.leads') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    {{ __('Voir tous les leads') }}
                </flux:button>
                <flux:button href="{{ route('owner.statistics') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    {{ __('Statistiques') }}
                </flux:button>
            </div>
        </div>
</section>
