<?php

declare(strict_types=1);

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\SmtpProfile;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Data Validation - SQL Injection Prevention', function () {
    test('form submission prevents SQL injection in email field', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Attempt SQL injection
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => "test@example.com'; DROP TABLE leads; --",
        ]);

        // Assert - Should be rejected as invalid email format
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('search prevents SQL injection', function () {
        // Arrange
        $user = User::factory()->create();

        // Act - Attempt SQL injection in search
        $response = $this->actingAs($user)
            ->get('/admin/leads?search=test\'; DROP TABLE leads; --');

        // Assert - Should not execute SQL, just treat as search string
        $response->assertSuccessful();
        // The search should be escaped by Eloquent
    });
});

describe('Data Validation - XSS Prevention', function () {
    test('form submission prevents XSS in name field', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit with XSS payload
        $response = $this->postJson(route('forms.submit', $form), [
            'name' => '<script>alert("XSS")</script>',
            'email' => 'test@example.com',
        ]);

        // Assert
        $response->assertStatus(201);
        // Data should be stored but escaped when displayed
        $lead = \App\Models\Lead::where('form_id', $form->id)->first();
        expect($lead->data['name'])->toContain('<script>'); // Stored as-is
        // But when rendered, Blade will escape it
    });

    test('email field validates format strictly', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit with invalid email formats
        $invalidEmails = [
            'not-an-email',
            'test@',
            '@example.com',
            'test..test@example.com',
            'test@example',
        ];

        foreach ($invalidEmails as $invalidEmail) {
            $response = $this->postJson(route('forms.submit', $form), [
                'email' => $invalidEmail,
            ]);

            // Assert
            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        }
    });
});

describe('Data Validation - Field Length Limits', function () {
    test('applies maximum length limits to text fields', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'required' => true, 'max_length' => 100],
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit with name exceeding max length
        $response = $this->postJson(route('forms.submit', $form), [
            'name' => str_repeat('a', 101), // 101 characters
            'email' => 'test@example.com',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('validates email field length', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit with email exceeding max length (255 chars)
        $longEmail = str_repeat('a', 250).'@example.com'; // > 255 chars
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => $longEmail,
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });
});

describe('Data Validation - Data Type Validation', function () {
    test('validates phone number format', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'phone', 'type' => 'tel', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit with invalid phone
        $response = $this->postJson(route('forms.submit', $form), [
            'phone' => 'not-a-phone',
            'email' => 'test@example.com',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    });

    test('validates date format', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'date', 'type' => 'date', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit with invalid date
        $response = $this->postJson(route('forms.submit', $form), [
            'date' => 'not-a-date',
            'email' => 'test@example.com',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    });

    test('validates numeric fields', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'age', 'type' => 'number', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit with non-numeric value
        $response = $this->postJson(route('forms.submit', $form), [
            'age' => 'not-a-number',
            'email' => 'test@example.com',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['age']);
    });
});

describe('Data Validation - Special Characters', function () {
    test('handles special characters in form data safely', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit with special characters
        $response = $this->postJson(route('forms.submit', $form), [
            'name' => "O'Brien & Associates <test>",
            'email' => 'test+tag@example.com',
        ]);

        // Assert
        $response->assertStatus(201);
        $lead = \App\Models\Lead::where('form_id', $form->id)->first();
        expect($lead->data['name'])->toBe("O'Brien & Associates <test>");
    });
});
