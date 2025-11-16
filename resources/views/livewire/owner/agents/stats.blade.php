<?php

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public User $agent;

    public function mount(User $user): void
    {
        $owner = Auth::user();
        if ($user->call_center_id !== $owner->call_center_id) {
            abort(403);
        }

        $this->agent = $user;
    }

    public function getStatsProperty(): array
    {
        $leads = Lead::where('assigned_to', $this->agent->id)->get();

        $total = $leads->count();
        $confirmed = $leads->where('status', 'confirmed')->count();
        $rejected = $leads->where('status', 'rejected')->count();
        $pending = $leads->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])->count();

        return [
            'total' => $total,
            'confirmed' => $confirmed,
            'rejected' => $rejected,
            'pending' => $pending,
            'conversion_rate' => $total > 0 ? round(($confirmed / $total) * 100, 2) : 0,
            'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 2) : 0,
            'average_time_to_confirm' => $this->calculateAverageTimeToConfirm(),
        ];
    }

    public function getRecentLeadsProperty()
    {
        return Lead::where('assigned_to', $this->agent->id)
            ->with(['form'])
            ->latest()
            ->limit(10)
            ->get();
    }

    protected function calculateAverageTimeToConfirm(): ?float
    {
        $confirmedLeads = Lead::where('assigned_to', $this->agent->id)
            ->where('status', 'confirmed')
            ->whereNotNull('called_at')
            ->whereNotNull('email_confirmed_at')
            ->get();

        if ($confirmedLeads->isEmpty()) {
            return null;
        }

        $totalHours = $confirmedLeads->sum(function ($lead) {
            return $lead->email_confirmed_at->diffInHours($lead->called_at);
        });

        return round($totalHours / $confirmedLeads->count(), 2);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:button
                href="{{ route('owner.agents') }}"
                variant="ghost"
                size="sm"
            >
                ← {{ __('Retour') }}
            </flux:button>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ __('Statistiques de :name', ['name' => $this->agent->name]) }}
            </h1>
        </div>
    </div>

    <!-- Statistiques principales -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Total leads') }}</div>
            <div class="mt-1 text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ $this->stats['total'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Confirmés') }}</div>
            <div class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->stats['confirmed'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Rejetés') }}</div>
            <div class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->stats['rejected'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ __('Taux de conversion') }}</div>
            <div class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->stats['conversion_rate'] }}%</div>
        </div>
    </div>

    <!-- Statistiques détaillées -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Détails') }}</h2>
            <dl class="space-y-4">
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('En attente') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->stats['pending'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Taux de rejet') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->stats['rejection_rate'] }}%</dd>
                </div>
                @if ($this->stats['average_time_to_confirm'] !== null)
                    <div>
                        <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Temps moyen de confirmation') }}</dt>
                        <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->stats['average_time_to_confirm'] }} heures</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Derniers leads') }}</h2>
            <div class="space-y-2">
                @forelse ($this->recentLeads as $lead)
                    <div class="flex items-center justify-between border-b border-neutral-200 py-2 dark:border-neutral-700">
                        <div>
                            <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $lead->email }}</div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ $lead->form?->name ?? 'N/A' }}</div>
                        </div>
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ $lead->created_at->format('d/m/Y') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucun lead') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

