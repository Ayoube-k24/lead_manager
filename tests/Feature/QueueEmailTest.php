<?php

declare(strict_types=1);

use App\Jobs\SendLeadConfirmationEmail;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use Illuminate\Support\Facades\Queue;

test('confirmation email job is dispatched when form is submitted', function () {
    Queue::fake();

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

    $response = $this->postJson(route('forms.submit', $form), [
        'email' => 'test@example.com',
    ]);

    $response->assertStatus(201);

    Queue::assertPushed(SendLeadConfirmationEmail::class, function ($job) {
        return $job->lead instanceof Lead;
    });
});

test('confirmation email job has correct configuration', function () {
    $lead = Lead::factory()->create([
        'status' => 'pending_email',
    ]);

    $job = new SendLeadConfirmationEmail($lead);

    expect($job->lead)->toBe($lead)
        ->and($job->tries)->toBe(5)
        ->and($job->backoff)->toBe(60);
});
