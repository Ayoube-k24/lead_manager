<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

describe('WebhookService', function () {
    beforeEach(function () {
        $this->service = new WebhookService();
        Log::spy();
    });

    describe('dispatch', function () {
        test('dispatches webhook for matching event', function () {
            $webhook = Webhook::factory()->create([
                'is_active' => true,
                'events' => ['lead.created'],
                'url' => 'https://example.com/webhook',
            ]);

            Http::fake([
                'example.com/*' => Http::response(['success' => true], 200),
            ]);

            $this->service->dispatch('lead.created', ['lead_id' => 1]);

            Http::assertSent(function ($request) use ($webhook) {
                return $request->url() === $webhook->url
                    && $request->method() === 'POST';
            });
        });

        test('only dispatches to active webhooks', function () {
            $activeWebhook = Webhook::factory()->create([
                'is_active' => true,
                'events' => ['lead.created'],
            ]);
            $inactiveWebhook = Webhook::factory()->create([
                'is_active' => false,
                'events' => ['lead.created'],
            ]);

            Http::fake([
                '*' => Http::response(['success' => true], 200),
            ]);

            $this->service->dispatch('lead.created', ['lead_id' => 1]);

            Http::assertSentCount(1);
        });

        test('filters by form when provided', function () {
            $form = Form::factory()->create();
            $webhook1 = Webhook::factory()->create([
                'is_active' => true,
                'events' => ['lead.created'],
                'form_id' => $form->id,
            ]);
            $webhook2 = Webhook::factory()->create([
                'is_active' => true,
                'events' => ['lead.created'],
                'form_id' => null,
            ]);

            Http::fake([
                '*' => Http::response(['success' => true], 200),
            ]);

            $this->service->dispatch('lead.created', ['lead_id' => 1], $form);

            // Should dispatch to both: webhook1 (matches form) and webhook2 (no form filter)
            Http::assertSentCount(2);
        });

        test('filters by call center when provided', function () {
            $callCenter = CallCenter::factory()->create();
            $webhook1 = Webhook::factory()->create([
                'is_active' => true,
                'events' => ['lead.created'],
                'call_center_id' => $callCenter->id,
            ]);
            $webhook2 = Webhook::factory()->create([
                'is_active' => true,
                'events' => ['lead.created'],
                'call_center_id' => null,
            ]);

            Http::fake([
                '*' => Http::response(['success' => true], 200),
            ]);

            $this->service->dispatch('lead.created', ['lead_id' => 1], null, $callCenter);

            Http::assertSentCount(2);
        });
    });

    describe('signPayload', function () {
        test('signs payload with secret', function () {
            $payload = ['lead_id' => 1, 'status' => 'confirmed'];
            $secret = 'test-secret';

            $signed = $this->service->signPayload($payload, $secret);

            expect($signed)->toHaveKeys(['payload', 'timestamp', 'signature'])
                ->and($signed['payload'])->toBe($payload)
                ->and($signed['signature'])->toBeString();
        });

        test('generates valid signature', function () {
            $payload = ['test' => 'data'];
            $secret = 'secret-key';

            $signed = $this->service->signPayload($payload, $secret);

            $expectedSignature = hash_hmac(
                'sha256',
                json_encode($payload).$signed['timestamp'],
                $secret
            );

            expect($signed['signature'])->toBe($expectedSignature);
        });
    });

    describe('validateWebhook', function () {
        test('validates correct signature', function () {
            $webhook = Webhook::factory()->create(['secret' => 'test-secret']);
            $payload = ['test' => 'data'];
            $signed = $this->service->signPayload($payload, $webhook->secret);

            $isValid = $this->service->validateWebhook($webhook, $signed);

            expect($isValid)->toBeTrue();
        });

        test('rejects invalid signature', function () {
            $webhook = Webhook::factory()->create(['secret' => 'test-secret']);
            $payload = ['test' => 'data'];
            $signed = $this->service->signPayload($payload, 'wrong-secret');

            $isValid = $this->service->validateWebhook($webhook, $signed);

            expect($isValid)->toBeFalse();
        });

        test('rejects payload with missing fields', function () {
            $webhook = Webhook::factory()->create();

            $isValid = $this->service->validateWebhook($webhook, ['payload' => []]);

            expect($isValid)->toBeFalse();
        });
    });

    describe('testWebhook', function () {
        test('sends test webhook successfully', function () {
            $webhook = Webhook::factory()->create([
                'url' => 'https://example.com/webhook',
            ]);

            Http::fake([
                'example.com/*' => Http::response(['success' => true], 200),
            ]);

            $result = $this->service->testWebhook($webhook);

            expect($result['success'])->toBeTrue()
                ->and($result['status'])->toBe(200);
        });

        test('handles webhook failure', function () {
            $webhook = Webhook::factory()->create([
                'url' => 'https://example.com/webhook',
            ]);

            Http::fake([
                'example.com/*' => Http::response(['error' => 'Not found'], 404),
            ]);

            $result = $this->service->testWebhook($webhook);

            expect($result['success'])->toBeFalse()
                ->and($result['status'])->toBe(404);
        });

        test('handles connection errors', function () {
            $webhook = Webhook::factory()->create([
                'url' => 'https://invalid-domain-12345.com/webhook',
            ]);

            Http::fake([
                '*' => function () {
                    throw new \Exception('Connection failed');
                },
            ]);

            $result = $this->service->testWebhook($webhook);

            expect($result['success'])->toBeFalse()
                ->and($result)->toHaveKey('error');
        });
    });

    describe('retry logic', function () {
        test('retries on failure', function () {
            $webhook = Webhook::factory()->create([
                'url' => 'https://example.com/webhook',
            ]);

            Http::fake([
                'example.com/*' => Http::sequence()
                    ->push(['error' => 'Server error'], 500)
                    ->push(['error' => 'Server error'], 500)
                    ->push(['success' => true], 200),
            ]);

            $this->service->dispatch('lead.created', ['lead_id' => 1]);

            Http::assertSentCount(3);
        });

        test('stops retrying after max attempts', function () {
            $webhook = Webhook::factory()->create([
                'url' => 'https://example.com/webhook',
            ]);

            Http::fake([
                'example.com/*' => Http::response(['error' => 'Server error'], 500),
            ]);

            $this->service->dispatch('lead.created', ['lead_id' => 1]);

            // Should try 3 times (MAX_RETRIES)
            Http::assertSentCount(3);
        });
    });
});

