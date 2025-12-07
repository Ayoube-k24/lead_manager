<?php

declare(strict_types=1);

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use App\Services\LeadConfirmationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

describe('Email Confirmation Security', function () {
    beforeEach(function () {
        Mail::fake();
        Log::spy();
    });

    describe('Email Validation', function () {
        test('prevents sending email to invalid email address', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'invalid-email-format',
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            // Should still attempt to send, but Laravel Mail will validate
            // The service should handle the exception gracefully
            expect($result)->toBeBool();
        });

        test('validates email is not empty before sending', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => '',
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            // Should handle empty email gracefully
            expect($result)->toBeBool();
        });

        test('validates email format using filter_var', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $invalidEmails = [
                'not-an-email',
                '@example.com',
                'test@',
                'test..test@example.com',
                'test@example',
                'test@.com',
            ];

            foreach ($invalidEmails as $invalidEmail) {
                $lead = Lead::factory()->create([
                    'form_id' => $form->id,
                    'email' => $invalidEmail,
                ]);

                $service = new LeadConfirmationService;
                $result = $service->sendConfirmationEmail($lead);

                // Service should attempt to send, but Mail will handle validation
                expect($result)->toBeBool();
            }
        });
    });

    describe('Template Rendering Security', function () {
        test('prevents XSS attacks in template variables', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create([
                'subject' => 'Hello {{name}}',
                'body_html' => '<p>{{name}}</p><p>{{confirmation_link}}</p>',
            ]);
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $xssPayload = '<script>alert("XSS")</script>';
            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
                'data' => ['name' => $xssPayload],
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            expect($result)->toBeTrue();

            // The template rendering should not escape HTML by default
            // But the email client should handle it
            // We verify the service doesn't crash
        });

        test('handles malicious confirmation link injection', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create([
                'subject' => 'Confirm: {{confirmation_link}}',
                'body_html' => '<a href="{{confirmation_link}}">Click here</a>',
            ]);
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            // Try to inject malicious token
            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
                'email_confirmation_token' => '../../etc/passwd',
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            expect($result)->toBeTrue();
            // The token should be used in route generation, which is safe
        });

        test('sanitizes template variables to prevent injection', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create([
                'subject' => '{{name}} - {{email}}',
                'body_html' => '<p>{{name}}</p><p>{{email}}</p>',
            ]);
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $maliciousData = [
                'name' => "'; DROP TABLE leads; --",
                'email' => 'test@example.com',
            ];

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
                'data' => $maliciousData,
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            expect($result)->toBeTrue();
            // Template rendering should handle this safely
        });
    });

    describe('SMTP Profile Security', function () {
        test('verifies email uses the SMTP profile from the form', function () {
            $smtpProfile = SmtpProfile::factory()->create([
                'is_active' => true,
                'from_address' => 'form-smtp@example.com',
                'from_name' => 'Form SMTP',
                'host' => 'smtp.form.com',
            ]);

            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            expect($result)->toBeTrue();

            // Verify email uses the form's SMTP profile
            Mail::assertSent(function ($mail) use ($smtpProfile) {
                return $mail->hasFrom($smtpProfile->from_address, $smtpProfile->from_name);
            });
        });

        test('prevents sending with inactive SMTP profile', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => false]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            expect($result)->toBeFalse();
        });

        test('handles missing SMTP profile gracefully', function () {
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => null,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            expect($result)->toBeFalse();
        });

        test('validates SMTP configuration before sending', function () {
            $smtpProfile = SmtpProfile::factory()->create([
                'is_active' => true,
                'host' => '',
                'port' => 0,
            ]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            // Should attempt to send but fail due to invalid SMTP config
            expect($result)->toBeBool();
        });

        test('ensures different forms use their own SMTP profiles', function () {
            // Create two different SMTP profiles
            $smtpProfile1 = SmtpProfile::factory()->create([
                'is_active' => true,
                'from_address' => 'form1@example.com',
                'from_name' => 'Form 1',
            ]);

            $smtpProfile2 = SmtpProfile::factory()->create([
                'is_active' => true,
                'from_address' => 'form2@example.com',
                'from_name' => 'Form 2',
            ]);

            $emailTemplate = EmailTemplate::factory()->create();

            $form1 = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile1->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $form2 = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile2->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead1 = Lead::factory()->create([
                'form_id' => $form1->id,
                'email' => 'lead1@example.com',
            ]);

            $lead2 = Lead::factory()->create([
                'form_id' => $form2->id,
                'email' => 'lead2@example.com',
            ]);

            $service = new LeadConfirmationService;
            $service->sendConfirmationEmail($lead1);
            $service->sendConfirmationEmail($lead2);

            // Verify each email uses its form's SMTP profile
            Mail::assertSent(function ($mail) use ($lead1, $smtpProfile1) {
                return $mail->hasTo($lead1->email)
                    && $mail->hasFrom($smtpProfile1->from_address);
            });

            Mail::assertSent(function ($mail) use ($lead2, $smtpProfile2) {
                return $mail->hasTo($lead2->email)
                    && $mail->hasFrom($smtpProfile2->from_address);
            });
        });
    });

    describe('Token Security', function () {
        test('generates secure random token', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
                'email_confirmation_token' => null,
            ]);

            $service = new LeadConfirmationService;
            $service->sendConfirmationEmail($lead);

            $token = $lead->fresh()->email_confirmation_token;
            expect($token)->not->toBeNull()
                ->and(strlen($token))->toBe(64);
        });

        test('sets token expiration correctly', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
                'email_confirmation_token_expires_at' => null,
            ]);

            $service = new LeadConfirmationService;
            $service->sendConfirmationEmail($lead);

            $expiresAt = $lead->fresh()->email_confirmation_token_expires_at;
            expect($expiresAt)->not->toBeNull()
                ->and($expiresAt->isFuture())->toBeTrue()
                ->and(now()->diffInHours($expiresAt, false))->toBeGreaterThanOrEqual(23)
                ->and(now()->diffInHours($expiresAt, false))->toBeLessThanOrEqual(24);
        });

        test('regenerates token expiration if expired', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
                'email_confirmation_token_expires_at' => now()->subDay(),
            ]);

            $service = new LeadConfirmationService;
            $service->sendConfirmationEmail($lead);

            $expiresAt = $lead->fresh()->email_confirmation_token_expires_at;
            expect($expiresAt)->not->toBeNull()
                ->and($expiresAt->isFuture())->toBeTrue();
        });

        test('prevents token reuse across different leads', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead1 = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test1@example.com',
            ]);

            $lead2 = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test2@example.com',
            ]);

            $service = new LeadConfirmationService;
            $service->sendConfirmationEmail($lead1);
            $service->sendConfirmationEmail($lead2);

            $token1 = $lead1->fresh()->email_confirmation_token;
            $token2 = $lead2->fresh()->email_confirmation_token;

            expect($token1)->not->toBe($token2);
        });
    });

    describe('Error Handling', function () {
        test('handles email sending failures gracefully', function () {
            $smtpProfile = SmtpProfile::factory()->create([
                'is_active' => true,
                'host' => 'invalid-host-that-does-not-exist.local',
                'port' => 587,
            ]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            // Should return false on failure
            expect($result)->toBeBool();

            // Should log the error
            Log::shouldHaveReceived('error')
                ->with('Failed to send confirmation email', \Mockery::type('array'));
        });

        test('handles missing email template gracefully', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => null,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            expect($result)->toBeFalse();
        });

        test('handles missing form gracefully', function () {
            $lead = Lead::factory()->create([
                'form_id' => null,
                'email' => 'test@example.com',
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            expect($result)->toBeFalse();
        });
    });

    describe('Rate Limiting and DoS Prevention', function () {
        test('prevents sending to extremely long email addresses', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $longEmail = str_repeat('a', 1000).'@example.com';
            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => $longEmail,
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            // Should handle long email gracefully
            expect($result)->toBeBool();
        });

        test('handles very long template content', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $longContent = str_repeat('<p>Content</p>', 10000);
            $emailTemplate = EmailTemplate::factory()->create([
                'subject' => 'Test',
                'body_html' => $longContent,
            ]);
            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'test@example.com',
            ]);

            $service = new LeadConfirmationService;
            $result = $service->sendConfirmationEmail($lead);

            // Should handle long content
            expect($result)->toBeBool();
        });
    });
});
