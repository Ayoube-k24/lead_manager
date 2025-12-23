<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadBusinessRulesService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(LeadBusinessRulesService::class);
});

it('allows agent to receive lead when no daily limit is configured', function () {
    config(['lead-rules.distribution.max_leads_per_agent_per_day' => null]);

    $agent = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id]);

    expect($this->service->canAgentReceiveLeadToday($agent))->toBeTrue();
});

it('allows agent to receive lead when under daily limit', function () {
    config(['lead-rules.distribution.max_leads_per_agent_per_day' => 10]);

    $agent = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id]);

    // Create 5 leads assigned today
    Lead::factory()->count(5)->create([
        'assigned_to' => $agent->id,
        'updated_at' => now(),
    ]);

    expect($this->service->canAgentReceiveLeadToday($agent))->toBeTrue();
});

it('prevents agent from receiving lead when daily limit is reached', function () {
    config(['lead-rules.distribution.max_leads_per_agent_per_day' => 5]);

    $agent = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id]);

    // Create 5 leads assigned today (at the limit)
    Lead::factory()->count(5)->create([
        'assigned_to' => $agent->id,
        'updated_at' => now(),
    ]);

    expect($this->service->canAgentReceiveLeadToday($agent))->toBeFalse();
});

it('counts only today leads for daily limit', function () {
    config(['lead-rules.distribution.max_leads_per_agent_per_day' => 5]);

    $agent = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id]);

    // Create 3 leads from yesterday
    Lead::factory()->count(3)->create([
        'assigned_to' => $agent->id,
        'updated_at' => now()->subDay(),
    ]);

    // Create 2 leads from today
    Lead::factory()->count(2)->create([
        'assigned_to' => $agent->id,
        'updated_at' => now(),
    ]);

    expect($this->service->getTodayLeadsCount($agent))->toBe(2);
    expect($this->service->canAgentReceiveLeadToday($agent))->toBeTrue();
});

it('calculates pending leads count correctly', function () {
    $agent = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id]);

    // Create untreated leads
    Lead::factory()->count(3)->create([
        'assigned_to' => $agent->id,
        'status' => 'pending_call',
    ]);

    Lead::factory()->count(2)->create([
        'assigned_to' => $agent->id,
        'status' => 'email_confirmed',
    ]);

    // Create treated leads (should not be counted)
    Lead::factory()->count(2)->create([
        'assigned_to' => $agent->id,
        'status' => 'confirmed',
    ]);

    expect($this->service->getPendingLeadsCount($agent))->toBe(5);
});

it('checks if agent has reached pending limit', function () {
    config(['lead-rules.distribution.max_pending_leads_per_agent' => 5]);

    $agent = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent'])->id]);

    // Create 4 pending leads (under limit)
    Lead::factory()->count(4)->create([
        'assigned_to' => $agent->id,
        'status' => 'pending_call',
    ]);

    expect($this->service->hasReachedPendingLimit($agent))->toBeFalse();

    // Add one more (at limit)
    Lead::factory()->create([
        'assigned_to' => $agent->id,
        'status' => 'pending_call',
    ]);

    expect($this->service->hasReachedPendingLimit($agent))->toBeTrue();
});

it('filters available agents by business rules', function () {
    config(['lead-rules.distribution.max_leads_per_agent_per_day' => 5]);

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

    // Agent1 has reached daily limit
    Lead::factory()->count(5)->create([
        'assigned_to' => $agent1->id,
        'call_center_id' => $callCenter->id,
        'updated_at' => now(),
    ]);

    $lead = Lead::factory()->create(['call_center_id' => $callCenter->id]);
    $agents = collect([$agent1, $agent2]);

    $availableAgents = $this->service->getAvailableAgents($lead, $agents);

    expect($availableAgents->count())->toBe(1);
    expect($availableAgents->first()->id)->toBe($agent2->id);
});
