<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RecalculateLeadScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:recalculate-scores {--all : Recalculate all leads, not just those without scores}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate scores for leads (only leads without scores by default, use --all for all leads)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Recalculating lead scores...');

        $query = \App\Models\Lead::query();

        // If --all is not specified, only recalculate leads without scores
        if (! $this->option('all')) {
            $query->whereNull('score');
        }

        $leads = $query->with(['form', 'notes', 'reminders', 'tags'])->get();
        $total = $leads->count();

        if ($total === 0) {
            $this->info('No leads to recalculate.');

            return self::SUCCESS;
        }

        $this->info("Found {$total} lead(s) to recalculate.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        $scoringService = app(\App\Services\LeadScoringService::class);

        foreach ($leads as $lead) {
            try {
                $scoringService->updateScore($lead);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Error recalculating score for lead {$lead->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Successfully recalculated: {$successCount}");
        if ($errorCount > 0) {
            $this->warn("❌ Errors: {$errorCount}");
        }

        return self::SUCCESS;
    }
}
