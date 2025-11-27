<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadReminder;
use App\Models\User;
use App\Services\ReminderService;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->service = app(ReminderService::class);
});

test('can schedule a reminder for a lead', function () {
    $lead = Lead::factory()->create();
    $user = User::factory()->create();
    $date = now()->addDays(1);
    $type = 'call_back';
    $notes = 'Test reminder notes';

    $reminder = $this->service->scheduleReminder($lead, $user, $date, $type, $notes);

    expect($reminder)
        ->toBeInstanceOf(LeadReminder::class)
        ->and($reminder->lead_id)->toBe($lead->id)
        ->and($reminder->user_id)->toBe($user->id)
        ->and($reminder->reminder_date->format('Y-m-d H:i'))->toBe($date->format('Y-m-d H:i'))
        ->and($reminder->reminder_type)->toBe($type)
        ->and($reminder->notes)->toBe($notes)
        ->and($reminder->is_completed)->toBeFalse();
});

test('can get upcoming reminders for a user', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create();

    $reminder1 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addDays(1),
        'is_completed' => false,
    ]);

    $reminder2 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addDays(2),
        'is_completed' => false,
    ]);

    // Past reminder (should not be included)
    LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->subDays(1),
        'is_completed' => false,
    ]);

    // Completed reminder (should not be included by default)
    LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addDays(3),
        'is_completed' => true,
    ]);

    $upcoming = $this->service->getUpcomingReminders($user, 7);

    expect($upcoming)
        ->toHaveCount(2)
        ->and($upcoming->pluck('id')->toArray())->toContain($reminder1->id, $reminder2->id);
});

test('can get upcoming reminders for all users', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $lead = Lead::factory()->create();

    LeadReminder::factory()->create([
        'user_id' => $user1->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addDays(1),
    ]);

    LeadReminder::factory()->create([
        'user_id' => $user2->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addDays(2),
    ]);

    $upcoming = $this->service->getUpcomingReminders(null, 7);

    expect($upcoming)->toHaveCount(2);
});

test('can complete a reminder', function () {
    $reminder = LeadReminder::factory()->create([
        'is_completed' => false,
        'completed_at' => null,
    ]);

    $this->service->completeReminder($reminder);

    $reminder->refresh();

    expect($reminder->is_completed)->toBeTrue()
        ->and($reminder->completed_at)->not->toBeNull();
});

test('can cancel a reminder', function () {
    $reminder = LeadReminder::factory()->create();

    $this->service->cancelReminder($reminder);

    expect(LeadReminder::find($reminder->id))->toBeNull();
});

test('can get reminders for a specific date', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create();
    $targetDate = now()->addDays(1)->startOfDay();

    $reminder1 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => $targetDate->copy()->setTime(10, 0),
    ]);

    $reminder2 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => $targetDate->copy()->setTime(14, 0),
    ]);

    // Different date
    LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => $targetDate->copy()->addDay(),
    ]);

    $reminders = $this->service->getRemindersForDate($targetDate, $user);

    expect($reminders)
        ->toHaveCount(2)
        ->and($reminders->pluck('id')->toArray())->toContain($reminder1->id, $reminder2->id);
});

test('can get overdue reminders', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create();

    $overdue1 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->subHours(2),
        'is_completed' => false,
    ]);

    $overdue2 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->subDays(1),
        'is_completed' => false,
    ]);

    // Future reminder (not overdue)
    LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addHours(1),
        'is_completed' => false,
    ]);

    $overdue = $this->service->getOverdueReminders($user);

    expect($overdue)
        ->toHaveCount(2)
        ->and($overdue->pluck('id')->toArray())->toContain($overdue1->id, $overdue2->id);
});

test('upcoming reminders are ordered by date', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create();

    $reminder3 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addDays(3),
    ]);

    $reminder1 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addDays(1),
    ]);

    $reminder2 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addDays(2),
    ]);

    $upcoming = $this->service->getUpcomingReminders($user, 7);

    expect($upcoming->pluck('id')->toArray())
        ->toBe([$reminder1->id, $reminder2->id, $reminder3->id]);
});
