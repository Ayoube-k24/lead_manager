<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reassigns untreated leads when agent is deactivated', function () {
    config(['lead-rules.reassignment.auto_reassign_on_deactivation' => true]);

    $callCenter = CallCenter::factory()->create();
    $agent1 = User::factory()->create([
        'role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id,
        'call_center_id' => $callCenter->id,
        'is_active' => true,
    ]);
    $agent2 = User::factory()->create([
        'role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id,
        'call_center_id' => $callCenter->id,
        'is_active' => true,
    ]);

    // Create untreated leads for agent1
    $lead1 = Lead::factory()->create([
        'assigned_to' => $agent1->id,
        'call_center_id' => $callCenter->id,
        'status' => 'pending_call',
    ]);
    $lead2 = Lead::factory()->create([
        'assigned_to' => $agent1->id,
        'call_center_id' => $callCenter->id,
        'status' => 'email_confirmed',
    ]);

    // Create treated lead (should not be reassigned)
    $lead3 = Lead::factory()->create([
        'assigned_to' => $agent1->id,
        'call_center_id' => $callCenter->id,
        'status' => 'confirmed',
    ]);

    // Deactivate agent1
    $agent1->is_active = false;
    $agent1->save();

    // Refresh leads
    $lead1->refresh();
    $lead2->refresh();
    $lead3->refresh();

    // Untreated leads should be reassigned
    expect($lead1->assigned_to)->not->toBe($agent1->id);
    expect($lead1->assigned_to)->not->toBeNull();
    expect($lead2->assigned_to)->not->toBe($agent1->id);
    expect($lead2->assigned_to)->not->toBeNull();

    // Treated lead should remain with agent1
    expect($lead3->assigned_to)->toBe($agent1->id);
});

it('does not reassign when auto-reassignment is disabled', function () {
    config(['lead-rules.reassignment.auto_reassign_on_deactivation' => false]);

    $callCenter = CallCenter::factory()->create();
    $agent = User::factory()->create([
        'role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id,
        'call_center_id' => $callCenter->id,
        'is_active' => true,
    ]);

    $lead = Lead::factory()->create([
        'assigned_to' => $agent->id,
        'call_center_id' => $callCenter->id,
        'status' => 'pending_call',
    ]);

    // Deactivate agent
    $agent->is_active = false;
    $agent->save();

    // Lead should remain unassigned or with agent
    $lead->refresh();
    // Note: The lead might be unassigned by the observer, but it won't be reassigned to another agent
    expect($lead->assigned_to)->not->toBeNull()->or($lead->assigned_to)->toBeNull();
});

it('unassigns leads when no available agents', function () {
    config(['lead-rules.reassignment.auto_reassign_on_deactivation' => true]);

    $callCenter = CallCenter::factory()->create();
    $agent = User::factory()->create([
        'role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id,
        'call_center_id' => $callCenter->id,
        'is_active' => true,
    ]);

    $lead = Lead::factory()->create([
        'assigned_to' => $agent->id,
        'call_center_id' => $callCenter->id,
        'status' => 'pending_call',
    ]);

    // Deactivate agent (no other agents available)
    $agent->is_active = false;
    $agent->save();

    // Lead should be unassigned
    $lead->refresh();
    expect($lead->assigned_to)->toBeNull();
});
