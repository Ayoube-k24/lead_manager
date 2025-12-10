<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Livewire\Volt\Component;

new class extends Component
{
    public User $agent;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $is_active = true;
    public ?int $supervisor_id = null;
    public bool $showMfaModal = false;

    public function mount(User $user): void
    {
        $owner = Auth::user();
        if ($user->call_center_id !== $owner->call_center_id) {
            abort(403);
        }

        $this->agent = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->is_active = (bool) $user->is_active;
        $this->supervisor_id = $user->supervisor_id;
    }

    public function getSupervisorsProperty()
    {
        $owner = Auth::user();
        return User::where('call_center_id', $owner->call_center_id)
            ->whereHas('role', fn($q) => $q->where('slug', 'supervisor'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function update(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$this->agent->id],
            'is_active' => ['boolean'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
        ];

        if (! empty($this->password)) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        $validated = $this->validate($rules);

        $this->agent->name = $validated['name'];
        $this->agent->email = $validated['email'];
        $this->agent->is_active = (bool) ($validated['is_active'] ?? $this->agent->is_active);
        $this->agent->supervisor_id = $validated['supervisor_id'] ?? null;

        if (! empty($validated['password'] ?? null)) {
            $this->agent->password = Hash::make($validated['password']);
        }

        $this->agent->save();

        $this->redirect(route('owner.agents'), navigate: true);
    }

    public function initializePassword(): void
    {
        $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->agent->password = Hash::make($this->password);
        $this->agent->save();

        $this->reset(['password', 'password_confirmation']);
        $this->dispatch('password-initialized', message: __('Mot de passe initialisé avec succès.'));
    }

    public function openMfaModal(): void
    {
        $this->showMfaModal = true;
    }

    public function closeMfaModal(): void
    {
        $this->showMfaModal = false;
    }

    public function disableMfa(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication($this->agent);
        $this->closeMfaModal();
        $this->dispatch('mfa-disabled', message: __('MFA désactivé avec succès.'));
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
                {{ __('Modifier l\'agent') }}
            </h1>
        </div>
    </div>

    <form wire:submit="update" class="space-y-6">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Informations de l\'agent') }}</h2>
            <div class="space-y-4">
                <flux:input wire:model.blur="name" :label="__('Nom')" required autofocus />
                <flux:input wire:model.blur="email" type="email" :label="__('Email')" required />
                <flux:select wire:model.blur="supervisor_id" :label="__('Superviseur (optionnel)')">
                    <option value="">{{ __('Aucun superviseur') }}</option>
                    @foreach($this->supervisors as $supervisor)
                        <option value="{{ $supervisor->id }}">{{ $supervisor->name }}</option>
                    @endforeach
                </flux:select>
                <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <div>
                        <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Agent actif') }}</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Désactivez pour suspendre temporairement cet agent.') }}</p>
                    </div>
                    <flux:switch wire:model="is_active" />
                </div>
            </div>
        </div>

        <!-- Section Gestion du Mot de passe -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Gestion du Mot de passe') }}</h2>
            <div class="space-y-4">
                <flux:input wire:model.blur="password" type="password" :label="__('Nouveau mot de passe')" />
                <flux:input wire:model.blur="password_confirmation" type="password" :label="__('Confirmer le nouveau mot de passe')" />
                <flux:button
                    wire:click="initializePassword"
                    wire:confirm="{{ __('Êtes-vous sûr de vouloir initialiser le mot de passe de cet agent ?') }}"
                    variant="primary"
                    type="button"
                >
                    {{ __('Initialiser le mot de passe') }}
                </flux:button>
            </div>
        </div>

        <!-- Section Gestion du MFA -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Authentification à deux facteurs (MFA)') }}</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <div>
                        <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Statut MFA') }}</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">
                            @if ($agent->hasEnabledTwoFactorAuthentication())
                                {{ __('L\'authentification à deux facteurs est activée pour cet agent.') }}
                            @else
                                {{ __('L\'authentification à deux facteurs n\'est pas activée.') }}
                            @endif
                        </p>
                    </div>
                    <div>
                        @if ($agent->hasEnabledTwoFactorAuthentication())
                            <span class="inline-flex rounded-full bg-orange-100 px-2 py-1 text-xs font-semibold text-orange-800 dark:bg-orange-900/20 dark:text-orange-300">
                                {{ __('Activé') }}
                            </span>
                        @else
                            <span class="inline-flex rounded-full bg-neutral-200 px-2 py-1 text-xs font-semibold text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ __('Désactivé') }}
                            </span>
                        @endif
                    </div>
                </div>
                @if ($agent->hasEnabledTwoFactorAuthentication())
                    <flux:button
                        wire:click="openMfaModal"
                        variant="danger"
                        type="button"
                        icon="shield-exclamation"
                    >
                        {{ __('Désactiver MFA') }}
                    </flux:button>
                @endif
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <flux:button href="{{ route('owner.agents') }}" variant="ghost">
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Enregistrer') }}
            </flux:button>
        </div>
    </form>

    <!-- Modal Désactivation MFA -->
    @if ($showMfaModal)
        <flux:modal name="mfa-modal" :close-button="true">
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Désactiver l\'authentification à deux facteurs') }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Êtes-vous sûr de vouloir désactiver l\'authentification à deux facteurs pour cet agent ?') }}
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
</script>
@endscript

