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

describe('Owner Lead Management - Viewing Leads', function () {
    test('owner can view all leads from their call center', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $lead1 = Lead::factory()->create(['call_center_id' => $callCenter->id]);
        $lead2 = Lead::factory()->create(['call_center_id' => $callCenter->id]);

        // Act
        $response = $this->actingAs($owner)->get(route('owner.leads'));

        // Assert
        $response->assertSuccessful()
            ->assertSeeLivewire('owner.leads');

        Volt::actingAs($owner)
            ->test('owner.leads')
            ->assertSee($lead1->email)
            ->assertSee($lead2->email);
    });

    test('owner cannot view leads from other call centers', function () {
        // Arrange
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );

        $owner1 = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
        ]);

        $lead1 = Lead::factory()->create(['call_center_id' => $callCenter1->id]);
        $lead2 = Lead::factory()->create(['call_center_id' => $callCenter2->id]);

        // Act
        Volt::actingAs($owner1)
            ->test('owner.leads')
            ->assertSee($lead1->email)
            ->assertDontSee($lead2->email);
    });

    test('owner can filter leads by status', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $confirmedLead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'confirmed',
        ]);

        $pendingLead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'pending_call',
        ]);

        // Act
        Volt::actingAs($owner)
            ->test('owner.leads')
            ->set('statusFilter', 'confirmed')
            ->assertSee($confirmedLead->email)
            ->assertDontSee($pendingLead->email);
    });

    test('owner can search leads by email or name', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $lead1 = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'email' => 'john@example.com',
            'data' => ['name' => 'John Doe'],
        ]);

        $lead2 = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'email' => 'jane@example.com',
            'data' => ['name' => 'Jane Smith'],
        ]);

        // Act
        Volt::actingAs($owner)
            ->test('owner.leads')
            ->set('search', 'john')
            ->assertSee($lead1->email)
            ->assertDontSee($lead2->email);
    });
});

describe('Owner Lead Management - Manual Assignment', function () {
    test('owner can manually assign lead to an agent', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        // Act
        Volt::actingAs($owner)
            ->test('owner.leads')
            ->call('openAssignModal', $lead->id)
            ->set('selectedAgentId', $agent->id)
            ->call('assignLead');

        // Assert
        $lead->refresh();
        expect($lead->assigned_to)->toBe($agent->id)
            ->and($lead->status)->toBe('pending_call');
    });

    test('owner cannot assign lead to agent from different call center', function () {
        // Arrange
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter2->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter1->id,
            'status' => 'email_confirmed',
        ]);

        // Act
        Volt::actingAs($owner)
            ->test('owner.leads')
            ->call('openAssignModal', $lead->id)
            ->set('selectedAgentId', $agent->id)
            ->call('assignLead')
            ->assertHasErrors(['assignment']);

        // Assert
        $lead->refresh();
        expect($lead->assigned_to)->not->toBe($agent->id);
    });

    test('owner can use auto-assign feature', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        // Act
        Volt::actingAs($owner)
            ->test('owner.leads')
            ->call('autoAssign', $lead->id);

        // Assert
        $lead->refresh();
        expect($lead->assigned_to)->toBe($agent->id)
            ->and($lead->status)->toBe('pending_call');
    });
});

describe('Owner Lead Management - Export', function () {
    test('owner can export leads to CSV', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        Lead::factory()->count(5)->create(['call_center_id' => $callCenter->id]);

        // Act
        $response = $this->actingAs($owner)->get(route('owner.leads.export.csv'));

        // Assert
        $response->assertSuccessful()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    test('owner cannot export leads from other call centers', function () {
        // Arrange
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );

        $owner1 = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
        ]);

        Lead::factory()->count(5)->create(['call_center_id' => $callCenter2->id]);

        // Act
        $response = $this->actingAs($owner1)->get(route('owner.leads.export.csv'));

        // Assert
        $response->assertSuccessful();
        // CSV should only contain leads from callCenter1
        $csvContent = $response->getContent();
        expect($csvContent)->not->toContain($callCenter2->name);
    });
});

describe('Owner Lead Management - Authorization', function () {
    test('non-owner users cannot access owner leads page', function () {
        // Arrange
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $agentRole->id]);

        // Act
        $response = $this->actingAs($agent)->get(route('owner.leads'));

        // Assert
        $response->assertForbidden();
    });

    test('unauthenticated users cannot access owner leads page', function () {
        // Act
        $response = $this->get(route('owner.leads'));

        // Assert
        $response->assertRedirect(route('login'));
    });

    test('owner from one call center cannot access leads from another call center', function () {
        // Arrange
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );

        $owner1 = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
        ]);

        $lead = Lead::factory()->create(['call_center_id' => $callCenter2->id]);

        // Act - Owner1 should not see lead from callCenter2
        Volt::actingAs($owner1)
            ->test('owner.leads')
            ->assertDontSee($lead->email);
    });
});
