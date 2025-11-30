<?php

declare(strict_types=1);

use App\LeadStatus;

describe('LeadStatus Enum - All Statuses', function () {
    test('has exactly 16 status cases', function () {
        // Act
        $statuses = LeadStatus::cases();

        // Assert
        expect($statuses)->toHaveCount(16);
    });

    test('all statuses have unique values', function () {
        // Arrange
        $statuses = LeadStatus::cases();
        $values = array_map(fn ($status) => $status->value, $statuses);

        // Assert
        expect($values)->toHaveCount(count(array_unique($values)));
    });

    test('all statuses have labels', function () {
        // Arrange
        $statuses = LeadStatus::cases();

        // Act & Assert
        foreach ($statuses as $status) {
            $label = $status->label();
            expect($label)->toBeString()
                ->and($label)->not->toBeEmpty();
        }
    });

    test('all statuses have color classes', function () {
        // Arrange
        $statuses = LeadStatus::cases();

        // Act & Assert
        foreach ($statuses as $status) {
            $colorClass = $status->colorClass();
            expect($colorClass)->toBeString()
                ->and($colorClass)->not->toBeEmpty()
                ->and($colorClass)->toContain('bg-')
                ->and($colorClass)->toContain('text-');
        }
    });

    test('all statuses have descriptions', function () {
        // Arrange
        $statuses = LeadStatus::cases();

        // Act & Assert
        foreach ($statuses as $status) {
            $description = $status->description();
            expect($description)->toBeString()
                ->and($description)->not->toBeEmpty();
        }
    });
});

describe('LeadStatus Enum - Active Statuses', function () {
    test('activeStatuses returns correct statuses', function () {
        // Act
        $activeStatuses = LeadStatus::activeStatuses();

        // Assert
        expect($activeStatuses)->toBeArray()
            ->and($activeStatuses)->toContain(LeadStatus::PendingEmail)
            ->and($activeStatuses)->toContain(LeadStatus::EmailConfirmed)
            ->and($activeStatuses)->toContain(LeadStatus::PendingCall)
            ->and($activeStatuses)->toContain(LeadStatus::CallbackPending)
            ->and($activeStatuses)->toContain(LeadStatus::FollowUp)
            ->and($activeStatuses)->toContain(LeadStatus::AppointmentScheduled)
            ->and($activeStatuses)->toContain(LeadStatus::QuoteSent)
            ->and($activeStatuses)->toHaveCount(7);
    });

    test('isActive returns true for active statuses', function () {
        // Arrange
        $activeStatuses = LeadStatus::activeStatuses();

        // Act & Assert
        foreach ($activeStatuses as $status) {
            expect($status->isActive())->toBeTrue();
        }
    });

    test('isActive returns false for final statuses', function () {
        // Arrange
        $finalStatuses = LeadStatus::finalStatuses();

        // Act & Assert
        foreach ($finalStatuses as $status) {
            expect($status->isActive())->toBeFalse();
        }
    });
});

describe('LeadStatus Enum - Final Statuses', function () {
    test('finalStatuses returns correct statuses', function () {
        // Act
        $finalStatuses = LeadStatus::finalStatuses();

        // Assert
        expect($finalStatuses)->toBeArray()
            ->and($finalStatuses)->toContain(LeadStatus::Confirmed)
            ->and($finalStatuses)->toContain(LeadStatus::Rejected)
            ->and($finalStatuses)->toContain(LeadStatus::Converted)
            ->and($finalStatuses)->toContain(LeadStatus::NotInterested)
            ->and($finalStatuses)->toContain(LeadStatus::WrongNumber)
            ->and($finalStatuses)->toContain(LeadStatus::DoNotCall)
            ->and($finalStatuses)->toHaveCount(6);
    });

    test('isFinal returns true for final statuses', function () {
        // Arrange
        $finalStatuses = LeadStatus::finalStatuses();

        // Act & Assert
        foreach ($finalStatuses as $status) {
            expect($status->isFinal())->toBeTrue();
        }
    });

    test('isFinal returns false for active statuses', function () {
        // Arrange
        $activeStatuses = LeadStatus::activeStatuses();

        // Act & Assert
        foreach ($activeStatuses as $status) {
            expect($status->isFinal())->toBeFalse();
        }
    });
});

