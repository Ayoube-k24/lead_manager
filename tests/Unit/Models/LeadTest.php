<?php

declare(strict_types=1);

use App\LeadStatus;
use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadReminder;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Lead Model - Email Confirmation', function () {
    test('returns true for isEmailConfirmed when email_confirmed_at is set', function () {
        // Arrange
        $lead = Lead::factory()->create(['email_confirmed_at' => now()]);

        // Act & Assert
        expect($lead->isEmailConfirmed())->toBeTrue();
    });

    test('returns false for isEmailConfirmed when email_confirmed_at is null', function () {
        // Arrange
        $lead = Lead::factory()->create(['email_confirmed_at' => null]);

        // Act & Assert
        expect($lead->isEmailConfirmed())->toBeFalse();
    });

    test('returns true for isConfirmationTokenValid when token exists and not expired', function () {
        // Arrange
        $lead = Lead::factory()->create([
            'email_confirmation_token' => Str::random(32),
            'email_confirmation_token_expires_at' => now()->addHours(24),
        ]);

        // Act & Assert
        expect($lead->isConfirmationTokenValid())->toBeTrue();
    });

    test('returns false for isConfirmationTokenValid when token is null', function () {
        // Arrange
        $lead = Lead::factory()->create(['email_confirmation_token' => null]);

        // Act & Assert
        expect($lead->isConfirmationTokenValid())->toBeFalse();
    });

    test('returns false for isConfirmationTokenValid when token is expired', function () {
        // Arrange
        $lead = Lead::factory()->create([
            'email_confirmation_token' => Str::random(32),
            'email_confirmation_token_expires_at' => now()->subHour(),
        ]);

        // Act & Assert
        expect($lead->isConfirmationTokenValid())->toBeFalse();
    });

    test('confirms email and updates status to email_confirmed', function () {
        // Arrange
        $lead = Lead::factory()->create([
            'status' => LeadStatus::PendingEmail->value,
            'email_confirmed_at' => null,
        ]);

        // Act
        $lead->confirmEmail();

        // Assert
        expect($lead->fresh()->email_confirmed_at)->not->toBeNull()
            ->and($lead->fresh()->status)->toBe(LeadStatus::EmailConfirmed->value);
    });
});

describe('Lead Model - Status Management', function () {
    test('returns correct LeadStatus enum from getStatusEnum', function () {
        // Arrange
        $lead = Lead::factory()->create(['status' => LeadStatus::EmailConfirmed->value]);

        // Act
        $statusEnum = $lead->getStatusEnum();

        // Assert
        expect($statusEnum)->toBeInstanceOf(LeadStatus::class)
            ->and($statusEnum)->toBe(LeadStatus::EmailConfirmed);
    });

    test('returns PendingEmail as default when status is invalid', function () {
        // Arrange
        $lead = Lead::factory()->create(['status' => 'invalid_status']);

        // Act
        $statusEnum = $lead->getStatusEnum();

        // Assert
        expect($statusEnum)->toBe(LeadStatus::PendingEmail);
    });

    test('sets status from LeadStatus enum', function () {
        // Arrange
        $lead = Lead::factory()->create();

        // Act
        $lead->setStatus(LeadStatus::Qualified);

        // Assert
        expect($lead->status)->toBe(LeadStatus::Qualified->value);
    });

    test('sets status from string', function () {
        // Arrange
        $lead = Lead::factory()->create();

        // Act
        $lead->setStatus('qualified');

        // Assert
        expect($lead->status)->toBe('qualified');
    });

    test('returns true for isActive when status is active', function () {
        // Arrange
        $lead = Lead::factory()->create(['status' => LeadStatus::EmailConfirmed->value]);

        // Act & Assert
        expect($lead->isActive())->toBeTrue();
    });

    test('returns false for isActive when status is final', function () {
        // Arrange
        $lead = Lead::factory()->create(['status' => LeadStatus::Converted->value]);

        // Act & Assert
        expect($lead->isActive())->toBeFalse();
    });

    test('returns true for isFinal when status is final', function () {
        // Arrange
        $lead = Lead::factory()->create(['status' => LeadStatus::Converted->value]);

        // Act & Assert
        expect($lead->isFinal())->toBeTrue();
    });

    test('returns false for isFinal when status is active', function () {
        // Arrange
        $lead = Lead::factory()->create(['status' => LeadStatus::PendingCall->value]);

        // Act & Assert
        expect($lead->isFinal())->toBeFalse();
    });

    test('marks lead as pending call', function () {
        // Arrange
        $lead = Lead::factory()->create(['status' => LeadStatus::EmailConfirmed->value]);

        // Act
        $lead->markAsPendingCall();

        // Assert
        expect($lead->fresh()->status)->toBe(LeadStatus::PendingCall->value);
    });

    test('updates status after call with valid status', function () {
        // Arrange
        $lead = Lead::factory()->create(['status' => LeadStatus::PendingCall->value]);

        // Act
        $lead->updateAfterCall(LeadStatus::Qualified, 'Lead is interested');

        // Assert
        expect($lead->fresh()->status)->toBe(LeadStatus::Qualified->value)
            ->and($lead->fresh()->call_comment)->toBe('Lead is interested')
            ->and($lead->fresh()->called_at)->not->toBeNull();
    });

    test('throws exception when updating after call with invalid status', function () {
        // Arrange
        $lead = Lead::factory()->create(['status' => LeadStatus::PendingEmail->value]);

        // Act & Assert
        expect(fn () => $lead->updateAfterCall(LeadStatus::Qualified))
            ->toThrow(\InvalidArgumentException::class);
    });
});

