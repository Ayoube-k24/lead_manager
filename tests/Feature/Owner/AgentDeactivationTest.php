<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

describe('Agent Deactivation', function () {
    test('owner can deactivate an agent', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $callCenter = CallCenter::factory()->create();

        $owner = User::factory()->withoutTwoFactor()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        expect($agent->is_active)->toBeTrue();

        Volt::test('owner.agents')
            ->actingAs($owner)
            ->call('toggleStatus', $agent->id)
            ->assertDispatched('agent-deactivated');

        $agent->refresh();
        expect($agent->is_active)->toBeFalse();
    });

    test('owner can reactivate a deactivated agent', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $callCenter = CallCenter::factory()->create();

        $owner = User::factory()->withoutTwoFactor()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => false,
        ]);

        expect($agent->is_active)->toBeFalse();

        Volt::test('owner.agents')
            ->actingAs($owner)
            ->call('toggleStatus', $agent->id)
            ->assertDispatched('agent-activated');

        $agent->refresh();
        expect($agent->is_active)->toBeTrue();
    });

    test('owner cannot deactivate agent with active leads', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $callCenter = CallCenter::factory()->create();

        $owner = User::factory()->withoutTwoFactor()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Create a lead with active status assigned to the agent
        Lead::factory()->create([
            'assigned_to' => $agent->id,
            'call_center_id' => $callCenter->id,
            'status' => 'pending_call',
        ]);

        expect($agent->is_active)->toBeTrue();

        Volt::test('owner.agents')
            ->actingAs($owner)
            ->call('toggleStatus', $agent->id)
            ->assertDispatched('agent-has-leads');

        $agent->refresh();
        expect($agent->is_active)->toBeTrue(); // Should still be active
    });

    test('owner cannot deactivate agent from different call center', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();

        $owner = User::factory()->withoutTwoFactor()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
            'is_active' => true,
        ]);

        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter2->id,
            'is_active' => true,
        ]);

        expect($agent->is_active)->toBeTrue();

        Volt::test('owner.agents')
            ->actingAs($owner)
            ->call('toggleStatus', $agent->id)
            ->assertDispatched('agent-error');

        $agent->refresh();
        expect($agent->is_active)->toBeTrue(); // Should still be active
    });

    test('deactivated agent status is persisted in database', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $callCenter = CallCenter::factory()->create();

        $owner = User::factory()->withoutTwoFactor()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        Volt::test('owner.agents')
            ->actingAs($owner)
            ->call('toggleStatus', $agent->id);

        // Check directly in database
        $agentFromDb = User::find($agent->id);
        expect($agentFromDb->is_active)->toBeFalse();
    });
});
