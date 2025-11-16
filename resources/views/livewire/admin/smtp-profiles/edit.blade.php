<?php

use App\Http\Requests\UpdateSmtpProfileRequest;
use App\Models\SmtpProfile;
use Livewire\Volt\Component;

new class extends Component {
    public SmtpProfile $smtpProfile;
    public string $name = '';
    public string $host = '';
    public int $port = 587;
    public string $encryption = 'tls';
    public string $username = '';
    public string $password = '';
    public string $from_address = '';
    public ?string $from_name = null;
    public bool $is_active = true;

    public function mount(SmtpProfile $smtpProfile): void
    {
        $this->smtpProfile = $smtpProfile;
        $this->name = $smtpProfile->name;
        $this->host = $smtpProfile->host;
        $this->port = $smtpProfile->port;
        $this->encryption = $smtpProfile->encryption;
        $this->username = $smtpProfile->username;
        $this->password = ''; // Don't pre-fill password
        $this->from_address = $smtpProfile->from_address;
        $this->from_name = $smtpProfile->from_name;
        $this->is_active = $smtpProfile->is_active;
    }

    public function update(): void
    {
        $rules = (new UpdateSmtpProfileRequest)->rules();
        
        // Only require password if it's being changed
        if (empty($this->password)) {
            unset($rules['password']);
        }

        $validated = $this->validate($rules);

        // Don't update password if it's empty
        if (empty($validated['password'] ?? '')) {
            unset($validated['password']);
        }

        $this->smtpProfile->update($validated);

        session()->flash('message', __('Profil SMTP modifié avec succès !'));

        $this->redirect(route('admin.smtp-profiles'), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumb avec bouton de retour -->
    <div class="flex items-center justify-between">
        <flux:button href="{{ route('admin.smtp-profiles') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
            {{ __('Retour') }}
        </flux:button>
        <nav class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
            <a href="{{ route('admin.smtp-profiles') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
                {{ __('Profils SMTP') }}
            </a>
            <span>/</span>
            <span class="text-neutral-900 dark:text-neutral-100">{{ $smtpProfile->name }}</span>
        </nav>
    </div>

    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold">{{ __('Modifier le profil SMTP') }}</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Modifiez les paramètres du profil SMTP') }}</p>
    </div>

    <!-- Formulaire -->
    <form wire:submit="update" class="space-y-6">
        <!-- Informations générales -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Informations générales') }}</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model.blur="name" :label="__('Nom du profil')" required autofocus />
                <flux:input wire:model.blur="host" :label="__('Serveur SMTP')" required />
                <flux:input wire:model.blur="port" type="number" :label="__('Port')" required />
                <flux:select wire:model="encryption" :label="__('Chiffrement')" required>
                    <option value="tls">{{ __('TLS (recommandé)') }}</option>
                    <option value="ssl">{{ __('SSL') }}</option>
                    <option value="none">{{ __('Aucun') }}</option>
                </flux:select>
            </div>
        </div>

        <!-- Identifiants -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Identifiants de connexion') }}</h2>
            <div class="space-y-4">
                <flux:input wire:model.blur="username" :label="__('Nom d\'utilisateur')" required />
                <flux:input wire:model.blur="password" type="password" :label="__('Mot de passe')" :placeholder="__('Laisser vide pour ne pas modifier')" />
                <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ __('Laissez le champ vide si vous ne souhaitez pas modifier le mot de passe') }}
                </flux:text>
            </div>
        </div>

        <!-- Expéditeur -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Expéditeur par défaut') }}</h2>
            <div class="space-y-4">
                <flux:input wire:model.blur="from_address" type="email" :label="__('Adresse email expéditeur')" required />
                <flux:input wire:model.blur="from_name" :label="__('Nom de l\'expéditeur')" />
            </div>
        </div>

        <!-- Statut -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <flux:switch wire:model="is_active" :label="__('Profil actif')" />
            <flux:text class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                {{ __('Seuls les profils actifs peuvent être utilisés dans les formulaires') }}
            </flux:text>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between border-t border-neutral-200 pt-6 dark:border-neutral-700">
            <flux:button href="{{ route('admin.smtp-profiles') }}" variant="ghost" wire:navigate>
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="update">{{ __('Enregistrer les modifications') }}</span>
                <span wire:loading wire:target="update">{{ __('Enregistrement...') }}</span>
            </flux:button>
        </div>
    </form>
</div>
