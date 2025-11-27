<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadDistributionService;

beforeEach(function () {
    require_once __DIR__.'/../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Multi-Agent Distribution - Round Robin', function () {
    test('distributes leads evenly across multiple agents using round-robin', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent1 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $agent2 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $agent3 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $service = app(LeadDistributionService::class);

        // Act - Distribute 6 leads
        $leads = Lead::factory()->count(6)->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        foreach ($leads as $lead) {
            $assignedAgent = $service->distributeLead($lead);
            if ($assignedAgent) {
                $service->assignToAgent($lead, $assignedAgent);
            }
        }

        // Assert - Leads distributed evenly
        $agent1Leads = Lead::where('assigned_to', $agent1->id)->count();
        $agent2Leads = Lead::where('assigned_to', $agent2->id)->count();
        $agent3Leads = Lead::where('assigned_to', $agent3->id)->count();

        expect($agent1Leads)->toBe(2)
            ->and($agent2Leads)->toBe(2)
            ->and($agent3Leads)->toBe(2);
    });

    test('round-robin considers current workload when distributing', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent1 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $agent2 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Agent1 already has 5 pending leads
        Lead::factory()->count(5)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent1->id,
            'status' => 'pending_call',
        ]);

        $service = app(LeadDistributionService::class);

        // Act - Distribute new lead
        $newLead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        $assignedAgent = $service->distributeLead($newLead);
        if ($assignedAgent) {
            $service->assignToAgent($newLead, $assignedAgent);
        }

        // Assert - Should assign to agent2 (less workload)
        $newLead->refresh();
        expect($newLead->assigned_to)->toBe($agent2->id);
    });
});

describe('Multi-Agent Distribution - Weighted', function () {
    test('distributes leads based on agent performance using weighted method', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'weighted']);
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent1 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $agent2 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Agent1 has high performance (8 confirmed out of 10)
        Lead::factory()->count(8)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent1->id,
            'status' => 'confirmed',
        ]);
        Lead::factory()->count(2)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent1->id,
            'status' => 'rejected',
        ]);

        // Agent2 has lower performance (2 confirmed out of 10)
        Lead::factory()->count(2)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent2->id,
            'status' => 'confirmed',
        ]);
        Lead::factory()->count(8)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent2->id,
            'status' => 'rejected',
        ]);

        $service = app(LeadDistributionService::class);

        // Act - Distribute new lead
        $newLead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        $assignedAgent = $service->distributeLead($newLead);
        if ($assignedAgent) {
            $service->assignToAgent($newLead, $assignedAgent);
        }

        // Assert - Should assign to agent2 (lower performance, needs more leads)
        $newLead->refresh();
        expect($newLead->assigned_to)->toBe($agent2->id);
    });
});

describe('Multi-Agent Distribution - Edge Cases', function () {
    test('handles distribution when some agents are inactive', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $activeAgent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $inactiveAgent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => false,
        ]);

        $service = app(LeadDistributionService::class);

        // Act - Distribute lead
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        $assignedAgent = $service->distributeLead($lead);
        if ($assignedAgent) {
            $service->assignToAgent($lead, $assignedAgent);
        }

        // Assert - Should only assign to active agent
        $lead->refresh();
        expect($lead->assigned_to)->toBe($activeAgent->id)
            ->and($lead->assigned_to)->not->toBe($inactiveAgent->id);
    });

    test('returns null when no active agents available', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => false,
        ]);

        $service = app(LeadDistributionService::class);

        // Act - Try to distribute lead
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        $assignedAgent = $service->distributeLead($lead);

        // Assert - Should return null
        expect($assignedAgent)->toBeNull();
    });
});
