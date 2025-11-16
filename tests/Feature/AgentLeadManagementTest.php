<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\User;
use Livewire\Volt\Volt;

test('agent can view their assigned leads', function () {
    $role = \App\Models\Role::where('slug', 'agent')->firstOrFail();
    $agent = User::factory()->create(['role_id' => $role->id]);
    $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

    $response = $this->actingAs($agent)->get(route('agent.leads'));

    $response->assertSuccessful()
        ->assertSeeLivewire('agent.leads');
});

test('agent can view lead details', function () {
    $role = \App\Models\Role::where('slug', 'agent')->firstOrFail();
    $agent = User::factory()->create(['role_id' => $role->id]);
    $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

    $response = $this->actingAs($agent)->get(route('agent.leads.show', $lead));

    $response->assertSuccessful()
        ->assertSeeLivewire('agent.leads.show');
});

test('agent cannot view leads assigned to other agents', function () {
    $role = \App\Models\Role::where('slug', 'agent')->firstOrFail();
    $agent = User::factory()->create(['role_id' => $role->id]);
    $otherAgent = User::factory()->create(['role_id' => $role->id]);
    $lead = Lead::factory()->create(['assigned_to' => $otherAgent->id]);

    $response = $this->actingAs($agent)->get(route('agent.leads.show', $lead));

    $response->assertForbidden();
});

test('agent can update lead status after call', function () {
    $role = \App\Models\Role::where('slug', 'agent')->firstOrFail();
    $agent = User::factory()->create(['role_id' => $role->id]);
    $lead = Lead::factory()->create([
        'assigned_to' => $agent->id,
        'status' => 'pending_call',
    ]);

    Volt::actingAs($agent)
        ->test('agent.leads.show', ['lead' => $lead])
        ->set('status', 'confirmed')
        ->set('comment', 'Lead intéressé, demande un rappel')
        ->call('updateStatus');

    $lead->refresh();
    expect($lead->status)->toBe('confirmed')
        ->and($lead->call_comment)->toBe('Lead intéressé, demande un rappel')
        ->and($lead->called_at)->not->toBeNull();
});
