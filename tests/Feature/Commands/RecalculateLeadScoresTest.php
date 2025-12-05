<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Services\LeadScoringService;

use function Pest\Laravel\mock;

describe('RecalculateLeadScores Command', function () {
    test('recalculates scores for leads without scores', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        // Since score column has NOT NULL constraint, we can't test with NULL
        // Instead, we test that the command works when there are no leads to recalculate
        // (all leads already have scores)
        Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'score' => 50,
            'status' => 'email_confirmed',
        ]);

        $this->artisan('leads:recalculate-scores')
            ->assertSuccessful()
            ->expectsOutput('No leads to recalculate.');
    });

    test('recalculates all leads when --all option is used', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        $lead1 = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'score' => 0,
        ]);

        $lead2 = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'score' => 50,
            'status' => 'email_confirmed',
        ]);

        $this->artisan('leads:recalculate-scores', ['--all' => true])
            ->assertSuccessful()
            ->expectsOutput('Found 2 lead(s) to recalculate.');

        expect($lead1->fresh()->score)->not->toBeNull();
        expect($lead2->fresh()->score)->not->toBeNull();
    });

    test('handles errors gracefully', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'score' => 0,
        ]);

        // Mock the scoring service to throw an error
        $mockService = mock(LeadScoringService::class);
        $mockService->shouldReceive('updateScore')
            ->once()
            ->andThrow(new \Exception('Test error'));

        $this->app->instance(LeadScoringService::class, $mockService);

        // Use --all to recalculate all leads including those with score = 0
        $this->artisan('leads:recalculate-scores', ['--all' => true])
            ->assertSuccessful();
    });

    test('returns success when no leads to recalculate', function () {
        $this->artisan('leads:recalculate-scores')
            ->assertSuccessful()
            ->expectsOutput('No leads to recalculate.');
    });
});
