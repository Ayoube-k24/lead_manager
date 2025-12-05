<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;

describe('User Model', function () {
    describe('Relations', function () {
        test('belongs to role', function () {
            $role = Role::factory()->create();
            $user = User::factory()->create(['role_id' => $role->id]);

            expect($user->role)->not->toBeNull()
                ->and($user->role->id)->toBe($role->id);
        });

        test('belongs to call center', function () {
            $callCenter = CallCenter::factory()->create();
            $user = User::factory()->create(['call_center_id' => $callCenter->id]);

            expect($user->callCenter)->not->toBeNull()
                ->and($user->callCenter->id)->toBe($callCenter->id);
        });

        test('belongs to supervisor', function () {
            $supervisor = User::factory()->create();
            $user = User::factory()->create(['supervisor_id' => $supervisor->id]);

            expect($user->supervisor)->not->toBeNull()
                ->and($user->supervisor->id)->toBe($supervisor->id);
        });

        test('has many supervised agents', function () {
            $supervisor = User::factory()->create();
            User::factory()->count(3)->create(['supervisor_id' => $supervisor->id]);

            expect($supervisor->supervisedAgents->count())->toBe(3);
        });

        test('has many assigned leads', function () {
            $user = User::factory()->create();
            Lead::factory()->count(5)->create(['assigned_to' => $user->id]);

            expect($user->assignedLeads->count())->toBe(5);
        });
    });

    describe('Role Checks', function () {
        test('isSuperAdmin returns true for super admin', function () {
            $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
            $user = User::factory()->create(['role_id' => $role->id]);

            expect($user->isSuperAdmin())->toBeTrue();
        });

        test('isCallCenterOwner returns true for call center owner', function () {
            $role = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
            $user = User::factory()->create(['role_id' => $role->id]);

            expect($user->isCallCenterOwner())->toBeTrue();
        });

        test('isSupervisor returns true for supervisor', function () {
            $role = Role::firstOrCreate(['slug' => 'supervisor'], ['name' => 'Supervisor']);
            $user = User::factory()->create(['role_id' => $role->id]);

            expect($user->isSupervisor())->toBeTrue();
        });

        test('isAgent returns true for agent', function () {
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $user = User::factory()->create(['role_id' => $role->id]);

            expect($user->isAgent())->toBeTrue();
        });
    });

    describe('Initials', function () {
        test('generates initials from name', function () {
            $user = User::factory()->create(['name' => 'John Doe']);

            expect($user->initials())->toBe('JD');
        });

        test('generates initials from single name', function () {
            $user = User::factory()->create(['name' => 'John']);

            expect($user->initials())->toBe('J');
        });

        test('generates initials from multiple words', function () {
            $user = User::factory()->create(['name' => 'John Michael Doe']);

            expect($user->initials())->toBe('JM');
        });
    });

    describe('Casts', function () {
        test('casts is_active to boolean', function () {
            $user = User::factory()->create(['is_active' => 1]);

            expect($user->is_active)->toBeTrue();
        });

        test('casts password to hashed', function () {
            $user = User::factory()->create(['password' => 'plaintext']);

            expect($user->password)->not->toBe('plaintext');
        });
    });
});
