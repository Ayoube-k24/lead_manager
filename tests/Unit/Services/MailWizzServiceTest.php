<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\MailWizzConfig;
use App\Models\MailWizzImportedLead;
use App\Models\Tag;
use App\Services\MailWizzService;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->service = app(MailWizzService::class);
    $this->tagService = app(TagService::class);
});

test('can check if subscriber is already imported', function () {
    $subscriberId = 'test-subscriber-123';
    $lead = Lead::factory()->create(['source' => 'mailwizz_seo']);

    MailWizzImportedLead::create([
        'mailwizz_subscriber_id' => $subscriberId,
        'lead_id' => $lead->id,
        'email' => $lead->email,
        'imported_at' => now(),
    ]);

    expect(MailWizzImportedLead::isAlreadyImported($subscriberId))->toBeTrue();
    expect(MailWizzImportedLead::isAlreadyImported('other-subscriber'))->toBeFalse();
});

test('can check if email exists in leads', function () {
    $lead = Lead::factory()->create(['email' => 'test@example.com']);

    expect(MailWizzImportedLead::emailExistsInLeads('test@example.com'))->toBeTrue();
    expect(MailWizzImportedLead::emailExistsInLeads('other@example.com'))->toBeFalse();
});

test('skips leads that already submitted form', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    // Create a lead that already submitted a form
    $existingLead = Lead::factory()->create([
        'email' => 'existing@example.com',
        'source' => 'form',
    ]);

    // Mock HTTP response for authentication
    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'existing@example.com',
                    'FNAME' => 'John',
                    'LNAME' => 'Doe',
                ],
            ],
        ], 200),
    ]);

    $stats = $this->service->importLeads($config, 10);

    expect($stats['skipped_has_form'])->toBe(1)
        ->and($stats['imported'])->toBe(0);
});

test('creates lead_seo tag automatically', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    // Mock HTTP responses
    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'new@example.com',
                    'FNAME' => 'Jane',
                    'LNAME' => 'Doe',
                ],
            ],
        ], 200),
    ]);

    $this->service->importLeads($config, 10);

    $tag = Tag::where('name', 'lead_seo')->first();

    expect($tag)->not->toBeNull()
        ->and($tag->is_system)->toBeTrue()
        ->and($tag->color)->toBe('#10B981');
});

test('can successfully import new leads', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'new1@example.com',
                    'FNAME' => 'John',
                    'LNAME' => 'Doe',
                ],
                [
                    'subscriber_uid' => 'sub-456',
                    'email' => 'new2@example.com',
                    'FNAME' => 'Jane',
                    'LNAME' => 'Smith',
                ],
            ],
        ], 200),
    ]);

    $stats = $this->service->importLeads($config, 10);

    expect($stats['imported'])->toBe(2)
        ->and($stats['skipped_duplicate'])->toBe(0)
        ->and($stats['skipped_has_form'])->toBe(0)
        ->and($stats['errors'])->toBe(0);

    expect(Lead::where('source', 'mailwizz_seo')->count())->toBe(2);
});

test('skips duplicate subscribers', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    $existingLead = Lead::factory()->create(['source' => 'mailwizz_seo']);
    \App\Models\MailWizzImportedLead::factory()->create([
        'mailwizz_subscriber_id' => 'sub-123',
        'lead_id' => $existingLead->id,
        'email' => $existingLead->email,
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'existing@example.com',
                ],
            ],
        ], 200),
    ]);

    $stats = $this->service->importLeads($config, 10);

    expect($stats['skipped_duplicate'])->toBe(1)
        ->and($stats['imported'])->toBe(0);
});

test('skips leads with duplicate email from other source', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    // Create lead with same email but from mailwizz_seo source
    Lead::factory()->create([
        'email' => 'duplicate@example.com',
        'source' => 'mailwizz_seo',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-999',
                    'email' => 'duplicate@example.com',
                ],
            ],
        ], 200),
    ]);

    $stats = $this->service->importLeads($config, 10);

    expect($stats['skipped_duplicate'])->toBe(1)
        ->and($stats['imported'])->toBe(0);
});

test('handles authentication failure gracefully', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'invalid-key',
        'private_key' => 'invalid-key',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'error',
            'message' => 'Invalid credentials',
        ], 401),
    ]);

    expect(fn () => $this->service->importLeads($config, 10))
        ->toThrow(\Exception::class, 'Failed to authenticate');
});

test('handles invalid email addresses', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'invalid-email',
                ],
            ],
        ], 200),
    ]);

    $stats = $this->service->importLeads($config, 10);

    expect($stats['errors'])->toBe(1)
        ->and($stats['imported'])->toBe(0);
});

