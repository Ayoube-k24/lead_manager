<?php

declare(strict_types=1);

use App\Events\LeadStatusUpdated;
use App\Models\Lead;

describe('LeadStatusUpdated Event', function () {
    test('contains lead and status information', function () {
        $lead = Lead::factory()->create();
        $event = new LeadStatusUpdated($lead, 'pending_email', 'confirmed');

        expect($event->lead)->toBeInstanceOf(Lead::class)
            ->and($event->oldStatus)->toBe('pending_email')
            ->and($event->newStatus)->toBe('confirmed');
    });
});






