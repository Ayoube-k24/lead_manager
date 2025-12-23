<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadDistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(LeadDistributionService::class);
});

it('reassigns untreated leads to specific agent', function () {
    $callCenter = CallCenter::factory()->create();
    $fromAgent = User::factory()->create([
        'role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id,
        'call_center_id' => $callCenter->id,
        'is_active' => true,
    ]);
    $toAgent = User::factory()->create([
        'role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id,
        'call_center_id' => $callCenter->id,
        'is_active' => true,
    ]);

    $lead1 = Lead::factory()->create([
        'assigned_to' => $fromAgent->id,
        'call_center_id' => $callCenter->id,
        'status' => 'pending_call',
    ]);
    $lead2 = Lead::factory()->create([
        'assigned_to' => $fromAgent->id,
        'call_center_id' => $callCenter->id,
        'status' => 'email_confirmed',
    ]);

    $result = $this->service->reassignUntreatedLeads($fromAgent, $toAgent, $callCenter->id);

    expect($result['reassigned'])->toBe(2);
    expect($result['failed'])->toBe(0);

    $lead1->refresh();
    $lead2->refresh();

    expect($lead1->assigned_to)->toBe($toAgent->id);
    expect($lead2->assigned_to)->toBe($toAgent->id);
});

it('auto-distributes untreated leads when no target agent specified', function () {
    $callCenter = CallCenter::factory()->create();
    $callCenter->update(['distribution_method' => 'round_robin']);

    $fromAgent = User::factory()->create([
        'role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id,
        'call_center_id' => $callCenter->id,
        'is_active' => true,
    ]);
    $toAgent = User::factory()->create([
        'role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id,
        'call_center_id' => $callCenter->id,
        'is_active' => true,
    ]);

    $lead = Lead::factory()->create([
        'assigned_to' => $fromAgent->id,
        'call_center_id' => $callCenter->id,
        'status' => 'pending_call',
    ]);

    $result = $this->service->reassignUntreatedLeads($fromAgent, null, $callCenter->id);

    expect($result['reassigned'])->toBeGreaterThan(0);

    $lead->refresh();
    expect($lead->assigned_to)->not->toBe($fromAgent->id);
    expect($lead->assigned_to)->not->toBeNull();
});

it('reassigns multiple leads in bulk', function () {
    $callCenter = CallCenter::factory()->create();
    $toAgent = User::factory()->create([
        'role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id,
        'call_center_id' => $callCenter->id,
        'is_active' => true,
    ]);

    $lead1 = Lead::factory()->create(['call_center_id' => $callCenter->id]);
    $lead2 = Lead::factory()->create(['call_center_id' => $callCenter->id]);
    $lead3 = Lead::factory()->create(['call_center_id' => $callCenter->id]);

    $result = $this->service->reassignLeads([$lead1->id, $lead2->id, $lead3->id], $toAgent);

    expect($result['reassigned'])->toBe(3);
    expect($result['failed'])->toBe(0);

    $lead1->refresh();
    $lead2->refresh();
    $lead3->refresh();

    expect($lead1->assigned_to)->toBe($toAgent->id);
    expect($lead2->assigned_to)->toBe($toAgent->id);
    expect($lead3->assigned_to)->toBe($toAgent->id);
});
