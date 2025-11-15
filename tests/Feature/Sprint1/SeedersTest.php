<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    require_once __DIR__.'/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

test('role seeder creates all required roles', function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    expect(Role::where('slug', 'super_admin')->exists())->toBeTrue()
        ->and(Role::where('slug', 'call_center_owner')->exists())->toBeTrue()
        ->and(Role::where('slug', 'agent')->exists())->toBeTrue();
});

test('role seeder creates roles with correct names', function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $superAdmin = Role::where('slug', 'super_admin')->first();
    $owner = Role::where('slug', 'call_center_owner')->first();
    $agent = Role::where('slug', 'agent')->first();

    expect($superAdmin->name)->toBe('Super Administrateur')
        ->and($owner->name)->toBe('Propriétaire de Centre d\'Appels')
        ->and($agent->name)->toBe('Agent de Centre d\'Appels');
});

test('role seeder does not create duplicate roles', function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    expect(Role::where('slug', 'super_admin')->count())->toBe(1)
        ->and(Role::where('slug', 'call_center_owner')->count())->toBe(1)
        ->and(Role::where('slug', 'agent')->count())->toBe(1);
});

test('user seeder creates super admin user', function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);

    $superAdmin = User::where('email', 'admin@leadmanager.com')->first();

    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->name)->toBe('Super Admin')
        ->and($superAdmin->role->slug)->toBe('super_admin')
        ->and($superAdmin->email_verified_at)->not->toBeNull()
        ->and($superAdmin->call_center_id)->toBeNull();
});

test('user seeder creates call center owner with call center', function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);

    $owner = User::where('email', 'owner@leadmanager.com')->first();
    $callCenter = CallCenter::where('owner_id', $owner->id)->first();

    expect($owner)->not->toBeNull()
        ->and($owner->name)->toBe('Propriétaire Centre d\'Appels')
        ->and($owner->role->slug)->toBe('call_center_owner')
        ->and($callCenter)->not->toBeNull()
        ->and($callCenter->name)->toBe('Centre d\'Appels Principal')
        ->and($owner->call_center_id)->toBe($callCenter->id)
        ->and($owner->email_verified_at)->not->toBeNull();
});

test('user seeder creates all three agents', function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);

    $agents = User::whereHas('role', fn ($q) => $q->where('slug', 'agent'))->get();

    expect($agents)->toHaveCount(3)
        ->and($agents->pluck('email')->toArray())->toContain('agent1@leadmanager.com')
        ->and($agents->pluck('email')->toArray())->toContain('agent2@leadmanager.com')
        ->and($agents->pluck('email')->toArray())->toContain('agent3@leadmanager.com');
});

test('user seeder assigns agents to call center', function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);

    $owner = User::where('email', 'owner@leadmanager.com')->first();
    $callCenter = $owner->callCenter;
    $agents = User::whereHas('role', fn ($q) => $q->where('slug', 'agent'))->get();

    foreach ($agents as $agent) {
        expect($agent->call_center_id)->toBe($callCenter->id)
            ->and($agent->email_verified_at)->not->toBeNull();
    }
});

test('user seeder does not create duplicate users', function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);
    $this->seed([UserSeeder::class]);

    expect(User::where('email', 'admin@leadmanager.com')->count())->toBe(1)
        ->and(User::where('email', 'owner@leadmanager.com')->count())->toBe(1)
        ->and(User::where('email', 'agent1@leadmanager.com')->count())->toBe(1);
});

test('all test users have correct password', function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);

    $users = User::whereIn('email', [
        'admin@leadmanager.com',
        'owner@leadmanager.com',
        'agent1@leadmanager.com',
        'agent2@leadmanager.com',
        'agent3@leadmanager.com',
    ])->get();

    foreach ($users as $user) {
        expect(\Illuminate\Support\Facades\Hash::check('password', $user->password))->toBeTrue();
    }
});

test('call center created by seeder has correct properties', function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);

    $owner = User::where('email', 'owner@leadmanager.com')->first();
    $callCenter = $owner->callCenter;

    expect($callCenter->name)->toBe('Centre d\'Appels Principal')
        ->and($callCenter->description)->toBe('Centre d\'appels principal pour les tests')
        ->and($callCenter->distribution_method)->toBe('round_robin')
        ->and($callCenter->is_active)->toBeTrue();
});
