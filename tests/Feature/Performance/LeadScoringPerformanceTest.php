<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadReminder;
use App\Services\LeadScoringService;
use Illuminate\Support\Facades\DB;

describe('Lead Scoring Performance', function () {
    beforeEach(function () {
        $this->scoringService = new LeadScoringService();
    });

    test('calculates scores efficiently for many leads', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create([
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Create 1000 leads with various data
        $leads = Lead::factory()->count(1000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'email_confirmed_at' => now()->subHours(2),
            'score' => 0, // Start with 0, will be calculated
        ]);

        $startTime = microtime(true);

        // Calculate scores for all leads
        foreach ($leads as $lead) {
            $this->scoringService->updateScore($lead);
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Should calculate scores efficiently (< 5000ms for 1000 leads in SQLite)
        expect($executionTime)->toBeLessThan(5000.0);

        // Verify scores were calculated
        $scoredLeads = Lead::whereIn('id', $leads->pluck('id'))
            ->whereNotNull('score')
            ->count();

        expect($scoredLeads)->toBe(1000);
    });

    test('handles complex lead data efficiently', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create([
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Create leads with notes, reminders, and tags
        $leads = Lead::factory()->count(500)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'email_confirmed_at' => now()->subHours(1),
            'score' => 0, // Start with 0, will be calculated
        ]);

        // Add related data
        foreach ($leads as $lead) {
            LeadNote::factory()->count(3)->create(['lead_id' => $lead->id]);
            LeadReminder::factory()->count(2)->create(['lead_id' => $lead->id]);
        }

        $startTime = microtime(true);

        foreach ($leads as $lead) {
            $this->scoringService->updateScore($lead);
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Should handle complex data efficiently
        expect($executionTime)->toBeLessThan(2000.0);
    });

    test('recalculates scores efficiently in batch', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create([
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Create leads with existing scores
        $leads = Lead::factory()->count(1000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'email_confirmed_at' => now()->subDays(1),
            'score' => 50,
            'score_updated_at' => now()->subDays(2),
        ]);

        $startTime = microtime(true);

        // Recalculate all scores
        foreach ($leads as $lead) {
            $this->scoringService->updateScore($lead);
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Batch recalculation should be efficient (< 6000ms for 1000 leads in SQLite)
        expect($executionTime)->toBeLessThan(6000.0);
    });

    test('calculates score factors efficiently', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create([
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'email_confirmed_at' => now()->subHours(12),
            'score' => 0, // Start with 0, will be calculated
        ]);

        // Add related data
        LeadNote::factory()->count(5)->create(['lead_id' => $lead->id]);
        LeadReminder::factory()->count(3)->create(['lead_id' => $lead->id]);

        $startTime = microtime(true);

        $result = $this->scoringService->calculateScore($lead);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Score calculation should be fast (< 100ms per lead)
        expect($executionTime)->toBeLessThan(100.0)
            ->and($result)->toHaveKey('score')
            ->and($result)->toHaveKey('factors')
            ->and($result['score'])->toBeInt()
            ->and($result['score'])->toBeGreaterThanOrEqual(0)
            ->and($result['score'])->toBeLessThanOrEqual(100);
    });
});

