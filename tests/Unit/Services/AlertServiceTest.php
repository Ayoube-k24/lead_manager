<?php

declare(strict_types=1);

use App\Models\Alert;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\AlertService;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->service = app(AlertService::class);
});

test('can create an alert', function () {
    $user = User::factory()->create();
    $conditions = ['hours' => 24];
    $threshold = 5.0;

    $alert = $this->service->createAlert(
        $user,
        'lead_stale',
        $conditions,
        $threshold,
        ['email', 'in_app']
    );

    expect($alert)
        ->toBeInstanceOf(Alert::class)
        ->and($alert->user_id)->toBe($user->id)
        ->and($alert->type)->toBe('lead_stale')
        ->and($alert->conditions)->toBe($conditions)
        ->and($alert->threshold)->toBe($threshold)
        ->and($alert->notification_channels)->toBe(['email', 'in_app'])
        ->and($alert->is_active)->toBeTrue();
});

test('can check alerts and trigger when conditions are met', function () {
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
    ]);

    $triggered = $this->service->checkAlerts($user);

    expect($triggered)->toHaveCount(1)
        ->and($triggered->first()->id)->toBe($alert->id);
});

test('does not trigger alert when conditions are not met', function () {
    $user = User::factory()->create();

    // Create recent leads (not stale)
    Lead::factory()->count(3)->create([
        'updated_at' => now()->subHours(1),
    ]);

    $alert = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'lead_stale',
        'conditions' => ['hours' => 24],
        'threshold' => 5.0,
        'is_active' => true,
    ]);

    $triggered = $this->service->checkAlerts($user);

    expect($triggered)->toHaveCount(0);
});

test('evaluates lead stale condition correctly', function () {
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
    ]);

    $result = $this->service->evaluateConditions($alert);

    expect($result)->toBeTrue();
});

test('evaluates agent performance condition correctly', function () {
    $user = User::factory()->create();
    $agentRole = Role::factory()->create(['slug' => 'agent']);
    $agent = User::factory()->create(['role_id' => $agentRole->id]);

    // Create leads with low conversion rate
    Lead::factory()->count(10)->create(['assigned_to' => $agent->id, 'status' => 'pending_call']);
    Lead::factory()->count(1)->create(['assigned_to' => $agent->id, 'status' => 'converted']);

    $alert = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'agent_performance',
        'conditions' => ['agent_id' => $agent->id],
        'threshold' => 20.0, // 20% conversion rate
        'is_active' => true,
    ]);

    $result = $this->service->evaluateConditions($alert);

    // 1 converted / 11 total = ~9% < 20% threshold
    expect($result)->toBeTrue();
});

test('evaluates conversion rate condition correctly', function () {
    $user = User::factory()->create();

    // Create leads with low conversion rate
    Lead::factory()->count(10)->create(['status' => 'pending_call']);
    Lead::factory()->count(1)->create(['status' => 'converted']);

    $alert = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'conversion_rate',
        'conditions' => [],
        'threshold' => 20.0,
        'is_active' => true,
    ]);

    $result = $this->service->evaluateConditions($alert);

    // 1 converted / 11 total = ~9% < 20% threshold
    expect($result)->toBeTrue();
});

test('evaluates high volume condition correctly', function () {
    $user = User::factory()->create();

    Lead::factory()->count(15)->create([
        'created_at' => now()->subMinutes(30),
    ]);

    $alert = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'high_volume',
        'conditions' => ['hours' => 1],
        'threshold' => 10.0,
        'is_active' => true,
    ]);

    $result = $this->service->evaluateConditions($alert);

    expect($result)->toBeTrue();
});

test('evaluates low volume condition correctly', function () {
    $user = User::factory()->create();

    Lead::factory()->count(3)->create([
        'created_at' => now()->subMinutes(30),
    ]);

    $alert = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'low_volume',
        'conditions' => ['hours' => 1],
        'threshold' => 5.0,
        'is_active' => true,
    ]);

    $result = $this->service->evaluateConditions($alert);

    expect($result)->toBeTrue();
});

test('evaluates form performance condition correctly', function () {
    $user = User::factory()->create();
    $form = Form::factory()->create();

    Lead::factory()->count(10)->create(['form_id' => $form->id, 'status' => 'pending_call']);
    Lead::factory()->count(1)->create(['form_id' => $form->id, 'status' => 'converted']);

    $alert = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'form_performance',
        'conditions' => ['form_id' => $form->id],
        'threshold' => 20.0,
        'is_active' => true,
    ]);

    $result = $this->service->evaluateConditions($alert);

    // 1 converted / 11 total = ~9% < 20% threshold
    expect($result)->toBeTrue();
});

test('does not trigger alert if cooldown period has not passed', function () {
    $user = User::factory()->create();

    Lead::factory()->count(6)->create([
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

    $result = $this->service->evaluateConditions($alert);

    expect($result)->toBeFalse();
});

test('can trigger alert and send notifications', function () {
    $user = User::factory()->create();
    $alert = Alert::factory()->create([
        'user_id' => $user->id,
        'notification_channels' => ['email', 'in_app'],
    ]);

    \Log::spy();

    $this->service->triggerAlert($alert, ['message' => 'Test alert']);

    $alert->refresh();

    expect($alert->last_triggered_at)->not->toBeNull();

    \Log::shouldHaveReceived('info')
        ->with('Email notification sent for alert', \Mockery::type('array'));

    \Log::shouldHaveReceived('info')
        ->with('In-app notification sent for alert', \Mockery::type('array'));
});
