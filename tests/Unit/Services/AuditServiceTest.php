<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('AuditService - Generic Logging', function () {
    test('logs a generic activity with authenticated user', function () {
        // Arrange
        $user = User::factory()->create();
        Auth::login($user);
        $service = new AuditService();

        // Act
        $log = $service->log('test.action');

        // Assert
        expect($log)->toBeInstanceOf(ActivityLog::class)
            ->and($log->user_id)->toBe($user->id)
            ->and($log->action)->toBe('test.action')
            ->and($log->subject_type)->toBeNull()
            ->and($log->subject_id)->toBeNull()
            ->and($log->properties)->toBeArray();
    });

    test('logs a generic activity with subject', function () {
        // Arrange
        $user = User::factory()->create();
        $form = Form::factory()->create();
        Auth::login($user);
        $service = new AuditService();

        // Act
        $log = $service->log('test.action', $form);

        // Assert
        expect($log)->toBeInstanceOf(ActivityLog::class)
            ->and($log->subject_type)->toBe(Form::class)
            ->and($log->subject_id)->toBe($form->id);
    });

    test('logs a generic activity with properties', function () {
        // Arrange
        $user = User::factory()->create();
        Auth::login($user);
        $service = new AuditService();
        $properties = ['key1' => 'value1', 'key2' => 'value2'];

        // Act
        $log = $service->log('test.action', null, $properties);

        // Assert
        expect($log->properties)->toBe($properties);
    });

    test('logs a generic activity with specific user', function () {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        Auth::login($user1);
        $service = new AuditService();

        // Act
        $log = $service->log('test.action', null, null, $user2);

        // Assert
        expect($log->user_id)->toBe($user2->id);
    });

    test('logs activity with IP address and user agent', function () {
        // Arrange
        $user = User::factory()->create();
        Auth::login($user);
        $service = new AuditService();

        // Act
        $log = $service->log('test.action');

        // Assert
        expect($log->ip_address)->not->toBeNull()
            ->and($log->user_agent)->not->toBeNull();
    });
});

describe('AuditService - Form Logging', function () {
    test('logs form creation', function () {
        // Arrange
        $user = User::factory()->create();
        $form = Form::factory()->create(['name' => 'Test Form']);
        Auth::login($user);
        $service = new AuditService();

        // Act
        $log = $service->logFormCreated($form);

        // Assert
        expect($log->action)->toBe('form.created')
            ->and($log->subject_type)->toBe(Form::class)
            ->and($log->subject_id)->toBe($form->id)
            ->and($log->properties)->toHaveKey('form_name')
            ->and($log->properties['form_name'])->toBe('Test Form');
    });

    test('logs form update', function () {
        // Arrange
        $user = User::factory()->create();
        $form = Form::factory()->create(['name' => 'Test Form']);
        Auth::login($user);
        $service = new AuditService();
        $changes = ['name' => ['old' => 'Old Name', 'new' => 'New Name']];

        // Act
        $log = $service->logFormUpdated($form, $changes);

        // Assert
        expect($log->action)->toBe('form.updated')
            ->and($log->properties)->toHaveKey('changes')
            ->and($log->properties['changes'])->toBe($changes);
    });

    test('logs form deletion', function () {
        // Arrange
        $user = User::factory()->create();
        $form = Form::factory()->create(['name' => 'Test Form']);
        Auth::login($user);
        $service = new AuditService();

        // Act
        $log = $service->logFormDeleted($form);

        // Assert
        expect($log->action)->toBe('form.deleted')
            ->and($log->subject_type)->toBe(Form::class)
            ->and($log->subject_id)->toBe($form->id);
    });
});

describe('AuditService - Lead Logging', function () {
    test('logs lead status update', function () {
        // Arrange
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['email' => 'test@example.com']);
        Auth::login($user);
        $service = new AuditService();

        // Act
        $log = $service->logLeadStatusUpdated($lead, 'pending_email', 'email_confirmed', 'Email confirmed');

        // Assert
        expect($log->action)->toBe('lead.status_updated')
            ->and($log->properties)->toHaveKey('old_status', 'new_status', 'comment')
            ->and($log->properties['old_status'])->toBe('pending_email')
            ->and($log->properties['new_status'])->toBe('email_confirmed')
            ->and($log->properties['comment'])->toBe('Email confirmed');
    });

    test('logs lead assignment', function () {
        // Arrange
        $user = User::factory()->create();
        $agent = User::factory()->create(['name' => 'Agent Name']);
        $lead = Lead::factory()->create(['email' => 'test@example.com']);
        Auth::login($user);
        $service = new AuditService();

        // Act
        $log = $service->logLeadAssigned($lead, $agent);

        // Assert
        expect($log->action)->toBe('lead.assigned')
            ->and($log->properties)->toHaveKey('agent_id', 'agent_name')
            ->and($log->properties['agent_id'])->toBe($agent->id)
            ->and($log->properties['agent_name'])->toBe('Agent Name');
    });
});

