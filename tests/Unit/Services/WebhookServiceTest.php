<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->service = app(WebhookService::class);
    Http::fake();
    Log::spy();
});

test('can sign payload with secret', function () {
    $payload = ['event' => 'test', 'data' => ['id' => 1]];
    $secret = 'test-secret';

    $signed = $this->service->signPayload($payload, $secret);

    expect($signed)
        ->toHaveKeys(['payload', 'timestamp', 'signature'])
        ->and($signed['payload'])->toBe($payload)
        ->and($signed['timestamp'])->toBeInt()
        ->and($signed['signature'])->toBeString();
});

test('can validate webhook signature', function () {
    $webhook = Webhook::factory()->create(['secret' => 'test-secret']);
    $payload = ['event' => 'test', 'data' => ['id' => 1]];

    $signed = $this->service->signPayload($payload, $webhook->secret);

    expect($this->service->validateWebhook($webhook, $signed))->toBeTrue();
});

test('rejects invalid webhook signature', function () {
    $webhook = Webhook::factory()->create(['secret' => 'test-secret']);
    $invalidPayload = [
        'payload' => ['event' => 'test'],
        'timestamp' => now()->timestamp,
        'signature' => 'invalid-signature',
    ];

    expect($this->service->validateWebhook($webhook, $invalidPayload))->toBeFalse();
});

test('rejects webhook payload without required fields', function () {
    $webhook = Webhook::factory()->create();
    $invalidPayload = ['payload' => ['event' => 'test']];

    expect($this->service->validateWebhook($webhook, $invalidPayload))->toBeFalse();
});

test('dispatches webhook for active webhooks listening to event', function () {
    $form = Form::factory()->create();
    $webhook = Webhook::factory()->create([
        'form_id' => $form->id,
        'events' => ['lead.created'],
        'is_active' => true,
        'url' => 'https://example.com/webhook',
    ]);

    Http::fake([
        'example.com/*' => Http::response(['success' => true], 200),
    ]);

    $this->service->dispatch('lead.created', ['lead_id' => 1], $form);

    Http::assertSent(function ($request) use ($webhook) {
        return $request->url() === $webhook->url
            && $request->hasHeader('Content-Type')
            && isset($request->data()['signature']);
    });
});

test('does not dispatch webhook for inactive webhooks', function () {
    $webhook = Webhook::factory()->create([
        'events' => ['lead.created'],
        'is_active' => false,
    ]);

    $this->service->dispatch('lead.created', ['lead_id' => 1]);

    Http::assertNothingSent();
});

test('does not dispatch webhook for events not in webhook events list', function () {
    $webhook = Webhook::factory()->create([
        'events' => ['lead.updated'],
        'is_active' => true,
    ]);

    $this->service->dispatch('lead.created', ['lead_id' => 1]);

    Http::assertNothingSent();
});

test('filters webhooks by form when provided', function () {
    $form1 = Form::factory()->create();
    $form2 = Form::factory()->create();

    $webhook1 = Webhook::factory()->create([
        'form_id' => $form1->id,
        'events' => ['lead.created'],
        'is_active' => true,
        'url' => 'https://example.com/webhook1',
    ]);

    $webhook2 = Webhook::factory()->create([
        'form_id' => $form2->id,
        'events' => ['lead.created'],
        'is_active' => true,
        'url' => 'https://example.com/webhook2',
    ]);

    Http::fake();

    $this->service->dispatch('lead.created', ['lead_id' => 1], $form1);

    Http::assertSentCount(1);
    Http::assertSent(function ($request) use ($webhook1) {
        return $request->url() === $webhook1->url;
    });
});

test('filters webhooks by call center when provided', function () {
    $callCenter1 = CallCenter::factory()->create();
    $callCenter2 = CallCenter::factory()->create();

    $webhook1 = Webhook::factory()->create([
        'call_center_id' => $callCenter1->id,
        'events' => ['lead.created'],
        'is_active' => true,
        'url' => 'https://example.com/webhook1',
    ]);

    $webhook2 = Webhook::factory()->create([
        'call_center_id' => $callCenter2->id,
        'events' => ['lead.created'],
        'is_active' => true,
        'url' => 'https://example.com/webhook2',
    ]);

    Http::fake();

    $this->service->dispatch('lead.created', ['lead_id' => 1], null, $callCenter1);

    Http::assertSentCount(1);
    Http::assertSent(function ($request) use ($webhook1) {
        return $request->url() === $webhook1->url;
    });
});

test('retries webhook on failure with exponential backoff', function () {
    $webhook = Webhook::factory()->create([
        'events' => ['lead.created'],
        'is_active' => true,
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

test('logs webhook success', function () {
    $webhook = Webhook::factory()->create([
        'events' => ['lead.created'],
        'is_active' => true,
        'url' => 'https://example.com/webhook',
    ]);

    Http::fake([
        'example.com/*' => Http::response(['success' => true], 200),
    ]);

    $this->service->dispatch('lead.created', ['lead_id' => 1]);

    Log::shouldHaveReceived('info')
        ->with('Webhook dispatched successfully', \Mockery::type('array'));
});

test('logs webhook failure after max retries', function () {
    $webhook = Webhook::factory()->create([
        'events' => ['lead.created'],
        'is_active' => true,
        'url' => 'https://example.com/webhook',
    ]);

    Http::fake([
        'example.com/*' => Http::response(['error' => 'Server error'], 500),
    ]);

    $this->service->dispatch('lead.created', ['lead_id' => 1]);

    Log::shouldHaveReceived('error')
        ->with('Webhook dispatch failed after retries', \Mockery::type('array'));
});

test('can test webhook connection', function () {
    $webhook = Webhook::factory()->create([
        'url' => 'https://example.com/webhook',
    ]);

    Http::fake([
        'example.com/*' => Http::response(['success' => true], 200),
    ]);

    $result = $this->service->testWebhook($webhook);

    expect($result)
        ->toHaveKey('success')
        ->and($result['success'])->toBeTrue()
        ->and($result['status'])->toBe(200);
});

test('handles webhook test connection failure', function () {
    $webhook = Webhook::factory()->create([
        'url' => 'https://invalid-url.com/webhook',
    ]);

    Http::fake(function () {
        throw new \Exception('Connection timeout');
    });

    $result = $this->service->testWebhook($webhook);

    expect($result)
        ->toHaveKey('success')
        ->and($result['success'])->toBeFalse()
        ->and($result)->toHaveKey('error');
});
