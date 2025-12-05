<?php

declare(strict_types=1);

use App\Jobs\SendLeadConfirmationEmail;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use App\Services\LeadConfirmationService;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\mock;

describe('SendLeadConfirmationEmail Job', function () {
    test('sends confirmation email successfully', function () {
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create();
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create(['form_id' => $form->id]);

        $confirmationService = mock(LeadConfirmationService::class);
        $confirmationService->shouldReceive('sendConfirmationEmail')
            ->once()
            ->with($lead)
            ->andReturn(true);

        $job = new SendLeadConfirmationEmail($lead);
        $job->handle($confirmationService);
    });

    test('skips when lead does not exist', function () {
        $lead = Lead::factory()->create();
        $lead->delete();

        Log::spy();

        $confirmationService = mock(LeadConfirmationService::class);
        $confirmationService->shouldNotReceive('sendConfirmationEmail');

        $job = new SendLeadConfirmationEmail($lead);
        $job->handle($confirmationService);

        Log::shouldHaveReceived('warning')
            ->with('Lead does not exist anymore', \Mockery::type('array'));
    });

    test('skips when form is missing', function () {
        $lead = Lead::factory()->create(['form_id' => null]);

        Log::spy();

        $confirmationService = mock(LeadConfirmationService::class);
        $confirmationService->shouldNotReceive('sendConfirmationEmail');

        $job = new SendLeadConfirmationEmail($lead);
        $job->handle($confirmationService);

        Log::shouldHaveReceived('warning');
    });

    test('skips when SMTP profile is inactive', function () {
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => false]);
        $emailTemplate = EmailTemplate::factory()->create();
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create(['form_id' => $form->id]);

        Log::spy();

        $confirmationService = mock(LeadConfirmationService::class);
        $confirmationService->shouldNotReceive('sendConfirmationEmail');

        $job = new SendLeadConfirmationEmail($lead);
        $job->handle($confirmationService);

        Log::shouldHaveReceived('warning');
    });

    test('throws exception when email sending fails', function () {
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create();
        $form = Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);
        $lead = Lead::factory()->create(['form_id' => $form->id]);

        $confirmationService = mock(LeadConfirmationService::class);
        $confirmationService->shouldReceive('sendConfirmationEmail')
            ->once()
            ->andReturn(false);

        $job = new SendLeadConfirmationEmail($lead);

        expect(fn () => $job->handle($confirmationService))
            ->toThrow(Exception::class);
    });

    test('has correct retry configuration', function () {
        $lead = Lead::factory()->create();
        $job = new SendLeadConfirmationEmail($lead);

        expect($job->tries)->toBe(5)
            ->and($job->backoff)->toBe(60);
    });

    test('logs failure when job fails', function () {
        $lead = Lead::factory()->create();
        $job = new SendLeadConfirmationEmail($lead);

        Log::spy();

        $job->failed(new \Exception('Test error'));

        Log::shouldHaveReceived('error')
            ->with('SendLeadConfirmationEmail job failed after all retries', \Mockery::type('array'));
    });
});
