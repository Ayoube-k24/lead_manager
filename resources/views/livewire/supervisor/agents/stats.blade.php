<?php

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public User $agent;
    public $stats = [];

    public function mount(User $user): void
    {
        // Vérifier que l'agent est sous la supervision
        $supervisor = Auth::user();
        if ($user->supervisor_id !== $supervisor->id) {
            abort(403, 'Cet agent n\'est pas sous votre supervision');
        }

        $this->agent = $user;
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $leads = Lead::where('assigned_to', $this->agent->id)->get();

        $this->stats = [
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
            <flux:button href="{{ route('supervisor.agents') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
                {{ __('Retour') }}
            </flux:button>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ __('Statistiques de') }} {{ $agent->name }}
            </h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Performance détaillée de l\'agent') }}
            </p>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Leads totaux') }}</p>
            <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">{{ $stats['total'] }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Confirmés') }}</p>
            <p class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{{ $stats['confirmed'] }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Rejetés') }}</p>
            <p class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">{{ $stats['rejected'] }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Taux de conversion') }}</p>
            <p class="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['conversion_rate'] }}%</p>
        </div>
    </div>
</div>