describe('LeadStatus Enum - Post Call Statuses', function () {
    test('postCallStatuses returns correct statuses', function () {
        // Act
        $postCallStatuses = LeadStatus::postCallStatuses();

        // Assert
        expect($postCallStatuses)->toBeArray()
            ->and($postCallStatuses)->toContain(LeadStatus::Confirmed)
            ->and($postCallStatuses)->toContain(LeadStatus::Rejected)
            ->and($postCallStatuses)->toContain(LeadStatus::CallbackPending)
            ->and($postCallStatuses)->toContain(LeadStatus::NoAnswer)
            ->and($postCallStatuses)->toContain(LeadStatus::Busy)
            ->and($postCallStatuses)->toContain(LeadStatus::WrongNumber)
            ->and($postCallStatuses)->toContain(LeadStatus::NotInterested)
            ->and($postCallStatuses)->toContain(LeadStatus::Qualified)
            ->and($postCallStatuses)->toContain(LeadStatus::Converted)
            ->and($postCallStatuses)->toContain(LeadStatus::FollowUp)
            ->and($postCallStatuses)->toContain(LeadStatus::AppointmentScheduled)
            ->and($postCallStatuses)->toContain(LeadStatus::QuoteSent)
            ->and($postCallStatuses)->toContain(LeadStatus::DoNotCall)
            ->and($postCallStatuses)->toHaveCount(13);
    });

    test('canBeSetAfterCall returns true for post call statuses', function () {
        // Arrange
        $postCallStatuses = LeadStatus::postCallStatuses();

        // Act & Assert
        foreach ($postCallStatuses as $status) {
            expect($status->canBeSetAfterCall())->toBeTrue();
        }
    });

    test('canBeSetAfterCall returns false for non-post call statuses', function () {
        // Arrange
        $nonPostCallStatuses = [
            LeadStatus::PendingEmail,
            LeadStatus::EmailConfirmed,
            LeadStatus::PendingCall,
        ];

        // Act & Assert
        foreach ($nonPostCallStatuses as $status) {
            expect($status->canBeSetAfterCall())->toBeFalse();
        }
    });
});

describe('LeadStatus Enum - Beginner Statuses', function () {
    test('beginnerStatuses returns correct statuses', function () {
        // Act
        $beginnerStatuses = LeadStatus::beginnerStatuses();

        // Assert
        expect($beginnerStatuses)->toBeArray()
            ->and($beginnerStatuses)->toContain(LeadStatus::Qualified)
            ->and($beginnerStatuses)->toContain(LeadStatus::NotInterested)
            ->and($beginnerStatuses)->toContain(LeadStatus::CallbackPending)
            ->and($beginnerStatuses)->toContain(LeadStatus::NoAnswer)
            ->and($beginnerStatuses)->toHaveCount(4);
    });
});

describe('LeadStatus Enum - Options', function () {
    test('options returns all statuses as array', function () {
        // Act
        $options = LeadStatus::options();

        // Assert
        expect($options)->toBeArray()
            ->and($options)->toHaveCount(16)
            ->and($options)->toHaveKey('pending_email')
            ->and($options)->toHaveKey('email_confirmed')
            ->and($options)->toHaveKey('pending_call')
            ->and($options)->toHaveKey('confirmed')
            ->and($options)->toHaveKey('rejected')
            ->and($options)->toHaveKey('callback_pending')
            ->and($options)->toHaveKey('no_answer')
            ->and($options)->toHaveKey('busy')
            ->and($options)->toHaveKey('wrong_number')
            ->and($options)->toHaveKey('not_interested')
            ->and($options)->toHaveKey('qualified')
            ->and($options)->toHaveKey('converted')
            ->and($options)->toHaveKey('follow_up')
            ->and($options)->toHaveKey('appointment_scheduled')
            ->and($options)->toHaveKey('quote_sent')
            ->and($options)->toHaveKey('do_not_call');
    });

    test('options values are labels', function () {
        // Act
        $options = LeadStatus::options();

        // Assert
        foreach (LeadStatus::cases() as $status) {
            expect($options[$status->value])->toBe($status->label());
        }
    });
});

