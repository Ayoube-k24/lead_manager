<?php

declare(strict_types=1);

use App\Events\LeadAssigned;
use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LeadDistributionService;
use Illuminate\Support\Facades\Event;
use function Pest\Laravel\mock;

describe('LeadDistributionService', function () {
    beforeEach(function () {
        $this->auditService = mock(AuditService::class);
        // Mock methods that may be called by the Auditable trait
        $this->auditService->shouldReceive('logAgentCreated')->zeroOrMoreTimes()->andReturn(\Mockery::mock(\App\Models\ActivityLog::class));
        $this->auditService->shouldReceive('logSmtpProfileCreated')->zeroOrMoreTimes()->andReturn(\Mockery::mock(\App\Models\ActivityLog::class));
        $this->auditService->shouldReceive('logEmailTemplateCreated')->zeroOrMoreTimes()->andReturn(\Mockery::mock(\App\Models\ActivityLog::class));
        $this->auditService->shouldReceive('logFormCreated')->zeroOrMoreTimes()->andReturn(\Mockery::mock(\App\Models\ActivityLog::class));
        $this->auditService->shouldReceive('log')->zeroOrMoreTimes()->andReturn(\Mockery::mock(\App\Models\ActivityLog::class));
        $this->service = new LeadDistributionService($this->auditService);
    });

    describe('distributeLead - Round Robin', function () {
        test('distributes lead to agent with least pending leads', function () {
            Event::fake();

            $callCenter = CallCenter::factory()->create([
                'distribution_method' => 'round_robin',
            ]);

            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent1 = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $agent2 = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $form = Form::factory()->create([
                'call_center_id' => $callCenter->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'status' => 'email_confirmed',
            ]);

            // Agent1 has 2 pending leads, Agent2 has 0
            Lead::factory()->count(2)->create([
                'assigned_to' => $agent1->id,
                'call_center_id' => $callCenter->id,
                'status' => 'pending_call',
            ]);

            $selectedAgent = $this->service->distributeLead($lead);

            expect($selectedAgent)->not->toBeNull()
                ->and($selectedAgent->id)->toBe($agent2->id);
        });

        test('distributes to agent with oldest last assignment when counts are equal', function () {
            Event::fake();

            $callCenter = CallCenter::factory()->create([
                'distribution_method' => 'round_robin',
            ]);

            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent1 = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $agent2 = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $form = Form::factory()->create([
                'call_center_id' => $callCenter->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'status' => 'email_confirmed',
            ]);

            // Both agents have 1 pending lead, but agent1 was assigned more recently
            Lead::factory()->create([
                'assigned_to' => $agent1->id,
                'call_center_id' => $callCenter->id,
                'status' => 'pending_call',
                'updated_at' => now(),
            ]);

            Lead::factory()->create([
                'assigned_to' => $agent2->id,
                'call_center_id' => $callCenter->id,
                'status' => 'pending_call',
                'updated_at' => now()->subHour(),
            ]);

            $selectedAgent = $this->service->distributeLead($lead);

            expect($selectedAgent)->not->toBeNull()
                ->and($selectedAgent->id)->toBe($agent2->id);
        });

        test('returns null when no active agents available', function () {
            $callCenter = CallCenter::factory()->create([
                'distribution_method' => 'round_robin',
            ]);

            $form = Form::factory()->create([
                'call_center_id' => $callCenter->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
            ]);

            $selectedAgent = $this->service->distributeLead($lead);

            expect($selectedAgent)->toBeNull();
        });

        test('returns null when lead has no call center', function () {
            $form = Form::factory()->create([
                'call_center_id' => null,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => null,
            ]);

            $selectedAgent = $this->service->distributeLead($lead);

            expect($selectedAgent)->toBeNull();
        });

        test('gets call center from form if not set on lead', function () {
            Event::fake();

            $callCenter = CallCenter::factory()->create([
                'distribution_method' => 'round_robin',
            ]);

            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $form = Form::factory()->create([
                'call_center_id' => $callCenter->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => null,
            ]);

            $selectedAgent = $this->service->distributeLead($lead);

            expect($selectedAgent)->not->toBeNull()
                ->and($lead->fresh()->call_center_id)->toBe($callCenter->id);
        });
    });

    describe('distributeLead - Weighted', function () {
        test('distributes to agent with lowest performance score', function () {
            Event::fake();

            $callCenter = CallCenter::factory()->create([
                'distribution_method' => 'weighted',
            ]);

            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent1 = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $agent2 = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $form = Form::factory()->create([
                'call_center_id' => $callCenter->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'status' => 'email_confirmed',
            ]);

            // Agent1 has high performance (5 confirmed out of 10)
            Lead::factory()->count(5)->create([
                'assigned_to' => $agent1->id,
                'call_center_id' => $callCenter->id,
                'status' => 'confirmed',
            ]);
            Lead::factory()->count(5)->create([
                'assigned_to' => $agent1->id,
                'call_center_id' => $callCenter->id,
                'status' => 'rejected',
            ]);

            // Agent2 has low performance (1 confirmed out of 10)
            Lead::factory()->count(1)->create([
                'assigned_to' => $agent2->id,
                'call_center_id' => $callCenter->id,
                'status' => 'confirmed',
            ]);
            Lead::factory()->count(9)->create([
                'assigned_to' => $agent2->id,
                'call_center_id' => $callCenter->id,
                'status' => 'rejected',
            ]);

            $selectedAgent = $this->service->distributeLead($lead);

            expect($selectedAgent)->not->toBeNull()
                ->and($selectedAgent->id)->toBe($agent2->id);
        });

        test('considers workload when distributing weighted', function () {
            Event::fake();

            $callCenter = CallCenter::factory()->create([
                'distribution_method' => 'weighted',
            ]);

            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent1 = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $agent2 = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $form = Form::factory()->create([
                'call_center_id' => $callCenter->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'status' => 'email_confirmed',
            ]);

            // Both agents have same performance, but agent1 has more pending leads
            Lead::factory()->count(5)->create([
                'assigned_to' => $agent1->id,
                'call_center_id' => $callCenter->id,
                'status' => 'pending_call',
            ]);

            $selectedAgent = $this->service->distributeLead($lead);

            expect($selectedAgent)->not->toBeNull()
                ->and($selectedAgent->id)->toBe($agent2->id);
        });
    });

    describe('distributeLead - Manual', function () {
        test('returns null for manual distribution method', function () {
            $callCenter = CallCenter::factory()->create([
                'distribution_method' => 'manual',
            ]);

            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $form = Form::factory()->create([
                'call_center_id' => $callCenter->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
            ]);

            $selectedAgent = $this->service->distributeLead($lead);

            expect($selectedAgent)->toBeNull();
        });
    });

    describe('assignToAgent', function () {
        test('assigns lead to agent successfully', function () {
            Event::fake();

            $callCenter = CallCenter::factory()->create();
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $lead = Lead::factory()->create([
                'call_center_id' => $callCenter->id,
            ]);

            $this->auditService->shouldReceive('logLeadAssigned')
                ->once()
                ->with(\Mockery::type(Lead::class), \Mockery::type(User::class))
                ->andReturn(\Mockery::mock(\App\Models\ActivityLog::class));

            $result = $this->service->assignToAgent($lead, $agent);

            expect($result)->toBeTrue()
                ->and($lead->fresh()->assigned_to)->toBe($agent->id);

            Event::assertDispatched(LeadAssigned::class);
        });

        test('fails when agent belongs to different call center', function () {
            $callCenter1 = CallCenter::factory()->create();
            $callCenter2 = CallCenter::factory()->create();
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter2->id,
                'is_active' => true,
            ]);

            $lead = Lead::factory()->create([
                'call_center_id' => $callCenter1->id,
                'assigned_to' => null, // Ensure lead is not pre-assigned
                'status' => 'pending_email', // Use status that doesn't trigger auto-distribution
            ]);

            // Refresh to ensure we have the latest state
            $lead->refresh();

            $result = $this->service->assignToAgent($lead, $agent);

            expect($result)->toBeFalse()
                ->and($lead->fresh()->assigned_to)->toBeNull();
        });

        test('fails when user is not an agent', function () {
            $callCenter = CallCenter::factory()->create();
            $role = Role::firstOrCreate(['slug' => 'supervisor'], ['name' => 'Supervisor']);

            $user = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $lead = Lead::factory()->create([
                'call_center_id' => $callCenter->id,
                'assigned_to' => null, // Ensure lead is not pre-assigned
            ]);

            $result = $this->service->assignToAgent($lead, $user);

            expect($result)->toBeFalse()
                ->and($lead->fresh()->assigned_to)->toBeNull();
        });

        test('fails when agent is inactive', function () {
            $callCenter = CallCenter::factory()->create();
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => false,
            ]);

            $lead = Lead::factory()->create([
                'call_center_id' => $callCenter->id,
                'assigned_to' => null, // Ensure lead is not pre-assigned
            ]);

            $result = $this->service->assignToAgent($lead, $agent);

            expect($result)->toBeFalse()
                ->and($lead->fresh()->assigned_to)->toBeNull();
        });

        test('sets call center from agent if lead has none', function () {
            Event::fake();

            $callCenter = CallCenter::factory()->create();
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

            $agent = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
                'is_active' => true,
            ]);

            $lead = Lead::factory()->create([
                'call_center_id' => null,
            ]);

            $this->auditService->shouldReceive('logLeadAssigned')
                ->once();

            $result = $this->service->assignToAgent($lead, $agent);

            expect($result)->toBeTrue()
                ->and($lead->fresh()->call_center_id)->toBe($callCenter->id);
        });
    });
});

