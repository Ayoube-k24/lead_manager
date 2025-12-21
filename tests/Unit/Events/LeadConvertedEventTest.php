<?php

declare(strict_types=1);

use App\Events\LeadConverted;
use App\Models\Lead;

describe('LeadConverted Event', function () {
    test('contains lead instance', function () {
        $lead = Lead::factory()->create();
        $event = new LeadConverted($lead);

        expect($event->lead)->toBeInstanceOf(Lead::class)
            ->and($event->lead->id)->toBe($lead->id);
    });
});






