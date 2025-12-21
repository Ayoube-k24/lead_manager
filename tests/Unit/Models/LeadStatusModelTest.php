<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadStatus;

describe('LeadStatus Model', function () {
    describe('Relations', function () {
        test('has many leads', function () {
            $status = LeadStatus::factory()->create();
            Lead::factory()->count(5)->create(['status_id' => $status->id]);

            expect($status->leads->count())->toBe(5);
        });
    });

    describe('Scopes', function () {
        test('active scope returns only active statuses', function () {
            LeadStatus::factory()->create(['is_active' => true]);
            LeadStatus::factory()->create(['is_active' => false]);
            LeadStatus::factory()->create(['is_active' => true]);

            $active = LeadStatus::active()->get();

            expect($active->count())->toBe(2)
                ->and($active->every(fn ($s) => $s->is_active))->toBeTrue();
        });

        test('final scope returns only final statuses', function () {
            LeadStatus::factory()->create(['is_final' => true]);
            LeadStatus::factory()->create(['is_final' => false]);

            $final = LeadStatus::final()->get();

            expect($final->count())->toBe(1)
                ->and($final->first()->is_final)->toBeTrue();
        });

        test('postCall scope returns post-call statuses', function () {
            LeadStatus::factory()->create(['can_be_set_after_call' => true]);
            LeadStatus::factory()->create(['can_be_set_after_call' => false]);

            $postCall = LeadStatus::postCall()->get();

            expect($postCall->count())->toBe(1);
        });
    });

    describe('Static Methods', function () {
        test('getBySlug returns status by slug', function () {
            $status = LeadStatus::factory()->create(['slug' => 'test_status']);

            $found = LeadStatus::getBySlug('test_status');

            expect($found)->not->toBeNull()
                ->and($found->id)->toBe($status->id);
        });

        test('getActiveStatuses returns active statuses', function () {
            LeadStatus::factory()->create(['is_active' => true]);
            LeadStatus::factory()->create(['is_active' => false]);

            $active = LeadStatus::getActiveStatuses();

            expect($active->every(fn ($s) => $s->is_active))->toBeTrue();
        });
    });

    describe('Methods', function () {
        test('canBeDeleted returns false for system statuses', function () {
            $systemStatus = LeadStatus::factory()->create(['is_system' => true]);
            $customStatus = LeadStatus::factory()->create(['is_system' => false]);

            expect($systemStatus->canBeDeleted())->toBeFalse()
                ->and($customStatus->canBeDeleted())->toBeTrue();
        });

        test('isActiveStatus returns is_active value', function () {
            $status = LeadStatus::factory()->create(['is_active' => true]);

            expect($status->isActiveStatus())->toBeTrue();
        });

        test('isFinalStatus returns is_final value', function () {
            $status = LeadStatus::factory()->create(['is_final' => true]);

            expect($status->isFinalStatus())->toBeTrue();
        });
    });

    describe('Casts', function () {
        test('casts boolean fields correctly', function () {
            $status = LeadStatus::factory()->create([
                'is_system' => 1,
                'is_active' => 1,
                'is_final' => 1,
                'can_be_set_after_call' => 1,
            ]);

            expect($status->is_system)->toBeTrue()
                ->and($status->is_active)->toBeTrue()
                ->and($status->is_final)->toBeTrue()
                ->and($status->can_be_set_after_call)->toBeTrue();
        });

        test('casts order to integer', function () {
            $status = LeadStatus::factory()->create(['order' => '10']);

            expect($status->order)->toBeInt()
                ->and($status->order)->toBe(10);
        });
    });
});