describe('Lead Model - Score Management', function () {
    test('returns high priority for score >= 80', function () {
        // Arrange
        $lead = Lead::factory()->create(['score' => 85]);

        // Act
        $priority = $lead->getScorePriority();

        // Assert
        expect($priority)->toBe('high');
    });

    test('returns medium priority for score between 60 and 79', function () {
        // Arrange
        $lead = Lead::factory()->create(['score' => 70]);

        // Act
        $priority = $lead->getScorePriority();

        // Assert
        expect($priority)->toBe('medium');
    });

    test('returns low priority for score < 60', function () {
        // Arrange
        $lead = Lead::factory()->create(['score' => 45]);

        // Act
        $priority = $lead->getScorePriority();

        // Assert
        expect($priority)->toBe('low');
    });

    test('returns correct badge color for high priority', function () {
        // Arrange
        $lead = Lead::factory()->create(['score' => 85]);

        // Act
        $badgeColor = $lead->getScoreBadgeColor();

        // Assert
        expect($badgeColor)->toContain('green');
    });

    test('returns correct badge color for medium priority', function () {
        // Arrange
        $lead = Lead::factory()->create(['score' => 70]);

        // Act
        $badgeColor = $lead->getScoreBadgeColor();

        // Assert
        expect($badgeColor)->toContain('orange');
    });

    test('returns correct badge color for low priority', function () {
        // Arrange
        $lead = Lead::factory()->create(['score' => 45]);

        // Act
        $badgeColor = $lead->getScoreBadgeColor();

        // Assert
        expect($badgeColor)->toContain('red');
    });

    test('returns correct score label for high priority', function () {
        // Arrange
        $lead = Lead::factory()->create(['score' => 85]);

        // Act
        $label = $lead->getScoreLabel();

        // Assert
        expect($label)->toContain('haute');
    });

    test('returns correct score label for medium priority', function () {
        // Arrange
        $lead = Lead::factory()->create(['score' => 70]);

        // Act
        $label = $lead->getScoreLabel();

        // Assert
        expect($label)->toContain('moyenne');
    });

    test('returns correct score label for low priority', function () {
        // Arrange
        $lead = Lead::factory()->create(['score' => 45]);

        // Act
        $label = $lead->getScoreLabel();

        // Assert
        expect($label)->toContain('basse');
    });

    test('handles null score gracefully', function () {
        // Arrange
        $lead = Lead::factory()->create(['score' => null]);

        // Act
        $priority = $lead->getScorePriority();

        // Assert
        expect($priority)->toBe('low');
    });
});