test('attaches lead_seo tag to imported leads', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'new@example.com',
                    'FNAME' => 'John',
                    'LNAME' => 'Doe',
                ],
            ],
        ], 200),
    ]);

    $this->service->importLeads($config, 10);

    $lead = Lead::where('email', 'new@example.com')->first();
    $tag = Tag::where('name', 'lead_seo')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->tags)->toHaveCount(1)
        ->and($lead->tags->first()->id)->toBe($tag->id);
});

test('updates config after import', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
        'last_import_at' => null,
        'last_import_count' => 0,
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'new@example.com',
                ],
            ],
        ], 200),
    ]);

    $this->service->importLeads($config, 10);

    $config->refresh();

    expect($config->last_import_at)->not->toBeNull()
        ->and($config->last_import_count)->toBe(1);
});

test('can authenticate successfully', function () {
    $config = new MailWizzConfig([
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token-123'],
        ], 200),
    ]);

    $result = $this->service->authenticate($config);

    expect($result)->toBeTrue();
});

test('authentication fails with invalid credentials', function () {
    $config = new MailWizzConfig([
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'invalid-public',
        'private_key' => 'invalid-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'error',
            'message' => 'Invalid credentials',
        ], 401),
    ]);

    $result = $this->service->authenticate($config);

    expect($result)->toBeFalse();
});

test('authentication handles network errors', function () {
    $config = new MailWizzConfig([
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([], 500),
    ]);

    $result = $this->service->authenticate($config);

    expect($result)->toBeFalse();
});

test('getSubscribers handles data records format', function () {
    $config = new MailWizzConfig([
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
        'list_uid' => 'list-123',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                'records' => [
                    ['subscriber_uid' => 'sub-1', 'email' => 'test1@example.com'],
                    ['subscriber_uid' => 'sub-2', 'email' => 'test2@example.com'],
                ],
            ],
        ], 200),
    ]);

    $subscribers = $this->service->getSubscribers($config, 1, 100);

    expect($subscribers)->toHaveCount(2)
        ->and($subscribers[0]['subscriber_uid'])->toBe('sub-1');
});

test('getSubscribers handles direct data format', function () {
    $config = new MailWizzConfig([
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
        'list_uid' => 'list-123',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                ['subscriber_uid' => 'sub-1', 'email' => 'test1@example.com'],
                ['subscriber_uid' => 'sub-2', 'email' => 'test2@example.com'],
            ],
        ], 200),
    ]);

    $subscribers = $this->service->getSubscribers($config, 1, 100);

    expect($subscribers)->toHaveCount(2);
});

test('getSubscribers handles empty response', function () {
    $config = new MailWizzConfig([
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
        'list_uid' => 'list-123',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [],
        ], 200),
    ]);

    $subscribers = $this->service->getSubscribers($config, 1, 100);

    expect($subscribers)->toBeArray()
        ->and($subscribers)->toBeEmpty();
});

test('getSubscribers throws exception on API failure', function () {
    $config = new MailWizzConfig([
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
        'list_uid' => 'list-123',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([], 500),
    ]);

    expect(fn () => $this->service->getSubscribers($config, 1, 100))
        ->toThrow(\Exception::class, 'Failed to fetch subscribers');
});

test('importLeads handles pagination with multiple pages', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
        'list_uid' => 'list-123',
    ]);

    $page1Subscribers = array_map(fn ($i) => [
        'subscriber_uid' => "sub-page1-{$i}",
        'email' => "page1-{$i}@example.com",
        'FNAME' => "First{$i}",
        'LNAME' => "Last{$i}",
    ], range(1, 10));

    $page2Subscribers = array_map(fn ($i) => [
        'subscriber_uid' => "sub-page2-{$i}",
        'email' => "page2-{$i}@example.com",
        'FNAME' => "First{$i}",
        'LNAME' => "Last{$i}",
    ], range(1, 5));

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::sequence()
            ->push([
                'status' => 'success',
                'data' => $page1Subscribers,
            ], 200)
            ->push([
                'status' => 'success',
                'data' => $page2Subscribers,
            ], 200),
    ]);

    $stats = $this->service->importLeads($config, 10);

    expect($stats['imported'])->toBe(15)
        ->and(Lead::where('source', 'mailwizz_seo')->count())->toBe(15);
});

test('importLeads handles subscriber with uid instead of subscriber_uid', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'uid' => 'sub-uid-123', // Using uid instead of subscriber_uid
                    'email' => 'test@example.com',
                    'FNAME' => 'John',
                    'LNAME' => 'Doe',
                ],
            ],
        ], 200),
    ]);

    $stats = $this->service->importLeads($config, 10);

    expect($stats['imported'])->toBe(1);

    $lead = Lead::where('email', 'test@example.com')->first();
    $imported = MailWizzImportedLead::where('mailwizz_subscriber_id', 'sub-uid-123')->first();

    expect($lead)->not->toBeNull()
        ->and($imported)->not->toBeNull()
        ->and($lead->data['mailwizz_subscriber_id'])->toBe('sub-uid-123');
});

