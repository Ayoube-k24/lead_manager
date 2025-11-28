<?php

namespace App\Console\Commands;

use App\Models\MailWizzConfig;
use App\Services\MailWizzService;
use Illuminate\Console\Command;

class ImportMailWizzLeadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailwizz:import-leads 
                            {--config-id= : ID de la configuration à utiliser}
                            {--force : Forcer l\'import même si inactif}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import leads from MailWizz (automatic or manual)';

    /**
     * Execute the console command.
     */
    public function handle(MailWizzService $mailwizz): int
    {
        $configId = $this->option('config-id');
        $force = $this->option('force');

        // Si config-id spécifié, utiliser celle-ci
        if ($configId) {
            $config = MailWizzConfig::find($configId);
            if (! $config) {
                $this->error("Configuration MailWizz #{$configId} introuvable.");

                return Command::FAILURE;
            }
            $configs = collect([$config]);
        } else {
            // Sinon, utiliser toutes les configs actives
            $configs = MailWizzConfig::where('is_active', true)->get();
        }

        if ($configs->isEmpty()) {
            $this->warn('Aucune configuration MailWizz active trouvée.');

            return Command::SUCCESS;
        }

        foreach ($configs as $config) {
            if (! $config->is_active && ! $force) {
                $this->line("Configuration #{$config->id} ignorée (inactive).");

                continue;
            }

            $this->info("Import depuis MailWizz (Config #{$config->id})...");

            try {
                $stats = $mailwizz->importLeads($config);

                $this->info('✓ Import terminé:');
                $this->line("  - Importés: {$stats['imported']}");
                $this->line("  - Doublons ignorés: {$stats['skipped_duplicate']}");
                $this->line("  - Leads avec formulaire ignorés: {$stats['skipped_has_form']}");
                $this->line("  - Erreurs: {$stats['errors']}");
            } catch (\Exception $e) {
                $this->error("Erreur lors de l'import: {$e->getMessage()}");
                \Log::error('MailWizz import command error', [
                    'config_id' => $config->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Command::SUCCESS;
    }
}
