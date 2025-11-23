<?php

use App\Http\Requests\StoreSmtpProfileRequest;
use App\Models\SmtpProfile;
use App\Services\SmtpTestService;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $host = '';
    public int $port = 587;
    public string $encryption = 'tls';
    public string $username = '';
    public string $password = '';
    public string $from_address = '';
    public ?string $from_name = null;
    public bool $is_active = true;
    public ?string $testResult = null;
    public bool $testSuccess = false;
    public bool $isTesting = false;

    public function testConnection(): void
    {
        $this->isTesting = true;
        $this->testResult = null;
        $this->testSuccess = false;

        $this->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'string'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $service = new SmtpTestService();
        $result = $service->testConnection([
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'password' => $this->password,
        ]);

        $this->testSuccess = $result['success'];
        $this->testResult = $result['message'];
        $this->isTesting = false;
    }

    public function store(): void
    {
        $validated = $this->validate((new StoreSmtpProfileRequest)->rules());

        SmtpProfile::create($validated);

        session()->flash('message', __('Profil SMTP créé avec succès !'));

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
            <span class="text-neutral-900 dark:text-neutral-100">{{ __('Créer') }}</span>
        </nav>
    </div>

    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold">{{ __('Créer un profil SMTP') }}</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Configurez un nouveau profil SMTP réutilisable pour l\'envoi d\'emails') }}</p>
    </div>

    <!-- Formulaire -->
    <form wire:submit="store" class="space-y-6">
        <!-- Informations générales -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Informations générales') }}</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model.blur="name" :label="__('Nom du profil')" required autofocus />
                <flux:input wire:model.blur="host" :label="__('Serveur SMTP')" placeholder="smtp.example.com" required />
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
                <flux:input wire:model.blur="password" type="password" :label="__('Mot de passe')" required />
                <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ __('Le mot de passe sera chiffré de manière sécurisée') }}
                </flux:text>
            </div>
        </div>

        <!-- Expéditeur -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Expéditeur par défaut') }}</h2>
            <div class="space-y-4">
                <flux:input wire:model.blur="from_address" type="email" :label="__('Adresse email expéditeur')" placeholder="noreply@example.com" required />
                <flux:input wire:model.blur="from_name" :label="__('Nom de l\'expéditeur')" placeholder="Lead Manager" />
            </div>
        </div>

        <!-- Test de connexion -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Test de connexion SMTP') }}</h2>
            <p class="mb-4 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Testez la connexion avec le serveur SMTP avant de sauvegarder le profil') }}
            </p>
            
            <div class="space-y-4">
                <flux:button 
                    wire:click="testConnection" 
                    variant="outline" 
                    wire:loading.attr="disabled"
                    wire:target="testConnection"
                >
                    <span wire:loading.remove wire:target="testConnection">
                        {{ __('Tester la connexion') }}
                    </span>
                    <span wire:loading wire:target="testConnection">
                        {{ __('Test en cours...') }}
                    </span>
                </flux:button>

                @if ($testResult)
                    <div class="rounded-lg border p-4 {{ $testSuccess ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20' }}">
                        <div class="flex items-start gap-3">
                            @if ($testSuccess)
                                <svg class="h-5 w-5 shrink-0 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            @else
                                <svg class="h-5 w-5 shrink-0 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            @endif
                            <p class="text-sm {{ $testSuccess ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                                {{ $testResult }}
                            </p>
                        </div>
                    </div>
                @endif
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
                <span wire:loading.remove wire:target="store">{{ __('Créer le profil') }}</span>
                <span wire:loading wire:target="store">{{ __('Création en cours...') }}</span>
            </flux:button>
        </div>
    </form>
</div>
