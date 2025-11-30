<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadDistributionService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    require_once __DIR__.'/../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Performance - Lead Distribution', function () {
    test('distributes 100 leads in less than 5 seconds', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agents = User::factory()->count(5)->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $leads = Lead::factory()->count(100)->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        $service = app(LeadDistributionService::class);

        // Act
        $startTime = microtime(true);

        foreach ($leads as $lead) {
            $assignedAgent = $service->distributeLead($lead);
            if ($assignedAgent) {
                $service->assignToAgent($lead, $assignedAgent);
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert
        expect($executionTime)->toBeLessThan(5.0); // Less than 5 seconds

        // Verify all leads are assigned
        $assignedLeads = Lead::where('call_center_id', $callCenter->id)
            ->whereNotNull('assigned_to')
            ->count();

        expect($assignedLeads)->toBe(100);
    });

    test('manages 1000 leads efficiently without N+1 queries', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agents = User::factory()->count(10)->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $leads = Lead::factory()->count(1000)->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        $service = app(LeadDistributionService::class);

        // Act - Count queries
        DB::enableQueryLog();
        DB::flushQueryLog();

        foreach ($leads as $lead) {
            $assignedAgent = $service->distributeLead($lead);
            if ($assignedAgent) {
                $service->assignToAgent($lead, $assignedAgent);
            }
        }

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Assert - Should not have excessive queries (N+1 problem)
        // Each lead should require minimal queries (ideally < 10 queries per lead)
        // With 1000 leads, we expect reasonable query count
        expect($queryCount)->toBeLessThan(5000); // Less than 5 queries per lead on average

        // Verify all leads are assigned
        $assignedLeads = Lead::where('call_center_id', $callCenter->id)
            ->whereNotNull('assigned_to')
            ->count();

        expect($assignedLeads)->toBe(1000);
    });

    test('optimizes queries by eager loading relationships', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agents = User::factory()->count(5)->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $leads = Lead::factory()->count(100)->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        // Act - Load leads with eager loading
        DB::enableQueryLog();
        DB::flushQueryLog();

        $leadsWithRelations = Lead::where('call_center_id', $callCenter->id)
            ->with(['form', 'callCenter', 'assignedAgent'])
            ->get();

        $queriesWithEagerLoading = count(DB::getQueryLog());

        // Act - Load leads without eager loading (simulate N+1)
        DB::flushQueryLog();

        $leadsWithoutRelations = Lead::where('call_center_id', $callCenter->id)->get();
        foreach ($leadsWithoutRelations as $lead) {
            $lead->form; // Trigger N+1
            $lead->callCenter; // Trigger N+1
            $lead->assignedAgent; // Trigger N+1
        }

        $queriesWithoutEagerLoading = count(DB::getQueryLog());

        // Assert - Eager loading should use fewer queries
        expect($queriesWithEagerLoading)->toBeLessThan($queriesWithoutEagerLoading);
    });
});

