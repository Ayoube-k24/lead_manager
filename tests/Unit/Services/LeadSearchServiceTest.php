<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Tag;
use App\Models\User;
use App\Services\LeadSearchService;

describe('LeadSearchService', function () {
    beforeEach(function () {
        $this->service = new LeadSearchService();
    });

    describe('search', function () {
        test('searches leads by email', function () {
            Lead::factory()->create(['email' => 'test@example.com']);
            Lead::factory()->create(['email' => 'other@example.com']);

            $results = $this->service->search('test@example.com');

            expect($results->count())->toBe(1)
                ->and($results->first()->email)->toBe('test@example.com');
        });

        test('searches leads by name in data', function () {
            Lead::factory()->create(['data' => ['name' => 'John Doe']]);
            Lead::factory()->create(['data' => ['name' => 'Jane Smith']]);

            $results = $this->service->search('John');

            expect($results->count())->toBe(1);
        });

        test('searches leads by phone in data', function () {
            Lead::factory()->create(['data' => ['phone' => '1234567890']]);
            Lead::factory()->create(['data' => ['phone' => '0987654321']]);

            $results = $this->service->search('1234567890');

            expect($results->count())->toBe(1);
        });

        test('returns paginated results', function () {
            Lead::factory()->count(25)->create();

            $results = $this->service->search('', [], 10);

            expect($results)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
                ->and($results->perPage())->toBe(10);
        });

        test('eager loads relationships', function () {
            $form = Form::factory()->create();
            $callCenter = CallCenter::factory()->create();
            $agent = User::factory()->create();

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'assigned_to' => $agent->id,
            ]);

            $results = $this->service->search('', []);

            expect($results->first()->relationLoaded('form'))->toBeTrue()
                ->and($results->first()->relationLoaded('callCenter'))->toBeTrue()
                ->and($results->first()->relationLoaded('assignedAgent'))->toBeTrue();
        });
    });

    describe('buildQuery', function () {
        test('filters by status', function () {
            $status = LeadStatus::factory()->create(['slug' => 'confirmed']);
            Lead::factory()->create(['status_id' => $status->id, 'status' => 'confirmed']);
            Lead::factory()->create(['status' => 'pending_email']);

            $query = $this->service->buildQuery(['status' => 'confirmed']);
            $results = $query->get();

            expect($results->count())->toBe(1)
                ->and($results->first()->status)->toBe('confirmed');
        });

        test('filters by multiple statuses', function () {
            $status1 = LeadStatus::factory()->create(['slug' => 'confirmed']);
            $status2 = LeadStatus::factory()->create(['slug' => 'rejected']);

            Lead::factory()->create(['status_id' => $status1->id]);
            Lead::factory()->create(['status_id' => $status2->id]);
            Lead::factory()->create(['status' => 'pending_email']);

            $query = $this->service->buildQuery(['status' => ['confirmed', 'rejected']]);
            $results = $query->get();

            expect($results->count())->toBe(2);
        });

        test('filters by date range', function () {
            // Create leads with specific dates
            Lead::factory()->create(['created_at' => now()->subDays(5)]);
            Lead::factory()->create(['created_at' => now()->subDays(2)]);
            Lead::factory()->create(['created_at' => now()->subDays(10)]);
            Lead::factory()->create(['created_at' => now()->subHours(2)]); // Created today

            $query = $this->service->buildQuery([
                'created_from' => now()->subDays(3)->toDateString(),
                'created_to' => now()->toDateString(),
            ]);
            $results = $query->get();

            // Should find: lead from 2 days ago and lead from today
            expect($results->count())->toBe(2);
        });

        test('filters by assigned agent', function () {
            $agent1 = User::factory()->create();
            $agent2 = User::factory()->create();

            Lead::factory()->create(['assigned_to' => $agent1->id]);
            Lead::factory()->create(['assigned_to' => $agent2->id]);
            Lead::factory()->create(['assigned_to' => null]);

            $query = $this->service->buildQuery(['assigned_to' => $agent1->id]);
            $results = $query->get();

            expect($results->count())->toBe(1)
                ->and($results->first()->assigned_to)->toBe($agent1->id);
        });

        test('filters by call center', function () {
            $callCenter1 = CallCenter::factory()->create();
            $callCenter2 = CallCenter::factory()->create();

            Lead::factory()->create(['call_center_id' => $callCenter1->id]);
            Lead::factory()->create(['call_center_id' => $callCenter2->id]);

            $query = $this->service->buildQuery(['call_center_id' => $callCenter1->id]);
            $results = $query->get();

            expect($results->count())->toBe(1);
        });

        test('filters by email confirmation status', function () {
            Lead::factory()->create(['email_confirmed_at' => now()]);
            Lead::factory()->create(['email_confirmed_at' => null]);

            $query = $this->service->buildQuery(['email_confirmed' => true]);
            $results = $query->get();

            expect($results->count())->toBe(1)
                ->and($results->first()->email_confirmed_at)->not->toBeNull();
        });

        test('filters by tags - any mode', function () {
            $tag1 = Tag::factory()->create();
            $tag2 = Tag::factory()->create();

            $lead1 = Lead::factory()->create();
            $lead2 = Lead::factory()->create();
            $lead3 = Lead::factory()->create();

            $lead1->tags()->attach($tag1->id);
            $lead2->tags()->attach($tag2->id);
            $lead3->tags()->attach([$tag1->id, $tag2->id]);

            $query = $this->service->buildQuery(['tags' => [$tag1->id, $tag2->id], 'tags_mode' => 'any']);
            $results = $query->get();

            expect($results->count())->toBe(3);
        });

        test('filters by tags - all mode', function () {
            $tag1 = Tag::factory()->create();
            $tag2 = Tag::factory()->create();

            $lead1 = Lead::factory()->create();
            $lead2 = Lead::factory()->create();

            $lead1->tags()->attach([$tag1->id, $tag2->id]);
            $lead2->tags()->attach($tag1->id);

            $query = $this->service->buildQuery(['tags' => [$tag1->id, $tag2->id], 'tags_mode' => 'all']);
            $results = $query->get();

            expect($results->count())->toBe(1)
                ->and($results->first()->id)->toBe($lead1->id);
        });

        test('filters leads with no tags', function () {
            $tag = Tag::factory()->create();

            $lead1 = Lead::factory()->create();
            $lead2 = Lead::factory()->create();
            $lead2->tags()->attach($tag->id);

            $query = $this->service->buildQuery(['no_tags' => true]);
            $results = $query->get();

            expect($results->count())->toBe(1)
                ->and($results->first()->id)->toBe($lead1->id);
        });

        test('filters by notes presence', function () {
            $lead1 = Lead::factory()->create();
            $lead2 = Lead::factory()->create();

            $user = User::factory()->create();
            $lead1->notes()->create(['content' => 'Test note', 'user_id' => $user->id]);

            $query = $this->service->buildQuery(['has_notes' => true]);
            $results = $query->get();

            expect($results->count())->toBe(1)
                ->and($results->first()->id)->toBe($lead1->id);
        });
    });

    describe('getAvailableFilters', function () {
        test('returns all available filters', function () {
            $filters = $this->service->getAvailableFilters();

            expect($filters)->toBeArray()
                ->and($filters)->toHaveKeys([
                    'status',
                    'created_from',
                    'created_to',
                    'assigned_to',
                    'call_center_id',
                    'email_confirmed',
                    'tags',
                ]);
        });
    });
});

