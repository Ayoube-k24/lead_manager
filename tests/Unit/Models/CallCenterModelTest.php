<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\User;

describe('CallCenter Model', function () {
    describe('Relations', function () {
        test('belongs to owner', function () {
            $owner = User::factory()->create();
            $callCenter = CallCenter::factory()->create(['owner_id' => $owner->id]);

            expect($callCenter->owner)->not->toBeNull()
                ->and($callCenter->owner->id)->toBe($owner->id);
        });

        test('has many users', function () {
            $callCenter = CallCenter::factory()->create();
            User::factory()->count(5)->create(['call_center_id' => $callCenter->id]);

            expect($callCenter->users->count())->toBe(5);
        });

        test('has many leads', function () {
            $callCenter = CallCenter::factory()->create();
            Lead::factory()->count(10)->create(['call_center_id' => $callCenter->id]);

            expect($callCenter->leads->count())->toBe(10);
        });

        test('has many forms', function () {
            $callCenter = CallCenter::factory()->create();
            Form::factory()->count(3)->create(['call_center_id' => $callCenter->id]);

            expect($callCenter->forms->count())->toBe(3);
        });
    });

    describe('Casts', function () {
        test('casts is_active to boolean', function () {
            $callCenter = CallCenter::factory()->create(['is_active' => 1]);

            expect($callCenter->is_active)->toBeTrue();
        });
    });

    describe('Distribution Methods', function () {
        test('can have round_robin distribution method', function () {
            $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);

            expect($callCenter->distribution_method)->toBe('round_robin');
        });

        test('can have weighted distribution method', function () {
            $callCenter = CallCenter::factory()->create(['distribution_method' => 'weighted']);

            expect($callCenter->distribution_method)->toBe('weighted');
        });

        test('can have manual distribution method', function () {
            $callCenter = CallCenter::factory()->create(['distribution_method' => 'manual']);

            expect($callCenter->distribution_method)->toBe('manual');
        });
    });
});






