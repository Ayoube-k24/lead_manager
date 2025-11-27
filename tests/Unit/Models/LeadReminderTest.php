<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadReminder;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('LeadReminder Model - Helper Methods', function () {
    test('returns true for isDueSoon when reminder is within 24 hours', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create([
            'reminder_date' => now()->addHours(12),
            'is_completed' => false,
        ]);

        // Act & Assert
        expect($reminder->isDueSoon())->toBeTrue();
    });

    test('returns false for isDueSoon when reminder is more than 24 hours away', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create([
            'reminder_date' => now()->addDays(2),
            'is_completed' => false,
        ]);

        // Act & Assert
        expect($reminder->isDueSoon())->toBeFalse();
    });

    test('returns false for isDueSoon when reminder is completed', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create([
            'reminder_date' => now()->addHours(12),
            'is_completed' => true,
        ]);

        // Act & Assert
        expect($reminder->isDueSoon())->toBeFalse();
    });

    test('returns true for isOverdue when reminder date is past and not completed', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create([
            'reminder_date' => now()->subHours(2),
            'is_completed' => false,
        ]);

        // Act & Assert
        expect($reminder->isOverdue())->toBeTrue();
    });

    test('returns false for isOverdue when reminder is completed', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create([
            'reminder_date' => now()->subHours(2),
            'is_completed' => true,
        ]);

        // Act & Assert
        expect($reminder->isOverdue())->toBeFalse();
    });

    test('marks reminder as completed', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create([
            'is_completed' => false,
            'completed_at' => null,
        ]);

        // Act
        $reminder->markAsCompleted();

        // Assert
        expect($reminder->fresh()->is_completed)->toBeTrue()
            ->and($reminder->fresh()->completed_at)->not->toBeNull();
    });

    test('returns correct type label for call_back', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create(['reminder_type' => 'call_back']);

        // Act
        $label = $reminder->getTypeLabel();

        // Assert
        expect($label)->toBe(__('Rappel'));
    });

    test('returns correct type label for follow_up', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create(['reminder_type' => 'follow_up']);

        // Act
        $label = $reminder->getTypeLabel();

        // Assert
        expect($label)->toBe(__('Suivi'));
    });

    test('returns correct type label for appointment', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create(['reminder_type' => 'appointment']);

        // Act
        $label = $reminder->getTypeLabel();

        // Assert
        expect($label)->toBe(__('Rendez-vous'));
    });
});

describe('LeadReminder Model - Scopes', function () {
    test('upcoming scope returns reminders within specified days', function () {
        // Arrange
        $upcoming1 = LeadReminder::factory()->create([
            'reminder_date' => now()->addDays(2),
            'is_completed' => false,
        ]);
        $upcoming2 = LeadReminder::factory()->create([
            'reminder_date' => now()->addDays(5),
            'is_completed' => false,
        ]);
        LeadReminder::factory()->create([
            'reminder_date' => now()->addDays(10),
            'is_completed' => false,
        ]);

        // Act
        $upcoming = LeadReminder::upcoming(7)->get();

        // Assert
        expect($upcoming)->toHaveCount(2)
            ->and($upcoming->pluck('id')->toArray())->toContain($upcoming1->id, $upcoming2->id);
    });

    test('completed scope returns only completed reminders', function () {
        // Arrange
        $completed1 = LeadReminder::factory()->create(['is_completed' => true]);
        $completed2 = LeadReminder::factory()->create(['is_completed' => true]);
        LeadReminder::factory()->create(['is_completed' => false]);

        // Act
        $completed = LeadReminder::completed()->get();

        // Assert
        expect($completed)->toHaveCount(2)
            ->and($completed->pluck('id')->toArray())->toContain($completed1->id, $completed2->id);
    });

    test('pending scope returns only pending reminders', function () {
        // Arrange
        $pending1 = LeadReminder::factory()->create([
            'reminder_date' => now()->addDays(1),
            'is_completed' => false,
        ]);
        $pending2 = LeadReminder::factory()->create([
            'reminder_date' => now()->addHours(12),
            'is_completed' => false,
        ]);
        LeadReminder::factory()->create([
            'reminder_date' => now()->subDays(1),
            'is_completed' => false,
        ]);

        // Act
        $pending = LeadReminder::pending()->get();

        // Assert
        expect($pending)->toHaveCount(2)
            ->and($pending->pluck('id')->toArray())->toContain($pending1->id, $pending2->id);
    });

    test('forDate scope filters by specific date', function () {
        // Arrange
        $targetDate = now()->addDays(1)->startOfDay();
        $reminder1 = LeadReminder::factory()->create([
            'reminder_date' => $targetDate->copy()->setTime(10, 0),
        ]);
        $reminder2 = LeadReminder::factory()->create([
            'reminder_date' => $targetDate->copy()->setTime(14, 0),
        ]);
        LeadReminder::factory()->create([
            'reminder_date' => $targetDate->copy()->addDay(),
        ]);

        // Act
        $reminders = LeadReminder::forDate($targetDate)->get();

        // Assert
        expect($reminders)->toHaveCount(2)
            ->and($reminders->pluck('id')->toArray())->toContain($reminder1->id, $reminder2->id);
    });

    test('byType scope filters by reminder type', function () {
        // Arrange
        $callBack1 = LeadReminder::factory()->create(['reminder_type' => 'call_back']);
        $callBack2 = LeadReminder::factory()->create(['reminder_type' => 'call_back']);
        LeadReminder::factory()->create(['reminder_type' => 'follow_up']);

        // Act
        $callBacks = LeadReminder::byType('call_back')->get();

        // Assert
        expect($callBacks)->toHaveCount(2)
            ->and($callBacks->pluck('id')->toArray())->toContain($callBack1->id, $callBack2->id);
    });
});

describe('LeadReminder Model - Relationships', function () {
    test('belongs to lead', function () {
        // Arrange
        $lead = Lead::factory()->create();
        $reminder = LeadReminder::factory()->create(['lead_id' => $lead->id]);

        // Act
        $reminderLead = $reminder->lead;

        // Assert
        expect($reminderLead)->toBeInstanceOf(Lead::class)
            ->and($reminderLead->id)->toBe($lead->id);
    });

    test('belongs to user', function () {
        // Arrange
        $user = User::factory()->create();
        $reminder = LeadReminder::factory()->create(['user_id' => $user->id]);

        // Act
        $reminderUser = $reminder->user;

        // Assert
        expect($reminderUser)->toBeInstanceOf(User::class)
            ->and($reminderUser->id)->toBe($user->id);
    });
});

describe('LeadReminder Model - Casts', function () {
    test('casts reminder_date to datetime', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create(['reminder_date' => now()]);

        // Act & Assert
        expect($reminder->reminder_date)->toBeInstanceOf(Carbon::class);
    });

    test('casts is_completed to boolean', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create(['is_completed' => 1]);

        // Act & Assert
        expect($reminder->is_completed)->toBeBool()
            ->and($reminder->is_completed)->toBeTrue();
    });

    test('casts completed_at to datetime', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create(['completed_at' => now()]);

        // Act & Assert
        expect($reminder->completed_at)->toBeInstanceOf(Carbon::class);
    });

    test('casts notified_at to datetime', function () {
        // Arrange
        $reminder = LeadReminder::factory()->create(['notified_at' => now()]);

        // Act & Assert
        expect($reminder->notified_at)->toBeInstanceOf(Carbon::class);
    });
});
