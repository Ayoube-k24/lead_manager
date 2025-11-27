<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('CallCenter Model - Casts', function () {
    test('casts is_active to boolean', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['is_active' => 1]);

        // Act & Assert
        expect($callCenter->is_active)->toBeBool()
            ->and($callCenter->is_active)->toBeTrue();
    });

    test('defaults is_active to true', function () {
        // Arrange & Act
        $callCenter = CallCenter::factory()->create();

        // Assert
        expect($callCenter->is_active)->toBeTrue();
    });
});

describe('CallCenter Model - Relationships', function () {
    test('belongs to owner', function () {
        // Arrange
        $owner = User::factory()->create();
        $callCenter = CallCenter::factory()->create(['owner_id' => $owner->id]);

        // Act
        $callCenterOwner = $callCenter->owner;

        // Assert
        expect($callCenterOwner)->toBeInstanceOf(User::class)
            ->and($callCenterOwner->id)->toBe($owner->id);
    });

    test('has many users', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        User::factory()->count(3)->create(['call_center_id' => $callCenter->id]);

        // Act
        $users = $callCenter->users;

        // Assert
        expect($users)->toHaveCount(3);
    });

    test('has many leads', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        Lead::factory()->count(5)->create(['call_center_id' => $callCenter->id]);

        // Act
        $leads = $callCenter->leads;

        // Assert
        expect($leads)->toHaveCount(5);
    });

    test('has many forms', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        Form::factory()->count(2)->create(['call_center_id' => $callCenter->id]);

        // Act
        $forms = $callCenter->forms;

        // Assert
        expect($forms)->toHaveCount(2);
    });
});

describe('CallCenter Model - Distribution Method', function () {
    test('defaults distribution_method to round_robin', function () {
        // Arrange & Act
        $callCenter = CallCenter::factory()->create();

        // Assert
        expect($callCenter->distribution_method)->toBe('round_robin');
    });

    test('can set distribution_method to weighted', function () {
        // Arrange & Act
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'weighted']);

        // Assert
        expect($callCenter->distribution_method)->toBe('weighted');
    });

    test('can set distribution_method to manual', function () {
        // Arrange & Act
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'manual']);

        // Assert
        expect($callCenter->distribution_method)->toBe('manual');
    });

    test('can update distribution_method', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);

        // Act
        $callCenter->update(['distribution_method' => 'weighted']);

        // Assert
        expect($callCenter->fresh()->distribution_method)->toBe('weighted');
    });
});

describe('CallCenter Model - Basic Properties', function () {
    test('can be created with name and description', function () {
        // Arrange & Act
        $callCenter = CallCenter::factory()->create([
            'name' => 'Test Call Center',
            'description' => 'Test Description',
        ]);

        // Assert
        expect($callCenter->name)->toBe('Test Call Center')
            ->and($callCenter->description)->toBe('Test Description');
    });

    test('can have null description', function () {
        // Arrange & Act
        $callCenter = CallCenter::factory()->create(['description' => null]);

        // Assert
        expect($callCenter->description)->toBeNull();
    });
});
