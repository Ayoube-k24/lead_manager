<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\User;

test('call center can be created with required fields', function () {
    $owner = User::factory()->create();
    $callCenter = CallCenter::factory()->create([
        'name' => 'Test Call Center',
        'description' => 'Test Description',
        'owner_id' => $owner->id,
        'distribution_method' => 'round_robin',
        'is_active' => true,
    ]);

    expect($callCenter->name)->toBe('Test Call Center')
        ->and($callCenter->description)->toBe('Test Description')
        ->and($callCenter->owner_id)->toBe($owner->id)
        ->and($callCenter->distribution_method)->toBe('round_robin')
        ->and($callCenter->is_active)->toBeTrue();
});

test('call center has owner relationship', function () {
    $owner = User::factory()->create();
    $callCenter = CallCenter::factory()->create(['owner_id' => $owner->id]);

    expect($callCenter->owner)->not->toBeNull()
        ->and($callCenter->owner->id)->toBe($owner->id);
});

test('call center has users relationship', function () {
    $callCenter = CallCenter::factory()->create();
    $user1 = User::factory()->create(['call_center_id' => $callCenter->id]);
    $user2 = User::factory()->create(['call_center_id' => $callCenter->id]);

    expect($callCenter->users)->toHaveCount(2)
        ->and($callCenter->users->pluck('id')->toArray())->toContain($user1->id)
        ->and($callCenter->users->pluck('id')->toArray())->toContain($user2->id);
});

test('call center has leads relationship', function () {
    $callCenter = CallCenter::factory()->create();
    $lead1 = Lead::factory()->create(['call_center_id' => $callCenter->id]);
    $lead2 = Lead::factory()->create(['call_center_id' => $callCenter->id]);

    expect($callCenter->leads)->toHaveCount(2)
        ->and($callCenter->leads->pluck('id')->toArray())->toContain($lead1->id)
        ->and($callCenter->leads->pluck('id')->toArray())->toContain($lead2->id);
});

test('call center is_active is cast to boolean', function () {
    $callCenter = CallCenter::factory()->create(['is_active' => 1]);

    expect($callCenter->is_active)->toBeTrue()
        ->and(is_bool($callCenter->is_active))->toBeTrue();
});
