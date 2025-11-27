<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Form Model - UID Generation', function () {
    test('generates unique UID automatically on creation', function () {
        // Arrange & Act
        $form = Form::factory()->create();

        // Assert
        expect($form->uid)->not->toBeNull()
            ->and($form->uid)->toBeString()
            ->and(strlen($form->uid))->toBeGreaterThan(0);
    });

    test('generates unique UIDs for multiple forms', function () {
        // Arrange & Act
        $form1 = Form::factory()->create();
        $form2 = Form::factory()->create();
        $form3 = Form::factory()->create();

        // Assert
        expect($form1->uid)->not->toBe($form2->uid)
            ->and($form2->uid)->not->toBe($form3->uid)
            ->and($form1->uid)->not->toBe($form3->uid);
    });

    test('preserves UID when updating form', function () {
        // Arrange
        $form = Form::factory()->create();
        $originalUid = $form->uid;

        // Act
        $form->update(['name' => 'Updated Name']);

        // Assert
        expect($form->fresh()->uid)->toBe($originalUid);
    });
});

describe('Form Model - Casts', function () {
    test('casts fields to array', function () {
        // Arrange
        $fields = [
            ['name' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'required' => true],
        ];
        $form = Form::factory()->create(['fields' => $fields]);

        // Act & Assert
        expect($form->fields)->toBeArray()
            ->and($form->fields)->toBe($fields);
    });

    test('casts is_active to boolean', function () {
        // Arrange
        $form = Form::factory()->create(['is_active' => 1]);

        // Act & Assert
        expect($form->is_active)->toBeBool()
            ->and($form->is_active)->toBeTrue();
    });

    test('handles null fields gracefully', function () {
        // Arrange
        $form = Form::factory()->create(['fields' => null]);

        // Act & Assert
        expect($form->fields)->toBeNull();
    });
});

describe('Form Model - Relationships', function () {
    test('belongs to call center', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        // Act
        $formCallCenter = $form->callCenter;

        // Assert
        expect($formCallCenter)->toBeInstanceOf(CallCenter::class)
            ->and($formCallCenter->id)->toBe($callCenter->id);
    });

    test('belongs to smtp profile', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $form = Form::factory()->create(['smtp_profile_id' => $smtpProfile->id]);

        // Act
        $formSmtpProfile = $form->smtpProfile;

        // Assert
        expect($formSmtpProfile)->toBeInstanceOf(SmtpProfile::class)
            ->and($formSmtpProfile->id)->toBe($smtpProfile->id);
    });

    test('belongs to email template', function () {
        // Arrange
        $emailTemplate = EmailTemplate::factory()->create();
        $form = Form::factory()->create(['email_template_id' => $emailTemplate->id]);

        // Act
        $formEmailTemplate = $form->emailTemplate;

        // Assert
        expect($formEmailTemplate)->toBeInstanceOf(EmailTemplate::class)
            ->and($formEmailTemplate->id)->toBe($emailTemplate->id);
    });

    test('has many leads', function () {
        // Arrange
        $form = Form::factory()->create();
        Lead::factory()->count(3)->create(['form_id' => $form->id]);

        // Act
        $leads = $form->leads;

        // Assert
        expect($leads)->toHaveCount(3);
    });
});

describe('Form Model - UID Generation Method', function () {
    test('generateUid creates unique 12-character uppercase string', function () {
        // Act
        $uid1 = Form::generateUid();
        $uid2 = Form::generateUid();

        // Assert
        expect($uid1)->toBeString()
            ->and(strlen($uid1))->toBe(12)
            ->and($uid1)->toBe(strtoupper($uid1))
            ->and($uid1)->not->toBe($uid2);
    });

    test('generateUid ensures uniqueness', function () {
        // Arrange
        $existingUid = 'ABCDEFGHIJKL';
        Form::factory()->create(['uid' => $existingUid]);

        // Act - Generate multiple UIDs to ensure we don't get the existing one
        $uids = [];
        for ($i = 0; $i < 10; $i++) {
            $uids[] = Form::generateUid();
        }

        // Assert
        expect($uids)->not->toContain($existingUid)
            ->and(count(array_unique($uids)))->toBe(10); // All unique
    });
});

describe('Form Model - Validation', function () {
    test('can access fields for validation', function () {
        // Arrange
        $fields = [
            ['name' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'required' => true],
            ['name' => 'phone', 'type' => 'tel', 'required' => false],
        ];
        $form = Form::factory()->create(['fields' => $fields]);

        // Act & Assert
        expect($form->fields)->toBeArray()
            ->and($form->fields)->toHaveCount(3)
            ->and($form->fields[0]['name'])->toBe('name')
            ->and($form->fields[0]['required'])->toBeTrue();
    });

    test('handles empty fields array', function () {
        // Arrange
        $form = Form::factory()->create(['fields' => []]);

        // Act & Assert
        expect($form->fields)->toBeArray()
            ->and($form->fields)->toBeEmpty();
    });
});
