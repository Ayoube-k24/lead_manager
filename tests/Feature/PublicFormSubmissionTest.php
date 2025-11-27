<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use Illuminate\Support\Facades\Queue;

describe('Public Form Submission - Successful Submission', function () {
    test('form can be submitted publicly', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                [
                    'name' => 'name',
                    'label' => 'Nom',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'name' => 'email',
                    'label' => 'Email',
                    'type' => 'email',
                    'required' => true,
                ],
            ],
        ]);

        // Act
        $response = $this->postJson(route('forms.submit', $form), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'lead_id',
            ]);

        expect(Lead::where('form_id', $form->id)->count())->toBe(1);
    });

    test('creates lead with correct data', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $callCenter = CallCenter::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'required' => false],
            ],
        ]);

        // Act
        $response = $this->postJson(route('forms.submit', $form), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ]);

        // Assert
        $response->assertStatus(201);
        $lead = Lead::where('form_id', $form->id)->first();
        expect($lead)->not->toBeNull()
            ->and($lead->email)->toBe('john@example.com')
            ->and($lead->data['name'])->toBe('John Doe')
            ->and($lead->data['phone'])->toBe('1234567890')
            ->and($lead->call_center_id)->toBe($callCenter->id)
            ->and($lead->status)->toBe('pending_email');
    });

    test('queues confirmation email after submission', function () {
        // Arrange
        Queue::fake();
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

        // Act
        $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        // Assert
        Queue::assertPushed(\App\Jobs\SendLeadConfirmationEmail::class);
    });
});

describe('Public Form Submission - Validation', function () {
    test('form submission validates required fields', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                [
                    'name' => 'email',
                    'label' => 'Email',
                    'type' => 'email',
                    'required' => true,
                ],
            ],
        ]);

        // Act
        $response = $this->postJson(route('forms.submit', $form), []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('validates email format', function () {
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

        // Act
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'invalid-email',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('handles optional fields correctly', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'required' => false],
            ],
        ]);

        // Act
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
            // phone is optional, so we don't include it
        ]);

        // Assert
        $response->assertStatus(201);
        $lead = Lead::where('form_id', $form->id)->first();
        expect($lead->data)->not->toHaveKey('phone');
    });

    test('validates different field types', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'required' => true],
                ['name' => 'date', 'type' => 'date', 'required' => true],
            ],
        ]);

        // Act
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'date' => '2025-01-27',
        ]);

        // Assert
        $response->assertStatus(201);
    });
});

describe('Public Form Submission - Form Status', function () {
    test('inactive form cannot be submitted', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => false,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);

        // Act
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        // Assert
        $response->assertStatus(403);
    });

    test('form without smtp profile cannot be submitted', function () {
        // Arrange
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => null,
            'email_template_id' => $emailTemplate->id,
        ]);

        // Act
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        // Assert
        $response->assertStatus(400);
    });

    test('form without email template cannot be submitted', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => null,
        ]);

        // Act
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        // Assert
        $response->assertStatus(400);
    });
});

describe('Public Form Submission - Rate Limiting', function () {
    test('applies rate limiting after multiple submissions', function () {
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

        // Act - Submit 10 times (limit is 10 per minute)
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('forms.submit', $form), [
                'email' => "test{$i}@example.com",
            ]);
        }

        // 11th submission should be throttled
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test11@example.com',
        ]);

        // Assert
        $response->assertStatus(429); // Too Many Requests
    });
});

describe('Public Form Submission - Route', function () {
    test('public submission route uses the form uid', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);

        // Act
        $url = route('forms.submit', $form);

        // Assert
        expect($url)->toContain($form->uid);
    });
});
