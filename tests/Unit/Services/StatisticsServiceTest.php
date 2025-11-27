<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\StatisticsService;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('StatisticsService - Global Statistics', function () {
    test('calculates global statistics correctly', function () {
        // Arrange
        Lead::factory()->count(10)->create(['status' => 'confirmed']);
        Lead::factory()->count(5)->create(['status' => 'rejected']);
        Lead::factory()->count(3)->create(['status' => 'pending_email']);
        Lead::factory()->count(2)->create(['status' => 'email_confirmed']);
        Lead::factory()->count(1)->create(['status' => 'pending_call']);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getGlobalStatistics();

        // Assert
        expect($stats)->toHaveKey('total_leads')
            ->toHaveKey('confirmed_leads')
            ->toHaveKey('rejected_leads')
            ->and($stats['total_leads'])->toBe(21)
            ->and($stats['confirmed_leads'])->toBe(10)
            ->and($stats['rejected_leads'])->toBe(5)
            ->and($stats['pending_leads'])->toBe(6) // pending_email + email_confirmed + pending_call
            ->and($stats['conversion_rate'])->toBeGreaterThan(0)
            ->and($stats['conversion_rate'])->toBeLessThanOrEqual(100);
    });

    test('calculates conversion rate correctly', function () {
        // Arrange
        Lead::factory()->count(8)->create(['status' => 'confirmed']);
        Lead::factory()->count(2)->create(['status' => 'rejected']);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getGlobalStatistics();

        // Assert - 8 confirmed out of 10 total = 80%
        expect($stats['conversion_rate'])->toBe(80.0);
    });

    test('returns zero conversion rate when no leads exist', function () {
        // Arrange
        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getGlobalStatistics();

        // Assert
        expect($stats['total_leads'])->toBe(0)
            ->and($stats['conversion_rate'])->toBe(0.0);
    });

    test('includes leads by status in global statistics', function () {
        // Arrange
        Lead::factory()->count(5)->create(['status' => 'confirmed']);
        Lead::factory()->count(3)->create(['status' => 'rejected']);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getGlobalStatistics();

        // Assert
        expect($stats['leads_by_status'])->toBeArray()
            ->and($stats['leads_by_status'])->toHaveKey('confirmed')
            ->and($stats['leads_by_status'])->toHaveKey('rejected')
            ->and($stats['leads_by_status']['confirmed'])->toBe(5)
            ->and($stats['leads_by_status']['rejected'])->toBe(3);
    });

    test('includes leads over time in global statistics', function () {
        // Arrange
        Lead::factory()->count(5)->create(['created_at' => now()]);
        Lead::factory()->count(3)->create(['created_at' => now()->subDays(5)]);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getGlobalStatistics();

        // Assert
        expect($stats['leads_over_time'])->toBeArray()
            ->and($stats['leads_over_time'])->toHaveCount(30); // Last 30 days
    });
});

describe('StatisticsService - Call Center Statistics', function () {
    test('calculates call center statistics correctly', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        Lead::factory()->count(10)->create([
            'call_center_id' => $callCenter->id,
            'status' => 'confirmed',
        ]);
        Lead::factory()->count(5)->create([
            'call_center_id' => $callCenter->id,
            'status' => 'rejected',
        ]);
        Lead::factory()->count(3)->create([
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
        ]);

        // Create leads for different call center (should not be included)
        Lead::factory()->count(5)->create([
            'status' => 'confirmed',
        ]);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getCallCenterStatistics($callCenter);

        // Assert
        expect($stats)->toHaveKey('call_center')
            ->toHaveKey('total_leads')
            ->toHaveKey('confirmed_leads')
            ->toHaveKey('rejected_leads')
            ->and($stats['total_leads'])->toBe(18) // Only leads for this call center
            ->and($stats['confirmed_leads'])->toBe(10)
            ->and($stats['rejected_leads'])->toBe(5)
            ->and($stats['conversion_rate'])->toBeGreaterThan(0);
    });

    test('includes agent performance in call center statistics', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
        ]);
        Lead::factory()->count(5)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'status' => 'confirmed',
        ]);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getCallCenterStatistics($callCenter);

        // Assert
        expect($stats['agent_performance'])->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($stats['agent_performance']->count())->toBeGreaterThan(0);
    });

    test('calculates conversion rate for call center correctly', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        Lead::factory()->count(6)->create([
            'call_center_id' => $callCenter->id,
            'status' => 'confirmed',
        ]);
        Lead::factory()->count(4)->create([
            'call_center_id' => $callCenter->id,
            'status' => 'rejected',
        ]);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getCallCenterStatistics($callCenter);

        // Assert - 6 confirmed out of 10 total = 60%
        expect($stats['conversion_rate'])->toBe(60.0);
    });
});

