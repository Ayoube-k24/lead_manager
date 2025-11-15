<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);
});

test('super admin can access admin dashboard', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/admin/dashboard');

    $response->assertSuccessful();
});

test('call center owner can access owner dashboard', function () {
    $user = User::where('email', 'owner@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/owner/dashboard');

    $response->assertSuccessful();
});

test('agent can access agent dashboard', function () {
    $user = User::where('email', 'agent1@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/agent/dashboard');

    $response->assertSuccessful();
});

test('super admin cannot access owner dashboard', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/owner/dashboard');

    $response->assertForbidden();
});

test('super admin cannot access agent dashboard', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/agent/dashboard');

    $response->assertForbidden();
});

test('call center owner cannot access admin dashboard', function () {
    $user = User::where('email', 'owner@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/admin/dashboard');

    $response->assertForbidden();
});

test('call center owner cannot access agent dashboard', function () {
    $user = User::where('email', 'owner@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/agent/dashboard');

    $response->assertForbidden();
});

test('agent cannot access admin dashboard', function () {
    $user = User::where('email', 'agent1@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/admin/dashboard');

    $response->assertForbidden();
});

test('agent cannot access owner dashboard', function () {
    $user = User::where('email', 'agent1@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/owner/dashboard');

    $response->assertForbidden();
});

test('unauthenticated user is redirected to login when accessing protected route', function () {
    $response = $this->get('/admin/dashboard');

    $response->assertRedirect('/login');
});

test('user without role cannot access any dashboard', function () {
    $user = User::factory()->create(['role_id' => null]);

    $response = $this->actingAs($user)->get('/admin/dashboard');

    $response->assertForbidden();
});

test('middleware returns json response for api requests', function () {
    $user = User::where('email', 'agent1@leadmanager.com')->first();

    $response = $this->actingAs($user)
        ->getJson('/admin/dashboard');

    $response->assertStatus(403)
        ->assertJson(['message' => 'Unauthorized action.']);
});

test('middleware returns json response for unauthenticated api requests', function () {
    $response = $this->getJson('/admin/dashboard');

    $response->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});
