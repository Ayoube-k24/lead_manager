<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);
});

test('super admin is redirected to admin dashboard after login', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->post('/login', [
        'email' => 'admin@leadmanager.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('call center owner is redirected to owner dashboard after login', function () {
    $user = User::where('email', 'owner@leadmanager.com')->first();

    $response = $this->post('/login', [
        'email' => 'owner@leadmanager.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/owner/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('agent is redirected to agent dashboard after login', function () {
    $user = User::where('email', 'agent1@leadmanager.com')->first();

    $response = $this->post('/login', [
        'email' => 'agent1@leadmanager.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/agent/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('user without role is redirected to dashboard route', function () {
    $role = Role::factory()->create(['slug' => 'unknown_role']);
    $user = User::factory()->create([
        'email' => 'unknown@example.com',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => $role->id,
    ]);

    $response = $this->post('/login', [
        'email' => 'unknown@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('unauthenticated user accessing dashboard is redirected to login', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

test('authenticated user accessing dashboard is redirected to role-specific dashboard', function () {
    $superAdmin = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->actingAs($superAdmin)->get('/dashboard');

    $response->assertRedirect('/admin/dashboard');
});

test('login fails with invalid credentials', function () {
    $response = $this->post('/login', [
        'email' => 'admin@leadmanager.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
});

test('login fails with non-existent email', function () {
    $response = $this->post('/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
});