test('importLeads handles missing subscriber id', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    // Missing both subscriber_uid and uid
                    'email' => 'test@example.com',
                ],
            ],
        ], 200),
    ]);

    $stats = $this->service->importLeads($config, 10);

    expect($stats['errors'])->toBe(1)
        ->and($stats['imported'])->toBe(0);
});

test('importLeads handles missing email field', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    // Missing email
                ],
            ],
        ], 200),
    ]);

    $stats = $this->service->importLeads($config, 10);

    expect($stats['errors'])->toBe(1)
        ->and($stats['imported'])->toBe(0);
});

test('importLeads handles first_name and last_name fields', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'test@example.com',
                    'first_name' => 'Jane', // Using first_name instead of FNAME
                    'last_name' => 'Smith', // Using last_name instead of LNAME
                ],
            ],
        ], 200),
    ]);

    $this->service->importLeads($config, 10);

    $lead = Lead::where('email', 'test@example.com')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->data['first_name'])->toBe('Jane')
        ->and($lead->data['last_name'])->toBe('Smith');
});

test('importLeads handles phone field variations', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'test@example.com',
                    'PHONE' => '+1234567890', // Using PHONE uppercase
                ],
            ],
        ], 200),
    ]);

    $this->service->importLeads($config, 10);

    $lead = Lead::where('email', 'test@example.com')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->data['phone'])->toBe('+1234567890');
});

test('importLeads handles clicked_url and clicked_at fields', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    $clickedAt = now()->subDays(2)->toIso8601String();

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'test@example.com',
                    'clicked_url' => 'https://example.com/page',
                    'clicked_at' => $clickedAt,
                ],
            ],
        ], 200),
    ]);

    $this->service->importLeads($config, 10);

    $lead = Lead::where('email', 'test@example.com')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->data['clicked_url'])->toBe('https://example.com/page')
        ->and($lead->data['clicked_at'])->toBe($clickedAt);
});

test('importLeads stores raw mailwizz data', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    $subscriberData = [
        'subscriber_uid' => 'sub-123',
        'email' => 'test@example.com',
        'FNAME' => 'John',
        'LNAME' => 'Doe',
        'custom_field' => 'custom_value',
    ];

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [$subscriberData],
        ], 200),
    ]);

    $this->service->importLeads($config, 10);

    $imported = MailWizzImportedLead::where('mailwizz_subscriber_id', 'sub-123')->first();

    expect($imported)->not->toBeNull()
        ->and($imported->mailwizz_data)->toBeArray()
        ->and($imported->mailwizz_data['custom_field'])->toBe('custom_value');
});

test('importLeads handles transaction rollback on error', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'test@example.com',
                ],
                [
                    'subscriber_uid' => 'sub-456',
                    'email' => 'invalid-email', // This will cause an error
                ],
            ],
        ], 200),
    ]);

    $stats = $this->service->importLeads($config, 10);

    // First lead should be imported, second should error
    expect($stats['imported'])->toBe(1)
        ->and($stats['errors'])->toBe(1)
        ->and(Lead::where('email', 'test@example.com')->exists())->toBeTrue()
        ->and(Lead::where('email', 'invalid-email')->exists())->toBeFalse();
});

test('importLeads handles network timeout gracefully', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        },
    ]);

    expect(fn () => $this->service->importLeads($config, 10))
        ->toThrow(\Exception::class);
});

test('importLeads handles empty subscriber list', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [],
        ], 200),
    ]);

    $stats = $this->service->importLeads($config, 10);

    expect($stats['imported'])->toBe(0)
        ->and($stats['errors'])->toBe(0)
        ->and($stats['skipped_duplicate'])->toBe(0);
});

test('importLeads assigns call center to leads', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'test@example.com',
                ],
            ],
        ], 200),
    ]);

    $this->service->importLeads($config, 10);

    $lead = Lead::where('email', 'test@example.com')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->call_center_id)->toBe($callCenter->id);
});

test('importLeads sets correct lead status', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'subscriber_uid' => 'sub-123',
                    'email' => 'test@example.com',
                ],
            ],
        ], 200),
    ]);

    $this->service->importLeads($config, 10);

    $lead = Lead::where('email', 'test@example.com')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->status)->toBe('pending_email')
        ->and($lead->source)->toBe('mailwizz_seo');
});

test('getSubscribers throws exception when list_uid is null', function () {
    $config = new MailWizzConfig([
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
        'list_uid' => null,
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([], 404),
    ]);

    expect(fn () => $this->service->getSubscribers($config, 1, 100))
        ->toThrow(\Exception::class);
});

test('importLeads handles null list_uid gracefully', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
        'list_uid' => null,
    ]);

    Http::fake([
        '*/authenticate' => Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => Http::response([], 404),
    ]);

    expect(fn () => $this->service->importLeads($config, 10))
        ->toThrow(\Exception::class);
});
