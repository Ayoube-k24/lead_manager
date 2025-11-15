<?php

use App\Models\CallCenter;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('super admin is redirected to /admin/dashboard after login', function () {
    $role = Role::where('slug', 'super_admin')->firstOrFail();

    $user = User::factory()->create([
        'role_id' => $role->id,
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard.admin'));
    $this->assertAuthenticatedAs($user);
});

test('call center owner is redirected to /owner/dashboard after login', function () {
    $role = Role::where('slug', 'call_center_owner')->firstOrFail();

    $owner = User::factory()->create([
        'role_id' => $role->id,
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);

    $callCenter = CallCenter::firstOrCreate(['owner_id' => $owner->id], [
        'name' => 'Test Call Center',
        'description' => 'Test',
        'distribution_method' => 'round_robin',
        'is_active' => true,
    ]);

    $owner->update(['call_center_id' => $callCenter->id]);

    $response = $this->post(route('login.store'), [
        'email' => $owner->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard.owner'));
    $this->assertAuthenticatedAs($owner);
});

test('agent is redirected to /agent/dashboard after login', function () {
    $ownerRole = Role::where('slug', 'call_center_owner')->firstOrFail();
    $agentRole = Role::where('slug', 'agent')->firstOrFail();

    $owner = User::factory()->create([
        'role_id' => $ownerRole->id,
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);

    $callCenter = CallCenter::firstOrCreate(['owner_id' => $owner->id], [
        'name' => 'Test Call Center',
        'description' => 'Test',
        'distribution_method' => 'round_robin',
        'is_active' => true,
    ]);

    $agent = User::factory()->create([
        'role_id' => $agentRole->id,
        'call_center_id' => $callCenter->id,
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $agent->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard.agent'));
    $this->assertAuthenticatedAs($agent);
});

test('super admin can access /admin/dashboard', function () {
    $role = Role::where('slug', 'super_admin')->firstOrFail();

    $user = User::factory()->create([
        'role_id' => $role->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.admin'));

    $response->assertSuccessful();
});

test('call center owner cannot access /admin/dashboard', function () {
    $role = Role::where('slug', 'call_center_owner')->firstOrFail();

    $owner = User::factory()->create([
        'role_id' => $role->id,
        'email_verified_at' => now(),
    ]);

    $callCenter = CallCenter::firstOrCreate(['owner_id' => $owner->id], [
        'name' => 'Test Call Center',
        'description' => 'Test',
        'distribution_method' => 'round_robin',
        'is_active' => true,
    ]);

    $owner->update(['call_center_id' => $callCenter->id]);

    $response = $this->actingAs($owner)->get(route('dashboard.admin'));

    $response->assertForbidden();
});

test('agent cannot access /admin/dashboard', function () {
    $ownerRole = Role::where('slug', 'call_center_owner')->firstOrFail();
    $agentRole = Role::where('slug', 'agent')->firstOrFail();

    $owner = User::factory()->create([
        'role_id' => $ownerRole->id,
        'email_verified_at' => now(),
    ]);

    $callCenter = CallCenter::firstOrCreate(['owner_id' => $owner->id], [
        'name' => 'Test Call Center',
        'description' => 'Test',
        'distribution_method' => 'round_robin',
        'is_active' => true,
    ]);

    $agent = User::factory()->create([
        'role_id' => $agentRole->id,
        'call_center_id' => $callCenter->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($agent)->get(route('dashboard.admin'));

    $response->assertForbidden();
});
