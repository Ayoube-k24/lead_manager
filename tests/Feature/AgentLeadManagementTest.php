<?php

declare(strict_types=1);

use App\LeadStatus;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    require_once __DIR__.'/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Agent Lead Management - Viewing Leads', function () {
    test('agent can view their assigned leads', function () {
        // Arrange
        $role = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

        // Act
        $response = $this->actingAs($agent)->get(route('agent.leads'));

        // Assert
        $response->assertSuccessful()
            ->assertSeeLivewire('agent.leads');
    });

    test('agent can view lead details', function () {
        // Arrange
        $role = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

        // Act
        $response = $this->actingAs($agent)->get(route('agent.leads.show', $lead));

        // Assert
        $response->assertSuccessful()
            ->assertSeeLivewire('agent.leads.show');
    });

    test('agent cannot view leads assigned to other agents', function () {
        // Arrange
        $role = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $role->id]);
        $otherAgent = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create(['assigned_to' => $otherAgent->id]);

        // Act
        $response = $this->actingAs($agent)->get(route('agent.leads.show', $lead));

        // Assert
        $response->assertForbidden();
    });

    test('agent sees only their assigned leads in list', function () {
        // Arrange
        $role = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $role->id]);
        $otherAgent = User::factory()->create(['role_id' => $role->id]);

        $agentLead = Lead::factory()->create(['assigned_to' => $agent->id]);
        $otherLead = Lead::factory()->create(['assigned_to' => $otherAgent->id]);

        // Act
        Volt::actingAs($agent)
            ->test('agent.leads')
            ->assertSee($agentLead->email)
            ->assertDontSee($otherLead->email);
    });
});

describe('Agent Lead Management - Status Updates', function () {
    test('agent can update lead status after call', function () {
        // Arrange
        $role = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create([
            'assigned_to' => $agent->id,
            'status' => 'pending_call',
        ]);

        // Act
        Volt::actingAs($agent)
            ->test('agent.leads.show', ['lead' => $lead])
            ->set('status', 'confirmed')
            ->set('comment', 'Lead intéressé, demande un rappel')
            ->call('updateStatus');

        // Assert
        $lead->refresh();
        expect($lead->status)->toBe('confirmed')
            ->and($lead->call_comment)->toBe('Lead intéressé, demande un rappel')
            ->and($lead->called_at)->not->toBeNull();
    });

    test('agent cannot update lead status to invalid status', function () {
        // Arrange
        $role = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create([
            'assigned_to' => $agent->id,
            'status' => 'pending_call',
        ]);

        // Act & Assert - Cannot set to pending_email (not a post-call status)
        Volt::actingAs($agent)
            ->test('agent.leads.show', ['lead' => $lead])
            ->set('status', 'pending_email')
            ->call('updateStatus')
            ->assertHasErrors(['status']);

        $lead->refresh();
        expect($lead->status)->toBe('pending_call'); // Status unchanged
    });

    test('agent can update to all valid post-call statuses', function () {
        // Arrange
        $role = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $role->id]);

        $validStatuses = LeadStatus::postCallStatuses();

        foreach ($validStatuses as $status) {
            $lead = Lead::factory()->create([
                'assigned_to' => $agent->id,
                'status' => 'pending_call',
            ]);

            // Act
            Volt::actingAs($agent)
                ->test('agent.leads.show', ['lead' => $lead])
                ->set('status', $status->value)
                ->set('comment', 'Test comment')
                ->call('updateStatus');

            // Assert
            $lead->refresh();
            expect($lead->status)->toBe($status->value);
        }
    });

    test('agent can add comment when updating status', function () {
        // Arrange
        $role = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create([
            'assigned_to' => $agent->id,
            'status' => 'pending_call',
        ]);

        // Act
        Volt::actingAs($agent)
            ->test('agent.leads.show', ['lead' => $lead])
            ->set('status', 'qualified')
            ->set('comment', 'Lead très intéressé, budget disponible')
            ->call('updateStatus');

        // Assert
        $lead->refresh();
        expect($lead->call_comment)->toBe('Lead très intéressé, budget disponible');
    });

    test('comment is optional when updating status', function () {
        // Arrange
        $role = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create([
            'assigned_to' => $agent->id,
            'status' => 'pending_call',
        ]);

        // Act
        Volt::actingAs($agent)
            ->test('agent.leads.show', ['lead' => $lead])
            ->set('status', 'rejected')
            ->set('comment', null)
            ->call('updateStatus');

        // Assert
        $lead->refresh();
        expect($lead->status)->toBe('rejected')
            ->and($lead->call_comment)->toBeNull();
    });
});

describe('Agent Lead Management - Status History', function () {
    test('agent can see status history for their lead', function () {
        // Arrange
        $role = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create([
            'assigned_to' => $agent->id,
            'status' => 'pending_call',
        ]);

        // Create status history
        ActivityLog::factory()->create([
            'action' => 'lead.status_updated',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'properties' => [
                'old_status' => 'email_confirmed',
                'new_status' => 'pending_call',
            ],
        ]);

        // Act
        $response = $this->actingAs($agent)->get(route('agent.leads.show', $lead));

        // Assert
        $response->assertSuccessful();
        $history = $lead->getStatusHistory();
        expect($history)->toHaveCount(1);
    });
});

describe('Agent Lead Management - Authorization', function () {
    test('non-agent users cannot access agent leads page', function () {
        // Arrange
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Owner', 'slug' => 'call_center_owner']
        );
        $owner = User::factory()->create(['role_id' => $ownerRole->id]);

        // Act
        $response = $this->actingAs($owner)->get(route('agent.leads'));

        // Assert
        $response->assertForbidden();
    });

    test('unauthenticated users cannot access agent leads page', function () {
        // Act
        $response = $this->get(route('agent.leads'));

        // Assert
        $response->assertRedirect(route('login'));
    });
});
