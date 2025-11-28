<?php

use App\Models\MailWizzConfig;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(MailWizzConfig $config): void
    {
        try {
            $config->delete();
            session()->flash('message', __('Configuration supprimée avec succès.'));
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function toggleActive(MailWizzConfig $config): void
    {
        $config->update(['is_active' => ! $config->is_active]);
        session()->flash('message', __('Configuration mise à jour.'));
    }

    public function importNow(MailWizzConfig $config): void
    {
        try {
            \Artisan::call('mailwizz:import-leads', [
                '--config-id' => $config->id,
                '--force' => true,
            ]);

            session()->flash('message', __('Import lancé avec succès !'));
        } catch (\Exception $e) {
            session()->flash('error', __('Erreur lors de l\'import : ').$e->getMessage());
        }
    }

    public function with(): array
    {
        $query = MailWizzConfig::query()
            ->with('callCenter')
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('api_url', 'like', "%{$this->search}%")
                    ->orWhere('list_uid', 'like', "%{$this->search}%");
            });
        }

        return [
            'configs' => $query->paginate(10),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Configuration MailWizz') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Gérez les configurations d\'import MailWizz') }}
            </p>
        </div>
        <flux:button href="{{ route('admin.mailwizz.create') }}" variant="primary" wire:navigate>
            {{ __('Nouvelle configuration') }}
        </flux:button>
    </div>

    <!-- Messages flash -->
    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-triangle">
            {{ session('error') }}
        </flux:callout>
    @endif

    <!-- Filtres -->
    <div class="flex items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Rechercher...') }}" class="flex-1" />
    </div>

    <!-- Liste -->
    @if ($configs->isEmpty())
        <flux:callout variant="ghost">
            {{ __('Aucune configuration MailWizz trouvée.') }}
        </flux:callout>
    @else
        <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($configs as $config)
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-lg font-semibold">{{ $config->api_url }}</h3>
                                    @if ($config->is_active)
                                        <flux:badge variant="success" size="sm">{{ __('Actif') }}</flux:badge>
                                    @else
                                        <flux:badge variant="ghost" size="sm">{{ __('Inactif') }}</flux:badge>
                                    @endif
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-neutral-600 dark:text-neutral-400">
                                    @if ($config->list_uid)
                                        <span>{{ __('List UID') }}: {{ $config->list_uid }}</span>
                                    @endif
                                    @if ($config->callCenter)
                                        <span>{{ __('Call Center') }}: {{ $config->callCenter->name }}</span>
                                    @endif
                                    <span>{{ __('Fréquence') }}: {{ $config->getFrequencyDescription() }}</span>
                                    @if ($config->last_import_at)
                                        <span>{{ __('Dernier import') }}: {{ $config->last_import_at->diffForHumans() }}</span>
                                    @endif
                                    @if ($config->last_import_count > 0)
                                        <span>{{ __('Dernier import') }}: {{ $config->last_import_count }} {{ __('leads') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button
                                    wire:click="toggleActive({{ $config->id }})"
                                    variant="ghost"
                                    size="sm"
                                >
                                    {{ $config->is_active ? __('Désactiver') : __('Activer') }}
                                </flux:button>
                                <flux:button
                                    wire:click="importNow({{ $config->id }})"
                                    variant="ghost"
                                    size="sm"
                                    wire:loading.attr="disabled"
                                >
                                    <span wire:loading.remove>{{ __('Importer maintenant') }}</span>
                                    <span wire:loading>{{ __('Import en cours...') }}</span>
                                </flux:button>
                                <flux:button
                                    href="{{ route('admin.mailwizz.edit', $config) }}"
                                    variant="ghost"
                                    size="sm"
                                    wire:navigate
                                >
                                    {{ __('Modifier') }}
                                </flux:button>
                                <flux:button
                                    wire:click="delete({{ $config->id }})"
                                    wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer cette configuration ?') }}"
                                    variant="danger"
                                    size="sm"
                                >
                                    {{ __('Supprimer') }}
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $configs->links() }}
        </div>
    @endif
</div>

