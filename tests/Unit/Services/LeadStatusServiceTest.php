<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Services\LeadStatusService;

describe('LeadStatusService', function () {
    beforeEach(function () {
        $this->service = new LeadStatusService();
    });

    describe('createStatus', function () {
        test('creates a new custom status', function () {
            $data = [
                'name' => 'Test Status',
                'slug' => 'test_status',
                'color' => '#FF0000',
                'order' => 10,
            ];

            $status = $this->service->createStatus($data);

            expect($status)->toBeInstanceOf(LeadStatus::class)
                ->and($status->name)->toBe('Test Status')
                ->and($status->slug)->toBe('test_status')
                ->and($status->is_system)->toBeFalse();
        });

        test('sets is_system to false by default', function () {
            $data = [
                'name' => 'Custom Status',
                'slug' => 'custom_status',
            ];

            $status = $this->service->createStatus($data);

            expect($status->is_system)->toBeFalse();
        });
    });

    describe('updateStatus', function () {
        test('updates custom status successfully', function () {
            $status = LeadStatus::factory()->create([
                'is_system' => false,
                'name' => 'Old Name',
                'color' => '#000000',
            ]);

            $updated = $this->service->updateStatus($status, [
                'name' => 'New Name',
                'color' => '#FFFFFF',
            ]);

            expect($updated->name)->toBe('New Name')
                ->and($updated->color)->toBe('#FFFFFF');
        });

        test('prevents updating slug of system status', function () {
            $status = LeadStatus::factory()->create([
                'is_system' => true,
                'slug' => 'system_status',
            ]);

            expect(fn () => $this->service->updateStatus($status, ['slug' => 'new_slug']))
                ->toThrow(Exception::class);
        });

        test('prevents updating name of system status', function () {
            $status = LeadStatus::factory()->create([
                'is_system' => true,
                'name' => 'System Status',
            ]);

            expect(fn () => $this->service->updateStatus($status, ['name' => 'New Name']))
                ->toThrow(Exception::class);
        });

        test('allows updating description and color of system status', function () {
            $status = LeadStatus::factory()->create([
                'is_system' => true,
                'color' => '#000000',
            ]);

            $updated = $this->service->updateStatus($status, [
                'color' => '#FFFFFF',
                'description' => 'Updated description',
            ]);

            expect($updated->color)->toBe('#FFFFFF');
        });
    });

    describe('deleteStatus', function () {
        test('deletes status when not used by leads', function () {
            $status = LeadStatus::factory()->create(['is_system' => false]);

            $result = $this->service->deleteStatus($status);

            expect($result)->toBeTrue()
                ->and(LeadStatus::find($status->id))->toBeNull();
        });

        test('prevents deleting system status', function () {
            $status = LeadStatus::factory()->create(['is_system' => true]);

            expect(fn () => $this->service->deleteStatus($status))
                ->toThrow(Exception::class);
        });

        test('throws exception when status is used and no replacement provided', function () {
            $status = LeadStatus::factory()->create(['is_system' => false]);
            Lead::factory()->create(['status_id' => $status->id]);

            expect(fn () => $this->service->deleteStatus($status))
                ->toThrow(Exception::class);
        });

        test('replaces status on leads when replacement provided', function () {
            $oldStatus = LeadStatus::factory()->create(['is_system' => false]);
            $newStatus = LeadStatus::factory()->create(['is_system' => false]);

            $lead1 = Lead::factory()->create(['status_id' => $oldStatus->id]);
            $lead2 = Lead::factory()->create(['status_id' => $oldStatus->id]);

            $result = $this->service->deleteStatus($oldStatus, $newStatus);

            expect($result)->toBeTrue()
                ->and($lead1->fresh()->status_id)->toBe($newStatus->id)
                ->and($lead2->fresh()->status_id)->toBe($newStatus->id);
        });

        test('filters by call center when provided', function () {
            $callCenter1 = CallCenter::factory()->create();
            $callCenter2 = CallCenter::factory()->create();

            $oldStatus = LeadStatus::factory()->create(['is_system' => false]);
            $newStatus = LeadStatus::factory()->create(['is_system' => false]);

            $lead1 = Lead::factory()->create([
                'status_id' => $oldStatus->id,
                'call_center_id' => $callCenter1->id,
            ]);
            $lead2 = Lead::factory()->create([
                'status_id' => $oldStatus->id,
                'call_center_id' => $callCenter2->id,
            ]);

            $result = $this->service->deleteStatus($oldStatus, $newStatus, $callCenter1);

            expect($result)->toBeTrue()
                ->and($lead1->fresh()->status_id)->toBe($newStatus->id)
                ->and($lead2->fresh()->status_id)->toBe($oldStatus->id);
        });
    });

    describe('getAllStatusesWithCount', function () {
        test('returns all statuses with lead counts', function () {
            $status1 = LeadStatus::factory()->create();
            $status2 = LeadStatus::factory()->create();

            Lead::factory()->count(3)->create(['status_id' => $status1->id]);
            Lead::factory()->count(5)->create(['status_id' => $status2->id]);

            $statuses = $this->service->getAllStatusesWithCount();

            expect($statuses)->toHaveCount(2)
                ->and($statuses->firstWhere('id', $status1->id)->leads_count)->toBe(3)
                ->and($statuses->firstWhere('id', $status2->id)->leads_count)->toBe(5);
        });

        test('filters by call center when provided', function () {
            $callCenter = CallCenter::factory()->create();
            $status = LeadStatus::factory()->create();

            Lead::factory()->count(2)->create([
                'status_id' => $status->id,
                'call_center_id' => $callCenter->id,
            ]);
            Lead::factory()->count(3)->create([
                'status_id' => $status->id,
                'call_center_id' => CallCenter::factory()->create()->id,
            ]);

            $statuses = $this->service->getAllStatusesWithCount($callCenter);

            expect($statuses->firstWhere('id', $status->id)->leads_count)->toBe(2);
        });
    });

    describe('getActiveStatuses', function () {
        test('returns only active statuses', function () {
            LeadStatus::factory()->create(['is_active' => true]);
            LeadStatus::factory()->create(['is_active' => false]);
            LeadStatus::factory()->create(['is_active' => true]);

            $statuses = $this->service->getActiveStatuses();

            expect($statuses->every(fn ($s) => $s->is_active))->toBeTrue();
        });
    });

    describe('getBySlug', function () {
        test('returns status by slug', function () {
            $status = LeadStatus::factory()->create(['slug' => 'test_slug']);

            $found = $this->service->getBySlug('test_slug');

            expect($found)->not->toBeNull()
                ->and($found->id)->toBe($status->id);
        });

        test('returns null when slug not found', function () {
            $found = $this->service->getBySlug('non_existent');

            expect($found)->toBeNull();
        });
    });

    describe('getOptions', function () {
        test('returns statuses as array for select options', function () {
            $status1 = LeadStatus::factory()->create(['name' => 'Status 1', 'order' => 2]);
            $status2 = LeadStatus::factory()->create(['name' => 'Status 2', 'order' => 1]);

            $options = $this->service->getOptions();

            expect($options)->toBeArray()
                ->and($options[$status2->id])->toBe('Status 2')
                ->and($options[$status1->id])->toBe('Status 1');
        });
    });
});


