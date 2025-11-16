<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Component;

new class extends Component
{
    public User $agent;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(User $user): void
    {
        $owner = Auth::user();
        if ($user->call_center_id !== $owner->call_center_id) {
            abort(403);
        }

        $this->agent = $user;
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function update(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$this->agent->id],
        ];

        if (! empty($this->password)) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        $validated = $this->validate($rules);

        $this->agent->name = $validated['name'];
        $this->agent->email = $validated['email'];

        if (! empty($validated['password'] ?? null)) {
            $this->agent->password = Hash::make($validated['password']);
        }

        $this->agent->save();

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
                <flux:input wire:model.blur="password" type="password" :label="__('Nouveau mot de passe (optionnel)')" />
                <flux:input wire:model.blur="password_confirmation" type="password" :label="__('Confirmer le nouveau mot de passe')" />
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
</div>

