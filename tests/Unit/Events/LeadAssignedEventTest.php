<?php

declare(strict_types=1);

use App\Events\LeadAssigned;
use App\Models\Lead;
use App\Models\User;

describe('LeadAssigned Event', function () {
    test('contains lead and agent instances', function () {
        $lead = Lead::factory()->create();
        $agent = User::factory()->create();
        $event = new LeadAssigned($lead, $agent);

        expect($event->lead)->toBeInstanceOf(Lead::class)
            ->and($event->agent)->toBeInstanceOf(User::class)
            ->and($event->lead->id)->toBe($lead->id)
            ->and($event->agent->id)->toBe($agent->id);
    });
});
