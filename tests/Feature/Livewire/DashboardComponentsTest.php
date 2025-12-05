<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

describe('Dashboard Components', function () {
    test('super admin dashboard loads correctly', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.admin'));
        $response->assertSuccessful();
        $response->assertSee('Statistiques');
    });

    test('call center owner dashboard loads correctly', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $callCenter = CallCenter::factory()->create();
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Ensure callCenter relationship is loaded
        $user->load('callCenter');

        $response = $this->actingAs($user)->get(route('dashboard.owner'));
        $response->assertSuccessful();
    });

    test('agent dashboard loads correctly', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $callCenter = CallCenter::factory()->create();
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.agent'));
        $response->assertSuccessful();
    });

    test('supervisor dashboard loads correctly', function () {
        $supervisorRole = Role::firstOrCreate(['slug' => 'supervisor'], ['name' => 'Supervisor']);
        $callCenter = CallCenter::factory()->create();
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $supervisorRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.supervisor'));
        $response->assertSuccessful();
    });

    test('super admin dashboard displays statistics', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        // Create some data
        CallCenter::factory()->count(3)->create();
        Form::factory()->count(5)->create();
        Lead::factory()->count(10)->create(['score' => 50]);

        $response = $this->actingAs($user)->get(route('dashboard.admin'));
        $response->assertSuccessful();
    });
});

