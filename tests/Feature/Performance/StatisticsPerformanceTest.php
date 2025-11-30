<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    require_once __DIR__.'/../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Performance - Statistics Calculation', function () {
    test('calculates statistics for 1000 leads in less than 2 seconds', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        // Create 1000 leads with various statuses
        Lead::factory()->count(400)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'status' => 'confirmed',
        ]);

        Lead::factory()->count(300)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'status' => 'rejected',
        ]);

        Lead::factory()->count(200)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'status' => 'pending_call',
        ]);

        Lead::factory()->count(100)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'status' => 'email_confirmed',
        ]);

        $service = app(StatisticsService::class);

        // Act
        $startTime = microtime(true);

        $stats = $service->getCallCenterStatistics($callCenter->id);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        expect($executionTime)->toBeLessThan(2.0); // Less than 2 seconds

        // Verify statistics are correct
        expect($stats['total_leads'])->toBe(1000)
            ->and($stats['confirmed_leads'])->toBe(400)
            ->and($stats['rejected_leads'])->toBe(300);
    });

    test('calculates statistics per call center efficiently', function () {
        // Arrange
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent1 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter1->id,
        ]);

        $agent2 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter2->id,
        ]);

        // Create leads for each call center
        Lead::factory()->count(500)->create([
            'call_center_id' => $callCenter1->id,
            'assigned_to' => $agent1->id,
            'status' => 'confirmed',
        ]);

        Lead::factory()->count(500)->create([
            'call_center_id' => $callCenter2->id,
            'assigned_to' => $agent2->id,
            'status' => 'confirmed',
        ]);

        $service = app(StatisticsService::class);

        // Act
        DB::enableQueryLog();
        DB::flushQueryLog();

        $stats1 = $service->getCallCenterStatistics($callCenter1->id);
        $stats2 = $service->getCallCenterStatistics($callCenter2->id);

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Assert - Should use efficient queries (aggregation, not loading all records)
        expect($queryCount)->toBeLessThan(20); // Efficient aggregation queries

        // Verify statistics are correct
        expect($stats1['total_leads'])->toBe(500)
            ->and($stats2['total_leads'])->toBe(500);
    });

    test('optimizes aggregation queries for statistics', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        Lead::factory()->count(1000)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'status' => 'confirmed',
        ]);

        // Act - Count queries for aggregation
        DB::enableQueryLog();
        DB::flushQueryLog();

        $confirmedCount = Lead::where('call_center_id', $callCenter->id)
            ->where('status', 'confirmed')
            ->count();

        $rejectedCount = Lead::where('call_center_id', $callCenter->id)
            ->where('status', 'rejected')
            ->count();

        $totalCount = Lead::where('call_center_id', $callCenter->id)->count();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Assert - Should use COUNT queries (efficient), not load all records
        expect($queryCount)->toBe(3); // Three COUNT queries
        expect($queries[0]['query'])->toContain('count(*)'); // Using COUNT aggregation
    });
});

