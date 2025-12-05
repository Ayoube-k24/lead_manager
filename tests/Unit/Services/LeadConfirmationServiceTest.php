<?php

declare(strict_types=1);

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use App\Services\LeadConfirmationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

describe('LeadConfirmationService', function () {
    beforeEach(function () {
        $this->service = new LeadConfirmationService();
        Mail::fake();
    });

    describe('sendConfirmationEmail', function () {
        test('sends confirmation email successfully', function () {
            $smtpProfile = SmtpProfile::factory()->create([
                'is_active' => true,
                'from_address' => 'noreply@example.com',
                'from_name' => 'Test Company',
            ]);

            $emailTemplate = EmailTemplate::factory()->create([
                'subject' => 'Confirm your email',
                'body_html' => '<p>Hello {{name}}, please confirm: {{confirmation_link}}</p>',
                'body_text' => 'Hello {{name}}, please confirm: {{confirmation_link}}',
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

            $result = $this->service->sendConfirmationEmail($lead);

            expect($result)->toBeTrue()
                ->and($lead->fresh()->email_confirmation_token)->not->toBeNull()
                ->and($lead->fresh()->email_confirmation_token_expires_at)->not->toBeNull();
        });

        test('generates token if not exists', function () {
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

            $this->service->sendConfirmationEmail($lead);

            expect($lead->fresh()->email_confirmation_token)->not->toBeNull()
                ->and(strlen($lead->fresh()->email_confirmation_token))->toBe(64);
        });

        test('sets token expiration to 24 hours', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email_confirmation_token' => null,
                'email_confirmation_token_expires_at' => null, // Ensure no expiration is set
            ]);

            $this->service->sendConfirmationEmail($lead);

            $expiresAt = $lead->fresh()->email_confirmation_token_expires_at;
            // Calculate hours until expiration (expiresAt is in the future)
            $hoursUntilExpiration = now()->diffInHours($expiresAt, false);
            
            expect($expiresAt)->not->toBeNull()
                ->and($expiresAt->isFuture())->toBeTrue()
                ->and($hoursUntilExpiration)->toBeGreaterThanOrEqual(23) // Should be close to 24 hours
                ->and($hoursUntilExpiration)->toBeLessThanOrEqual(24);
        });

        test('returns false when form is missing', function () {
            $lead = Lead::factory()->create([
                'form_id' => null,
            ]);

            $result = $this->service->sendConfirmationEmail($lead);

            expect($result)->toBeFalse();
        });

        test('returns false when SMTP profile is missing', function () {
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => null,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
            ]);

            $result = $this->service->sendConfirmationEmail($lead);

            expect($result)->toBeFalse();
        });

        test('returns false when email template is missing', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => null,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
            ]);

            $result = $this->service->sendConfirmationEmail($lead);

            expect($result)->toBeFalse();
        });

        test('returns false when SMTP profile is inactive', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => false]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
            ]);

            $result = $this->service->sendConfirmationEmail($lead);

            expect($result)->toBeFalse();
        });

        test('renders template variables correctly', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create([
                'subject' => 'Hello {{name}}',
                'body_html' => '<p>Confirm: {{confirmation_link}}</p>',
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

            $result = $this->service->sendConfirmationEmail($lead);

            expect($result)->toBeTrue();
            // The email should be sent with rendered variables
        });

        test('uses existing token if already exists', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $existingToken = 'existing-token-123';
            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email_confirmation_token' => $existingToken,
            ]);

            $this->service->sendConfirmationEmail($lead);

            expect($lead->fresh()->email_confirmation_token)->toBe($existingToken);
        });
    });

    describe('prepareTemplateVariables', function () {
        test('extracts name from various data fields', function () {
            $lead = Lead::factory()->create([
                'data' => ['name' => 'John Doe'],
            ]);

            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('prepareTemplateVariables');
            $method->setAccessible(true);

            $variables = $method->invoke($this->service, $lead, 'https://example.com/confirm');

            expect($variables['name'])->toBe('John Doe');
        });

        test('uses first_name if name is not available', function () {
            $lead = Lead::factory()->create([
                'data' => ['first_name' => 'John'],
            ]);

            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('prepareTemplateVariables');
            $method->setAccessible(true);

            $variables = $method->invoke($this->service, $lead, 'https://example.com/confirm');

            expect($variables['name'])->toBe('John');
        });

        test('uses default name when no name fields available', function () {
            $lead = Lead::factory()->create([
                'data' => [],
            ]);

            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('prepareTemplateVariables');
            $method->setAccessible(true);

            $variables = $method->invoke($this->service, $lead, 'https://example.com/confirm');

            expect($variables['name'])->toBe('Cher client');
        });

        test('includes confirmation link in variables', function () {
            $lead = Lead::factory()->create();
            $confirmationUrl = 'https://example.com/confirm/token123';

            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('prepareTemplateVariables');
            $method->setAccessible(true);

            $variables = $method->invoke($this->service, $lead, $confirmationUrl);

            expect($variables['confirmation_link'])->toBe($confirmationUrl);
        });
    });
});

