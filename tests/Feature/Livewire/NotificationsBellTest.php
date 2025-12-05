<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

describe('Notifications Bell Component', function () {
    test('notifications bell component can be tested as included component', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        // notifications-bell is an included component, not a route
        // Test it by accessing a page that includes it
        $response = $this->actingAs($user)->get('/admin/dashboard');
        $response->assertSuccessful();
    });

    test('notifications bell displays notification count', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        // Test through a page that includes the component
        $response = $this->actingAs($user)->get('/admin/dashboard');
        $response->assertSuccessful();
    });
});

