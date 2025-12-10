<?php

use App\Services\StatisticsService;
use Livewire\Volt\Component;

new class extends Component
{
    public array $stats = [];
    public array $leadsOverTime = [];
    public array $leadsByStatus = [];
    public array $underperformingAgents = [];
    public array $leadsNeedingAttention = [];

    public function mount(StatisticsService $statisticsService): void
    {
        $this->stats = $statisticsService->getGlobalStatistics();
        $this->leadsOverTime = $this->stats['leads_over_time'] ?? [];
        $this->leadsByStatus = $this->stats['leads_by_status'] ?? [];
        $this->underperformingAgents = $statisticsService->getUnderperformingAgents()->toArray();
        $this->leadsNeedingAttention = $statisticsService->getLeadsNeedingAttention()->take(10)->toArray();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Statistiques Avancées') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Vue globale des performances de la plateforme') }}
            </p>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.statistics.export.csv') }}" variant="ghost" target="_blank">
                {{ __('Exporter CSV') }}
            </flux:button>
            <flux:button href="{{ route('admin.statistics.export.pdf') }}" variant="ghost" target="_blank">
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

        <!-- Répartition par statut -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Répartition par statut') }}
            </h2>
            @if (empty($leadsByStatus))
                <div class="flex h-32 items-center justify-center text-sm text-neutral-500 dark:text-neutral-400">
                    {{ __('Aucune donnée disponible') }}
                </div>
            @else
                <div class="relative h-64">
                    <canvas id="leadsByStatusChart"></canvas>
                </div>
            @endif
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
                                <flux:button href="{{ route('admin.leads.show', $lead['id']) }}" size="sm" variant="ghost">
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
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">
                                    {{ $agentStats['total_leads'] }} {{ __('leads') }}
                                </p>
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

        // Leads by status chart
        const leadsByStatusCtx = document.getElementById('leadsByStatusChart');
        if (leadsByStatusCtx) {
            // Destroy existing chart if it exists
            if (leadsByStatusCtx.chart) {
                leadsByStatusCtx.chart.destroy();
            }
            
            const statusLabels = {
                'pending_email': '{{ __('En attente email') }}',
                'email_confirmed': '{{ __('Email confirmé') }}',
                'pending_call': '{{ __('En attente appel') }}',
                'confirmed': '{{ __('Confirmé') }}',
                'rejected': '{{ __('Rejeté') }}',
                'callback_pending': '{{ __('Rappel en attente') }}'
            };

            @php
                $leadsByStatusArray = is_array($leadsByStatus) ? $leadsByStatus : [];
                $statusKeys = array_keys($leadsByStatusArray);
                $statusValues = array_values($leadsByStatusArray);
            @endphp

            try {
                leadsByStatusCtx.chart = new Chart(leadsByStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: @json($statusKeys).map(key => statusLabels[key] || key),
                        datasets: [{
                            data: @json($statusValues),
                            backgroundColor: [
                                'rgb(234, 179, 8)',
                                'rgb(59, 130, 246)',
                                'rgb(249, 115, 22)',
                                'rgb(34, 197, 94)',
                                'rgb(239, 68, 68)',
                                'rgb(168, 85, 247)'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                enabled: true,
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return label + ': ' + value + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error creating leads by status chart:', error);
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

