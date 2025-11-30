<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Role Model - Basic Properties', function () {
    test('can be created with required fields', function () {
        // Arrange & Act
        $role = Role::factory()->create([
            'name' => 'Test Role',
            'slug' => 'test_role',
            'description' => 'Test Description',
        ]);

        // Assert
        expect($role->name)->toBe('Test Role')
            ->and($role->slug)->toBe('test_role')
            ->and($role->description)->toBe('Test Description');
    });

    test('can be created without description', function () {
        // Arrange & Act
        $role = Role::factory()->create([
            'name' => 'Test Role',
            'slug' => 'test_role',
            'description' => null,
        ]);

        // Assert
        expect($role->description)->toBeNull();
    });
});

describe('Role Model - Uniqueness Constraints', function () {
    test('slug must be unique', function () {
        // Arrange
        Role::factory()->create(['slug' => 'unique_slug']);

        // Act & Assert
        expect(fn () => Role::factory()->create(['slug' => 'unique_slug']))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('name must be unique', function () {
        // Arrange
        Role::factory()->create(['name' => 'Unique Name']);

        // Act & Assert
        expect(fn () => Role::factory()->create(['name' => 'Unique Name']))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });
});

describe('Role Model - Relationships', function () {
    test('has many users', function () {
        // Arrange
        $role = Role::factory()->create();
        $user1 = User::factory()->create(['role_id' => $role->id]);
        $user2 = User::factory()->create(['role_id' => $role->id]);
        $user3 = User::factory()->create(['role_id' => $role->id]);

        // Act
        $users = $role->users;

        // Assert
        expect($users)->toHaveCount(3)
            ->and($users->pluck('id')->toArray())->toContain($user1->id, $user2->id, $user3->id);
    });

    test('returns empty collection when no users assigned', function () {
        // Arrange
        $role = Role::factory()->create();

        // Act
        $users = $role->users;

        // Assert
        expect($users)->toBeEmpty();
    });
});

describe('Role Model - Standard Roles', function () {
    test('can create super_admin role', function () {
        // Arrange & Act
        $role = Role::factory()->create([
            'name' => 'Super Administrateur',
            'slug' => 'super_admin',
        ]);

        // Assert
        expect($role->slug)->toBe('super_admin');
    });

    test('can create call_center_owner role', function () {
        // Arrange & Act
        $role = Role::factory()->create([
            'name' => 'Propriétaire de Centre d\'Appels',
            'slug' => 'call_center_owner',
        ]);

        // Assert
        expect($role->slug)->toBe('call_center_owner');
    });

    test('can create supervisor role', function () {
        // Arrange & Act
        $role = Role::factory()->create([
            'name' => 'Superviseur',
            'slug' => 'supervisor',
        ]);

        // Assert
        expect($role->slug)->toBe('supervisor');
    });

    test('can create agent role', function () {
        // Arrange & Act
        $role = Role::factory()->create([
            'name' => 'Agent de Centre d\'Appels',
            'slug' => 'agent',
        ]);

        // Assert
        expect($role->slug)->toBe('agent');
    });
});

