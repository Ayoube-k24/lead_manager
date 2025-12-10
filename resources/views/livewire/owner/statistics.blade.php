<?php

use App\Services\StatisticsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public array $stats = [];
    public array $leadsOverTime = [];
    public array $agentPerformance = [];
    public array $underperformingAgents = [];
    public array $leadsNeedingAttention = [];

    public function mount(StatisticsService $statisticsService): void
    {
        $user = Auth::user();
        $callCenter = $user->callCenter;

        if ($callCenter) {
            $this->stats = $statisticsService->getCallCenterStatistics($callCenter);
            $this->leadsOverTime = $this->stats['leads_over_time'] ?? [];
            $this->agentPerformance = $this->stats['agent_performance']->toArray();
            $this->underperformingAgents = $statisticsService->getUnderperformingAgents($callCenter)->toArray();
            $this->leadsNeedingAttention = $statisticsService->getLeadsNeedingAttention($callCenter)->take(10)->toArray();
        }
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Statistiques du Centre d\'Appels') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Performance de votre équipe et de vos leads') }}
            </p>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('owner.statistics.export.csv') }}" variant="ghost" target="_blank">
                {{ __('Exporter CSV') }}
            </flux:button>
            <flux:button href="{{ route('owner.statistics.export.pdf') }}" variant="ghost" target="_blank">
                {{ __('Exporter PDF') }}
            </flux:button>
        </div>
    </div>

    <!-- Statistiques principales -->
    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Taux de Conversion') }}</p>
            <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ $stats['conversion_rate'] ?? 0 }}%</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Temps de Traitement Moyen') }}</p>
            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['avg_processing_time'] ?? 0 }}h</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Leads Confirmés') }}</p>
            <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ $stats['confirmed_leads'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Leads en Attente') }}</p>
            <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['pending_leads'] ?? 0 }}</p>
        </div>
    </div>

    <!-- Graphiques -->
    <div class="grid gap-6 lg:grid-cols-2">
        <!-- Leads créés sur 30 jours -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Leads créés (30 derniers jours)') }}
            </h2>
            @if (empty($leadsOverTime))
                <div class="flex h-32 items-center justify-center text-sm text-neutral-500 dark:text-neutral-400">
                    {{ __('Aucune donnée disponible') }}
                </div>
            @else
                <div class="relative h-64">
                    <canvas id="leadsOverTimeChart"></canvas>
                </div>
            @endif
        </div>

        <!-- Performance des agents -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Performance des agents') }}
            </h2>
            @if (empty($agentPerformance))
                <div class="flex h-32 items-center justify-center text-sm text-neutral-500 dark:text-neutral-400">
                    {{ __('Aucune donnée disponible') }}
                </div>
            @else
                <div class="relative h-64">
                    <canvas id="agentPerformanceChart"></canvas>
                </div>
            @endif
        </div>
    </div>

    <!-- Tableau de performance des agents -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
            {{ __('Détails des performances des agents') }}
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Agent') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Total Leads') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Confirmés') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('En attente') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Taux de Conversion') }}
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-neutral-800">
                    @forelse ($agentPerformance as $agentStats)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">
                                {{ $agentStats['agent']['name'] }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $agentStats['total_leads'] }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-green-600 dark:text-green-400">
                                {{ $agentStats['confirmed_leads'] }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-yellow-600 dark:text-yellow-400">
                                {{ $agentStats['pending_leads'] }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $agentStats['conversion_rate'] >= 50 ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' : ($agentStats['conversion_rate'] >= 30 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400' : 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400') }}">
                                    {{ $agentStats['conversion_rate'] }}%
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <flux:button href="{{ route('owner.agents.stats', $agentStats['agent']['id']) }}" size="sm" variant="ghost">
                                    {{ __('Détails') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Aucun agent trouvé') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Alertes -->
    <div class="grid gap-6 lg:grid-cols-2">
        <!-- Leads nécessitant une attention -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Leads nécessitant une attention') }}
            </h2>
            @if (count($leadsNeedingAttention) > 0)
                <div class="space-y-2">
                    @foreach ($leadsNeedingAttention as $lead)
                        <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-800 dark:bg-yellow-900/20">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $lead['email'] }}</p>
                                    <p class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ __('Statut') }}: {{ $lead['status'] }}
                                    </p>
                                </div>
                                <flux:button href="{{ route('owner.leads') }}" size="sm" variant="ghost">
                                    {{ __('Voir') }}
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucun lead nécessitant une attention') }}</p>
            @endif
        </div>

        <!-- Agents sous-performants -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Agents sous-performants') }}
            </h2>
            @if (count($underperformingAgents) > 0)
                <div class="space-y-2">
                    @foreach ($underperformingAgents as $agentStats)
                        <div class="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $agentStats['agent']['name'] }}</p>
                                    <p class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ __('Taux de conversion') }}: {{ $agentStats['conversion_rate'] }}%
                                    </p>
                                </div>
                                <flux:button href="{{ route('owner.agents.stats', $agentStats['agent']['id']) }}" size="sm" variant="ghost">
                                    {{ __('Voir') }}
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucun agent sous-performant') }}</p>
            @endif
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    function initializeCharts() {
        // Wait for Chart.js to be available
        if (typeof Chart === 'undefined') {
            console.error('Chart.js is not loaded');
            return;
        }

        // Leads over time chart
        const leadsOverTimeCtx = document.getElementById('leadsOverTimeChart');
        if (leadsOverTimeCtx) {
            // Destroy existing chart if it exists
            if (leadsOverTimeCtx.chart) {
                leadsOverTimeCtx.chart.destroy();
            }
            
            @php
                $leadsOverTimeArray = is_array($leadsOverTime) ? $leadsOverTime : [];
                $labels = array_column($leadsOverTimeArray, 'date');
                $counts = array_column($leadsOverTimeArray, 'count');
                // Ensure we have valid arrays
                $labels = $labels !== false ? $labels : [];
                $counts = $counts !== false ? $counts : [];
            @endphp
            
            try {
                leadsOverTimeCtx.chart = new Chart(leadsOverTimeCtx, {
                    type: 'line',
                    data: {
                        labels: @json($labels),
                        datasets: [{
                            label: '{{ __('Leads créés') }}',
                            data: @json($counts),
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: 'rgb(59, 130, 246)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: true,
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error creating leads over time chart:', error);
            }
        }

        // Agent performance chart
        const agentPerformanceCtx = document.getElementById('agentPerformanceChart');
        if (agentPerformanceCtx) {
            // Destroy existing chart if it exists
            if (agentPerformanceCtx.chart) {
                agentPerformanceCtx.chart.destroy();
            }
            
            @php
                $agentPerformanceArray = is_array($agentPerformance) ? $agentPerformance : [];
                $agentLabels = [];
                $conversionRates = [];
                foreach ($agentPerformanceArray as $item) {
                    if (isset($item['agent']['name'])) {
                        $agentLabels[] = $item['agent']['name'];
                    } elseif (isset($item['agent']->name)) {
                        $agentLabels[] = $item['agent']->name;
                    } else {
                        $agentLabels[] = '';
                    }
                    $conversionRates[] = $item['conversion_rate'] ?? 0;
                }
            @endphp

            try {
                agentPerformanceCtx.chart = new Chart(agentPerformanceCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($agentLabels),
                        datasets: [{
                            label: '{{ __('Taux de conversion (%)') }}',
                            data: @json($conversionRates),
                            backgroundColor: 'rgba(34, 197, 94, 0.5)',
                            borderColor: 'rgb(34, 197, 94)',
                            borderWidth: 2,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: true
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error creating agent performance chart:', error);
            }
        }
    }

    // Initialize when DOM is ready
    function waitForChartJS() {
        if (typeof Chart !== 'undefined') {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeCharts);
            } else {
                // DOM already loaded, wait a bit for Livewire to render
                setTimeout(initializeCharts, 200);
            }
        } else {
            setTimeout(waitForChartJS, 50);
        }
    }

    waitForChartJS();

    // Re-initialize after Livewire updates
    document.addEventListener('livewire:init', function() {
        setTimeout(initializeCharts, 200);
    });
    
    document.addEventListener('livewire:update', function() {
        setTimeout(initializeCharts, 200);
    });
</script>

