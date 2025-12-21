<?php

namespace App\Services;

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StatisticsService
{
    /**
     * Get global statistics for Super Admin.
     *
     * @return array<string, mixed>
     */
    public function getGlobalStatistics(): array
    {
        $totalLeads = Lead::count();
        $confirmedLeads = Lead::where('status', 'confirmed')->count();
        $rejectedLeads = Lead::where('status', 'rejected')->count();
        $pendingLeads = Lead::whereIn('status', ['pending_email', 'email_confirmed', 'pending_call'])->count();

        $conversionRate = $totalLeads > 0 ? round(($confirmedLeads / $totalLeads) * 100, 2) : 0;

        // Calculate average processing time
        $avgProcessingTime = $this->calculateAverageProcessingTime();

        // Leads by status
        $leadsByStatus = Lead::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Leads created over time (last 30 days)
        $leadsOverTime = $this->getLeadsOverTime(30);

        return [
            'total_leads' => $totalLeads,
            'confirmed_leads' => $confirmedLeads,
            'rejected_leads' => $rejectedLeads,
            'pending_leads' => $pendingLeads,
            'conversion_rate' => $conversionRate,
            'avg_processing_time' => $avgProcessingTime,
            'leads_by_status' => $leadsByStatus,
            'leads_over_time' => $leadsOverTime,
        ];
    }

    /**
     * Get statistics for a call center.
     *
     * @return array<string, mixed>
     */
    public function getCallCenterStatistics(CallCenter $callCenter): array
    {
        $leads = Lead::where('call_center_id', $callCenter->id)->get();

        $totalLeads = $leads->count();
        $confirmedLeads = $leads->where('status', 'confirmed')->count();
        $rejectedLeads = $leads->where('status', 'rejected')->count();
        $pendingLeads = $leads->whereIn('status', ['pending_email', 'email_confirmed', 'pending_call'])->count();

        $conversionRate = $totalLeads > 0 ? round(($confirmedLeads / $totalLeads) * 100, 2) : 0;

        // Average processing time for this call center
        $avgProcessingTime = $this->calculateAverageProcessingTime($callCenter);

        // Agent performance
        $agentPerformance = $this->getAgentPerformance($callCenter);

        // Leads over time
        $leadsOverTime = $this->getLeadsOverTime(30, $callCenter);

        return [
            'call_center' => $callCenter,
            'total_leads' => $totalLeads,
            'confirmed_leads' => $confirmedLeads,
            'rejected_leads' => $rejectedLeads,
            'pending_leads' => $pendingLeads,
            'conversion_rate' => $conversionRate,
            'avg_processing_time' => $avgProcessingTime,
            'agent_performance' => $agentPerformance,
            'leads_over_time' => $leadsOverTime,
        ];
    }

    /**
     * Get statistics for an agent.
     *
     * @return array<string, mixed>
     */
    public function getAgentStatistics(User $agent): array
    {
        $leads = Lead::where('assigned_to', $agent->id)->get();

        $totalLeads = $leads->count();
        $confirmedLeads = $leads->where('status', 'confirmed')->count();
        $rejectedLeads = $leads->where('status', 'rejected')->count();
        $pendingLeads = $leads->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])->count();

        $conversionRate = $totalLeads > 0 ? round(($confirmedLeads / $totalLeads) * 100, 2) : 0;

        // Average processing time
        $avgProcessingTime = $this->calculateAverageProcessingTime(null, $agent);

        // Leads over time
        $leadsOverTime = $this->getLeadsOverTime(30, null, $agent);

        return [
            'agent' => $agent,
            'total_leads' => $totalLeads,
            'confirmed_leads' => $confirmedLeads,
            'rejected_leads' => $rejectedLeads,
            'pending_leads' => $pendingLeads,
            'conversion_rate' => $conversionRate,
            'avg_processing_time' => $avgProcessingTime,
            'leads_over_time' => $leadsOverTime,
        ];
    }

    /**
     * Calculate average processing time in hours.
     */
    protected function calculateAverageProcessingTime(?CallCenter $callCenter = null, ?User $agent = null): float
    {
        $query = Lead::where('status', 'confirmed')
            ->whereNotNull('email_confirmed_at')
            ->whereNotNull('called_at');

        if ($callCenter) {
            $query->where('call_center_id', $callCenter->id);
        }

        if ($agent) {
            $query->where('assigned_to', $agent->id);
        }

        $leads = $query->get();

        if ($leads->isEmpty()) {
            return 0;
        }

        $totalHours = $leads->sum(function ($lead) {
            return $lead->email_confirmed_at->diffInHours($lead->called_at);
        });

        return round($totalHours / $leads->count(), 2);
    }

    /**
     * Get agent performance statistics.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function getAgentPerformance(CallCenter $callCenter): Collection
    {
        $agents = User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn($q) => $q->where('slug', 'agent'))
            ->get();

        return $agents->map(function ($agent) {
            $leads = Lead::where('assigned_to', $agent->id)->get();
            $total = $leads->count();
            $confirmed = $leads->where('status', 'confirmed')->count();
            $conversionRate = $total > 0 ? round(($confirmed / $total) * 100, 2) : 0;

            return [
                'agent' => $agent,
                'total_leads' => $total,
                'confirmed_leads' => $confirmed,
                'conversion_rate' => $conversionRate,
                'pending_leads' => $leads->whereIn('status', ['pending_call', 'email_confirmed', 'callback_pending'])->count(),
            ];
        })->sortByDesc('conversion_rate');
    }

    /**
     * Get leads created over time.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getLeadsOverTime(int $days, ?CallCenter $callCenter = null, ?User $agent = null): array
    {
        $query = Lead::query();

        if ($callCenter) {
            $query->where('call_center_id', $callCenter->id);
        }

        if ($agent) {
            $query->where('assigned_to', $agent->id);
        }

        $startDate = Carbon::now()->subDays($days);
        $leads = $query->where('created_at', '>=', $startDate)
            ->orderBy('created_at')
            ->get()
            ->groupBy(function ($lead) {
                return $lead->created_at->format('Y-m-d');
            });

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'count' => $leads->get($date)?->count() ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Get leads that need attention (untreated or overdue).
     *
     * @return Collection<int, Lead>
     */
    public function getLeadsNeedingAttention(?CallCenter $callCenter = null, int $hoursThreshold = 48): Collection
    {
        $threshold = Carbon::now()->subHours($hoursThreshold);

        $query = Lead::whereIn('status', ['email_confirmed', 'pending_call'])
            ->whereNull('called_at')
            ->where('email_confirmed_at', '<=', $threshold);

        if ($callCenter) {
            $query->where('call_center_id', $callCenter->id);
        }

        return $query->orderBy('email_confirmed_at', 'asc')->get();
    }

    /**
     * Get underperforming agents.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getUnderperformingAgents(?CallCenter $callCenter = null, float $minConversionRate = 20.0): Collection
    {
        $query = User::whereHas('role', fn($q) => $q->where('slug', 'agent'));

        if ($callCenter) {
            $query->where('call_center_id', $callCenter->id);
        }

        $agents = $query->get();

        return $agents->map(function ($agent) {
            $leads = Lead::where('assigned_to', $agent->id)->get();
            $total = $leads->count();
            $confirmed = $leads->where('status', 'confirmed')->count();
            $conversionRate = $total > 0 ? round(($confirmed / $total) * 100, 2) : 0;

            return [
                'agent' => $agent,
                'total_leads' => $total,
                'confirmed_leads' => $confirmed,
                'conversion_rate' => $conversionRate,
            ];
        })->filter(function ($stats) use ($minConversionRate) {
            return $stats['total_leads'] >= 10 && $stats['conversion_rate'] < $minConversionRate;
        })->sortBy('conversion_rate');
    }
}
