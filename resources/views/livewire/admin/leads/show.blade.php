<?php

use App\Models\Lead;
use Livewire\Volt\Component;

new class extends Component
{
    public Lead $lead;

    public function mount(Lead $lead): void
    {
        $this->lead = $lead->load(['form', 'callCenter', 'assignedAgent']);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:button
                href="{{ route('admin.leads') }}"
                variant="ghost"
                size="sm"
            >
                ← {{ __('Retour') }}
            </flux:button>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ __('Détails du Lead') }}
            </h1>
        </div>
    </div>

    <!-- Informations principales -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Informations du lead') }}
            </h2>
            <dl class="space-y-4">
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Email') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->email }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Statut') }}</dt>
                    <dd class="mt-1">
                        @php
                            $statusLabels = [
                                'pending_email' => __('En attente email'),
                                'email_confirmed' => __('Email confirmé'),
                                'pending_call' => __('En attente d\'appel'),
                                'confirmed' => __('Confirmé'),
                                'rejected' => __('Rejeté'),
                                'callback_pending' => __('En attente de rappel'),
                            ];
                            $statusColors = [
                                'pending_email' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
                                'email_confirmed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
                                'pending_call' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400',
                                'confirmed' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                                'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                                'callback_pending' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400',
                            ];
                        @endphp
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusColors[$this->lead->status] ?? 'bg-neutral-100 text-neutral-800 dark:bg-neutral-900/20 dark:text-neutral-400' }}">
                            {{ $statusLabels[$this->lead->status] ?? $this->lead->status }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Formulaire') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->form?->name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Centre d\'appels') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->callCenter?->name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Agent assigné') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->assignedAgent?->name ?? __('Non assigné') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Date de création') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->created_at->format('d/m/Y H:i') }}</dd>
                </div>
                @if ($this->lead->email_confirmed_at)
                    <div>
                        <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Email confirmé le') }}</dt>
                        <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->email_confirmed_at->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
                @if ($this->lead->called_at)
                    <div>
                        <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Appelé le') }}</dt>
                        <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->called_at->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Données du formulaire') }}
            </h2>
            <dl class="space-y-4">
                @foreach ($this->lead->data ?? [] as $key => $value)
                    @if (!empty($value))
                        <div>
                            <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                            <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">
                                @if (is_array($value))
                                    {{ json_encode($value) }}
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        </div>
    </div>

    <!-- Commentaire d'appel -->
    @if ($this->lead->call_comment)
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Commentaire d\'appel') }}
            </h2>
            <p class="text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->call_comment }}</p>
        </div>
    @endif
</div>

