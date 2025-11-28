<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Services\LeadStatusService;

beforeEach(function () {
    require_once __DIR__.'/../../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->service = app(LeadStatusService::class);
});

describe('LeadStatusService - Create', function () {
    test('can create a status', function () {
        $status = $this->service->createStatus(
            'new_status',
            'New Status',
            '#FF0000',
            'Test description',
            true,
            false,
            true,
            10
        );

        expect($status)
            ->toBeInstanceOf(LeadStatus::class)
            ->and($status->slug)->toBe('new_status')
            ->and($status->name)->toBe('New Status')
            ->and($status->color)->toBe('#FF0000')
            ->and($status->description)->toBe('Test description')
            ->and($status->is_active)->toBeTrue()
            ->and($status->is_final)->toBeFalse()
            ->and($status->can_be_set_after_call)->toBeTrue()
            ->and($status->order)->toBe(10)
            ->and($status->is_system)->toBeFalse();
    });

    test('creates status with default values', function () {
        $status = $this->service->createStatus('default_status', 'Default Status');

        expect($status->color)->toBe('#6B7280')
            ->and($status->is_active)->toBeFalse()
            ->and($status->is_final)->toBeFalse()
            ->and($status->can_be_set_after_call)->toBeFalse()
            ->and($status->order)->toBe(0);
    });
});

describe('LeadStatusService - Update', function () {
    test('can update a status', function () {
        $status = LeadStatus::create([
            'slug' => 'update_me',
            'name' => 'Update Me',
            'color' => '#FF0000',
        ]);

        $updated = $this->service->updateStatus($status, [
            'name' => 'Updated Name',
            'color' => '#00FF00',
            'description' => 'Updated description',
        ]);

        expect($updated->name)->toBe('Updated Name')
            ->and($updated->color)->toBe('#00FF00')
            ->and($updated->description)->toBe('Updated description');
    });

    test('cannot update slug of system status', function () {
        $status = LeadStatus::create([
            'slug' => 'system_status',
            'name' => 'System Status',
            'color' => '#FF0000',
            'is_system' => true,
        ]);

        expect(fn () => $this->service->updateStatus($status, ['slug' => 'new_slug']))
            ->toThrow(\Exception::class);
    });

    test('cannot update name of system status', function () {
        $status = LeadStatus::create([
            'slug' => 'system_status',
            'name' => 'System Status',
            'color' => '#FF0000',
            'is_system' => true,
        ]);

        expect(fn () => $this->service->updateStatus($status, ['name' => 'New Name']))
            ->toThrow(\Exception::class);
    });

    test('can update description and color of system status', function () {
        $status = LeadStatus::create([
            'slug' => 'system_status',
            'name' => 'System Status',
            'color' => '#FF0000',
            'is_system' => true,
        ]);

        $updated = $this->service->updateStatus($status, [
            'description' => 'New description',
            'color' => '#00FF00',
        ]);

        expect($updated->description)->toBe('New description')
            ->and($updated->color)->toBe('#00FF00');
    });
});

describe('LeadStatusService - Delete', function () {
    test('can delete a non-system status', function () {
        $status = LeadStatus::create([
            'slug' => 'delete_me',
            'name' => 'Delete Me',
            'color' => '#FF0000',
            'is_system' => false,
        ]);

        $result = $this->service->deleteStatus($status);

        expect($result)->toBeTrue()
            ->and(LeadStatus::find($status->id))->toBeNull();
    });

    test('cannot delete a system status', function () {
        $status = LeadStatus::create([
            'slug' => 'system_status',
            'name' => 'System Status',
            'color' => '#FF0000',
            'is_system' => true,
        ]);

        expect(fn () => $this->service->deleteStatus($status))
            ->toThrow(\Exception::class, 'Les statuts système ne peuvent pas être supprimés.');
    });

    test('cannot delete a status used by leads', function () {
        $status = LeadStatus::create([
            'slug' => 'used_status',
            'name' => 'Used Status',
            'color' => '#FF0000',
            'is_system' => false,
        ]);

        Lead::factory()->create(['status_id' => $status->id]);

        expect(fn () => $this->service->deleteStatus($status))
            ->toThrow(\Exception::class, 'Ce statut est utilisé sur des leads');
    });
});

describe('LeadStatusService - Get Methods', function () {
    test('getAllStatusesWithCount returns statuses with lead count', function () {
        $status1 = LeadStatus::create(['slug' => 'status1', 'name' => 'Status 1', 'color' => '#FF0000']);
        $status2 = LeadStatus::create(['slug' => 'status2', 'name' => 'Status 2', 'color' => '#00FF00']);

        Lead::factory()->count(3)->create(['status_id' => $status1->id]);
        Lead::factory()->count(2)->create(['status_id' => $status2->id]);

        $statuses = $this->service->getAllStatusesWithCount();

        expect($statuses)->toHaveCount(2)
            ->and($statuses->firstWhere('slug', 'status1')->leads_count)->toBe(3)
            ->and($statuses->firstWhere('slug', 'status2')->leads_count)->toBe(2);
    });

    test('getAllStatusesWithCount filters by call center', function () {
        $callCenter = CallCenter::factory()->create();
        $status = LeadStatus::create(['slug' => 'filtered', 'name' => 'Filtered', 'color' => '#FF0000']);

        Lead::factory()->create(['status_id' => $status->id, 'call_center_id' => $callCenter->id]);
        Lead::factory()->create(['status_id' => $status->id]); // Different call center

        $statuses = $this->service->getAllStatusesWithCount($callCenter);

        expect($statuses)->toHaveCount(1)
            ->and($statuses->first()->leads_count)->toBe(1);
    });

    test('getActiveStatuses returns only active statuses', function () {
        LeadStatus::create(['slug' => 'active1', 'name' => 'Active 1', 'color' => '#FF0000', 'is_active' => true]);
        LeadStatus::create(['slug' => 'inactive1', 'name' => 'Inactive 1', 'color' => '#00FF00', 'is_active' => false]);

        $activeStatuses = $this->service->getActiveStatuses();

        expect($activeStatuses)->toHaveCount(1)
            ->and($activeStatuses->first()->slug)->toBe('active1');
    });

    test('getPostCallStatuses returns only post-call statuses', function () {
        LeadStatus::create(['slug' => 'post1', 'name' => 'Post 1', 'color' => '#FF0000', 'can_be_set_after_call' => true]);
        LeadStatus::create(['slug' => 'pre1', 'name' => 'Pre 1', 'color' => '#00FF00', 'can_be_set_after_call' => false]);

        $postCallStatuses = $this->service->getPostCallStatuses();

        expect($postCallStatuses)->toHaveCount(1)
            ->and($postCallStatuses->first()->slug)->toBe('post1');
    });

    test('getBySlug returns correct status', function () {
        $status = LeadStatus::create(['slug' => 'find_me', 'name' => 'Find Me', 'color' => '#FF0000']);

        $found = $this->service->getBySlug('find_me');

        expect($found)->toBeInstanceOf(LeadStatus::class)
            ->and($found->id)->toBe($status->id);
    });

    test('getOptions returns array of id => name', function () {
        LeadStatus::create(['slug' => 'option1', 'name' => 'Option 1', 'color' => '#FF0000', 'order' => 2]);
        LeadStatus::create(['slug' => 'option2', 'name' => 'Option 2', 'color' => '#00FF00', 'order' => 1]);

        $options = $this->service->getOptions();

        expect($options)->toBeArray()
            ->and($options)->toHaveCount(2)
            ->and(array_values($options)[0])->toBe('Option 2'); // Lower order first
    });
});
