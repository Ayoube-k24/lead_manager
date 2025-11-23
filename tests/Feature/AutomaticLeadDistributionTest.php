<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

test('observer automatically distributes lead in round_robin mode when email is confirmed', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $smtpProfile = SmtpProfile::factory()->create();
    $emailTemplate = EmailTemplate::factory()->create();
    $form = Form::factory()->create([
        'call_center_id' => $callCenter->id,
        'smtp_profile_id' => $smtpProfile->id,
        'email_template_id' => $emailTemplate->id,
    ]);

    $lead = Lead::factory()->create([
        'form_id' => $form->id,
        'call_center_id' => $callCenter->id,
        'status' => 'pending_email',
        'assigned_to' => null,
    ]);

    // Confirm email - Observer should automatically distribute
    $lead->confirmEmail();

    $lead->refresh();

    expect($lead->assigned_to)->toBe($agent->id)
        ->and($lead->status)->toBe('pending_call');
});

test('observer does not distribute lead in manual mode', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'manual']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $smtpProfile = SmtpProfile::factory()->create();
    $emailTemplate = EmailTemplate::factory()->create();
    $form = Form::factory()->create([
        'call_center_id' => $callCenter->id,
        'smtp_profile_id' => $smtpProfile->id,
        'email_template_id' => $emailTemplate->id,
    ]);

    $lead = Lead::factory()->create([
        'form_id' => $form->id,
        'call_center_id' => $callCenter->id,
        'status' => 'pending_email',
        'assigned_to' => null,
    ]);

    // Confirm email - Observer should NOT distribute in manual mode
    $lead->confirmEmail();

    $lead->refresh();

    expect($lead->assigned_to)->toBeNull()
        ->and($lead->status)->toBe('email_confirmed');
});

test('observer automatically distributes lead in weighted mode', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'weighted']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $smtpProfile = SmtpProfile::factory()->create();
    $emailTemplate = EmailTemplate::factory()->create();
    $form = Form::factory()->create([
        'call_center_id' => $callCenter->id,
        'smtp_profile_id' => $smtpProfile->id,
        'email_template_id' => $emailTemplate->id,
    ]);

    $lead = Lead::factory()->create([
        'form_id' => $form->id,
        'call_center_id' => $callCenter->id,
        'status' => 'pending_email',
        'assigned_to' => null,
    ]);

    // Confirm email - Observer should automatically distribute
    $lead->confirmEmail();

    $lead->refresh();

    expect($lead->assigned_to)->toBe($agent->id)
        ->and($lead->status)->toBe('pending_call');
});

test('observer sets call_center_id from form if not set', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $smtpProfile = SmtpProfile::factory()->create();
    $emailTemplate = EmailTemplate::factory()->create();
    $form = Form::factory()->create([
        'call_center_id' => $callCenter->id,
        'smtp_profile_id' => $smtpProfile->id,
        'email_template_id' => $emailTemplate->id,
    ]);

    $lead = Lead::factory()->create([
        'form_id' => $form->id,
        'call_center_id' => null, // No call center initially
        'status' => 'pending_email',
        'assigned_to' => null,
    ]);

    // Confirm email - Observer should set call_center_id from form and distribute
    $lead->confirmEmail();

    $lead->refresh();

    expect($lead->call_center_id)->toBe($callCenter->id)
        ->and($lead->assigned_to)->toBe($agent->id);
});