describe('Lead Model - Relationships', function () {
    test('belongs to form', function () {
        // Arrange
        $form = Form::factory()->create();
        $lead = Lead::factory()->create(['form_id' => $form->id]);

        // Act
        $leadForm = $lead->form;

        // Assert
        expect($leadForm)->toBeInstanceOf(Form::class)
            ->and($leadForm->id)->toBe($form->id);
    });

    test('belongs to assigned agent', function () {
        // Arrange
        $agent = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

        // Act
        $assignedAgent = $lead->assignedAgent;

        // Assert
        expect($assignedAgent)->toBeInstanceOf(User::class)
            ->and($assignedAgent->id)->toBe($agent->id);
    });

    test('belongs to call center', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $lead = Lead::factory()->create(['call_center_id' => $callCenter->id]);

        // Act
        $leadCallCenter = $lead->callCenter;

        // Assert
        expect($leadCallCenter)->toBeInstanceOf(CallCenter::class)
            ->and($leadCallCenter->id)->toBe($callCenter->id);
    });

    test('has many notes', function () {
        // Arrange
        $lead = Lead::factory()->create();
        LeadNote::factory()->count(3)->create(['lead_id' => $lead->id]);

        // Act
        $notes = $lead->notes;

        // Assert
        expect($notes)->toHaveCount(3);
    });

    test('has many reminders', function () {
        // Arrange
        $lead = Lead::factory()->create();
        LeadReminder::factory()->count(2)->create(['lead_id' => $lead->id]);

        // Act
        $reminders = $lead->reminders;

        // Assert
        expect($reminders)->toHaveCount(2);
    });

    test('belongs to many tags', function () {
        // Arrange
        $lead = Lead::factory()->create();
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $lead->tags()->attach([$tag1->id, $tag2->id]);

        // Act
        $tags = $lead->tags;

        // Assert
        expect($tags)->toHaveCount(2)
            ->and($tags->pluck('id')->toArray())->toContain($tag1->id, $tag2->id);
    });
});

describe('Lead Model - Status History', function () {
    test('retrieves status history from activity logs', function () {
        // Arrange
        $lead = Lead::factory()->create(['status' => LeadStatus::PendingEmail->value]);
        \App\Models\ActivityLog::factory()->create([
            'action' => 'lead.status_updated',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'properties' => [
                'old_status' => 'pending_email',
                'new_status' => 'email_confirmed',
            ],
        ]);

        // Act
        $history = $lead->getStatusHistory();

        // Assert
        expect($history)->not->toBeEmpty()
            ->and($history->first()->action)->toBe('lead.status_updated');
    });

    test('returns empty collection when no status history exists', function () {
        // Arrange
        $lead = Lead::factory()->create();

        // Act
        $history = $lead->getStatusHistory();

        // Assert
        expect($history)->toBeEmpty();
    });
});

describe('Lead Model - Casts', function () {
    test('casts data to array', function () {
        // Arrange
        $data = ['name' => 'John', 'phone' => '1234567890'];
        $lead = Lead::factory()->create(['data' => $data]);

        // Act & Assert
        expect($lead->data)->toBeArray()
            ->and($lead->data)->toBe($data);
    });

    test('casts email_confirmed_at to datetime', function () {
        // Arrange
        $lead = Lead::factory()->create(['email_confirmed_at' => now()]);

        // Act & Assert
        expect($lead->email_confirmed_at)->toBeInstanceOf(Carbon::class);
    });

    test('casts email_confirmation_token_expires_at to datetime', function () {
        // Arrange
        $lead = Lead::factory()->create([
            'email_confirmation_token_expires_at' => now()->addHours(24),
        ]);

        // Act & Assert
        expect($lead->email_confirmation_token_expires_at)->toBeInstanceOf(Carbon::class);
    });

    test('casts called_at to datetime', function () {
        // Arrange
        $lead = Lead::factory()->create(['called_at' => now()]);

        // Act & Assert
        expect($lead->called_at)->toBeInstanceOf(Carbon::class);
    });

    test('casts score_updated_at to datetime', function () {
        // Arrange
        $lead = Lead::factory()->create(['score_updated_at' => now()]);

        // Act & Assert
        expect($lead->score_updated_at)->toBeInstanceOf(Carbon::class);
    });

    test('casts score_factors to array', function () {
        // Arrange
        $factors = ['form_source' => 10, 'email_confirmation' => 15];
        $lead = Lead::factory()->create(['score_factors' => $factors]);

        // Act & Assert
        expect($lead->score_factors)->toBeArray()
            ->and($lead->score_factors)->toBe($factors);
    });

    test('handles null score_factors gracefully', function () {
        // Arrange
        $lead = Lead::factory()->create(['score_factors' => null]);

        // Act & Assert
        expect($lead->score_factors)->toBeNull();
    });
});
