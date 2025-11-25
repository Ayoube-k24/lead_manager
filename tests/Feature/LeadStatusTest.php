<?php

declare(strict_types=1);

use App\LeadStatus;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

test('quote sent status can be set after call', function () {
    $lead = Lead::factory()->create([
        'status' => 'pending_call',
    ]);

    $lead->updateAfterCall(LeadStatus::QuoteSent, 'Devis envoyé au prospect');

    $lead->refresh();

    expect($lead->status)->toBe('quote_sent')
        ->and($lead->call_comment)->toBe('Devis envoyé au prospect')
        ->and($lead->called_at)->not->toBeNull();
});

test('quote sent status is active', function () {
    $lead = Lead::factory()->create([
        'status' => 'quote_sent',
    ]);

    expect($lead->isActive())->toBeTrue()
        ->and($lead->getStatusEnum())->toBe(LeadStatus::QuoteSent);
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
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );

    $agent = User::factory()->create(['role_id' => $agentRole->id]);
    $lead = Lead::factory()->create([
        'status' => 'pending_call',
        'assigned_to' => $agent->id,
    ]);

    $lead->updateAfterCall('quote_sent', 'Devis envoyé');

    $lead->refresh();

    expect($lead->status)->toBe('quote_sent')
        ->and($lead->getStatusEnum())->toBe(LeadStatus::QuoteSent);
});
