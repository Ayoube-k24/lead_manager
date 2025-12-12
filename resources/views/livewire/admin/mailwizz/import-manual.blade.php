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

    public bool $showGuide = false;

    public function mount(): void
    {
        //
    }

    public function toggleGuide(): void
    {
        $this->showGuide = ! $this->showGuide;
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
        <div class="flex items-center gap-3">
            <flux:button wire:click="toggleGuide" variant="ghost" icon="question-mark-circle">
                {{ $showGuide ? __('Masquer le guide') : __('Afficher le guide') }}
            </flux:button>
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

    <!-- Guide complet -->
    @if ($showGuide)
        <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="border-b border-neutral-200 p-6 dark:border-neutral-700">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Guide complet d\'importation MailWizz') }}</h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Tout ce que vous devez savoir pour importer vos leads depuis MailWizz') }}</p>
            </div>

            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                <!-- Prérequis -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        ✅ {{ __('Prérequis') }}
                    </h3>
                    <ul class="list-inside list-disc space-y-2 text-sm text-neutral-600 dark:text-neutral-400">
                        <li>{{ __('Un compte Super Admin dans Lead Manager') }}</li>
                        <li>{{ __('Un Call Center créé et actif') }}</li>
                        <li>{{ __('Un fichier CSV exporté depuis MailWizz') }}</li>
                        <li>{{ __('Les droits d\'accès à la page d\'importation manuelle') }}</li>
                    </ul>
                </div>

                <!-- Exportation depuis MailWizz -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        📤 {{ __('Exportation depuis MailWizz') }}
                    </h3>
                    <div class="space-y-3 text-sm text-neutral-600 dark:text-neutral-400">
                        <div>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Étape 1 : Accéder à votre liste') }}</strong>
                            <p class="mt-1">{{ __('Connectez-vous à votre instance MailWizz, allez dans Lists → Sélectionnez votre liste → Cliquez sur Subscribers (Abonnés)') }}</p>
                        </div>
                        <div>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Étape 2 : Exporter les abonnés') }}</strong>
                            <p class="mt-1">{{ __('Cliquez sur Export ou Export Subscribers, sélectionnez le format CSV, et choisissez les champs à exporter :') }}</p>
                            <ul class="mt-2 ml-4 list-inside list-disc space-y-1">
                                <li>{{ __('Email (obligatoire)') }}</li>
                                <li>{{ __('Subscriber UID ou UID (recommandé pour éviter les doublons)') }}</li>
                                <li>{{ __('Tous les autres champs personnalisés que vous souhaitez importer') }}</li>
                            </ul>
                        </div>
                        <div>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Étape 3 : Télécharger le fichier') }}</strong>
                            <p class="mt-1">{{ __('Cliquez sur Export ou Download. Le fichier CSV sera téléchargé sur votre ordinateur. Vérifiez que le fichier n\'est pas vide et contient bien les données.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Format du fichier CSV -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        📄 {{ __('Format du fichier CSV') }}
                    </h3>
                    <div class="space-y-4">
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Colonnes obligatoires') }}</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                <p><strong>email</strong> - {{ __('Adresse email de l\'abonné (OBLIGATOIRE)') }}</p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Colonnes recommandées') }}</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400 space-y-1">
                                <p><strong>subscriber_uid</strong> ou <strong>uid</strong> - {{ __('Identifiant unique MailWizz (recommandé)') }}</p>
                                <p><strong>fname</strong> ou <strong>first_name</strong> - {{ __('Prénom') }}</p>
                                <p><strong>lname</strong> ou <strong>last_name</strong> - {{ __('Nom de famille') }}</p>
                                <p><strong>phone</strong> - {{ __('Numéro de téléphone') }}</p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-2">{{ __('Colonnes optionnelles') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Toutes les autres colonnes de votre export MailWizz seront automatiquement importées dans les données du lead. Par exemple : company, city, country, et tous les champs personnalisés MailWizz.') }}</p>
                        </div>

                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                            <div class="font-semibold text-blue-900 dark:text-blue-100 mb-2">{{ __('Exemple de fichier CSV') }}</div>
                            <pre class="mt-2 overflow-x-auto rounded bg-neutral-900 p-3 text-xs text-neutral-100 dark:bg-neutral-950"><code>email,subscriber_uid,fname,lname,phone,company,city,country
