<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadDistributionService;

beforeEach(function () {
    require_once __DIR__.'/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

test('round robin distribution assigns leads evenly', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent1 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $agent2 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);

    $service = app(LeadDistributionService::class);

    // Create leads and distribute them
    $lead1 = Lead::factory()->create(['call_center_id' => $callCenter->id, 'status' => 'email_confirmed']);
    $lead2 = Lead::factory()->create(['call_center_id' => $callCenter->id, 'status' => 'email_confirmed']);

    $assignedAgent1 = $service->distributeLead($lead1);
    $assignedAgent2 = $service->distributeLead($lead2);

    expect($assignedAgent1)->not->toBeNull()
        ->and($assignedAgent2)->not->toBeNull()
        ->and($assignedAgent1->id)->toBeIn([$agent1->id, $agent2->id])
        ->and($assignedAgent2->id)->toBeIn([$agent1->id, $agent2->id]);
});

test('manual assignment works correctly', function () {
    $callCenter = CallCenter::factory()->create();
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $lead = Lead::factory()->create(['call_center_id' => $callCenter->id, 'status' => 'email_confirmed']);

    $service = app(LeadDistributionService::class);

    $result = $service->assignToAgent($lead, $agent);

    expect($result)->toBeTrue();
    $lead->refresh();
    expect($lead->assigned_to)->toBe($agent->id);
});

test('cannot assign lead to agent from different call center', function () {
    $callCenter1 = CallCenter::factory()->create();
    $callCenter2 = CallCenter::factory()->create();
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter1->id]);
    $lead = Lead::factory()->create(['call_center_id' => $callCenter2->id]);

    $service = app(LeadDistributionService::class);

    $result = $service->assignToAgent($lead, $agent);

    expect($result)->toBeFalse();
});

test('observer automatically distributes lead when status changes to email_confirmed in round_robin mode', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $smtpProfile = \App\Models\SmtpProfile::factory()->create();
    $emailTemplate = \App\Models\EmailTemplate::factory()->create();
    $form = \App\Models\Form::factory()->create([
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

    $agent = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $smtpProfile = \App\Models\SmtpProfile::factory()->create();
    $emailTemplate = \App\Models\EmailTemplate::factory()->create();
    $form = \App\Models\Form::factory()->create([
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
    $smtpProfile = \App\Models\SmtpProfile::factory()->create();
    $emailTemplate = \App\Models\EmailTemplate::factory()->create();
    $form = \App\Models\Form::factory()->create([
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
    $smtpProfile = \App\Models\SmtpProfile::factory()->create();
    $emailTemplate = \App\Models\EmailTemplate::factory()->create();
    $form = \App\Models\Form::factory()->create([
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

test('distributeLead returns null for manual mode', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'manual']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $lead = Lead::factory()->create(['call_center_id' => $callCenter->id, 'status' => 'email_confirmed']);

    $service = app(LeadDistributionService::class);

    $agent = $service->distributeLead($lead);

    expect($agent)->toBeNull();
});

test('distributeLead works for round_robin mode', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $lead = Lead::factory()->create(['call_center_id' => $callCenter->id, 'status' => 'email_confirmed']);

    $service = app(LeadDistributionService::class);

    $assignedAgent = $service->distributeLead($lead);

    expect($assignedAgent)->not->toBeNull()
        ->and($assignedAgent->id)->toBe($agent->id);
});

test('distributeLead works for weighted mode', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'weighted']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $lead = Lead::factory()->create(['call_center_id' => $callCenter->id, 'status' => 'email_confirmed']);

    $service = app(LeadDistributionService::class);

    $assignedAgent = $service->distributeLead($lead);

    expect($assignedAgent)->not->toBeNull()
        ->and($assignedAgent->id)->toBe($agent->id);
});
