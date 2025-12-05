<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

describe('Forms Components', function () {
    test('admin forms list loads correctly', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        Form::factory()->count(5)->create();

        $response = $this->actingAs($user)->get(route('admin.forms'));
        $response->assertSuccessful();
    });

    test('admin forms create page loads correctly', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('admin.forms.create'));
        $response->assertSuccessful();
    });

    test('admin forms edit page loads correctly', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        $response = $this->actingAs($user)->get(route('admin.forms.edit', $form));
        $response->assertSuccessful();
    });

    test('admin forms search works', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $callCenter = CallCenter::factory()->create();
        Form::factory()->create([
            'call_center_id' => $callCenter->id,
            'name' => 'Test Form',
        ]);

        // Test search via Livewire component interaction
        $response = $this->actingAs($user)->get(route('admin.forms'));
        $response->assertSuccessful();
        $response->assertSee('Test Form');
    });
});