contact@example.com,abc123def456,Jean,Dupont,+33 6 12 34 56 78,Acme Inc,Paris,France
marie@example.com,xyz789ghi012,Marie,Martin,+33 6 98 76 54 32,Tech Corp,Lyon,France</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Importation via l'interface -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        🚀 {{ __('Importation via l\'interface') }}
                    </h3>
                    <ol class="list-decimal list-inside space-y-3 text-sm text-neutral-600 dark:text-neutral-400">
                        <li>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Accéder à la page d\'importation') }}</strong>
                            <p class="mt-1 ml-6">{{ __('Connectez-vous en tant que Super Admin et accédez à Admin → MailWizz → Import Manuel') }}</p>
                        </li>
                        <li>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Sélectionner le fichier CSV') }}</strong>
                            <p class="mt-1 ml-6">{{ __('Cliquez sur Choisir un fichier ou glissez-déposez votre fichier CSV. Vérifiez que le fichier est au format .csv ou .txt, la taille ne dépasse pas 10 Mo, et le fichier contient bien une colonne email.') }}</p>
                        </li>
                        <li>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Sélectionner le Call Center') }}</strong>
                            <p class="mt-1 ml-6">{{ __('Dans le menu déroulant Call Center, sélectionnez le centre d\'appels qui recevra les leads. Le Call Center est obligatoire.') }}</p>
                        </li>
                        <li>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Lancer l\'importation') }}</strong>
                            <p class="mt-1 ml-6">{{ __('Cliquez sur le bouton Importer et attendez que l\'importation se termine. Les résultats s\'afficheront automatiquement.') }}</p>
                        </li>
                        <li>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Vérifier les résultats') }}</strong>
                            <p class="mt-1 ml-6">{{ __('Après l\'importation, vous verrez un résumé avec : Importés, Doublons ignorés, Leads avec formulaire ignorés, et Erreurs.') }}</p>
                        </li>
                    </ol>
                </div>

                <!-- Résolution des problèmes -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        🔧 {{ __('Résolution des problèmes') }}
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-start gap-3">
                            <span class="text-red-500 dark:text-red-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('"La colonne email est requise"') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('Le fichier CSV ne contient pas de colonne nommée email. Vérifiez que votre export MailWizz contient bien la colonne email. Si la colonne s\'appelle différemment (ex: EMAIL, Email, e-mail), renommez-la en email.') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-red-500 dark:text-red-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('"Le fichier CSV est vide ou invalide"') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('Ouvrez le fichier CSV dans un éditeur de texte, vérifiez qu\'il contient bien des données, qu\'il utilise l\'encodage UTF-8, et qu\'il n\'y a pas de caractères spéciaux qui cassent le format CSV.') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-yellow-500 dark:text-yellow-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('"Fichier trop volumineux"') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('Le fichier dépasse la limite de 10 Mo. Divisez votre fichier CSV en plusieurs fichiers plus petits et importez-les un par un. Ou utilisez l\'importation automatique via l\'API MailWizz.') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-blue-500 dark:text-blue-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('Beaucoup de doublons ignorés') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('C\'est normal ! Le système évite les doublons automatiquement. Si vous voulez forcer l\'import, vous devrez d\'abord supprimer les leads existants (non recommandé).') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-red-500 dark:text-red-400">⚠️</span>
                            <div>
                                <strong class="text-neutral-900 dark:text-neutral-100">{{ __('"Erreur lors de l\'import"') }}</strong>
                                <p class="text-neutral-600 dark:text-neutral-400 mt-1">{{ __('Vérifiez les logs Laravel (storage/logs/laravel.log), vérifiez que la base de données est accessible, vérifiez les permissions d\'écriture dans storage/app/temp, et contactez l\'administrateur système si le problème persiste.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bonnes pratiques -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        💡 {{ __('Bonnes pratiques') }}
                    </h3>
                    <div class="space-y-3 text-sm text-neutral-600 dark:text-neutral-400">
                        <div>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('1. Vérification avant l\'import') }}</strong>
                            <p class="mt-1">{{ __('Ouvrez votre fichier CSV dans Excel ou un éditeur CSV, vérifiez que la colonne email est présente, qu\'il n\'y a pas de lignes vides, et que l\'encodage est UTF-8.') }}</p>
                        </div>
                        <div>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('2. Importation par lots') }}</strong>
                            <p class="mt-1">{{ __('Pour de gros volumes, divisez votre fichier en lots de 1000-5000 leads, importez-les un par un pour éviter les timeouts, et vérifiez les résultats après chaque import.') }}</p>
                        </div>
                        <div>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('3. Sauvegarde') }}</strong>
                            <p class="mt-1">{{ __('Avant un gros import, faites une sauvegarde de la base de données, testez d\'abord avec un petit fichier (10-20 leads), et vérifiez que tout fonctionne correctement.') }}</p>
                        </div>
                        <div>
                            <strong class="text-neutral-900 dark:text-neutral-100">{{ __('4. Vérification après l\'import') }}</strong>
                            <p class="mt-1">{{ __('Vérifiez les statistiques affichées, vérifiez que les leads apparaissent dans la liste des leads, vérifiez que le tag lead_seo est bien attaché, et vérifiez que le statut est email_confirmed.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- FAQ -->
                <div class="p-6">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-3">
                        ❓ {{ __('Questions fréquentes') }}
                    </h3>
                    <div class="space-y-4">
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Puis-je importer plusieurs fois le même fichier ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Oui, mais les doublons seront automatiquement ignorés. Le système vérifie si le subscriber_uid a déjà été importé et si l\'email existe déjà dans le système.') }}</p>
                        </div>

                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Les leads importés doivent-ils confirmer leur email ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Non. Les leads importés depuis MailWizz sont automatiquement marqués comme email_confirmed car ils proviennent d\'une liste MailWizz existante.') }}</p>
                        </div>

                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Que se passe-t-il si un lead a déjà rempli un formulaire ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Le lead sera ignoré (statut skipped_has_form) pour éviter d\'écraser les données du formulaire.') }}</p>
                        </div>

                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Puis-je importer des leads sans subscriber_uid ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Oui, mais c\'est moins recommandé. Le système générera automatiquement un ID basé sur l\'email et le numéro de ligne. Cependant, cela peut créer des problèmes si vous réimportez le même fichier.') }}</p>
                        </div>

                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Les champs personnalisés MailWizz sont-ils importés ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Oui, tous les champs de votre export CSV sont importés dans le champ data du lead. Vous pouvez y accéder via $lead->data[\'nom_du_champ\'].') }}</p>
                        </div>

                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Quelle est la limite de taille de fichier ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('La limite est de 10 Mo par fichier. Pour des fichiers plus volumineux, divisez-les en plusieurs fichiers.') }}</p>
                        </div>

                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <div class="font-semibold text-neutral-900 dark:text-neutral-100 mb-1">{{ __('Comment savoir quels leads ont été importés depuis MailWizz ?') }}</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Tous les leads importés depuis MailWizz ont le tag lead_seo attaché, la source import, et une entrée dans la table mailwizz_imported_leads.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Instructions rapides -->
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-900/20">
        <h2 class="mb-3 text-lg font-semibold text-blue-900 dark:text-blue-100">{{ __('Instructions rapides') }}</h2>
        <ul class="list-inside list-disc space-y-2 text-sm text-blue-800 dark:text-blue-200">
            <li>{{ __('Le fichier CSV doit contenir une colonne "email" (obligatoire)') }}</li>
            <li>{{ __('Les colonnes "subscriber_uid" ou "uid" sont recommandées pour éviter les doublons') }}</li>
            <li>{{ __('Toutes les autres colonnes seront importées dans les données du lead') }}</li>
            <li>{{ __('Les leads seront automatiquement marqués comme confirmés (pas d\'email de confirmation)') }}</li>
            <li>{{ __('Les doublons seront automatiquement ignorés') }}</li>
            <li>{{ __('Taille maximale du fichier : 10 Mo') }}</li>
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
