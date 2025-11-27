<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadSearchService;
use Carbon\Carbon;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->service = app(LeadSearchService::class);
});

test('can search leads by email', function () {
    Lead::factory()->create(['email' => 'john@example.com']);
    Lead::factory()->create(['email' => 'jane@example.com']);

    $results = $this->service->search('john@example.com');

    expect($results->total())->toBe(1)
        ->and($results->first()->email)->toBe('john@example.com');
});

test('can search leads by name in data', function () {
    Lead::factory()->create(['data' => ['name' => 'John Doe']]);
    Lead::factory()->create(['data' => ['name' => 'Jane Smith']]);

    $results = $this->service->search('John');

    expect($results->total())->toBe(1)
        ->and($results->first()->data['name'])->toBe('John Doe');
});

test('can search leads by phone in data', function () {
    Lead::factory()->create(['data' => ['phone' => '1234567890']]);
    Lead::factory()->create(['data' => ['phone' => '0987654321']]);

    $results = $this->service->search('1234567890');

    expect($results->total())->toBe(1);
});

test('can filter leads by status', function () {
    Lead::factory()->count(3)->create(['status' => 'pending_email']);
    Lead::factory()->count(2)->create(['status' => 'email_confirmed']);

    $results = $this->service->search('', ['status' => 'pending_email']);

    expect($results->total())->toBe(3);
});

test('can filter leads by multiple statuses', function () {
    Lead::factory()->count(2)->create(['status' => 'pending_email']);
    Lead::factory()->count(3)->create(['status' => 'email_confirmed']);
    Lead::factory()->count(1)->create(['status' => 'converted']);

    $results = $this->service->search('', ['status' => ['pending_email', 'email_confirmed']]);

    expect($results->total())->toBe(5);
});

test('can filter leads by created date range', function () {
    Lead::factory()->create(['created_at' => Carbon::parse('2024-01-01')]);
    Lead::factory()->create(['created_at' => Carbon::parse('2024-01-15')]);
    Lead::factory()->create(['created_at' => Carbon::parse('2024-02-01')]);

    $results = $this->service->search('', [
        'created_from' => '2024-01-01',
        'created_to' => '2024-01-31',
    ]);

    expect($results->total())->toBe(2);
});

test('can filter leads by email confirmation date', function () {
    Lead::factory()->create(['email_confirmed_at' => Carbon::parse('2024-01-01')]);
    Lead::factory()->create(['email_confirmed_at' => Carbon::parse('2024-01-15')]);
    Lead::factory()->create(['email_confirmed_at' => null]);

    $results = $this->service->search('', [
        'email_confirmed_from' => '2024-01-01',
        'email_confirmed_to' => '2024-01-31',
    ]);

    expect($results->total())->toBe(2);
});

test('can filter leads by assigned agent', function () {
    $agent = User::factory()->create(['role_id' => Role::factory()->create(['slug' => 'agent'])->id]);

    Lead::factory()->count(3)->create(['assigned_to' => $agent->id]);
    Lead::factory()->count(2)->create(['assigned_to' => null]);

    $results = $this->service->search('', ['assigned_to' => $agent->id]);

    expect($results->total())->toBe(3);
});

test('can filter leads by call center', function () {
    $callCenter1 = CallCenter::factory()->create();
    $callCenter2 = CallCenter::factory()->create();

    Lead::factory()->count(3)->create(['call_center_id' => $callCenter1->id]);
    Lead::factory()->count(2)->create(['call_center_id' => $callCenter2->id]);

    $results = $this->service->search('', ['call_center_id' => $callCenter1->id]);

    expect($results->total())->toBe(3);
});

test('can filter leads by form', function () {
    $form1 = Form::factory()->create();
    $form2 = Form::factory()->create();

    Lead::factory()->count(3)->create(['form_id' => $form1->id]);
    Lead::factory()->count(2)->create(['form_id' => $form2->id]);

    $results = $this->service->search('', ['form_id' => $form1->id]);

    expect($results->total())->toBe(3);
});

test('can filter leads by email confirmation status', function () {
    Lead::factory()->count(3)->create(['email_confirmed_at' => now()]);
    Lead::factory()->count(2)->create(['email_confirmed_at' => null]);

    $results = $this->service->search('', ['email_confirmed' => true]);

    expect($results->total())->toBe(3);
});

test('can filter leads by call date range', function () {
    Lead::factory()->create(['called_at' => Carbon::parse('2024-01-01')]);
    Lead::factory()->create(['called_at' => Carbon::parse('2024-01-15')]);
    Lead::factory()->create(['called_at' => null]);

    $results = $this->service->search('', [
        'called_from' => '2024-01-01',
        'called_to' => '2024-01-31',
    ]);

    expect($results->total())->toBe(2);
});

test('can filter leads by notes presence', function () {
    $lead1 = Lead::factory()->create();
    $lead2 = Lead::factory()->create();

    LeadNote::factory()->create(['lead_id' => $lead1->id]);

    $results = $this->service->search('', ['has_notes' => true]);

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($lead1->id);
});

test('can combine search query with filters', function () {
    Lead::factory()->create([
        'email' => 'john@example.com',
        'status' => 'pending_email',
    ]);

    Lead::factory()->create([
        'email' => 'john@example.com',
        'status' => 'converted',
    ]);

    Lead::factory()->create([
        'email' => 'jane@example.com',
        'status' => 'pending_email',
    ]);

    $results = $this->service->search('john@example.com', ['status' => 'pending_email']);

    expect($results->total())->toBe(1);
});

test('can get available filters', function () {
    $filters = $this->service->getAvailableFilters();

    expect($filters)
        ->toHaveKeys([
            'status',
            'created_from',
            'created_to',
            'email_confirmed_from',
            'email_confirmed_to',
            'assigned_to',
            'call_center_id',
            'form_id',
            'email_confirmed',
            'called_from',
            'called_to',
            'has_notes',
        ])
        ->and($filters['status'])->toHaveKey('type')
        ->and($filters['status'])->toHaveKey('label');
});

test('returns paginated results', function () {
    Lead::factory()->count(25)->create();

    $results = $this->service->search('', [], 10);

    expect($results)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
        ->and($results->perPage())->toBe(10)
        ->and($results->total())->toBe(25)
        ->and($results->count())->toBe(10);
});
