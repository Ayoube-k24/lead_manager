<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    require_once __DIR__.'/EnsureMigrationsRun.php';
    ensureMigrationsRun();
    $this->seed([RoleSeeder::class, UserSeeder::class]);
});

test('home route is accessible', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
});

test('login route is accessible', function () {
    $response = $this->get('/login');

    $response->assertSuccessful();
});

test('register route is accessible', function () {
    $response = $this->get('/register');

    $response->assertSuccessful();
});

test('dashboard route redirects authenticated users to role-specific dashboard', function () {
    $superAdmin = User::where('email', 'admin@leadmanager.com')->first();
    $owner = User::where('email', 'owner@leadmanager.com')->first();
    $agent = User::where('email', 'agent1@leadmanager.com')->first();

    $this->actingAs($superAdmin)->get('/dashboard')->assertRedirect('/admin/dashboard');
    $this->actingAs($owner)->get('/dashboard')->assertRedirect('/owner/dashboard');
    $this->actingAs($agent)->get('/dashboard')->assertRedirect('/agent/dashboard');
});

test('admin dashboard route is protected by middleware', function () {
    $response = $this->get('/admin/dashboard');

    $response->assertRedirect('/login');
});

test('owner dashboard route is protected by middleware', function () {
    $response = $this->get('/owner/dashboard');

    $response->assertRedirect('/login');
});

test('agent dashboard route is protected by middleware', function () {
    $response = $this->get('/agent/dashboard');

    $response->assertRedirect('/login');
});

test('settings routes are protected by authentication', function () {
    $response = $this->get('/settings/profile');

    $response->assertRedirect('/login');
});

test('authenticated user can access settings routes', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/settings/profile');

    $response->assertSuccessful();
});

test('user without role is redirected to settings when accessing dashboard', function () {
    $user = User::factory()->create(['role_id' => null]);

    $response = $this->actingAs($user)->get('/dashboard');

    // User without role should be redirected to settings profile page
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/settings/profile');
});

test('routes return 404 for non-existent paths', function () {
    $response = $this->get('/non-existent-route');

    $response->assertNotFound();
});

test('api routes return json responses', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->actingAs($user)->getJson('/admin/dashboard');

    $response->assertStatus(200);
});
