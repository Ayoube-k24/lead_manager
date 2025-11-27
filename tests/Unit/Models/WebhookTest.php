<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Support\Str;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Webhook Model - Secret Generation', function () {
    test('generates secret automatically on creation if not provided', function () {
        // Arrange & Act
        $webhook = Webhook::factory()->create(['secret' => null]);

        // Assert
        expect($webhook->secret)->not->toBeNull()
            ->and($webhook->secret)->toBeString()
            ->and(strlen($webhook->secret))->toBe(32);
    });

    test('preserves provided secret on creation', function () {
        // Arrange
        $customSecret = Str::random(32);

        // Act
        $webhook = Webhook::factory()->create(['secret' => $customSecret]);

        // Assert
        expect($webhook->secret)->toBe($customSecret);
    });

    test('generateSecret returns 32 character string', function () {
        // Act
        $secret = Webhook::generateSecret();

        // Assert
        expect($secret)->toBeString()
            ->and(strlen($secret))->toBe(32);
    });

    test('generated secrets are unique', function () {
        // Act
        $secret1 = Webhook::generateSecret();
        $secret2 = Webhook::generateSecret();
        $secret3 = Webhook::generateSecret();

        // Assert
        expect($secret1)->not->toBe($secret2)
            ->and($secret2)->not->toBe($secret3)
            ->and($secret1)->not->toBe($secret3);
    });
});

describe('Webhook Model - Event Management', function () {
    test('returns true for listensTo when event is in events array', function () {
        // Arrange
        $webhook = Webhook::factory()->create([
            'events' => ['lead.created', 'lead.updated'],
        ]);

        // Act & Assert
        expect($webhook->listensTo('lead.created'))->toBeTrue()
            ->and($webhook->listensTo('lead.updated'))->toBeTrue();
    });

    test('returns false for listensTo when event is not in events array', function () {
        // Arrange
        $webhook = Webhook::factory()->create([
            'events' => ['lead.created'],
        ]);

        // Act & Assert
        expect($webhook->listensTo('lead.deleted'))->toBeFalse();
    });

    test('returns false for listensTo when events is null', function () {
        // Arrange
        $webhook = Webhook::factory()->create(['events' => null]);

        // Act & Assert
        expect($webhook->listensTo('lead.created'))->toBeFalse();
    });
});

describe('Webhook Model - Status', function () {
    test('returns true for shouldTrigger when webhook is active', function () {
        // Arrange
        $webhook = Webhook::factory()->create(['is_active' => true]);

        // Act & Assert
        expect($webhook->shouldTrigger())->toBeTrue();
    });

    test('returns false for shouldTrigger when webhook is inactive', function () {
        // Arrange
        $webhook = Webhook::factory()->create(['is_active' => false]);

        // Act & Assert
        expect($webhook->shouldTrigger())->toBeFalse();
    });
});

describe('Webhook Model - Relationships', function () {
    test('belongs to form', function () {
        // Arrange
        $form = Form::factory()->create();
        $webhook = Webhook::factory()->create(['form_id' => $form->id]);

        // Act
        $webhookForm = $webhook->form;

        // Assert
        expect($webhookForm)->toBeInstanceOf(Form::class)
            ->and($webhookForm->id)->toBe($form->id);
    });

    test('belongs to call center', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $webhook = Webhook::factory()->create(['call_center_id' => $callCenter->id]);

        // Act
        $webhookCallCenter = $webhook->callCenter;

        // Assert
        expect($webhookCallCenter)->toBeInstanceOf(CallCenter::class)
            ->and($webhookCallCenter->id)->toBe($callCenter->id);
    });

    test('belongs to user', function () {
        // Arrange
        $user = User::factory()->create();
        $webhook = Webhook::factory()->create(['user_id' => $user->id]);

        // Act
        $webhookUser = $webhook->user;

        // Assert
        expect($webhookUser)->toBeInstanceOf(User::class)
            ->and($webhookUser->id)->toBe($user->id);
    });
});

describe('Webhook Model - Casts', function () {
    test('casts events to array', function () {
        // Arrange
        $events = ['lead.created', 'lead.updated', 'lead.deleted'];
        $webhook = Webhook::factory()->create(['events' => $events]);

        // Act & Assert
        expect($webhook->events)->toBeArray()
            ->and($webhook->events)->toBe($events);
    });

    test('casts is_active to boolean', function () {
        // Arrange
        $webhook = Webhook::factory()->create(['is_active' => 1]);

        // Act & Assert
        expect($webhook->is_active)->toBeBool()
            ->and($webhook->is_active)->toBeTrue();
    });
});
