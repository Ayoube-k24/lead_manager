<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadStatus;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('LeadStatus Model - Basic Properties', function () {
    test('can create a lead status', function () {
        $status = LeadStatus::create([
            'slug' => 'test_status',
            'name' => 'Test Status',
            'color' => '#FF0000',
            'description' => 'Test description',
            'is_system' => false,
            'is_active' => true,
            'is_final' => false,
            'can_be_set_after_call' => true,
            'order' => 0,
        ]);

        expect($status)
            ->toBeInstanceOf(LeadStatus::class)
            ->and($status->slug)->toBe('test_status')
            ->and($status->name)->toBe('Test Status')
            ->and($status->color)->toBe('#FF0000')
            ->and($status->description)->toBe('Test description')
            ->and($status->is_system)->toBeFalse()
            ->and($status->is_active)->toBeTrue()
            ->and($status->is_final)->toBeFalse()
            ->and($status->can_be_set_after_call)->toBeTrue();
    });

    test('slug must be unique', function () {
        LeadStatus::create([
            'slug' => 'unique_status',
            'name' => 'Unique Status',
            'color' => '#FF0000',
        ]);

        expect(fn () => LeadStatus::create([
            'slug' => 'unique_status',
            'name' => 'Another Status',
            'color' => '#00FF00',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('can get label', function () {
        $status = LeadStatus::create([
            'slug' => 'test_label',
            'name' => 'Test Label',
            'color' => '#FF0000',
        ]);

        expect($status->getLabel())->toBe('Test Label');
    });

    test('can get color class', function () {
        $status = LeadStatus::create([
            'slug' => 'test_color',
            'name' => 'Test Color',
            'color' => '#FCD34D',
        ]);

        $colorClass = $status->getColorClass();
        expect($colorClass)->toBeString()->not->toBeEmpty();
    });
});

describe('LeadStatus Model - Status Checks', function () {
    test('isActiveStatus returns true for active status', function () {
        $status = LeadStatus::create([
            'slug' => 'active_status',
            'name' => 'Active Status',
            'color' => '#FF0000',
            'is_active' => true,
        ]);

        expect($status->isActiveStatus())->toBeTrue();
    });

    test('isActiveStatus returns false for inactive status', function () {
        $status = LeadStatus::create([
            'slug' => 'inactive_status',
            'name' => 'Inactive Status',
            'color' => '#FF0000',
            'is_active' => false,
        ]);

        expect($status->isActiveStatus())->toBeFalse();
    });

    test('isFinalStatus returns true for final status', function () {
        $status = LeadStatus::create([
            'slug' => 'final_status',
            'name' => 'Final Status',
            'color' => '#FF0000',
            'is_final' => true,
        ]);

        expect($status->isFinalStatus())->toBeTrue();
    });

    test('canBeSetAfterCallStatus returns true when allowed', function () {
        $status = LeadStatus::create([
            'slug' => 'post_call_status',
            'name' => 'Post Call Status',
            'color' => '#FF0000',
            'can_be_set_after_call' => true,
        ]);

        expect($status->canBeSetAfterCallStatus())->toBeTrue();
    });
});

describe('LeadStatus Model - Scopes', function () {
    test('active scope returns only active statuses', function () {
        LeadStatus::create([
            'slug' => 'active1',
            'name' => 'Active 1',
            'color' => '#FF0000',
            'is_active' => true,
        ]);

        LeadStatus::create([
            'slug' => 'inactive1',
            'name' => 'Inactive 1',
            'color' => '#00FF00',
            'is_active' => false,
        ]);

        $activeStatuses = LeadStatus::active()->get();

        expect($activeStatuses)->toHaveCount(1)
            ->and($activeStatuses->first()->slug)->toBe('active1');
    });

    test('final scope returns only final statuses', function () {
        LeadStatus::create([
            'slug' => 'final1',
            'name' => 'Final 1',
            'color' => '#FF0000',
            'is_final' => true,
        ]);

        LeadStatus::create([
            'slug' => 'non_final1',
            'name' => 'Non Final 1',
            'color' => '#00FF00',
            'is_final' => false,
        ]);

        $finalStatuses = LeadStatus::final()->get();

        expect($finalStatuses)->toHaveCount(1)
            ->and($finalStatuses->first()->slug)->toBe('final1');
    });

    test('postCall scope returns only post-call statuses', function () {
        LeadStatus::create([
            'slug' => 'post_call1',
            'name' => 'Post Call 1',
            'color' => '#FF0000',
            'can_be_set_after_call' => true,
        ]);

        LeadStatus::create([
            'slug' => 'pre_call1',
            'name' => 'Pre Call 1',
            'color' => '#00FF00',
            'can_be_set_after_call' => false,
        ]);

        $postCallStatuses = LeadStatus::postCall()->get();

        expect($postCallStatuses)->toHaveCount(1)
            ->and($postCallStatuses->first()->slug)->toBe('post_call1');
    });
});

describe('LeadStatus Model - Static Methods', function () {
    test('allStatuses returns all statuses ordered', function () {
        LeadStatus::create(['slug' => 'status1', 'name' => 'Status 1', 'color' => '#FF0000', 'order' => 2]);
        LeadStatus::create(['slug' => 'status2', 'name' => 'Status 2', 'color' => '#00FF00', 'order' => 1]);

        $statuses = LeadStatus::allStatuses();

        expect($statuses)->toHaveCount(2)
            ->and($statuses->first()->slug)->toBe('status2'); // Lower order first
    });

    test('getBySlug returns correct status', function () {
        $status = LeadStatus::create([
            'slug' => 'find_me',
            'name' => 'Find Me',
            'color' => '#FF0000',
        ]);

        $found = LeadStatus::getBySlug('find_me');

        expect($found)->toBeInstanceOf(LeadStatus::class)
            ->and($found->id)->toBe($status->id);
    });

    test('getBySlug returns null for non-existent slug', function () {
        $found = LeadStatus::getBySlug('non_existent');

        expect($found)->toBeNull();
    });

    test('getActiveStatuses returns only active statuses', function () {
        LeadStatus::create(['slug' => 'active1', 'name' => 'Active 1', 'color' => '#FF0000', 'is_active' => true]);
        LeadStatus::create(['slug' => 'inactive1', 'name' => 'Inactive 1', 'color' => '#00FF00', 'is_active' => false]);

        $activeStatuses = LeadStatus::getActiveStatuses();

        expect($activeStatuses)->toHaveCount(1)
            ->and($activeStatuses->first()->slug)->toBe('active1');
    });

    test('getPostCallStatuses returns only post-call statuses', function () {
        LeadStatus::create(['slug' => 'post1', 'name' => 'Post 1', 'color' => '#FF0000', 'can_be_set_after_call' => true]);
        LeadStatus::create(['slug' => 'pre1', 'name' => 'Pre 1', 'color' => '#00FF00', 'can_be_set_after_call' => false]);

        $postCallStatuses = LeadStatus::getPostCallStatuses();

        expect($postCallStatuses)->toHaveCount(1)
            ->and($postCallStatuses->first()->slug)->toBe('post1');
    });
});

describe('LeadStatus Model - Relationships', function () {
    test('has many leads', function () {
        $status = LeadStatus::create([
            'slug' => 'test_relation',
            'name' => 'Test Relation',
            'color' => '#FF0000',
        ]);

        $lead1 = Lead::factory()->create(['status_id' => $status->id]);
        $lead2 = Lead::factory()->create(['status_id' => $status->id]);

        expect($status->leads)->toHaveCount(2)
            ->and($status->leads->first()->id)->toBe($lead1->id);
    });
});

describe('LeadStatus Model - Deletion', function () {
    test('canBeDeleted returns false for system status', function () {
        $status = LeadStatus::create([
            'slug' => 'system_status',
            'name' => 'System Status',
            'color' => '#FF0000',
            'is_system' => true,
        ]);

        expect($status->canBeDeleted())->toBeFalse();
    });

    test('canBeDeleted returns true for non-system status', function () {
        $status = LeadStatus::create([
            'slug' => 'custom_status',
            'name' => 'Custom Status',
            'color' => '#FF0000',
            'is_system' => false,
        ]);

        expect($status->canBeDeleted())->toBeTrue();
    });
});

