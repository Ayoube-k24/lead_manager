<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;

describe('TestLeadDistribution Command', function () {
    test('tests distribution for specific lead', function () {
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $role->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        $this->artisan('leads:test-distribution', ['lead_id' => $lead->id])
            ->assertSuccessful()
            ->expectsOutput("Testing Lead #{$lead->id}");
    });

    test('fails when lead does not exist', function () {
        $this->artisan('leads:test-distribution', ['lead_id' => 999])
            ->assertFailed()
            ->expectsOutput('Lead #999 not found');
    });

    test('tests distribution for multiple unassigned leads', function () {
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        User::factory()->create([
            'role_id' => $role->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        // Create leads without events to prevent observer from auto-assigning
        $leads = Lead::withoutEvents(function () use ($form, $callCenter) {
            return Lead::factory()->count(3)->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'status' => 'email_confirmed',
                'assigned_to' => null,
                'email_confirmed_at' => now(),
            ]);
        });

        $this->artisan('leads:test-distribution')
            ->assertSuccessful()
            ->expectsOutput('Found 3 leads to test');
    });

    test('shows message when no leads to test', function () {
        $this->artisan('leads:test-distribution')
            ->assertSuccessful()
            ->expectsOutput('No leads found to test distribution');
    });
});
