<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\StatisticsService;

describe('StatisticsService', function () {
    beforeEach(function () {
        $this->service = new StatisticsService();
    });

    describe('getGlobalStatistics', function () {
        test('calculates global statistics correctly', function () {
            Lead::factory()->count(5)->create(['status' => 'confirmed']);
            Lead::factory()->count(3)->create(['status' => 'rejected']);
            Lead::factory()->count(2)->create(['status' => 'pending_email']);

            $stats = $this->service->getGlobalStatistics();

            expect($stats)->toHaveKeys([
                'total_leads',
                'confirmed_leads',
                'rejected_leads',
                'pending_leads',
                'conversion_rate',
                'avg_processing_time',
                'leads_by_status',
                'leads_over_time',
            ])
                ->and($stats['total_leads'])->toBe(10)
                ->and($stats['confirmed_leads'])->toBe(5)
                ->and($stats['rejected_leads'])->toBe(3)
                ->and($stats['pending_leads'])->toBe(2)
                ->and($stats['conversion_rate'])->toBe(50.0);
        });

        test('calculates conversion rate correctly', function () {
            Lead::factory()->count(10)->create(['status' => 'confirmed']);
            Lead::factory()->count(5)->create(['status' => 'rejected']);

            $stats = $this->service->getGlobalStatistics();

            expect($stats['conversion_rate'])->toBe(66.67);
        });

        test('returns zero conversion rate when no leads', function () {
            $stats = $this->service->getGlobalStatistics();

            // Service returns int 0, not float 0.0
            expect($stats['conversion_rate'])->toBe(0);
        });

        test('includes leads by status breakdown', function () {
            Lead::factory()->count(3)->create(['status' => 'confirmed']);
            Lead::factory()->count(2)->create(['status' => 'rejected']);

            $stats = $this->service->getGlobalStatistics();

            expect($stats['leads_by_status'])->toBeArray()
                ->and($stats['leads_by_status']['confirmed'])->toBe(3)
                ->and($stats['leads_by_status']['rejected'])->toBe(2);
        });
    });

    describe('getCallCenterStatistics', function () {
        test('calculates call center statistics', function () {
            $callCenter = CallCenter::factory()->create();

            Lead::factory()->count(5)->create([
                'call_center_id' => $callCenter->id,
                'status' => 'confirmed',
            ]);
            Lead::factory()->count(3)->create([
                'call_center_id' => $callCenter->id,
                'status' => 'rejected',
            ]);

            $stats = $this->service->getCallCenterStatistics($callCenter);

            expect($stats['total_leads'])->toBe(8)
                ->and($stats['confirmed_leads'])->toBe(5)
                ->and($stats['rejected_leads'])->toBe(3)
                ->and($stats['call_center']->id)->toBe($callCenter->id);
        });

        test('includes agent performance', function () {
            $callCenter = CallCenter::factory()->create();
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
            ]);

            Lead::factory()->count(3)->create([
                'assigned_to' => $agent->id,
                'status' => 'confirmed',
            ]);
            Lead::factory()->count(2)->create([
                'assigned_to' => $agent->id,
                'status' => 'rejected',
            ]);

            $stats = $this->service->getCallCenterStatistics($callCenter);

            expect($stats['agent_performance'])->toBeInstanceOf(\Illuminate\Support\Collection::class)
                ->and($stats['agent_performance']->first()['agent']->id)->toBe($agent->id);
        });
    });

    describe('getAgentStatistics', function () {
        test('calculates agent statistics', function () {
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $agent = User::factory()->create(['role_id' => $role->id]);

            Lead::factory()->count(4)->create([
                'assigned_to' => $agent->id,
                'status' => 'confirmed',
            ]);
            Lead::factory()->count(2)->create([
                'assigned_to' => $agent->id,
                'status' => 'rejected',
            ]);

            $stats = $this->service->getAgentStatistics($agent);

            expect($stats['total_leads'])->toBe(6)
                ->and($stats['confirmed_leads'])->toBe(4)
                ->and($stats['rejected_leads'])->toBe(2)
                ->and($stats['conversion_rate'])->toBe(66.67)
                ->and($stats['agent']->id)->toBe($agent->id);
        });
    });

    describe('getLeadsNeedingAttention', function () {
        test('returns leads that need attention', function () {
            // Lead 1: email_confirmed, confirmed 50h ago, not called -> should match
            Lead::factory()->create([
                'status' => 'email_confirmed',
                'email_confirmed_at' => now()->subHours(50),
                'called_at' => null,
                'score' => 50,
            ]);
            // Lead 2: pending_call, confirmed 50h ago, not called -> should match
            Lead::factory()->create([
                'status' => 'pending_call',
                'email_confirmed_at' => now()->subHours(50),
                'called_at' => null,
                'score' => 50,
            ]);
            // Lead 3: email_confirmed, confirmed 1h ago, not called -> should NOT match (within threshold)
            Lead::factory()->create([
                'status' => 'email_confirmed',
                'email_confirmed_at' => now()->subHour(),
                'called_at' => null,
                'score' => 50,
            ]);

            $leads = $this->service->getLeadsNeedingAttention(null, 48);

            // Should return 2 leads (the ones confirmed more than 48h ago)
            expect($leads->count())->toBe(2);
        });

        test('filters by call center', function () {
            $callCenter = CallCenter::factory()->create();

            Lead::factory()->create([
                'call_center_id' => $callCenter->id,
                'status' => 'email_confirmed',
                'email_confirmed_at' => now()->subHours(50),
            ]);
            Lead::factory()->create([
                'call_center_id' => CallCenter::factory()->create()->id,
                'status' => 'email_confirmed',
                'email_confirmed_at' => now()->subHours(50),
            ]);

            $leads = $this->service->getLeadsNeedingAttention($callCenter, 48);

            expect($leads->count())->toBe(1);
        });
    });

    describe('getUnderperformingAgents', function () {
        test('returns agents with low conversion rate', function () {
            $callCenter = CallCenter::factory()->create();
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent1 = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
            ]);

            // Agent with low conversion rate
            Lead::factory()->count(15)->create([
                'assigned_to' => $agent1->id,
                'status' => 'confirmed',
            ]);
            Lead::factory()->count(85)->create([
                'assigned_to' => $agent1->id,
                'status' => 'rejected',
            ]);

            $agents = $this->service->getUnderperformingAgents($callCenter, 20.0);

            expect($agents->count())->toBe(1)
                ->and($agents->first()['agent']->id)->toBe($agent1->id);
        });

        test('excludes agents with less than 10 leads', function () {
            $callCenter = CallCenter::factory()->create();
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
            ]);

            Lead::factory()->count(5)->create([
                'assigned_to' => $agent->id,
                'status' => 'rejected',
            ]);

            $agents = $this->service->getUnderperformingAgents($callCenter, 20.0);

            expect($agents->count())->toBe(0);
        });
    });
});


