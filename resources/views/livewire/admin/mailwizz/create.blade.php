<?php

use App\Models\CallCenter;
use App\Models\MailWizzConfig;
use Livewire\Volt\Component;

new class extends Component {
    public string $api_url = '';
    public string $public_key = '';
    public string $private_key = '';
    public ?string $list_uid = null;
    public ?int $call_center_id = null;
    public int $import_frequency = 15;
    public bool $is_active = false;

    public function store(): void
    {
        $validated = $this->validate([
            'api_url' => ['required', 'url'],
            'public_key' => ['required', 'string'],
            'private_key' => ['required', 'string'],
            'list_uid' => ['nullable', 'string'],
            'call_center_id' => ['nullable', 'exists:call_centers,id'],
            'import_frequency' => ['required', 'integer', 'in:15,30,60,120,240,1440'],
            'is_active' => ['boolean'],
        ], [
            'api_url.required' => __('L\'URL API est requise.'),
            'api_url.url' => __('L\'URL API doit être une URL valide.'),
            'public_key.required' => __('La clé publique est requise.'),
            'private_key.required' => __('La clé privée est requise.'),
            'call_center_id.exists' => __('Le Call Center sélectionné n\'existe pas.'),
            'import_frequency.required' => __('La fréquence d\'import est requise.'),
            'import_frequency.in' => __('La fréquence d\'import doit être une valeur valide.'),
        ]);

        MailWizzConfig::create($validated);

        session()->flash('message', __('Configuration créée avec succès !'));

        $this->redirect(route('admin.mailwizz.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'callCenters' => CallCenter::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumb -->
    <div class="flex items-center justify-between">
        <flux:button href="{{ route('admin.mailwizz.index') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
            {{ __('Retour') }}
        </flux:button>
        <nav class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
            <a href="{{ route('admin.mailwizz.index') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
                {{ __('MailWizz') }}
            </a>
            <span>/</span>
            <span class="text-neutral-900 dark:text-neutral-100">{{ __('Créer') }}</span>
        </nav>
    </div>

    <!-- Messages flash -->
    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold">{{ __('Créer une configuration MailWizz') }}</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
            {{ __('Configurez l\'import automatique de leads depuis MailWizz') }}
        </p>
    </div>

    <!-- Formulaire -->
    <form wire:submit="store" class="flex flex-col gap-6">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Informations de connexion') }}</h2>

            <div class="flex flex-col gap-4">
                <flux:field>
                    <flux:label>{{ __('URL API MailWizz') }}</flux:label>
                    <flux:input wire:model="api_url" placeholder="https://your-mailwizz-instance.com" />
                    <flux:error name="api_url" />
                    <flux:description>{{ __('URL de base de votre instance MailWizz') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Clé publique') }}</flux:label>
                    <flux:input wire:model="public_key" />
                    <flux:error name="public_key" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Clé privée') }}</flux:label>
                    <flux:input wire:model="private_key" type="password" />
                    <flux:error name="private_key" />
                    <flux:description>{{ __('La clé privée sera chiffrée dans la base de données') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('List UID') }}</flux:label>
                    <flux:input wire:model="list_uid" placeholder="{{ __('Optionnel') }}" />
                    <flux:error name="list_uid" />
                    <flux:description>{{ __('UID de la liste MailWizz à importer (optionnel)') }}</flux:description>
                </flux:field>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Configuration d\'import') }}</h2>

            <div class="flex flex-col gap-4">
                <flux:field>
                    <flux:label>{{ __('Call Center') }}</flux:label>
                    <flux:select wire:model="call_center_id">
                        <option value="">{{ __('Sélectionner un Call Center') }}</option>
                        @foreach ($callCenters as $callCenter)
                            <option value="{{ $callCenter->id }}">{{ $callCenter->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="call_center_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fréquence d\'import') }}</flux:label>
                    <flux:select wire:model="import_frequency">
                        <option value="15">{{ __('Toutes les 15 minutes') }}</option>
                        <option value="30">{{ __('Toutes les 30 minutes') }}</option>
                        <option value="60">{{ __('Toutes les heures') }}</option>
                        <option value="120">{{ __('Toutes les 2 heures') }}</option>
                        <option value="240">{{ __('Toutes les 4 heures') }}</option>
                        <option value="1440">{{ __('Une fois par jour') }}</option>
                    </flux:select>
                    <flux:error name="import_frequency" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="is_active" label="{{ __('Activer l\'import automatique') }}" />
                    <flux:error name="is_active" />
                </flux:field>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <flux:button type="button" href="{{ route('admin.mailwizz.index') }}" variant="ghost" wire:navigate>
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('Créer') }}</span>
                <span wire:loading>{{ __('Création...') }}</span>
            </flux:button>
        </div>
    </form>
</div>




