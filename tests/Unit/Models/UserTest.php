<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\ApiToken;
use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadReminder;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('User Model - Role Checks', function () {
    test('returns true for isSuperAdmin when user has super_admin role', function () {
        // Arrange
        $role = Role::factory()->create(['slug' => 'super_admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        // Act & Assert
        expect($user->isSuperAdmin())->toBeTrue();
    });

    test('returns false for isSuperAdmin when user does not have super_admin role', function () {
        // Arrange
        $role = Role::factory()->create(['slug' => 'agent']);
        $user = User::factory()->create(['role_id' => $role->id]);

        // Act & Assert
        expect($user->isSuperAdmin())->toBeFalse();
    });

    test('returns true for isCallCenterOwner when user has call_center_owner role', function () {
        // Arrange
        $role = Role::factory()->create(['slug' => 'call_center_owner']);
        $user = User::factory()->create(['role_id' => $role->id]);

        // Act & Assert
        expect($user->isCallCenterOwner())->toBeTrue();
    });

    test('returns true for isAgent when user has agent role', function () {
        // Arrange
        $role = Role::factory()->create(['slug' => 'agent']);
        $user = User::factory()->create(['role_id' => $role->id]);

        // Act & Assert
        expect($user->isAgent())->toBeTrue();
    });

    test('returns true for isSupervisor when user has supervisor role', function () {
        // Arrange
        $role = Role::factory()->create(['slug' => 'supervisor']);
        $user = User::factory()->create(['role_id' => $role->id]);

        // Act & Assert
        expect($user->isSupervisor())->toBeTrue();
    });

    test('returns false for role checks when user has no role', function () {
        // Arrange
        $user = User::factory()->create(['role_id' => null]);

        // Act & Assert
        expect($user->isSuperAdmin())->toBeFalse()
            ->and($user->isCallCenterOwner())->toBeFalse()
            ->and($user->isAgent())->toBeFalse()
            ->and($user->isSupervisor())->toBeFalse();
    });
});

describe('User Model - Initials', function () {
    test('generates correct initials for full name', function () {
        // Arrange
        $user = User::factory()->create(['name' => 'John Doe']);

        // Act
        $initials = $user->initials();

        // Assert
        expect($initials)->toBe('JD');
    });

    test('generates correct initials for single name', function () {
        // Arrange
        $user = User::factory()->create(['name' => 'John']);

        // Act
        $initials = $user->initials();

        // Assert
        expect($initials)->toBe('J');
    });

    test('generates correct initials for three word name', function () {
        // Arrange
        $user = User::factory()->create(['name' => 'John Michael Doe']);

        // Act
        $initials = $user->initials();

        // Assert
        expect($initials)->toBe('JM');
    });

    test('handles empty name gracefully', function () {
        // Arrange
        $user = User::factory()->create(['name' => '']);

        // Act
        $initials = $user->initials();

        // Assert
        expect($initials)->toBe('');
    });
});

describe('User Model - Experience Level', function () {
    test('returns true for isBeginner when experience_level is beginner', function () {
        // Arrange
        $user = User::factory()->create(['experience_level' => 'beginner']);

        // Act & Assert
        expect($user->isBeginner())->toBeTrue();
    });

    test('returns true for isIntermediate when experience_level is intermediate', function () {
        // Arrange
        $user = User::factory()->create(['experience_level' => 'intermediate']);

        // Act & Assert
        expect($user->isIntermediate())->toBeTrue();
    });

    test('returns true for isAdvanced when experience_level is advanced', function () {
        // Arrange
        $user = User::factory()->create(['experience_level' => 'advanced']);

        // Act & Assert
        expect($user->isAdvanced())->toBeTrue();
    });

    test('returns false for experience level checks when level is null', function () {
        // Arrange
        $user = User::factory()->create(['experience_level' => null]);

        // Act & Assert
        expect($user->isBeginner())->toBeFalse()
            ->and($user->isIntermediate())->toBeFalse()
            ->and($user->isAdvanced())->toBeFalse();
    });
});

describe('User Model - Relationships', function () {
    test('belongs to role', function () {
        // Arrange
        $role = Role::factory()->create();
        $user = User::factory()->create(['role_id' => $role->id]);

        // Act
        $userRole = $user->role;

        // Assert
        expect($userRole)->toBeInstanceOf(Role::class)
            ->and($userRole->id)->toBe($role->id);
    });

    test('belongs to call center', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $user = User::factory()->create(['call_center_id' => $callCenter->id]);

        // Act
        $userCallCenter = $user->callCenter;

        // Assert
        expect($userCallCenter)->toBeInstanceOf(CallCenter::class)
            ->and($userCallCenter->id)->toBe($callCenter->id);
    });

    test('belongs to supervisor', function () {
        // Arrange
        $supervisor = User::factory()->create();
        $user = User::factory()->create(['supervisor_id' => $supervisor->id]);

        // Act
        $userSupervisor = $user->supervisor;

        // Assert
        expect($userSupervisor)->toBeInstanceOf(User::class)
            ->and($userSupervisor->id)->toBe($supervisor->id);
    });

    test('has many supervised agents', function () {
        // Arrange
        $supervisor = User::factory()->create();
        $agent1 = User::factory()->create(['supervisor_id' => $supervisor->id]);
        $agent2 = User::factory()->create(['supervisor_id' => $supervisor->id]);

        // Act
        $supervisedAgents = $supervisor->supervisedAgents;

        // Assert
        expect($supervisedAgents)->toHaveCount(2)
            ->and($supervisedAgents->pluck('id')->toArray())->toContain($agent1->id, $agent2->id);
    });

    test('has many assigned leads', function () {
        // Arrange
        $agent = User::factory()->create();
        $lead1 = Lead::factory()->create(['assigned_to' => $agent->id]);
        $lead2 = Lead::factory()->create(['assigned_to' => $agent->id]);

        // Act
        $assignedLeads = $agent->assignedLeads;

        // Assert
        expect($assignedLeads)->toHaveCount(2)
            ->and($assignedLeads->pluck('id')->toArray())->toContain($lead1->id, $lead2->id);
    });

    test('has many activity logs', function () {
        // Arrange
        $user = User::factory()->create();
        ActivityLog::factory()->count(3)->create(['user_id' => $user->id]);

        // Act
        $activityLogs = $user->activityLogs;

        // Assert
        expect($activityLogs)->toHaveCount(3);
    });

    test('has many API tokens', function () {
        // Arrange
        $user = User::factory()->create();
        ApiToken::factory()->count(2)->create(['user_id' => $user->id]);

        // Act
        $apiTokens = $user->apiTokens;

        // Assert
        expect($apiTokens)->toHaveCount(2);
    });

    test('has many lead notes', function () {
        // Arrange
        $user = User::factory()->create();
        LeadNote::factory()->count(3)->create(['user_id' => $user->id]);

        // Act
        $leadNotes = $user->leadNotes;

        // Assert
        expect($leadNotes)->toHaveCount(3);
    });

    test('has many reminders', function () {
        // Arrange
        $user = User::factory()->create();
        LeadReminder::factory()->count(2)->create(['user_id' => $user->id]);

        // Act
        $reminders = $user->reminders;

        // Assert
        expect($reminders)->toHaveCount(2);
    });
});

describe('User Model - Casts', function () {
    test('casts is_active to boolean', function () {
        // Arrange
        $user = User::factory()->create(['is_active' => 1]);

        // Act & Assert
        expect($user->is_active)->toBeBool()
            ->and($user->is_active)->toBeTrue();
    });

    test('casts is_active to false when set to 0', function () {
        // Arrange
        $user = User::factory()->create(['is_active' => 0]);

        // Act & Assert
        expect($user->is_active)->toBeBool()
            ->and($user->is_active)->toBeFalse();
    });

    test('casts experience_level to string', function () {
        // Arrange
        $user = User::factory()->create(['experience_level' => 'beginner']);

        // Act & Assert
        expect($user->experience_level)->toBeString()
            ->and($user->experience_level)->toBe('beginner');
    });

    test('casts email_verified_at to datetime', function () {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act & Assert
        expect($user->email_verified_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });

    test('casts password to hashed', function () {
        // Arrange
        $plainPassword = 'password123';
        $user = User::factory()->create(['password' => $plainPassword]);

        // Act & Assert
        expect($user->password)->not->toBe($plainPassword)
            ->and($user->password)->toBeString()
            ->and(strlen($user->password))->toBeGreaterThan(0);
    });
});
