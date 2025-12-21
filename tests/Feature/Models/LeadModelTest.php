<?php

declare(strict_types=1);

use App\Events\LeadEmailConfirmed;
use App\LeadStatus;
use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Event;

describe('Lead Model - Email Confirmation', function () {
    test('confirms email and updates status', function () {
        Event::fake();

        $lead = Lead::factory()->create([
            'status' => 'pending_email',
            'email_confirmed_at' => null,
        ]);

        $lead->confirmEmail();

        $lead->refresh();
        expect($lead->email_confirmed_at)->not->toBeNull()
            ->and($lead->status)->toBe('email_confirmed');

        Event::assertDispatched(LeadEmailConfirmed::class);
    });

    test('isEmailConfirmed returns true when email is confirmed', function () {
        $lead = Lead::factory()->create([
            'email_confirmed_at' => now(),
        ]);

        expect($lead->isEmailConfirmed())->toBeTrue();
    });

    test('isEmailConfirmed returns false when email is not confirmed', function () {
        $lead = Lead::factory()->create([
            'email_confirmed_at' => null,
        ]);

        expect($lead->isEmailConfirmed())->toBeFalse();
    });

    test('isConfirmationTokenValid returns true for valid token', function () {
        $lead = Lead::factory()->create([
            'email_confirmation_token' => 'valid-token',
            'email_confirmation_token_expires_at' => now()->addHours(24),
        ]);

        expect($lead->isConfirmationTokenValid())->toBeTrue();
    });

    test('isConfirmationTokenValid returns false for expired token', function () {
        $lead = Lead::factory()->create([
            'email_confirmation_token' => 'expired-token',
            'email_confirmation_token_expires_at' => now()->subHours(1),
        ]);

        expect($lead->isConfirmationTokenValid())->toBeFalse();
    });

    test('isConfirmationTokenValid returns false for null token', function () {
        $lead = Lead::factory()->create([
            'email_confirmation_token' => null,
        ]);

        expect($lead->isConfirmationTokenValid())->toBeFalse();
    });
});

describe('Lead Model - Status Management', function () {
    test('getStatusEnum returns correct enum', function () {
        $lead = Lead::factory()->create(['status' => 'email_confirmed']);

        expect($lead->getStatusEnum())->toBe(LeadStatus::EmailConfirmed);
    });

    test('setStatus updates status from enum', function () {
        $lead = Lead::factory()->create(['status' => 'pending_email']);

        $lead->setStatus(LeadStatus::EmailConfirmed);

        expect($lead->status)->toBe('email_confirmed');
    });

    test('setStatus updates status from string', function () {
        $lead = Lead::factory()->create(['status' => 'pending_email']);

        $lead->setStatus('email_confirmed');

        expect($lead->status)->toBe('email_confirmed');
    });

    test('markAsPendingCall updates status', function () {
        $lead = Lead::factory()->create(['status' => 'email_confirmed']);

        $lead->markAsPendingCall();

        expect($lead->status)->toBe('pending_call');
    });

    test('isActive returns correct value', function () {
        $activeLead = Lead::factory()->create(['status' => 'email_confirmed']);
        $inactiveLead = Lead::factory()->create(['status' => 'rejected']);

        expect($activeLead->isActive())->toBeTrue()
            ->and($inactiveLead->isActive())->toBeFalse();
    });

    test('isFinal returns correct value', function () {
        $finalLead = Lead::factory()->create(['status' => 'converted']);
        $nonFinalLead = Lead::factory()->create(['status' => 'pending_email']);

        expect($finalLead->isFinal())->toBeTrue()
            ->and($nonFinalLead->isFinal())->toBeFalse();
    });
});

describe('Lead Model - Relationships', function () {
    test('belongs to form', function () {
        $form = Form::factory()->create();
        $lead = Lead::factory()->create(['form_id' => $form->id]);

        expect($lead->form)->not->toBeNull()
            ->and($lead->form->id)->toBe($form->id);
    });

    test('belongs to assigned agent', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create(['role_id' => $agentRole->id]);
        $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

        expect($lead->assignedAgent)->not->toBeNull()
            ->and($lead->assignedAgent->id)->toBe($agent->id);
    });

    test('belongs to call center', function () {
        $callCenter = CallCenter::factory()->create();
        $lead = Lead::factory()->create(['call_center_id' => $callCenter->id]);

        expect($lead->callCenter)->not->toBeNull()
            ->and($lead->callCenter->id)->toBe($callCenter->id);
    });
});

describe('Lead Model - Data Management', function () {
    test('data is cast to array', function () {
        $lead = Lead::factory()->create([
            'data' => ['name' => 'John', 'email' => 'john@example.com'],
        ]);

        expect($lead->data)->toBeArray()
            ->and($lead->data['name'])->toBe('John');
    });

    test('can update data', function () {
        $lead = Lead::factory()->create([
            'data' => ['name' => 'John'],
        ]);

        $lead->data = ['name' => 'Jane', 'email' => 'jane@example.com'];
        $lead->save();

        $lead->refresh();
        expect($lead->data['name'])->toBe('Jane')
            ->and($lead->data['email'])->toBe('jane@example.com');
    });
});






