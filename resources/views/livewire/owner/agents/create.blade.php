<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function store(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = Auth::user();
        $callCenter = $user->callCenter;

        if (! $callCenter) {
            $this->addError('call_center', 'Vous devez être associé à un centre d\'appels.');
            return;
        }

        $agentRole = Role::where('slug', 'agent')->first();

        if (! $agentRole) {
            $this->addError('role', 'Le rôle agent n\'existe pas.');
            return;
        }

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'email_verified_at' => now(),
        ]);

        $this->redirect(route('owner.agents'), navigate: true);
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
                {{ __('Créer un agent') }}
            </h1>
        </div>
    </div>

    <form wire:submit="store" class="space-y-6">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Informations de l\'agent') }}</h2>
            <div class="space-y-4">
                <flux:input wire:model.blur="name" :label="__('Nom')" required autofocus />
                <flux:input wire:model.blur="email" type="email" :label="__('Email')" required />
                <flux:input wire:model.blur="password" type="password" :label="__('Mot de passe')" required />
                <flux:input wire:model.blur="password_confirmation" type="password" :label="__('Confirmer le mot de passe')" required />
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <flux:button href="{{ route('owner.agents') }}" variant="ghost">
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Créer l\'agent') }}
            </flux:button>
        </div>
    </form>
</div>

