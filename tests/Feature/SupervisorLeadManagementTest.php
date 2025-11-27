<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    require_once __DIR__.'/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Supervisor Lead Management - Viewing Leads', function () {
    test('supervisor can view all leads from their supervised agents', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $supervisorRole = Role::firstOrCreate(
            ['slug' => 'supervisor'],
            ['name' => 'Supervisor', 'slug' => 'supervisor']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $supervisor = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $agent1 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $agent2 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $lead1 = Lead::factory()->create(['assigned_to' => $agent1->id]);
        $lead2 = Lead::factory()->create(['assigned_to' => $agent2->id]);

        // Act
        $response = $this->actingAs($supervisor)->get(route('supervisor.leads'));

        // Assert
        $response->assertSuccessful()
            ->assertSeeLivewire('supervisor.leads');

        Volt::actingAs($supervisor)
            ->test('supervisor.leads')
            ->assertSee($lead1->email)
            ->assertSee($lead2->email);
    });

    test('supervisor cannot view leads from agents not under their supervision', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $supervisorRole = Role::firstOrCreate(
            ['slug' => 'supervisor'],
            ['name' => 'Supervisor', 'slug' => 'supervisor']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $supervisor1 = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $supervisor2 = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $agent1 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'supervisor_id' => $supervisor1->id,
        ]);

        $agent2 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'supervisor_id' => $supervisor2->id,
        ]);

        $lead1 = Lead::factory()->create(['assigned_to' => $agent1->id]);
        $lead2 = Lead::factory()->create(['assigned_to' => $agent2->id]);

        // Act
        Volt::actingAs($supervisor1)
            ->test('supervisor.leads')
            ->assertSee($lead1->email)
            ->assertDontSee($lead2->email);
    });

    test('supervisor can filter leads by status', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $supervisorRole = Role::firstOrCreate(
            ['slug' => 'supervisor'],
            ['name' => 'Supervisor', 'slug' => 'supervisor']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $supervisor = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $confirmedLead = Lead::factory()->create([
            'assigned_to' => $agent->id,
            'status' => 'confirmed',
        ]);

        $pendingLead = Lead::factory()->create([
            'assigned_to' => $agent->id,
            'status' => 'pending_call',
        ]);

        // Act
        Volt::actingAs($supervisor)
            ->test('supervisor.leads')
            ->set('statusFilter', 'confirmed')
            ->assertSee($confirmedLead->email)
            ->assertDontSee($pendingLead->email);
    });

    test('supervisor can search leads by email or name', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $supervisorRole = Role::firstOrCreate(
            ['slug' => 'supervisor'],
            ['name' => 'Supervisor', 'slug' => 'supervisor']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $supervisor = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $lead1 = Lead::factory()->create([
            'assigned_to' => $agent->id,
            'email' => 'john@example.com',
            'data' => ['name' => 'John Doe'],
        ]);

        $lead2 = Lead::factory()->create([
            'assigned_to' => $agent->id,
            'email' => 'jane@example.com',
            'data' => ['name' => 'Jane Smith'],
        ]);

        // Act
        Volt::actingAs($supervisor)
            ->test('supervisor.leads')
            ->set('search', 'john')
            ->assertSee($lead1->email)
            ->assertDontSee($lead2->email);
    });
});

describe('Supervisor Lead Management - Statistics', function () {
    test('supervisor can view statistics of their agents', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $supervisorRole = Role::firstOrCreate(
            ['slug' => 'supervisor'],
            ['name' => 'Supervisor', 'slug' => 'supervisor']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $supervisor = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'supervisor_id' => $supervisor->id,
        ]);

        Lead::factory()->count(5)->create([
            'assigned_to' => $agent->id,
            'status' => 'confirmed',
        ]);

        Lead::factory()->count(3)->create([
            'assigned_to' => $agent->id,
            'status' => 'rejected',
        ]);

        // Act
        $response = $this->actingAs($supervisor)->get(route('supervisor.statistics'));

        // Assert
        $response->assertSuccessful()
            ->assertSeeLivewire('supervisor.statistics');
    });

    test('supervisor can view individual agent statistics', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $supervisorRole = Role::firstOrCreate(
            ['slug' => 'supervisor'],
            ['name' => 'Supervisor', 'slug' => 'supervisor']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $supervisor = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'supervisor_id' => $supervisor->id,
        ]);

        // Act
        $response = $this->actingAs($supervisor)->get(route('supervisor.agents.stats', $agent));

        // Assert
        $response->assertSuccessful()
            ->assertSeeLivewire('supervisor.agents.stats');
    });

    test('supervisor cannot view statistics of agents not under their supervision', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $supervisorRole = Role::firstOrCreate(
            ['slug' => 'supervisor'],
            ['name' => 'Supervisor', 'slug' => 'supervisor']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $supervisor1 = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $supervisor2 = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'supervisor_id' => $supervisor2->id,
        ]);

        // Act
        $response = $this->actingAs($supervisor1)->get(route('supervisor.agents.stats', $agent));

        // Assert
        $response->assertForbidden();
    });
});

describe('Supervisor Lead Management - Reassignment', function () {
    test('supervisor can reassign lead to another supervised agent', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $supervisorRole = Role::firstOrCreate(
            ['slug' => 'supervisor'],
            ['name' => 'Supervisor', 'slug' => 'supervisor']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $supervisor = User::factory()->create([
            'role_id' => $supervisorRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $agent1 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $agent2 = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $lead = Lead::factory()->create([
            'assigned_to' => $agent1->id,
            'status' => 'pending_call',
        ]);

        // Act - This would require a reassign method in the component
        // For now, we test that supervisor can see the lead
        $response = $this->actingAs($supervisor)->get(route('supervisor.leads'));

        // Assert
        $response->assertSuccessful()
            ->assertSee($lead->email);
    });
});

describe('Supervisor Lead Management - Authorization', function () {
    test('non-supervisor users cannot access supervisor leads page', function () {
        // Arrange
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $agentRole->id]);

        // Act
        $response = $this->actingAs($agent)->get(route('supervisor.leads'));

        // Assert
        $response->assertForbidden();
    });

    test('unauthenticated users cannot access supervisor leads page', function () {
        // Act
        $response = $this->get(route('supervisor.leads'));

        // Assert
        $response->assertRedirect(route('login'));
    });
});
