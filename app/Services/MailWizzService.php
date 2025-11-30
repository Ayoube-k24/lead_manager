<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\MailWizzConfig;
use App\Models\MailWizzImportedLead;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MailWizzService
{
    protected ?string $accessToken = null;

    public function __construct(
        protected TagService $tagService
    ) {}

    /**
     * Authenticate with MailWizz API.
     */
    public function authenticate(MailWizzConfig $config): bool
    {
        try {
            $response = Http::timeout(30)
                ->post("{$config->api_url}/api/index.php/customer/api_keys/authenticate", [
                    'public_key' => $config->public_key,
                    'private_key' => $config->private_key,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['data']['access_token'] ?? null;

                if ($this->accessToken) {
                    return true;
                }
            }

            Log::error('MailWizz authentication failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('MailWizz authentication error', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get subscribers from MailWizz list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSubscribers(MailWizzConfig $config, int $page = 1, int $perPage = 100): array
    {
        if (! $this->accessToken) {
            if (! $this->authenticate($config)) {
                throw new \Exception('Failed to authenticate with MailWizz');
            }
        }

        try {
            $url = "{$config->api_url}/api/index.php/lists/{$config->list_uid}/subscribers";

            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Api-Key' => $this->accessToken,
                ])
                ->get($url, [
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['data']['records'] ?? $data['data'] ?? [];
            }

            throw new \Exception("Failed to fetch subscribers: HTTP {$response->status()}");
        } catch (\Exception $e) {
            Log::error('MailWizz get subscribers error', [
                'error' => $e->getMessage(),
                'page' => $page,
                'list_uid' => $config->list_uid,
            ]);
            throw $e;
        }
    }

    /**
     * Import leads from MailWizz.
     *
     * @return array{imported: int, skipped_duplicate: int, skipped_has_form: int, errors: int}
     */
    public function importLeads(MailWizzConfig $config, int $batchSize = 100): array
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

        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            try {
                $subscribers = $this->getSubscribers($config, $page, $batchSize);

                if (empty($subscribers)) {
                    $hasMore = false;
                    break;
                }

                foreach ($subscribers as $subscriber) {
                    try {
                        DB::beginTransaction();

                        $subscriberId = $subscriber['subscriber_uid'] ?? $subscriber['uid'] ?? null;
                        $email = strtolower(trim($subscriber['email'] ?? ''));

                        if (! $subscriberId || ! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $stats['errors']++;
                            DB::rollBack();

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

                        // Créer le lead
                        $lead = Lead::create([
                            'email' => $email,
                            'source' => 'mailwizz_seo',
                            'status' => 'pending_email',
                            'call_center_id' => $config->call_center_id,
                            'data' => [
                                'first_name' => $subscriber['FNAME'] ?? $subscriber['first_name'] ?? null,
                                'last_name' => $subscriber['LNAME'] ?? $subscriber['last_name'] ?? null,
                                'phone' => $subscriber['phone'] ?? $subscriber['PHONE'] ?? null,
                                'mailwizz_subscriber_id' => $subscriberId,
                                'clicked_url' => $subscriber['clicked_url'] ?? null,
                                'clicked_at' => $subscriber['clicked_at'] ?? null,
                            ],
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
                        Log::error('Error importing lead from MailWizz', [
                            'subscriber_id' => $subscriberId ?? 'unknown',
                            'email' => $email ?? 'unknown',
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                // Vérifier s'il y a plus de pages
                if (count($subscribers) < $batchSize) {
                    $hasMore = false;
                } else {
                    $page++;
                }
            } catch (\Exception $e) {
                Log::error('Error fetching subscribers from MailWizz', [
                    'page' => $page,
                    'error' => $e->getMessage(),
                ]);
                $hasMore = false;
            }
        }

        // Mettre à jour la config
        $config->update([
            'last_import_at' => now(),
            'last_import_count' => $stats['imported'],
        ]);

        return $stats;
    }
}

