<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\StatisticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        protected StatisticsService $statisticsService
    ) {}

    /**
     * Export leads to CSV.
     */
    public function exportLeadsCsv(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        $query = Lead::query();

        if ($callCenter && ! $user->isSuperAdmin()) {
            $query->where('call_center_id', $callCenter->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $leads = $query->with(['form', 'callCenter', 'assignedAgent'])->get();

        $filename = 'leads_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($leads) {
            $file = fopen('php://output', 'w');

            // BOM for Excel UTF-8 support
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, [
                'ID',
                'Email',
                'Nom',
                'Téléphone',
                'Formulaire',
                'Centre d\'appels',
                'Agent',
                'Statut',
                'Email confirmé le',
                'Appelé le',
                'Commentaire',
                'Créé le',
            ]);

            // Data
            foreach ($leads as $lead) {
                $data = $lead->data ?? [];
                fputcsv($file, [
                    $lead->id,
                    $lead->email,
                    $data['name'] ?? $data['first_name'] ?? '',
                    $data['phone'] ?? $data['telephone'] ?? '',
                    $lead->form?->name ?? '',
                    $lead->callCenter?->name ?? '',
                    $lead->assignedAgent?->name ?? '',
                    $lead->status,
                    $lead->email_confirmed_at?->format('Y-m-d H:i:s') ?? '',
                    $lead->called_at?->format('Y-m-d H:i:s') ?? '',
                    $lead->call_comment ?? '',
                    $lead->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Export statistics to CSV.
     */
    public function exportStatisticsCsv(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if ($user->isSuperAdmin()) {
            $stats = $this->statisticsService->getGlobalStatistics();
        } elseif ($callCenter) {
            $stats = $this->statisticsService->getCallCenterStatistics($callCenter);
        } else {
            abort(403);
        }

        $filename = 'statistics_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($stats) {
            $file = fopen('php://output', 'w');

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['Statistique', 'Valeur']);
            fputcsv($file, ['Total Leads', $stats['total_leads']]);
            fputcsv($file, ['Leads Confirmés', $stats['confirmed_leads']]);
            fputcsv($file, ['Leads Rejetés', $stats['rejected_leads']]);
            fputcsv($file, ['Leads en Attente', $stats['pending_leads']]);
            fputcsv($file, ['Taux de Conversion (%)', $stats['conversion_rate']]);
            fputcsv($file, ['Temps de Traitement Moyen (heures)', $stats['avg_processing_time']]);

            if (isset($stats['agent_performance'])) {
                fputcsv($file, []);
                fputcsv($file, ['Agent', 'Total Leads', 'Confirmés', 'Taux de Conversion (%)']);
                foreach ($stats['agent_performance'] as $agentStats) {
                    fputcsv($file, [
                        $agentStats['agent']->name,
                        $agentStats['total_leads'],
                        $agentStats['confirmed_leads'],
                        $agentStats['conversion_rate'],
                    ]);
                }
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Export statistics to PDF.
     */
    public function exportStatisticsPdf(Request $request)
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if ($user->isSuperAdmin()) {
            $stats = $this->statisticsService->getGlobalStatistics();
            $view = 'exports.statistics.admin';
        } elseif ($callCenter) {
            $stats = $this->statisticsService->getCallCenterStatistics($callCenter);
            $view = 'exports.statistics.owner';
        } else {
            abort(403);
        }

        $pdf = Pdf::loadView($view, [
            'stats' => $stats,
            'user' => $user,
            'generated_at' => now(),
        ]);

        return $pdf->download('statistics_'.now()->format('Y-m-d_His').'.pdf');
    }
}
