<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadDistributionService;
use Illuminate\Console\Command;

class DistributeUnassignedLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:distribute-unassigned {--limit=50 : Maximum number of leads to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Distribute automatically unassigned leads that are email confirmed';

    /**
     * Execute the console command.
     */
    public function handle(LeadDistributionService $distributionService): int
    {
        $limit = (int) $this->option('limit');

        $this->info('Recherche des leads non assignés à distribuer...');

        // Find leads that are email confirmed but not assigned
        // Only process leads from call centers with automatic distribution (not manual)
        $leads = Lead::with(['form', 'callCenter'])
            ->whereNull('assigned_to')
            ->whereIn('status', ['email_confirmed', 'pending_call'])
            ->whereNotNull('call_center_id')
            ->whereHas('callCenter', function ($query) {
                $query->where('distribution_method', '!=', 'manual');
            })
            ->limit($limit)
            ->get();

        if ($leads->isEmpty()) {
            $this->info('Aucun lead à distribuer trouvé.');

            return Command::SUCCESS;
        }

        $this->info("Trouvé {$leads->count()} lead(s) à distribuer.");
        $this->newLine();

        $distributed = 0;
        $failed = 0;

        foreach ($leads as $lead) {
            $this->line("Traitement du lead #{$lead->id} ({$lead->email})...");

            try {
                // Skip if call center is in manual mode
                if ($lead->callCenter && $lead->callCenter->distribution_method === 'manual') {
                    $this->warn('  ⚠️  Mode manuel - distribution automatique désactivée');

                    continue;
                }

                $agent = $distributionService->distributeLead($lead);

                if ($agent) {
                    if ($distributionService->assignToAgent($lead, $agent)) {
                        $lead->refresh();

                        // Mark as pending call if still email_confirmed
                        if ($lead->status === 'email_confirmed' && $lead->assigned_to) {
                            $lead->status = 'pending_call';
                            $lead->saveQuietly();
                        }

                        $this->info("  ✅ Assigné à l'agent: {$agent->name} (#{$agent->id})");
                        $distributed++;
                    } else {
                        $this->error("  ❌ Échec de l'assignation");
                        $failed++;
                    }
                } else {
                    $this->warn("  ⚠️  Aucun agent trouvé pour le centre d'appel #{$lead->call_center_id}");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Erreur: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info('Résumé:');
        $this->line("  ✅ Distribués: {$distributed}");
        $this->line("  ❌ Échecs: {$failed}");

        return Command::SUCCESS;
    }
}
