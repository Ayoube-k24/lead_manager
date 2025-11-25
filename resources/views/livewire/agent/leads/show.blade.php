<?php

use App\Models\Lead;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public Lead $lead;

    public string $status = '';

    public string $comment = '';

    public bool $showModal = false;

    public function mount(Lead $lead): void
    {
        // Vérifier que le lead est attribué à l'agent connecté
        $user = Auth::user();
        if ($lead->assigned_to !== $user->id) {
            abort(403, 'Vous n\'avez pas accès à ce lead.');
        }

        $this->lead = $lead;
        $this->status = $lead->status;
    }

    public function openUpdateModal(): void
    {
        $this->showModal = true;
        // Initialiser avec le statut actuel s'il est valide, sinon utiliser le premier statut post-appel par défaut
        $statusEnum = $this->lead->getStatusEnum();
        $postCallStatuses = \App\LeadStatus::postCallStatuses();
        
        if (in_array($statusEnum, $postCallStatuses)) {
            $this->status = $statusEnum->value;
        } else {
            // Utiliser 'qualified' par défaut pour les nouveaux appels
            $this->status = \App\LeadStatus::Qualified->value;
        }
        
        $this->comment = $this->lead->call_comment ?? '';
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->status = '';
        $this->comment = '';
    }

    public function updateStatus(): void
    {
        // Get valid status values from enum
        $validStatuses = array_map(
            fn($status) => $status->value,
            \App\LeadStatus::postCallStatuses()
        );
        
        $this->validate([
            'status' => ['required', 'in:'.implode(',', $validStatuses)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->lead->updateAfterCall($this->status, $this->comment);

        $this->closeModal();
        $this->dispatch('lead-updated');
    }

    public function getStatusOptionsProperty(): array
    {
        // Get all post-call statuses from the enum
        $statuses = \App\LeadStatus::postCallStatuses();
        $options = [];
        
        foreach ($statuses as $status) {
            $options[$status->value] = $status->label();
        }
        
        return $options;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:button
                href="{{ route('agent.leads') }}"
                variant="ghost"
                size="sm"
            >
                ← {{ __('Retour') }}
            </flux:button>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ __('Détails du Lead') }}
            </h1>
        </div>
        @php
            $statusEnum = $this->lead->getStatusEnum();
            $canUpdate = $statusEnum->isActive() || in_array($statusEnum, \App\LeadStatus::postCallStatuses());
        @endphp
        @if ($canUpdate)
            <flux:button wire:click="openUpdateModal" variant="primary">
                {{ __('Mettre à jour le statut') }}
            </flux:button>
        @endif
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
                            $statusEnum = $this->lead->getStatusEnum();
                        @endphp
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusEnum->colorClass() }}">
                            {{ $statusEnum->label() }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Formulaire') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->form?->name ?? 'N/A' }}</dd>
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

    <!-- Historique des statuts -->
    @php
        $statusHistory = $this->lead->getStatusHistory();
    @endphp
    @if ($statusHistory->isNotEmpty())
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Historique des statuts') }}
            </h2>
            <div class="space-y-3">
                @foreach ($statusHistory as $log)
                    @php
                        $oldStatus = \App\LeadStatus::tryFrom($log->properties['old_status'] ?? '');
                        $newStatus = \App\LeadStatus::tryFrom($log->properties['new_status'] ?? '');
                    @endphp
                    <div class="flex items-start gap-3 border-b border-neutral-200 pb-3 last:border-0 dark:border-neutral-700">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                @if ($oldStatus && $newStatus)
                                    <span class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $oldStatus->label() }}
                                    </span>
                                    <span class="text-neutral-400">→</span>
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $newStatus->colorClass() }}">
                                        {{ $newStatus->label() }}
                                    </span>
                                @else
                                    <span class="text-sm text-neutral-900 dark:text-neutral-100">
                                        {{ $log->properties['new_status'] ?? 'N/A' }}
                                    </span>
                                @endif
                            </div>
                            @if (!empty($log->properties['comment']))
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $log->properties['comment'] }}
                                </p>
                            @endif
                            <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">
                                {{ $log->created_at->format('d/m/Y H:i') }}
                                @if ($log->user)
                                    • {{ $log->user->name }}
                                @endif
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Modal de mise à jour -->
    <flux:modal wire:model="showModal" name="update-status">
        <form wire:submit="updateStatus" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Mettre à jour le statut') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Mettez à jour le statut de ce lead après votre appel téléphonique.') }}
                </p>
            </div>

            <flux:select wire:model="status" :label="__('Nouveau statut')" required>
                <option value="">{{ __('Sélectionner un statut') }}</option>
                @foreach ($this->statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="comment"
                :label="__('Commentaire d\'appel')"
                :placeholder="__('Décrivez le résultat de votre appel...')"
                rows="5"
            />

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Enregistrer') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

