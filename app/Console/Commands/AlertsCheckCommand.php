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
        $this->info('Vérification des alertes actives par rôle...');

        $service = app(AlertService::class);

        // Get all unique role slugs from alerts
        $roleSlugs = \App\Models\Alert::where('is_active', true)
            ->distinct()
            ->pluck('role_slug')
            ->filter();

        if ($roleSlugs->isEmpty()) {
            $this->info('Aucune alerte active trouvée.');

            return Command::SUCCESS;
        }

        $totalTriggered = 0;

        foreach ($roleSlugs as $roleSlug) {
            $this->line("Vérification des alertes pour le rôle: {$roleSlug}");
            $triggered = $service->checkAlertsForRole($roleSlug);

            if ($triggered->isNotEmpty()) {
                $totalTriggered += $triggered->count();
                foreach ($triggered as $alert) {
                    $this->line("  - {$alert->name} (Type: {$alert->type}, Rôle: {$alert->role_slug})");
                }
            }
        }

        if ($totalTriggered === 0) {
            $this->info('Aucune alerte déclenchée.');

            return Command::SUCCESS;
        }

        $this->info("{$totalTriggered} alerte(s) déclenchée(s) au total.");

        return Command::SUCCESS;
    }
}
