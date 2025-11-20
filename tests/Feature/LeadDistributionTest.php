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
