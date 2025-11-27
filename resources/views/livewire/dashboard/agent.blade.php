<?php

use App\Models\Lead;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public $stats = [];

    public function mount(): void
    {
        $user = Auth::user();

        $this->stats = [
            'assigned_leads' => Lead::where('assigned_to', $user->id)->count(),
            'pending_leads' => Lead::where('assigned_to', $user->id)
                ->whereIn('status', ['pending_call', 'email_confirmed'])
                ->count(),
            'confirmed_leads' => Lead::where('assigned_to', $user->id)
                ->where('status', 'confirmed')
                ->count(),
            'rejected_leads' => Lead::where('assigned_to', $user->id)
                ->where('status', 'rejected')
                ->count(),
            'callback_pending' => Lead::where('assigned_to', $user->id)
                ->where('status', 'callback_pending')
                ->count(),
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Bannière Agent -->
        <div class="rounded-xl border-2 border-green-500 bg-gradient-to-r from-green-600 to-emerald-600 p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <div class="mb-2 inline-block rounded-full bg-white/20 px-4 py-1 text-sm font-bold backdrop-blur-sm">
                        📞 AGENT DE CENTRE D'APPELS
                    </div>
                    <h1 class="text-3xl font-bold">{{ __('Dashboard Agent') }}</h1>
                    <p class="mt-2 text-green-100">
                        {{ __('Bienvenue') }}, <strong>{{ Auth::user()->name }}</strong> - {{ __('Vue d\'ensemble de vos leads assignés') }}
                    </p>
                </div>
                <div class="hidden md:block">
                    <svg class="h-20 w-20 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Statistiques principales -->
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Leads Assignés') }}</p>
                        <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">{{ $stats['assigned_leads'] }}</p>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ __('Total') }}
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
                        <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('En Attente') }}</p>
                        <p class="mt-2 text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['pending_leads'] }}</p>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ __('Appel à faire') }}
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
                        <p class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ $stats['confirmed_leads'] }}</p>
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
                        <p class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">{{ $stats['rejected_leads'] }}</p>
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

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Rappels') }}</p>
                        <p class="mt-2 text-3xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['callback_pending'] }}</p>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ __('À rappeler') }}
                        </p>
                    </div>
                    <div class="rounded-full bg-orange-100 p-3 dark:bg-orange-900/20">
                        <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Taux de conversion -->
        @php
            $conversionRate = $stats['assigned_leads'] > 0 
                ? round(($stats['confirmed_leads'] / $stats['assigned_leads']) * 100, 1) 
                : 0;
        @endphp
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Taux de conversion') }}</h3>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Pourcentage de leads confirmés sur le total assigné') }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-4xl font-bold text-green-600 dark:text-green-400">{{ $conversionRate }}%</p>
                    <div class="mt-2 h-2 w-32 rounded-full bg-neutral-200 dark:bg-neutral-700">
                        <div class="h-2 rounded-full bg-green-500" style="width: {{ min($conversionRate, 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Missions -->
        <div class="rounded-xl border-2 border-green-200 bg-gradient-to-r from-green-50 to-emerald-50 p-6 dark:border-green-800 dark:from-green-900/20 dark:to-emerald-900/20">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 rounded-full bg-green-100 p-3 dark:bg-green-900/40">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h2 class="mb-2 text-xl font-bold text-green-900 dark:text-green-100">{{ __('Vos Missions') }}</h2>
                    <p class="mb-4 text-sm text-green-700 dark:text-green-300">
                        {{ __('Contactez les leads qui vous sont assignés, mettez à jour leur statut et gérez vos rappels pour optimiser vos performances.') }}
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:button href="{{ route('agent.leads') }}" variant="primary" class="w-full" wire:navigate>
                            {{ __('Voir tous mes leads') }}
                        </flux:button>
                        <flux:button href="{{ route('agent.reminders.calendar') }}" variant="primary" class="w-full" wire:navigate>
                            {{ __('Calendrier des rappels') }}
                        </flux:button>
                        @if ($stats['pending_leads'] > 0)
                            <flux:button href="{{ route('agent.leads') }}?statusFilter=pending_call" variant="primary" class="w-full" wire:navigate>
                                {{ __('Leads en attente') }} <span class="ml-2 rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ $stats['pending_leads'] }}</span>
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Accès rapide') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <flux:button href="{{ route('agent.leads') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    {{ __('Tous mes leads') }}
                </flux:button>
                <flux:button href="{{ route('agent.leads') }}?statusFilter=pending_call" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('En attente') }}
                    @if ($stats['pending_leads'] > 0)
                        <span class="ml-auto rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-400">{{ $stats['pending_leads'] }}</span>
                    @endif
                </flux:button>
                <flux:button href="{{ route('agent.leads') }}?statusFilter=confirmed" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('Confirmés') }}
                </flux:button>
                <flux:button href="{{ route('agent.leads') }}?statusFilter=callback_pending" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {{ __('Rappels') }}
                    @if ($stats['callback_pending'] > 0)
                        <span class="ml-auto rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-800 dark:bg-orange-900/40 dark:text-orange-400">{{ $stats['callback_pending'] }}</span>
                    @endif
                </flux:button>
                <flux:button href="{{ route('agent.reminders.calendar') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {{ __('Calendrier') }}
                </flux:button>
            </div>
        </div>
</section>
