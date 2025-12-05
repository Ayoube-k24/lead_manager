<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

describe('ListTestUsers Command', function () {
    test('lists all users in database', function () {
        $role = Role::factory()->create();
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->artisan('users:list')
            ->assertSuccessful()
            ->expectsOutput('Utilisateurs dans la base de données:');
    });

    test('shows warning when no users found', function () {
        $this->artisan('users:list')
            ->assertFailed()
            ->expectsOutput('Aucun utilisateur trouvé dans la base de données.');
    });

    test('displays user information in table format', function () {
        $role = Role::factory()->create(['name' => 'Test Role']);
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role_id' => $role->id,
        ]);

        $this->artisan('users:list')
            ->assertSuccessful();
    });
});
