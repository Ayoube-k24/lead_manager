<?php

declare(strict_types=1);

use App\LeadStatus;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    // Ensure statuses are seeded
    if (\App\Models\LeadStatus::count() === 0) {
        \Artisan::call('db:seed', ['--class' => 'LeadStatusSeeder']);
    }
});

test('quote sent status can be set after call', function () {
    $pendingCallStatus = \App\Models\LeadStatus::where('slug', 'pending_call')->first();
    $quoteSentStatus = \App\Models\LeadStatus::where('slug', 'quote_sent')->first();

    $lead = Lead::factory()->create([
        'status_id' => $pendingCallStatus->id,
        'status' => 'pending_call',
    ]);

    $lead->updateAfterCall('quote_sent', 'Devis envoyé au prospect');

    $lead->refresh();

    expect($lead->status)->toBe('quote_sent')
        ->and($lead->status_id)->toBe($quoteSentStatus->id)
        ->and($lead->call_comment)->toBe('Devis envoyé au prospect')
        ->and($lead->called_at)->not->toBeNull();
});

test('quote sent status is active', function () {
    $quoteSentStatus = \App\Models\LeadStatus::firstOrCreate(
        ['slug' => 'quote_sent'],
        ['name' => 'Devis envoyé', 'color' => '#22D3EE', 'is_system' => true, 'is_active' => true]
    );

    $lead = Lead::factory()->create([
        'status_id' => $quoteSentStatus->id,
        'status' => 'quote_sent',
    ]);

    expect($lead->isActive())->toBeTrue();
});

test('quote sent status has correct label', function () {
    expect(LeadStatus::QuoteSent->label())->toBe('Devis envoyé')
        ->and(LeadStatus::QuoteSent->value)->toBe('quote_sent');
});

test('quote sent status has correct color class', function () {
    $colorClass = LeadStatus::QuoteSent->colorClass();

    expect($colorClass)->toContain('bg-cyan')
        ->and($colorClass)->toContain('text-cyan');
});

test('quote sent status is in post call statuses', function () {
    $postCallStatuses = LeadStatus::postCallStatuses();

    expect($postCallStatuses)->toContain(LeadStatus::QuoteSent);
});

test('quote sent status is in active statuses', function () {
    $activeStatuses = LeadStatus::activeStatuses();

    expect($activeStatuses)->toContain(LeadStatus::QuoteSent);
});

test('agent can update lead status to quote sent', function () {
    $pendingCallStatus = \App\Models\LeadStatus::firstOrCreate(
        ['slug' => 'pending_call'],
        ['name' => 'En file d\'appel', 'color' => '#FB923C', 'is_system' => true]
    );
    $quoteSentStatus = \App\Models\LeadStatus::firstOrCreate(
        ['slug' => 'quote_sent'],
        ['name' => 'Devis envoyé', 'color' => '#22D3EE', 'is_system' => true, 'can_be_set_after_call' => true]
    );

    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent = User::factory()->create(['role_id' => $agentRole->id]);
    $lead = Lead::factory()->create([
        'status_id' => $pendingCallStatus->id,
        'status' => 'pending_call',
        'assigned_to' => $agent->id,
    ]);

    $lead->updateAfterCall('quote_sent', 'Devis envoyé');

    $lead->refresh();

    expect($lead->status)->toBe('quote_sent')
        ->and($lead->status_id)->toBe($quoteSentStatus->id);
});
