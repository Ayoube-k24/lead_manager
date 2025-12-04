<?php

use App\Models\CallCenter;
use App\Services\MailWizzCsvImportService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $file;

    public ?int $call_center_id = null;

    public bool $importing = false;

    public ?array $importStats = null;

    public function mount(): void
    {
        //
    }

    public function import(MailWizzCsvImportService $importService): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // 10MB max
            'call_center_id' => ['required', 'exists:call_centers,id'],
        ], [
            'file.required' => __('Le fichier CSV est requis.'),
            'file.mimes' => __('Le fichier doit être au format CSV.'),
            'file.max' => __('Le fichier ne doit pas dépasser 10 Mo.'),
            'call_center_id.required' => __('Le Call Center est obligatoire.'),
            'call_center_id.exists' => __('Le Call Center sélectionné n\'existe pas.'),
        ]);

        $this->importing = true;
        $this->importStats = null;

        try {
            // Stocker le fichier temporairement
            $path = $this->file->storeAs('temp', 'import_'.now()->timestamp.'.csv', 'local');

            // Importer depuis le CSV
            $stats = $importService->importFromCsv(
                storage_path('app/'.$path),
                $this->call_center_id
            );

            // Supprimer le fichier temporaire
            if (\Storage::disk('local')->exists($path)) {
                \Storage::disk('local')->delete($path);
            }

            $this->importStats = $stats;
            session()->flash('message', __('Import terminé avec succès !'));
        } catch (\Exception $e) {
            session()->flash('error', __('Erreur lors de l\'import : ').$e->getMessage());
            \Log::error('CSV import error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->importing = false;
            $this->file = null;
        }
    }

    public function with(): array
    {
        return [
            'callCenters' => CallCenter::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:button href="{{ route('admin.mailwizz.index') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
                {{ __('Retour') }}
            </flux:button>
            <h1 class="mt-4 text-2xl font-bold">{{ __('Import manuel de leads MailWizz') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Importez une liste de leads depuis un fichier CSV exporté de MailWizz') }}
            </p>
        </div>
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

    <!-- Instructions -->
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-900/20">
        <h2 class="mb-3 text-lg font-semibold text-blue-900 dark:text-blue-100">{{ __('Instructions') }}</h2>
        <ul class="list-inside list-disc space-y-2 text-sm text-blue-800 dark:text-blue-200">
            <li>{{ __('Le fichier CSV doit contenir une colonne "email" (obligatoire)') }}</li>
            <li>{{ __('Les colonnes "subscriber_uid" ou "uid" sont recommandées pour éviter les doublons') }}</li>
            <li>{{ __('Toutes les autres colonnes seront importées dans les données du lead') }}</li>
            <li>{{ __('Les leads seront automatiquement marqués comme confirmés (pas d\'email de confirmation)') }}</li>
            <li>{{ __('Les doublons seront automatiquement ignorés') }}</li>
        </ul>
    </div>

    <!-- Formulaire d'import -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <form wire:submit="import" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Fichier CSV') }}</flux:label>
                <flux:input type="file" wire:model="file" accept=".csv,.txt" />
                <flux:error name="file" />
                <flux:description>{{ __('Format CSV, taille maximale : 10 Mo') }}</flux:description>
                @if ($file)
                    <div class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Fichier sélectionné :') }} {{ $file->getClientOriginalName() }}
                        ({{ number_format($file->getSize() / 1024, 2) }} KB)
                    </div>
                @endif
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Call Center') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="call_center_id" required>
                    <option value="">{{ __('Sélectionner un Call Center') }}</option>
                    @foreach ($callCenters as $callCenter)
                        <option value="{{ $callCenter->id }}">{{ $callCenter->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="call_center_id" />
                <flux:description>{{ __('Le Call Center est obligatoire pour l\'importation') }}</flux:description>
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" href="{{ route('admin.mailwizz.index') }}" variant="ghost" wire:navigate>
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('Importer') }}</span>
                    <span wire:loading>{{ __('Import en cours...') }}</span>
                </flux:button>
            </div>
        </form>
    </div>

    <!-- Statistiques d'import -->
    @if ($importStats)
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Résultats de l\'import') }}</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                    <div class="text-xs font-medium text-green-700 dark:text-green-300">{{ __('Importés') }}</div>
                    <div class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ $importStats['imported'] }}</div>
                </div>
                <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                    <div class="text-xs font-medium text-yellow-700 dark:text-yellow-300">{{ __('Doublons ignorés') }}</div>
                    <div class="mt-1 text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $importStats['skipped_duplicate'] }}</div>
                </div>
                <div class="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-900/20">
                    <div class="text-xs font-medium text-orange-700 dark:text-orange-300">{{ __('Leads avec formulaire ignorés') }}</div>
                    <div class="mt-1 text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $importStats['skipped_has_form'] }}</div>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                    <div class="text-xs font-medium text-red-700 dark:text-red-300">{{ __('Erreurs') }}</div>
                    <div class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">{{ $importStats['errors'] }}</div>
                </div>
            </div>
        </div>
    @endif
</div>
