<?php

declare(strict_types=1);

use App\Events\LeadCreated;
use App\Jobs\SendLeadConfirmationEmail;
use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

describe('PublicFormController - Submit', function () {
    test('creates lead and logs submission', function () {
        Queue::fake();
        Event::fake();

        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $callCenter = CallCenter::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
        ]);

        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201);

        // Verify lead was created
        $lead = Lead::where('form_id', $form->id)->first();
        expect($lead)->not->toBeNull();
    });

    test('rejects inactive form', function () {
        Queue::fake();

        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => false,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
        ]);

        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Ce formulaire n\'est pas actif.',
            ]);
    });

    test('extracts email from different field names', function () {
        Queue::fake();
        Event::fake();

        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $callCenter = CallCenter::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
            'fields' => [
                ['name' => 'email_address', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
        ]);

        $response = $this->postJson(route('forms.submit', $form), [
            'email_address' => 'test@example.com',
        ]);

        $response->assertStatus(201);

        $lead = Lead::where('form_id', $form->id)->first();
        expect($lead->email)->toBe('test@example.com');
    });

    test('associates call center id from form', function () {
        Queue::fake();
        Event::fake();

        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $callCenter = CallCenter::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
        ]);

        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201);

        $lead = Lead::where('form_id', $form->id)->first();
        expect($lead->call_center_id)->toBe($callCenter->id);
    });

    test('generates 64 character confirmation token', function () {
        Queue::fake();
        Event::fake();

        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $callCenter = CallCenter::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
        ]);

        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201);

        $lead = Lead::where('form_id', $form->id)->first();
        expect($lead->email_confirmation_token)->toBeString()
            ->toHaveLength(64);
    });

    test('sets token expiration to 24 hours', function () {
        Queue::fake();
        Event::fake();

        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $callCenter = CallCenter::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
        ]);

        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201);

        $lead = Lead::where('form_id', $form->id)->first();
        expect($lead->email_confirmation_token_expires_at)->not->toBeNull()
            ->and($lead->email_confirmation_token_expires_at->diffInHours(now()))->toBe(24);
    });

    test('dispatches LeadCreated event', function () {
        Queue::fake();
        Event::fake();

        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $callCenter = CallCenter::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
        ]);

        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(LeadCreated::class);
    });

    test('dispatches SendLeadConfirmationEmail job', function () {
        Queue::fake();
        Event::fake();

        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $callCenter = CallCenter::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
        ]);

        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201);

        Queue::assertPushed(SendLeadConfirmationEmail::class);
    });

    test('creates lead with pending_email status', function () {
        Queue::fake();
        Event::fake();

        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $callCenter = CallCenter::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
        ]);

        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201);

        $lead = Lead::where('form_id', $form->id)->first();
        expect($lead->status)->toBe('pending_email');
    });

    test('includes CORS headers in response for form submission', function () {
        Queue::fake();
        Event::fake();

        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $callCenter = CallCenter::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
        ]);

        $response = $this->withHeaders([
            'Origin' => 'https://external-landing-page.com',
        ])->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201)
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->assertHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With');
    });

    test('handles CORS preflight OPTIONS request', function () {
        $form = Form::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Origin' => 'https://external-landing-page.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ])->options(route('forms.submit', $form));

        $response->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->assertHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With')
            ->assertHeader('Access-Control-Max-Age', '86400');
    });
});
