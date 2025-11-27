<?php

namespace App\Listeners;

use App\Events\LeadAssigned;
use App\Events\LeadConverted;
use App\Events\LeadCreated;
use App\Events\LeadEmailConfirmed;
use App\Events\LeadStatusUpdated;
use App\Services\WebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendWebhookForLeadEvent implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $eventName = $this->getEventName($event);
        $payload = $this->buildPayload($event);
        $form = $event->lead->form ?? null;
        $callCenter = $event->lead->callCenter ?? null;

        $this->webhookService->dispatch($eventName, $payload, $form, $callCenter);
    }

    /**
     * Get the event name for webhook.
     */
    protected function getEventName(object $event): string
    {
        return match (true) {
            $event instanceof LeadCreated => 'lead.created',
            $event instanceof LeadEmailConfirmed => 'lead.email_confirmed',
            $event instanceof LeadAssigned => 'lead.assigned',
            $event instanceof LeadStatusUpdated => 'lead.status_updated',
            $event instanceof LeadConverted => 'lead.converted',
            default => 'lead.unknown',
        };
    }

    /**
     * Build the payload for the webhook.
     *
     * @return array<string, mixed>
     */
    protected function buildPayload(object $event): array
    {
        $basePayload = [
            'lead_id' => $event->lead->id,
            'lead_email' => $event->lead->email,
            'lead_status' => $event->lead->status,
            'lead_data' => $event->lead->data,
            'form_id' => $event->lead->form_id,
            'call_center_id' => $event->lead->call_center_id,
            'created_at' => $event->lead->created_at->toIso8601String(),
        ];

        return match (true) {
            $event instanceof LeadAssigned => array_merge($basePayload, [
                'agent_id' => $event->agent->id,
                'agent_name' => $event->agent->name,
                'agent_email' => $event->agent->email,
            ]),
            $event instanceof LeadStatusUpdated => array_merge($basePayload, [
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
            ]),
            default => $basePayload,
        };
    }
}
