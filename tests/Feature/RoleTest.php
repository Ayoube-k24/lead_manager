<?php

use App\Models\Role;
use App\Models\User;

test('roles can be created', function () {
    $role = Role::factory()->create([
        'name' => 'Test Role',
        'slug' => 'test_role',
    ]);

    expect($role->name)->toBe('Test Role')
        ->and($role->slug)->toBe('test_role');
});

test('user can have a role', function () {
    $role = Role::factory()->create([
        'slug' => 'agent',
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    expect($user->role)->not->toBeNull()
        ->and($user->role->slug)->toBe('agent')
        ->and($user->isAgent())->toBeTrue()
        ->and($user->isSuperAdmin())->toBeFalse()
        ->and($user->isCallCenterOwner())->toBeFalse();
});

test('user can check if they are super admin', function () {
    $role = Role::factory()->create([
        'slug' => 'super_admin',
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->isAgent())->toBeFalse()
        ->and($user->isCallCenterOwner())->toBeFalse();
});

test('user can check if they are call center owner', function () {
    $role = Role::factory()->create([
        'slug' => 'call_center_owner',
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    expect($user->isCallCenterOwner())->toBeTrue()
        ->and($user->isSuperAdmin())->toBeFalse()
        ->and($user->isAgent())->toBeFalse();
});

test('user can check if they are supervisor', function () {
    $role = Role::factory()->create([
        'slug' => 'supervisor',
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    expect($user->isSupervisor())->toBeTrue()
        ->and($user->isSuperAdmin())->toBeFalse()
        ->and($user->isCallCenterOwner())->toBeFalse()
        ->and($user->isAgent())->toBeFalse();
});

test('role seeder creates default roles', function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    expect(Role::where('slug', 'super_admin')->exists())->toBeTrue()
        ->and(Role::where('slug', 'call_center_owner')->exists())->toBeTrue()
        ->and(Role::where('slug', 'supervisor')->exists())->toBeTrue()
        ->and(Role::where('slug', 'agent')->exists())->toBeTrue();
});
