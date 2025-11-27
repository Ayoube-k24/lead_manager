<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LeadDistributionService;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('LeadDistributionService - Round Robin Distribution', function () {
    test('distributes lead with round-robin method', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $assignedAgent = $service->distributeLead($lead);

        // Assert
        expect($assignedAgent)->not->toBeNull()
            ->and($assignedAgent->id)->toBe($agent->id);
    });

    test('distributes leads evenly across multiple agents with round-robin', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
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
        $service = new LeadDistributionService(app(AuditService::class));

        // Act - Distribute 4 leads
        $assignments = [];
        for ($i = 0; $i < 4; $i++) {
            $lead = Lead::factory()->create([
                'call_center_id' => $callCenter->id,
                'status' => 'email_confirmed',
            ]);
            $assignedAgent = $service->distributeLead($lead);
            if ($assignedAgent) {
                $service->assignToAgent($lead, $assignedAgent);
                $assignments[] = $assignedAgent->id;
            }
        }

        // Assert - Should be relatively evenly distributed
        $agent1Count = count(array_filter($assignments, fn ($id) => $id === $agent1->id));
        $agent2Count = count(array_filter($assignments, fn ($id) => $id === $agent2->id));

        expect($agent1Count)->toBeGreaterThan(0)
            ->and($agent2Count)->toBeGreaterThan(0)
            ->and($agent1Count + $agent2Count)->toBe(4);
    });

    test('considers workload when distributing with round-robin', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
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

        // Give agent1 some pending leads
        Lead::factory()->count(3)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent1->id,
            'status' => 'pending_call',
        ]);

        $service = new LeadDistributionService(app(AuditService::class));
        $newLead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
        ]);

        // Act
        $assignedAgent = $service->distributeLead($newLead);

        // Assert - Should assign to agent2 (has fewer pending leads)
        expect($assignedAgent)->not->toBeNull()
            ->and($assignedAgent->id)->toBe($agent2->id);
    });
});

describe('LeadDistributionService - Weighted Distribution', function () {
    test('distributes lead with weighted method', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'weighted']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $assignedAgent = $service->distributeLead($lead);

        // Assert
        expect($assignedAgent)->not->toBeNull()
            ->and($assignedAgent->id)->toBe($agent->id);
    });

    test('considers performance when distributing with weighted method', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'weighted']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
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

        // Agent1 has high performance (8 confirmed out of 10 = 0.8 score)
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

        // Agent2 has lower performance (2 confirmed out of 10 = 0.2 score)
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

        $service = new LeadDistributionService(app(AuditService::class));
        $newLead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
        ]);

        // Act
        $assignedAgent = $service->distributeLead($newLead);

        // Assert - Should assign to agent2 (lower performance score, needs more leads)
        expect($assignedAgent)->not->toBeNull()
            ->and($assignedAgent->id)->toBe($agent2->id);
    });

    test('considers workload when performance scores are similar in weighted method', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'weighted']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
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

        // Both agents have no leads (same default score 0.5)
        // But agent1 has more pending workload
        Lead::factory()->count(3)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent1->id,
            'status' => 'pending_call',
        ]);

        $service = new LeadDistributionService(app(AuditService::class));
        $newLead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
        ]);

        // Act
        $assignedAgent = $service->distributeLead($newLead);

        // Assert - Should assign to agent2 (same score but lower workload)
        expect($assignedAgent)->not->toBeNull()
            ->and($assignedAgent->id)->toBe($agent2->id);
    });
});

describe('LeadDistributionService - Manual Distribution', function () {
    test('returns null for manual distribution method', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'manual']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $assignedAgent = $service->distributeLead($lead);

        // Assert
        expect($assignedAgent)->toBeNull();
    });
});

describe('LeadDistributionService - No Active Agents', function () {
    test('returns null when no active agents available', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $assignedAgent = $service->distributeLead($lead);

        // Assert
        expect($assignedAgent)->toBeNull();
    });

    test('skips inactive agents during distribution', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $activeAgent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);
        User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => false,
        ]);
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $assignedAgent = $service->distributeLead($lead);

        // Assert
        expect($assignedAgent)->not->toBeNull()
            ->and($assignedAgent->id)->toBe($activeAgent->id);
    });
});

describe('LeadDistributionService - Manual Assignment', function () {
    test('assigns lead to agent manually', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $result = $service->assignToAgent($lead, $agent);

        // Assert
        expect($result)->toBeTrue();
        $lead->refresh();
        expect($lead->assigned_to)->toBe($agent->id);
    });

    test('fails to assign lead to agent from different call center', function () {
        // Arrange
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter1->id,
            'is_active' => true,
        ]);
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter2->id,
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $result = $service->assignToAgent($lead, $agent);

        // Assert
        expect($result)->toBeFalse();
        $lead->refresh();
        expect($lead->assigned_to)->not->toBe($agent->id);
    });

    test('fails to assign lead to inactive agent', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => false,
        ]);
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $result = $service->assignToAgent($lead, $agent);

        // Assert
        expect($result)->toBeFalse();
    });

    test('fails to assign lead to non-agent user', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Owner']);
        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $result = $service->assignToAgent($lead, $owner);

        // Assert
        expect($result)->toBeFalse();
    });
});

describe('LeadDistributionService - Edge Cases', function () {
    test('handles lead without call center when form has call center', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);
        $form = \App\Models\Form::factory()->create([
            'call_center_id' => $callCenter->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => null,
            'status' => 'email_confirmed',
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $assignedAgent = $service->distributeLead($lead);

        // Assert
        expect($assignedAgent)->not->toBeNull()
            ->and($assignedAgent->id)->toBe($agent->id);
        $lead->refresh();
        expect($lead->call_center_id)->toBe($callCenter->id);
    });

    test('returns null when lead has no call center and form has no call center', function () {
        // Arrange
        $form = \App\Models\Form::factory()->create([
            'call_center_id' => null,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => null,
            'status' => 'email_confirmed',
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $assignedAgent = $service->distributeLead($lead);

        // Assert
        expect($assignedAgent)->toBeNull();
    });

    test('uses default round-robin when distribution method is invalid', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'invalid_method']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
        ]);
        $service = new LeadDistributionService(app(AuditService::class));

        // Act
        $assignedAgent = $service->distributeLead($lead);

        // Assert - Should default to round-robin
        expect($assignedAgent)->not->toBeNull()
            ->and($assignedAgent->id)->toBe($agent->id);
    });
});
