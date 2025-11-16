<?php

namespace App\Console\Commands;

use App\Jobs\SendLeadReminderEmail;
use App\Models\Lead;
use Illuminate\Console\Command;

class SendLeadReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:send-reminders 
                            {--hours=24 : Nombre d\'heures d\'inactivité avant relance}
                            {--dry-run : Afficher les leads sans envoyer d\'emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoyer des emails de relance aux leads inactifs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');

        $this->info("Recherche des leads inactifs depuis plus de {$hours} heures...");

        // Leads en attente de confirmation email depuis plus de X heures
        $leadsPendingEmail = Lead::where('status', 'pending_email')
            ->where('created_at', '<=', now()->subHours($hours))
            ->whereNull('email_confirmed_at')
            ->get();

        // Leads avec email confirmé mais pas encore appelés depuis plus de X heures
        $leadsPendingCall = Lead::where('status', 'email_confirmed')
            ->whereNotNull('email_confirmed_at')
            ->where('email_confirmed_at', '<=', now()->subHours($hours))
            ->whereNull('called_at')
            ->get();

        $totalLeads = $leadsPendingEmail->count() + $leadsPendingCall->count();

        if ($totalLeads === 0) {
            $this->info('Aucun lead inactif trouvé.');

            return Command::SUCCESS;
        }

        $this->info("{$totalLeads} lead(s) inactif(s) trouvé(s).");

        if ($dryRun) {
            $this->warn('Mode dry-run activé - aucun email ne sera envoyé.');
            $this->table(
                ['ID', 'Email', 'Statut', 'Créé le', 'Email confirmé le'],
                $leadsPendingEmail->merge($leadsPendingCall)->map(function ($lead) {
                    return [
                        $lead->id,
                        $lead->email,
                        $lead->status,
                        $lead->created_at->format('d/m/Y H:i'),
                        $lead->email_confirmed_at?->format('d/m/Y H:i') ?? 'N/A',
                    ];
                })->toArray()
            );

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totalLeads);
        $bar->start();

        $sent = 0;
        $failed = 0;

        foreach ($leadsPendingEmail as $lead) {
            try {
                SendLeadReminderEmail::dispatch($lead);
                $sent++;
            } catch (\Exception $e) {
                $this->error("\nErreur pour le lead {$lead->id}: {$e->getMessage()}");
                $failed++;
            }
            $bar->advance();
        }

        foreach ($leadsPendingCall as $lead) {
            try {
                SendLeadReminderEmail::dispatch($lead);
                $sent++;
            } catch (\Exception $e) {
                $this->error("\nErreur pour le lead {$lead->id}: {$e->getMessage()}");
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ {$sent} email(s) de relance envoyé(s).");
        if ($failed > 0) {
            $this->warn("⚠️  {$failed} échec(s).");
        }

        return Command::SUCCESS;
    }
}
