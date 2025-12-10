<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = 'all'; // all, call_center_owner, supervisor

    public ?int $selectedUserId = null;

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public bool $showPasswordModal = false;

    public bool $showMfaModal = false;

    public bool $showResetAccessModal = false;

    public bool $disableMfaOnReset = true;

    public string $activeTab = 'access'; // access, alerts

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
        $query = User::whereHas('role', function ($q) {
            if ($this->roleFilter === 'call_center_owner') {
                $q->where('slug', 'call_center_owner');
            } elseif ($this->roleFilter === 'supervisor') {
                $q->where('slug', 'supervisor');
            } else {
                $q->whereIn('slug', ['call_center_owner', 'supervisor']);
            }
        })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->with(['role', 'callCenter'])
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

    public function openResetAccessModal(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
        $this->disableMfaOnReset = true;
        $this->showResetAccessModal = true;
    }

    public function closeResetAccessModal(): void
    {
        $this->showResetAccessModal = false;
        $this->selectedUserId = null;
        $this->reset(['newPassword', 'newPasswordConfirmation', 'disableMfaOnReset']);
    }

    public function initializePassword(): void
    {
        $this->validate([
            'selectedUserId' => ['required', 'exists:users,id'],
            'newPassword' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::findOrFail($this->selectedUserId);

        // Vérifier que c'est un OWNER ou Supervisor
        if (! in_array($user->role?->slug, ['call_center_owner', 'supervisor'])) {
            $this->addError('user', 'Seuls les propriétaires de centres d\'appels et les superviseurs peuvent avoir leur mot de passe initialisé.');

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

        // Vérifier que c'est un OWNER ou Supervisor
        if (! in_array($user->role?->slug, ['call_center_owner', 'supervisor'])) {
            $this->addError('user', 'Seuls les propriétaires de centres d\'appels et les superviseurs peuvent avoir leur MFA désactivé.');

            return;
        }

        $disableTwoFactorAuthentication($user);

        $this->closeMfaModal();
        $this->dispatch('mfa-disabled', message: __('MFA désactivé avec succès.'));
    }

    public function resetAccess(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->validate([
            'selectedUserId' => ['required', 'exists:users,id'],
            'newPassword' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::findOrFail($this->selectedUserId);

        // Vérifier que c'est un OWNER ou Supervisor
        if (! in_array($user->role?->slug, ['call_center_owner', 'supervisor'])) {
            $this->addError('user', 'Seuls les propriétaires de centres d\'appels et les superviseurs peuvent avoir leur accès réinitialisé.');

            return;
        }

        // Vérifier si MFA est activé avant de modifier l'utilisateur
        $mfaWasEnabled = $user->hasEnabledTwoFactorAuthentication();

        // Initialiser le mot de passe
        $user->password = Hash::make($this->newPassword);
        $user->save();

        // Désactiver MFA si demandé et si activé
        $mfaDisabled = false;
        if ($this->disableMfaOnReset && $mfaWasEnabled) {
            $disableTwoFactorAuthentication($user);
            $mfaDisabled = true;
        }

        $this->closeResetAccessModal();
        $this->resetPage();
        
        $message = __('Accès réinitialisé avec succès. Le mot de passe a été initialisé');
        if ($mfaDisabled) {
            $message .= ' '.__('et le MFA a été désactivé');
        }
        $message .= '.';
        
        $this->dispatch('access-reset', message: $message);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Paramètres Super Admin') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Gérez les alertes et les accès des propriétaires et superviseurs') }}
            </p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-neutral-200 dark:border-neutral-700">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button
                wire:click="$set('activeTab', 'access')"
                class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium transition-colors {{ $activeTab === 'access' ? 'border-purple-500 text-purple-600 dark:text-purple-400' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
            >
                {{ __('Gestion des Accès') }}
            </button>
            <button
                wire:click="$set('activeTab', 'alerts')"
                class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium transition-colors {{ $activeTab === 'alerts' ? 'border-purple-500 text-purple-600 dark:text-purple-400' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
            >
                {{ __('Alertes') }}
            </button>
        </nav>
    </div>

    <!-- Access Management Tab -->
    @if ($activeTab === 'access')
        <div class="flex flex-col gap-6">
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
                    <option value="call_center_owner">{{ __('Propriétaires') }}</option>
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
                                    {{ __('Centre d\'Appels') }}
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
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $user->callCenter?->name ?? __('N/A') }}
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
                                                wire:click="openResetAccessModal({{ $user->id }})"
                                                variant="primary"
                                                size="sm"
                                                icon="arrow-path"
                                            >
                                                {{ __('Réinitialiser l\'accès') }}
                                            </flux:button>
                                            <flux:dropdown position="bottom" align="end">
                                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical">
                                                    {{ __('Plus') }}
                                                </flux:button>
                                                <flux:menu>
                                                    <flux:menu.radio.group>
                                                        <flux:menu.item
                                                            wire:click="openPasswordModal({{ $user->id }})"
                                                            icon="key"
                                                        >
                                                            {{ __('Initialiser mot de passe') }}
                                                        </flux:menu.item>
                                                        @if ($user->hasEnabledTwoFactorAuthentication())
                                                            <flux:menu.item
                                                                wire:click="openMfaModal({{ $user->id }})"
                                                                icon="shield-exclamation"
                                                            >
                                                                {{ __('Désactiver MFA') }}
                                                            </flux:menu.item>
                                                        @endif
                                                    </flux:menu.radio.group>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
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
        </div>
    @endif

    <!-- Alerts Tab -->
    @if ($activeTab === 'alerts')
        <div class="flex flex-col gap-6">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Gestion des Alertes') }}</h2>
                        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Configurez et gérez vos alertes depuis cette section') }}
                        </p>
                    </div>
                    <flux:button href="{{ route('settings.alerts') }}" variant="primary" wire:navigate>
                        {{ __('Gérer les Alertes') }}
                    </flux:button>
                </div>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Cliquez sur "Gérer les Alertes" pour accéder à la page complète de gestion des alertes où vous pourrez créer, modifier et supprimer vos alertes.') }}
                </p>
            </div>
        </div>
    @endif

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

    <!-- Modal Réinitialisation Accès (Mot de passe + MFA) -->
    @if ($showResetAccessModal)
        <flux:modal name="reset-access-modal" :close-button="true">
            <form wire:submit="resetAccess" class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Réinitialiser l\'accès') }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Initialisez le mot de passe et désactivez le MFA pour cet utilisateur.') }}
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

                    <flux:checkbox
                        wire:model="disableMfaOnReset"
                        :label="__('Désactiver l\'authentification à deux facteurs (MFA)')"
                    />
                    <flux:description>
                        {{ __('Si coché, le MFA sera désactivé en plus de l\'initialisation du mot de passe.') }}
                    </flux:description>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <flux:button
                        type="button"
                        wire:click="closeResetAccessModal"
                        variant="ghost"
                    >
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Réinitialiser l\'accès') }}
                    </flux:button>
                </div>
            </form>
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

    $wire.on('access-reset', (event) => {
        $dispatch('notify', {
            message: event.message || 'Accès réinitialisé avec succès.',
            type: 'success'
        });
    });
</script>
@endscript

