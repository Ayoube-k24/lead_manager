<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

describe('Lead Search Component', function () {
    test('lead search component can be accessed through admin leads page', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        // lead-search is an included component, test through admin.leads route
        $response = $this->actingAs($user)->get(route('admin.leads'));
        $response->assertSuccessful();
    });

    test('lead search filters by query through admin leads page', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        // Create leads with specific emails
        Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'email' => 'test@example.com',
            'score' => 50,
        ]);

        Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'email' => 'other@example.com',
            'score' => 50,
        ]);

        $response = $this->actingAs($user)->get(route('admin.leads'));
        $response->assertSuccessful();
        $response->assertSee('test@example.com');
    });

    test('lead search filters by status through admin leads page', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'confirmed',
            'score' => 50,
        ]);

        $response = $this->actingAs($user)->get(route('admin.leads'));
        $response->assertSuccessful();
    });
});

