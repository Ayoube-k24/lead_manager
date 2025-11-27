<?php

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'total_users' => User::count(),
            'total_call_centers' => CallCenter::count(),
            'total_forms' => Form::count(),
            'total_leads' => Lead::count(),
            'confirmed_leads' => Lead::where('status', 'confirmed')->count(),
            'pending_leads' => Lead::whereIn('status', ['pending_email', 'email_confirmed', 'pending_call'])->count(),
            'rejected_leads' => Lead::where('status', 'rejected')->count(),
            'total_smtp_profiles' => SmtpProfile::count(),
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Bannière Super Admin -->
        <div class="rounded-xl border-2 border-purple-500 bg-gradient-to-r from-purple-600 to-pink-600 p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <div class="mb-2 inline-block rounded-full bg-white/20 px-4 py-1 text-sm font-bold backdrop-blur-sm">
                        🔐 SUPER ADMINISTRATEUR
                    </div>
                    <h1 class="text-3xl font-bold">{{ __('Dashboard Super Admin') }}</h1>
                    <p class="mt-2 text-purple-100">{{ __('Vue d\'ensemble complète de la plateforme - Accès total') }}</p>
                </div>
                <div class="hidden md:block">
                    <svg class="h-20 w-20 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Utilisateurs') }}</p>
                        <p class="text-2xl font-bold">{{ $stats['total_users'] }}</p>
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
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Centres d\'Appels') }}</p>
                        <p class="text-2xl font-bold">{{ $stats['total_call_centers'] }}</p>
                    </div>
                    <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                    </div>
                </div>
            </div>

            <a href="{{ route('admin.forms') }}" wire:navigate class="rounded-xl border border-neutral-200 bg-white p-6 transition-colors hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:bg-neutral-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Formulaires') }}</p>
                        <p class="text-2xl font-bold">{{ $stats['total_forms'] }}</p>
                    </div>
                    <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
            </a>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Leads Totaux') }}</p>
                        <p class="text-2xl font-bold">{{ $stats['total_leads'] }}</p>
                    </div>
                    <div class="rounded-full bg-orange-100 p-3 dark:bg-orange-900">
                        <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Leads Confirmés') }}</p>
                <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ $stats['confirmed_leads'] }}</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Leads en Attente') }}</p>
                <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['pending_leads'] }}</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Leads Rejetés') }}</p>
                <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ $stats['rejected_leads'] }}</p>
            </div>
        </div>

        <!-- Section supplémentaire pour Super Admin -->
        <div class="rounded-xl border-2 border-purple-200 bg-purple-50 p-6 dark:border-purple-800 dark:bg-purple-900/20">
            <h2 class="mb-4 text-xl font-bold text-purple-900 dark:text-purple-100">🔑 Accès Administrateur</h2>
            <p class="mb-4 text-purple-700 dark:text-purple-300">
                En tant que Super Administrateur, vous avez un accès complet à toutes les fonctionnalités de la plateforme, 
                y compris la gestion des utilisateurs, des centres d'appels, des formulaires et des configurations système.
            </p>
            
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
                <flux:button href="{{ route('admin.forms') }}" variant="primary" class="w-full" wire:navigate>
                    {{ __('Gérer les Formulaires') }}
                </flux:button>
                <flux:button href="{{ route('admin.smtp-profiles') }}" variant="primary" class="w-full" wire:navigate>
                    {{ __('Gérer les Profils SMTP') }}
                </flux:button>
                <flux:button href="{{ route('admin.email-templates') }}" variant="primary" class="w-full" wire:navigate>
                    {{ __('Gérer les Templates') }}
                </flux:button>
                <flux:button href="{{ route('admin.leads') }}" variant="primary" class="w-full" wire:navigate>
                    {{ __('Voir tous les Leads') }}
                </flux:button>
                <flux:button href="{{ route('admin.call-centers') }}" variant="primary" class="w-full" wire:navigate>
                    {{ __('Gestion des Centres') }}
                </flux:button>
                <flux:button href="{{ route('admin.call-centers.leads') }}" variant="primary" class="w-full" wire:navigate>
                    {{ __('Leads par Centre') }}
                </flux:button>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Actions rapides') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <flux:button href="{{ route('admin.forms.create') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Nouveau formulaire') }}
                </flux:button>
                <flux:button href="{{ route('admin.smtp-profiles.create') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Nouveau profil SMTP') }}
                </flux:button>
                <flux:button href="{{ route('admin.email-templates.create') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Nouveau template') }}
                </flux:button>
                <flux:button href="{{ route('admin.call-centers') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 102 2 2 2 0 00-2-2zm-6 0a2 2 0 102 2 2 2 0 00-2-2zM5 7a2 2 0 102 2 2 2 0 00-2-2zm14 6H5a2 2 0 00-2 2v3h18v-3a2 2 0 00-2-2z" />
                    </svg>
                    {{ __('Gérer les accès centres') }}
                </flux:button>
                <flux:button href="{{ route('admin.call-centers.leads') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
                    </svg>
                    {{ __('Leads par centre') }}
                </flux:button>
                <flux:button href="{{ route('admin.leads') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    {{ __('Voir tous les leads') }}
                </flux:button>
                <flux:button href="{{ route('admin.statistics') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    {{ __('Statistiques avancées') }}
                </flux:button>
                <flux:button href="{{ route('admin.webhooks') }}" variant="ghost" class="w-full justify-start" wire:navigate>
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                    </svg>
                    {{ __('Webhooks') }}
                </flux:button>
            </div>
        </div>
</section>
