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

    $this->ownerRole = Role::factory()->create([
        'name' => 'Owner',
        'slug' => 'call_center_owner',
    ]);

    $this->supervisorRole = Role::factory()->create([
        'name' => 'Supervisor',
        'slug' => 'supervisor',
    ]);

    $this->agentRole = Role::factory()->create([
        'name' => 'Agent',
        'slug' => 'agent',
    ]);
});

test('supervisor can view their supervised agents', function () {
    $callCenter = CallCenter::factory()->create();

    $owner = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->ownerRole->id,
        'call_center_id' => $callCenter->id,
    ]);

    $supervisor = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->supervisorRole->id,
        'call_center_id' => $callCenter->id,
    ]);

    $agent1 = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->agentRole->id,
        'call_center_id' => $callCenter->id,
        'supervisor_id' => $supervisor->id,
    ]);

    $agent2 = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->agentRole->id,
        'call_center_id' => $callCenter->id,
        'supervisor_id' => $supervisor->id,
    ]);

    // Agent not supervised by this supervisor
    $agent3 = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->agentRole->id,
        'call_center_id' => $callCenter->id,
        'supervisor_id' => null,
    ]);

    $response = $this->actingAs($supervisor)->get(route('supervisor.agents'));

    $response->assertSuccessful();
    $response->assertSee($agent1->name);
    $response->assertSee($agent2->name);
    $response->assertDontSee($agent3->name);
});

test('supervisor can view leads from their supervised agents', function () {
    $callCenter = CallCenter::factory()->create();

    $supervisor = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->supervisorRole->id,
        'call_center_id' => $callCenter->id,
    ]);

    $agent = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->agentRole->id,
        'call_center_id' => $callCenter->id,
        'supervisor_id' => $supervisor->id,
    ]);

    $lead = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent->id,
        'status' => 'pending_call',
    ]);

    $response = $this->actingAs($supervisor)->get(route('supervisor.leads'));

    $response->assertSuccessful();
    $response->assertSee($lead->email);
});

test('supervisor cannot view leads from agents not under their supervision', function () {
    $callCenter = CallCenter::factory()->create();

    $supervisor1 = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->supervisorRole->id,
        'call_center_id' => $callCenter->id,
    ]);

    $supervisor2 = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->supervisorRole->id,
        'call_center_id' => $callCenter->id,
    ]);

    $agent1 = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->agentRole->id,
        'call_center_id' => $callCenter->id,
        'supervisor_id' => $supervisor1->id,
    ]);

    $agent2 = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->agentRole->id,
        'call_center_id' => $callCenter->id,
        'supervisor_id' => $supervisor2->id,
    ]);

    $lead1 = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent1->id,
        'status' => 'pending_call',
    ]);

    $lead2 = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent2->id,
        'status' => 'pending_call',
    ]);

    $response = $this->actingAs($supervisor1)->get(route('supervisor.leads'));

    $response->assertSuccessful();
    $response->assertSee($lead1->email);
    $response->assertDontSee($lead2->email);
});

test('owner can assign supervisor to agent', function () {
    $callCenter = CallCenter::factory()->create();

    $owner = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->ownerRole->id,
        'call_center_id' => $callCenter->id,
    ]);

    $supervisor = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->supervisorRole->id,
        'call_center_id' => $callCenter->id,
    ]);

    $agent = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->agentRole->id,
        'call_center_id' => $callCenter->id,
        'supervisor_id' => null,
    ]);

    Volt::actingAs($owner)
        ->test('owner.agents.edit', ['user' => $agent])
        ->set('supervisor_id', $supervisor->id)
        ->call('update');

    expect($agent->fresh()->supervisor_id)->toBe($supervisor->id);
});

test('user model has supervisor relationship', function () {
    $callCenter = CallCenter::factory()->create();

    $supervisor = User::factory()->create([
        'role_id' => $this->supervisorRole->id,
        'call_center_id' => $callCenter->id,
    ]);

    $agent = User::factory()->create([
        'role_id' => $this->agentRole->id,
        'call_center_id' => $callCenter->id,
        'supervisor_id' => $supervisor->id,
    ]);

    expect($agent->supervisor)->not->toBeNull()
        ->and($agent->supervisor->id)->toBe($supervisor->id)
        ->and($supervisor->supervisedAgents)->toHaveCount(1)
        ->and($supervisor->supervisedAgents->first()->id)->toBe($agent->id);
});
