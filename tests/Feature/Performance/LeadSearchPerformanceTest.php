<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Services\LeadSearchService;

describe('Lead Search Performance', function () {
    beforeEach(function () {
        $this->searchService = new LeadSearchService;
    });

    test('searches efficiently with large dataset', function () {
        // Create a large dataset
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        // Create 1000 leads
        Lead::factory()->count(1000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'email_confirmed_at' => null,
            'score' => 50,
        ]);

        // Measure search performance
        $startTime = microtime(true);

        $results = $this->searchService->search('test', [], 15);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Should complete in reasonable time (< 500ms for 1000 records)
        expect($executionTime)->toBeLessThan(500.0)
            ->and($results)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    });

    test('handles complex filters efficiently', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $status = LeadStatus::firstOrCreate(['slug' => 'confirmed'], ['name' => 'Confirmed']);

        // Create leads with various statuses
        Lead::factory()->count(500)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'score' => 50,
        ]);

        Lead::factory()->count(300)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'confirmed',
            'score' => 80,
        ]);

        $startTime = microtime(true);

        $results = $this->searchService->search('', [
            'status' => ['confirmed'],
            'call_center_id' => $callCenter->id,
            'min_score' => 70,
            'max_score' => 100,
        ], 15);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Should complete efficiently even with complex filters
        expect($executionTime)->toBeLessThan(500.0)
            ->and($results->count())->toBeGreaterThan(0);
    });

    test('paginates efficiently with large result sets', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        // Create 2000 leads
        Lead::factory()->count(2000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'score' => 50,
        ]);

        $startTime = microtime(true);

        // Test pagination performance
        $page1 = $this->searchService->search('', [], 50);
        $page2 = $this->searchService->search('', [], 50);
        $page2->setCurrentPage(2);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Pagination should be efficient
        expect($executionTime)->toBeLessThan(1000.0)
            ->and($page1->count())->toBe(50)
            ->and($page1->total())->toBe(2000);
    });

    test('uses indexes efficiently for status filtering', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        // Create leads with different statuses
        Lead::factory()->count(1000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'score' => 50,
        ]);

        $startTime = microtime(true);

        $results = $this->searchService->search('', [
            'status' => ['pending_email'],
        ], 15);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Status filtering should use indexes efficiently
        expect($executionTime)->toBeLessThan(300.0)
            ->and($results->count())->toBeGreaterThan(0);
    });
});
