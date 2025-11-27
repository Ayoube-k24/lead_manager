<?php

declare(strict_types=1);

use App\Models\Alert;
use App\Models\Lead;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/../../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

test('alerts:check command returns success when no alerts triggered', function () {
    // Arrange - No alerts or alerts that don't meet conditions

    // Act
    $this->artisan('alerts:check')
        ->expectsOutput('Aucune alerte déclenchée.')
        ->assertSuccessful();
});

test('alerts:check command triggers alerts when conditions are met', function () {
    // Arrange
    $user = User::factory()->create();

    // Create stale leads
    Lead::factory()->count(6)->create([
        'status' => 'pending_call',
        'updated_at' => now()->subHours(25),
    ]);

    $alert = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'lead_stale',
        'conditions' => ['hours' => 24],
        'threshold' => 5.0,
        'is_active' => true,
        'last_triggered_at' => null,
    ]);

    // Act
    $this->artisan('alerts:check')
        ->expectsOutput('1 alerte(s) déclenchée(s).')
        ->assertSuccessful();

    // Assert
    expect($alert->fresh()->last_triggered_at)->not->toBeNull();
});

test('alerts:check command does not trigger alerts in cooldown period', function () {
    // Arrange
    $user = User::factory()->create();

    Lead::factory()->count(6)->create([
        'status' => 'pending_call',
        'updated_at' => now()->subHours(25),
    ]);

    $alert = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'lead_stale',
        'conditions' => ['hours' => 24],
        'threshold' => 5.0,
        'is_active' => true,
        'last_triggered_at' => now()->subMinutes(30), // Recently triggered
    ]);

    // Act
    $this->artisan('alerts:check')
        ->expectsOutput('Aucune alerte déclenchée.')
        ->assertSuccessful();
});

test('alerts:check command processes multiple alerts', function () {
    // Arrange
    $user = User::factory()->create();

    // Create conditions for multiple alerts
    Lead::factory()->count(15)->create([
        'created_at' => now()->subMinutes(30),
    ]);

    $alert1 = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'high_volume',
        'conditions' => ['hours' => 1],
        'threshold' => 10.0,
        'is_active' => true,
    ]);

    $alert2 = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'lead_stale',
        'conditions' => ['hours' => 24],
        'threshold' => 5.0,
        'is_active' => true,
    ]);

    // Act
    $this->artisan('alerts:check')
        ->expectsOutput('2 alerte(s) déclenchée(s).')
        ->assertSuccessful();

    // Assert
    expect($alert1->fresh()->last_triggered_at)->not->toBeNull()
        ->and($alert2->fresh()->last_triggered_at)->not->toBeNull();
});

test('alerts:check command only checks active alerts', function () {
    // Arrange
    $user = User::factory()->create();

    Lead::factory()->count(6)->create([
        'status' => 'pending_call',
        'updated_at' => now()->subHours(25),
    ]);

    $activeAlert = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'lead_stale',
        'conditions' => ['hours' => 24],
        'threshold' => 5.0,
        'is_active' => true,
    ]);

    $inactiveAlert = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'lead_stale',
        'conditions' => ['hours' => 24],
        'threshold' => 5.0,
        'is_active' => false,
    ]);

    // Act
    $this->artisan('alerts:check')
        ->expectsOutput('1 alerte(s) déclenchée(s).')
        ->assertSuccessful();

    // Assert
    expect($activeAlert->fresh()->last_triggered_at)->not->toBeNull()
        ->and($inactiveAlert->fresh()->last_triggered_at)->toBeNull();
});
