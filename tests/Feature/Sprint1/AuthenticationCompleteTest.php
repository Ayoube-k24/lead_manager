<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    require_once __DIR__.'/EnsureMigrationsRun.php';
    ensureMigrationsRun();
    $this->seed([RoleSeeder::class, UserSeeder::class]);
});

test('user can login with correct credentials', function () {
    $response = $this->post('/login', [
        'email' => 'admin@leadmanager.com',
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticated();
});

test('user cannot login with incorrect password', function () {
    $response = $this->post('/login', [
        'email' => 'admin@leadmanager.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
});

test('user cannot login with non-existent email', function () {
    $response = $this->post('/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
});

test('login requires email field', function () {
    $response = $this->post('/login', [
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('login requires password field', function () {
    $response = $this->post('/login', [
        'email' => 'admin@leadmanager.com',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('login requires valid email format', function () {
    $response = $this->post('/login', [
        'email' => 'invalid-email',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('user can logout', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/');
    $this->assertGuest();
});

test('remember me functionality works', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->post('/login', [
        'email' => 'admin@leadmanager.com',
        'password' => 'password',
        'remember' => true,
    ]);

    $response->assertRedirect();
    $this->assertAuthenticated();
    expect($user->fresh()->remember_token)->not->toBeNull();
});

test('authenticated user can access protected routes', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect('/admin/dashboard');
});

test('unauthenticated user cannot access protected routes', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

test('login redirects to intended URL after authentication', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->get('/admin/dashboard');
    $response->assertRedirect('/login');

    $response = $this->post('/login', [
        'email' => 'admin@leadmanager.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('password is hashed when user is created', function () {
    $user = User::factory()->create([
        'password' => 'plain-password',
    ]);

    expect($user->password)->not->toBe('plain-password')
        ->and(Hash::check('plain-password', $user->password))->toBeTrue();
});

test('user can update password', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $this->actingAs($user);

    $response = \Livewire\Volt\Volt::test('settings.password')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});
