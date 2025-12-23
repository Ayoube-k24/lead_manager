<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\StatisticsService;

describe('Statistics Performance', function () {
    beforeEach(function () {
        $this->statisticsService = new StatisticsService;
    });

    test('calculates global statistics efficiently with large dataset', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $confirmedStatus = LeadStatus::firstOrCreate(['slug' => 'confirmed'], ['name' => 'Confirmed']);
        $rejectedStatus = LeadStatus::firstOrCreate(['slug' => 'rejected'], ['name' => 'Rejected']);

        // Create a large dataset
        Lead::factory()->count(5000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'score' => 50,
        ]);

        Lead::factory()->count(2000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'confirmed',
            'score' => 80,
        ]);

        Lead::factory()->count(1000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'rejected',
            'score' => 20,
        ]);

        $startTime = microtime(true);

        $stats = $this->statisticsService->getGlobalStatistics();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Should complete in reasonable time (< 1000ms for 8000 records)
        expect($executionTime)->toBeLessThan(1000.0)
            ->and($stats)->toHaveKey('total_leads')
            ->and($stats)->toHaveKey('confirmed_leads')
            ->and($stats)->toHaveKey('conversion_rate')
            ->and($stats['total_leads'])->toBeGreaterThanOrEqual(8000);
    });

    test('calculates call center statistics efficiently', function () {
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        $form1 = Form::factory()->create(['call_center_id' => $callCenter1->id]);
        $form2 = Form::factory()->create(['call_center_id' => $callCenter2->id]);

        // Create leads for both call centers
        Lead::factory()->count(3000)->create([
            'form_id' => $form1->id,
            'call_center_id' => $callCenter1->id,
            'status' => 'pending_email',
            'score' => 50,
        ]);

        Lead::factory()->count(2000)->create([
            'form_id' => $form2->id,
            'call_center_id' => $callCenter2->id,
            'status' => 'confirmed',
            'score' => 80,
        ]);

        $startTime = microtime(true);

        $stats = $this->statisticsService->getCallCenterStatistics($callCenter1->id);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Should filter by call center efficiently
        expect($executionTime)->toBeLessThan(500.0)
            ->and($stats)->toHaveKey('total_leads')
            ->and($stats['total_leads'])->toBe(3000);
    });

    test('calculates agent statistics efficiently', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $agentRole = \App\Models\Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Create many leads assigned to agent
        Lead::factory()->count(2000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'status' => 'confirmed',
            'score' => 75,
        ]);

        $startTime = microtime(true);

        $stats = $this->statisticsService->getAgentStatistics($agent->id, $callCenter->id);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Should calculate agent stats efficiently
        expect($executionTime)->toBeLessThan(500.0)
            ->and($stats)->toHaveKey('total_leads')
            ->and($stats['total_leads'])->toBe(2000);
    });

    test('handles date range filtering efficiently', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        // Create leads over different time periods
        Lead::factory()->count(1000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'confirmed',
            'score' => 70,
            'created_at' => now()->subDays(10),
        ]);

        Lead::factory()->count(500)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'confirmed',
            'score' => 70,
            'created_at' => now()->subDays(5),
        ]);

        $startTime = microtime(true);

        $stats = $this->statisticsService->getCallCenterStatistics(
            $callCenter->id,
            now()->subDays(7),
            now()
        );

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Date filtering should be efficient
        expect($executionTime)->toBeLessThan(500.0)
            ->and($stats)->toHaveKey('total_leads');
    });
});
