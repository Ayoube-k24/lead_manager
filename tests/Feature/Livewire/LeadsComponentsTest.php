<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;

describe('Leads Components', function () {
    test('owner leads list loads correctly', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $callCenter = CallCenter::factory()->create();
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Ensure user has callCenter relationship loaded
        $user->load('callCenter');

        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        Lead::factory()->count(5)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'score' => 50,
        ]);

        $response = $this->actingAs($user)->get(route('owner.leads'));
        $response->assertSuccessful();
    });

    test('agent leads list loads correctly', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $callCenter = CallCenter::factory()->create();
        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        Lead::factory()->count(3)->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'score' => 50,
        ]);

        $response = $this->actingAs($agent)->get(route('agent.leads'));
        $response->assertSuccessful();
    });

    test('agent lead show page loads correctly', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $callCenter = CallCenter::factory()->create();
        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'score' => 50,
        ]);

        $response = $this->actingAs($agent)->get(route('agent.leads.show', $lead));
        $response->assertSuccessful();
    });

    test('owner leads search works', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $callCenter = CallCenter::factory()->create();
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Ensure user has callCenter relationship loaded
        $user->load('callCenter');

        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'email' => 'test@example.com',
            'score' => 50,
        ]);

        $response = $this->actingAs($user)->get(route('owner.leads'));
        $response->assertSuccessful();
        $response->assertSee('test@example.com');
    });

    test('owner leads display correctly in manual distribution mode', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'manual']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        // Create unassigned leads that should be visible in manual mode
        $unassignedLead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
            'source' => 'form',
            'score' => 50,
        ]);

        $response = $this->actingAs($user)->get(route('owner.leads'));
        $response->assertSuccessful();

        // Should see the unassigned lead
        $response->assertSee($unassignedLead->email);

        // Should see the assign button (not auto button in manual mode)
        $response->assertSee(__('Assigner'));
        $response->assertDontSee(__('Auto'));
    });
});
