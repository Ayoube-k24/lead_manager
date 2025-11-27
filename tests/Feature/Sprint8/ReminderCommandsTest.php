<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadReminder;
use App\Models\User;
use App\Services\ReminderService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    require_once __DIR__.'/../../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

test('reminders:notify command returns success when no reminders to notify', function () {
    // Arrange - No reminders

    // Act
    $result = Artisan::call('reminders:notify');

    // Assert
    expect($result)->toBe(0);
    $this->artisan('reminders:notify')
        ->expectsOutput('Aucun rappel à notifier.')
        ->assertSuccessful();
});

test('reminders:notify command sends notifications for upcoming reminders', function () {
    // Arrange
    $user = User::factory()->create();
    $lead = Lead::factory()->create();
    $reminder = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addHours(12),
        'is_completed' => false,
        'notified_at' => null,
    ]);

    // Act
    $this->artisan('reminders:notify')
        ->expectsOutput('1 rappel(s) à notifier.')
        ->assertSuccessful();

    // Assert
    expect($reminder->fresh()->notified_at)->not->toBeNull();
});

test('reminders:notify command does not notify already notified reminders', function () {
    // Arrange
    $user = User::factory()->create();
    $lead = Lead::factory()->create();
    $reminder = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addHours(12),
        'is_completed' => false,
        'notified_at' => now()->subMinutes(30), // Already notified
    ]);

    // Act
    $this->artisan('reminders:notify')
        ->expectsOutput('Aucun rappel à notifier.')
        ->assertSuccessful();
});

test('reminders:notify command handles errors gracefully', function () {
    // Arrange
    $user = User::factory()->create();
    $lead = Lead::factory()->create();
    $reminder = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead->id,
        'reminder_date' => now()->addHours(12),
        'is_completed' => false,
        'notified_at' => null,
    ]);

    // Mock service to throw exception
    $this->mock(ReminderService::class, function ($mock) {
        $mock->shouldReceive('getRemindersToNotify')
            ->andThrow(new \Exception('Service error'));
    });

    // Act & Assert
    $this->artisan('reminders:notify')
        ->assertFailed();
});

test('reminders:notify command processes multiple reminders', function () {
    // Arrange
    $user = User::factory()->create();
    $lead1 = Lead::factory()->create();
    $lead2 = Lead::factory()->create();
    $lead3 = Lead::factory()->create();

    $reminder1 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead1->id,
        'reminder_date' => now()->addHours(6),
        'is_completed' => false,
        'notified_at' => null,
    ]);

    $reminder2 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead2->id,
        'reminder_date' => now()->addHours(12),
        'is_completed' => false,
        'notified_at' => null,
    ]);

    $reminder3 = LeadReminder::factory()->create([
        'user_id' => $user->id,
        'lead_id' => $lead3->id,
        'reminder_date' => now()->addHours(18),
        'is_completed' => false,
        'notified_at' => null,
    ]);

    // Act
    $this->artisan('reminders:notify')
        ->expectsOutput('3 rappel(s) à notifier.')
        ->assertSuccessful();

    // Assert
    expect($reminder1->fresh()->notified_at)->not->toBeNull()
        ->and($reminder2->fresh()->notified_at)->not->toBeNull()
        ->and($reminder3->fresh()->notified_at)->not->toBeNull();
});
