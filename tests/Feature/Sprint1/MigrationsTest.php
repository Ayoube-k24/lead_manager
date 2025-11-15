<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    require_once __DIR__.'/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

test('users table has all required columns', function () {
    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'name'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'email'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'password'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'role_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'call_center_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'email_verified_at'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'remember_token'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'updated_at'))->toBeTrue();
});

test('roles table has all required columns', function () {
    expect(Schema::hasTable('roles'))->toBeTrue()
        ->and(Schema::hasColumn('roles', 'id'))->toBeTrue()
        ->and(Schema::hasColumn('roles', 'name'))->toBeTrue()
        ->and(Schema::hasColumn('roles', 'slug'))->toBeTrue()
        ->and(Schema::hasColumn('roles', 'description'))->toBeTrue()
        ->and(Schema::hasColumn('roles', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('roles', 'updated_at'))->toBeTrue();
});

test('call_centers table has all required columns', function () {
    expect(Schema::hasTable('call_centers'))->toBeTrue()
        ->and(Schema::hasColumn('call_centers', 'id'))->toBeTrue()
        ->and(Schema::hasColumn('call_centers', 'name'))->toBeTrue()
        ->and(Schema::hasColumn('call_centers', 'description'))->toBeTrue()
        ->and(Schema::hasColumn('call_centers', 'owner_id'))->toBeTrue()
        ->and(Schema::hasColumn('call_centers', 'distribution_method'))->toBeTrue()
        ->and(Schema::hasColumn('call_centers', 'is_active'))->toBeTrue()
        ->and(Schema::hasColumn('call_centers', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('call_centers', 'updated_at'))->toBeTrue();
});

test('users table has foreign key to roles', function () {
    // Test that we can create a user with a role_id foreign key
    $role = \App\Models\Role::factory()->create();
    $user = \App\Models\User::factory()->create(['role_id' => $role->id]);

    expect($user->role_id)->toBe($role->id)
        ->and($user->role)->not->toBeNull();
});

test('users table has foreign key to call_centers', function () {
    // Test that we can create a user with a call_center_id foreign key
    $callCenter = \App\Models\CallCenter::factory()->create();
    $user = \App\Models\User::factory()->create(['call_center_id' => $callCenter->id]);

    expect($user->call_center_id)->toBe($callCenter->id)
        ->and($user->callCenter)->not->toBeNull();
});

test('call_centers table has foreign key to users for owner', function () {
    // Test that we can create a call center with an owner_id foreign key
    $owner = \App\Models\User::factory()->create();
    $callCenter = \App\Models\CallCenter::factory()->create(['owner_id' => $owner->id]);

    expect($callCenter->owner_id)->toBe($owner->id)
        ->and($callCenter->owner)->not->toBeNull();
});

test('users email column is unique', function () {
    \App\Models\User::factory()->create(['email' => 'test@example.com']);

    expect(fn () => \App\Models\User::factory()->create(['email' => 'test@example.com']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('roles slug column is unique', function () {
    \App\Models\Role::factory()->create(['slug' => 'test_slug']);

    expect(fn () => \App\Models\Role::factory()->create(['slug' => 'test_slug']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('roles name column is unique', function () {
    \App\Models\Role::factory()->create(['name' => 'Test Name']);

    expect(fn () => \App\Models\Role::factory()->create(['name' => 'Test Name']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
