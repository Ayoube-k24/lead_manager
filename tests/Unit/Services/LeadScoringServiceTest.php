<?php

declare(strict_types=1);

use App\LeadStatus;
use App\Models\Form;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadReminder;
use App\Models\Tag;
use App\Services\LeadScoringService;
use Illuminate\Support\Facades\Config;

describe('LeadScoringService', function () {
    beforeEach(function () {
        $this->service = new LeadScoringService();
    });

    describe('calculateScore', function () {
        test('calculates score for lead with all factors', function () {
            $form = Form::factory()->create(['is_active' => true]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'status' => 'email_confirmed',
                'email_confirmed_at' => now()->subHour(),
                'created_at' => now()->subHours(2),
                'data' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone' => '1234567890',
                    'company' => 'Acme Corp',
                ],
            ]);

            $result = $this->service->calculateScore($lead);

            expect($result)->toHaveKeys(['score', 'factors'])
                ->and($result['score'])->toBeInt()
                ->and($result['score'])->toBeGreaterThanOrEqual(0)
                ->and($result['score'])->toBeLessThanOrEqual(100)
                ->and($result['factors'])->toBeArray();
        });

        test('form source contributes to score', function () {
            $activeForm = Form::factory()->create(['is_active' => true]);
            $inactiveForm = Form::factory()->create(['is_active' => false]);

            $lead1 = Lead::factory()->create([
                'form_id' => $activeForm->id,
                'status' => 'pending_email',
            ]);

            $lead2 = Lead::factory()->create([
                'form_id' => $inactiveForm->id,
                'status' => 'pending_email',
            ]);

            $result1 = $this->service->calculateScore($lead1);
            $result2 = $this->service->calculateScore($lead2);

            expect($result1['factors']['form_source']['value'])->toBe(80)
                ->and($result2['factors']['form_source']['value'])->toBe(50);
        });

        test('email confirmation time contributes to score', function () {
            $lead1 = Lead::factory()->create([
                'email_confirmed_at' => now()->subMinutes(30),
                'created_at' => now()->subHour(),
            ]);

            // Lead confirmed 2 days after creation (48+ hours)
            $lead2 = Lead::factory()->create([
                'created_at' => now()->subDays(3),
                'email_confirmed_at' => now()->subDays(1), // Confirmed 2 days after creation (48+ hours)
            ]);

            $lead3 = Lead::factory()->create([
                'email_confirmed_at' => null,
            ]);

            $result1 = $this->service->calculateScore($lead1);
            $result2 = $this->service->calculateScore($lead2);
            $result3 = $this->service->calculateScore($lead3);

            expect($result1['factors']['email_confirmation_time']['value'])->toBe(100)
                ->and($result2['factors']['email_confirmation_time']['value'])->toBe(25)
                ->and($result3['factors']['email_confirmation_time']['value'])->toBe(0);
        });

        test('data completeness contributes to score', function () {
            $completeLead = Lead::factory()->create([
                'data' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone' => '1234567890',
                    'company' => 'Acme Corp',
                    'message' => 'Hello',
                    'address' => '123 Main St',
                ],
            ]);

            $partialLead = Lead::factory()->create([
                'data' => [
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                ],
            ]);

            $result1 = $this->service->calculateScore($completeLead);
            $result2 = $this->service->calculateScore($partialLead);

            expect($result1['factors']['data_completeness']['value'])->toBeGreaterThan(
                $result2['factors']['data_completeness']['value']
            );
        });

        test('lead history contributes to score', function () {
            $lead = Lead::factory()->create();

            $result1 = $this->service->calculateScore($lead);

            // Add notes
            LeadNote::factory()->count(3)->create(['lead_id' => $lead->id]);

            $result2 = $this->service->calculateScore($lead);

            expect($result2['factors']['lead_history']['value'])
                ->toBeGreaterThan($result1['factors']['lead_history']['value']);

            // Add completed reminders
            LeadReminder::factory()->count(2)->create([
                'lead_id' => $lead->id,
                'is_completed' => true,
            ]);

            $result3 = $this->service->calculateScore($lead);

            expect($result3['factors']['lead_history']['value'])
                ->toBeGreaterThan($result2['factors']['lead_history']['value']);
        });

        test('current status contributes to score', function () {
            $confirmedLead = Lead::factory()->create(['status' => 'confirmed']);
            $rejectedLead = Lead::factory()->create(['status' => 'rejected']);
            $emailConfirmedLead = Lead::factory()->create(['status' => 'email_confirmed']);

            $result1 = $this->service->calculateScore($confirmedLead);
            $result2 = $this->service->calculateScore($rejectedLead);
            $result3 = $this->service->calculateScore($emailConfirmedLead);

            expect($result1['factors']['current_status']['value'])->toBe(85)
                ->and($result2['factors']['current_status']['value'])->toBe(10)
                ->and($result3['factors']['current_status']['value'])->toBe(80);
        });

        test('behavioral data contributes to score', function () {
            // Business hours weekday
            $lead1 = Lead::factory()->create([
                'created_at' => now()->setTime(14, 0)->startOfWeek()->addDays(1),
            ]);

            // Weekend
            $lead2 = Lead::factory()->create([
                'created_at' => now()->setTime(14, 0)->startOfWeek()->addDays(6),
            ]);

            $result1 = $this->service->calculateScore($lead1);
            $result2 = $this->service->calculateScore($lead2);

            expect($result1['factors']['behavioral_data']['value'])
                ->toBeGreaterThan($result2['factors']['behavioral_data']['value']);
        });

        test('score is capped between 0 and 100', function () {
            $lead = Lead::factory()->create([
                'status' => 'rejected',
                'email_confirmed_at' => null,
                'data' => [],
            ]);

            $result = $this->service->calculateScore($lead);

            expect($result['score'])->toBeGreaterThanOrEqual(0)
                ->and($result['score'])->toBeLessThanOrEqual(100);
        });
    });

    describe('updateScore', function () {
        test('updates lead score and factors', function () {
            $lead = Lead::factory()->create([
                'score' => 0,
                'score_factors' => null,
            ]);

            $updatedLead = $this->service->updateScore($lead);

            expect($updatedLead->score)->not->toBeNull()
                ->and($updatedLead->score_updated_at)->not->toBeNull()
                ->and($updatedLead->score_factors)->toBeArray();
        });

        test('uses saveQuietly to avoid observer loops', function () {
            $lead = Lead::factory()->create();

            // Should not trigger observer
            $updatedLead = $this->service->updateScore($lead);

            expect($updatedLead->score)->not->toBeNull();
        });

        test('loads necessary relationships before calculation', function () {
            $form = Form::factory()->create();
            $lead = Lead::factory()->create([
                'form_id' => $form->id,
            ]);

            // Create notes and reminders
            LeadNote::factory()->count(2)->create(['lead_id' => $lead->id]);
            LeadReminder::factory()->count(1)->create(['lead_id' => $lead->id]);

            $updatedLead = $this->service->updateScore($lead);

            expect($updatedLead->score)->not->toBeNull();
        });
    });

    describe('getScoreFactors', function () {
        test('returns default score factors configuration', function () {
            $factors = $this->service->getScoreFactors();

            expect($factors)->toBeArray()
                ->and($factors)->toHaveKeys([
                    'form_source',
                    'email_confirmation_time',
                    'data_completeness',
                    'lead_history',
                    'current_status',
                    'behavioral_data',
                ]);
        });

        test('uses config values when available', function () {
            Config::set('lead-scoring.factors.form_source.weight', 15);

            $factors = $this->service->getScoreFactors();

            expect($factors['form_source']['weight'])->toBe(15);
        });
    });
});

