<?php

declare(strict_types=1);

use App\Events\LeadEmailConfirmed;
use App\Models\Lead;

describe('LeadEmailConfirmed Event', function () {
    test('contains lead instance', function () {
        $lead = Lead::factory()->create();
        $event = new LeadEmailConfirmed($lead);

        expect($event->lead)->toBeInstanceOf(Lead::class)
            ->and($event->lead->id)->toBe($lead->id);
    });
});

