<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\User;
use App\Services\StatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure roles exist
    \App\Models\Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
    \App\Models\Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
    \App\Models\Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

    $this->statisticsService = app(StatisticsService::class);
});

it('calculates global statistics correctly', function () {
    // Create test data
    Lead::factory()->count(10)->create(['status' => 'confirmed']);
    Lead::factory()->count(5)->create(['status' => 'rejected']);
    Lead::factory()->count(3)->create(['status' => 'pending_email']);

    $stats = $this->statisticsService->getGlobalStatistics();

    expect($stats)
        ->toHaveKey('total_leads')
        ->toHaveKey('confirmed_leads')
        ->toHaveKey('rejected_leads')
        ->toHaveKey('pending_leads')
        ->toHaveKey('conversion_rate')
        ->toHaveKey('avg_processing_time')
        ->toHaveKey('leads_by_status')
        ->toHaveKey('leads_over_time');

    expect($stats['total_leads'])->toBe(18);
    expect($stats['confirmed_leads'])->toBe(10);
    expect($stats['rejected_leads'])->toBe(5);
    expect($stats['pending_leads'])->toBe(3);
    expect($stats['conversion_rate'])->toBeGreaterThan(0);
});

it('calculates call center statistics correctly', function () {
    $callCenter = CallCenter::factory()->create();
    $agent = User::factory()->create([
        'call_center_id' => $callCenter->id,
    ]);
    $agent->role()->associate(\App\Models\Role::where('slug', 'agent')->first());
    $agent->save();

    Lead::factory()->count(5)->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent->id,
        'status' => 'confirmed',
    ]);
    Lead::factory()->count(2)->create([
        'call_center_id' => $callCenter->id,
        'status' => 'rejected',
    ]);

    $stats = $this->statisticsService->getCallCenterStatistics($callCenter);

    expect($stats)
        ->toHaveKey('call_center')
        ->toHaveKey('total_leads')
        ->toHaveKey('confirmed_leads')
        ->toHaveKey('conversion_rate')
        ->toHaveKey('agent_performance');

    expect($stats['total_leads'])->toBe(7);
    expect($stats['confirmed_leads'])->toBe(5);
    expect($stats['call_center']->id)->toBe($callCenter->id);
});

it('calculates agent statistics correctly', function () {
    $agent = User::factory()->create();
    $agent->role()->associate(\App\Models\Role::where('slug', 'agent')->first());
    $agent->save();

    Lead::factory()->count(8)->create([
        'assigned_to' => $agent->id,
        'status' => 'confirmed',
    ]);
    Lead::factory()->count(2)->create([
        'assigned_to' => $agent->id,
        'status' => 'rejected',
    ]);

    $stats = $this->statisticsService->getAgentStatistics($agent);

    expect($stats)
        ->toHaveKey('agent')
        ->toHaveKey('total_leads')
        ->toHaveKey('confirmed_leads')
        ->toHaveKey('conversion_rate');

    expect($stats['total_leads'])->toBe(10);
    expect($stats['confirmed_leads'])->toBe(8);
    expect($stats['conversion_rate'])->toBe(80.0);
});

it('identifies leads needing attention', function () {
    $callCenter = CallCenter::factory()->create();

    // Create leads that need attention (confirmed email but not called)
    Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'status' => 'email_confirmed',
        'email_confirmed_at' => now()->subHours(50),
        'called_at' => null,
    ]);

    $leads = $this->statisticsService->getLeadsNeedingAttention($callCenter, 48);

    expect($leads)->not->toBeEmpty();
    expect($leads->first()->status)->toBeIn(['email_confirmed', 'pending_call']);
});

it('identifies underperforming agents', function () {
    $callCenter = CallCenter::factory()->create();
    $agent = User::factory()->create([
        'call_center_id' => $callCenter->id,
    ]);
    $agent->role()->associate(\App\Models\Role::where('slug', 'agent')->first());
    $agent->save();

    // Create agent with low conversion rate
    Lead::factory()->count(15)->create([
        'assigned_to' => $agent->id,
        'status' => 'confirmed',
    ]);
    Lead::factory()->count(10)->create([
        'assigned_to' => $agent->id,
        'status' => 'rejected',
    ]);

    $underperforming = $this->statisticsService->getUnderperformingAgents($callCenter, 20.0);

    // Agent has 15 confirmed out of 25 total = 60% conversion rate, so should not be underperforming
    expect($underperforming)->toBeEmpty();
});

it('calculates average processing time correctly', function () {
    $callCenter = CallCenter::factory()->create();
    $agent = User::factory()->create([
        'call_center_id' => $callCenter->id,
    ]);
    $agent->role()->associate(\App\Models\Role::where('slug', 'agent')->first());
    $agent->save();

    // Create leads with processing times
    $lead1 = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent->id,
        'status' => 'confirmed',
        'email_confirmed_at' => now()->subHours(10),
        'called_at' => now()->subHours(5),
    ]);

    $lead2 = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
        'assigned_to' => $agent->id,
        'status' => 'confirmed',
        'email_confirmed_at' => now()->subHours(20),
        'called_at' => now()->subHours(10),
    ]);

    $stats = $this->statisticsService->getCallCenterStatistics($callCenter);

    expect($stats['avg_processing_time'])->toBeGreaterThan(0);
});

it('returns empty collection when no leads need attention', function () {
    $callCenter = CallCenter::factory()->create();

    $leads = $this->statisticsService->getLeadsNeedingAttention($callCenter, 48);

    expect($leads)->toBeEmpty();
});

it('returns leads over time correctly', function () {
    $callCenter = CallCenter::factory()->create();

    // Create leads over the last 5 days
    for ($i = 0; $i < 5; $i++) {
        Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'created_at' => now()->subDays($i),
        ]);
    }

    $stats = $this->statisticsService->getCallCenterStatistics($callCenter);

    expect($stats['leads_over_time'])->toBeArray();
    expect(count($stats['leads_over_time']))->toBeGreaterThan(0);
});
