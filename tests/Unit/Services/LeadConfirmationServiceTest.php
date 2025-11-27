<?php

declare(strict_types=1);

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use App\Services\LeadConfirmationService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
    Mail::fake();
});

describe('LeadConfirmationService - sendConfirmationEmail', function () {
    test('sends confirmation email successfully with valid form and SMTP profile', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create([
            'subject' => 'Confirm your email {{name}}',
            'body_html' => '<p>Click here: {{confirmation_link}}</p>',
            'body_text' => 'Click here: {{confirmation_link}}',
        ]);
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'email' => 'test@example.com',
            'data' => ['name' => 'John Doe'],
        ]);
        $service = new LeadConfirmationService();

        // Act
        $result = $service->sendConfirmationEmail($lead);

        // Assert
        expect($result)->toBeTrue();
        Mail::assertSent(function ($mail) use ($lead) {
            return $mail->hasTo($lead->email);
        });
    });

    test('returns false when lead has no form', function () {
        // Arrange
        $lead = Lead::factory()->create(['form_id' => null]);
        $service = new LeadConfirmationService();

        // Act
        $result = $service->sendConfirmationEmail($lead);

        // Assert
        expect($result)->toBeFalse();
        Mail::assertNothingSent();
    });

    test('returns false when form has no SMTP profile', function () {
        // Arrange
        $form = Form::factory()->create(['smtp_profile_id' => null]);
        $lead = Lead::factory()->create(['form_id' => $form->id]);
        $service = new LeadConfirmationService();

        // Act
        $result = $service->sendConfirmationEmail($lead);

        // Assert
        expect($result)->toBeFalse();
        Mail::assertNothingSent();
    });

    test('returns false when form has no email template', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => null,
        ]);
        $lead = Lead::factory()->create(['form_id' => $form->id]);
        $service = new LeadConfirmationService();

        // Act
        $result = $service->sendConfirmationEmail($lead);

        // Assert
        expect($result)->toBeFalse();
        Mail::assertNothingSent();
    });

    test('returns false when SMTP profile is inactive', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => false]);
        $emailTemplate = EmailTemplate::factory()->create();
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create(['form_id' => $form->id]);
        $service = new LeadConfirmationService();

        // Act
        $result = $service->sendConfirmationEmail($lead);

        // Assert
        expect($result)->toBeFalse();
        Mail::assertNothingSent();
    });

    test('generates confirmation token if not exists', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create();
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'email_confirmation_token' => null,
        ]);
        $service = new LeadConfirmationService();

        // Act
        $service->sendConfirmationEmail($lead);

        // Assert
        $lead->refresh();
        expect($lead->email_confirmation_token)->not->toBeNull()
            ->and($lead->email_confirmation_token_expires_at)->not->toBeNull();
    });

    test('uses existing confirmation token if already exists', function () {
        // Arrange
        $existingToken = 'existing-token-123';
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create();
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'email_confirmation_token' => $existingToken,
        ]);
        $service = new LeadConfirmationService();

        // Act
        $service->sendConfirmationEmail($lead);

        // Assert
        $lead->refresh();
        expect($lead->email_confirmation_token)->toBe($existingToken);
    });

    test('renders template variables correctly', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create([
            'is_active' => true,
            'from_address' => 'noreply@example.com',
            'from_name' => 'Test Company',
        ]);
        $emailTemplate = EmailTemplate::factory()->create([
            'subject' => 'Hello {{name}}',
            'body_html' => '<p>Your email is {{email}}. Confirm: {{confirmation_link}}</p>',
        ]);
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'email' => 'test@example.com',
            'data' => ['name' => 'John Doe'],
        ]);
        $service = new LeadConfirmationService();

        // Act
        $service->sendConfirmationEmail($lead);

        // Assert
        Mail::assertSent(function ($mail) {
            $subject = $mail->subject;
            $body = $mail->viewData['body'] ?? '';

            return str_contains($subject, 'John Doe')
                && str_contains($body, 'test@example.com')
                && str_contains($body, route('leads.confirm-email', ['token' => '']));
        });
    });

    test('handles template variables with both {{}} and {} syntax', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create([
            'subject' => 'Hello {name}',
            'body_html' => '<p>Email: {{email}}</p>',
        ]);
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'email' => 'test@example.com',
            'data' => ['name' => 'John'],
        ]);
        $service = new LeadConfirmationService();

        // Act
        $result = $service->sendConfirmationEmail($lead);

        // Assert
        expect($result)->toBeTrue();
    });

    test('extracts name from various data fields', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create([
            'subject' => 'Hello {{name}}',
        ]);
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'data' => ['first_name' => 'John', 'last_name' => 'Doe'],
        ]);
        $service = new LeadConfirmationService();

        // Act
        $result = $service->sendConfirmationEmail($lead);

        // Assert
        expect($result)->toBeTrue();
    });

    test('uses default name when no name field found', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create([
            'subject' => 'Hello {{name}}',
        ]);
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'data' => [],
        ]);
        $service = new LeadConfirmationService();

        // Act
        $result = $service->sendConfirmationEmail($lead);

        // Assert
        expect($result)->toBeTrue();
    });
});

