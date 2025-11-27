<?php

declare(strict_types=1);

use App\Models\Alert;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Alert Model - Trigger Management', function () {
    test('returns true for canBeTriggered when never triggered', function () {
        // Arrange
        $alert = Alert::factory()->create(['last_triggered_at' => null]);

        // Act & Assert
        expect($alert->canBeTriggered())->toBeTrue();
    });

    test('returns true for canBeTriggered when cooldown period has passed', function () {
        // Arrange
        $alert = Alert::factory()->create([
            'last_triggered_at' => now()->subHours(2),
        ]);

        // Act & Assert
        expect($alert->canBeTriggered(60))->toBeTrue(); // 60 minutes cooldown
    });

    test('returns false for canBeTriggered when cooldown period has not passed', function () {
        // Arrange
        $alert = Alert::factory()->create([
            'last_triggered_at' => now()->subMinutes(30),
        ]);

        // Act & Assert
        expect($alert->canBeTriggered(60))->toBeFalse(); // 60 minutes cooldown
    });

    test('marks alert as triggered', function () {
        // Arrange
        $alert = Alert::factory()->create(['last_triggered_at' => null]);

        // Act
        $alert->markAsTriggered();

        // Assert
        expect($alert->fresh()->last_triggered_at)->not->toBeNull();
    });
});

describe('Alert Model - Type Labels', function () {
    test('returns correct label for lead_stale type', function () {
        // Arrange
        $alert = Alert::factory()->create(['type' => 'lead_stale']);

        // Act
        $label = $alert->getTypeLabel();

        // Assert
        expect($label)->toBe(__('Lead inactif'));
    });

    test('returns correct label for agent_performance type', function () {
        // Arrange
        $alert = Alert::factory()->create(['type' => 'agent_performance']);

        // Act
        $label = $alert->getTypeLabel();

        // Assert
        expect($label)->toBe(__('Performance agent'));
    });

    test('returns correct label for conversion_rate type', function () {
        // Arrange
        $alert = Alert::factory()->create(['type' => 'conversion_rate']);

        // Act
        $label = $alert->getTypeLabel();

        // Assert
        expect($label)->toBe(__('Taux de conversion'));
    });

    test('returns type as label for unknown type', function () {
        // Arrange
        $alert = Alert::factory()->create(['type' => 'unknown_type']);

        // Act
        $label = $alert->getTypeLabel();

        // Assert
        expect($label)->toBe('unknown_type');
    });
});

describe('Alert Model - Relationships', function () {
    test('belongs to user', function () {
        // Arrange
        $user = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $user->id]);

        // Act
        $alertUser = $alert->user;

        // Assert
        expect($alertUser)->toBeInstanceOf(User::class)
            ->and($alertUser->id)->toBe($user->id);
    });
});

describe('Alert Model - Casts', function () {
    test('casts conditions to array', function () {
        // Arrange
        $conditions = ['hours' => 24, 'agent_id' => 1];
        $alert = Alert::factory()->create(['conditions' => $conditions]);

        // Act & Assert
        expect($alert->conditions)->toBeArray()
            ->and($alert->conditions)->toBe($conditions);
    });

    test('casts threshold to decimal', function () {
        // Arrange
        $alert = Alert::factory()->create(['threshold' => 50.75]);

        // Act & Assert
        expect($alert->threshold)->toBe('50.75');
    });

    test('casts is_active to boolean', function () {
        // Arrange
        $alert = Alert::factory()->create(['is_active' => 1]);

        // Act & Assert
        expect($alert->is_active)->toBeBool()
            ->and($alert->is_active)->toBeTrue();
    });

    test('casts notification_channels to array', function () {
        // Arrange
        $channels = ['email', 'in_app', 'sms'];
        $alert = Alert::factory()->create(['notification_channels' => $channels]);

        // Act & Assert
        expect($alert->notification_channels)->toBeArray()
            ->and($alert->notification_channels)->toBe($channels);
    });

    test('casts last_triggered_at to datetime', function () {
        // Arrange
        $alert = Alert::factory()->create(['last_triggered_at' => now()]);

        // Act & Assert
        expect($alert->last_triggered_at)->toBeInstanceOf(Carbon::class);
    });

    test('casts is_system to boolean', function () {
        // Arrange
        $alert = Alert::factory()->create(['is_system' => 1]);

        // Act & Assert
        expect($alert->is_system)->toBeBool()
            ->and($alert->is_system)->toBeTrue();
    });
});