describe('AuditService - Agent Logging', function () {
    test('logs agent creation', function () {
        // Arrange
        $user = User::factory()->create();
        $agent = User::factory()->create(['name' => 'New Agent', 'email' => 'agent@example.com']);
        Auth::login($user);
        $service = new AuditService();

        // Act
        $log = $service->logAgentCreated($agent);

        // Assert
        expect($log->action)->toBe('agent.created')
            ->and($log->properties)->toHaveKey('agent_id', 'agent_name', 'agent_email')
            ->and($log->properties['agent_name'])->toBe('New Agent')
            ->and($log->properties['agent_email'])->toBe('agent@example.com');
    });

    test('logs agent update', function () {
        // Arrange
        $user = User::factory()->create();
        $agent = User::factory()->create(['name' => 'Agent Name']);
        Auth::login($user);
        $service = new AuditService();
        $changes = ['name' => ['old' => 'Old Name', 'new' => 'New Name']];

        // Act
        $log = $service->logAgentUpdated($agent, $changes);

        // Assert
        expect($log->action)->toBe('agent.updated')
            ->and($log->properties)->toHaveKey('changes')
            ->and($log->properties['changes'])->toBe($changes);
    });
});

describe('AuditService - SMTP Profile Logging', function () {
    test('logs SMTP profile creation', function () {
        // Arrange
        $user = User::factory()->create();
        $profile = SmtpProfile::factory()->create(['name' => 'Test SMTP']);
        Auth::login($user);
        $service = new AuditService();

        // Act
        $log = $service->logSmtpProfileCreated($profile);

        // Assert
        expect($log->action)->toBe('smtp_profile.created')
            ->and($log->subject_type)->toBe(SmtpProfile::class)
            ->and($log->properties)->toHaveKey('profile_name')
            ->and($log->properties['profile_name'])->toBe('Test SMTP');
    });

    test('logs SMTP profile update', function () {
        // Arrange
        $user = User::factory()->create();
        $profile = SmtpProfile::factory()->create(['name' => 'Test SMTP']);
        Auth::login($user);
        $service = new AuditService();
        $changes = ['host' => ['old' => 'old.host.com', 'new' => 'new.host.com']];

        // Act
        $log = $service->logSmtpProfileUpdated($profile, $changes);

        // Assert
        expect($log->action)->toBe('smtp_profile.updated')
            ->and($log->properties)->toHaveKey('changes')
            ->and($log->properties['changes'])->toBe($changes);
    });
});

describe('AuditService - Email Template Logging', function () {
    test('logs email template creation', function () {
        // Arrange
        $user = User::factory()->create();
        $template = \App\Models\EmailTemplate::factory()->create(['name' => 'Test Template']);
        Auth::login($user);
        $service = new AuditService();

        // Act
        $log = $service->logEmailTemplateCreated($template);

        // Assert
        expect($log->action)->toBe('email_template.created')
            ->and($log->subject_type)->toBe(\App\Models\EmailTemplate::class)
            ->and($log->properties)->toHaveKey('template_name');
    });

    test('logs email template update', function () {
        // Arrange
        $user = User::factory()->create();
        $template = \App\Models\EmailTemplate::factory()->create(['name' => 'Test Template']);
        Auth::login($user);
        $service = new AuditService();
        $changes = ['subject' => ['old' => 'Old Subject', 'new' => 'New Subject']];

        // Act
        $log = $service->logEmailTemplateUpdated($template, $changes);

        // Assert
        expect($log->action)->toBe('email_template.updated')
            ->and($log->properties)->toHaveKey('changes');
    });
});

describe('AuditService - Distribution Method Logging', function () {
    test('logs distribution method change', function () {
        // Arrange
        $user = User::factory()->create();
        $callCenter = CallCenter::factory()->create(['name' => 'Test Center']);
        Auth::login($user);
        $service = new AuditService();

        // Act
        $log = $service->logDistributionMethodChanged($callCenter, 'round_robin', 'weighted');

        // Assert
        expect($log->action)->toBe('distribution_method.changed')
            ->and($log->properties)->toHaveKey('old_method', 'new_method')
            ->and($log->properties['old_method'])->toBe('round_robin')
            ->and($log->properties['new_method'])->toBe('weighted');
    });
});

describe('AuditService - Authentication Logging', function () {
    test('logs successful login', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new AuditService();

        // Act
        $log = $service->logLogin($user, true);

        // Assert
        expect($log->action)->toBe('auth.login')
            ->and($log->user_id)->toBe($user->id)
            ->and($log->properties)->toHaveKey('success')
            ->and($log->properties['success'])->toBeTrue();
    });

    test('logs failed login', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new AuditService();

        // Act
        $log = $service->logLogin($user, false, 'Invalid password');

        // Assert
        expect($log->action)->toBe('auth.login')
            ->and($log->properties)->toHaveKey('success', 'reason')
            ->and($log->properties['success'])->toBeFalse()
            ->and($log->properties['reason'])->toBe('Invalid password');
    });

    test('logs logout', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new AuditService();

        // Act
        $log = $service->logLogout($user);

        // Assert
        expect($log->action)->toBe('auth.logout')
            ->and($log->user_id)->toBe($user->id);
    });

    test('logs failed login attempt', function () {
        // Arrange
        $service = new AuditService();

        // Act
        $log = $service->logFailedLogin('test@example.com', 'Invalid credentials');

        // Assert
        expect($log->action)->toBe('auth.login_failed')
            ->and($log->user_id)->toBeNull()
            ->and($log->properties)->toHaveKey('email', 'reason')
            ->and($log->properties['email'])->toBe('test@example.com')
            ->and($log->properties['reason'])->toBe('Invalid credentials');
    });

    test('logs failed login attempt with default reason', function () {
        // Arrange
        $service = new AuditService();

        // Act
        $log = $service->logFailedLogin('test@example.com');

        // Assert
        expect($log->properties['reason'])->toBe('Invalid credentials');
    });
});

