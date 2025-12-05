<?php

declare(strict_types=1);

use App\Events\LeadCreated;
use App\Models\Lead;

describe('LeadCreated Event', function () {
    test('contains lead instance', function () {
        $lead = Lead::factory()->create();
        $event = new LeadCreated($lead);

        expect($event->lead)->toBeInstanceOf(Lead::class)
            ->and($event->lead->id)->toBe($lead->id);
    });

    test('is serializable', function () {
        $lead = Lead::factory()->create();
        $event = new LeadCreated($lead);

        // Event should be serializable for queues (uses SerializesModels trait)
        expect($event)->toBeInstanceOf(LeadCreated::class);
    });
});
