<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadStatus;
use Illuminate\Console\Command;

class AnalyzeLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:analyze';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyser les leads existants dans la base de données';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== ANALYSE DES LEADS ===');
        $this->newLine();

        // Total de leads
        $totalLeads = Lead::count();
        $this->info("📊 Total de leads: {$totalLeads}");
        $this->newLine();

        if ($totalLeads === 0) {
            $this->warn('Aucun lead trouvé dans la base de données.');

            return self::SUCCESS;
        }

        // Répartition par statut
        $this->info('=== 📈 Répartition par statut ===');
        $statusCounts = Lead::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('count', 'desc')
            ->get();

        $table = [];
        foreach ($statusCounts as $item) {
            $percentage = round(($item->count / $totalLeads) * 100, 2);
            $table[] = [
                'Statut' => $item->status ?? 'null',
                'Nombre' => $item->count,
                'Pourcentage' => "{$percentage}%",
            ];
        }
        $this->table(['Statut', 'Nombre', 'Pourcentage'], $table);
        $this->newLine();

        // Répartition par source
        $this->info('=== 📍 Répartition par source ===');
        $sourceCounts = Lead::selectRaw('source, COUNT(*) as count')
            ->groupBy('source')
            ->orderBy('count', 'desc')
            ->get();

        $table = [];
        foreach ($sourceCounts as $item) {
            $percentage = round(($item->count / $totalLeads) * 100, 2);
            $table[] = [
                'Source' => $item->source ?? 'null',
                'Nombre' => $item->count,
                'Pourcentage' => "{$percentage}%",
            ];
        }
        $this->table(['Source', 'Nombre', 'Pourcentage'], $table);
        $this->newLine();

        // Email confirmé
        $this->info('=== ✉️ Confirmation email ===');
        $emailConfirmed = Lead::whereNotNull('email_confirmed_at')->count();
        $emailNotConfirmed = Lead::whereNull('email_confirmed_at')->count();
        $confirmedPercentage = round(($emailConfirmed / $totalLeads) * 100, 2);
        $this->line("Confirmés: {$emailConfirmed} ({$confirmedPercentage}%)");
        $this->line("Non confirmés: {$emailNotConfirmed}");
        $this->newLine();

        // Assignation
        $this->info('=== 👤 Assignation ===');
        $assigned = Lead::whereNotNull('assigned_to')->count();
        $notAssigned = Lead::whereNull('assigned_to')->count();
        $assignedPercentage = round(($assigned / $totalLeads) * 100, 2);
        $this->line("Assignés: {$assigned} ({$assignedPercentage}%)");
        $this->line("Non assignés: {$notAssigned}");
        $this->newLine();

        // Appels
        $this->info('=== 📞 Appels ===');
        $called = Lead::whereNotNull('called_at')->count();
        $notCalled = Lead::whereNull('called_at')->count();
        $calledPercentage = round(($called / $totalLeads) * 100, 2);
        $this->line("Appelés: {$called} ({$calledPercentage}%)");
        $this->line("Non appelés: {$notCalled}");
        $this->newLine();

        // Scores
        $this->info('=== 🎯 Scores ===');
        $withScore = Lead::whereNotNull('score')->where('score', '>', 0)->count();
        $highScore = Lead::where('score', '>=', 80)->count();
        $mediumScore = Lead::whereBetween('score', [60, 79])->count();
        $lowScore = Lead::where('score', '<', 60)->where('score', '>', 0)->count();
        $avgScore = Lead::whereNotNull('score')->avg('score');
        $this->line("Leads avec score: {$withScore}");
        if ($avgScore) {
            $this->line('Score moyen: '.round($avgScore, 2));
        }
        $this->line("Priorité haute (≥80): {$highScore}");
        $this->line("Priorité moyenne (60-79): {$mediumScore}");
        $this->line("Priorité basse (<60): {$lowScore}");
        $this->newLine();

        // Répartition par call center
        $this->info('=== 🏢 Répartition par call center ===');
        $centerCounts = Lead::selectRaw('call_center_id, COUNT(*) as count')
            ->with('callCenter:id,name')
            ->groupBy('call_center_id')
            ->orderBy('count', 'desc')
            ->get();

        if ($centerCounts->isNotEmpty()) {
            $table = [];
            foreach ($centerCounts as $item) {
                $centerName = $item->callCenter?->name ?? 'Non assigné';
                $percentage = round(($item->count / $totalLeads) * 100, 2);
                $table[] = [
                    'Call Center' => $centerName,
                    'Nombre' => $item->count,
                    'Pourcentage' => "{$percentage}%",
                ];
            }
            $this->table(['Call Center', 'Nombre', 'Pourcentage'], $table);
        } else {
            $this->line('Aucun call center trouvé.');
        }
        $this->newLine();

        // Leads récents (7 derniers jours)
        $this->info('=== 📅 Activité récente ===');
        $recentLeads = Lead::where('created_at', '>=', now()->subDays(7))->count();
        $this->line("Leads créés (7 derniers jours): {$recentLeads}");
        $this->newLine();

        // Statuts actifs vs finaux
        $this->info('=== 🔄 Statuts actifs vs finaux ===');
        $activeStatusSlugs = LeadStatus::getActiveStatuses()->pluck('slug')->toArray();
        $finalStatusSlugs = LeadStatus::getFinalStatuses()->pluck('slug')->toArray();

        $activeLeads = Lead::whereIn('status', $activeStatusSlugs)->count();
        $finalLeads = Lead::whereIn('status', $finalStatusSlugs)->count();
        $otherLeads = $totalLeads - $activeLeads - $finalLeads;

        $this->line("Statuts actifs: {$activeLeads}");
        $this->line("Statuts finaux: {$finalLeads}");
        if ($otherLeads > 0) {
            $this->line("Autres statuts: {$otherLeads}");
        }
        $this->newLine();

        // Top 10 leads récents
        $this->info('=== 🔝 10 leads les plus récents ===');
        $recentLeads = Lead::with(['form', 'assignedAgent', 'callCenter', 'leadStatus'])
            ->latest()
            ->limit(10)
            ->get();

        if ($recentLeads->isNotEmpty()) {
            $table = [];
            foreach ($recentLeads as $lead) {
                $table[] = [
                    'ID' => $lead->id,
                    'Email' => $lead->email ?? 'N/A',
                    'Statut' => $lead->status ?? 'N/A',
                    'Source' => $lead->source ?? 'N/A',
                    'Créé le' => $lead->created_at->format('Y-m-d H:i'),
                ];
            }
            $this->table(['ID', 'Email', 'Statut', 'Source', 'Créé le'], $table);
        }

        return self::SUCCESS;
    }
}
