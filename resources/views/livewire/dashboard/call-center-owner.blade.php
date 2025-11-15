<?php

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public $stats = [];

    public function mount(): void
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if ($callCenter) {
            // Get all agent IDs for this call center
            $agentIds = User::where('call_center_id', $callCenter->id)
                ->whereHas('role', fn($q) => $q->where('slug', 'agent'))
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

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Agents') }}</p>
                        <p class="text-2xl font-bold">{{ $stats['total_agents'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Leads Totaux') }}</p>
                        <p class="text-2xl font-bold">{{ $stats['total_leads'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-full bg-orange-100 p-3 dark:bg-orange-900">
                        <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Leads Confirmés') }}</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['confirmed_leads'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Leads en Attente') }}</p>
                <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['pending_leads'] ?? 0 }}</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Leads Rejetés') }}</p>
                <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ $stats['rejected_leads'] ?? 0 }}</p>
            </div>
        </div>

        <!-- Section supplémentaire pour Propriétaire -->
        <div class="rounded-xl border-2 border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-900/20">
            <h2 class="mb-4 text-xl font-bold text-blue-900 dark:text-blue-100">📊 Gestion de votre Centre d'Appels</h2>
            <p class="text-blue-700 dark:text-blue-300">
                En tant que Propriétaire, vous pouvez gérer vos agents, consulter les performances de votre équipe 
                et suivre l'évolution des leads de votre centre d'appels.
            </p>
        </div>
</section>
