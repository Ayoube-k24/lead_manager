<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Form;
use App\Models\Lead;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('ActivityLog Model - Basic Properties', function () {
    test('can be created with all required fields', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $log = ActivityLog::factory()->create([
            'user_id' => $user->id,
            'action' => 'test.action',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test User Agent',
        ]);

        // Assert
        expect($log->user_id)->toBe($user->id)
            ->and($log->action)->toBe('test.action')
            ->and($log->ip_address)->toBe('127.0.0.1')
            ->and($log->user_agent)->toBe('Test User Agent');
    });

    test('can be created without user (system action)', function () {
        // Arrange & Act
        $log = ActivityLog::factory()->create([
            'user_id' => null,
            'action' => 'system.action',
        ]);

        // Assert
        expect($log->user_id)->toBeNull();
    });

    test('can be created without subject', function () {
        // Arrange & Act
        $log = ActivityLog::factory()->create([
            'subject_type' => null,
            'subject_id' => null,
        ]);

        // Assert
        expect($log->subject_type)->toBeNull()
            ->and($log->subject_id)->toBeNull();
    });
});

describe('ActivityLog Model - Casts', function () {
    test('casts properties to array', function () {
        // Arrange
        $properties = [
            'key1' => 'value1',
            'key2' => 'value2',
            'nested' => ['key' => 'value'],
        ];
        $log = ActivityLog::factory()->create(['properties' => $properties]);

        // Act & Assert
        expect($log->properties)->toBeArray()
            ->and($log->properties)->toBe($properties);
    });

    test('handles null properties gracefully', function () {
        // Arrange
        $log = ActivityLog::factory()->create(['properties' => null]);

        // Act & Assert
        expect($log->properties)->toBeNull();
    });

    test('defaults to empty array when properties not set', function () {
        // Arrange & Act
        $log = ActivityLog::factory()->create();

        // Assert
        expect($log->properties)->toBeArray();
    });
});

describe('ActivityLog Model - Relationships', function () {
    test('belongs to user', function () {
        // Arrange
        $user = User::factory()->create();
        $log = ActivityLog::factory()->create(['user_id' => $user->id]);

        // Act
        $logUser = $log->user;

        // Assert
        expect($logUser)->toBeInstanceOf(User::class)
            ->and($logUser->id)->toBe($user->id);
    });

    test('returns null when user_id is null', function () {
        // Arrange
        $log = ActivityLog::factory()->create(['user_id' => null]);

        // Act
        $logUser = $log->user;

        // Assert
        expect($logUser)->toBeNull();
    });

    test('morphs to form subject', function () {
        // Arrange
        $form = Form::factory()->create();
        $log = ActivityLog::factory()->create([
            'subject_type' => Form::class,
            'subject_id' => $form->id,
        ]);

        // Act
        $subject = $log->subject;

        // Assert
        expect($subject)->toBeInstanceOf(Form::class)
            ->and($subject->id)->toBe($form->id);
    });

    test('morphs to lead subject', function () {
        // Arrange
        $lead = Lead::factory()->create();
        $log = ActivityLog::factory()->create([
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
        ]);

        // Act
        $subject = $log->subject;

        // Assert
        expect($subject)->toBeInstanceOf(Lead::class)
            ->and($subject->id)->toBe($lead->id);
    });

    test('returns null when subject is not set', function () {
        // Arrange
        $log = ActivityLog::factory()->create([
            'subject_type' => null,
            'subject_id' => null,
        ]);

        // Act
        $subject = $log->subject;

        // Assert
        expect($subject)->toBeNull();
    });
});

describe('ActivityLog Model - Common Actions', function () {
    test('can log form creation', function () {
        // Arrange
        $user = User::factory()->create();
        $form = Form::factory()->create();

        // Act
        $log = ActivityLog::factory()->create([
            'user_id' => $user->id,
            'action' => 'form.created',
            'subject_type' => Form::class,
            'subject_id' => $form->id,
            'properties' => ['form_name' => $form->name],
        ]);

        // Assert
        expect($log->action)->toBe('form.created')
            ->and($log->subject_type)->toBe(Form::class)
            ->and($log->subject_id)->toBe($form->id);
    });

    test('can log lead status update', function () {
        // Arrange
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        // Act
        $log = ActivityLog::factory()->create([
            'user_id' => $user->id,
            'action' => 'lead.status_updated',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'properties' => [
                'old_status' => 'pending_email',
                'new_status' => 'email_confirmed',
            ],
        ]);

        // Assert
        expect($log->action)->toBe('lead.status_updated')
            ->and($log->properties['old_status'])->toBe('pending_email')
            ->and($log->properties['new_status'])->toBe('email_confirmed');
    });

    test('can log user login', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $log = ActivityLog::factory()->create([
            'user_id' => $user->id,
            'action' => 'auth.login',
            'ip_address' => '192.168.1.1',
        ]);

        // Assert
        expect($log->action)->toBe('auth.login')
            ->and($log->ip_address)->toBe('192.168.1.1');
    });
});
