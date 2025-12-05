<?php

declare(strict_types=1);

use App\Jobs\SendLeadReminderEmail;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use App\Services\LeadConfirmationService;

use function Pest\Laravel\mock;

describe('SendLeadReminderEmail Job', function () {
    test('sends reminder email for pending lead', function () {
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create();
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'status' => 'pending_email',
        ]);

        $confirmationService = mock(LeadConfirmationService::class);
        $confirmationService->shouldReceive('sendConfirmationEmail')
            ->once()
            ->with($lead)
            ->andReturn(true);

        $job = new SendLeadReminderEmail($lead);
        $job->handle($confirmationService);
    });

    test('skips when lead status is not pending', function () {
        $lead = Lead::factory()->create(['status' => 'confirmed']);

        $confirmationService = mock(LeadConfirmationService::class);
        $confirmationService->shouldNotReceive('sendConfirmationEmail');

        $job = new SendLeadReminderEmail($lead);
        $job->handle($confirmationService);
    });

    test('regenerates token when expired', function () {
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create();
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'status' => 'pending_email',
            'email_confirmation_token' => 'old-token',
            'email_confirmation_token_expires_at' => now()->subHour(),
        ]);

        $confirmationService = mock(LeadConfirmationService::class);
        $confirmationService->shouldReceive('sendConfirmationEmail')
            ->once()
            ->andReturn(true);

        $job = new SendLeadReminderEmail($lead);
        $job->handle($confirmationService);

        expect($lead->fresh()->email_confirmation_token)->not->toBe('old-token')
            ->and($lead->fresh()->email_confirmation_token_expires_at)->not->toBeNull();
    });

    test('throws exception when email sending fails', function () {
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create();
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'status' => 'pending_email',
        ]);

        $confirmationService = mock(LeadConfirmationService::class);
        $confirmationService->shouldReceive('sendConfirmationEmail')
            ->once()
            ->andReturn(false);

        $job = new SendLeadReminderEmail($lead);

        expect(fn () => $job->handle($confirmationService))
            ->toThrow(Exception::class);
    });
});