describe('StatisticsService - Agent Statistics', function () {
    test('calculates agent statistics correctly', function () {
        // Arrange
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create(['role_id' => $agentRole->id]);
        Lead::factory()->count(8)->create([
            'assigned_to' => $agent->id,
            'status' => 'confirmed',
        ]);
        Lead::factory()->count(2)->create([
            'assigned_to' => $agent->id,
            'status' => 'rejected',
        ]);
        Lead::factory()->count(3)->create([
            'assigned_to' => $agent->id,
            'status' => 'pending_call',
        ]);

        // Create leads for different agent (should not be included)
        $otherAgent = User::factory()->create(['role_id' => $agentRole->id]);
        Lead::factory()->count(5)->create([
            'assigned_to' => $otherAgent->id,
            'status' => 'confirmed',
        ]);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getAgentStatistics($agent);

        // Assert
        expect($stats)->toHaveKey('agent')
            ->toHaveKey('total_leads')
            ->toHaveKey('confirmed_leads')
            ->toHaveKey('rejected_leads')
            ->and($stats['total_leads'])->toBe(13) // Only leads for this agent
            ->and($stats['confirmed_leads'])->toBe(8)
            ->and($stats['rejected_leads'])->toBe(2)
            ->and($stats['pending_leads'])->toBe(3)
            ->and($stats['conversion_rate'])->toBeGreaterThan(0);
    });

    test('calculates conversion rate for agent correctly', function () {
        // Arrange
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create(['role_id' => $agentRole->id]);
        Lead::factory()->count(7)->create([
            'assigned_to' => $agent->id,
            'status' => 'confirmed',
        ]);
        Lead::factory()->count(3)->create([
            'assigned_to' => $agent->id,
            'status' => 'rejected',
        ]);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getAgentStatistics($agent);

        // Assert - 7 confirmed out of 10 total = 70%
        expect($stats['conversion_rate'])->toBe(70.0);
    });
});

describe('StatisticsService - Average Processing Time', function () {
    test('calculates average processing time correctly', function () {
        // Arrange
        $lead1 = Lead::factory()->create([
            'status' => 'confirmed',
            'email_confirmed_at' => now()->subHours(10),
            'called_at' => now(),
        ]);
        $lead2 = Lead::factory()->create([
            'status' => 'confirmed',
            'email_confirmed_at' => now()->subHours(20),
            'called_at' => now(),
        ]);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getGlobalStatistics();

        // Assert - Average of 10 and 20 hours = 15 hours
        expect($stats['avg_processing_time'])->toBe(15.0);
    });

    test('returns zero when no confirmed leads with processing time', function () {
        // Arrange
        Lead::factory()->count(5)->create([
            'status' => 'pending_email',
        ]);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getGlobalStatistics();

        // Assert
        expect($stats['avg_processing_time'])->toBe(0.0);
    });

    test('calculates average processing time for call center', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'confirmed',
            'email_confirmed_at' => now()->subHours(12),
            'called_at' => now(),
        ]);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getCallCenterStatistics($callCenter);

        // Assert
        expect($stats['avg_processing_time'])->toBe(12.0);
    });

    test('calculates average processing time for agent', function () {
        // Arrange
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create(['role_id' => $agentRole->id]);
        Lead::factory()->create([
            'assigned_to' => $agent->id,
            'status' => 'confirmed',
            'email_confirmed_at' => now()->subHours(8),
            'called_at' => now(),
        ]);

        $service = app(StatisticsService::class);

        // Act
        $stats = $service->getAgentStatistics($agent);

        // Assert
        expect($stats['avg_processing_time'])->toBe(8.0);
    });
});

