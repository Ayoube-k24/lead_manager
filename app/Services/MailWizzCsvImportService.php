<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\MailWizzImportedLead;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MailWizzCsvImportService
{
    public function __construct(
        protected TagService $tagService
    ) {}

    /**
     * Import leads from CSV file.
     *
     * @return array{imported: int, skipped_duplicate: int, skipped_has_form: int, errors: int}
     */
    public function importFromCsv(string $filePath, ?int $callCenterId = null): array
    {
        $stats = [
            'imported' => 0,
            'skipped_duplicate' => 0,
            'skipped_has_form' => 0,
            'errors' => 0,
        ];

        // Get or create lead_seo tag
        $seoTag = Tag::firstOrCreate(
            ['name' => 'lead_seo'],
            [
                'name' => 'lead_seo',
                'color' => '#10B981',
                'description' => 'Lead importé depuis MailWizz (SEO)',
                'is_system' => true,
            ]
        );

        // Obtenir le statut email_confirmed
        $emailConfirmedStatus = \App\Models\LeadStatus::getBySlug('email_confirmed');

        // Lire le fichier CSV
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \Exception('Impossible d\'ouvrir le fichier CSV');
        }

        // Lire la première ligne (en-têtes)
        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            throw new \Exception('Le fichier CSV est vide ou invalide');
        }

        // Normaliser les en-têtes (minuscules, trim)
        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        // Vérifier que l'email est présent
        $emailIndex = array_search('email', $headers);
        if ($emailIndex === false) {
            fclose($handle);
            throw new \Exception('La colonne "email" est requise dans le fichier CSV');
        }

        // Trouver l'index du subscriber_uid ou uid
        $subscriberIdIndex = array_search('subscriber_uid', $headers);
        if ($subscriberIdIndex === false) {
            $subscriberIdIndex = array_search('uid', $headers);
        }

        $lineNumber = 1;

        // Lire chaque ligne
        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            try {
                DB::beginTransaction();

                // Construire un tableau associatif à partir de la ligne
                $subscriber = [];
                foreach ($headers as $index => $header) {
                    $subscriber[$header] = $row[$index] ?? null;
                }

                // Extraire l'email et le subscriber_id
                $email = strtolower(trim($subscriber['email'] ?? ''));
                $subscriberId = $subscriberIdIndex !== false ? ($row[$subscriberIdIndex] ?? null) : null;

                // Si pas de subscriber_id, générer un ID basé sur l'email et la ligne
                if (! $subscriberId) {
                    $subscriberId = 'csv_'.md5($email.$lineNumber);
                }

                // Validation
                if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $stats['errors']++;
                    DB::rollBack();
                    Log::warning('Invalid email in CSV import', [
                        'line' => $lineNumber,
                        'email' => $email,
                    ]);

                    continue;
                }

                // Vérification 1: Déjà importé depuis MailWizz
                if (MailWizzImportedLead::isAlreadyImported($subscriberId)) {
                    $stats['skipped_duplicate']++;
                    DB::rollBack();

                    continue;
                }

                // Vérification 2: Email existe déjà dans leads (toutes sources)
                if (MailWizzImportedLead::emailExistsInLeads($email)) {
                    // Vérifier si le lead a déjà rempli un formulaire
                    $existingLead = Lead::where('email', $email)->first();

                    if ($existingLead && $existingLead->source === 'form') {
                        $stats['skipped_has_form']++;
                        Log::info('Skipping lead - already submitted form', [
                            'email' => $email,
                            'lead_id' => $existingLead->id,
                        ]);
                    } else {
                        $stats['skipped_duplicate']++;
                    }

                    DB::rollBack();

                    continue;
                }

                // Préparer les données du lead
                $leadData = $this->prepareLeadData($subscriber, $subscriberId);

                // Créer le lead avec statut email_confirmed directement
                $lead = Lead::create([
                    'email' => $email,
                    'source' => 'import',
                    'status' => $emailConfirmedStatus ? $emailConfirmedStatus->slug : 'email_confirmed',
                    'status_id' => $emailConfirmedStatus?->id,
                    'email_confirmed_at' => now(),
                    'call_center_id' => $callCenterId,
                    'data' => $leadData,
                ]);

                // Enregistrer dans mailwizz_imported_leads
                MailWizzImportedLead::create([
                    'mailwizz_subscriber_id' => $subscriberId,
                    'lead_id' => $lead->id,
                    'email' => $email,
                    'imported_at' => now(),
                    'mailwizz_data' => $subscriber,
                ]);

                // Attacher le tag lead_seo
                $this->tagService->attachTag($lead, $seoTag);

                DB::commit();
                $stats['imported']++;
            } catch (\Exception $e) {
                DB::rollBack();
                $stats['errors']++;
                Log::error('Error importing lead from CSV', [
                    'line' => $lineNumber,
                    'subscriber_id' => $subscriberId ?? 'unknown',
                    'email' => $email ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        fclose($handle);

        return $stats;
    }

    /**
     * Prepare lead data from CSV row data.
     * Imports all fields from the row, normalizing common field names.
     *
     * @param  array<string, mixed>  $subscriber
     * @return array<string, mixed>
     */
    protected function prepareLeadData(array $subscriber, string $subscriberId): array
    {
        // Champs système à exclure (déjà traités séparément)
        $excludedFields = ['subscriber_uid', 'uid', 'email'];

        // Normalisation des noms de champs courants pour compatibilité
        $fieldMappings = [
            'fname' => 'first_name',
            'lname' => 'last_name',
            'phone' => 'phone',
        ];

        $leadData = [
            'mailwizz_subscriber_id' => $subscriberId,
        ];

        // Parcourir tous les champs du subscriber
        foreach ($subscriber as $key => $value) {
            $keyLower = strtolower(trim($key));

            // Ignorer les champs système
            if (in_array($keyLower, array_map('strtolower', $excludedFields))) {
                continue;
            }

            // Normaliser les noms de champs courants
            $normalizedKey = $fieldMappings[$keyLower] ?? $key;

            // Ajouter le champ normalisé
            if ($normalizedKey !== $key) {
                // Si normalisé, ajouter les deux versions
                $leadData[$normalizedKey] = $value;
                $leadData['mailwizz_'.$key] = $value; // Conserver l'original avec préfixe
            } else {
                // Sinon, ajouter tel quel
                $leadData[$key] = $value;
            }
        }

        return $leadData;
    }
}

