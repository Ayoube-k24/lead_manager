<?php

declare(strict_types=1);

use App\Models\Alert;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\AlertService;

describe('AlertService', function () {
    beforeEach(function () {
        $this->service = new AlertService;
    });

    describe('createAlert', function () {
        test('creates alert successfully', function () {
            $user = User::factory()->create();

            $alert = $this->service->createAlert(
                $user,
                'lead_stale',
                ['hours' => 24],
                5.0,
                ['email', 'in_app']
            );

            expect($alert)->toBeInstanceOf(Alert::class)
                ->and($alert->user_id)->toBe($user->id)
                ->and($alert->type)->toBe('lead_stale')
                ->and($alert->is_active)->toBeTrue()
                ->and($alert->notification_channels)->toBe(['email', 'in_app']);
        });
    });

    describe('evaluateConditions', function () {
        test('evaluates lead_stale condition', function () {
            Lead::factory()->count(3)->create([
                'status' => 'pending_email',
                'updated_at' => now()->subHours(25),
            ]);

            $alert = Alert::factory()->create([
                'type' => 'lead_stale',
                'conditions' => ['hours' => 24],
                'threshold' => 2.0,
            ]);

            $result = $this->service->evaluateConditions($alert);

            expect($result)->toBeTrue();
        });

        test('evaluates agent_performance condition', function () {
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $agent = User::factory()->create(['role_id' => $role->id]);

            Lead::factory()->count(10)->create([
                'assigned_to' => $agent->id,
                'status' => 'rejected',
            ]);
            Lead::factory()->count(2)->create([
                'assigned_to' => $agent->id,
                'status' => 'converted',
            ]);

            $alert = Alert::factory()->create([
                'type' => 'agent_performance',
                'conditions' => ['agent_id' => $agent->id],
                'threshold' => 30.0,
            ]);

            $result = $this->service->evaluateConditions($alert);

            expect($result)->toBeTrue(); // 20% conversion < 30% threshold
        });

        test('evaluates conversion_rate condition', function () {
            Lead::factory()->count(10)->create(['status' => 'rejected']);
            Lead::factory()->count(2)->create(['status' => 'converted']);

            $alert = Alert::factory()->create([
                'type' => 'conversion_rate',
                'threshold' => 30.0,
            ]);

            $result = $this->service->evaluateConditions($alert);

            expect($result)->toBeTrue(); // 16.67% < 30%
        });

        test('evaluates high_volume condition', function () {
            Lead::factory()->count(15)->create([
                'created_at' => now()->subMinutes(30),
            ]);

            $alert = Alert::factory()->create([
                'type' => 'high_volume',
                'conditions' => ['hours' => 1],
                'threshold' => 10.0,
            ]);

            $result = $this->service->evaluateConditions($alert);

            expect($result)->toBeTrue();
        });

        test('evaluates low_volume condition', function () {
            Lead::factory()->count(3)->create([
                'created_at' => now()->subMinutes(30),
            ]);

            $alert = Alert::factory()->create([
                'type' => 'low_volume',
                'conditions' => ['hours' => 1],
                'threshold' => 5.0,
            ]);

            $result = $this->service->evaluateConditions($alert);

            expect($result)->toBeTrue();
        });

        test('evaluates form_performance condition', function () {
            $form = Form::factory()->create();

            Lead::factory()->count(10)->create([
                'form_id' => $form->id,
                'status' => 'rejected',
            ]);
            Lead::factory()->count(2)->create([
                'form_id' => $form->id,
                'status' => 'converted',
            ]);

            $alert = Alert::factory()->create([
                'type' => 'form_performance',
                'conditions' => ['form_id' => $form->id],
                'threshold' => 30.0,
            ]);

            $result = $this->service->evaluateConditions($alert);

            expect($result)->toBeTrue();
        });
    });

    describe('checkAlerts', function () {
        test('checks and triggers active alerts', function () {
            $user = User::factory()->create();

            Lead::factory()->count(5)->create([
                'status' => 'pending_email',
                'updated_at' => now()->subHours(25),
            ]);

            $alert = Alert::factory()->create([
                'user_id' => $user->id,
                'is_active' => true,
                'type' => 'lead_stale',
                'conditions' => ['hours' => 24],
                'threshold' => 3.0,
            ]);

            $triggered = $this->service->checkAlerts($user);

            expect($triggered->count())->toBe(1)
                ->and($triggered->first()->id)->toBe($alert->id)
                ->and($alert->fresh()->last_triggered_at)->not->toBeNull();
        });

        test('only checks active alerts', function () {
            $user = User::factory()->create();

            $activeAlert = Alert::factory()->create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);
            $inactiveAlert = Alert::factory()->create([
                'user_id' => $user->id,
                'is_active' => false,
            ]);

            $triggered = $this->service->checkAlerts($user);

            // May or may not trigger, but should only check active ones
            expect($triggered->pluck('id'))->not->toContain($inactiveAlert->id);
        });
    });
});
