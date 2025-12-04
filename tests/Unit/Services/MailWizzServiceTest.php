<?php

declare(strict_types=1);

use App\Models\Form;
use App\Models\Lead;
use App\Models\MailWizzConfig;
use App\Models\MailWizzImportedLead;
use App\Services\MailWizzService;
use App\Services\TagService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\mock;

describe('MailWizzService', function () {
    beforeEach(function () {
        $this->tagService = mock(TagService::class);
        $this->tagService->shouldReceive('attachTag')->zeroOrMoreTimes()->andReturn(true);
        $this->service = new MailWizzService($this->tagService);
        Log::spy();
    });

    describe('authenticate', function () {
        test('authenticates successfully with valid credentials', function () {
            $config = MailWizzConfig::factory()->create([
                'api_url' => 'https://mailwizz.example.com',
                'public_key' => 'public-key',
                'private_key' => 'private-key',
            ]);

            Http::fake([
                function ($request) {
                    $url = (string) $request->url();
                    // Match the exact URL pattern used by MailWizzService
                    if ($request->method() === 'POST' &&
                        (str_contains($url, '/api/index.php/customer/api_keys/authenticate') ||
                         str_contains($url, '/authenticate') ||
                         str_contains($url, 'api_keys/authenticate'))) {
                        return Http::response([
                            'data' => [
                                'access_token' => 'test-access-token',
                            ],
                        ], 200);
                    }

                    // Default response for any other requests
                    return Http::response(['error' => 'Not found'], 404);
                },
            ]);

            $result = $this->service->authenticate($config);

            expect($result)->toBeTrue();
        });

        test('returns false on authentication failure', function () {
            $config = MailWizzConfig::factory()->create([
                'api_url' => 'https://mailwizz.example.com',
            ]);

            Http::fake([
                '*/*/authenticate' => Http::response(['error' => 'Invalid credentials'], 401),
            ]);

            $result = $this->service->authenticate($config);

            expect($result)->toBeFalse();
        });

        test('handles network errors', function () {
            $config = MailWizzConfig::factory()->create([
                'api_url' => 'https://invalid-domain-12345.com',
            ]);

            Http::fake([
                '*' => function () {
                    throw new \Exception('Connection failed');
                },
            ]);

            $result = $this->service->authenticate($config);

            expect($result)->toBeFalse();
        });
    });

    describe('getSubscribers', function () {
        test('fetches subscribers successfully', function () {
            $config = MailWizzConfig::factory()->create([
                'api_url' => 'https://mailwizz.example.com',
                'list_uid' => 'list-123',
            ]);

            Http::fake([
                '*/*/authenticate' => Http::response([
                    'data' => ['access_token' => 'token'],
                ], 200),
                '*/*/subscribers*' => Http::response([
                    'data' => [
                        'records' => [
                            ['email' => 'test1@example.com', 'subscriber_uid' => 'uid1'],
                            ['email' => 'test2@example.com', 'subscriber_uid' => 'uid2'],
                        ],
                    ],
                ], 200),
            ]);

            $subscribers = $this->service->getSubscribers($config, 1, 100);

            expect($subscribers)->toBeArray()
                ->and(count($subscribers))->toBe(2);
        });

        test('throws exception when authentication fails', function () {
            $config = MailWizzConfig::factory()->create();

            Http::fake([
                '*' => Http::response(['error' => 'Unauthorized'], 401),
            ]);

            expect(fn () => $this->service->getSubscribers($config))
                ->toThrow(Exception::class);
        });
    });

    describe('importLeads', function () {
        test('imports leads from MailWizz', function () {
            $callCenter = \App\Models\CallCenter::factory()->create();
            $config = MailWizzConfig::factory()->create([
                'call_center_id' => $callCenter->id,
                'list_uid' => 'list-123',
            ]);

            Http::fake([
                function ($request) {
                    $url = (string) $request->url();
                    // Match authentication endpoint
                    if (str_contains($url, '/api/index.php/customer/api_keys/authenticate') ||
                        str_contains($url, '/authenticate') ||
                        str_contains($url, 'api_keys/authenticate')) {
                        return Http::response([
                            'data' => ['access_token' => 'token'],
                        ], 200);
                    }
                    // Match subscribers endpoint - must match the exact URL pattern
                    // The URL is: {api_url}/api/index.php/lists/{list_uid}/subscribers
                    if (str_contains($url, '/api/index.php/lists/') && str_contains($url, '/subscribers')) {
                        return Http::response([
                            'data' => [
                                'records' => [
                                    [
                                        'email' => 'test@example.com',
                                        'subscriber_uid' => 'uid1',
                                        'FNAME' => 'John',
                                        'LNAME' => 'Doe',
                                    ],
                                ],
                            ],
                        ], 200);
                    }

                    return Http::response(['error' => 'Not found'], 404);
                },
            ]);

            $stats = $this->service->importLeads($config);

            expect($stats['imported'])->toBe(1)
                ->and(Lead::where('email', 'test@example.com')->where('source', 'leads_seo')->exists())->toBeTrue();
        });

        test('imports all fields from MailWizz subscriber', function () {
            $callCenter = \App\Models\CallCenter::factory()->create();
            $config = MailWizzConfig::factory()->create([
                'call_center_id' => $callCenter->id,
                'list_uid' => 'list-123',
            ]);

            Http::fake([
                function ($request) {
                    $url = (string) $request->url();
                    if (str_contains($url, '/api/index.php/customer/api_keys/authenticate') ||
                        str_contains($url, '/authenticate') ||
                        str_contains($url, 'api_keys/authenticate')) {
                        return Http::response([
                            'data' => ['access_token' => 'token'],
                        ], 200);
                    }
                    if (str_contains($url, '/api/index.php/lists/') && str_contains($url, '/subscribers')) {
                        return Http::response([
                            'data' => [
                                'records' => [
                                    [
                                        'email' => 'test@example.com',
                                        'subscriber_uid' => 'uid1',
                                        'FNAME' => 'John',
                                        'LNAME' => 'Doe',
                                        'PHONE' => '1234567890',
                                        'company' => 'Test Company',
                                        'city' => 'Paris',
                                        'country' => 'France',
                                        'custom_field_1' => 'Custom Value 1',
                                        'custom_field_2' => 'Custom Value 2',
                                        'clicked_url' => 'https://example.com',
                                        'clicked_at' => '2024-01-01 12:00:00',
                                    ],
                                ],
                            ],
                        ], 200);
                    }

                    return Http::response(['error' => 'Not found'], 404);
                },
            ]);

            $stats = $this->service->importLeads($config);

            $lead = Lead::where('email', 'test@example.com')->where('source', 'leads_seo')->first();

            expect($stats['imported'])->toBe(1)
                ->and($lead)->not->toBeNull()
                ->and($lead->data['mailwizz_subscriber_id'])->toBe('uid1')
                ->and($lead->data['first_name'])->toBe('John')
                ->and($lead->data['last_name'])->toBe('Doe')
                ->and($lead->data['phone'])->toBe('1234567890')
                ->and($lead->data['company'])->toBe('Test Company')
                ->and($lead->data['city'])->toBe('Paris')
                ->and($lead->data['country'])->toBe('France')
                ->and($lead->data['custom_field_1'])->toBe('Custom Value 1')
                ->and($lead->data['custom_field_2'])->toBe('Custom Value 2')
                ->and($lead->data['clicked_url'])->toBe('https://example.com')
                ->and($lead->data['clicked_at'])->toBe('2024-01-01 12:00:00')
                // Vérifier que les versions originales sont aussi présentes
                ->and($lead->data['mailwizz_FNAME'])->toBe('John')
                ->and($lead->data['mailwizz_LNAME'])->toBe('Doe')
                ->and($lead->data['mailwizz_PHONE'])->toBe('1234567890');
        });

        test('skips duplicate emails', function () {
            $config = MailWizzConfig::factory()->create();
            // Create lead with source other than 'form' to trigger skipped_duplicate
            // The default source is 'form', so we need to set it to something else
            $existingLead = Lead::factory()->create([
                'email' => 'existing@example.com',
                'source' => 'leads_seo', // Not 'form' so it will be counted as duplicate
            ]);

            Http::fake([
                '*/*/authenticate' => Http::response([
                    'data' => ['access_token' => 'token'],
                ], 200),
                '*/*/subscribers*' => Http::response([
                    'data' => [
                        'records' => [
                            [
                                'email' => 'existing@example.com',
                                'subscriber_uid' => 'uid1',
                            ],
                        ],
                    ],
                ], 200),
            ]);

            $stats = $this->service->importLeads($config);

            expect($stats['skipped_duplicate'])->toBe(1)
                ->and($stats['imported'])->toBe(0);
        });

        test('skips already imported MailWizz leads', function () {
            $config = MailWizzConfig::factory()->create();

            // Create an imported lead record to simulate already imported subscriber
            $lead = Lead::factory()->create([
                'email' => 'test@example.com',
                'source' => 'leads_seo',
                'score' => 50,
            ]);

            MailWizzImportedLead::factory()->create([
                'mailwizz_subscriber_id' => 'uid1',
                'lead_id' => $lead->id,
                'email' => 'test@example.com',
            ]);

            Http::fake([
                '*/*/authenticate' => Http::response([
                    'data' => ['access_token' => 'token'],
                ], 200),
                '*/*/subscribers*' => Http::response([
                    'data' => [
                        'records' => [
                            [
                                'email' => 'test@example.com',
                                'subscriber_uid' => 'uid1',
                            ],
                        ],
                    ],
                ], 200),
            ]);

            $stats = $this->service->importLeads($config);

            expect($stats['imported'])->toBe(0);
        });
    });
});
