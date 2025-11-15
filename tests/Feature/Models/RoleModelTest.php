<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

test('role can be created with required fields', function () {
    $role = Role::factory()->create([
        'name' => 'Test Role',
        'slug' => 'test_role',
        'description' => 'Test Description',
    ]);

    expect($role->name)->toBe('Test Role')
        ->and($role->slug)->toBe('test_role')
        ->and($role->description)->toBe('Test Description');
});

test('role has users relationship', function () {
    $role = Role::factory()->create();
    $user1 = User::factory()->create(['role_id' => $role->id]);
    $user2 = User::factory()->create(['role_id' => $role->id]);

    expect($role->users)->toHaveCount(2)
        ->and($role->users->pluck('id')->toArray())->toContain($user1->id)
        ->and($role->users->pluck('id')->toArray())->toContain($user2->id);
});

test('role slug must be unique', function () {
    Role::factory()->create(['slug' => 'unique_slug']);

    expect(fn () => Role::factory()->create(['slug' => 'unique_slug']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('role name must be unique', function () {
    Role::factory()->create(['name' => 'Unique Name']);

    expect(fn () => Role::factory()->create(['name' => 'Unique Name']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
