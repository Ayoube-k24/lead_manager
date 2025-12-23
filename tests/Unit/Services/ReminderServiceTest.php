<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadReminder;
use App\Models\User;
use App\Services\ReminderService;

describe('ReminderService', function () {
    beforeEach(function () {
        $this->service = new ReminderService;
    });

    describe('scheduleReminder', function () {
        test('creates a reminder successfully', function () {
            $lead = Lead::factory()->create();
            $user = User::factory()->create();
            $date = now()->addDays(2);

            $reminder = $this->service->scheduleReminder($lead, $user, $date, 'call', 'Test note');

            expect($reminder)->toBeInstanceOf(LeadReminder::class)
                ->and($reminder->lead_id)->toBe($lead->id)
                ->and($reminder->user_id)->toBe($user->id)
                ->and($reminder->reminder_date->format('Y-m-d'))->toBe($date->format('Y-m-d'))
                ->and($reminder->reminder_type)->toBe('call')
                ->and($reminder->notes)->toBe('Test note');
        });
    });

    describe('getUpcomingReminders', function () {
        test('returns upcoming reminders', function () {
            $user = User::factory()->create();
            $lead = Lead::factory()->create();

            LeadReminder::factory()->create([
                'user_id' => $user->id,
                'lead_id' => $lead->id,
                'reminder_date' => now()->addDays(3),
            ]);
            LeadReminder::factory()->create([
                'user_id' => $user->id,
                'lead_id' => $lead->id,
                'reminder_date' => now()->addDays(10),
            ]);

            $reminders = $this->service->getUpcomingReminders($user, 7);

            expect($reminders->count())->toBe(1);
        });

        test('filters by user when provided', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $lead = Lead::factory()->create();

            LeadReminder::factory()->create([
                'user_id' => $user1->id,
                'lead_id' => $lead->id,
                'reminder_date' => now()->addDays(3),
            ]);
            LeadReminder::factory()->create([
                'user_id' => $user2->id,
                'lead_id' => $lead->id,
                'reminder_date' => now()->addDays(3),
            ]);

            $reminders = $this->service->getUpcomingReminders($user1, 7);

            expect($reminders->count())->toBe(1)
                ->and($reminders->first()->user_id)->toBe($user1->id);
        });
    });

    describe('getRemindersForDate', function () {
        test('returns reminders for specific date', function () {
            $user = User::factory()->create();
            $lead = Lead::factory()->create();
            $date = now()->addDays(2)->startOfDay();

            LeadReminder::factory()->create([
                'user_id' => $user->id,
                'lead_id' => $lead->id,
                'reminder_date' => $date,
                'is_completed' => false,
            ]);
            LeadReminder::factory()->create([
                'user_id' => $user->id,
                'lead_id' => $lead->id,
                'reminder_date' => now()->addDays(3),
                'is_completed' => false,
            ]);

            $reminders = $this->service->getRemindersForDate($date, $user);

            expect($reminders->count())->toBe(1);
        });

        test('excludes completed reminders', function () {
            $user = User::factory()->create();
            $lead = Lead::factory()->create();
            $date = now()->addDays(2)->startOfDay();

            LeadReminder::factory()->create([
                'user_id' => $user->id,
                'lead_id' => $lead->id,
                'reminder_date' => $date,
                'is_completed' => true,
            ]);

            $reminders = $this->service->getRemindersForDate($date, $user);

            expect($reminders->count())->toBe(0);
        });
    });

    describe('completeReminder', function () {
        test('marks reminder as completed', function () {
            $reminder = LeadReminder::factory()->create(['is_completed' => false]);

            $completed = $this->service->completeReminder($reminder);

            expect($completed->is_completed)->toBeTrue();
        });
    });

    describe('cancelReminder', function () {
        test('deletes reminder', function () {
            $reminder = LeadReminder::factory()->create();

            $result = $this->service->cancelReminder($reminder);

            expect($result)->toBeTrue()
                ->and(LeadReminder::find($reminder->id))->toBeNull();
        });
    });

    describe('getRemindersToNotify', function () {
        test('returns reminders that need notification', function () {
            $lead = Lead::factory()->create();

            LeadReminder::factory()->create([
                'lead_id' => $lead->id,
                'reminder_date' => now()->addHours(12),
                'is_completed' => false,
                'notified_at' => null,
            ]);
            LeadReminder::factory()->create([
                'lead_id' => $lead->id,
                'reminder_date' => now()->addDays(2),
                'is_completed' => false,
            ]);

            $reminders = $this->service->getRemindersToNotify();

            expect($reminders->count())->toBe(1);
        });

        test('excludes already notified reminders', function () {
            $lead = Lead::factory()->create();

            LeadReminder::factory()->create([
                'lead_id' => $lead->id,
                'reminder_date' => now()->addHours(12),
                'is_completed' => false,
                'notified_at' => now(),
            ]);

            $reminders = $this->service->getRemindersToNotify();

            expect($reminders->count())->toBe(0);
        });
    });

    describe('getOverdueReminders', function () {
        test('returns overdue reminders', function () {
            $user = User::factory()->create();
            $lead = Lead::factory()->create();

            LeadReminder::factory()->create([
                'user_id' => $user->id,
                'lead_id' => $lead->id,
                'reminder_date' => now()->subDays(1),
                'is_completed' => false,
            ]);
            LeadReminder::factory()->create([
                'user_id' => $user->id,
                'lead_id' => $lead->id,
                'reminder_date' => now()->addDays(1),
                'is_completed' => false,
            ]);

            $reminders = $this->service->getOverdueReminders($user);

            expect($reminders->count())->toBe(1);
        });
    });
});
