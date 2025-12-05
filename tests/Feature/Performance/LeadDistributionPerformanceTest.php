<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadDistributionService;
use Illuminate\Support\Facades\DB;

describe('Lead Distribution Performance', function () {
    beforeEach(function () {
        $this->distributionService = app(LeadDistributionService::class);
    });

    test('distributes leads efficiently with many agents', function () {
        $callCenter = CallCenter::factory()->create([
            'distribution_method' => 'round_robin',
        ]);
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

        // Create 50 agents
        $agents = User::factory()->count(50)->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Create 1000 unassigned leads
        $leads = Lead::factory()->count(1000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
            'email_confirmed_at' => now(),
            'score' => 60,
        ]);

        $startTime = microtime(true);

        // Distribute all leads
        $distributed = 0;
        foreach ($leads as $lead) {
            $agent = $this->distributionService->distributeLead($lead);
            if ($agent) {
                $this->distributionService->assignToAgent($lead, $agent);
                $distributed++;
            }
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Should distribute efficiently (< 2000ms for 1000 leads)
        expect($executionTime)->toBeLessThan(2000.0)
            ->and($distributed)->toBeGreaterThan(0);
    });

    test('handles weighted distribution efficiently', function () {
        $callCenter = CallCenter::factory()->create([
            'distribution_method' => 'weighted',
        ]);
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

        // Create agents with different performance levels
        $agents = User::factory()->count(20)->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Create some leads already assigned to test workload calculation
        foreach ($agents->take(10) as $agent) {
            Lead::factory()->count(10)->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'assigned_to' => $agent->id,
                'status' => 'pending_call',
                'score' => 60,
            ]);
        }

        // Create new leads to distribute
        $leads = Lead::factory()->count(500)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
            'email_confirmed_at' => now(),
            'score' => 60,
        ]);

        $startTime = microtime(true);

        foreach ($leads as $lead) {
            $agent = $this->distributionService->distributeLead($lead);
            if ($agent) {
                $this->distributionService->assignToAgent($lead, $agent);
            }
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Weighted distribution should be efficient
        expect($executionTime)->toBeLessThan(3000.0);
    });

    test('calculates agent workload efficiently', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Create many leads assigned to agent
        Lead::factory()->count(2000)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'status' => 'pending_call',
            'score' => 60,
        ]);

        $startTime = microtime(true);

        // The distribution service calculates workload internally
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
            'email_confirmed_at' => now(),
            'score' => 60,
        ]);

        $this->distributionService->distributeLead($lead);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Workload calculation should be efficient
        expect($executionTime)->toBeLessThan(500.0);
    });
});

