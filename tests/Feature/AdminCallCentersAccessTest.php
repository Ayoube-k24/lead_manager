<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    require_once __DIR__.'/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->superAdminRole = Role::factory()->create([
        'name' => 'Super Admin',
        'slug' => 'super_admin',
    ]);

    $this->ownerRole = Role::factory()->create([
        'name' => 'Owner',
        'slug' => 'call_center_owner',
    ]);

    $this->agentRole = Role::factory()->create([
        'name' => 'Agent',
        'slug' => 'agent',
    ]);

    $this->superAdmin = User::factory()->withoutTwoFactor()->create();
    $this->superAdmin->role()->associate($this->superAdminRole);
    $this->superAdmin->save();
});

test('super admin can view call centers management page', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $owner->role()->associate($this->ownerRole);
    $owner->save();

    CallCenter::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Centre Paris',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test('admin.call-centers')
        ->assertSee('Centre Paris')
        ->assertStatus(200);
});

test('super admin can update call center access', function () {
    $initialOwner = User::factory()->withoutTwoFactor()->create();
    $initialOwner->role()->associate($this->ownerRole);
    $initialOwner->save();

    $callCenter = CallCenter::factory()->create([
        'owner_id' => $initialOwner->id,
        'distribution_method' => 'round_robin',
        'is_active' => true,
        'name' => 'Centre Lyon',
    ]);
    $initialOwner->call_center_id = $callCenter->id;
    $initialOwner->save();

    $newOwner = User::factory()->withoutTwoFactor()->create();
    $newOwner->role()->associate($this->ownerRole);
    $newOwner->save();

    $agentOne = User::factory()->withoutTwoFactor()->create();
    $agentOne->role()->associate($this->agentRole);
    $agentOne->save();

    $agentTwo = User::factory()->withoutTwoFactor()->create();
    $agentTwo->role()->associate($this->agentRole);
    $agentTwo->save();

    Livewire::actingAs($this->superAdmin)
        ->test('admin.call-centers')
        ->call('openAccessModal', $callCenter->id)
        ->set('ownerId', $newOwner->id)
        ->set('agentIds', [$agentOne->id, $agentTwo->id])
        ->set('distributionMethod', 'manual')
        ->set('isActive', false)
        ->call('saveAccess')
        ->assertHasNoErrors()
        ->assertSet('showAccessModal', false);

    $callCenter->refresh();
    expect($callCenter->owner_id)->toBe($newOwner->id)
        ->and($callCenter->distribution_method)->toBe('manual')
        ->and($callCenter->is_active)->toBeFalse();

    expect($newOwner->fresh()->call_center_id)->toBe($callCenter->id);
    expect($agentOne->fresh()->call_center_id)->toBe($callCenter->id);
    expect($agentTwo->fresh()->call_center_id)->toBe($callCenter->id);
    expect($initialOwner->fresh()->call_center_id)->toBeNull();
});

test('super admin can create call center and owner account without registration page', function () {
    Livewire::actingAs($this->superAdmin)
        ->test('admin.call-centers')
        ->call('openCreateModal')
        ->set('newCenterName', 'Centre Nice')
        ->set('newCenterDescription', 'Centre axé sur les leads B2B')
        ->set('newOwnerName', 'Alice Dupuis')
        ->set('newOwnerEmail', 'alice.dupuis@example.com')
        ->set('newOwnerPassword', 'SecurePass123!')
        ->set('newDistributionMethod', 'weighted')
        ->set('newIsActive', true)
        ->call('createCallCenter')
        ->assertHasNoErrors()
        ->assertSet('showCreateModal', false);

    $owner = User::where('email', 'alice.dupuis@example.com')->first();
    $callCenter = CallCenter::where('name', 'Centre Nice')->first();

    expect($owner)->not->toBeNull()
        ->and($callCenter)->not->toBeNull()
        ->and($owner->role?->slug)->toBe('call_center_owner')
        ->and($owner->call_center_id)->toBe($callCenter->id)
        ->and($callCenter->owner_id)->toBe($owner->id)
        ->and($callCenter->distribution_method)->toBe('weighted')
        ->and($callCenter->is_active)->toBeTrue();
});

