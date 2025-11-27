<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

class AlertsCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifier toutes les alertes actives et déclencher celles dont les conditions sont remplies';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Vérification des alertes actives...');

        $service = app(AlertService::class);
        $triggered = $service->checkAlerts();

        if ($triggered->isEmpty()) {
            $this->info('Aucune alerte déclenchée.');

            return Command::SUCCESS;
        }

        $this->info("{$triggered->count()} alerte(s) déclenchée(s).");

        foreach ($triggered as $alert) {
            $this->line("  - {$alert->name} (Type: {$alert->type}, Utilisateur: {$alert->user->name})");
        }

        return Command::SUCCESS;
    }
}
