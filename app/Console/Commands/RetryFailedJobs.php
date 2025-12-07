<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryFailedJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:retry-failed 
                            {--limit=50 : Nombre maximum de jobs à relancer}
                            {--older-than=60 : Relancer uniquement les jobs échoués il y a plus de X minutes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Relance automatiquement les jobs échoués (notamment les emails de confirmation)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $olderThan = (int) $this->option('older-than');

        $this->info("Recherche des jobs échoués (limite: {$limit}, plus anciens que {$olderThan} minutes)...");

        // Récupérer les jobs échoués
        $failedJobs = DB::table('failed_jobs')
            ->where('failed_at', '<=', now()->subMinutes($olderThan))
            ->orderBy('failed_at', 'asc')
            ->limit($limit)
            ->get();

        if ($failedJobs->isEmpty()) {
            $this->info('Aucun job échoué à relancer.');

            return Command::SUCCESS;
        }

        $this->info("Trouvé {$failedJobs->count()} job(s) échoué(s) à relancer.");

        $retried = 0;
        $errors = 0;

        foreach ($failedJobs as $failedJob) {
            try {
                // Décoder le payload pour obtenir les informations du job
                $payload = json_decode($failedJob->payload, true);

                // Vérifier si c'est un job d'email de confirmation
                if (isset($payload['displayName']) && str_contains($payload['displayName'], 'SendLeadConfirmationEmail')) {
                    $this->line("Relance du job d'email de confirmation (ID: {$failedJob->id})...");

                    // Relancer le job
                    Artisan::call('queue:retry', [
                        'id' => $failedJob->uuid,
                    ]);

                    $retried++;
                    Log::info('Failed job retried automatically', [
                        'job_id' => $failedJob->id,
                        'job_uuid' => $failedJob->uuid,
                        'job_type' => $payload['displayName'] ?? 'unknown',
                    ]);
                } else {
                    // Pour les autres types de jobs, on peut aussi les relancer
                    $this->line("Relance du job (ID: {$failedJob->id}, Type: {$payload['displayName'] ?? 'unknown'})...");

                    Artisan::call('queue:retry', [
                        'id' => $failedJob->uuid,
                    ]);

                    $retried++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("Erreur lors de la relance du job {$failedJob->id}: {$e->getMessage()}");
                Log::error('Failed to retry job', [
                    'job_id' => $failedJob->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Relance terminée: {$retried} job(s) relancé(s), {$errors} erreur(s).");

        return Command::SUCCESS;
    }
}
