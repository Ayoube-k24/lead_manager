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

            // Verify email was sent
            Mail::assertSent(function ($mail) use ($lead, $smtpProfile) {
                return $mail->hasTo($lead->email)
                    && $mail->hasFrom($smtpProfile->from_address);
            });
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

            // Verify no email was sent
            Mail::assertNothingSent();
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

            // Verify no email was sent
            Mail::assertNothingSent();
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

            // Verify no email was sent
            Mail::assertNothingSent();
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

            // Verify no email was sent
            Mail::assertNothingSent();
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

            // Verify email was sent with correct subject (rendered)
            Mail::assertSent(function ($mail) use ($lead) {
                return $mail->hasTo($lead->email)
                    && str_contains($mail->subject, 'John Doe');
            });
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

            // Verify email was sent
            Mail::assertSent(function ($mail) use ($lead) {
                return $mail->hasTo($lead->email);
            });
        });

        test('verifies email is actually sent with correct recipient and sender', function () {
            $smtpProfile = SmtpProfile::factory()->create([
                'is_active' => true,
                'from_address' => 'sender@example.com',
                'from_name' => 'Test Sender',
            ]);

            $emailTemplate = EmailTemplate::factory()->create([
                'subject' => 'Test Subject',
                'body_html' => '<p>Test body</p>',
                'body_text' => 'Test body',
            ]);

            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'recipient@example.com',
            ]);

            $result = $this->service->sendConfirmationEmail($lead);

            expect($result)->toBeTrue();

            // Verify email was sent to the correct recipient
            Mail::assertSent(function ($mail) use ($lead, $smtpProfile) {
                return $mail->hasTo($lead->email)
                    && $mail->hasFrom($smtpProfile->from_address, $smtpProfile->from_name);
            });

            // Verify at least one email was sent
            Mail::assertSentCount(1);
        });

        test('uses the SMTP profile associated with the form', function () {
            // Create two different SMTP profiles
            $smtpProfile1 = SmtpProfile::factory()->create([
                'is_active' => true,
                'from_address' => 'form1@example.com',
                'from_name' => 'Form 1 Company',
                'host' => 'smtp1.example.com',
            ]);

            $smtpProfile2 = SmtpProfile::factory()->create([
                'is_active' => true,
                'from_address' => 'form2@example.com',
                'from_name' => 'Form 2 Company',
                'host' => 'smtp2.example.com',
            ]);

            $emailTemplate = EmailTemplate::factory()->create();

            // Create two forms with different SMTP profiles
            $form1 = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile1->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            $form2 = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile2->id,
                'email_template_id' => $emailTemplate->id,
            ]);

            // Create leads for each form
            $lead1 = Lead::factory()->create([
                'form_id' => $form1->id,
                'email' => 'lead1@example.com',
            ]);

            $lead2 = Lead::factory()->create([
                'form_id' => $form2->id,
                'email' => 'lead2@example.com',
            ]);

            // Send emails
            $this->service->sendConfirmationEmail($lead1);
            $this->service->sendConfirmationEmail($lead2);

            // Verify lead1 email uses form1's SMTP
            Mail::assertSent(function ($mail) use ($lead1, $smtpProfile1) {
                return $mail->hasTo($lead1->email)
                    && $mail->hasFrom($smtpProfile1->from_address, $smtpProfile1->from_name);
            });

            // Verify lead2 email uses form2's SMTP
            Mail::assertSent(function ($mail) use ($lead2, $smtpProfile2) {
                return $mail->hasTo($lead2->email)
                    && $mail->hasFrom($smtpProfile2->from_address, $smtpProfile2->from_name);
            });

            // Verify both emails were sent
            Mail::assertSentCount(2);
        });

        test('verifies SMTP configuration is applied correctly from form profile', function () {
            $smtpProfile = SmtpProfile::factory()->create([
                'is_active' => true,
                'host' => 'custom-smtp.example.com',
                'port' => 465,
                'encryption' => 'ssl',
                'username' => 'custom-user',
                'password' => 'custom-password',
                'from_address' => 'custom@example.com',
                'from_name' => 'Custom SMTP',
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

            $result = $this->service->sendConfirmationEmail($lead);

            expect($result)->toBeTrue();

            // Verify email uses the SMTP profile's from address and name
            Mail::assertSent(function ($mail) use ($smtpProfile) {
                return $mail->hasFrom($smtpProfile->from_address, $smtpProfile->from_name);
            });

            // Verify the SMTP configuration was set (check Config)
            expect(Config::get('mail.mailers.dynamic_smtp.host'))->toBe($smtpProfile->host)
                ->and(Config::get('mail.mailers.dynamic_smtp.port'))->toBe($smtpProfile->port)
                ->and(Config::get('mail.mailers.dynamic_smtp.encryption'))->toBe($smtpProfile->encryption)
                ->and(Config::get('mail.mailers.dynamic_smtp.username'))->toBe($smtpProfile->username);
        });

        test('each form uses its own SMTP profile independently', function () {
            // Create multiple SMTP profiles with different configurations
            $smtpProfiles = SmtpProfile::factory()->count(3)->create([
                'is_active' => true,
            ]);

            $emailTemplate = EmailTemplate::factory()->create();

            // Create forms, each with a different SMTP profile
            $forms = [];
            foreach ($smtpProfiles as $index => $smtpProfile) {
                $forms[] = Form::factory()->create([
                    'smtp_profile_id' => $smtpProfile->id,
                    'email_template_id' => $emailTemplate->id,
                ]);
            }

            // Create leads for each form
            $leads = [];
            foreach ($forms as $index => $form) {
                $leads[] = Lead::factory()->create([
                    'form_id' => $form->id,
                    'email' => "lead{$index}@example.com",
                ]);
            }

            // Send emails for all leads
            foreach ($leads as $index => $lead) {
                $this->service->sendConfirmationEmail($lead);

                // Verify each email uses its form's SMTP profile
                $smtpProfile = $smtpProfiles[$index];
                Mail::assertSent(function ($mail) use ($lead, $smtpProfile) {
                    return $mail->hasTo($lead->email)
                        && $mail->hasFrom($smtpProfile->from_address);
                });
            }

            // Verify all emails were sent
            Mail::assertSentCount(3);
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

