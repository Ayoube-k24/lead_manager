<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    require_once __DIR__.'/../../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

test('agent can view notes on lead detail page', function () {
    // Arrange
    $agentRole = Role::factory()->create(['slug' => 'agent']);
    $agent = User::factory()->create(['role_id' => $agentRole->id]);
    $lead = Lead::factory()->create(['assigned_to' => $agent->id]);
    LeadNote::factory()->count(3)->create(['lead_id' => $lead->id]);

    // Act
    $response = $this->actingAs($agent)->get(route('agent.leads.show', $lead));

    // Assert
    $response->assertSuccessful()
        ->assertSeeLivewire('agent.leads.show');
});

test('agent can create a public note on a lead', function () {
    // Arrange
    $agentRole = Role::factory()->create(['slug' => 'agent']);
    $agent = User::factory()->create(['role_id' => $agentRole->id]);
    $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

    // Act
    Volt::actingAs($agent)
        ->test('agent.leads.show', ['lead' => $lead])
        ->set('noteContent', 'This is a test note')
        ->set('noteIsPrivate', false)
        ->call('createNote')
        ->assertHasNoErrors();

    // Assert
    expect(LeadNote::where('lead_id', $lead->id)->count())->toBe(1)
        ->and(LeadNote::where('lead_id', $lead->id)->first()->is_private)->toBeFalse();
});

test('agent can create a private note on a lead', function () {
    // Arrange
    $agentRole = Role::factory()->create(['slug' => 'agent']);
    $agent = User::factory()->create(['role_id' => $agentRole->id]);
    $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

    // Act
    Volt::actingAs($agent)
        ->test('agent.leads.show', ['lead' => $lead])
        ->set('noteContent', 'This is a private note')
        ->set('noteIsPrivate', true)
        ->call('createNote')
        ->assertHasNoErrors();

    // Assert
    expect(LeadNote::where('lead_id', $lead->id)->count())->toBe(1)
        ->and(LeadNote::where('lead_id', $lead->id)->first()->is_private)->toBeTrue();
});

test('agent can only see their own private notes', function () {
    // Arrange
    $agentRole = Role::factory()->create(['slug' => 'agent']);
    $agent1 = User::factory()->create(['role_id' => $agentRole->id]);
    $agent2 = User::factory()->create(['role_id' => $agentRole->id]);
    $lead = Lead::factory()->create(['assigned_to' => $agent1->id]);

    LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent1->id,
        'is_private' => true,
        'content' => 'Private note from agent1',
    ]);

    LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent2->id,
        'is_private' => true,
        'content' => 'Private note from agent2',
    ]);

    LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent1->id,
        'is_private' => false,
        'content' => 'Public note',
    ]);

    // Act
    $response = $this->actingAs($agent1)->get(route('agent.leads.show', $lead));

    // Assert
    $response->assertSuccessful()
        ->assertSee('Private note from agent1')
        ->assertSee('Public note')
        ->assertDontSee('Private note from agent2');
});

test('super admin can see all private notes', function () {
    // Arrange
    $superAdminRole = Role::factory()->create(['slug' => 'super_admin']);
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
    $agentRole = Role::factory()->create(['slug' => 'agent']);
    $agent = User::factory()->create(['role_id' => $agentRole->id]);
    $lead = Lead::factory()->create();

    LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent->id,
        'is_private' => true,
        'content' => 'Private note',
    ]);

    // Act
    $response = $this->actingAs($superAdmin)->get(route('agent.leads.show', $lead));

    // Assert
    $response->assertSuccessful()
        ->assertSee('Private note');
});

test('agent can delete their own note', function () {
    // Arrange
    $agentRole = Role::factory()->create(['slug' => 'agent']);
    $agent = User::factory()->create(['role_id' => $agentRole->id]);
    $lead = Lead::factory()->create(['assigned_to' => $agent->id]);
    $note = LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent->id,
    ]);

    // Act
    Volt::actingAs($agent)
        ->test('agent.leads.show', ['lead' => $lead])
        ->call('deleteNote', $note->id)
        ->assertHasNoErrors();

    // Assert
    expect(LeadNote::find($note->id))->toBeNull();
});

test('agent cannot delete notes from other agents', function () {
    // Arrange
    $agentRole = Role::factory()->create(['slug' => 'agent']);
    $agent1 = User::factory()->create(['role_id' => $agentRole->id]);
    $agent2 = User::factory()->create(['role_id' => $agentRole->id]);
    $lead = Lead::factory()->create(['assigned_to' => $agent1->id]);
    $note = LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $agent2->id,
    ]);

    // Act & Assert
    Volt::actingAs($agent1)
        ->test('agent.leads.show', ['lead' => $lead])
        ->call('deleteNote', $note->id)
        ->assertForbidden();

    expect(LeadNote::find($note->id))->not->toBeNull();
});
