<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = 'all'; // all, agent, supervisor

    public ?int $selectedUserId = null;

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public bool $showPasswordModal = false;

    public bool $showMfaModal = false;

    public function mount(): void
    {
        //
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function getUsersProperty()
    {
        $owner = Auth::user();
        $callCenter = $owner->callCenter;

        if (! $callCenter) {
            return collect();
        }

        $query = User::where('call_center_id', $callCenter->id)
            ->whereHas('role', function ($q) {
                if ($this->roleFilter === 'agent') {
                    $q->where('slug', 'agent');
                } elseif ($this->roleFilter === 'supervisor') {
                    $q->where('slug', 'supervisor');
                } else {
                    $q->whereIn('slug', ['agent', 'supervisor']);
                }
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->with(['role', 'supervisor'])
            ->orderByDesc('is_active')
            ->latest();

        return $query->paginate(15);
    }

    public function openPasswordModal(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
        $this->showPasswordModal = true;
    }

    public function closePasswordModal(): void
    {
        $this->showPasswordModal = false;
        $this->selectedUserId = null;
        $this->reset(['newPassword', 'newPasswordConfirmation']);
    }

    public function initializePassword(): void
    {
        $this->validate([
            'selectedUserId' => ['required', 'exists:users,id'],
            'newPassword' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::findOrFail($this->selectedUserId);
        $owner = Auth::user();

        // Vérifier que l'utilisateur appartient au centre d'appels du propriétaire
        if ($user->call_center_id !== $owner->call_center_id) {
            $this->addError('user', 'Utilisateur non autorisé.');

            return;
        }

        // Vérifier que c'est un agent ou superviseur
        if (! in_array($user->role?->slug, ['agent', 'supervisor'])) {
            $this->addError('user', 'Seuls les agents et superviseurs peuvent avoir leur mot de passe initialisé.');

            return;
        }

        $user->password = Hash::make($this->newPassword);
        $user->save();

        $this->closePasswordModal();
        $this->dispatch('password-initialized', message: __('Mot de passe initialisé avec succès.'));
    }

    public function openMfaModal(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->showMfaModal = true;
    }

    public function closeMfaModal(): void
    {
        $this->showMfaModal = false;
        $this->selectedUserId = null;
    }

    public function disableMfa(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        if (! $this->selectedUserId) {
            return;
        }

        $user = User::findOrFail($this->selectedUserId);
        $owner = Auth::user();

        // Vérifier que l'utilisateur appartient au centre d'appels du propriétaire
        if ($user->call_center_id !== $owner->call_center_id) {
            $this->addError('user', 'Utilisateur non autorisé.');

            return;
        }

        // Vérifier que c'est un agent ou superviseur
        if (! in_array($user->role?->slug, ['agent', 'supervisor'])) {
            $this->addError('user', 'Seuls les agents et superviseurs peuvent avoir leur MFA désactivé.');

            return;
        }

        $disableTwoFactorAuthentication($user);

        $this->closeMfaModal();
        $this->dispatch('mfa-disabled', message: __('MFA désactivé avec succès.'));
    }

    public function toggleStatus(int $userId): void
    {
        $user = User::find($userId);

        if (! $user) {
            $this->dispatch('user-error', message: __('Utilisateur introuvable.'));

            return;
        }

        $owner = Auth::user();

        // Vérifier que l'utilisateur appartient au centre d'appels du propriétaire
        if ($user->call_center_id !== $owner->call_center_id) {
            $this->dispatch('user-error', message: __('Vous n\'êtes pas autorisé à modifier cet utilisateur.'));

            return;
        }

        // Vérifier que c'est un agent ou superviseur
        if (! in_array($user->role?->slug, ['agent', 'supervisor'])) {
            $this->dispatch('user-error', message: __('Seuls les agents et superviseurs peuvent être activés/désactivés.'));

            return;
        }

        // Pour les agents, vérifier qu'il n'y a pas de leads assignés si on désactive
        if ($user->role?->slug === 'agent' && $user->is_active) {
            $hasActiveLeads = $user->assignedLeads()
                ->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])
                ->exists();

            if ($hasActiveLeads) {
                $this->dispatch('user-has-leads');

                return;
            }
        }

        // Toggle le statut
        $user->is_active = ! $user->is_active;
        $saved = $user->save();

        if (! $saved) {
            $this->dispatch('user-error', message: __('Erreur lors de la modification du statut de l\'utilisateur.'));

            return;
        }

        // Rafraîchir l'utilisateur pour s'assurer d'avoir la valeur à jour
        $user->refresh();

        $this->resetPage();
        $this->dispatch($user->is_active ? 'user-activated' : 'user-deactivated');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Gestion des Accès') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Gérez les accès, mots de passe et MFA de vos agents et superviseurs') }}
            </p>
        </div>
    </div>

    <!-- Filtres -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Rechercher')"
            placeholder="{{ __('Nom, email...') }}"
            class="flex-1"
        />

        <flux:select wire:model.live="roleFilter" :label="__('Rôle')" class="sm:w-48">
            <option value="all">{{ __('Tous') }}</option>
            <option value="agent">{{ __('Agents') }}</option>
            <option value="supervisor">{{ __('Superviseurs') }}</option>
        </flux:select>
    </div>

    <!-- Liste des utilisateurs -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Nom') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Email') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Rôle') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Statut') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('MFA') }}
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-neutral-800">
                    @forelse ($this->users as $user)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                {{ $user->name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $user->email }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $user->role?->slug === 'supervisor' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300' }}">
                                    {{ $user->role?->name }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($user->is_active)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300">
                                        {{ __('Actif') }}
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-neutral-200 px-2 py-1 text-xs font-semibold text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                        {{ __('Désactivé') }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($user->hasEnabledTwoFactorAuthentication())
                                    <span class="inline-flex rounded-full bg-orange-100 px-2 py-1 text-xs font-semibold text-orange-800 dark:bg-orange-900/20 dark:text-orange-300">
                                        {{ __('Activé') }}
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-neutral-200 px-2 py-1 text-xs font-semibold text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                        {{ __('Désactivé') }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button
                                        wire:click="openPasswordModal({{ $user->id }})"
                                        variant="ghost"
                                        size="sm"
                                        icon="key"
                                    >
                                        {{ __('Mot de passe') }}
                                    </flux:button>
                                    @if ($user->hasEnabledTwoFactorAuthentication())
                                        <flux:button
                                            wire:click="openMfaModal({{ $user->id }})"
                                            variant="ghost"
                                            size="sm"
                                            icon="shield-exclamation"
                                        >
                                            {{ __('Désactiver MFA') }}
                                        </flux:button>
                                    @endif
                                    <flux:button
                                        wire:click="toggleStatus({{ $user->id }})"
                                        wire:confirm="{{ $user->is_active ? __('Désactiver cet utilisateur ?') : __('Réactiver cet utilisateur ?') }}"
                                        variant="{{ $user->is_active ? 'danger' : 'primary' }}"
                                        size="sm"
                                    >
                                        {{ $user->is_active ? __('Désactiver') : __('Réactiver') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Aucun utilisateur trouvé') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->users->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                {{ $this->users->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Initialisation Mot de passe -->
    @if ($showPasswordModal)
        <flux:modal name="password-modal" :close-button="true">
            <form wire:submit="initializePassword" class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Initialiser le mot de passe') }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Définissez un nouveau mot de passe pour cet utilisateur.') }}
                    </p>
                </div>

                <div class="space-y-4">
                    <flux:input
                        wire:model.blur="newPassword"
                        type="password"
                        :label="__('Nouveau mot de passe')"
                        required
                        autofocus
                    />
                    @error('newPassword')
                        <flux:callout variant="danger">{{ $message }}</flux:callout>
                    @enderror

                    <flux:input
                        wire:model.blur="newPasswordConfirmation"
                        type="password"
                        :label="__('Confirmer le mot de passe')"
                        required
                    />
                    @error('newPasswordConfirmation')
                        <flux:callout variant="danger">{{ $message }}</flux:callout>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-3">
                    <flux:button
                        type="button"
                        wire:click="closePasswordModal"
                        variant="ghost"
                    >
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Initialiser') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <!-- Modal Désactivation MFA -->
    @if ($showMfaModal)
        <flux:modal name="mfa-modal" :close-button="true">
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Désactiver l\'authentification à deux facteurs') }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Êtes-vous sûr de vouloir désactiver l\'authentification à deux facteurs pour cet utilisateur ?') }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <flux:button
                        type="button"
                        wire:click="closeMfaModal"
                        variant="ghost"
                    >
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button
                        wire:click="disableMfa"
                        variant="danger"
                    >
                        {{ __('Désactiver MFA') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>

@script
<script>
    $wire.on('password-initialized', (event) => {
        $dispatch('notify', {
            message: event.message || 'Mot de passe initialisé avec succès.',
            type: 'success'
        });
    });

    $wire.on('mfa-disabled', (event) => {
        $dispatch('notify', {
            message: event.message || 'MFA désactivé avec succès.',
            type: 'success'
        });
    });

    $wire.on('user-activated', () => {
        $dispatch('notify', {
            message: 'Utilisateur activé avec succès.',
            type: 'success'
        });
    });

    $wire.on('user-deactivated', () => {
        $dispatch('notify', {
            message: 'Utilisateur désactivé avec succès.',
            type: 'success'
        });
    });

    $wire.on('user-error', (event) => {
        $dispatch('notify', {
            message: event.message || 'Erreur lors de la modification de l\'utilisateur.',
            type: 'danger'
        });
    });

    $wire.on('user-has-leads', () => {
        $dispatch('notify', {
            message: 'Impossible de désactiver cet agent car il a des leads en cours. Réassignez-les d\'abord.',
            type: 'warning'
        });
    });
</script>
@endscript

