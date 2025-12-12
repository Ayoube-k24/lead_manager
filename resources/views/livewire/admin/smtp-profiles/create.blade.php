<?php

use App\Http\Requests\StoreSmtpProfileRequest;
use App\Models\SmtpProfile;
use App\Services\SmtpTestService;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
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

        $service = new SmtpTestService;
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
        // Validate using the FormRequest rules
        $rules = (new StoreSmtpProfileRequest)->rules();
        $validated = $this->validate($rules);

        // Always use password from property (Livewire may not include it in validated data)
        if (! empty($this->password)) {
            $validated['password'] = $this->password;
        }

        // Ensure password is included and not empty
        if (empty($validated['password'])) {
            $this->addError('password', __('Le mot de passe est requis.'));

            return;
        }

        // Log for debugging (without password value)
        \Illuminate\Support\Facades\Log::debug('Creating SMTP profile', [
            'name' => $validated['name'] ?? null,
            'host' => $validated['host'] ?? null,
            'password_provided' => ! empty($validated['password']),
            'password_length' => strlen($validated['password']),
        ]);

        // Create the SMTP profile (password will be encrypted by the mutator)
        // Use new() + fill() + save() instead of create() to have more control
        $smtpProfile = new SmtpProfile;
        $smtpProfile->fill($validated);
        $smtpProfile->save();

        // Verify password was stored by checking the raw database value
        // Refresh the model first to ensure it's in sync with DB
        $smtpProfile->refresh();

        // Get raw password directly from database to avoid accessor interference
        $rawPassword = DB::table('smtp_profiles')
            ->where('id', $smtpProfile->id)
            ->value('password');

        // Also check model attributes for debugging
        $passwordFromAttributes = $smtpProfile->getAttributes()['password'] ?? null;

        \Illuminate\Support\Facades\Log::debug('Password verification after create', [
            'smtp_profile_id' => $smtpProfile->id,
            'password_from_attributes' => $passwordFromAttributes ? substr($passwordFromAttributes, 0, 20).'...' : 'null',
            'password_from_db' => $rawPassword ? substr($rawPassword, 0, 20).'...' : 'null',
            'attributes_length' => $passwordFromAttributes ? strlen($passwordFromAttributes) : 0,
            'db_length' => $rawPassword ? strlen($rawPassword) : 0,
        ]);

        if (empty($rawPassword)) {
            \Illuminate\Support\Facades\Log::error('SMTP profile created but password was not stored', [
                'smtp_profile_id' => $smtpProfile->id,
                'validated_has_password' => ! empty($validated['password']),
            ]);
            session()->flash('error', __('Erreur lors du stockage du mot de passe. Veuillez réessayer.'));

            return;
        }

        // Verify password can be decrypted (to ensure it was encrypted correctly)
        try {
            $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($rawPassword);
            \Illuminate\Support\Facades\Log::debug('SMTP profile password created and encrypted successfully', [
                'smtp_profile_id' => $smtpProfile->id,
                'decrypted_length' => strlen($decrypted),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('SMTP profile password created but cannot be decrypted', [
                'smtp_profile_id' => $smtpProfile->id,
                'error' => $e->getMessage(),
                'raw_password_length' => strlen($rawPassword),
                'raw_password_preview' => substr($rawPassword, 0, 20).'...',
                'password_from_attributes' => $passwordFromAttributes ? substr($passwordFromAttributes, 0, 20).'...' : 'null',
            ]);
            session()->flash('error', __('Le mot de passe a été créé mais ne peut pas être vérifié. Veuillez réessayer.'));

            return;
        }

        // Clear password from component for security
        $this->password = '';

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
                <flux:input wire:model="password" type="password" :label="__('Mot de passe')" required autocomplete="new-password" />
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
