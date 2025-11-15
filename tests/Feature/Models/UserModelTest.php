<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;

test('user can be created with required fields', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->password)->not->toBeNull();
});

test('user has role relationship', function () {
    $role = Role::factory()->create(['slug' => 'agent']);
    $user = User::factory()->create(['role_id' => $role->id]);

    expect($user->role)->not->toBeNull()
        ->and($user->role->slug)->toBe('agent')
        ->and($user->role_id)->toBe($role->id);
});

test('user has call center relationship', function () {
    $callCenter = CallCenter::factory()->create();
    $user = User::factory()->create(['call_center_id' => $callCenter->id]);

    expect($user->callCenter)->not->toBeNull()
        ->and($user->callCenter->id)->toBe($callCenter->id)
        ->and($user->call_center_id)->toBe($callCenter->id);
});

test('user has assigned leads relationship', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['assigned_to' => $user->id]);

    expect($user->assignedLeads)->toHaveCount(1)
        ->and($user->assignedLeads->first()->id)->toBe($lead->id);
});

test('user initials method works correctly', function () {
    $user1 = User::factory()->create(['name' => 'John Doe']);
    $user2 = User::factory()->create(['name' => 'Mary Jane Watson']);
    $user3 = User::factory()->create(['name' => 'SingleName']);

    expect($user1->initials())->toBe('JD')
        ->and($user2->initials())->toBe('MJ')
        ->and($user3->initials())->toBe('S');
});

test('user isSuperAdmin method works correctly', function () {
    $superAdminRole = Role::factory()->create(['slug' => 'super_admin']);
    $agentRole = Role::factory()->create(['slug' => 'agent']);

    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
    $agent = User::factory()->create(['role_id' => $agentRole->id]);
    $userWithoutRole = User::factory()->create(['role_id' => null]);

    expect($superAdmin->isSuperAdmin())->toBeTrue()
        ->and($agent->isSuperAdmin())->toBeFalse()
        ->and($userWithoutRole->isSuperAdmin())->toBeFalse();
});

test('user isCallCenterOwner method works correctly', function () {
    $ownerRole = Role::factory()->create(['slug' => 'call_center_owner']);
    $agentRole = Role::factory()->create(['slug' => 'agent']);

    $owner = User::factory()->create(['role_id' => $ownerRole->id]);
    $agent = User::factory()->create(['role_id' => $agentRole->id]);
    $userWithoutRole = User::factory()->create(['role_id' => null]);

    expect($owner->isCallCenterOwner())->toBeTrue()
        ->and($agent->isCallCenterOwner())->toBeFalse()
        ->and($userWithoutRole->isCallCenterOwner())->toBeFalse();
});

test('user isAgent method works correctly', function () {
    $agentRole = Role::factory()->create(['slug' => 'agent']);
    $ownerRole = Role::factory()->create(['slug' => 'call_center_owner']);

    $agent = User::factory()->create(['role_id' => $agentRole->id]);
    $owner = User::factory()->create(['role_id' => $ownerRole->id]);
    $userWithoutRole = User::factory()->create(['role_id' => null]);

    expect($agent->isAgent())->toBeTrue()
        ->and($owner->isAgent())->toBeFalse()
        ->and($userWithoutRole->isAgent())->toBeFalse();
});

test('user password is hashed', function () {
    $user = User::factory()->create([
        'password' => 'plain-password',
    ]);

    expect($user->password)->not->toBe('plain-password')
        ->and(\Illuminate\Support\Facades\Hash::check('plain-password', $user->password))->toBeTrue();
});
