<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    require_once __DIR__.'/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->ownerRole = Role::factory()->create([
        'name' => 'Owner',
        'slug' => 'call_center_owner',
    ]);

    $this->agentRole = Role::factory()->create([
        'name' => 'Agent',
        'slug' => 'agent',
    ]);
});

test('owner can deactivate and reactivate an agent without deleting', function () {
    $callCenter = CallCenter::factory()->create();

    $owner = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->ownerRole->id,
        'call_center_id' => $callCenter->id,
    ]);

    $agent = User::factory()->withoutTwoFactor()->create([
        'role_id' => $this->agentRole->id,
        'call_center_id' => $callCenter->id,
        'is_active' => true,
    ]);

    Livewire::actingAs($owner)
        ->test('owner.agents')
        ->call('toggleStatus', $agent->id)
        ->assertDispatched('agent-deactivated');

    expect($agent->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($owner)
        ->test('owner.agents')
        ->call('toggleStatus', $agent->id)
        ->assertDispatched('agent-activated');

    expect($agent->fresh()->is_active)->toBeTrue();
});

