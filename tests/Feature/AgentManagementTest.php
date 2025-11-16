<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Role;
use App\Models\User;

test('call center owner can view agents list', function () {
    $ownerRole = Role::where('slug', 'call_center_owner')->firstOrFail();
    $callCenter = CallCenter::factory()->create();
    $owner = User::factory()->create(['role_id' => $ownerRole->id, 'call_center_id' => $callCenter->id]);

    $response = $this->actingAs($owner)->get(route('owner.agents'));

    $response->assertSuccessful()
        ->assertSeeLivewire('owner.agents');
});

test('call center owner can create an agent', function () {
    $ownerRole = Role::where('slug', 'call_center_owner')->firstOrFail();
    $agentRole = Role::where('slug', 'agent')->firstOrFail();
    $callCenter = CallCenter::factory()->create();
    $owner = User::factory()->create(['role_id' => $ownerRole->id, 'call_center_id' => $callCenter->id]);

    $response = $this->actingAs($owner)->get(route('owner.agents.create'));

    $response->assertSuccessful()
        ->assertSeeLivewire('owner.agents.create');
});

test('call center owner can view agent statistics', function () {
    $ownerRole = Role::where('slug', 'call_center_owner')->firstOrFail();
    $agentRole = Role::where('slug', 'agent')->firstOrFail();
    $callCenter = CallCenter::factory()->create();
    $owner = User::factory()->create(['role_id' => $ownerRole->id, 'call_center_id' => $callCenter->id]);
    $agent = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);

    $response = $this->actingAs($owner)->get(route('owner.agents.stats', $agent));

    $response->assertSuccessful()
        ->assertSeeLivewire('owner.agents.stats');
});
