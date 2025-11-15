<?php

use App\Models\CallCenter;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

test('user seeder creates super admin', function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);

    $superAdmin = User::where('email', 'admin@leadmanager.com')->first();

    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->role->slug)->toBe('super_admin')
        ->and($superAdmin->email_verified_at)->not->toBeNull();
});

test('user seeder creates call center owner with call center', function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);

    $owner = User::where('email', 'owner@leadmanager.com')->first();
    $callCenter = CallCenter::where('owner_id', $owner->id)->first();

    expect($owner)->not->toBeNull()
        ->and($owner->role->slug)->toBe('call_center_owner')
        ->and($callCenter)->not->toBeNull()
        ->and($owner->call_center_id)->toBe($callCenter->id);
});

test('user seeder creates agents', function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);

    $agents = User::whereHas('role', fn ($q) => $q->where('slug', 'agent'))->get();

    expect($agents)->toHaveCount(3)
        ->and($agents->pluck('email')->toArray())->toContain('agent1@leadmanager.com')
        ->and($agents->pluck('email')->toArray())->toContain('agent2@leadmanager.com')
        ->and($agents->pluck('email')->toArray())->toContain('agent3@leadmanager.com');

    foreach ($agents as $agent) {
        expect($agent->call_center_id)->not->toBeNull()
            ->and($agent->email_verified_at)->not->toBeNull();
    }
});

test('all test users have password set', function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);

    $users = User::whereIn('email', [
        'admin@leadmanager.com',
        'owner@leadmanager.com',
        'agent1@leadmanager.com',
        'agent2@leadmanager.com',
        'agent3@leadmanager.com',
    ])->get();

    foreach ($users as $user) {
        expect($user->password)->not->toBeNull()
            ->and(Hash::check('password', $user->password))->toBeTrue();
    }
});
