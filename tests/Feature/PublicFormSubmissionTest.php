<?php

declare(strict_types=1);

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;

test('form can be submitted publicly', function () {
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

    $response = $this->postJson(route('forms.submit', $form), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'lead_id',
        ]);

    expect(Lead::where('form_id', $form->id)->count())->toBe(1);
});

test('form submission validates required fields', function () {
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

    $response = $this->postJson(route('forms.submit', $form), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('inactive form cannot be submitted', function () {
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

    $response->assertStatus(403);
});

test('form without smtp profile cannot be submitted', function () {
    $emailTemplate = EmailTemplate::factory()->create();

    $form = Form::factory()->create([
        'is_active' => true,
        'smtp_profile_id' => null,
        'email_template_id' => $emailTemplate->id,
    ]);

    $response = $this->postJson(route('forms.submit', $form), [
        'email' => 'test@example.com',
    ]);

    $response->assertStatus(400);
});
