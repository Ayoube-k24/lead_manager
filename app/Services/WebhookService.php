<?php

namespace App\Services;

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Webhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Maximum number of retry attempts.
     */
    protected const MAX_RETRIES = 3;

    /**
     * Base delay in seconds for exponential backoff.
     */
    protected const BASE_DELAY = 1;

    /**
     * Dispatch webhooks for a given event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload, ?Form $form = null, ?CallCenter $callCenter = null): void
    {
        $webhooks = $this->getWebhooksForEvent($event, $form, $callCenter);

        foreach ($webhooks as $webhook) {
            $this->sendWebhook($webhook, $event, $payload);
        }
    }

    /**
     * Get webhooks that should be triggered for an event.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Webhook>
     */
    protected function getWebhooksForEvent(string $event, ?Form $form = null, ?CallCenter $callCenter = null)
    {
        $query = Webhook::where('is_active', true)
            ->whereJsonContains('events', $event);

        // Filter by form if provided
        if ($form) {
            $query->where(function ($q) use ($form) {
                $q->whereNull('form_id')
                    ->orWhere('form_id', $form->id);
            });
        }

        // Filter by call center if provided
        if ($callCenter) {
            $query->where(function ($q) use ($callCenter) {
                $q->whereNull('call_center_id')
                    ->orWhere('call_center_id', $callCenter->id);
            });
        }

        return $query->get();
    }

    /**
     * Send a webhook with retry logic.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function sendWebhook(Webhook $webhook, string $event, array $payload): void
    {
        $signedPayload = $this->signPayload($payload, $webhook->secret);

        $attempt = 0;
        $success = false;

        while ($attempt < self::MAX_RETRIES && ! $success) {
            try {
                $response = Http::timeout(10)
                    ->post($webhook->url, $signedPayload);

                if ($response->successful()) {
                    $success = true;
                    $this->logWebhookSuccess($webhook, $event, $response->status());
                } else {
                    $attempt++;
                    if ($attempt < self::MAX_RETRIES) {
                        $this->logWebhookRetry($webhook, $event, $response->status(), $attempt);
                        sleep(self::BASE_DELAY * (2 ** ($attempt - 1))); // Exponential backoff
                    } else {
                        $this->logWebhookFailure($webhook, $event, $response->status());
                    }
                }
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt < self::MAX_RETRIES) {
                    $this->logWebhookRetry($webhook, $event, 0, $attempt, $e->getMessage());
                    sleep(self::BASE_DELAY * (2 ** ($attempt - 1))); // Exponential backoff
                } else {
                    $this->logWebhookFailure($webhook, $event, 0, $e->getMessage());
                }
            }
        }
    }

    /**
     * Sign a payload with the webhook secret.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function signPayload(array $payload, string $secret): array
    {
        $timestamp = now()->timestamp;
        $payloadString = json_encode($payload);
        $signature = hash_hmac('sha256', $payloadString.$timestamp, $secret);

        return [
            'payload' => $payload,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ];
    }

    /**
     * Validate webhook signature.
     *
     * @param  array<string, mixed>  $payload
     */
    public function validateWebhook(Webhook $webhook, array $payload): bool
    {
        if (! isset($payload['signature'], $payload['timestamp'], $payload['payload'])) {
            return false;
        }

        $expectedSignature = hash_hmac(
            'sha256',
            json_encode($payload['payload']).$payload['timestamp'],
            $webhook->secret
        );

        return hash_equals($expectedSignature, $payload['signature']);
    }

    /**
     * Test a webhook by sending a test payload.
     */
    public function testWebhook(Webhook $webhook): array
    {
        $testPayload = [
            'event' => 'webhook.test',
            'message' => 'This is a test webhook from Lead Manager',
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            $signedPayload = $this->signPayload($testPayload, $webhook->secret);
            $response = Http::timeout(10)->post($webhook->url, $signedPayload);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Log successful webhook dispatch.
     */
    protected function logWebhookSuccess(Webhook $webhook, string $event, int $status): void
    {
        Log::info('Webhook dispatched successfully', [
            'webhook_id' => $webhook->id,
            'webhook_name' => $webhook->name,
            'event' => $event,
            'status' => $status,
        ]);
    }

    /**
     * Log webhook retry attempt.
     */
    protected function logWebhookRetry(Webhook $webhook, string $event, int $status, int $attempt, ?string $error = null): void
    {
        Log::warning('Webhook retry attempt', [
            'webhook_id' => $webhook->id,
            'webhook_name' => $webhook->name,
            'event' => $event,
            'status' => $status,
            'attempt' => $attempt,
            'error' => $error,
        ]);
    }

    /**
     * Log webhook failure.
     */
    protected function logWebhookFailure(Webhook $webhook, string $event, int $status, ?string $error = null): void
    {
        Log::error('Webhook dispatch failed after retries', [
            'webhook_id' => $webhook->id,
            'webhook_name' => $webhook->name,
            'event' => $event,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
