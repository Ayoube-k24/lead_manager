<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

describe('EnsureUserIsActive Middleware', function () {
    test('allows active user to access protected routes', function () {
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertSuccessful();
    });

    test('redirects inactive user', function () {
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        // Should redirect or return forbidden
        expect($response->status())->toBeIn([302, 403]);
    });

    test('handles user without is_active field', function () {
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        // If is_active is null, should default to false or handle gracefully
        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        // Should either work or redirect based on default behavior
        expect($response->status())->toBeIn([200, 302, 403]);
    });
});
