<?php

declare(strict_types=1);

use App\Events\LeadAssigned;
use App\Events\LeadConverted;
use App\Events\LeadCreated;
use App\Events\LeadEmailConfirmed;
use App\Events\LeadStatusUpdated;
use App\Listeners\SendWebhookForLeadEvent;
use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Queue;

describe('SendWebhookForLeadEvent Listener', function () {
    beforeEach(function () {
        Queue::fake();
    });

    test('handles LeadCreated event', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
        ]);

        $event = new LeadCreated($lead);
        $webhookService = $this->mock(WebhookService::class);
        $webhookService->shouldReceive('dispatch')
            ->once()
            ->with(
                'lead.created',
                \Mockery::on(function ($payload) use ($lead) {
                    return $payload['lead_id'] === $lead->id
                        && $payload['lead_email'] === $lead->email
                        && $payload['lead_status'] === $lead->status
                        && isset($payload['form_id'])
                        && isset($payload['call_center_id'])
                        && isset($payload['created_at']);
                }),
                \Mockery::type(Form::class),
                \Mockery::type(CallCenter::class)
            );

        $listener = new SendWebhookForLeadEvent($webhookService);
        $listener->handle($event);
    });

    test('handles LeadEmailConfirmed event', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'email_confirmed_at' => now(),
        ]);

        $event = new LeadEmailConfirmed($lead);
        $webhookService = $this->mock(WebhookService::class);
        $webhookService->shouldReceive('dispatch')
            ->once()
            ->with(
                'lead.email_confirmed',
                \Mockery::on(function ($payload) use ($lead) {
                    return $payload['lead_id'] === $lead->id
                        && $payload['lead_email'] === $lead->email
                        && $payload['lead_status'] === $lead->status;
                }),
                \Mockery::type(Form::class),
                \Mockery::type(CallCenter::class)
            );

        $listener = new SendWebhookForLeadEvent($webhookService);
        $listener->handle($event);
    });

    test('handles LeadAssigned event with agent information', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $role->id,
            'call_center_id' => $callCenter->id,
        ]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
        ]);

        $event = new LeadAssigned($lead, $agent);
        $webhookService = $this->mock(WebhookService::class);
        $webhookService->shouldReceive('dispatch')
            ->once()
            ->with(
                'lead.assigned',
                \Mockery::on(function ($payload) use ($lead, $agent) {
                    return $payload['lead_id'] === $lead->id
                        && $payload['agent_id'] === $agent->id
                        && $payload['agent_name'] === $agent->name
                        && $payload['agent_email'] === $agent->email
                        && isset($payload['lead_email'])
                        && isset($payload['lead_status']);
                }),
                \Mockery::type(Form::class),
                \Mockery::type(CallCenter::class)
            );

        $listener = new SendWebhookForLeadEvent($webhookService);
        $listener->handle($event);
    });

    test('handles LeadStatusUpdated event with old and new status', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'confirmed',
        ]);

        $oldStatus = 'pending_call';
        $newStatus = 'confirmed';
        $event = new LeadStatusUpdated($lead, $oldStatus, $newStatus);
        $webhookService = $this->mock(WebhookService::class);
        $webhookService->shouldReceive('dispatch')
            ->once()
            ->with(
                'lead.status_updated',
                \Mockery::on(function ($payload) use ($lead, $oldStatus, $newStatus) {
                    return $payload['lead_id'] === $lead->id
                        && $payload['old_status'] === $oldStatus
                        && $payload['new_status'] === $newStatus
                        && isset($payload['lead_email'])
                        && isset($payload['lead_status']);
                }),
                \Mockery::type(Form::class),
                \Mockery::type(CallCenter::class)
            );

        $listener = new SendWebhookForLeadEvent($webhookService);
        $listener->handle($event);
    });

    test('handles LeadConverted event', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'converted',
        ]);

        $event = new LeadConverted($lead);
        $webhookService = $this->mock(WebhookService::class);
        $webhookService->shouldReceive('dispatch')
            ->once()
            ->with(
                'lead.converted',
                \Mockery::on(function ($payload) use ($lead) {
                    return $payload['lead_id'] === $lead->id
                        && $payload['lead_email'] === $lead->email
                        && $payload['lead_status'] === $lead->status;
                }),
                \Mockery::type(Form::class),
                \Mockery::type(CallCenter::class)
            );

        $listener = new SendWebhookForLeadEvent($webhookService);
        $listener->handle($event);
    });

    test('handles events with null form and call center', function () {
        $lead = Lead::factory()->create([
            'form_id' => null,
            'call_center_id' => null,
        ]);

        $event = new LeadCreated($lead);
        $webhookService = $this->mock(WebhookService::class);
        $webhookService->shouldReceive('dispatch')
            ->once()
            ->with(
                'lead.created',
                \Mockery::type('array'),
                null,
                null
            );

        $listener = new SendWebhookForLeadEvent($webhookService);
        $listener->handle($event);
    });

    test('returns unknown event name for unsupported event types', function () {
        $lead = Lead::factory()->create();
        $unsupportedEvent = new class($lead)
        {
            public function __construct(public $lead) {}
        };

        $webhookService = $this->mock(WebhookService::class);
        $webhookService->shouldReceive('dispatch')
            ->once()
            ->with(
                'lead.unknown',
                \Mockery::type('array'),
                \Mockery::any(),
                \Mockery::any()
            );

        $listener = new SendWebhookForLeadEvent($webhookService);
        $listener->handle($unsupportedEvent);
    });

    test('includes lead data in payload', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'data' => ['custom_field' => 'custom_value'],
        ]);

        $event = new LeadCreated($lead);
        $webhookService = $this->mock(WebhookService::class);
        $webhookService->shouldReceive('dispatch')
            ->once()
            ->with(
                'lead.created',
                \Mockery::on(function ($payload) {
                    return isset($payload['lead_data'])
                        && $payload['lead_data']['custom_field'] === 'custom_value';
                }),
                \Mockery::type(Form::class),
                \Mockery::type(CallCenter::class)
            );

        $listener = new SendWebhookForLeadEvent($webhookService);
        $listener->handle($event);
    });

    test('is queued', function () {
        expect(new SendWebhookForLeadEvent(app(WebhookService::class)))
            ->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });
});