describe('StatisticsService - Leads Needing Attention', function () {
    test('identifies leads needing attention', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $oldLead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'email_confirmed_at' => now()->subHours(50), // Over 48 hours
        ]);
        $newLead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'email_confirmed_at' => now()->subHours(10), // Under 48 hours
        ]);

        $service = app(StatisticsService::class);

        // Act
        $leadsNeedingAttention = $service->getLeadsNeedingAttention($callCenter, 48);

        // Assert
        expect($leadsNeedingAttention)->toHaveCount(1)
            ->and($leadsNeedingAttention->first()->id)->toBe($oldLead->id);
    });

    test('identifies leads without called_at as needing attention', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'pending_call',
            'email_confirmed_at' => now(),
            'called_at' => null,
        ]);

        $service = app(StatisticsService::class);

        // Act
        $leadsNeedingAttention = $service->getLeadsNeedingAttention($callCenter, 48);

        // Assert
        expect($leadsNeedingAttention)->toHaveCount(1)
            ->and($leadsNeedingAttention->first()->id)->toBe($lead->id);
    });

    test('filters leads needing attention by call center', function () {
        // Arrange
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        Lead::factory()->create([
            'call_center_id' => $callCenter1->id,
            'status' => 'email_confirmed',
            'email_confirmed_at' => now()->subHours(50),
        ]);
        Lead::factory()->create([
            'call_center_id' => $callCenter2->id,
            'status' => 'email_confirmed',
            'email_confirmed_at' => now()->subHours(50),
        ]);

        $service = app(StatisticsService::class);

        // Act
        $leadsNeedingAttention = $service->getLeadsNeedingAttention($callCenter1, 48);

        // Assert
        expect($leadsNeedingAttention)->toHaveCount(1)
            ->and($leadsNeedingAttention->first()->call_center_id)->toBe($callCenter1->id);
    });
});

describe('StatisticsService - Underperforming Agents', function () {
    test('identifies underperforming agents', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $underperformingAgent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
        ]);
        $goodAgent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        // Underperforming agent: 1 confirmed out of 15 total = 6.67% (below 20%)
        Lead::factory()->count(1)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $underperformingAgent->id,
            'status' => 'confirmed',
        ]);
        Lead::factory()->count(14)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $underperformingAgent->id,
            'status' => 'rejected',
        ]);

        // Good agent: 8 confirmed out of 10 total = 80% (above 20%)
        Lead::factory()->count(8)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $goodAgent->id,
            'status' => 'confirmed',
        ]);
        Lead::factory()->count(2)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $goodAgent->id,
            'status' => 'rejected',
        ]);

        $service = app(StatisticsService::class);

        // Act
        $underperformingAgents = $service->getUnderperformingAgents($callCenter, 20.0);

        // Assert
        expect($underperformingAgents)->toHaveCount(1)
            ->and($underperformingAgents->first()['agent']->id)->toBe($underperformingAgent->id);
    });

    test('excludes agents with less than 10 leads from underperforming list', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        // Agent has only 5 leads (below minimum of 10)
        Lead::factory()->count(5)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'status' => 'rejected',
        ]);

        $service = app(StatisticsService::class);

        // Act
        $underperformingAgents = $service->getUnderperformingAgents($callCenter, 20.0);

        // Assert
        expect($underperformingAgents)->toHaveCount(0);
    });

    test('filters underperforming agents by call center', function () {
        // Arrange
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent1 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter1->id,
        ]);
        $agent2 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter2->id,
        ]);

        // Both agents are underperforming
        Lead::factory()->count(1)->create([
            'call_center_id' => $callCenter1->id,
            'assigned_to' => $agent1->id,
            'status' => 'confirmed',
        ]);
        Lead::factory()->count(14)->create([
            'call_center_id' => $callCenter1->id,
            'assigned_to' => $agent1->id,
            'status' => 'rejected',
        ]);

        Lead::factory()->count(1)->create([
            'call_center_id' => $callCenter2->id,
            'assigned_to' => $agent2->id,
            'status' => 'confirmed',
        ]);
        Lead::factory()->count(14)->create([
            'call_center_id' => $callCenter2->id,
            'assigned_to' => $agent2->id,
            'status' => 'rejected',
        ]);

        $service = app(StatisticsService::class);

        // Act
        $underperformingAgents = $service->getUnderperformingAgents($callCenter1, 20.0);

        // Assert
        expect($underperformingAgents)->toHaveCount(1)
            ->and($underperformingAgents->first()['agent']->id)->toBe($agent1->id);
    });
});
