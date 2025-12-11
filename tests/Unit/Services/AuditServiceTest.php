<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Form;
use App\Models\Lead;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;

describe('AuditService', function () {
    beforeEach(function () {
        $this->service = new AuditService;
    });

    describe('log', function () {
        test('creates activity log entry', function () {
            $user = User::factory()->create();
            Auth::login($user);

            $lead = Lead::factory()->create();

            $log = $this->service->log('test.action', $lead, ['key' => 'value']);

            expect($log)->toBeInstanceOf(ActivityLog::class)
                ->and($log->user_id)->toBe($user->id)
                ->and($log->action)->toBe('test.action')
                ->and($log->subject_type)->toBe(Lead::class)
                ->and($log->subject_id)->toBe($lead->id)
                ->and($log->properties)->toBe(['key' => 'value']);
        });

        test('uses provided user instead of authenticated user', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            Auth::login($user1);

            $log = $this->service->log('test.action', null, [], $user2);

            expect($log->user_id)->toBe($user2->id);
        });

        test('records IP address and user agent', function () {
            $user = User::factory()->create();
            Auth::login($user);

            $log = $this->service->log('test.action');

            expect($log->ip_address)->not->toBeNull()
                ->and($log->user_agent)->not->toBeNull();
        });
    });

    describe('logFormCreated', function () {
        test('logs form creation', function () {
            $form = Form::factory()->create(['name' => 'Test Form']);

            $log = $this->service->logFormCreated($form);

            expect($log->action)->toBe('form.created')
                ->and($log->subject_id)->toBe($form->id)
                ->and($log->properties['form_name'])->toBe('Test Form');
        });
    });

    describe('logLeadStatusUpdated', function () {
        test('logs lead status update', function () {
            $lead = Lead::factory()->create(['email' => 'test@example.com']);

            $log = $this->service->logLeadStatusUpdated($lead, 'pending_email', 'confirmed', 'Test comment');

            expect($log->action)->toBe('lead.status_updated')
                ->and($log->properties['old_status'])->toBe('pending_email')
                ->and($log->properties['new_status'])->toBe('confirmed')
                ->and($log->properties['comment'])->toBe('Test comment');
        });
    });

    describe('logLeadAssigned', function () {
        test('logs lead assignment', function () {
            $lead = Lead::factory()->create();
            $agent = User::factory()->create(['name' => 'John Doe']);

            $log = $this->service->logLeadAssigned($lead, $agent);

            expect($log->action)->toBe('lead.assigned')
                ->and($log->properties['agent_id'])->toBe($agent->id)
                ->and($log->properties['agent_name'])->toBe('John Doe');
        });
    });

    describe('logLogin', function () {
        test('logs successful login', function () {
            $user = User::factory()->create();

            $log = $this->service->logLogin($user, true);

            expect($log->action)->toBe('auth.login')
                ->and($log->user_id)->toBe($user->id)
                ->and($log->properties['success'])->toBeTrue();
        });

        test('logs failed login', function () {
            $user = User::factory()->create();

            $log = $this->service->logLogin($user, false, 'Invalid password');

            expect($log->properties['success'])->toBeFalse()
                ->and($log->properties['reason'])->toBe('Invalid password');
        });
    });

    describe('logFailedLogin', function () {
        test('logs failed login attempt', function () {
            $log = $this->service->logFailedLogin('test@example.com', 'Invalid credentials');

            expect($log->action)->toBe('auth.login_failed')
                ->and($log->properties['email'])->toBe('test@example.com')
                ->and($log->properties['reason'])->toBe('Invalid credentials');
        });
    });
});

