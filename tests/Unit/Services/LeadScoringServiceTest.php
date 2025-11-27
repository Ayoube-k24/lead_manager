<?php

declare(strict_types=1);

use App\LeadStatus;
use App\Models\Form;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Services\LeadScoringService;
use Carbon\Carbon;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->service = app(LeadScoringService::class);
});

test('can calculate score for a lead', function () {
    $form = Form::factory()->create(['is_active' => true]);
    $lead = Lead::factory()->create([
        'form_id' => $form->id,
        'status' => LeadStatus::EmailConfirmed,
        'email_confirmed_at' => now()->subMinutes(30),
        'data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'company' => 'Test Company',
        ],
    ]);

    $result = $this->service->calculateScore($lead);

    expect($result)
        ->toHaveKeys(['score', 'factors'])
        ->and($result['score'])->toBeInt()
        ->and($result['score'])->toBeGreaterThanOrEqual(0)
        ->and($result['score'])->toBeLessThanOrEqual(100)
        ->and($result['factors'])->toBeArray();
});

test('can update score for a lead', function () {
    $lead = Lead::factory()->create([
        'score' => null,
        'score_updated_at' => null,
        'score_factors' => null,
    ]);

    $updatedLead = $this->service->updateScore($lead);

    expect($updatedLead->score)->not->toBeNull()
        ->and($updatedLead->score_updated_at)->not->toBeNull()
        ->and($updatedLead->score_factors)->toBeArray();
});

test('calculates higher score for active forms', function () {
    $activeForm = Form::factory()->create(['is_active' => true]);
    $inactiveForm = Form::factory()->create(['is_active' => false]);

    $lead1 = Lead::factory()->create(['form_id' => $activeForm->id]);
    $lead2 = Lead::factory()->create(['form_id' => $inactiveForm->id]);

    $result1 = $this->service->calculateScore($lead1);
    $result2 = $this->service->calculateScore($lead2);

    // Active form should contribute more to score
    expect($result1['factors']['form_source']['value'])
        ->toBeGreaterThan($result2['factors']['form_source']['value']);
});

test('calculates higher score for quick email confirmation', function () {
    $lead1 = Lead::factory()->create([
        'email_confirmed_at' => now()->subMinutes(30), // < 1 hour
    ]);

    $lead2 = Lead::factory()->create([
        'email_confirmed_at' => now()->subDays(2), // > 48 hours
    ]);

    $result1 = $this->service->calculateScore($lead1);
    $result2 = $this->service->calculateScore($lead2);

    expect($result1['factors']['email_confirmation_time']['value'])
        ->toBeGreaterThan($result2['factors']['email_confirmation_time']['value']);
});

test('calculates score based on data completeness', function () {
    $lead1 = Lead::factory()->create([
        'data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'company' => 'Test Company',
            'message' => 'Test message',
            'address' => 'Test address',
        ],
    ]);

    $lead2 = Lead::factory()->create([
        'data' => [
            'email' => 'john@example.com',
        ],
    ]);

    $result1 = $this->service->calculateScore($lead1);
    $result2 = $this->service->calculateScore($lead2);

    expect($result1['factors']['data_completeness']['value'])
        ->toBeGreaterThan($result2['factors']['data_completeness']['value']);
});

test('calculates higher score for leads with notes', function () {
    $lead1 = Lead::factory()->create();
    $lead2 = Lead::factory()->create();

    LeadNote::factory()->count(5)->create(['lead_id' => $lead1->id]);

    $result1 = $this->service->calculateScore($lead1);
    $result2 = $this->service->calculateScore($lead2);

    expect($result1['factors']['lead_history']['value'])
        ->toBeGreaterThan($result2['factors']['lead_history']['value']);
});

test('calculates higher score for qualified leads', function () {
    $lead1 = Lead::factory()->create(['status' => LeadStatus::Qualified]);
    $lead2 = Lead::factory()->create(['status' => LeadStatus::PendingEmail]);

    $result1 = $this->service->calculateScore($lead1);
    $result2 = $this->service->calculateScore($lead2);

    expect($result1['factors']['current_status']['value'])
        ->toBeGreaterThan($result2['factors']['current_status']['value']);
});

test('calculates higher score for business hours submissions', function () {
    $businessHour = Carbon::parse('2024-01-15 14:00:00'); // Monday 2 PM
    $weekend = Carbon::parse('2024-01-13 20:00:00'); // Saturday 8 PM

    $lead1 = Lead::factory()->create(['created_at' => $businessHour]);
    $lead2 = Lead::factory()->create(['created_at' => $weekend]);

    $result1 = $this->service->calculateScore($lead1);
    $result2 = $this->service->calculateScore($lead2);

    expect($result1['factors']['behavioral_data']['value'])
        ->toBeGreaterThan($result2['factors']['behavioral_data']['value']);
});

test('score is always between 0 and 100', function () {
    $lead = Lead::factory()->create([
        'status' => LeadStatus::Rejected,
        'email_confirmed_at' => null,
        'data' => [],
    ]);

    $result = $this->service->calculateScore($lead);

    expect($result['score'])->toBeGreaterThanOrEqual(0)
        ->and($result['score'])->toBeLessThanOrEqual(100);
});

test('can get score factors configuration', function () {
    $factors = $this->service->getScoreFactors();

    expect($factors)
        ->toHaveKeys([
            'form_source',
            'email_confirmation_time',
            'data_completeness',
            'lead_history',
            'current_status',
            'behavioral_data',
        ])
        ->and($factors['form_source'])->toHaveKey('weight')
        ->and($factors['form_source'])->toHaveKey('label');
});
