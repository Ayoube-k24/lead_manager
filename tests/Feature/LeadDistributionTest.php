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

test('inactive agents are skipped during distribution', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $activeAgent = User::factory()->create([
        'role_id' => $agentRole->id,
        'call_center_id' => $callCenter->id,
        'is_active' => true,
    ]);
    $inactiveAgent = User::factory()->create([
        'role_id' => $agentRole->id,
        'call_center_id' => $callCenter->id,
        'is_active' => false,
    ]);

    $service = app(LeadDistributionService::class);
    $lead = Lead::factory()->create(['call_center_id' => $callCenter->id, 'status' => 'email_confirmed']);

    $assignedAgent = $service->distributeLead($lead);

    expect($assignedAgent)->not->toBeNull()
        ->and($assignedAgent->id)->toBe($activeAgent->id);
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

test('round_robin distributes leads evenly across multiple agents', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent1 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $agent2 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $agent3 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);

    $service = app(LeadDistributionService::class);

    // Distribute 6 leads - should be evenly distributed (2 each)
    $leads = [];
    $assignments = [];

    for ($i = 0; $i < 6; $i++) {
        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        $assignedAgent = $service->distributeLead($lead);
        expect($assignedAgent)->not->toBeNull();

        if ($assignedAgent) {
            $service->assignToAgent($lead, $assignedAgent);
            $assignments[] = $assignedAgent->id;
        }
    }

    // Count assignments per agent
    $agent1Count = count(array_filter($assignments, fn ($id) => $id === $agent1->id));
    $agent2Count = count(array_filter($assignments, fn ($id) => $id === $agent2->id));
    $agent3Count = count(array_filter($assignments, fn ($id) => $id === $agent3->id));

    // Each agent should have at least 1 lead (with 6 leads and 3 agents, distribution should be relatively even)
    expect($agent1Count)->toBeGreaterThan(0)
        ->and($agent2Count)->toBeGreaterThan(0)
        ->and($agent3Count)->toBeGreaterThan(0)
        ->and($agent1Count + $agent2Count + $agent3Count)->toBe(6);
});

test('round_robin prefers agent with fewer pending leads', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent1 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $agent2 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);

    // Give agent1 some pending leads
    Lead::factory()->count(3)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent1->id,
        'status' => 'pending_call',
    ]);

    // agent2 has no leads
    $service = app(LeadDistributionService::class);

    $newLead = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'status' => 'email_confirmed',
        'assigned_to' => null,
    ]);

    $assignedAgent = $service->distributeLead($newLead);

    // Should assign to agent2 (has fewer leads)
    expect($assignedAgent)->not->toBeNull()
        ->and($assignedAgent->id)->toBe($agent2->id);
});

test('weighted distribution prefers agent with lower performance score', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'weighted']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent1 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $agent2 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);

    // Agent1 has high performance (5 confirmed out of 10 total = 0.5 score)
    Lead::factory()->count(5)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent1->id,
        'status' => 'confirmed',
    ]);
    Lead::factory()->count(5)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent1->id,
        'status' => 'rejected',
    ]);

    // Agent2 has lower performance (2 confirmed out of 10 total = 0.2 score)
    Lead::factory()->count(2)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent2->id,
        'status' => 'confirmed',
    ]);
    Lead::factory()->count(8)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent2->id,
        'status' => 'rejected',
    ]);

    $service = app(LeadDistributionService::class);

    $newLead = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'status' => 'email_confirmed',
        'assigned_to' => null,
    ]);

    $assignedAgent = $service->distributeLead($newLead);

    // Should assign to agent2 (lower performance score, needs more leads)
    expect($assignedAgent)->not->toBeNull()
        ->and($assignedAgent->id)->toBe($agent2->id);
});

test('weighted distribution considers workload when scores are similar', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'weighted']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent1 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $agent2 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);

    // Both agents have same performance (no leads = 0.5 default score)
    // But agent1 has more pending workload
    Lead::factory()->count(3)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent1->id,
        'status' => 'pending_call',
    ]);

    $service = app(LeadDistributionService::class);

    $newLead = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'status' => 'email_confirmed',
        'assigned_to' => null,
    ]);

    $assignedAgent = $service->distributeLead($newLead);

    // Should assign to agent2 (same score but lower workload)
    expect($assignedAgent)->not->toBeNull()
        ->and($assignedAgent->id)->toBe($agent2->id);
});

test('distribution method is correctly respected in round_robin mode', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent1 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $agent2 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);

    // Create leads with different statuses to test round_robin logic
    Lead::factory()->count(2)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent1->id,
        'status' => 'pending_call',
    ]);

    $service = app(LeadDistributionService::class);

    $newLead = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'status' => 'email_confirmed',
        'assigned_to' => null,
    ]);

    $assignedAgent = $service->distributeLead($newLead);

    // Round robin should assign to agent2 (has fewer pending leads)
    expect($assignedAgent)->not->toBeNull()
        ->and($assignedAgent->id)->toBe($agent2->id);
});

test('distribution method is correctly respected in weighted mode', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'weighted']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent1 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $agent2 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);

    // Agent1 has better performance
    Lead::factory()->count(8)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent1->id,
        'status' => 'confirmed',
    ]);
    Lead::factory()->count(2)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent1->id,
        'status' => 'rejected',
    ]);

    // Agent2 has worse performance
    Lead::factory()->count(3)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent2->id,
        'status' => 'confirmed',
    ]);
    Lead::factory()->count(7)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent2->id,
        'status' => 'rejected',
    ]);

    $service = app(LeadDistributionService::class);

    $newLead = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'status' => 'email_confirmed',
        'assigned_to' => null,
    ]);

    $assignedAgent = $service->distributeLead($newLead);

    // Weighted should assign to agent2 (lower performance score)
    expect($assignedAgent)->not->toBeNull()
        ->and($assignedAgent->id)->toBe($agent2->id);
});

test('manual mode does not auto-distribute even when agents are available', function () {
    $callCenter = CallCenter::factory()->create(['distribution_method' => 'manual']);
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter->id]);
    $lead = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'status' => 'email_confirmed',
        'assigned_to' => null,
    ]);

    $service = app(LeadDistributionService::class);

    $assignedAgent = $service->distributeLead($lead);

    // Should return null in manual mode
    expect($assignedAgent)->toBeNull();
    $lead->refresh();
    expect($lead->assigned_to)->toBeNull();
});
