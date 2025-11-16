<?php

use App\Models\Lead;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
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

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Leads Assignés') }}</p>
                        <p class="text-2xl font-bold">{{ $stats['assigned_leads'] }}</p>
                    </div>
                    <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('En Attente d\'Appel') }}</p>
                        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['pending_leads'] }}</p>
                    </div>
                    <div class="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900">
                        <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Confirmés') }}</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['confirmed_leads'] }}</p>
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
                <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Rejetés') }}</p>
                <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ $stats['rejected_leads'] }}</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Rappels en Attente') }}</p>
                <p class="text-3xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['callback_pending'] }}</p>
            </div>
        </div>

        <!-- Section supplémentaire pour Agent -->
        <div class="rounded-xl border-2 border-green-200 bg-green-50 p-6 dark:border-green-800 dark:bg-green-900/20">
            <h2 class="mb-4 text-xl font-bold text-green-900 dark:text-green-100">📞 Vos Missions</h2>
            <p class="mb-4 text-green-700 dark:text-green-300">
                En tant qu'Agent, vous recevez les leads qui vous sont assignés. Contactez-les par téléphone, 
                mettez à jour leur statut et gérez vos rappels pour optimiser vos performances.
            </p>
            
            <div class="grid gap-3 md:grid-cols-2">
                <flux:button href="{{ route('agent.leads') }}" variant="primary" class="w-full" wire:navigate>
                    {{ __('Voir mes Leads') }}
                </flux:button>
                @if ($stats['pending_leads'] > 0)
                    <flux:button href="{{ route('agent.leads') }}?statusFilter=pending_call" variant="primary" class="w-full" wire:navigate>
                        {{ __('Leads en attente') }} ({{ $stats['pending_leads'] }})
                    </flux:button>
                @endif
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Actions rapides') }}</h2>
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
                </flux:button>
            </div>
        </div>
</section>
