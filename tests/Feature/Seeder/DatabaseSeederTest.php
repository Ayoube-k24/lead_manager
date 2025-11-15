<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('database seeder creates all required roles', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Role::where('slug', 'super_admin')->exists())->toBeTrue()
        ->and(Role::where('slug', 'call_center_owner')->exists())->toBeTrue()
        ->and(Role::where('slug', 'agent')->exists())->toBeTrue();
});

test('database seeder creates super admin user', function () {
    $this->seed(DatabaseSeeder::class);

    $superAdmin = User::where('email', 'admin@leadmanager.com')->first();

    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->role->slug)->toBe('super_admin')
        ->and($superAdmin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $superAdmin->password))->toBeTrue();
});

test('database seeder creates call center owner with call center', function () {
    $this->seed(DatabaseSeeder::class);

    $owner = User::where('email', 'owner@leadmanager.com')->first();
    $callCenter = CallCenter::where('owner_id', $owner->id)->first();

    expect($owner)->not->toBeNull()
        ->and($owner->role->slug)->toBe('call_center_owner')
        ->and($callCenter)->not->toBeNull()
        ->and($owner->call_center_id)->toBe($callCenter->id)
        ->and($callCenter->name)->toBe('Centre d\'Appels Principal')
        ->and(Hash::check('password', $owner->password))->toBeTrue();
});

test('database seeder creates three agents', function () {
    $this->seed(DatabaseSeeder::class);

    $agents = User::whereHas('role', fn ($q) => $q->where('slug', 'agent'))->get();

    expect($agents)->toHaveCount(3)
        ->and($agents->pluck('email')->toArray())->toContain('agent1@leadmanager.com')
        ->and($agents->pluck('email')->toArray())->toContain('agent2@leadmanager.com')
        ->and($agents->pluck('email')->toArray())->toContain('agent3@leadmanager.com');
});

test('database seeder assigns agents to call center', function () {
    $this->seed(DatabaseSeeder::class);

    $callCenter = CallCenter::first();
    $agents = User::whereHas('role', fn ($q) => $q->where('slug', 'agent'))->get();

    foreach ($agents as $agent) {
        expect($agent->call_center_id)->toBe($callCenter->id)
            ->and($agent->email_verified_at)->not->toBeNull()
            ->and(Hash::check('password', $agent->password))->toBeTrue();
    }
});

test('database seeder is idempotent', function () {
    $this->seed(DatabaseSeeder::class);
    $firstRunRoles = Role::count();
    $firstRunUsers = User::count();
    $firstRunCallCenters = CallCenter::count();

    $this->seed(DatabaseSeeder::class);

    expect(Role::count())->toBe($firstRunRoles)
        ->and(User::count())->toBe($firstRunUsers)
        ->and(CallCenter::count())->toBe($firstRunCallCenters);
});