describe('LeadStatus Enum - Individual Status Properties', function () {
    test('PendingEmail has correct properties', function () {
        // Arrange
        $status = LeadStatus::PendingEmail;

        // Act & Assert
        expect($status->value)->toBe('pending_email')
            ->and($status->isActive())->toBeTrue()
            ->and($status->isFinal())->toBeFalse()
            ->and($status->canBeSetAfterCall())->toBeFalse();
    });

    test('EmailConfirmed has correct properties', function () {
        // Arrange
        $status = LeadStatus::EmailConfirmed;

        // Act & Assert
        expect($status->value)->toBe('email_confirmed')
            ->and($status->isActive())->toBeTrue()
            ->and($status->isFinal())->toBeFalse()
            ->and($status->canBeSetAfterCall())->toBeFalse();
    });

    test('PendingCall has correct properties', function () {
        // Arrange
        $status = LeadStatus::PendingCall;

        // Act & Assert
        expect($status->value)->toBe('pending_call')
            ->and($status->isActive())->toBeTrue()
            ->and($status->isFinal())->toBeFalse()
            ->and($status->canBeSetAfterCall())->toBeFalse();
    });

    test('Confirmed has correct properties', function () {
        // Arrange
        $status = LeadStatus::Confirmed;

        // Act & Assert
        expect($status->value)->toBe('confirmed')
            ->and($status->isActive())->toBeFalse()
            ->and($status->isFinal())->toBeTrue()
            ->and($status->canBeSetAfterCall())->toBeTrue();
    });

    test('Rejected has correct properties', function () {
        // Arrange
        $status = LeadStatus::Rejected;

        // Act & Assert
        expect($status->value)->toBe('rejected')
            ->and($status->isActive())->toBeFalse()
            ->and($status->isFinal())->toBeTrue()
            ->and($status->canBeSetAfterCall())->toBeTrue();
    });

    test('Converted has correct properties', function () {
        // Arrange
        $status = LeadStatus::Converted;

        // Act & Assert
        expect($status->value)->toBe('converted')
            ->and($status->isActive())->toBeFalse()
            ->and($status->isFinal())->toBeTrue()
            ->and($status->canBeSetAfterCall())->toBeTrue();
    });

    test('Qualified has correct properties', function () {
        // Arrange
        $status = LeadStatus::Qualified;

        // Act & Assert
        expect($status->value)->toBe('qualified')
            ->and($status->isActive())->toBeFalse()
            ->and($status->isFinal())->toBeFalse()
            ->and($status->canBeSetAfterCall())->toBeTrue();
    });
});

describe('LeadStatus Enum - TryFrom', function () {
    test('tryFrom returns correct enum for valid value', function () {
        // Act
        $status = LeadStatus::tryFrom('pending_email');

        // Assert
        expect($status)->toBe(LeadStatus::PendingEmail);
    });

    test('tryFrom returns null for invalid value', function () {
        // Act
        $status = LeadStatus::tryFrom('invalid_status');

        // Assert
        expect($status)->toBeNull();
    });

    test('tryFrom works for all status values', function () {
        // Arrange
        $statuses = LeadStatus::cases();

        // Act & Assert
        foreach ($statuses as $expectedStatus) {
            $status = LeadStatus::tryFrom($expectedStatus->value);
            expect($status)->toBe($expectedStatus);
        }
    });
});

