<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadDistributionService;
use Illuminate\Console\Command;

class UpdateLeadsCallCenter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:update-call-center';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing leads with call_center_id from their form and distribute to agents if needed';

    /**
     * Execute the console command.
     */
    public function handle(LeadDistributionService $distributionService): int
    {
        $this->info('Mise à jour des leads existants...');

        // Update leads without call_center_id using their form's call_center_id
        $leadsWithoutCallCenter = Lead::whereNull('call_center_id')
            ->whereHas('form', function ($query) {
                $query->whereNotNull('call_center_id');
            })
            ->with('form')
            ->get();

        $updated = 0;
        foreach ($leadsWithoutCallCenter as $lead) {
            if ($lead->form && $lead->form->call_center_id) {
                $lead->call_center_id = $lead->form->call_center_id;
                $lead->save();
                $updated++;

                // Try to distribute if email is confirmed and no agent assigned
                if ($lead->isEmailConfirmed() && ! $lead->assigned_to) {
                    $agent = $distributionService->distributeLead($lead);
                    if ($agent) {
                        $distributionService->assignToAgent($lead, $agent);
                        $lead->markAsPendingCall();
                        $this->line("Lead #{$lead->id} assigné à l'agent {$agent->name}");
                    }
                }
            }
        }

        $leadWord = $updated === 1 ? 'lead' : 'leads';
        $this->info("{$updated} {$leadWord} mis à jour avec succès.");

        // Count leads that still don't have call_center_id
        $stillWithoutCallCenter = Lead::whereNull('call_center_id')
            ->whereHas('form', function ($query) {
                $query->whereNull('call_center_id');
            })
            ->count();

        if ($stillWithoutCallCenter > 0) {
            if ($stillWithoutCallCenter === 1) {
                $this->warn("1 lead ne peut pas être mis à jour car son formulaire n'a pas de centre d'appel associé.");
            } else {
                $this->warn("{$stillWithoutCallCenter} leads ne peuvent pas être mis à jour car leur formulaire n'a pas de centre d'appel associé.");
            }
            $this->info('Assurez-vous d\'associer un centre d\'appel aux formulaires dans l\'interface d\'administration.');
        }

        return Command::SUCCESS;
    }
}
